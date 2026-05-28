<?php
/**
 * Frontend output.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes en publieke flows.
 */
class Aardbei_Reserveringen_Frontend {

	/**
	 * Voorkomt dubbele popup-output wanneer ook de shortcode wordt gebruikt.
	 *
	 * @var bool
	 */
	private $popup_rendered = false;

	/**
	 * Voorkomt dubbele wp_localize_script/wp_add_inline_style output.
	 *
	 * @var bool
	 */
	private $assets_configured = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'aardbei_reserveren', array( $this, 'render_reservation_shortcode' ) );
		add_shortcode( 'aardbei_reserveren_popup', array( $this, 'render_popup_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_cancel_request' ) );
		add_action( 'wp_footer', array( $this, 'render_automatic_popup' ), 10 );
	}

	/**
	 * Registreer frontend assets.
	 */
	public function register_assets() {
		wp_register_style(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css',
			array(),
			'5.11.5'
		);
		wp_register_style(
			'aardbei-frontend',
			AARDBEI_RESERVERINGEN_PLUGIN_URL . 'public/css/frontend.css',
			array(),
			AARDBEI_RESERVERINGEN_VERSION
		);
		wp_register_script(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js',
			array(),
			'5.11.5',
			true
		);
		wp_register_script(
			'aardbei-frontend-calendar',
			AARDBEI_RESERVERINGEN_PLUGIN_URL . 'public/js/frontend-calendar.js',
			array(),
			AARDBEI_RESERVERINGEN_VERSION,
			true
		);
		wp_register_script(
			'aardbei-popup',
			AARDBEI_RESERVERINGEN_PLUGIN_URL . 'public/js/popup.js',
			array( 'aardbei-frontend-calendar' ),
			AARDBEI_RESERVERINGEN_VERSION,
			true
		);

		$should_enqueue = Aardbei_Reserveringen_Settings::get_setting( 'popup_enabled', 1 );
		if ( ! $should_enqueue && is_singular() ) {
			global $post;
			$content        = isset( $post->post_content ) ? $post->post_content : '';
			$should_enqueue = has_shortcode( $content, 'aardbei_reserveren' ) || has_shortcode( $content, 'aardbei_reserveren_popup' );
		}

		if ( $should_enqueue ) {
			$this->enqueue_frontend_assets();
			wp_enqueue_script( 'aardbei-popup' );
		}
	}

	/**
	 * Shortcode: [aardbei_reserveren].
	 *
	 * @return string
	 */
	public function render_reservation_shortcode() {
		$this->enqueue_frontend_assets();
		$this->popup_rendered = true;

		return $this->get_reservation_widget_html( 'aardbei-frontend-calendar', false );
	}

	/**
	 * Shortcode: [aardbei_reserveren_popup].
	 *
	 * @return string
	 */
	public function render_popup_shortcode() {
		if ( ! Aardbei_Reserveringen_Settings::get_setting( 'popup_enabled', 1 ) ) {
			return '';
		}

		$this->enqueue_frontend_assets();
		wp_enqueue_script( 'aardbei-popup' );
		$this->popup_rendered = true;

		return $this->get_popup_html();
	}

	/**
	 * Toon automatisch de popup rechtsonder zonder shortcode.
	 */
	public function render_automatic_popup() {
		if ( $this->popup_rendered || is_admin() || is_feed() || is_embed() ) {
			return;
		}

		if ( ! Aardbei_Reserveringen_Settings::get_setting( 'popup_enabled', 1 ) ) {
			return;
		}

		$this->enqueue_frontend_assets();
		wp_enqueue_script( 'aardbei-popup' );
		$this->popup_rendered = true;

		echo $this->get_popup_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Popup HTML.
	 *
	 * @return string
	 */
	private function get_popup_html() {
		$button_text = Aardbei_Reserveringen_Settings::get_setting( 'popup_button_text', '🍓 Reserveer je pluktijd' );
		$title       = Aardbei_Reserveringen_Settings::get_setting( 'frontend_widget_title', get_bloginfo( 'name' ) );

		ob_start();
		?>
		<button type="button" class="aardbei-popup-button" data-aardbei-popup-open>
			<?php echo esc_html( $button_text ); ?>
		</button>
		<div class="aardbei-popup-drawer" data-aardbei-popup-drawer aria-hidden="true">
			<div class="aardbei-popup-header">
				<h2><?php echo esc_html( $title ); ?></h2>
				<button type="button" class="aardbei-popup-close" data-aardbei-popup-close aria-label="<?php echo esc_attr__( 'Sluiten', 'aardbei-reserveringen' ); ?>">×</button>
			</div>
			<?php echo $this->get_reservation_widget_html( 'aardbei-popup-picker', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="aardbei-popup-backdrop" data-aardbei-popup-close></div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Annuleren via mailtoken.
	 */
	public function handle_cancel_request() {
		if ( empty( $_GET['aardbei_cancel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token        = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reservations = new Aardbei_Reserveringen_Reservations();
		$result       = $reservations->cancel_reservation_by_token( $token );

		if ( is_wp_error( $result ) ) {
			$title   = __( 'Annuleren mislukt', 'aardbei-reserveringen' );
			$message = $result->get_error_message();
			$status  = 400;
		} else {
			$title   = __( 'Reservering geannuleerd', 'aardbei-reserveringen' );
			$message = __( 'Je reservering is geannuleerd.', 'aardbei-reserveringen' );
			$status  = 200;
		}

		$html = '<div class="aardbei-cancel-page" style="max-width:680px;margin:48px auto;font-family:system-ui,sans-serif;line-height:1.5;">';
		$html .= '<h1>' . esc_html( $title ) . '</h1>';
		$html .= '<p>' . esc_html( $message ) . '</p>';
		$html .= '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Terug naar de website', 'aardbei-reserveringen' ) . '</a></p>';
		$html .= '</div>';

		wp_die( wp_kses_post( $html ), esc_html( $title ), array( 'response' => $status ) );
	}

	/**
	 * Laad assets wanneer shortcode gebruikt wordt.
	 */
	private function enqueue_frontend_assets() {
		wp_enqueue_style( 'aardbei-frontend' );
		wp_enqueue_script( 'aardbei-frontend-calendar' );

		if ( $this->assets_configured ) {
			return;
		}

		$this->assets_configured = true;

		wp_add_inline_style( 'aardbei-frontend', $this->get_frontend_custom_css() );
		wp_localize_script(
			'aardbei-frontend-calendar',
			'aardbeiFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aardbei_frontend_nonce' ),
				'i18n'    => array(
					'chooseSlot'      => __( 'Kies eerst een pluktijd.', 'aardbei-reserveringen' ),
					'loadingError'    => __( 'De kalender kon niet worden geladen.', 'aardbei-reserveringen' ),
					'noSlots'         => __( 'Er zijn op dit moment geen pluktijden beschikbaar. Nieuwe tijden komen binnenkort online.', 'aardbei-reserveringen' ),
					'reservationDone' => __( 'Je reservering is ontvangen. Je ontvangt zo een bevestiging per e-mail.', 'aardbei-reserveringen' ),
				),
			)
		);
	}

	/**
	 * CSS vanuit backend instellingen.
	 *
	 * @return string
	 */
	private function get_frontend_custom_css() {
		$bg_color    = Aardbei_Reserveringen_Settings::get_setting( 'popup_button_bg_color', '#cf2e2e' );
		$text_color  = Aardbei_Reserveringen_Settings::get_setting( 'popup_button_text_color', '#ffffff' );
		$hover_color = Aardbei_Reserveringen_Settings::get_setting( 'popup_button_hover_color', '#a82424' );
		$action_bg   = Aardbei_Reserveringen_Settings::get_setting( 'frontend_action_bg_color', '#171717' );
		$action_text = Aardbei_Reserveringen_Settings::get_setting( 'frontend_action_text_color', '#ffffff' );
		$widget_width = absint( Aardbei_Reserveringen_Settings::get_setting( 'frontend_widget_width', 420 ) );
		$widget_width = min( 560, max( 360, $widget_width ) );

		return sprintf(
			':root{--aardbei-popup-button-bg:%1$s;--aardbei-popup-button-text:%2$s;--aardbei-popup-button-hover:%3$s;--aardbei-action-bg:%4$s;--aardbei-action-text:%5$s;--aardbei-widget-width:%6$dpx;}',
			esc_attr( $bg_color ),
			esc_attr( $text_color ),
			esc_attr( $hover_color ),
			esc_attr( $action_bg ),
			esc_attr( $action_text ),
			$widget_width
		);
	}

	/**
	 * HTML voor reserveringswidget.
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param bool   $compact     Compacte popup variant.
	 * @return string
	 */
	private function get_reservation_widget_html( $calendar_id, $compact ) {
		$settings     = Aardbei_Reserveringen_Settings::get_settings();
		$title        = $settings['frontend_widget_title'];
		$button_text  = $settings['frontend_reserve_button_text'];
		$card_title   = $settings['frontend_card_title'];
		$card_subtitle = $settings['frontend_card_subtitle'];
		$image_url    = $settings['frontend_card_image_url'];
		$powered_by   = $settings['frontend_powered_by_text'];

		ob_start();
		?>
		<div class="aardbei-reservation-widget <?php echo $compact ? 'aardbei-reservation-widget--compact' : ''; ?>" id="<?php echo esc_attr( $calendar_id ); ?>" data-aardbei-widget>
			<?php if ( ! $compact ) : ?>
				<div class="aardbei-widget-header">
					<h2><?php echo esc_html( $title ); ?></h2>
				</div>
			<?php endif; ?>

			<div class="aardbei-booking-selectors">
				<button type="button" class="aardbei-selector-row" data-aardbei-panel-toggle="guests">
					<span class="aardbei-selector-icon" aria-hidden="true"><?php echo $this->get_icon_svg( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="aardbei-selector-value" data-aardbei-guests-label>2 gasten</span>
					<span class="aardbei-selector-chevron" aria-hidden="true"><?php echo $this->get_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
				<div class="aardbei-selector-panel" data-aardbei-panel="guests" hidden>
					<div class="aardbei-stepper">
						<button type="button" data-aardbei-persons-minus aria-label="<?php echo esc_attr__( 'Minder personen', 'aardbei-reserveringen' ); ?>">−</button>
						<input type="number" min="1" step="1" value="2" data-aardbei-persons-display>
						<button type="button" data-aardbei-persons-plus aria-label="<?php echo esc_attr__( 'Meer personen', 'aardbei-reserveringen' ); ?>">+</button>
					</div>
				</div>

				<button type="button" class="aardbei-selector-row" data-aardbei-panel-toggle="date">
					<span class="aardbei-selector-icon" aria-hidden="true"><?php echo $this->get_icon_svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="aardbei-selector-value" data-aardbei-date-label><?php echo esc_html__( 'Kies een datum', 'aardbei-reserveringen' ); ?></span>
					<span class="aardbei-selector-chevron" aria-hidden="true"><?php echo $this->get_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
				<div class="aardbei-selector-panel" data-aardbei-panel="date" hidden>
					<div class="aardbei-month-picker">
						<div class="aardbei-month-header">
							<button type="button" data-aardbei-prev-month aria-label="<?php echo esc_attr__( 'Vorige maand', 'aardbei-reserveringen' ); ?>">‹</button>
							<strong data-aardbei-month-label></strong>
							<button type="button" data-aardbei-next-month aria-label="<?php echo esc_attr__( 'Volgende maand', 'aardbei-reserveringen' ); ?>">›</button>
						</div>
						<div class="aardbei-weekdays" aria-hidden="true">
							<span>ma</span><span>di</span><span>wo</span><span>do</span><span>vr</span><span>za</span><span>zo</span>
						</div>
						<div class="aardbei-days-grid" data-aardbei-days-grid></div>
					</div>
				</div>

				<button type="button" class="aardbei-selector-row" data-aardbei-panel-toggle="time">
					<span class="aardbei-selector-icon" aria-hidden="true"><?php echo $this->get_icon_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="aardbei-selector-value" data-aardbei-time-label><?php echo esc_html__( 'Kies een tijd', 'aardbei-reserveringen' ); ?></span>
					<span class="aardbei-selector-chevron" aria-hidden="true"><?php echo $this->get_icon_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
				<div class="aardbei-selector-panel" data-aardbei-panel="time" hidden>
					<div class="aardbei-time-grid" data-aardbei-time-grid></div>
				</div>
			</div>

			<button type="button" class="aardbei-reserve-open-button" data-aardbei-open-form>
				<?php echo esc_html( $button_text ); ?>
			</button>

			<div class="aardbei-selection-heading">
				<span></span>
				<strong><?php echo esc_html__( 'Beschikbaar voor jouw selectie', 'aardbei-reserveringen' ); ?></strong>
				<span></span>
			</div>

			<div class="aardbei-availability-card" data-aardbei-availability-card>
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $card_title ); ?>">
				<?php else : ?>
					<div class="aardbei-card-placeholder" aria-hidden="true"></div>
				<?php endif; ?>
				<div class="aardbei-availability-copy">
					<h3><?php echo esc_html( $card_title ); ?></h3>
					<p><?php echo esc_html( $card_subtitle ); ?></p>
				</div>
			</div>

			<form class="aardbei-reservation-form" data-aardbei-form hidden>
				<input type="hidden" name="slot_id" data-aardbei-slot-id value="">
				<input type="hidden" name="persons" data-aardbei-persons-input value="2">
				<div class="aardbei-selected-slot" data-aardbei-selected-slot hidden></div>
				<div class="aardbei-form-grid">
					<label>
						<?php echo esc_html__( 'Naam', 'aardbei-reserveringen' ); ?>
						<input type="text" name="name" required>
					</label>
					<label>
						<?php echo esc_html__( 'E-mailadres', 'aardbei-reserveringen' ); ?>
						<input type="email" name="email" required>
					</label>
					<label>
						<?php echo esc_html__( 'Telefoonnummer', 'aardbei-reserveringen' ); ?>
						<input type="text" name="phone" required>
					</label>
					<label class="aardbei-form-full">
						<?php echo esc_html__( 'Opmerking, optioneel', 'aardbei-reserveringen' ); ?>
						<textarea name="note" rows="3"></textarea>
					</label>
				</div>
				<p class="aardbei-payment-note"><?php echo esc_html__( 'Betaling vindt plaats op locatie.', 'aardbei-reserveringen' ); ?></p>
				<button type="submit" class="aardbei-submit-button">
					<?php echo esc_html__( 'Reservering plaatsen', 'aardbei-reserveringen' ); ?>
				</button>
				<div class="aardbei-message" data-aardbei-message aria-live="polite"></div>
			</form>

			<div class="aardbei-message" data-aardbei-message-main aria-live="polite"></div>
			<?php if ( '' !== $powered_by ) : ?>
				<p class="aardbei-powered-by">
					<?php echo esc_html__( 'Powered by', 'aardbei-reserveringen' ); ?> <strong><?php echo esc_html( $powered_by ); ?></strong>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Kleine inline iconen voor de Guestplan-achtige frontend.
	 *
	 * @param string $icon Icon key.
	 * @return string
	 */
	private function get_icon_svg( $icon ) {
		$icons = array(
			'user'     => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>',
			'calendar' => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></svg>',
			'clock'    => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
			'chevron'  => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
		);

		return isset( $icons[ $icon ] ) ? $icons[ $icon ] : '';
	}
}
