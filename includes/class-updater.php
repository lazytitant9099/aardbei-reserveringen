<?php
/**
 * Auto-updater via info.json.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controleert de info.json op updates en biedt ze aan via het WP-updatescherm.
 */
class Aardbei_Reserveringen_Updater {

	private $plugin_basename;
	private $plugin_slug = 'aardbei-reserveringen';
	private $current_version;
	private $remote_url;
	private $cache_key;
	private $cache_ttl = 43200; // 12 uur

	/**
	 * @param string $plugin_file Absoluut pad naar het hoofd-pluginbestand.
	 * @param string $remote_url  URL naar info.json.
	 */
	public function __construct( $plugin_file, $remote_url ) {
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->current_version = AARDBEI_RESERVERINGEN_VERSION;
		$this->remote_url      = $remote_url;
		$this->cache_key       = 'aardbei_update_' . md5( $remote_url );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
	}

	/**
	 * Injecteer update-object in de WP-transient wanneer een nieuwere versie beschikbaar is.
	 *
	 * @param object $transient WP update transient.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( isset( $_GET['force-check'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['force-check'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_transient( $this->cache_key );
		}

		if ( empty( $transient->checked ) || ! isset( $transient->checked[ $this->plugin_basename ] ) ) {
			return $transient;
		}

		$remote = $this->get_remote_info();

		if ( $remote && isset( $remote->version ) && version_compare( $this->current_version, (string) $remote->version, '<' ) ) {
			$update              = new stdClass();
			$update->slug        = $this->plugin_slug;
			$update->plugin      = $this->plugin_basename;
			$update->new_version = $remote->version;
			$update->url         = isset( $remote->homepage ) ? $remote->homepage : '';
			$update->package     = $remote->download_url;
			$update->icons       = array();
			$update->banners     = array();
			$update->tested      = isset( $remote->tested ) ? $remote->tested : '6.8';
			$update->requires    = isset( $remote->requires ) ? $remote->requires : '6.0';

			$transient->response[ $this->plugin_basename ] = $update;
		} elseif ( isset( $transient->response[ $this->plugin_basename ] ) ) {
			unset( $transient->response[ $this->plugin_basename ] );
		}

		return $transient;
	}

	/**
	 * Vul de plugin-informatiepagina met release-data.
	 *
	 * @param mixed  $res    Resultaat.
	 * @param string $action Actie.
	 * @param object $args   Argumenten.
	 * @return mixed
	 */
	public function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $res;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote ) {
			return $res;
		}

		$info               = new stdClass();
		$info->name         = isset( $remote->name ) ? $remote->name : 'Aardbei Reserveringen';
		$info->slug         = $this->plugin_slug;
		$info->version      = isset( $remote->version ) ? $remote->version : '';
		$info->tested       = isset( $remote->tested ) ? $remote->tested : '6.8';
		$info->requires     = isset( $remote->requires ) ? $remote->requires : '6.0';
		$info->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '7.4';
		$info->author       = isset( $remote->author ) ? $remote->author : 'Roy Boer';
		$info->homepage     = isset( $remote->homepage ) ? $remote->homepage : '';
		$info->download_link = isset( $remote->download_url ) ? $remote->download_url : '';
		$info->trunk        = isset( $remote->download_url ) ? $remote->download_url : '';
		$info->last_updated = isset( $remote->last_updated ) ? $remote->last_updated : '';
		$info->sections     = array(
			'description' => isset( $remote->sections->description ) ? $remote->sections->description : '',
			'changelog'   => isset( $remote->sections->changelog ) ? $remote->sections->changelog : '',
		);

		return $info;
	}

	/**
	 * Wis de cache na een succesvolle plugin-update.
	 *
	 * @param WP_Upgrader $upgrader_object Upgrader.
	 * @param array       $options         Opties.
	 */
	public function clear_cache_after_update( $upgrader_object, $options ) {
		if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
			return;
		}
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		delete_transient( $this->cache_key );
	}

	/**
	 * Haal de huidige update-status op (op basis van gecachede data).
	 *
	 * @return array
	 */
	public function get_update_status() {
		$remote = $this->get_remote_info();

		if ( false === $remote ) {
			$cached = get_transient( $this->cache_key );
			if ( false === $cached ) {
				return array(
					'status'          => 'pending',
					'current_version' => $this->current_version,
					'message'         => __( 'Klik op "Nu controleren" om de laatste versie op te halen.', 'aardbei-reserveringen' ),
				);
			}
			return array(
				'status'  => 'error',
				'message' => __( 'Kon geen verbinding maken met de updateserver.', 'aardbei-reserveringen' ),
			);
		}

		$update_available = version_compare( $this->current_version, (string) $remote->version, '<' );

		return array(
			'status'           => 'connected',
			'current_version'  => $this->current_version,
			'latest_version'   => $remote->version,
			'update_available' => $update_available,
			'message'          => $update_available
				/* translators: %s: version number. */
				? sprintf( __( 'Update beschikbaar: v%s', 'aardbei-reserveringen' ), $remote->version )
				: __( 'Je gebruikt de nieuwste versie.', 'aardbei-reserveringen' ),
		);
	}

	/**
	 * Wis cache, haal verse data op en geef status terug.
	 *
	 * @return array
	 */
	public function check_now() {
		delete_transient( $this->cache_key );
		return $this->get_update_status();
	}

	/**
	 * Haal remote info.json op (12 uur gecached).
	 *
	 * @return object|false
	 */
	private function get_remote_info() {
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$this->remote_url,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $data ) {
			set_transient( $this->cache_key, $data, $this->cache_ttl );
		}

		return $data ?: false;
	}
}
