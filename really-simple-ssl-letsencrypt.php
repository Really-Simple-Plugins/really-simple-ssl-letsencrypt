<?php
/**
 * Plugin Name: Really Simple SSL Let's Encrypt
 * Plugin URI: https://really-simple-ssl.com
 * Description: Lightweight plugin without any setup to generate an SSL certificate from Let's encrypt
 * Version: 1.0
 * Author: Really Simple Plugins
 * Author URI: https://really-simple-plugins.com
 * License: GPL2
 * Text Domain: really-simple-ssl
 * Domain Path: /languages
 */
/*  Copyright 2020  Really Simple Plugins BV  (email : support@really-simple-ssl.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
defined('ABSPATH') or die("you do not have access to this page!");
define('rsssl_beta_addon', true);
if (!defined('rsssl_file')) define('rsssl_file', __FILE__);

add_action('plugins_loaded', 'rsssl_load_beta_addon', 8);
function rsssl_load_beta_addon() {
	require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'lets-encrypt/letsencrypt.php' );
}



