<?php
/**
 * REST API.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
class Rest {

	const NAMESPACE = 'wpsquoosh/v1';

	/**
	 * Register routes.
	 */
	public function register() {
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_binary' ), 10, 4 );

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_upload' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_upload' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_media' ),
				'permission_callback' => array( $this, 'can_upload' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media/(?P<id>\d+)/file',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_file' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media/(?P<id>\d+)/replace',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'replace_media' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/legacy',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_legacy' ),
				'permission_callback' => array( $this, 'can_upload' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/legacy/purge',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'purge_legacy' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @return bool
	 */
	public function can_upload() {
		return current_user_can( 'upload_files' );
	}

	/**
	 * GET settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::get_all() );
	}

	/**
	 * POST settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		return rest_ensure_response( Settings::save( $params ) );
	}

	/**
	 * GET stats.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats() {
		return rest_ensure_response( Attachments::stats() );
	}

	/**
	 * GET media list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_media( $request ) {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		return rest_ensure_response( Attachments::list_media( $page, $per_page ) );
	}

	/**
	 * GET legacy pending cleanup.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_legacy( $request ) {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		return rest_ensure_response( Legacy_Cleanup::list_pending( $page, $per_page ) );
	}

	/**
	 * POST purge legacy files.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function purge_legacy( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$all = ! empty( $params['all'] );
		if ( $all ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'wpsquoosh_forbidden',
					__( 'La purge globale nécessite manage_options.', 'wpsquoosh' ),
					array( 'status' => 403 )
				);
			}
			return rest_ensure_response( Legacy_Cleanup::purge_all() );
		}

		$ids = isset( $params['ids'] ) ? $params['ids'] : array();
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return new \WP_Error( 'wpsquoosh_no_ids', __( 'Aucun ID fourni.', 'wpsquoosh' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( Legacy_Cleanup::purge_ids( $ids ) );
	}

	/**
	 * Prepare binary file response (served via rest_pre_serve_request).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_file( $request ) {
		$id   = (int) $request['id'];
		$path = Attachments::file_path( $id );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$mime = get_post_mime_type( $id );
		$size = filesize( $path );
		if ( false === $size ) {
			return new \WP_Error( 'wpsquoosh_read', __( 'Lecture du fichier impossible.', 'wpsquoosh' ), array( 'status' => 500 ) );
		}

		$response = new \WP_REST_Response(
			array(
				'wpsquoosh_binary' => true,
				'path'             => $path,
			)
		);
		$response->header( 'Content-Type', $mime ? $mime : 'application/octet-stream' );
		$response->header( 'Content-Length', (string) $size );
		$response->header( 'Content-Disposition', 'inline; filename="' . basename( $path ) . '"' );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Stream raw bytes instead of JSON for /media/{id}/file.
	 *
	 * @param bool              $served  Already served.
	 * @param \WP_HTTP_Response $result  Result.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public function serve_binary( $served, $result, $request, $server ) {
		if ( $served ) {
			return $served;
		}
		if ( ! ( $result instanceof \WP_HTTP_Response ) ) {
			return $served;
		}

		$data = $result->get_data();
		if ( ! is_array( $data ) || empty( $data['wpsquoosh_binary'] ) || empty( $data['path'] ) ) {
			return $served;
		}

		$path = $data['path'];
		if ( ! is_string( $path ) || ! file_exists( $path ) ) {
			return $served;
		}

		$headers = $result->get_headers();
		foreach ( $headers as $name => $value ) {
			header( sprintf( '%s: %s', $name, $value ) );
		}
		status_header( $result->get_status() );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		return true;
	}

	/**
	 * Replace with compressed upload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function replace_media( $request ) {
		$id = (int) $request['id'];

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'wpsquoosh_no_file', __( 'Paramètre file manquant.', 'wpsquoosh' ), array( 'status' => 400 ) );
		}

		$max = wp_max_upload_size();
		if ( ! empty( $files['file']['size'] ) && (int) $files['file']['size'] > $max ) {
			return new \WP_Error( 'wpsquoosh_too_large', __( 'Fichier trop volumineux.', 'wpsquoosh' ), array( 'status' => 413 ) );
		}

		$migrate_webp = filter_var( $request->get_param( 'migrate_webp' ), FILTER_VALIDATE_BOOLEAN );
		$keep_legacy  = filter_var( $request->get_param( 'keep_legacy' ), FILTER_VALIDATE_BOOLEAN );
		if ( $migrate_webp ) {
			$keep_legacy = true;
		}

		$backup = $request->get_param( 'backup' );
		if ( null === $backup || '' === $backup ) {
			$backup = Settings::get( 'backup_enabled' );
		} else {
			$backup = filter_var( $backup, FILTER_VALIDATE_BOOLEAN );
		}
		if ( $keep_legacy ) {
			$backup = false;
		}

		$allow_larger = $request->get_param( 'allow_larger' );
		if ( null === $allow_larger || '' === $allow_larger ) {
			$allow_larger = Settings::get( 'allow_larger' );
		} else {
			$allow_larger = filter_var( $allow_larger, FILTER_VALIDATE_BOOLEAN );
		}
		if ( $migrate_webp ) {
			$allow_larger = true;
		}

		$settings_raw = $request->get_param( 'settings' );
		$settings     = array();
		if ( is_string( $settings_raw ) && $settings_raw !== '' ) {
			$decoded = json_decode( $settings_raw, true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		} elseif ( is_array( $settings_raw ) ) {
			$settings = $settings_raw;
		}

		if ( $migrate_webp ) {
			$settings['advanced_enabled'] = true;
			$settings['convert_to']       = 'webp';
		}

		$mime = $request->get_param( 'mime' );
		if ( ! $mime && ! empty( $files['file']['type'] ) ) {
			$mime = $files['file']['type'];
		}
		if ( $migrate_webp ) {
			$mime = 'image/webp';
		}

		$result = Attachments::replace_file(
			$id,
			$files['file'],
			array(
				'backup'        => (bool) $backup,
				'allow_larger'  => (bool) $allow_larger,
				'settings'      => $settings,
				'original_size' => (int) $request->get_param( 'original_size' ),
				'mime'          => $mime,
				'keep_legacy'   => (bool) $keep_legacy,
				'migrate_webp'  => (bool) $migrate_webp,
			)
		);

		if ( is_wp_error( $result ) ) {
			$status = 'wpsquoosh_no_gain' === $result->get_error_code() ? 422 : 400;
			$result->add_data( array( 'status' => $status ) );
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
