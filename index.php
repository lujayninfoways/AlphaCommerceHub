<?php
/*
   Plugin Name: Alpha commerce hub woocommerce
   Description: Alpha commerce hub woocommerce.
   Version: 1.0
   Plugin URI: 
   Author: Lujayn Infoways
   Author URI: 
   License: Under GPL2
   WC requires at least: 3.0.0
   WC tested up to: 3.3.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( 
  in_array( 
    'woocommerce/woocommerce.php', 
    apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) 
  ) 
) {
	
add_action('plugins_loaded', 'alpha_hub_commerce_init', 0);

function alpha_hub_commerce_init() {
/**
 * WC_Gateway_Alphapaypal Class.
 */

class WC_Gateway_Alphapaypal extends WC_Payment_Gateway {

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'alphapaypal';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Proceed to PayPal', 'woocommerce' );
		$this->method_title       = __( 'Paypal (Alpha Commerce Hub)', 'woocommerce' );
		$this->method_description = sprintf( __( 'PayPal Standard sends customers to PayPal to enter their payment information. PayPal IPN requires fsockopen/cURL support to update order statuses after payment. Check the <a href="%s">system status</a> page for more details.', 'woocommerce' ), admin_url( 'admin.php?page=wc-status' ) );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
		$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
		$this->email          = $this->get_option( 'email' );
		$this->receiver_email = $this->get_option( 'receiver_email', $this->email );
		$this->identity_token = $this->get_option( 'identity_token' );

		 // Customer Emails
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		
		//Actions
		add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ));
		 add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_alpha', array( $this, 'thankyou_page' ) );
		// Payment listener/API hook
		add_action('woocommerce_api_wc_gateway_alpha', array($this, 'check_response'));

	
	}

	
	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields =  array(
	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable PayPal Standard', 'woocommerce' ),
		'default' => 'yes',
	),
	'title' => array(
		'title'       => __( 'Title', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
		'default'     => __( 'Paypal (Alpha Commerce Hub)', 'woocommerce' ),
		'desc_tip'    => true,
	),
	'description' => array(
		'title'       => __( 'Description', 'woocommerce' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
		'default'     => __( "Pay via PayPal; you can pay with your credit card if you don't have a PayPal account.", 'woocommerce' ),
	),
	'email' => array(
		'title'       => __( 'PayPal email', 'woocommerce' ),
		'type'        => 'email',
		'description' => __( 'Please enter your PayPal email address; this is needed in order to take payment.', 'woocommerce' ),
		'default'     => get_option( 'admin_email' ),
		'desc_tip'    => true,
		'placeholder' => 'you@youremail.com',
	),
	'testmode' => array(
		'title'       => __( 'PayPal sandbox', 'woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable PayPal sandbox', 'woocommerce' ),
		'default'     => 'no',
		'description' => sprintf( __( 'PayPal sandbox can be used to test payments. Sign up for a <a href="%s">developer account</a>.', 'woocommerce' ), 'https://developer.paypal.com/' ),
	),
	'MerchantId' => array(
        'title'		  => __('Alpha Bank Merchant ID', 'woocommerce'),
        'type' 		  => 'text',
        'description' => __('Enter Your Alpha Bank Merchant ID', 'woocommerce'),
        'default' 	  => '',
        'desc_tip'    => true
	),
	'url' => array(
		'title'       => __( 'Hosted Payment Page URL', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Hosted Payment Page URL that the customer will use for UAT or Production.', 'woocommerce' ),
		'default'     => __( '', 'woocommerce' ),
		'desc_tip'    => true,
	),
	'UserID' => array(
		'title'       => __( 'User ID', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'User ID', 'woocommerce' ),
		'default'     => __( '', 'woocommerce' ),
				
	),

);

	}

 protected function get_alpha_args( $order, $uniqid, $installments ) {
		$return = WC()->api_request_url( 'WC_Gateway_Alphacard' );
		$address = array(
				'address_1'     => ( WC()->version >= '3.0.0' ) ? $order->get_billing_address_1() : $order->billing_address_1,
                'address_2'     => ( WC()->version >= '3.0.0' ) ? $order->get_billing_address_2() : $order->billing_address_2,
                'city'          => ( WC()->version >= '3.0.0' ) ? $order->get_billing_city() : $order->billing_city,
                'state'         => ( WC()->version >= '3.0.0' ) ? $order->get_billing_state() : $order->billing_state,
                'postcode'      => ( WC()->version >= '3.0.0' ) ? $order->get_billing_postcode() : $order->billing_postcode,
                'country'       => ( WC()->version >= '3.0.0' ) ? $order->get_billing_country() : $order->billing_country
				);

		$args = array_merge($args, array(
			'confirmUrl' => add_query_arg( 'confirm', ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id , $return),
			'cancelUrl'  => add_query_arg( 'cancel', ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id , $return), 
		));
				
		return apply_filters( 'woocommerce_alpha_args', $args , $order );
	} 


    /**
	* Output for the order received page.
	* */
	public function receipt_page($order_id) {
		echo '<p>' . __('Thank you - your order is now pending payment. Please click the button below to proceed.', 'woocommerce') . '</p>';
		$order = wc_get_order( $order_id );
		$uniqid = uniqid();
						
		$form_data = $this->get_alpha_args($order, $uniqid, 0);
		$digest = base64_encode(sha1(implode("", array_merge($form_data, array('secret' => $this->Secret))), true));

		$html_form_fields = array(); ?>

		 <script type="text/javascript">
		jQuery(document).ready(function(){
  
		    var alphabank_payment_form = document.getElementById('shopform1');
			alphabank_payment_form.style.visibility="hidden";
			alphabank_payment_form.submit();

		}); 
		</script> 
		<?php $total = wc_format_decimal($order->get_total(), 2, true) * 1000 ; ?>
				<form id="shopform1" name="shopform1" method="POST" action="<?php echo 'https://hubuat.alphacommercehub.com.au/'.$this->get_option('url'); ?>" accept-charset="UTF-8" >
			
					<input type="hidden" name="Amount" value="<?php echo $total; ?>">
					<input type="hidden" name="Currency" value="<?php echo $order->get_currency(); ?>">
					<input type="hidden" name="CancelURL" value="<?php global $woocommerce; echo $woocommerce->cart->get_checkout_url(); ?>">
					<input type="hidden" name="business" value="<?php echo $this->get_option('email'); ?>">
					<input type="hidden" name="MerchantTxnID" value="<?php echo $order_id; ?>">
					<input type="hidden" name="MerchantID" value="<?php echo $this->get_option('MerchantId'); ?>">
					<input type="hidden" name="UserId" value="1">	
					<input type="hidden" name="Version" value="2">	
					<input type="hidden" name="ChannelType" value="07">		
					<input type="hidden" name="TransactionType" value="AuthPayment">		
					<input type="submit" class="button alt" id="submit_twocheckout_payment_form" value="<?php echo __( 'Pay via Alpha bank', 'woocommerce' ) ?>" /> 
					<a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() )?>"><?php echo __( 'Cancel order &amp; restore cart', 'woocommerce' )?></a>
				</form>		
		<?php
		
		
		$order->update_status( 'pending', __( 'Sent request to Alpha bank with orderID: ' . $form_data['orderid'] , 'woocommerce' ) );
	}

	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		 return array(
		 	'result' 	=> 'success',
		 	'redirect'	=> $order->get_checkout_payment_url( true ) // $this->get_return_url( $order )
		);
	}
	
	/**
     * Output for the order received page.
     */
    public function thankyou_page() {
		if ( $this->instructions ) {
        	echo wpautop( wptexturize( $this->instructions ) );
		}
	}
	
	/**
		* Verify a successful Payment!
	* */
	public function check_response() { 
		$required_response = array(
			'mid' => '',
			'orderid' => '',
			'status' => '',
			'orderAmount' => '',
			'currency' => '',
			'paymentTotal' => ''
		);
		
		$notrequired_response = array(
			'message' => '',
			'riskScore' => '',
			'payMethod' => '',
			'txId' => '',
			'sequence' => '',
			'seqTxId' => '',
			'paymentRef' => '' 
		);
		

		
		foreach ($required_response as $key => $value) {
			if (isset($_REQUEST[$key])){
				$required_response[$key] = $_REQUEST[$key];
			}
			else{
				// required parameter not set 
				wp_die( 'Alpha Bank Request Failure', 'Alpha Bank Gateway', array( 'response' => 500 ) );
			}
		}
		
		foreach ($notrequired_response as $key => $value) {
			if (isset($_REQUEST[$key])){
				$required_response[$key] = $_REQUEST[$key];
			}
			else{
			}
		}

		
		if(isset($_REQUEST['cancel'])){
			$order = wc_get_order(wc_clean($_REQUEST['cancel']));
			if (isset($order)){
				$order->add_order_note('Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. txId: ' . $required_response['txId'] . '. ' . $required_response['message'] );
				wp_redirect( $order->get_cancel_order_url_raw());
				exit();
			}
		}
		else if (isset($_REQUEST['confirm'])){
			$order = wc_get_order(wc_clean($_REQUEST['confirm']));
			if (isset($order)){
				if ($required_response['orderAmount'] == wc_format_decimal($order->get_total(), 2, false)){
					$order->add_order_note('Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. txId: ' . $required_response['txId'] . '. payMethod: ' . $required_response['payMethod']. '. paymentRef: ' . $required_response['paymentRef'] . '. ' . $required_response['message'] );
					$order->payment_complete('Alpha Bank Payment ' . $required_response['status'] . '. txId: ' . $required_response['txId'] );
					wp_redirect($this->get_return_url( $order ));
					exit();
				}
				else{
					$order->add_order_note('Payment received with incorrect amount. Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. '. $required_response['message'] );
				}
			}
		}
		
		// something went wrong so die
		wp_die( 'Unspecified Error', 'Payment Gateway error', array( 'response' => 500 ) );
	}
}


function woocommerce_add_alpha_gateway($methods) 
   {
      $methods[] = 'WC_Gateway_Alphapaypal';
      return $methods;
   }

   add_filter('woocommerce_payment_gateways', 'woocommerce_add_alpha_gateway' );
}



add_action( 'plugins_loaded', 'gateway_init_alphacard', 0 );
function gateway_init_alphacard() {

	// If we made it this far, then include our Gateway Class
	include_once( 'alphagateway_class.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_alphagateway' );
	function add_alphagateway( $methods ) {
		$methods[] = 'WC_Gateway_Alphacard';
		return $methods;
	}
}
	}
else{
	
	function disabled_notice() {
    global $current_screen;
    if ($current_screen->parent_base == 'plugins'):
      ?>
      <div class="error" style="padding: 8px 8px;">
        <strong>
          <?= __('Alpha commerce hub woocommerce requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> activated in order to work. Please install and activate <a href="' . admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce') . '" target="_blank">WooCommerce</a> first.','video_gallery') ?>
        </strong>
      </div>
      <?php
    endif;
  }
add_action( 'admin_notices', 'disabled_notice' );
}
