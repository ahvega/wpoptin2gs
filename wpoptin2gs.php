<?php

/*
Plugin Name: Integración WPOptin -> Google Sheets
Description: Integra WP Optin con Google Sheets para enviar datos de la ruleta automáticamente.
Version: 1.0
Author: Adalberto Hernández Vega
Author URI: https://ncdigital.net
License: GPL2
*/

// Evitar el acceso directo al archivo
if (!defined('ABSPATH')) {
	exit;
}

// Encolar el script de JavaScript
function iwgs_enqueue_scripts() {
	wp_enqueue_script('iwgs-script', plugin_dir_url(__FILE__) . 'js/iwgs-script.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'iwgs_enqueue_scripts');

// Incluir el archivo principal del plugin
require_once plugin_dir_path(__FILE__) . 'src/iwgs.php';
