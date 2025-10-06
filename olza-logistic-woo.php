<?php

/**
 * Plugin Name: Olza Logistic WooCommerce
 * Description: Olza Logistic WooCommerce is an addon that allows you to select pickup locations and delivery prices on the checkout page.
 * Version:     1.0.7
 * Author:      Grand Brand
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: olza-logistic-woo
 *
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

define('OLZA_LOGISTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OLZA_LOGISTIC_PLUGIN_VERSION', '1.0.0');

define('OLZA_LOGISTIC_PLUGIN_DIR', plugin_dir_url(__DIR__));
define('OLZA_LOGISTIC_PLUGIN_PATH', plugin_dir_path(__FILE__));

/*
 * Function to load plugin textdomain
 */
function olza_logistic_load_textdomain()
{

	global $wp_version;

	/**
	 * Load text domain
	 */

	$olza_lang_dir = dirname(plugin_basename(__FILE__)) . '/languages/';
	$olza_lang_dir = apply_filters('olza_logistics_languages_directory', $olza_lang_dir);

	$get_locale = get_locale();

	if ($wp_version >= 4.7) {
		$get_locale = get_user_locale();
	}

	$locale = apply_filters('plugin_locale',  $get_locale, 'olza-logistic-woo');
	$mofile = sprintf('%1$s-%2$s.mo', 'olza-logistic-woo', $locale);

	$mofile_global  = WP_LANG_DIR . '/plugins/' . basename(OLZA_LOGISTIC_PLUGIN_PATH) . '/' . $mofile;

	if (file_exists($mofile_global)) {
		load_textdomain('olza-logistic-woo', $mofile_global);
	} else {
		load_plugin_textdomain('olza-logistic-woo', false, $olza_lang_dir);
	}

	/**
	 * Loading Files
	 */

	if (class_exists('WooCommerce')) {

		require OLZA_LOGISTIC_PLUGIN_PATH . '/inc/olza-logistic-functions.php';
		require OLZA_LOGISTIC_PLUGIN_PATH . '/inc/olza-logistic-options.php';
	}

	if (is_admin()) {
		require OLZA_LOGISTIC_PLUGIN_PATH . '/inc/olza-logistic-admin-functions.php';
	}
}

add_action('plugins_loaded', 'olza_logistic_load_textdomain');

/**
 * Check dependent plugin activation
 */

add_action('admin_init', 'olza_logistic_check_activation');

function olza_logistic_check_activation()
{

	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'olza_logistic_confirm_woocommerce_activate');
	}
}


// Admin notice if woocommerce not installed
function olza_logistic_confirm_woocommerce_activate()
{
	echo '<div class="notice notice-error">';
	echo __('<p> The <strong>Olza Logistic WooCommerce </strong> plugin requires <strong>WooCommerce</strong> plugin installed & activated. </p>', 'olza-logistic-woo');
	echo '</div>';
}

/**
 * Adding Scripts
 */


add_action('wp_enqueue_scripts', 'aloa_marketing_adding_scripts');

function aloa_marketing_adding_scripts()
{
	global $olza_options;
	$olza_options = get_option('olza_options');

	$mapbox_api = isset($olza_options['mapbox_api']) && !empty($olza_options['mapbox_api']) ? $olza_options['mapbox_api'] : '';

	if (class_exists('WooCommerce') && (is_checkout() || is_cart())) {

		wp_enqueue_style('mapbox', OLZA_LOGISTIC_PLUGIN_URL . 'assets/css/mapbox.css');
		wp_enqueue_script('mapbox', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/mapbox.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, false);

		wp_enqueue_style('mapbox-geocoder', OLZA_LOGISTIC_PLUGIN_URL . 'assets/css/mapbox-geocoder.css');
		wp_enqueue_script('mapbox-geocoder', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/mapbox-geocoder.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, false);

		wp_enqueue_style('olza-confirm', OLZA_LOGISTIC_PLUGIN_URL . 'assets/css/olza-confirm.css');
		wp_enqueue_script('olza-confirm', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/olza-confirm.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, false);
	}

	wp_enqueue_style('olza-logistic', OLZA_LOGISTIC_PLUGIN_URL . 'assets/css/olza-logistic.css');
	wp_enqueue_script('olza-logistic', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/olza-logistic.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, true);


	wp_localize_script('olza-logistic', 'olza_global', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('olza_checkout'),
		'mapbox_token' => $mapbox_api,
		'geocode_placeholder' => __('Search Pick Up', 'olza-logistic-woo'),
		'choose_another' => __('Search Pick Up', 'olza-logistic-woo'),
		'r_u_sure' => __('Are You Sure', 'olza-logistic-woo'),
		'pic_selection' => __('Your pickup selection is : ', 'olza-logistic-woo'),
		'goto_checkout' => __('Go to checkout', 'olza-logistic-woo'),
		'chose_ship_method' => __('Please choose pickup point shipping method to show pickup point', 'olza-logistic-woo'),
		'confirm' => __('Confirm', 'olza-logistic-woo'),
		'cancel' => __('Cancel', 'olza-logistic-woo'),
	));
}


/**
 * Adding Admin Scripts
 */

add_action('admin_enqueue_scripts', 'aloa_marketing_adding_admin_scripts');

function aloa_marketing_adding_admin_scripts()
{

	wp_enqueue_script('olza-logistic-admin', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/olza-logistic-admin.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, true);
	wp_enqueue_script('olza-repeater', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/repeater.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, false);

	wp_localize_script('olza-logistic-admin', 'olza_global_admin', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('olza_load_files'),
		'confirm_msg' => __('Are you sure to refresh the data list! \n it takes around 1 minute to complete', 'olza-logistic-woo'),

	));
}