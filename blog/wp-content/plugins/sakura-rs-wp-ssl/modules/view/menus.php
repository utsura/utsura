<?php
/**
 * Sakura_Menus
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @package Sakura_Ssl
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define Sakura Plugin's admin page menus.
 *
 * @class Sakura_Menus
 * @since 0.1.0
 */
class Sakura_Menus extends Sakura_Base {
	/**
	 * Instance class
	 *
	 * @var Object $instance instance class
	 **/
	private static $instance;

	/**
	 * Get Instance Class
	 *
	 * @return Sakura_Menus
	 * @since 0.1.0
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
	 * @since 0.1.0
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'define_menus' ) );
	}

	/**
	 *  Define Sakura plugin menus
	 *
	 * @access public
	 * @since 0.1.0
	 */
	public function define_menus() {
		$root = Sakura_Admin::get_instance();
		add_options_page(
			__( 'SAKURA RS SSL', 'sakura-ssl' ),
			__( 'SAKURA RS SSL', 'sakura-ssl' ),
			'manage_options',
			self::MENU_ID,
			array( $root, 'init_panel' )
		);
	}
}
