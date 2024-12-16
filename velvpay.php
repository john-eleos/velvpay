<?php
/*
 * Plugin Name: Velvpay Official
 * Plugin URI: 
 * Description: Allow for seamless payment using the velvpay platform.
 * Author: Velv Technologies Limited
 * Author URI: https://velvpay.com
 * Version: 1.0.1
 * License: GPLv3
 */

 use Digikraaft\VelvPay\VelvPay;
 use Digikraaft\VelvPay\Payment;

 
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'velvpay_add_platform_class' );
function velvpay_add_platform_class( $gateways ) {
	$gateways[] = 'WC_VELVPAY_PLATFORM'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'velvpay_init_payment_class' );
function velvpay_init_payment_class() {

	class WC_VELVPAY_PLATFORM extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
        public function __construct() {

            $this->id = 'velvpay'; // payment gateway plugin ID
            $this->icon = 'https://velvpay.com/assets/velvpay_logo.svg'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Velvpay';
            $this->method_description = 'Pay with Velvpay'; // will be displayed on the options page
        
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );    
            $this->private_key = $this->get_option('private_key');
            $this->publishable_key = $this->get_option('publishable_key');
            $this->encryption_key = $this->get_option('encryption_key');
        
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // We need custom JavaScript to obtain a token
            // add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            

            // webhook for any payment status updates becomes http://{{URL}}/wc-api/velvpay/
            // You can also register a webhook here
            add_action( 'woocommerce_api_velvpay', array( $this, 'webhook' ) );
        }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
          public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Velvpay',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'VelvPay Payment',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Secure payment via VelvPay.',
                ),
                'publishable_key' => array(
                    'title'       => 'Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Private Key',
                    'type'        => 'password'
                ),
                'encryption_key' => array(
                    'title'       => 'Encryption Key',
                    'type'        => 'password',
                    'description' => 'Your VelvPay encryption key for added security.'
                ),
                'webhook_token' => array(
                    'title'       => 'Webhook Token should be same as stored on your velvpay dashboard',
                    'type'        => 'password',
                    'description' => 'Your VelvPay webhook token key for added security.'
                )
            );
        }

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
        public function process_payment( $order_id ) {
            // Retrieve the WooCommerce order details
            $order = wc_get_order( $order_id );
        
            try {
                // Set VelvPay keys
                VelvPay::setKeys(
                    $this->get_option('private_key'), 
                    $this->get_option('publishable_key'), 
                    $this->get_option('encryption_key')
                );
        
                // Set custom reference to the order ID
                VelvPay::setRequestReference('ORDER_' . $order->get_id());
        
                // Initiate payment using the VelvPay SDK
                $response = VelvPay::initiatePayment(
                    amount: $order->get_total(),
                    isNaira: true,
                    title: 'Order Payment',
                    description: 'Order #' . $order->get_id(),
                    chargeCustomer: false,
                    postPaymentInstructions: 'Thank you for your order.'
                );
        
                // Check if the payment was successful
                if ($response && $response->status === 'success') {
                    // Store the successful response in the order
                    $order->update_meta_data('_velvpay_response', json_encode($response));
                    $order->save();
        
                    // Redirect customer to payment link
                    return [
                        'result'   => 'success',
                        'redirect' => $response->link,
                    ];
                } else {
                    wc_add_notice('Payment failed. Please try again.', 'error');
                    return;
                }
            } catch (Exception $e) {
                wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
                return;
            }
        }

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
            // Check if the request method is POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                wp_die('Invalid request', 'Invalid Request', array('response' => 400));
            }
        
            // Get the incoming data from the webhook
            $payload = json_decode(file_get_contents('php://input'), true);
        
            // Ensure we have the short link in the payload
            if (isset($payload['link'])) {
                $short_link = $payload['link']; // The short link sent in the payload
                
                // Get all orders to find the one with the matching short link
                $args = array(
                    'limit' => -1, // Retrieve all orders
                    'meta_key' => '_velvpay_response',
                    'meta_query' => array(
                        array(
                            'key' => '_velvpay_response',
                            'value' => '"' . $short_link . '"', // Check if the short link is in the stored response
                            'compare' => 'LIKE'
                        )
                    )
                );
        
                $orders = wc_get_orders($args);
        
                if (!empty($orders)) {
                    // Assuming we only need to update the first matching order
                    $order = $orders[0]; // Get the first matching order

                    // Get the order ID
                    $order_id = $order->get_id();
        
                    // Update order status based on the webhook payload
                    $status = $payload['status']; // Assuming the status is sent in the payload
        
                    // Map Velvpay status to WooCommerce order statuses, if necessary
                    switch ($status) {
                        case 'successful':
                            // $order->update_status('completed', 'Payment was successful via Velvpay.');
                            $order->payment_complete();
                            $order->reduce_order_stock();
                            WC()->cart->empty_cart();
                            $order->add_order_note('Payment was successful via Velvpay.');
                            return array(
                                'result' => 'success',
                                'redirect' => $this->get_return_url( $order ),
                            );
                        case 'failed':
                            $order->update_status('cancelled', 'Payment failed via Velvpay.');
                            wc_add_notice( 'Please try again.', 'error' );
                            return;
                        case 'pending':
                            $order->update_status('on-hold', 'Payment is pending via Velvpay.');
                            wc_add_notice( 'Payment is pending via Velvpay.', 'error' );
                            return;
                        // Add more mapping as needed
                    }
                } else {
                    wp_die('Order with the specified short link not found', 'Order Not Found', array('response' => 404));
                }
            } else {
                wp_die('Invalid payload', 'Invalid Payload', array('response' => 400));
            }
        
            // Send a 200 response to acknowledge receipt of the webhook
            http_response_code(200);
        }
 	}
}