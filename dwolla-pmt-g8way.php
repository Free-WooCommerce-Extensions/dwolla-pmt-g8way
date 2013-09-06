<?php
/**
 * Plugin Name: Dwolla Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/vDevices/woocommerce-dwolla
 * Description: Send money to anyone for no more than $0.25 per transaction. Dwolla, Inc. is an agent of Veridian Credit Union.
 * Version: 1.0
 * Author: Pablo Carranza
 * Author URI: https://github.com/vDevices/
 * License: GPLv3
 */
 
add_action('plugins_loaded', 'woocommerce_vdevices_dwolla_init', 0);

function woocommerce_vdevices_dwolla_init() {

   if ( !class_exists( 'WC_Payment_Gateway' ) ) 
      return;

   /**
   * Localization
   */
   load_plugin_textdomain('wc-vdevices-dwolla', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

   if ( $_GET['msg'] != '' ){
     add_action('the_content', 'showMessage');
   }

   function showMessage($content)
   {
      return '<div class="box '.htmlentities($_GET['type']).'-box">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
   }
   
   /**
   * Dwolla Payment Gateway class
   */
   class WC_vDevices_Dwolla extends WC_Payment_Gateway 
   {
      protected $msg = array();
      
      public function __construct(){

         $this->id               = 'dwolla';
         $this->method_title     = __('Dwolla', 'vdevices');
         $this->icon             = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/dwolla-sm-no-bg.png';
         $this->has_fields       = false;
         $this->init_form_fields();
         $this->init_settings();
         $this->title            = $this->settings['title'];
         $this->description      = $this->settings['description'];
         $this->login            = $this->settings['login_id'];
         $this->mode             = $this->settings['working_mode'];
         $this->transaction_key  = $this->settings['transaction_key'];
         $this->success_message  = $this->settings['success_message'];
         $this->failed_message   = $this->settings['failed_message'];
         $this->liveurl          = 'https://www.dwolla.com/payment/request';
         $this->msg['message']   = "";
         $this->msg['class']     = "";
        
         add_action('init', array(&$this, 'check_dwolla_response'));
         //update for woocommerce >2.0
         add_action( 'woocommerce_api_wc_vdevices_dwolla' , array( $this, 'check_dwolla_response' ) );
         add_action('valid-dwolla-request', array(&$this, 'successful_request'));
         
         if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
             add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
          } else {
             add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
         }

         add_action('woocommerce_receipt_dwolla', array(&$this, 'receipt_page'));
         add_action('woocommerce_thankyou_dwolla',array(&$this, 'thankyou_page'));
      }

      function init_form_fields(){

         $this->form_fields = array(
            'enabled'      => array(
                  'title'        => __('Enable/Disable', 'vdevices'),
                  'type'         => 'checkbox',
                  'label'        => __('Enable Dwolla Payment Module.', 'vdevices'),
                  'default'      => 'no'),
            'title'        => array(
                  'title'        => __('Title:', 'vdevices'),
                  'type'         => 'text',
                  'description'  => __('This controls the title which the user sees during checkout.', 'vdevices'),
                  'default'      => __('Dwolla', 'vdevices')),
            'description'  => array(
                  'title'        => __('Description:', 'vdevices'),
                  'type'         => 'textarea',
                  'description'  => __('This controls the description which the user sees during checkout.', 'vdevices'),
                  'default'      => __('Pay securely by Credit or Debit Card through Dwolla Secure Servers.', 'vdevices')),
            'key'     => array(
                  'title'        => __('Key', 'vdevices'),
                  'type'         => 'text',
                  'description'  => __('Your consumer key from Dwolla, for this plugin')),
            'secret' => array(
                  'title'        => __('Secret', 'vdevices'),
                  'type'         => 'text',
                  'description'  =>  __('Your consumer secret from Dwolla', 'vdevices')),
            'success_message' => array(
                  'title'        => __('Transaction Success Message', 'vdevices'),
                  'type'         => 'textarea',
                  'description'=>  __('Message to be displayed on successful transaction.', 'vdevices'),
                  'default'      => __('Your payment has been processed successfully.', 'vdevices')),
            'failed_message'  => array(
                  'title'        => __('Transaction Failed Message', 'vdevices'),
                  'type'         => 'textarea',
                  'description'  =>  __('Message to be displayed on failed transaction.', 'vdevices'),
                  'default'      => __('Your transaction has been declined.', 'vdevices')),
         );
      }
      
      /**
       * Admin Panel Options
       * - Options for bits like 'title' and availability on a country-by-country basis
      **/
      public function admin_options()
      {
         echo '<h3>'.__('Dwolla Payment Gateway', 'vdevices').'</h3>';
         echo '<p>'.__('Dwolla is the least expensive payment gateway for online payment processing').'</p>';
         echo '<table class="form-table">';
         $this->generate_settings_html();
         echo '</table>';

      }
      
      /**
      *  There are no payment fields for Dwolla, but want to show the description if set.
      **/
      function payment_fields()
      {
         if ( $this->description ) 
            echo wpautop(wptexturize($this->description));
      }
      
      /**
      * Receipt Page
      **/
      function receipt_page($order)
      {
         echo '<p>'.__('Thank you for your order, please click the button below to pay via Dwolla.', 'vdevices').'</p>';
         echo $this->generate_dwolla_form($order);
      }
      
      /**
       * Process the payment and return the result
      **/
      function process_payment($order_id)
      {
         $order = new WC_Order($order_id);
         return array('result'   => 'success',
                     'redirect'  => add_query_arg('order',
                                    $order->id, 
                                    add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
         );
      }
      
      /**
       * Check for valid Dwolla server callback to validate the transaction response.
      **/
      function check_dwolla_response()
      {
        
         global $woocommerce;
         
         if ( count($_POST) ){
         
            $redirect_url = '';
            $this->msg['class']     = 'error';
            $this->msg['message']   = $this->failed_message;

            if ( $_POST['x_response_code'] != '' ){
               try{
               
                  $order            = new WC_Order($_POST['x_invoice_num']);
                  $amount           = $_POST['x_amount'];
                  $hash             = $_POST['x_MD5_Hash'];
                  $transauthorised  = false;
                     
                  if ( $order->status != 'completed'){
                     
                     if ( $_POST['x_response_code'] == 1 ){
                        $transauthorised        = true;
                        $this->msg['message']   = $this->success_message;
                        $this->msg['class']     = 'success';
                        
                        if ( $order->status == 'processing' ){
                           
                        }
                        else{
                            $order->payment_complete();
                            $order->add_order_note('Dwolla payment successful<br/>Ref Number/Transaction ID: '.$_REQUEST['x_trans_id']);
                            $order->add_order_note($this->msg['message']);
                            $woocommerce->cart->empty_cart();
                        }
                     }
                     else{
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = $this->failed_message;
                        $order->add_order_note($this->msg['message']);
                        $order->update_status('failed');
                        //extra code can be added here such as sending an email to customer on transaction fail
                     }
                  }
                  if ( $transauthorised==false ){
                    $order->update_status('failed');
                    $order->add_order_note($this->msg['message']);
                  }

               }
               catch(Exception $e){
                         // $errorOccurred = true;
                         $msg = "Error";
               }

            }
            $redirect_url = (get_option('woocommerce_thanks_page_id') != '' ) ? get_permalink(get_option('woocommerce_thanks_page_id')): get_site_url().'/' ;
            $redirect_url = add_query_arg( array('msg'=> urlencode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );
            $this->web_redirect( $redirect_url); exit;
         }
         else{
            
            $redirect_url =  (get_option('woocommerce_thanks_page_id') != '' ) ? get_permalink(get_option('woocommerce_thanks_page_id')): get_site_url().'/' ;
            $this->web_redirect($redirect_url.'?msg=Unknown_error_occured');
            exit;
         }
      }
      
      
      public function web_redirect($url){
      
         echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
      
      }
      /**
      * Generate Dwolla button link
      **/
      public function generate_dwolla_form($order_id)
      {
         global $woocommerce;
         
         $order      = new WC_Order($order_id);
         $sequence   = rand(1, 1000);
         $timeStamp  = time();

         if( phpversion() >= '5.1.2' ) { 
            $fingerprint = hash_hmac("md5", $this->login . "^" . $sequence . "^" . $timeStamp . "^" . $order->order_total . "^", $this->transaction_key); }
         else { 
            $fingerprint = bin2hex(mhash(MHASH_MD5,  $this->login . "^" . $sequence . "^" . $timeStamp . "^" . $order->order_total . "^", $this->transaction_key)); 
         }
         $redirect_url = (get_option('woocommerce_thanks_page_id') != '' ) ? get_permalink(get_option('woocommerce_thanks_page_id')): get_site_url().'/' ;
         $relay_url = add_query_arg( array('wc-api' => get_class( $this ) ,'order_id' => $order_id ), $redirect_url );
         
         $dwolla_args = array(
            'x_login'                  => $this->login,
            'x_amount'                 => $order->order_total,
            'x_invoice_num'            => $order_id,
            'x_relay_response'         => "TRUE",
            'x_relay_url'              => $relay_url,
            'x_fp_sequence'            => $sequence,
            'x_fp_hash'                => $fingerprint,
            'x_show_form'              => 'PAYMENT_FORM',
            'x_test_request'           => $this->mode,
            'x_fp_timestamp'           => $timeStamp,
            'x_first_name'             => $order->billing_first_name ,
            'x_last_name'              => $order->billing_last_name ,
            'x_company'                => $order->billing_company ,
            'x_address'                => $order->billing_address_1 .' '. $order->billing_address_2,
            'x_country'                => $order->billing_country,
            'x_state'                  => $order->billing_state,
            'x_city'                   => $order->billing_city,
            'x_zip'                    => $order->billing_postcode,
            'x_phone'                  => $order->billing_phone,
            'x_email'                  => $order->billing_email,
            'x_ship_to_first_name'     => $order->shipping_first_name ,
            'x_ship_to_last_name'      => $order->shipping_last_name ,
            'x_ship_to_company'        => $order->shipping_company ,
            'x_ship_to_address'        => $order->shipping_address_1 .' '. $order->shipping_address_2,
            'x_ship_to_country'        => $order->shipping_country,
            'x_ship_to_state'          => $order->shipping_state,
            'x_ship_to_city'           => $order->shipping_city,
            'x_ship_to_zip'            => $order->shipping_postcode,
            );

         $dwolla_args_array = array();
         
         foreach($dwolla_args as $key => $value){
           $dwolla_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
         }
         
         if($this->mode == 'true'){
           $processURI = $this->testurl;
         }
         else{
           $processURI = $this->liveurl;
         }
         
         $html_form    = '<form action="'.$processURI.'" method="post" id="dwolla_payment_form">' 
               . implode('', $dwolla_args_array) 
               . '<input type="submit" class="button" id="submit_dwolla_payment_form" value="'.__('Pay via Dwolla', 'vdevices').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'vdevices').'</a>'
               . '<script type="text/javascript">
                  jQuery(function(){
                     jQuery("body").block({
                           message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Dwolla\'s secure server to make your payment.', 'vdevices').'",
                           overlayCSS:
                        {
                           background:       "#ccc",
                           opacity:          0.6,
                           "z-index": "99999999999999999999999999999999"
                        },
                     css: {
                           padding:          20,
                           textAlign:        "center",
                           color:            "#555",
                           border:           "3px solid #aaa",
                           backgroundColor:  "#fff",
                           cursor:           "wait",
                           lineHeight:       "32px",
                           "z-index": "999999999999999999999999999999999"
                     }
                     });
                  jQuery("#submit_dwolla_payment_form").click();
               });
               </script>
               </form>';

         return $html_form;
      }

   }

   /**
    * Add this Gateway to WooCommerce
   **/
   function woocommerce_add_vdevices_dwolla_gateway($methods) 
   {
      $methods[] = 'WC_vDevices_Dwolla';
      return $methods;
   }

   add_filter('woocommerce_payment_gateways', 'woocommerce_add_vdevices_dwolla_gateway' );
}
?>
