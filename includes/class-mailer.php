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

		$subject = Aardbei_Reserveringen_Settings::get_setting( 'customer_mail_subject', __( 'Je reservering voor aardbeien plukken', 'aardbei-reserveringen' ) );
		$message = sprintf(
			"Hallo %s,\n\nBedankt voor je reservering om aardbeien te komen plukken.\n\nDatum: %s\nTijd: %s - %s\nAantal personen: %d\n\nBetaling vindt plaats op locatie.\n\nWil je annuleren? Gebruik dan deze link:\n%s\n\nTot snel!",
			$reservation['name'],
			$this->format_date( $reservation['date'] ),
			$this->format_time( $reservation['start_time'] ),
			$this->format_time( $reservation['end_time'] ),
			(int) $reservation['persons'],
			$this->get_cancel_url( $reservation['cancel_token'] )
		);

		return wp_mail( $reservation['email'], $subject, $message );
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
		$message = sprintf(
			"Nieuwe reservering:\n\nDatum: %s\nTijd: %s - %s\nNaam: %s\nE-mail: %s\nTelefoon: %s\nPersonen: %d\nOpmerking: %s",
			$this->format_date( $reservation['date'] ),
			$this->format_time( $reservation['start_time'] ),
			$this->format_time( $reservation['end_time'] ),
			$reservation['name'],
			$reservation['email'],
			$reservation['phone'],
			(int) $reservation['persons'],
			$reservation['note']
		);

		return wp_mail( $to, $subject, $message );
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
			"Een reservering is geannuleerd:\n\nDatum: %s\nTijd: %s - %s\nNaam: %s\nE-mail: %s\nPersonen: %d",
			$this->format_date( $reservation['date'] ),
			$this->format_time( $reservation['start_time'] ),
			$this->format_time( $reservation['end_time'] ),
			$reservation['name'],
			$reservation['email'],
			(int) $reservation['persons']
		);

		return wp_mail( $to, $subject, $message );
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
