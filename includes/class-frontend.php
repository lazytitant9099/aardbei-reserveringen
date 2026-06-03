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

		// Render in wp_footer to avoid stacking context issues with theme transforms.
		add_action( 'wp_footer', array( $this, '_render_popup_in_footer' ), 10 );

		return '';
	}

	/**
	 * Footer callback voor popup HTML (via shortcode).
	 */
	public function _render_popup_in_footer() {
		echo $this->get_popup_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		if ( empty( $_GET['aardbei_cancel'] ) && empty( $_POST['aardbei_confirm_cancel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Handle POST confirmation submission
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['aardbei_confirm_cancel'] ) ) {
			if ( ! isset( $_POST['aardbei_cancel_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aardbei_cancel_nonce'] ), 'aardbei_confirm_cancel_action' ) ) {
				$this->render_cancel_page( __( 'Ongeldige beveiligingscontrole.', 'aardbei-reserveringen' ), __( 'Ongeldige beveiligingscontrole.', 'aardbei-reserveringen' ), false, '', 403 );
			}

			$token        = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			$reservations = new Aardbei_Reserveringen_Reservations();
			$result       = $reservations->cancel_reservation_by_token( $token );

			if ( is_wp_error( $result ) ) {
				$this->render_cancel_page( __( 'Annuleren mislukt', 'aardbei-reserveringen' ), $result->get_error_message(), false, '', 400 );
			} else {
				$this->render_cancel_page( __( 'Reservering geannuleerd', 'aardbei-reserveringen' ), __( 'Je reservering is succesvol geannuleerd.', 'aardbei-reserveringen' ), false, '', 200 );
			}
		}

		// Handle GET confirmation page load
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $token ) ) {
			$this->render_cancel_page( __( 'Annuleren mislukt', 'aardbei-reserveringen' ), __( 'Deze annuleringslink is ongeldig.', 'aardbei-reserveringen' ), false, '', 400 );
		}

		global $wpdb;
		$table       = Aardbei_Reserveringen_Database::get_reservations_table();
		$reservation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE cancel_token = %s", $token ),
			ARRAY_A
		);

		if ( ! $reservation ) {
			$this->render_cancel_page( __( 'Annuleren mislukt', 'aardbei-reserveringen' ), __( 'Deze annuleringslink is ongeldig of de reservering bestaat niet.', 'aardbei-reserveringen' ), false, '', 404 );
		}

		if ( 'cancelled' === $reservation['status'] ) {
			$this->render_cancel_page( __( 'Al geannuleerd', 'aardbei-reserveringen' ), __( 'Deze reservering is al geannuleerd.', 'aardbei-reserveringen' ), false, '', 200 );
		}

		$slots_table = Aardbei_Reserveringen_Database::get_slots_table();
		$slot        = $wpdb->get_row(
			$wpdb->prepare( "SELECT date, start_time, end_time FROM {$slots_table} WHERE id = %d", $reservation['slot_id'] ),
			ARRAY_A
		);

		$date_str = '';
		if ( $slot ) {
			$date_str = date_i18n( 'l j F Y', strtotime( $slot['date'] ) ) . ' (' . substr( $slot['start_time'], 0, 5 ) . ')';
		}

		$message = sprintf(
			/* translators: 1: name, 2: persons, 3: date/time */
			__( 'Beste %1$s, weet je zeker dat je de reservering voor %2$d perso(o)n(en) op %3$s wilt annuleren?', 'aardbei-reserveringen' ),
			esc_html( $reservation['name'] ),
			(int) $reservation['persons'],
			esc_html( $date_str )
		);

		$this->render_cancel_page( __( 'Reservering annuleren', 'aardbei-reserveringen' ), $message, true, $token, 200 );
	}

	/**
	 * Output een volledige HTML-pagina voor de annuleringsstroom en stop de uitvoering.
	 *
	 * @param string $title     Paginatitel.
	 * @param string $message   Berichttekst.
	 * @param bool   $show_form Toon bevestigingsformulier.
	 * @param string $token     Annuleringstoken.
	 * @param int    $status    HTTP-statuscode.
	 */
	private function render_cancel_page( $title, $message, $show_form = false, $token = '', $status = 200 ) {
		status_header( $status );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-store, no-cache' );
		echo $this->get_cancel_page_html( $title, $message, $show_form, $token ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Render a beautiful, premium cancel confirmation/result page.
	 *
	 * @param string $title Page title.
	 * @param string $message Page content.
	 * @param bool   $show_form Whether to show the confirmation form.
	 * @param string $token The cancel token (if showing form).
	 * @return string
	 */
	private function get_cancel_page_html( $title, $message, $show_form = false, $token = '' ) {
		$bg_color    = Aardbei_Reserveringen_Settings::get_setting( 'popup_button_bg_color', '#cf2e2e' );
		$hover_color = Aardbei_Reserveringen_Settings::get_setting( 'popup_button_hover_color', '#a82424' );
		$site_name   = get_bloginfo( 'name' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title . ( $site_name ? ' – ' . $site_name : '' ) ); ?></title>
<style>
*,*::before,*::after{box-sizing:border-box}
body{background:#f7f9fa;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1e293b}
.aardbei-cancel-container{width:100%;max-width:500px;padding:24px}
.aardbei-cancel-card{background:#fff;border-radius:16px;box-shadow:0 10px 25px -5px rgba(0,0,0,.05),0 8px 10px -6px rgba(0,0,0,.05);border:1px solid #f1f5f9;padding:40px 32px;text-align:center}
.aardbei-cancel-icon{font-size:48px;margin-bottom:20px;line-height:1}
.aardbei-cancel-card h1{font-size:24px;font-weight:700;margin:0 0 16px;color:#0f172a;letter-spacing:-.025em}
.aardbei-cancel-msg{font-size:16px;line-height:1.6;color:#475569;margin:0 0 32px}
.aardbei-cancel-form{display:flex;flex-direction:column;gap:12px}
.aardbei-btn{display:inline-flex;align-items:center;justify-content:center;padding:14px 24px;font-size:16px;font-weight:600;border-radius:10px;border:none;cursor:pointer;transition:all .2s ease;text-decoration:none;width:100%}
.aardbei-btn-primary{background-color:<?php echo esc_attr( $bg_color ); ?>;color:#fff}
.aardbei-btn-primary:hover{background-color:<?php echo esc_attr( $hover_color ); ?>;transform:translateY(-1px)}
.aardbei-btn-secondary{background-color:#f1f5f9;color:#475569}
.aardbei-btn-secondary:hover{background-color:#e2e8f0;color:#1e293b}
</style>
</head>
<body>
<div class="aardbei-cancel-container">
	<div class="aardbei-cancel-card">
		<div class="aardbei-cancel-icon">🍓</div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<div class="aardbei-cancel-msg"><?php echo esc_html( $message ); ?></div>

		<?php if ( $show_form ) : ?>
		<form method="POST" action="" class="aardbei-cancel-form">
			<?php wp_nonce_field( 'aardbei_confirm_cancel_action', 'aardbei_cancel_nonce' ); ?>
			<input type="hidden" name="aardbei_cancel" value="1">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="aardbei_confirm_cancel" value="1">
			<button type="submit" class="aardbei-btn aardbei-btn-primary">
				<?php echo esc_html__( 'Ja, annuleer mijn reservering', 'aardbei-reserveringen' ); ?>
			</button>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="aardbei-btn aardbei-btn-secondary">
				<?php echo esc_html__( 'Nee, ga terug', 'aardbei-reserveringen' ); ?>
			</a>
		</form>
		<?php else : ?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="aardbei-btn aardbei-btn-secondary">
			<?php echo esc_html__( 'Terug naar de website', 'aardbei-reserveringen' ); ?>
		</a>
		<?php endif; ?>
	</div>
</div>
</body>
</html>
		<?php
		return ob_get_clean();
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
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'aardbei_frontend_nonce' ),
				'showRemainingCapacity'  => (bool) Aardbei_Reserveringen_Settings::get_setting( 'show_remaining_capacity', 1 ),
				'contactEmail'           => Aardbei_Reserveringen_Settings::get_setting( 'admin_email', get_option( 'admin_email' ) ),
				'maxPersonsForContact'   => (int) Aardbei_Reserveringen_Settings::get_setting( 'max_persons_for_contact', 9 ),
				'i18n'                  => array(
					'chooseSlot'      => __( 'Kies eerst een pluktijd.', 'aardbei-reserveringen' ),
					'loadingError'    => __( 'De kalender kon niet worden geladen.', 'aardbei-reserveringen' ),
					'noSlots'         => __( 'Er zijn op dit moment geen pluktijden beschikbaar. Nieuwe tijden komen binnenkort online.', 'aardbei-reserveringen' ),
					'reservationDone' => __( 'Je reservering is ontvangen. Je ontvangt zo een bevestiging per e-mail.', 'aardbei-reserveringen' ),
					'addToCalendar'   => __( 'Toevoegen aan agenda', 'aardbei-reserveringen' ),
					'googleCalendar'  => __( 'Google Agenda', 'aardbei-reserveringen' ),
					'downloadIcs'     => __( 'iCal / Apple Agenda (.ics)', 'aardbei-reserveringen' ),
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
		$started_at   = (string) current_time( 'timestamp' );
		$spam_random  = wp_generate_password( 12, false, false );
		$spam_token   = wp_hash( $started_at . '|' . $spam_random . '|aardbei_reservation_form' );

		ob_start();
		?>
		<div class="aardbei-reservation-widget <?php echo $compact ? 'aardbei-reservation-widget--compact' : ''; ?>" id="<?php echo esc_attr( $calendar_id ); ?>" data-aardbei-widget>
			<?php if ( ! $compact ) : ?>
				<div class="aardbei-widget-header">
					<h2><?php echo esc_html( $title ); ?></h2>
				</div>
			<?php endif; ?>

			<ol class="aardbei-steps" aria-label="<?php echo esc_attr__( 'Reserveringsstappen', 'aardbei-reserveringen' ); ?>" data-aardbei-steps>
				<li class="aardbei-step is-active" data-aardbei-step="1"><span class="aardbei-step-num">1</span><span class="aardbei-step-label"><?php echo esc_html__( 'Gasten', 'aardbei-reserveringen' ); ?></span></li>
				<li class="aardbei-step" data-aardbei-step="2"><span class="aardbei-step-num">2</span><span class="aardbei-step-label"><?php echo esc_html__( 'Datum & Tijd', 'aardbei-reserveringen' ); ?></span></li>
				<li class="aardbei-step" data-aardbei-step="3"><span class="aardbei-step-num">3</span><span class="aardbei-step-label"><?php echo esc_html__( 'Gegevens', 'aardbei-reserveringen' ); ?></span></li>
			</ol>

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

			<div class="aardbei-contact-bar" data-aardbei-contact-bar hidden>
				&#x2709;&#xFE0F; <span data-aardbei-contact-msg></span>
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
				<input type="hidden" name="aardbei_started_at" value="<?php echo esc_attr( $started_at ); ?>">
				<input type="hidden" name="aardbei_spam_random" value="<?php echo esc_attr( $spam_random ); ?>">
				<input type="hidden" name="aardbei_spam_token" value="<?php echo esc_attr( $spam_token ); ?>">
				<div class="aardbei-honeypot" aria-hidden="true">
					<label>
						<?php echo esc_html__( 'Website', 'aardbei-reserveringen' ); ?>
						<input type="text" name="website" value="" tabindex="-1" autocomplete="off">
					</label>
				</div>
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
			<p class="aardbei-powered-by">
				<span><?php echo esc_html__( 'Powered by', 'aardbei-reserveringen' ); ?></span>
				<a href="<?php echo esc_url( AARDBEI_MODERN_VISUALS_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( AARDBEI_MODERN_VISUALS_LOGO ); ?>" alt="<?php echo esc_attr__( 'Modern Visuals', 'aardbei-reserveringen' ); ?>" loading="lazy">
					<strong><?php echo esc_html__( 'ModernVisuals', 'aardbei-reserveringen' ); ?></strong>
				</a>
			</p>
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
