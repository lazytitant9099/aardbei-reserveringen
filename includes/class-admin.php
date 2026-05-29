<?php
/**
 * Admin area.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin beheer.
 */
class Aardbei_Reserveringen_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_menu', array( $this, 'register_kassa_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_action( 'admin_post_aardbei_save_settings', array( $this, 'handle_save_settings' ) );
		// handle_save_github_settings is no longer needed (updater uses info.json, no credentials)
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
		add_action( 'admin_post_aardbei_add_week_template', array( $this, 'handle_add_week_template' ) );
		add_action( 'admin_post_aardbei_delete_week_template', array( $this, 'handle_delete_week_template' ) );
		add_action( 'admin_post_aardbei_generate_next_week_slots', array( $this, 'handle_generate_next_week_slots' ) );
		add_action( 'admin_post_aardbei_add_slot', array( $this, 'handle_add_slot' ) );
		add_action( 'admin_post_aardbei_update_slot_capacity', array( $this, 'handle_update_slot_capacity' ) );
		add_action( 'admin_post_aardbei_close_slot', array( $this, 'handle_close_slot' ) );
		add_action( 'admin_post_aardbei_open_slot', array( $this, 'handle_open_slot' ) );
		add_action( 'admin_post_aardbei_cancel_reservation', array( $this, 'handle_cancel_reservation' ) );
		add_action( 'admin_post_aardbei_delete_slot', array( $this, 'handle_delete_slot' ) );

		add_action( 'admin_post_aardbei_check_in_reservation', array( $this, 'handle_check_in_reservation' ) );
		add_action( 'admin_post_aardbei_undo_check_in', array( $this, 'handle_undo_check_in' ) );
	}

	/**
	 * Registreer menu en submenu's.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Aardbei Reserveringen', 'aardbei-reserveringen' ),
			__( 'Aardbei Reserveringen', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen',
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Dashboard', 'aardbei-reserveringen' ),
			__( 'Dashboard', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Kalender', 'aardbei-reserveringen' ),
			__( 'Kalender', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen-calendar',
			array( $this, 'render_calendar' )
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Reserveringen', 'aardbei-reserveringen' ),
			__( 'Reserveringen', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen-reservations',
			array( $this, 'render_reservations' )
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Weekschema', 'aardbei-reserveringen' ),
			__( 'Weekschema', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen-week-template',
			array( $this, 'render_week_template' )
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Tijdsloten', 'aardbei-reserveringen' ),
			__( 'Tijdsloten', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen-slots',
			array( $this, 'render_slots' )
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Instellingen', 'aardbei-reserveringen' ),
			__( 'Instellingen', 'aardbei-reserveringen' ),
			'manage_options',
			'aardbei-reserveringen-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'aardbei-reserveringen',
			__( 'Kassa', 'aardbei-reserveringen' ),
			__( 'Kassa', 'aardbei-reserveringen' ),
			'aardbei_kassa',
			'aardbei-reserveringen-kassa',
			array( $this, 'render_kassa' )
		);
	}

	/**
	 * Registreer standalone kassapagina voor kassamedewerkers (geen manage_options).
	 * Loopt apart van register_menu zodat kassamedewerkers het menu zien.
	 */
	public function register_kassa_menu() {
		if ( ! current_user_can( 'aardbei_kassa' ) || current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			__( 'Kassa', 'aardbei-reserveringen' ),
			__( 'Kassa', 'aardbei-reserveringen' ),
			'aardbei_kassa',
			'aardbei-reserveringen-kassa',
			array( $this, 'render_kassa' ),
			'dashicons-store',
			3
		);
	}

	/**
	 * Laad admin assets.
	 */
	public function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views = $this->get_views();

		if ( ! isset( $views[ $page ] ) ) {
			return;
		}

		wp_enqueue_style(
			'aardbei-admin',
			AARDBEI_RESERVERINGEN_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			AARDBEI_RESERVERINGEN_VERSION
		);

		wp_enqueue_script(
			'aardbei-admin',
			AARDBEI_RESERVERINGEN_PLUGIN_URL . 'admin/assets/js/admin.js',
			array(),
			AARDBEI_RESERVERINGEN_VERSION,
			true
		);

		wp_localize_script(
			'aardbei-admin',
			'aardbeiAdminData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aardbei_admin_nonce' ),
				'i18n'    => array(
					'confirmCancel'    => __( 'Weet je zeker dat je deze reservering wilt annuleren?', 'aardbei-reserveringen' ),
					'confirmBulk'      => __( 'Weet je zeker dat je de geselecteerde reserveringen wilt annuleren?', 'aardbei-reserveringen' ),
					'cancelled'        => __( 'Geannuleerd', 'aardbei-reserveringen' ),
					'error'            => __( 'Er ging iets mis. Probeer opnieuw.', 'aardbei-reserveringen' ),
					'checking'         => __( 'Controleren…', 'aardbei-reserveringen' ),
					'checkNow'         => __( 'Nu controleren', 'aardbei-reserveringen' ),
					'selectAll'              => __( 'Alles selecteren', 'aardbei-reserveringen' ),
					'bulkCancel'             => __( 'Annuleer geselecteerde', 'aardbei-reserveringen' ),
					'confirmDeleteSlot'      => __( 'Weet je zeker dat je dit tijdslot wilt verwijderen?', 'aardbei-reserveringen' ),
					'confirmBulkDeleteSlots' => __( 'Weet je zeker dat je de geselecteerde tijdsloten wilt verwijderen?', 'aardbei-reserveringen' ),
					'deleted'                => __( 'Verwijderd', 'aardbei-reserveringen' ),
				),
			)
		);

		if ( 'aardbei-reserveringen-kassa' === $page ) {
			wp_enqueue_script(
				'aardbei-admin-kassa',
				AARDBEI_RESERVERINGEN_PLUGIN_URL . 'admin/assets/js/admin-kassa.js',
				array(),
				AARDBEI_RESERVERINGEN_VERSION,
				true
			);
			wp_localize_script(
				'aardbei-admin-kassa',
				'aardbeiKassaData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'aardbei_kassa_nonce' ),
					'i18n'    => array(
						'confirmUndo' => __( 'Aanmelding ongedaan maken?', 'aardbei-reserveringen' ),
						'error'       => __( 'Er ging iets mis. Probeer opnieuw.', 'aardbei-reserveringen' ),
						'aangemeld'   => __( 'Aangemeld', 'aardbei-reserveringen' ),
					),
				)
			);
		}

		if ( 'aardbei-reserveringen-calendar' === $page ) {
			wp_enqueue_style(
				'fullcalendar',
				'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css',
				array(),
				'5.11.5'
			);
			wp_enqueue_script(
				'fullcalendar',
				'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js',
				array(),
				'5.11.5',
				true
			);
			wp_enqueue_script(
				'aardbei-admin-calendar',
				AARDBEI_RESERVERINGEN_PLUGIN_URL . 'admin/assets/js/admin-calendar.js',
				array( 'fullcalendar' ),
				AARDBEI_RESERVERINGEN_VERSION,
				true
			);
			wp_localize_script(
				'aardbei-admin-calendar',
				'aardbeiAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'aardbei_admin_nonce' ),
					'i18n'    => array(
						'loadingError'      => __( 'De kalender kon niet worden geladen.', 'aardbei-reserveringen' ),
						'closeSlot'         => __( 'Tijdslot sluiten', 'aardbei-reserveringen' ),
						'openSlot'          => __( 'Tijdslot openen', 'aardbei-reserveringen' ),
						'deleteSlot'        => __( 'Verwijderen', 'aardbei-reserveringen' ),
						'confirmDeleteSlot' => __( 'Weet je zeker dat je dit tijdslot wilt verwijderen?', 'aardbei-reserveringen' ),
					),
				)
			);
		}
	}

	/**
	 * Render callbacks.
	 */
	public function render_dashboard() {
		$this->render_view( 'aardbei-reserveringen' );
	}

	public function render_calendar() {
		$this->render_view( 'aardbei-reserveringen-calendar' );
	}

	public function render_reservations() {
		$this->render_view( 'aardbei-reserveringen-reservations' );
	}

	public function render_week_template() {
		$this->render_view( 'aardbei-reserveringen-week-template' );
	}

	public function render_slots() {
		$this->render_view( 'aardbei-reserveringen-slots' );
	}

	public function render_settings() {
		$this->render_view( 'aardbei-reserveringen-settings' );
	}

	public function render_kassa() {
		if ( ! current_user_can( 'aardbei_kassa' ) ) {
			wp_die( esc_html__( 'Je hebt geen rechten voor deze pagina.', 'aardbei-reserveringen' ) );
		}

		$this->show_admin_notice();

		$view_file = AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'admin/views/kassa.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Meld klant aan bij de kassa.
	 */
	public function handle_check_in_reservation() {
		$reservation_id = isset( $_REQUEST['reservation_id'] ) ? absint( wp_unslash( $_REQUEST['reservation_id'] ) ) : 0;
		$this->verify_kassa_action( 'aardbei_check_in_' . $reservation_id );

		$date         = isset( $_REQUEST['kassa_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['kassa_date'] ) ) : '';
		$reservations = new Aardbei_Reserveringen_Reservations();
		$result       = $reservations->check_in_reservation( $reservation_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_kassa( $date, 'error', $result->get_error_message() );
		}

		$this->redirect_kassa( $date, 'updated', __( 'Klant aangemeld.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Maak aanmelding ongedaan.
	 */
	public function handle_undo_check_in() {
		$reservation_id = isset( $_REQUEST['reservation_id'] ) ? absint( wp_unslash( $_REQUEST['reservation_id'] ) ) : 0;
		$this->verify_kassa_action( 'aardbei_undo_check_in_' . $reservation_id );

		$date         = isset( $_REQUEST['kassa_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['kassa_date'] ) ) : '';
		$reservations = new Aardbei_Reserveringen_Reservations();
		$reservations->undo_check_in( $reservation_id );

		$this->redirect_kassa( $date, 'updated', __( 'Aanmelding ongedaan gemaakt.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Toon pluginversie in de admin footer.
	 *
	 * @param string $text Bestaande footer-tekst.
	 * @return string
	 */
	public function admin_footer_text( $text ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$views = $this->get_views();
		if ( ! isset( $views[ $page ] ) ) {
			return $text;
		}

		return sprintf(
			'%s &nbsp;·&nbsp; <strong>Aardbei Reserveringen</strong> v%s',
			$text,
			esc_html( AARDBEI_RESERVERINGEN_VERSION )
		);
	}

	/**
	 * Sla instellingen op.
	 */
	public function handle_save_settings() {
		$this->verify_admin_action( 'aardbei_save_settings' );
		Aardbei_Reserveringen_Settings::update_settings( $_POST );
		$this->redirect( 'aardbei-reserveringen-settings', 'updated', __( 'Instellingen opgeslagen.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Sla GitHub-updater instellingen op.
	 */
	public function handle_save_github_settings() {
		$this->verify_admin_action( 'aardbei_save_github_settings' );

		$gh_user  = isset( $_POST['gh_user'] ) ? sanitize_text_field( wp_unslash( $_POST['gh_user'] ) ) : '';
		$gh_repo  = isset( $_POST['gh_repo'] ) ? sanitize_text_field( wp_unslash( $_POST['gh_repo'] ) ) : '';
		$gh_token = isset( $_POST['gh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['gh_token'] ) ) : '';

		update_option( 'aardbei_gh_user', $gh_user );
		update_option( 'aardbei_gh_repo', $gh_repo );

		if ( $gh_token ) {
			update_option( 'aardbei_gh_token', $gh_token );
		}

		delete_transient( 'aardbei_update_' . md5( $gh_user . '/' . $gh_repo ) );

		$this->redirect( 'aardbei-reserveringen-settings', 'updated', __( 'GitHub-instellingen opgeslagen.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Voeg weekschema regel toe.
	 */
	public function handle_add_week_template() {
		global $wpdb;

		$this->verify_admin_action( 'aardbei_add_week_template' );

		$weekday   = isset( $_POST['weekday'] ) ? absint( wp_unslash( $_POST['weekday'] ) ) : 0;
		$start     = isset( $_POST['start_time'] ) ? $this->sanitize_time( wp_unslash( $_POST['start_time'] ) ) : '';
		$end       = isset( $_POST['end_time'] ) ? $this->sanitize_time( wp_unslash( $_POST['end_time'] ) ) : '';
		$capacity  = isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 0;
		$duration  = isset( $_POST['slot_duration_minutes'] ) ? absint( wp_unslash( $_POST['slot_duration_minutes'] ) ) : 30;
		$active    = empty( $_POST['active'] ) ? 0 : 1;
		$templates = Aardbei_Reserveringen_Database::get_week_templates_table();

		if ( ! in_array( $duration, array( 15, 30, 45, 60, 90, 120 ), true ) ) {
			$duration = 30;
		}

		if ( $weekday < 1 || $weekday > 7 || ! $start || ! $end || $end <= $start || $capacity < 1 ) {
			$this->redirect( 'aardbei-reserveringen-week-template', 'error', __( 'Controleer de ingevoerde weekschema regel.', 'aardbei-reserveringen' ) );
		}

		$inserted = $wpdb->insert(
			$templates,
			array(
				'weekday'    => $weekday,
				'start_time' => $start,
				'end_time'   => $end,
				'capacity'   => $capacity,
				'slot_duration_minutes' => $duration,
				'active'     => $active,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => null,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->redirect( 'aardbei-reserveringen-week-template', 'error', __( 'De regel kon niet worden opgeslagen.', 'aardbei-reserveringen' ) );
		}

		$this->redirect( 'aardbei-reserveringen-week-template', 'updated', __( 'Weekschema regel toegevoegd.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Verwijder weekschema regel.
	 */
	public function handle_delete_week_template() {
		global $wpdb;

		$id = isset( $_REQUEST['id'] ) ? absint( wp_unslash( $_REQUEST['id'] ) ) : 0;
		$this->verify_admin_action( 'aardbei_delete_week_template_' . $id );

		if ( $id ) {
			$wpdb->delete( Aardbei_Reserveringen_Database::get_week_templates_table(), array( 'id' => $id ), array( '%d' ) );
		}

		$this->redirect( 'aardbei-reserveringen-week-template', 'updated', __( 'Weekschema regel verwijderd.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Handmatig slots genereren.
	 */
	public function handle_generate_next_week_slots() {
		$this->verify_admin_action( 'aardbei_generate_next_week_slots' );

		$slots   = new Aardbei_Reserveringen_Slots();
		$created = $slots->generate_slots_for_next_bookable_week();

		$this->redirect(
			'aardbei-reserveringen-week-template',
			'updated',
			sprintf(
				/* translators: %d: amount of slots. */
				__( '%d tijdsloten gegenereerd.', 'aardbei-reserveringen' ),
				$created
			)
		);
	}

	/**
	 * Voeg handmatig slot toe.
	 */
	public function handle_add_slot() {
		$this->verify_admin_action( 'aardbei_add_slot' );

		$slots  = new Aardbei_Reserveringen_Slots();
		$result = $slots->create_slot( $_POST );

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'aardbei-reserveringen-slots', 'error', $result->get_error_message() );
		}

		$this->redirect( 'aardbei-reserveringen-slots', 'updated', __( 'Tijdslot toegevoegd.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Pas capaciteit aan.
	 */
	public function handle_update_slot_capacity() {
		$slot_id = isset( $_POST['slot_id'] ) ? absint( wp_unslash( $_POST['slot_id'] ) ) : 0;
		$this->verify_admin_action( 'aardbei_update_slot_capacity_' . $slot_id );

		$capacity = isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 0;
		$slots    = new Aardbei_Reserveringen_Slots();
		$result   = $slots->update_slot_capacity( $slot_id, $capacity );

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'aardbei-reserveringen-slots', 'error', $result->get_error_message() );
		}

		$this->redirect( 'aardbei-reserveringen-slots', 'updated', __( 'Capaciteit aangepast.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Sluit slot.
	 */
	public function handle_close_slot() {
		$slot_id = isset( $_REQUEST['slot_id'] ) ? absint( wp_unslash( $_REQUEST['slot_id'] ) ) : 0;
		$this->verify_admin_action( 'aardbei_close_slot_' . $slot_id );

		$slots = new Aardbei_Reserveringen_Slots();
		$slots->close_slot( $slot_id, true );

		$this->redirect( 'aardbei-reserveringen-slots', 'updated', __( 'Tijdslot gesloten.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Open slot.
	 */
	public function handle_open_slot() {
		$slot_id = isset( $_REQUEST['slot_id'] ) ? absint( wp_unslash( $_REQUEST['slot_id'] ) ) : 0;
		$this->verify_admin_action( 'aardbei_open_slot_' . $slot_id );

		$slots = new Aardbei_Reserveringen_Slots();
		$slots->open_slot( $slot_id );

		$this->redirect( 'aardbei-reserveringen-slots', 'updated', __( 'Tijdslot geopend.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Verwijder tijdslot.
	 */
	public function handle_delete_slot() {
		$slot_id = isset( $_REQUEST['slot_id'] ) ? absint( wp_unslash( $_REQUEST['slot_id'] ) ) : 0;
		$this->verify_admin_action( 'aardbei_delete_slot_' . $slot_id );

		$slots  = new Aardbei_Reserveringen_Slots();
		$result = $slots->delete_slot( $slot_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'aardbei-reserveringen-slots', 'error', $result->get_error_message() );
		}

		$this->redirect( 'aardbei-reserveringen-slots', 'updated', __( 'Tijdslot verwijderd.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Annuleer reservering.
	 */
	public function handle_cancel_reservation() {
		$reservation_id = isset( $_REQUEST['reservation_id'] ) ? absint( wp_unslash( $_REQUEST['reservation_id'] ) ) : 0;
		$this->verify_admin_action( 'aardbei_cancel_reservation_' . $reservation_id );

		$reservations = new Aardbei_Reserveringen_Reservations();
		$result       = $reservations->cancel_reservation_by_admin( $reservation_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'aardbei-reserveringen-reservations', 'error', $result->get_error_message() );
		}

		$this->redirect( 'aardbei-reserveringen-reservations', 'updated', __( 'Reservering geannuleerd.', 'aardbei-reserveringen' ) );
	}

	/**
	 * Weekdag labels.
	 *
	 * @return array
	 */
	public static function get_weekday_options() {
		return array(
			1 => __( 'Maandag', 'aardbei-reserveringen' ),
			2 => __( 'Dinsdag', 'aardbei-reserveringen' ),
			3 => __( 'Woensdag', 'aardbei-reserveringen' ),
			4 => __( 'Donderdag', 'aardbei-reserveringen' ),
			5 => __( 'Vrijdag', 'aardbei-reserveringen' ),
			6 => __( 'Zaterdag', 'aardbei-reserveringen' ),
			7 => __( 'Zondag', 'aardbei-reserveringen' ),
		);
	}

	/**
	 * Vaste whitelist met admin views.
	 *
	 * @return array
	 */
	private function get_views() {
		return array(
			'aardbei-reserveringen'               => 'dashboard.php',
			'aardbei-reserveringen-calendar'      => 'calendar.php',
			'aardbei-reserveringen-reservations'  => 'reservations.php',
			'aardbei-reserveringen-week-template' => 'week-template.php',
			'aardbei-reserveringen-slots'         => 'slots.php',
			'aardbei-reserveringen-settings'      => 'settings.php',
			'aardbei-reserveringen-kassa'         => 'kassa.php',
		);
	}

	/**
	 * Render een whitelisted view.
	 *
	 * @param string $slug Menu slug.
	 */
	private function render_view( $slug ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen rechten voor deze pagina.', 'aardbei-reserveringen' ) );
		}

		$views = $this->get_views();
		if ( ! isset( $views[ $slug ] ) ) {
			wp_die( esc_html__( 'Ongeldige beheerpagina.', 'aardbei-reserveringen' ) );
		}

		$this->show_admin_notice();

		$view_file = AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'admin/views/' . $views[ $slug ];
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Toon notices op basis van query args.
	 */
	private function show_admin_notice() {
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : __( 'Opgeslagen.', 'aardbei-reserveringen' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}

		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : __( 'Er ging iets mis.', 'aardbei-reserveringen' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Controleer rechten en nonce.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	private function verify_admin_action( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen rechten voor deze actie.', 'aardbei-reserveringen' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Controleer kassa rechten en nonce.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	private function verify_kassa_action( $nonce_action ) {
		if ( ! current_user_can( 'aardbei_kassa' ) ) {
			wp_die( esc_html__( 'Je hebt geen rechten voor deze actie.', 'aardbei-reserveringen' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect naar kassapagina.
	 *
	 * @param string $date    Datum (Y-m-d) of leeg voor vandaag.
	 * @param string $status  updated of error.
	 * @param string $message Bericht.
	 */
	private function redirect_kassa( $date, $status, $message ) {
		$args = array(
			'page'    => 'aardbei-reserveringen-kassa',
			$status   => 1,
			'message' => $message,
		);

		if ( $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$args['date'] = $date;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Redirect naar admin pagina.
	 *
	 * @param string $page    Pagina slug.
	 * @param string $status  updated of error.
	 * @param string $message Bericht.
	 */
	private function redirect( $page, $status, $message ) {
		$args = array(
			'page'    => sanitize_key( $page ),
			$status   => 1,
			'message' => $message,
		);

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Tijd uit formulier.
	 *
	 * @param string $time Tijd.
	 * @return string
	 */
	private function sanitize_time( $time ) {
		$time = sanitize_text_field( $time );

		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return $time . ':00';
		}

		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time ) ) {
			return $time;
		}

		return '';
	}
}
