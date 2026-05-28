<?php
/**
 * AJAX endpoints.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Behandelt publieke en admin AJAX requests.
 */
class Aardbei_Reserveringen_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_aardbei_get_public_slots', array( $this, 'get_public_slots' ) );
		add_action( 'wp_ajax_nopriv_aardbei_get_public_slots', array( $this, 'get_public_slots' ) );
		add_action( 'wp_ajax_aardbei_create_reservation', array( $this, 'create_reservation' ) );
		add_action( 'wp_ajax_nopriv_aardbei_create_reservation', array( $this, 'create_reservation' ) );

		add_action( 'wp_ajax_aardbei_get_admin_slots', array( $this, 'get_admin_slots' ) );
		add_action( 'wp_ajax_aardbei_get_slot_details', array( $this, 'get_slot_details' ) );
		add_action( 'wp_ajax_aardbei_admin_close_slot', array( $this, 'admin_close_slot' ) );
		add_action( 'wp_ajax_aardbei_admin_open_slot', array( $this, 'admin_open_slot' ) );
		add_action( 'wp_ajax_aardbei_cancel_reservation', array( $this, 'admin_cancel_reservation' ) );
		add_action( 'wp_ajax_aardbei_bulk_cancel_reservations', array( $this, 'bulk_cancel_reservations' ) );
		add_action( 'wp_ajax_aardbei_check_for_update', array( $this, 'check_for_update' ) );
	}

	/**
	 * Publieke kalender events.
	 */
	public function get_public_slots() {
		$this->verify_public_ajax();

		$start = $this->request_date( 'start' );
		$end   = $this->request_end_date();
		$slots = new Aardbei_Reserveringen_Slots();
		$rows  = $slots->get_slots( $start, $end, true );
		$events = array();
		$show_remaining = Aardbei_Reserveringen_Settings::get_setting( 'show_remaining_capacity', 1 );

		foreach ( $rows as $row ) {
			$remaining = (int) $row['remaining'];
			$title     = $this->format_time( $row['start_time'] ) . ' - ' . $this->format_time( $row['end_time'] );

			if ( $show_remaining ) {
				$title .= ' | ' . sprintf(
					/* translators: %d: remaining places. */
					_n( 'nog %d plek', 'nog %d plekken', $remaining, 'aardbei-reserveringen' ),
					$remaining
				);
			}

			$events[] = array(
				'id'            => (string) $row['id'],
				'title'         => $title,
				'start'         => $row['date'] . 'T' . $row['start_time'],
				'end'           => $row['date'] . 'T' . $row['end_time'],
				'extendedProps' => array(
					'slot_id'   => (int) $row['id'],
					'remaining' => $remaining,
					'capacity'  => (int) $row['capacity'],
				),
			);
		}

		wp_send_json_success( $events );
	}

	/**
	 * Maak publieke reservering.
	 */
	public function create_reservation() {
		$this->verify_public_ajax();

		$reservations = new Aardbei_Reserveringen_Reservations();
		$result       = $reservations->create_reservation( $_POST );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'reservation_id' => (int) $result,
				'message'        => __( 'Je reservering is ontvangen. Je ontvangt zo een bevestiging per e-mail.', 'aardbei-reserveringen' ),
			)
		);
	}

	/**
	 * Admin kalender events.
	 */
	public function get_admin_slots() {
		$this->verify_admin_ajax();

		$start = $this->request_date( 'start' );
		$end   = $this->request_end_date();
		$slots = new Aardbei_Reserveringen_Slots();
		$rows  = $slots->get_slots( $start, $end, false );
		$events = array();

		foreach ( $rows as $row ) {
			$status = 'open';
			if ( 'closed' === $row['status'] || (int) $row['manual_closed'] ) {
				$status = 'closed';
			} elseif ( (int) $row['remaining'] <= 0 ) {
				$status = 'full';
			}

			$events[] = array(
				'id'            => (string) $row['id'],
				'title'         => sprintf(
					'%s - %s | %d/%d geboekt',
					$this->format_time( $row['start_time'] ),
					$this->format_time( $row['end_time'] ),
					(int) $row['booked_persons'],
					(int) $row['capacity']
				),
				'start'         => $row['date'] . 'T' . $row['start_time'],
				'end'           => $row['date'] . 'T' . $row['end_time'],
				'classNames'    => array( 'aardbei-slot-' . $status ),
				'extendedProps' => array(
					'slot_id'   => (int) $row['id'],
					'status'    => $status,
					'booked'    => (int) $row['booked_persons'],
					'capacity'  => (int) $row['capacity'],
					'remaining' => (int) $row['remaining'],
				),
			);
		}

		wp_send_json_success( $events );
	}

	/**
	 * Admin slotdetails.
	 */
	public function get_slot_details() {
		$this->verify_admin_ajax();

		$slot_id = isset( $_REQUEST['slot_id'] ) ? absint( wp_unslash( $_REQUEST['slot_id'] ) ) : 0;
		$slots   = new Aardbei_Reserveringen_Slots();
		$slot    = $slots->get_slot_capacity_info( $slot_id );

		if ( ! $slot ) {
			wp_send_json_error( array( 'message' => __( 'Tijdslot niet gevonden.', 'aardbei-reserveringen' ) ), 404 );
		}

		$reservations = new Aardbei_Reserveringen_Reservations();
		$items        = $reservations->get_reservations_by_slot( $slot_id );

		wp_send_json_success(
			array(
				'slot'         => $slot,
				'reservations' => $items,
			)
		);
	}

	/**
	 * Admin sluit slot.
	 */
	public function admin_close_slot() {
		$this->verify_admin_ajax();

		$slot_id = isset( $_POST['slot_id'] ) ? absint( wp_unslash( $_POST['slot_id'] ) ) : 0;
		$slots   = new Aardbei_Reserveringen_Slots();

		if ( ! $slots->close_slot( $slot_id, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Tijdslot kon niet worden gesloten.', 'aardbei-reserveringen' ) ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Tijdslot gesloten.', 'aardbei-reserveringen' ) ) );
	}

	/**
	 * Admin opent slot.
	 */
	public function admin_open_slot() {
		$this->verify_admin_ajax();

		$slot_id = isset( $_POST['slot_id'] ) ? absint( wp_unslash( $_POST['slot_id'] ) ) : 0;
		$slots   = new Aardbei_Reserveringen_Slots();

		if ( ! $slots->open_slot( $slot_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Tijdslot kon niet worden geopend.', 'aardbei-reserveringen' ) ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Tijdslot geopend.', 'aardbei-reserveringen' ) ) );
	}

	/**
	 * Admin annuleert reservering via AJAX.
	 */
	public function admin_cancel_reservation() {
		$this->verify_admin_ajax();

		$id           = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		$reservations = new Aardbei_Reserveringen_Reservations();
		$result       = $reservations->cancel_reservation_by_admin( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Reservering geannuleerd.', 'aardbei-reserveringen' ) ) );
	}

	/**
	 * Bulk annulering via AJAX.
	 */
	public function bulk_cancel_reservations() {
		$this->verify_admin_ajax();

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Geen reserveringen geselecteerd.', 'aardbei-reserveringen' ) ), 400 );
		}

		$reservations = new Aardbei_Reserveringen_Reservations();
		$cancelled    = 0;

		foreach ( $ids as $id ) {
			if ( ! is_wp_error( $reservations->cancel_reservation_by_admin( $id ) ) ) {
				$cancelled++;
			}
		}

		wp_send_json_success(
			array(
				'cancelled' => $cancelled,
				/* translators: %d: aantal geannuleerde reserveringen. */
				'message'   => sprintf( __( '%d reservering(en) geannuleerd.', 'aardbei-reserveringen' ), $cancelled ),
			)
		);
	}

	/**
	 * Controleer op updates via AJAX.
	 */
	public function check_for_update() {
		$this->verify_admin_ajax();

		global $aardbei_updater;

		if ( ! $aardbei_updater instanceof Aardbei_Reserveringen_Updater ) {
			wp_send_json_error( array( 'message' => __( 'Updater niet beschikbaar.', 'aardbei-reserveringen' ) ), 500 );
		}

		$status = $aardbei_updater->check_now();
		wp_send_json_success( $status );
	}

	/**
	 * Admin AJAX beveiliging.
	 */
	private function verify_admin_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Geen rechten.', 'aardbei-reserveringen' ) ), 403 );
		}

		if ( ! check_ajax_referer( 'aardbei_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Ongeldige beveiligingscontrole.', 'aardbei-reserveringen' ) ), 403 );
		}
	}

	/**
	 * Publieke AJAX nonce check.
	 */
	private function verify_public_ajax() {
		if ( ! check_ajax_referer( 'aardbei_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Ongeldige beveiligingscontrole.', 'aardbei-reserveringen' ) ), 403 );
		}
	}

	/**
	 * Haal datum uit request.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function request_date( $key ) {
		if ( empty( $_REQUEST[ $key ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
		$date  = substr( $value, 0, 10 );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
	}

	/**
	 * FullCalendar stuurt de einddatum exclusief mee; maak daar een inclusieve datum van.
	 *
	 * @return string
	 */
	private function request_end_date() {
		$end = $this->request_date( 'end' );
		if ( ! $end ) {
			return '';
		}

		return date( 'Y-m-d', strtotime( $end . ' -1 day' ) );
	}

	/**
	 * Tijd formatteren.
	 *
	 * @param string $time Tijd.
	 * @return string
	 */
	private function format_time( $time ) {
		return substr( $time, 0, 5 );
	}
}
