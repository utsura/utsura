<?php
/**
 * Sakura_Admin Class file
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @package Sakura_Ssl
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sakura Plugin admin page scripts
 *
 * @class Sakura_Admin
 * @since 0.1.0
 */
class Sakura_Admin extends Sakura_Components {
	/**
	 * Instance class
	 *
	 * @var Object $instance instance class
	 **/
	private static $instance;

	/**
	 * Get Instance Class
	 *
	 * @return Sakura_Admin
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
	 *  Show admin page html
	 *
	 * @access public
	 * @since 0.1.0
	 */
	public function init_panel() {
		$this->show_panel_html();
	}

	/**
	 *  Get admin page html content
	 *
	 * @access public
	 * @return string html
	 * @since 0.1.0
	 */
	public function get_content_html() {
		$html = '';
		$html .= $this->_get_header();
		$html .= $this->_get_check_form();
		return $html;
	}

	/**
	 * Get SSL Checklist form
	 *
	 * @access private
	 * @return string html
	 * @since 0.1.0
	 **/
	private function _get_check_form() {
		$admin_url = $this->replace_url_as_https( get_admin_url() );
		$home_url = $this->replace_url_as_https( get_home_url() );
		$status = 'false';
		if ( get_option( Sakura_Base::SAKURA_FORCE_SSL ) ) {
			$status = 'true';
		}
		$options = "{ admin_url: \"{$admin_url}\", home_url: \"{$home_url}\", ssl_status: \"{$status}\"}";
		$html  = '';
		$html .= "<form method='post' action='' >";
		$html .= "<sakurassladmin></sakurassladmin>";
		$html .= "<script>riot.mount('sakurassladmin', {$options})</script>";
		$html .= wp_nonce_field( self::SAKURA_FORCE_SSL , self::SAKURA_FORCE_SSL , true , false );
		$html .= '</form>';
		return $html;
	}

	/**
	 *  Get pugin root admin header HTML
	 *
	 * @access public
	 * @return string html
	 * @since 0.1.0
	 */
	private function _get_header() {
		$html  = '';
		$html .= '<h2>' . __( 'さくらのレンタルサーバ　簡単SSL化プラグイン' , 'sakura-ssl' ) . '</h2>';
		return $html;
	}
}
