<?php
/**
 * GitHub auto-updater.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controleert GitHub Releases op nieuwe versies en biedt ze aan via het WP-updatescherm.
 */
class Aardbei_Reserveringen_Updater {

	private $plugin_file;
	private $plugin_basename;
	private $plugin_slug = 'aardbei-reserveringen';
	private $current_version;

	/**
	 * @param string $plugin_file Absoluut pad naar het hoofd-pluginbestand.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->current_version = AARDBEI_RESERVERINGEN_VERSION;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'maybe_download_with_auth' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	/**
	 * GitHub-configuratie ophalen (user, repo, token).
	 *
	 * @return array
	 */
	private function get_config() {
		return array(
			'user'  => sanitize_text_field( get_option( 'aardbei_gh_user', '' ) ),
			'repo'  => sanitize_text_field( get_option( 'aardbei_gh_repo', '' ) ),
			'token' => get_option( 'aardbei_gh_token', '' ),
		);
	}

	/**
	 * Cache sleutel genereren.
	 *
	 * @return string
	 */
	private function cache_key() {
		$config = $this->get_config();
		return 'aardbei_update_' . md5( $config['user'] . '/' . $config['repo'] );
	}

	/**
	 * Haal de nieuwste release op van de GitHub API (12 uur gecached).
	 *
	 * @return array|false
	 */
	public function get_latest_release() {
		$config = $this->get_config();

		if ( ! $config['user'] || ! $config['repo'] ) {
			return false;
		}

		$cached = get_transient( $this->cache_key() );
		if ( false !== $cached ) {
			return $cached;
		}

		$url  = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( $config['user'] ),
			rawurlencode( $config['repo'] )
		);
		$args = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			),
			'timeout' => 15,
		);

		if ( $config['token'] ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['tag_name'] ) ) {
			return false;
		}

		$version      = ltrim( $data['tag_name'], 'v' );
		$download_url = '';
		$needs_auth   = false;

		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( '.zip' === substr( $asset['name'], -4 ) ) {
					if ( $config['token'] ) {
						$download_url = $asset['url'];
						$needs_auth   = true;
					} else {
						$download_url = $asset['browser_download_url'];
					}
					break;
				}
			}
		}

		if ( ! $download_url && ! empty( $data['zipball_url'] ) ) {
			$download_url = $data['zipball_url'];
			$needs_auth   = ! empty( $config['token'] );
		}

		$result = array(
			'version'      => $version,
			'download_url' => $download_url,
			'needs_auth'   => $needs_auth,
			'changelog'    => isset( $data['body'] ) ? $data['body'] : '',
			'published_at' => isset( $data['published_at'] ) ? $data['published_at'] : '',
		);

		set_transient( $this->cache_key(), $result, 12 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Vergelijk met huidige versie en voeg een update-object toe aan de WP-transient.
	 *
	 * @param object $transient WP update transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release || ! version_compare( $release['version'], $this->current_version, '>' ) ) {
			return $transient;
		}

		$config = $this->get_config();

		$update              = new stdClass();
		$update->slug        = $this->plugin_slug;
		$update->plugin      = $this->plugin_basename;
		$update->new_version = $release['version'];
		$update->url         = 'https://github.com/' . $config['user'] . '/' . $config['repo'];
		$update->package     = $release['download_url'];
		$update->icons       = array();
		$update->banners     = array();
		$update->tested      = '6.8';
		$update->requires    = '6.0';

		$transient->response[ $this->plugin_basename ] = $update;

		return $transient;
	}

	/**
	 * Vul de plugin-informatiepagina met release-data.
	 *
	 * @param mixed  $result Resultaat.
	 * @param string $action Actie.
	 * @param object $args   Argumenten.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$config = $this->get_config();

		$info               = new stdClass();
		$info->name         = 'Aardbei Reserveringen';
		$info->slug         = $this->plugin_slug;
		$info->version      = $release['version'];
		$info->author       = '<a href="https://github.com/' . esc_attr( $config['user'] ) . '">Roy Boer</a>';
		$info->homepage     = 'https://github.com/' . $config['user'] . '/' . $config['repo'];
		$info->requires     = '6.0';
		$info->tested       = '6.8';
		$info->requires_php = '7.4';
		$info->last_updated = $release['published_at'];
		$info->download_link = $release['download_url'];
		$info->sections     = array(
			'description' => '<p>' . esc_html__( 'Reserveringssysteem voor aardbeien plukken met kalender, tijdsloten en capaciteitsmanagement.', 'aardbei-reserveringen' ) . '</p>',
			'changelog'   => $release['changelog']
				? '<pre>' . esc_html( $release['changelog'] ) . '</pre>'
				: '<p>' . esc_html__( 'Geen changelog beschikbaar.', 'aardbei-reserveringen' ) . '</p>',
		);

		return $info;
	}

	/**
	 * Download een privé-asset met Authorization-header.
	 * Retourneert het pad naar een tijdelijk bestand of false om normaal verder te gaan.
	 *
	 * @param bool|string $reply    Vorig antwoord.
	 * @param string      $url      Download-URL.
	 * @param object      $upgrader WP_Upgrader-instantie.
	 * @return bool|string|WP_Error
	 */
	public function maybe_download_with_auth( $reply, $url, $upgrader ) {
		$release = $this->get_latest_release();

		if ( ! $release || ! $release['needs_auth'] || $url !== $release['download_url'] ) {
			return $reply;
		}

		$config = $this->get_config();
		if ( ! $config['token'] ) {
			return $reply;
		}

		$tmpfname = wp_tempnam( 'aardbei-update' );
		$response = wp_remote_get(
			$url,
			array(
				'headers'  => array(
					'Authorization' => 'Bearer ' . $config['token'],
					'Accept'        => 'application/octet-stream',
					'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $tmpfname,
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmpfname ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			@unlink( $tmpfname ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'aardbei_download_failed', sprintf( 'Download mislukt (HTTP %d).', $code ) );
		}

		return $tmpfname;
	}

	/**
	 * Verwijder de update-cache handmatig.
	 */
	public function force_clear_cache() {
		delete_transient( $this->cache_key() );
	}

	/**
	 * Haal de huidige update-status op (op basis van gecachede data).
	 *
	 * @return array
	 */
	public function get_update_status() {
		$config = $this->get_config();

		if ( ! $config['user'] || ! $config['repo'] ) {
			return array(
				'status'  => 'not_configured',
				'message' => __( 'Vul GitHub-gebruikersnaam en repository in.', 'aardbei-reserveringen' ),
			);
		}

		$cached = get_transient( $this->cache_key() );

		if ( false === $cached ) {
			return array(
				'status'          => 'pending',
				'current_version' => $this->current_version,
				'message'         => __( 'Klik op "Nu controleren" om de laatste versie op te halen.', 'aardbei-reserveringen' ),
			);
		}

		$update_available = version_compare( $cached['version'], $this->current_version, '>' );

		return array(
			'status'           => 'connected',
			'current_version'  => $this->current_version,
			'latest_version'   => $cached['version'],
			'update_available' => $update_available,
			'message'          => $update_available
				/* translators: %s: version number. */
				? sprintf( __( 'Update beschikbaar: v%s', 'aardbei-reserveringen' ), $cached['version'] )
				: __( 'Je gebruikt de nieuwste versie.', 'aardbei-reserveringen' ),
		);
	}

	/**
	 * Wis cache, haal verse release op en geef status terug.
	 *
	 * @return array
	 */
	public function check_now() {
		$this->force_clear_cache();
		$release = $this->get_latest_release();

		$config = $this->get_config();
		if ( ! $config['user'] || ! $config['repo'] ) {
			return array(
				'status'  => 'not_configured',
				'message' => __( 'Vul GitHub-gebruikersnaam en repository in.', 'aardbei-reserveringen' ),
			);
		}

		if ( ! $release ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Kon geen verbinding maken met GitHub. Controleer je instellingen.', 'aardbei-reserveringen' ),
			);
		}

		$update_available = version_compare( $release['version'], $this->current_version, '>' );

		return array(
			'status'           => 'connected',
			'current_version'  => $this->current_version,
			'latest_version'   => $release['version'],
			'update_available' => $update_available,
			'message'          => $update_available
				/* translators: %s: version number. */
				? sprintf( __( 'Update beschikbaar: v%s', 'aardbei-reserveringen' ), $release['version'] )
				: __( 'Je gebruikt de nieuwste versie.', 'aardbei-reserveringen' ),
		);
	}

	/**
	 * Verwijder de update-cache na een succesvolle plugin-update.
	 *
	 * @param WP_Upgrader $upgrader Upgrader-instantie.
	 * @param array       $options  Opties.
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( $this->cache_key() );
		}
	}
}
