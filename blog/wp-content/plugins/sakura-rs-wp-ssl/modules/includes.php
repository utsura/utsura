<?php
/**
 * Load included files
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @package Sakura_Ssl
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once( 'base.php' );
require_once( 'model/force-ssl.php' );
require_once( 'model/redirect-fixture.php' );


require_once( 'view/menus.php' );
require_once( 'view/components.php' );
require_once( 'view/admin-top.php' );
