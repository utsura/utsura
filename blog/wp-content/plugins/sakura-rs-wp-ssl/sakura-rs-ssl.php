<?php
/**
 * Plugin Name:     SAKURA RS WP SSL
 * Description:     このプラグインはさくらのレンタルサーバ上のWordPressで、常時SSL化を簡単に行えるプラグインです。
 * Author: SAKURA Internet Inc.
 * Author URI: http://www.sakura.ne.jp/
 * Plugin URI: https://help.sakura.ad.jp/hc/ja/articles/115000047641
 * Text Domain:     sakura-rs-ssl
 * Domain Path:     /languages
 * Version:         1.4.0
 *
 * @package         Sakura_Ssl
 * License:
 *  Released under the GPL license
 *   http://www.gnu.org/copyleft/gpl.html
 *   Copyright 2017 wokamoto (email : wokamoto@digitalcube.jp)
 *     This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation; either version 2 of the License, or
 *     (at your option) any later version.
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *     You should have received a copy of the GNU General Public License
 *     along with this program; if not, write to the Free Software
 *     Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define( 'SAKURA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAKURA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAKURA_PLUGIN_ROOT', __FILE__ );

require 'modules/includes.php';

$sakura = Sakura_Ssl_Controller::get_instance();
$sakura->init();

/**
 * Controller class
 *
 * @class Sakura_Ssl_Controller
 **/
class Sakura_Ssl_Controller {
	/**
	 * Base class
	 *
	 * @var Object $base base class
	 **/
	private $base;

	/**
	 * Instance class
	 *
	 * @var Object $instance instance class
	 **/
	private static $instance;

	/**
	 * WP_Error Class
	 *
	 * @var Object $wp_error WP_Error Class
	 **/
	private $wp_error = '';

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
	 *  Initialize Plugin
	 *
	 * @access public
	 * @since 0.1.0
	 */
	public function init() {
		$this->base = new Sakura_Base();
		$menu = Sakura_Menus::get_instance();
		$menu->init();
		$this->_enque();
		add_action( 'admin_init',    array( $this, 'update_settings' ) );

		$sakura = new Force_ssl_sakura();
		register_deactivation_hook( __FILE__, array( $sakura, 'unforce_ssl' ) );
		$sakura->replace_link_hooks();
		$sakura->replace_options();

		$fixture = Redirect_fixture_sakura::get_instance();
		$fixture->init();
	}

	/**
	 * Display custom admin notice
	 *
	 * @access public
	 * @since 0.1.0
	 **/
	public function admin_alert() {
		if ( ! is_wp_error( $this->wp_error ) ) {
			return;
		}
		$errors = $this->wp_error->get_error_messages();
		if ( ! is_array( $errors ) ) {
			return;
		}
		$html = '<div class="notice notice-error is-dismissible">';
		foreach ( $errors as $error ) {
			$html .= "<p>{$error}</p>";
		}
		$html .= '</div>';
		$html = str_replace( ']]>', ']]&gt;', $html );
		echo $html;
	}

	/**
	 * Update admin configuration
	 *
	 * @access public
	 * @since 0.1.0
	 **/
	public function update_settings() {
		if ( empty( $_POST ) ) {
			return;
		}
		$key = Sakura_Base::SAKURA_FORCE_SSL;
		if ( isset( $_POST[ $key ] ) && $_POST[ $key ] ) {
			if ( check_admin_referer( $key, $key ) ) {
				$sakura = new Force_ssl_sakura();
				$this->wp_error = $sakura->activate();
				if ( is_wp_error( $this->wp_error ) ) {
					add_action('admin_notices', array( $this, 'admin_alert' ) );
				}
			}
		}
	}

	/**
	 * Run enqueue scripts hook
	 *
	 * @access private
	 * @since 0.1.0
	 **/
	private function _enque() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Enqueue scripts
	 *
	 * @access public
	 * @since 0.1.0
	 **/
	public function admin_scripts() {
		wp_enqueue_script( 'sakura-riot', path_join( SAKURA_PLUGIN_URL, 'client/js/riot.min.js' ), array(), '3.2.0', false );
		wp_enqueue_script( 'sakura-admin-components', path_join( SAKURA_PLUGIN_URL, 'client/js/tags.js' ), array( 'jquery', 'sakura-riot' ), '0.2.0', false );
	}
}
