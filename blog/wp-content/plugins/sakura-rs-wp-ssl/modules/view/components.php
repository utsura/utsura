<?php
/**
 * Sakura_Components Class file
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @package Sakura_Ssl
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define Sakura Plugin's common comnponents
 *
 * @class Sakura_Components
 * @since 0.1.0
 */
class Sakura_Components extends Sakura_Base {
	/**
	 * Instance class
	 *
	 * @var Object $instance instance class
	 **/
	private static $instance;

	/**
	 * Get Instance Class
	 *
	 * @return Sakura_Components
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
	 *  Show Sakura Plugin admin page html
	 *
	 * @access public
	 * @since 0.1.0
	 */
	public function show_panel_html() {
		$content = $this->get_content_html();
		$html = $this->get_layout_html( $content );
		$html = str_replace( ']]>', ']]&gt;', $html );
		echo $html;
	}

	/**
	 *  Create Sakura Plugin's admin page html
	 *
	 * @access public
	 * @param string $content content html.
	 * @return string HTML
	 * @since 0.1.0
	 */
	public function get_layout_html( $content ) {
		$html  = "<div class='wrap' id='sakura-ssl-dashboard'>";
		$html .= $content;
		$html .= '</div>';
		return $html;
	}
}
