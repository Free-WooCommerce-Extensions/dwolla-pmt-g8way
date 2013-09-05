<?php
/**
 * Plugin Name: WooCommerce Dwolla Payment Gateway
 * Plugin URI: http://vDevices.com/wordpress/plugins/woocommerce-dwolla
 * Description: A Dwolla Payment Gateway for WooCommerce v2.0.
 * Version: 1.0
 * Author: Pablo Carranza
 * Author URI: http://vDevices.com/about/pablo-carranza
 * License: GPLv3
 */
 
 /**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php',
	apply_filters( 'active_plugins',
	get_option( 'active_plugins' ) ) ) ) {
    // Put your plugin code here
}
?>