<?php
/**
 * Mail handling.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verstuurt pluginmails.
 */
class Aardbei_Reserveringen_Mailer {

	/**
	 * Klantbevestiging.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return bool
	 */
	public function send_customer_confirmation( $reservation_id ) {
		$reservation = $this->get_reservation_with_slot( $reservation_id );
		if ( ! $reservation ) {
			return false;
		}

		$subject    = Aardbei_Reserveringen_Settings::get_setting( 'customer_mail_subject', __( 'Je reservering voor aardbeien plukken', 'aardbei-reserveringen' ) );
		$cancel_url = $this->get_cancel_url( $reservation['cancel_token'] );
		$site_name  = get_bloginfo( 'name' );

		$rows = array(
			array( __( 'Reserveringsnr.', 'aardbei-reserveringen' ), '#' . absint( $reservation_id ) ),
			array( __( 'Datum', 'aardbei-reserveringen' ), $this->format_date( $reservation['date'] ) ),
			array( __( 'Tijd', 'aardbei-reserveringen' ), $this->format_time( $reservation['start_time'] ) ),
			array( __( 'Personen', 'aardbei-reserveringen' ), (int) $reservation['persons'] ),
			array( __( 'Naam', 'aardbei-reserveringen' ), $reservation['name'] ),
		);

		$rows_html = '';
		foreach ( $rows as $row ) {
			$rows_html .= '<tr>'
				. '<td style="padding:8px 16px;color:#64748b;font-size:13px;white-space:nowrap;border-bottom:1px solid #f1f5f9;">' . esc_html( $row[0] ) . '</td>'
				. '<td style="padding:8px 16px;font-size:13px;font-weight:600;border-bottom:1px solid #f1f5f9;">' . esc_html( $row[1] ) . '</td>'
				. '</tr>';
		}

		$message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f8fafc;font-family:system-ui,-apple-system,sans-serif;">'
			. '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 0;">'
			. '<tr><td align="center">'
			. '<table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">'
			. '<tr><td style="background:#171717;padding:24px 32px;">'
			. '<p style="margin:0;font-size:20px;font-weight:700;color:#ffffff;">' . esc_html( $site_name ) . '</p>'
			. '</td></tr>'
			. '<tr><td style="padding:32px 32px 8px;">'
			. '<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#0f172a;">' . esc_html__( 'Reservering bevestigd!', 'aardbei-reserveringen' ) . '</h1>'
			. '<p style="margin:0;color:#475569;font-size:14px;">' . sprintf( esc_html__( 'Hallo %s, je pluktijd staat ingepland.', 'aardbei-reserveringen' ), esc_html( $reservation['name'] ) ) . '</p>'
			. '</td></tr>'
			. '<tr><td style="padding:16px 32px;">'
			. '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
			. $rows_html
			. '</table>'
			. '</td></tr>'
			. '<tr><td style="padding:8px 32px 32px;">'
			. '<p style="margin:0 0 16px;color:#475569;font-size:13px;">' . esc_html__( 'Betaling vindt plaats op locatie.', 'aardbei-reserveringen' ) . '</p>'
			. '<a href="' . esc_url( $cancel_url ) . '" style="display:inline-block;padding:10px 20px;background:#fee2e2;color:#991b1b;font-size:13px;font-weight:600;border-radius:6px;text-decoration:none;">'
			. esc_html__( 'Reservering annuleren', 'aardbei-reserveringen' )
			. '</a>'
			. '</td></tr>'
			. '<tr><td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;">'
			. '<p style="margin:0;font-size:11px;color:#94a3b8;">' . esc_html( $site_name ) . '</p>'
			. '</td></tr>'
			. '</table>'
			. '</td></tr></table>'
			. '</body></html>';

		return $this->send_mail( $reservation['email'], $subject, $message, array(), true );
	}

	/**
	 * Admin notificatie nieuwe reservering.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return bool
	 */
	public function send_admin_notification( $reservation_id ) {
		$reservation = $this->get_reservation_with_slot( $reservation_id );
		if ( ! $reservation ) {
			return false;
		}

		$to      = Aardbei_Reserveringen_Settings::get_setting( 'admin_email', get_option( 'admin_email' ) );
		$subject = Aardbei_Reserveringen_Settings::get_setting( 'admin_mail_subject', __( 'Nieuwe reservering voor aardbeien plukken', 'aardbei-reserveringen' ) );

		$rows = array(
			array( 'Reserveringsnr.', '#' . absint( $reservation_id ) ),
			array( 'Datum', $this->format_date( $reservation['date'] ) ),
			array( 'Tijd', $this->format_time( $reservation['start_time'] ) ),
			array( 'Naam', $reservation['name'] ),
			array( 'E-mail', $reservation['email'] ),
			array( 'Telefoon', $reservation['phone'] ),
			array( 'Personen', (int) $reservation['persons'] ),
			array( 'Opmerking', $reservation['note'] ?: '—' ),
		);

		$rows_html = '';
		foreach ( $rows as $row ) {
			$rows_html .= '<tr>'
				. '<td style="padding:6px 12px;color:#64748b;font-size:13px;white-space:nowrap;border-bottom:1px solid #f1f5f9;">' . esc_html( $row[0] ) . '</td>'
				. '<td style="padding:6px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;">' . esc_html( $row[1] ) . '</td>'
				. '</tr>';
		}

		$message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:system-ui,sans-serif;padding:24px;background:#f8fafc;">'
			. '<div style="max-width:480px;background:#fff;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.08);">'
			. '<h2 style="margin:0 0 16px;font-size:17px;color:#0f172a;">' . esc_html__( 'Nieuwe reservering', 'aardbei-reserveringen' ) . '</h2>'
			. '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">'
			. $rows_html
			. '</table>'
			. '</div>'
			. '</body></html>';

		return $this->send_mail( $to, $subject, $message, array(), true );
	}

	/**
	 * Admin notificatie annulering.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return bool
	 */
	public function send_admin_cancellation_notification( $reservation_id ) {
		$reservation = $this->get_reservation_with_slot( $reservation_id );
		if ( ! $reservation ) {
			return false;
		}

		$to      = Aardbei_Reserveringen_Settings::get_setting( 'admin_email', get_option( 'admin_email' ) );
		$subject = __( 'Reservering geannuleerd', 'aardbei-reserveringen' );
		$message = sprintf(
			"Reservering geannuleerd:\n\nNr.: #%d\nDatum: %s\nTijd: %s\nNaam: %s\nE-mail: %s\nPersonen: %d",
			absint( $reservation_id ),
			$this->format_date( $reservation['date'] ),
			$this->format_time( $reservation['start_time'] ),
			$reservation['name'],
			$reservation['email'],
			(int) $reservation['persons']
		);

		return $this->send_mail( $to, $subject, $message );
	}

	/**
	 * Verstuur een testmail naar het opgegeven adres.
	 *
	 * @param string $to Ontvanger.
	 * @return bool
	 */
	public function send_test_mail( $to ) {
		delete_transient( 'aardbei_mail_error' );

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( __( 'Testmail van %s', 'aardbei-reserveringen' ), $site_name );
		$message   = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:system-ui,sans-serif;padding:24px;background:#f8fafc;">'
			. '<div style="max-width:480px;background:#fff;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.08);">'
			. '<h2 style="margin:0 0 12px;font-size:17px;color:#0f172a;">' . esc_html__( 'Testmail', 'aardbei-reserveringen' ) . '</h2>'
			. '<p style="margin:0;color:#475569;font-size:14px;">' . sprintf( esc_html__( 'Dit is een testmail van %s. Als je dit ontvangt werkt de e-mailfunctie correct.', 'aardbei-reserveringen' ), esc_html( $site_name ) ) . '</p>'
			. '</div></body></html>';

		return $this->send_mail( $to, $subject, $message, array(), true );
	}

	/**
	 * Sla mailfout op in een transient voor foutdiagnose.
	 *
	 * @param WP_Error $wp_error Foutobject van wp_mail.
	 */
	public function log_mail_error( $wp_error ) {
		$message = $wp_error->get_error_message();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Aardbei Reserveringen] Mail error: ' . $message );
		set_transient( 'aardbei_mail_error', $message, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * HTML content-type filter callback.
	 *
	 * @return string
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Afzendernaam voor pluginmails.
	 *
	 * @return string
	 */
	public function set_from_name( $from_name = '' ) {
		return $this->get_from_name();
	}

	/**
	 * Afzenderadres voor pluginmails.
	 * Gebruik het WordPress admin-adres zodat het overeenkomt met de SPF-configuratie op de server.
	 *
	 * @param string $from_email Huidig afzenderadres.
	 * @return string
	 */
	public function set_from_email( $from_email = '' ) {
		$admin_email = get_option( 'admin_email' );
		return is_email( $admin_email ) ? $admin_email : $from_email;
	}

	/**
	 * Verstuur mail met plugin-afzendernaam en -adres.
	 *
	 * @param string|array $to      Ontvanger(s).
	 * @param string       $subject Onderwerp.
	 * @param string       $message Bericht.
	 * @param array        $headers Extra headers.
	 * @param bool         $html    Verstuur als HTML.
	 * @return bool
	 */
	private function send_mail( $to, $subject, $message, $headers = array(), $html = false ) {
		add_filter( 'wp_mail_from', array( $this, 'set_from_email' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'set_from_name' ) );
		add_action( 'wp_mail_failed', array( $this, 'log_mail_error' ) );

		if ( $html ) {
			add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		}

		$result = wp_mail( $to, $subject, $message, $headers );

		if ( $html ) {
			remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		}

		remove_action( 'wp_mail_failed', array( $this, 'log_mail_error' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'set_from_name' ) );
		remove_filter( 'wp_mail_from', array( $this, 'set_from_email' ) );

		return $result;
	}

	/**
	 * Bepaal bedrijfsnaam voor de afzender.
	 *
	 * @return string
	 */
	private function get_from_name() {
		$name = Aardbei_Reserveringen_Settings::get_setting( 'frontend_widget_title', '' );

		if ( '' === trim( (string) $name ) ) {
			$name = get_bloginfo( 'name' );
		}

		if ( '' === trim( (string) $name ) ) {
			$name = __( 'Aardbei Reserveringen', 'aardbei-reserveringen' );
		}

		return wp_specialchars_decode( sanitize_text_field( $name ), ENT_QUOTES );
	}

	/**
	 * Annuleerlink.
	 *
	 * @param string $cancel_token Token.
	 * @return string
	 */
	public function get_cancel_url( $cancel_token ) {
		return add_query_arg(
			array(
				'aardbei_cancel' => 1,
				'token'          => $cancel_token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Haal reservering met slot op.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return array|null
	 */
	private function get_reservation_with_slot( $reservation_id ) {
		global $wpdb;

		$reservation_id     = absint( $reservation_id );
		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();
		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();

		if ( ! $reservation_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, s.date, s.start_time, s.end_time
				FROM {$reservations_table} r
				INNER JOIN {$slots_table} s ON s.id = r.slot_id
				WHERE r.id = %d",
				$reservation_id
			),
			ARRAY_A
		);
	}

	/**
	 * Datum mooi tonen.
	 *
	 * @param string $date Datum.
	 * @return string
	 */
	private function format_date( $date ) {
		return date_i18n( get_option( 'date_format' ), strtotime( $date ) );
	}

	/**
	 * Tijd mooi tonen.
	 *
	 * @param string $time Tijd.
	 * @return string
	 */
	private function format_time( $time ) {
		return substr( $time, 0, 5 );
	}
}
