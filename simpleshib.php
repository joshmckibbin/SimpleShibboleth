<?php
/**
 * Plugin Name: Simple Shibboleth
 * Description: User authentication via Shibboleth Single Sign-On.
 * Version: 1.5.0
 * Requires at least: 5.2
 * Requires PHP: 8.0
 * Author: Steve Guglielmo, Josh Mckibbin
 * License: MIT
 * Network: true
 *
 * See the LICENSE file for more information.
 *
 * @package SimpleShib
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the plugin version.
define( 'SIMPLE_SHIBBOLETH_VERSION', '1.5.0' );

require_once 'class-simple-shib.php';

new Simple_Shib();
