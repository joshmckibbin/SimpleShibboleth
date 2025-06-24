<?php
/**
 * Plugin Name: Simple Shibboleth
 * Description: User authentication via Shibboleth Single Sign-On.
 * Version: 1.5.1
 * Requires at least: 5.9
 * Requires PHP: 8.0
 * Author: Steve Guglielmo, Josh Mckibbin
 * License: MIT
 * Network: true
 *
 * See the LICENSE file for more information.
 *
 * @package SimpleShibboleth
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the plugin version.
define( 'SIMPLE_SHIBBOLETH_VERSION', '1.5.1' );

require_once 'class-simple-shib.php';

// Register activation hook.
register_activation_hook( __FILE__, array( 'Simple_Shib', 'activate' ) );

// Register deactivation hook.
register_deactivation_hook( __FILE__, array( 'Simple_Shib', 'deactivate' ) );

// Register uninstall hook.
register_uninstall_hook( __FILE__, array( 'Simple_Shib', 'uninstall' ) );

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'Simple_Shib', 'init' ) );
