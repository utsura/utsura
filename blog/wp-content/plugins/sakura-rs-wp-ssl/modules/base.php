<?php
/**
 * Sakura_Base Class file
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @package Sakura_Ssl
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define Sakura plugin's basic function and parameters
 *
 * @class Sakura_Base
 * @since 0.1.0
 */
class Sakura_Base {
	/**
	 * Instance class
	 *
	 * @var Object $instance instance class
	 **/
	private static $instance;

	// Panel key.
	const MENU_ID = 'sakura-admin-menu';
	const OPTION_NAME = 'sakura_settings';

	// Action key.
	const SAKURA_FORCE_SSL = 'sakura-force-ssl';

	/**
	 * Get Plugin version
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public static function version() {
		static $version;

		if ( ! $version ) {
			$data = get_file_data( SAKURA_PLUGIN_ROOT , array( 'version' => 'Version' ) );
			$version = $data['version'];
		}
		return $version;
	}

	/**
	 * Replace http to https
	 *
	 * @return string https url
	 * @param string $http_url http url.
	 * @since 0.1.0
	 **/
	public function replace_url_as_https( $http_url ) {
		$https_url = set_url_scheme( $http_url, 'https' );
		return $https_url;
	}
}
