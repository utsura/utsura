<?php
/**
 * Sakura redirect loop fixture class
 *
 * @package Sakura_Ssl
 * @author wokamoto <wokamoto@digitalcube.jp>
 *		 hideokamoto <hide.okamoto@digitalcube.jp>
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update redirect loop fixture class
 *
 * @class Redirect_fixture_sakura
 **/
class Redirect_fixture_sakura {
	/**
	 * Instance class
	 *
	 * @var Object $instance instance class
	 **/
	private static $instance;

	/**
	 * Get Instance Class
	 *
	 * @return Redirect_fixture_sakura
	 * @since 1.3.0
	 * @access public
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}

	/**
	 *  Init plugin menu.
	 *
	 * @access public
	 * @since 1.3.0
	 */
	public function init() {
		add_action( 'redirect_canonical', array( $this, 'fix_redirect_loop' ) );
	}

	/**
	 * Replace home url
	 *
	 * @access public
	 * @since 1.3.0
	 * @param string $redirect_url  The redirect URL.
	 * @return string
	 **/
	public function fix_redirect_loop($redirect_url) {
		$redirect_url = is_ssl() ? 'https://' : 'http://';
		$redirect_url .= $_SERVER['HTTP_HOST'];
		$redirect_url .= $_SERVER['REQUEST_URI'];
		return $redirect_url;
	}
}
