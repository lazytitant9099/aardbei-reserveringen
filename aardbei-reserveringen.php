<?php
/**
 * Plugin Name: Aardbei Reserveringen
 * Description: Reserveringssysteem voor aardbeien plukken met kalender, tijdsloten en capaciteit. Ontwikkeld door Modern Visuals: https://modernvisuals.nl/
 * Version: 1.3.9
 * Author: Modern Visuals
 * Author URI: https://modernvisuals.nl/
 * Plugin URI: https://modernvisuals.nl/
 * Text Domain: aardbei-reserveringen
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AARDBEI_RESERVERINGEN_VERSION', '1.3.9' );
define( 'AARDBEI_RESERVERINGEN_DB_VERSION', '1.2.0' );
define( 'AARDBEI_RESERVERINGEN_PLUGIN_FILE', __FILE__ );
define( 'AARDBEI_RESERVERINGEN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AARDBEI_RESERVERINGEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AARDBEI_UPDATE_URL', 'https://raw.githubusercontent.com/lazytitant9099/aardbei-reserveringen/main/aardbei-updates/info.json' );
define( 'AARDBEI_MODERN_VISUALS_URL', 'https://modernvisuals.nl/' );
define( 'AARDBEI_MODERN_VISUALS_LOGO', AARDBEI_RESERVERINGEN_PLUGIN_URL . 'public/images/modernvisuals-beeldmerk-black.png' );

require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-updater.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-database.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-settings.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-cron.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-activator.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-slots.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-reservations.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-mailer.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-admin.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-frontend.php';
require_once AARDBEI_RESERVERINGEN_PLUGIN_DIR . 'includes/class-ajax.php';

global $aardbei_updater;
$aardbei_updater = new Aardbei_Reserveringen_Updater( __FILE__, AARDBEI_UPDATE_URL );

register_activation_hook( __FILE__, array( 'Aardbei_Reserveringen_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Aardbei_Reserveringen_Deactivator', 'deactivate' ) );

/**
 * Start de plugin.
 */
function aardbei_reserveringen_bootstrap() {
	if ( get_option( 'aardbei_reserveringen_db_version' ) !== AARDBEI_RESERVERINGEN_DB_VERSION ) {
		Aardbei_Reserveringen_Database::create_tables();
		update_option( 'aardbei_reserveringen_db_version', AARDBEI_RESERVERINGEN_DB_VERSION );
	}

	Aardbei_Reserveringen_Activator::setup_roles();

	new Aardbei_Reserveringen_Frontend();
	new Aardbei_Reserveringen_Ajax();
	new Aardbei_Reserveringen_Cron();

	if ( is_admin() ) {
		new Aardbei_Reserveringen_Admin();
	}
}
add_action( 'plugins_loaded', 'aardbei_reserveringen_bootstrap' );
