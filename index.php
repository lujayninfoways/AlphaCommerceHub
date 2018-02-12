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
   WC tested up to: 3.2.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
		
			include_once( dirname( __FILE__ ) . '/includes/paypal-ipn-handler.php' );
			new WC_Gateway_Alphapaypal_IPN_Handler( $this->testmode, $this->receiver_email );

			if ( $this->identity_token ) {
				include_once( dirname( __FILE__ ) . '/includes/paypal-pdt-handler.php' );
				new WC_Gateway_Alphapaypal_PDT_Handler( $this->testmode, $this->identity_token );
			}
		}
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'paypal' ) );
		}
	}

	
	/**
	 * Check if this gateway is enabled and available in the user's country.
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB', 'RUB', 'INR' ) ) );
	}

	/**
	 * Admin Panel Options.
	 * - Options for bits like 'title' and availability on a country-by-country basis.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>: <?php _e( 'PayPal does not support your store currency.', 'woocommerce' ); ?></p></div>
			<?php
		}
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

);

	}


	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once( dirname( __FILE__ ) . '/includes/paypal-request.php' );

		$order          = wc_get_order( $order_id );
		$paypal_request = new WC_Gateway_Alphapaypal_Request( $this );

		return array(
			'result'   => 'success',
			'redirect' => $paypal_request->get_request_url( $order, $this->testmode ),
		);
	}

	/**
	 * Can the order be refunded via PayPal?
	 * @param  WC_Order $order
	 * @return bool
	 */
	 public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Init the API class and set the username/password etc.
	 */
	 protected function init_api() {
		include_once( dirname( __FILE__ ) . '/includes/paypal-api-handler.php' );

		WC_Gateway_Alphapaypal_API_Handler::$api_username  = $this->get_option( 'api_username' );
		WC_Gateway_Alphapaypal_API_Handler::$api_password  = $this->get_option( 'api_password' );
		WC_Gateway_Alphapaypal_API_Handler::$api_signature = $this->get_option( 'api_signature' );
		WC_Gateway_Alphapaypal_API_Handler::$sandbox       = $this->testmode;
	}

	/**
	 * Process a refund if supported.
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log( 'Refund Failed: No transaction ID', 'error' );
			return new WP_Error( 'error', __( 'Refund failed: No transaction ID', 'woocommerce' ) );
		}

		$this->init_api();

		$result = WC_Gateway_Alphapaypal_API_Handler::refund_transaction( $order, $amount, $reason );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Refund Failed: ' . $result->get_error_message(), 'error' );
			return new WP_Error( 'error', $result->get_error_message() );
		}

		$this->log( 'Refund Result: ' . wc_print_r( $result, true ) );

		switch ( strtolower( $result->ACK ) ) {
			case 'success':
			case 'successwithwarning':
				$order->add_order_note( sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'woocommerce' ), $result->GROSSREFUNDAMT, $result->REFUNDTRANSACTIONID ) );
				return true;
			break;
		}

		return isset( $result->L_LONGMESSAGE0 ) ? new WP_Error( 'error', $result->L_LONGMESSAGE0 ) : false;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'paypal' === $order->get_payment_method() && 'pending' === get_post_meta( $order->get_id(), '_paypal_status', true ) && $order->get_transaction_id() ) {
			$this->init_api();
			$result = WC_Gateway_Alphapaypal_API_Handler::do_capture( $order );

			if ( is_wp_error( $result ) ) {
				$this->log( 'Capture Failed: ' . $result->get_error_message(), 'error' );
				$order->add_order_note( sprintf( __( 'Payment could not captured: %s', 'woocommerce' ), $result->get_error_message() ) );
				return;
			}

			$this->log( 'Capture Result: ' . wc_print_r( $result, true ) );

			if ( ! empty( $result->PAYMENTSTATUS ) ) {
				switch ( $result->PAYMENTSTATUS ) {
					case 'Completed' :
						$order->add_order_note( sprintf( __( 'Payment of %1$s was captured - Auth ID: %2$s, Transaction ID: %3$s', 'woocommerce' ), $result->AMT, $result->AUTHORIZATIONID, $result->TRANSACTIONID ) );
						update_post_meta( $order->get_id(), '_paypal_status', $result->PAYMENTSTATUS );
						update_post_meta( $order->get_id(), '_transaction_id', $result->TRANSACTIONID );
					break;
					default :
						$order->add_order_note( sprintf( __( 'Payment could not captured - Auth ID: %1$s, Status: %2$s', 'woocommerce' ), $result->AUTHORIZATIONID, $result->PAYMENTSTATUS ) );
					break;
				}
			}
		}
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
/* */
