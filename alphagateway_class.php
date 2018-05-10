<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Gateway_Alphacard extends WC_Payment_Gateway {
	

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'alphacard';
        $this->icon               = apply_filters( 'woocommerce_cod_icon', '' );
        $this->method_title       = __( 'Credit Card (Alpha Commerce Hub)', 'woocommerce' );
        $this->method_description = __( 'Alpha bank web payment system.', 'woocommerce' );
        $this->has_fields         = false;

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );

        $this->mode= $this->get_option( 'mode' );
        $this->instructions       = $this->get_option( 'instructions', $this->description );
		$this->MerchantId = $this->get_option('MerchantId');
		
		$this->AlphaBankUrl = "https://hubuat.alphacommercehub.com.au";
	
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
	 * Check if this gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}


		if ( ! $this->MerchantId ) {
			return false;
		}

		return true;
	}
    
	 /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
    	$shipping_methods = array();

    	if ( is_admin() )
	    	foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
		    	$shipping_methods[ $method->id ] = $method->get_title();
	    	}

    	$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable Alpha Bank', 'woocommerce' ),
				'label'       => __( 'Enabled', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Bank Card (Alpha Commerce Hub)', 'woocommerce' ),
				'desc_tip'    => true,
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
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
				'default'     => __( 'Bank Card (Alpha Commerce Hub)', 'woocommerce' ),
				'desc_tip'    => true,
			),
			
			'testmode' => array(
				'title'       => __( 'Test mode', 'woocommerce' ),
				'label'       => __( 'Enable test mode', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => 'uncheck this to disable test mode',
				'default'     => 'yes'
			),
			'3dSecure' => array(
				'title'       => __( 'Enable 3d Secure', 'woocommerce' ),
				'label'       => __( 'Enable 3d Secure', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => 'uncheck this to disable 3d Secure',
				'default'     => 'yes'
			),
                        'mode' => array(
				'title'       => __( 'Select Mode', 'woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'desc_tip'    => true,
				'options'     => array('UAT','Production'),
				'authorization' => __( '', 'woocommerce' ),		
			),
			'MerchantId' => array(
                'title' => __('Alpha Bank Merchant ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('Enter Your Alpha Bank Merchant ID', 'woocommerce'),
                'default' => '',
                'desc_tip' => true
			),
			'min_order' => array(
				'title'       => __( 'Minimum Order Total', 'woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
				),
			'max_order' => array(
				'title'       => __( 'Maximum Order Total', 'woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
			),
			'region_selection' => array(
				'title'       => __( 'Region Selection', 'woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce' ),
				'default'     => 'sale',
				'desc_tip'    => true,
				'options'     => array(
				'authorization' => __( '', 'woocommerce' ),		
			),
		)
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
$aa= str_replace('\"',"",$_POST['data']);
		$a=explode(",",$aa);
		
		foreach($a as $au){
			$uu=str_replace(":",",",$au);
			$u=explode(",",$uu);
			//print_r($u);
		if($u[0] == 'MerchantTxnID' || $u[0] == 'Status'){
			$aaa[$u[0]] = $u[1];
			}
			}
			//echo $aaa['MerchantTxnID'];
			if($aaa['MerchantTxnID'] != '' && $aaa['Status'] == 0){
				global $woocommerce;
				
				global $wpdb;
				$wpdb->update( 
	'wp_posts', 
	array( 
		'post_status' => 'wc-completed'	// string
	), 
	array( 'ID' => $aaa['MerchantTxnID'] ), 
	array( 
		'%s'	// value1
	), 
	array( '%d' ) 
);

     $woocommerce->cart->empty_cart();
WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
WC()->mailer()->emails['WC_Email_New_Order']->trigger($order_id);
    $url=site_url(); 
      wp_redirect($this->get_return_url( $order ));
			}
		?>


		<script type="text/javascript">

		jQuery(document).ready(function(){
  
		    var alphabank_payment_form = document.getElementById('shopform1');
			alphabank_payment_form.style.visibility="hidden";
			alphabank_payment_form.submit();

		});


		</script>
<?php $order = wc_get_order( $order_id );
			//print_r($order);
			$items = $order->get_items();
			 foreach ( $items as $item ) {
						//print_r($item);
     $product_name = $item->get_name();
     $product_amount[] = $item->get_subtotal();
     $quantity[] = $item->get_quantity();
    $product_variation_id = $item->get_variation_id(); }
    $product_amount=array_sum($product_amount);
    $quantity=array_sum($quantity);
     $product_name=str_replace(" ","",$product_name);
    ?>

		<?php $total = wc_format_decimal($order->get_total(), 2, true) * 1000 ; 
$mode=$this->get_option( 'mode');
if($mode == 1){
$action = 'https://hub.alphacommercehub.com.au/pp/'.$this->get_option('url');
}
elseif($mode == 0){
$action = 'https://hubuat.alphacommercehub.com.au/pp/'.$this->get_option('url');
}
?>
				<form id="shopform1" name="shopform1" method="POST" action="<?php echo $action; ?>" accept-charset="UTF-8" >
			
					<input type="hidden" name="MerchantID" value="<?php echo $this->get_option('MerchantId'); ?>">
					<input type="hidden" name="Amount" value="<?php echo round($order->get_total()) * 1000; ?>">
                    <?php if($this->get_option( '3dSecure') == 'yes') { $testmode = 'N'; } else { $testmode = 'Y'; }  ?>
					<input type="hidden" name="3DSecureBypass" maxlength="1" value="<?php echo $testmode; ?>">
					<input type="hidden" name="Country" value="<?php echo 'AUSTRALIA'; ?>">
					<input type="hidden" name="Currency" value="<?php echo $order->get_currency(); ?>">
					
					<input type="hidden" name="MerchantTxnID" value="<?php echo $order_id; ?>">
					<input type="hidden" name="OrderDetails[0].ItemAmount" value="<?php echo wc_format_decimal(($order->get_total()* 1000), 2, true); ?>">	
					<input type="hidden" name="OrderDetails[0].ItemName" value="<?php echo $product_name; ?>">	
					<input type="hidden" name="OrderDetails[0].ItemDescription" value="<?php echo $product_name; ?>">	
					<input type="hidden" name="OrderDetails[0].ItemQuantity" value="<?php echo $quantity; ?>">
					<input type="hidden" name="UserId" value="<?php echo $this->get_option('UserID'); ?>">	
					<input type="hidden" name="SuccessURL" value="<?php echo $order->get_checkout_payment_url( true ); ?>">	
	                                <input type="hidden" name="CancelURL" value="<?php echo $order->get_cancel_order_url(); ?>">
			
			<input type="submit" class="button alt" id="submit_twocheckout_payment_form" value="<?php echo __( 'Pay via Alpha bank', 'woocommerce' ) ?>" /> 
			<a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() )?>"><?php echo __( 'Cancel order &amp; restore cart', 'woocommerce' )?></a>
			
		</form>		
		<?php
		
		
		$order->update_status( 'pending', __( 'Sent request to Alpha bank with orderID: ' . $form_data['orderid'] , 'woocommerce' ) );
	}
    
    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
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

    /**
     * Output for the order received page.
     */
	public function thankyou_page() {
		if ( $this->instructions ) {
        	echo wpautop( wptexturize( $this->instructions ) );
		}
	}

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}

	
}
