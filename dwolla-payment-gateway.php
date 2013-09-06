<?php
/**
 * Plugin Name: WooCommerce Dwolla Payment Gateway
 * Plugin URI: https://github.com/vDevices/woocommerce-dwolla
 * Description: A Dwolla Payment Gateway for WooCommerce
 * Version: 1.0
 * Author: Pablo Carranza
 * Author URI: https://github.com/vDevices/
 * License: GPLv3
 */
 
 /** Check if WooCommerce is active */
if ( in_array( 'woocommerce/woocommerce.php',
	apply_filters( 'active_plugins',
	get_option( 'active_plugins' ) ) ) ) {

	/** Check to ensure a class with the same name as this plugin doesn’t already exist */
	if ( ! class_exists( 'WC_Payment_Gateway')) return;

	/** Localisation */
	load_plugin_textdomain( 'wc_dwolla', false, dirname( plugin_basename( __FILE__ ) ) . '/' );
	
	/** Dwolla Payment Gateway class*/
	class WC_dwolla extends WC_Payment_Gateway{
		public function __construct(){
			$this -> id = 'dwolla';
			$this -> medthod_title = 'Dwolla';
			$this -> has_fields = false;
			$this -> init_form_fields();
			$this -> init_settings();
			$this -> title = $this -> settings['title'];
			$this -> description = $this -> settings['description'];
			$this -> merchant_id = $this -> settings['merchant_id'];
			$this -> salt = $this -> settings['salt'];
			$this -> redirect_page_id = $this -> settings['redirect_page_id'];
			$this -> liveurl = '';
			$this -> msg['message'] = "";
			$this -> msg['class'] = "";
			
			add_action('init', array(&$this, 'check_dwolla_response'));
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				} else {
					add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
				}
			add_action('woocommerce_receipt_dwolla', array(&$this, 'receipt_page'));
			}
		function init_form_fields(){
		
			$this -> form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'dwolla'),
					'type' => 'checkbox'
					'label' => __('Enable Dwolla Payment Module.', 'dwolla'),
					'default' => 'no'),
				'title' => array(
					'title' => __('Title:', 'dwolla'),
					'type'=> 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'dwolla'),
					'default' => __('Dwolla', 'dwolla')),
				'description' => array(
					'title' => __('Description:', 'dwolla'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'dwolla'),
					'default' => __('Pay securely by Credit or Debit card or internet banking through Dwolla Secure Servers.', 'dwolla')),
				
		}
		
	/** Add the Gateway to WooCommerce */
	function woocommerce_add_dwolla_pmt_gateway($methods) {
		$methods[] = 'WC_Dwolla';
		return $methods;
		}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_dwolla_pmt_gateway' );
}
?>