<?php
/**
 * Settings view.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Aardbei_Reserveringen_Settings::get_settings();
$weekdays = Aardbei_Reserveringen_Admin::get_weekday_options();
?>
<div class="wrap aardbei-admin-wrap">
	<h1><?php echo esc_html__( 'Instellingen', 'aardbei-reserveringen' ); ?></h1>

	<div class="aardbei-tabs-nav" id="aardbei-tabs-nav">
		<button type="button" class="aardbei-tab-btn is-active" data-tab="booking"><?php echo esc_html__( 'Boekingsvenster', 'aardbei-reserveringen' ); ?></button>
		<button type="button" class="aardbei-tab-btn" data-tab="email"><?php echo esc_html__( 'E-mail', 'aardbei-reserveringen' ); ?></button>
		<button type="button" class="aardbei-tab-btn" data-tab="frontend"><?php echo esc_html__( 'Frontend', 'aardbei-reserveringen' ); ?></button>
		<button type="button" class="aardbei-tab-btn" data-tab="popup"><?php echo esc_html__( 'Popup', 'aardbei-reserveringen' ); ?></button>
		<button type="button" class="aardbei-tab-btn" data-tab="updates"><?php echo esc_html__( 'Updates', 'aardbei-reserveringen' ); ?></button>
	</div>

	<!-- Tab: Boekingsvenster -->
	<div class="aardbei-tab-panel is-active" id="aardbei-tab-booking">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aardbei_save_settings">
			<?php wp_nonce_field( 'aardbei_save_settings' ); ?>
			<input type="hidden" name="_tab" value="booking">

			<div class="aardbei-settings-section">
				<div class="aardbei-settings-section-header"><h3><?php echo esc_html__( 'Boekingsvenster', 'aardbei-reserveringen' ); ?></h3></div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Boekingen openen op dag', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Op welke weekdag worden boekingen voor de volgende week vrijgegeven?', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<select name="opening_weekday">
							<?php foreach ( $weekdays as $number => $label ) : ?>
								<option value="<?php echo esc_attr( $number ); ?>" <?php selected( (int) $settings['opening_weekday'], $number ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Boekingen openen om', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Hoe laat worden de nieuwe tijdsloten zichtbaar?', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<input type="time" name="opening_time" value="<?php echo esc_attr( $settings['opening_time'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Aantal weken vooruit boekbaar', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Hoeveel weken vooruit kunnen bezoekers een pluktijd reserveren? (1–8)', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<input type="number" min="1" max="8" name="bookable_weeks" value="<?php echo esc_attr( $settings['bookable_weeks'] ); ?>" style="max-width:80px;">
					</div>
				</div>
			</div>

			<div class="aardbei-settings-footer">
				<button type="submit" class="aardbei-btn aardbei-btn--primary">
					<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
					<?php echo esc_html__( 'Opslaan', 'aardbei-reserveringen' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Tab: E-mail -->
	<div class="aardbei-tab-panel" id="aardbei-tab-email">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aardbei_save_settings">
			<?php wp_nonce_field( 'aardbei_save_settings' ); ?>
			<input type="hidden" name="_tab" value="email">

			<div class="aardbei-settings-section">
				<div class="aardbei-settings-section-header"><h3><?php echo esc_html__( 'E-mailinstellingen', 'aardbei-reserveringen' ); ?></h3></div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Beheerder e-mailadres', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Hier ontvang je meldingen van nieuwe reserveringen.', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<input type="email" name="admin_email" value="<?php echo esc_attr( $settings['admin_email'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Onderwerp bevestiging klant', 'aardbei-reserveringen' ); ?>
					</div>
					<div class="aardbei-settings-control">
						<input type="text" name="customer_mail_subject" value="<?php echo esc_attr( $settings['customer_mail_subject'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Onderwerp melding beheerder', 'aardbei-reserveringen' ); ?>
					</div>
					<div class="aardbei-settings-control">
						<input type="text" name="admin_mail_subject" value="<?php echo esc_attr( $settings['admin_mail_subject'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Mail bij annulering', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Stuur een melding bij klantannulering.', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<label class="checkbox-row">
							<input type="checkbox" name="cancellation_mail_admin" value="1" <?php checked( (int) $settings['cancellation_mail_admin'], 1 ); ?>>
							<?php echo esc_html__( 'Beheerder informeren bij annulering', 'aardbei-reserveringen' ); ?>
						</label>
					</div>
				</div>
			</div>

			<div class="aardbei-settings-footer">
				<button type="submit" class="aardbei-btn aardbei-btn--primary">
					<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
					<?php echo esc_html__( 'Opslaan', 'aardbei-reserveringen' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Tab: Frontend -->
	<div class="aardbei-tab-panel" id="aardbei-tab-frontend">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aardbei_save_settings">
			<?php wp_nonce_field( 'aardbei_save_settings' ); ?>
			<input type="hidden" name="_tab" value="frontend">

			<div class="aardbei-settings-section">
				<div class="aardbei-settings-section-header"><h3><?php echo esc_html__( 'Widget', 'aardbei-reserveringen' ); ?></h3></div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Widget titel', 'aardbei-reserveringen' ); ?>
					</div>
					<div class="aardbei-settings-control">
						<input type="text" name="frontend_widget_title" value="<?php echo esc_attr( $settings['frontend_widget_title'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Widget breedte (px)', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Aanbevolen: 420. Bereik: 360–560.', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<input type="number" min="360" max="560" step="10" name="frontend_widget_width" value="<?php echo esc_attr( $settings['frontend_widget_width'] ); ?>" style="max-width:100px;">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Resterende capaciteit tonen', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Toont het aantal vrije plekken bij elk tijdslot.', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<label class="checkbox-row">
							<input type="checkbox" name="show_remaining_capacity" value="1" <?php checked( (int) $settings['show_remaining_capacity'], 1 ); ?>>
							<?php echo esc_html__( 'Toon resterende capaciteit', 'aardbei-reserveringen' ); ?>
						</label>
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Reserveringsknop tekst', 'aardbei-reserveringen' ); ?>
					</div>
					<div class="aardbei-settings-control">
						<input type="text" name="frontend_reserve_button_text" value="<?php echo esc_attr( $settings['frontend_reserve_button_text'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Knopkleur', 'aardbei-reserveringen' ); ?>
					</div>
					<div class="aardbei-settings-control" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
						<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#64748b;">
							<?php echo esc_html__( 'Achtergrond', 'aardbei-reserveringen' ); ?>
							<input type="color" name="frontend_action_bg_color" value="<?php echo esc_attr( $settings['frontend_action_bg_color'] ); ?>" class="aardbei-color-preview-input" data-target="preview-action">
						</label>
						<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#64748b;">
							<?php echo esc_html__( 'Tekst', 'aardbei-reserveringen' ); ?>
							<input type="color" name="frontend_action_text_color" value="<?php echo esc_attr( $settings['frontend_action_text_color'] ); ?>" class="aardbei-color-preview-input" data-target="preview-action">
						</label>
						<div class="aardbei-color-preview-btn" id="preview-action"
							style="background:<?php echo esc_attr( $settings['frontend_action_bg_color'] ); ?>;color:<?php echo esc_attr( $settings['frontend_action_text_color'] ); ?>;"
							data-bg="frontend_action_bg_color" data-text="frontend_action_text_color">
							<?php echo esc_html( $settings['frontend_reserve_button_text'] ?: __( 'Reserveren', 'aardbei-reserveringen' ) ); ?>
						</div>
					</div>
				</div>
			</div>

			<div class="aardbei-settings-section">
				<div class="aardbei-settings-section-header"><h3><?php echo esc_html__( 'Beschikbaarheidskaart', 'aardbei-reserveringen' ); ?></h3></div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label"><?php echo esc_html__( 'Titel', 'aardbei-reserveringen' ); ?></div>
					<div class="aardbei-settings-control">
						<input type="text" name="frontend_card_title" value="<?php echo esc_attr( $settings['frontend_card_title'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label"><?php echo esc_html__( 'Subtitel', 'aardbei-reserveringen' ); ?></div>
					<div class="aardbei-settings-control">
						<input type="text" name="frontend_card_subtitle" value="<?php echo esc_attr( $settings['frontend_card_subtitle'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Afbeelding URL', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Plak de URL van een afbeelding uit de mediabibliotheek.', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<input type="url" name="frontend_card_image_url" value="<?php echo esc_url( $settings['frontend_card_image_url'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label"><?php echo esc_html__( '"Powered by" tekst', 'aardbei-reserveringen' ); ?></div>
					<div class="aardbei-settings-control">
						<input type="text" name="frontend_powered_by_text" value="<?php echo esc_attr( $settings['frontend_powered_by_text'] ); ?>">
					</div>
				</div>
			</div>

			<div class="aardbei-settings-footer">
				<button type="submit" class="aardbei-btn aardbei-btn--primary">
					<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
					<?php echo esc_html__( 'Opslaan', 'aardbei-reserveringen' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Tab: Popup -->
	<div class="aardbei-tab-panel" id="aardbei-tab-popup">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aardbei_save_settings">
			<?php wp_nonce_field( 'aardbei_save_settings' ); ?>
			<input type="hidden" name="_tab" value="popup">

			<div class="aardbei-settings-section">
				<div class="aardbei-settings-section-header"><h3><?php echo esc_html__( 'Popup-knop', 'aardbei-reserveringen' ); ?></h3></div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label">
						<?php echo esc_html__( 'Popup inschakelen', 'aardbei-reserveringen' ); ?>
						<span class="desc"><?php echo esc_html__( 'Toont automatisch rechtsonder op alle pagina\'s. De shortcode [aardbei_reserveren_popup] werkt ook los hiervan.', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-settings-control">
						<label class="checkbox-row">
							<input type="checkbox" name="popup_enabled" value="1" <?php checked( (int) $settings['popup_enabled'], 1 ); ?>>
							<?php echo esc_html__( 'Popup inschakelen', 'aardbei-reserveringen' ); ?>
						</label>
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label"><?php echo esc_html__( 'Knoptekst', 'aardbei-reserveringen' ); ?></div>
					<div class="aardbei-settings-control">
						<input type="text" name="popup_button_text" value="<?php echo esc_attr( $settings['popup_button_text'] ); ?>">
					</div>
				</div>

				<div class="aardbei-settings-row">
					<div class="aardbei-settings-label"><?php echo esc_html__( 'Kleuren', 'aardbei-reserveringen' ); ?></div>
					<div class="aardbei-settings-control" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
						<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#64748b;">
							<?php echo esc_html__( 'Achtergrond', 'aardbei-reserveringen' ); ?>
							<input type="color" name="popup_button_bg_color" value="<?php echo esc_attr( $settings['popup_button_bg_color'] ); ?>" class="aardbei-color-preview-input" data-target="preview-popup">
						</label>
						<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#64748b;">
							<?php echo esc_html__( 'Tekst', 'aardbei-reserveringen' ); ?>
							<input type="color" name="popup_button_text_color" value="<?php echo esc_attr( $settings['popup_button_text_color'] ); ?>" class="aardbei-color-preview-input" data-target="preview-popup">
						</label>
						<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#64748b;">
							<?php echo esc_html__( 'Hover', 'aardbei-reserveringen' ); ?>
							<input type="color" name="popup_button_hover_color" value="<?php echo esc_attr( $settings['popup_button_hover_color'] ); ?>">
						</label>
						<div class="aardbei-color-preview-btn" id="preview-popup"
							style="background:<?php echo esc_attr( $settings['popup_button_bg_color'] ); ?>;color:<?php echo esc_attr( $settings['popup_button_text_color'] ); ?>;border-radius:999px;"
							data-bg="popup_button_bg_color" data-text="popup_button_text_color">
							<?php echo esc_html( $settings['popup_button_text'] ?: __( 'Reserveer nu', 'aardbei-reserveringen' ) ); ?>
						</div>
					</div>
				</div>
			</div>

			<div class="aardbei-settings-footer">
				<button type="submit" class="aardbei-btn aardbei-btn--primary">
					<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
					<?php echo esc_html__( 'Opslaan', 'aardbei-reserveringen' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Tab: Updates -->
	<div class="aardbei-tab-panel" id="aardbei-tab-updates">
		<?php
		global $aardbei_updater;
		$update_status = $aardbei_updater instanceof Aardbei_Reserveringen_Updater
			? $aardbei_updater->get_update_status()
			: array( 'status' => 'pending', 'message' => __( 'Updater niet beschikbaar.', 'aardbei-reserveringen' ) );
		?>

		<!-- Status indicator -->
		<div class="aardbei-update-status aardbei-update-status--<?php echo esc_attr( $update_status['status'] ); ?>">
			<div class="aardbei-update-status-left">
				<?php if ( 'connected' === $update_status['status'] && empty( $update_status['update_available'] ) ) : ?>
					<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				<?php elseif ( 'connected' === $update_status['status'] && ! empty( $update_status['update_available'] ) ) : ?>
					<svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
				<?php elseif ( 'error' === $update_status['status'] ) : ?>
					<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
				<?php else : ?>
					<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<?php endif; ?>
				<div class="aardbei-update-status-text">
					<strong>
					<?php
					if ( 'connected' === $update_status['status'] ) {
						echo esc_html( ! empty( $update_status['update_available'] )
							? __( 'Update beschikbaar', 'aardbei-reserveringen' )
							: __( 'Je gebruikt de nieuwste versie', 'aardbei-reserveringen' )
						);
					} elseif ( 'pending' === $update_status['status'] ) {
						echo esc_html__( 'Nog niet gecontroleerd', 'aardbei-reserveringen' );
					} else {
						echo esc_html__( 'Verbindingsfout', 'aardbei-reserveringen' );
					}
					?>
					</strong>
					<span><?php echo esc_html( $update_status['message'] ); ?></span>
					<?php if ( 'connected' === $update_status['status'] ) : ?>
						<span style="font-size:11px;color:#94a3b8;">
							<?php echo esc_html( sprintf( __( 'Huidig: v%s', 'aardbei-reserveringen' ), $update_status['current_version'] ) ); ?>
							<?php if ( ! empty( $update_status['latest_version'] ) ) : ?>
								&nbsp;·&nbsp;
								<?php echo esc_html( sprintf( __( 'Nieuwste: v%s', 'aardbei-reserveringen' ), $update_status['latest_version'] ) ); ?>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</div>
			</div>
			<button type="button" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm" id="aardbei-check-updates"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'aardbei_admin_nonce' ) ); ?>">
				<svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
				<?php echo esc_html__( 'Nu controleren', 'aardbei-reserveringen' ); ?>
			</button>
		</div>

		<div class="aardbei-settings-section" style="margin-top:16px;">
			<div class="aardbei-settings-section-header"><h3><?php echo esc_html__( 'Nieuwe versie uitbrengen', 'aardbei-reserveringen' ); ?></h3></div>
			<div style="padding:16px 20px;font-size:13px;color:#475569;line-height:1.7;">
				<p style="margin:0 0 10px;"><?php echo esc_html__( 'Om een update beschikbaar te maken via het WordPress-dashboard:', 'aardbei-reserveringen' ); ?></p>
				<ol style="margin:0;padding-left:20px;">
					<li><?php echo esc_html__( 'Bump de versie in aardbei-reserveringen.php én in aardbei-updates/info.json', 'aardbei-reserveringen' ); ?></li>
					<li><?php echo esc_html__( 'Commit & push naar GitHub', 'aardbei-reserveringen' ); ?></li>
					<li><?php echo esc_html__( 'Maak een tag aan — GitHub Actions bouwt automatisch de ZIP en release', 'aardbei-reserveringen' ); ?></li>
				</ol>
				<p style="margin:10px 0 0;"><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;">git tag v1.4.0 &amp;&amp; git push origin v1.4.0</code></p>
				<p style="margin:10px 0 0;color:#94a3b8;font-size:12px;">
					<?php echo esc_html__( 'Update-informatie wordt opgehaald van:', 'aardbei-reserveringen' ); ?>
					<code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo esc_html( AARDBEI_UPDATE_URL ); ?></code>
				</p>
			</div>
		</div>
	</div>
</div>
