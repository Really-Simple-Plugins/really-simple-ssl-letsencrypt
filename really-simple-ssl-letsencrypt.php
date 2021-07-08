<?php
/**
 * Plugin Name: Really Simple SSL Shell Add on
 * Plugin URI: https://really-simple-ssl.com
 * Description: Add on for Really Simple SSL adding shell functionality to install SSL certificates
 * Version: 1.0
 * Author: Really Simple Plugins
 * Author URI: https://really-simple-plugins.com
 * License: GPL2
 * Text Domain: really-simple-ssl-shell
 * Domain Path: /languages
 */
/*  Copyright 2021  Really Simple Plugins BV  (email : support@really-simple-ssl.com)
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
defined('ABSPATH') or die();

if (!defined('rsssl_shell_path')) define('rsssl_shell_path', trailingslashit(plugin_dir_path(__FILE__)) );

function rsssl_le_load_shell_addon(){
	if (function_exists('rsssl_letsencrypt_generation_allowed') && rsssl_letsencrypt_generation_allowed() ) {
		require_once( rsssl_shell_path . 'functions.php' );
	}
}
add_action( 'plugins_loaded', 'rsssl_le_load_shell_addon' );
