<?php
/*
 * Plugin Name: Velvpay Official
 * Plugin URI: 
 * Description: Allow for seamless payment using the Velvpay platform.
 * Author: Velv Technologies Limited
 * Author URI: https://velvpay.com
 * Version: 1.0.1
 * License: GPLv3
 */

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Digikraaft\VelvPay\VelvPay;

// Load text domain for translations
add_action('plugins_loaded', 'velvpay_load_textdomain');
function velvpay_load_textdomain() {
    load_plugin_textdomain('woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Custom debug output function
function debug_output($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    exit; // Stop execution for debugging
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'velvpay_add_platform_class');
function velvpay_add_platform_class($gateways) {
    $gateways[] = 'WC_VELVPAY_PLATFORM'; // your class name here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'velvpay_init_payment_class');
function velvpay_init_payment_class() {
    class WC_VELVPAY_PLATFORM extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'velvpay'; // payment gateway plugin ID
            $this->icon = 'https://velvpay.com/assets/velvpay_logo.svg'; // URL of the icon displayed on the checkout page
            $this->has_fields = true; // enables a custom credit card form if needed
            $this->method_title = __('Velvpay', 'woocommerce');
            $this->method_description = __('Pay with Velvpay', 'woocommerce'); // displayed on the options page

            $this->supports = array('products'); // supports products

            // Initialize form fields and settings
            $this->init_form_fields();
            $this->init_settings();

            // Load settings
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->private_key = $this->get_option('private_key');
            $this->publishable_key = $this->get_option('publishable_key');
            $this->encryption_key = $this->get_option('encryption_key');

            // Action hook to save the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Webhook for payment status updates
            add_action('woocommerce_api_' . strtolower($this->id), array($this, 'webhook'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'label' => __('Enable Velvpay', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('VelvPay Payment', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Secure payment via VelvPay.', 'woocommerce'),
                ),
                'publishable_key' => array(
                    'title' => __('Publishable Key', 'woocommerce'),
                    'type' => 'text',
                ),
                'private_key' => array(
                    'title' => __('Private Key', 'woocommerce'),
                    'type' => 'password',
                ),
                'encryption_key' => array(
                    'title' => __('Encryption Key', 'woocommerce'),
                    'type' => 'password',
                    'description' => __('Your VelvPay encryption key for added security.', 'woocommerce'),
                ),
                'webhook_token' => array(
                    'title' => __('Webhook Token', 'woocommerce'),
                    'type' => 'password',
                    'description' => __('Your VelvPay webhook token key for added security.', 'woocommerce'),
                ),
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // Debugging output
            debug_output($order); // Remove or comment out after debugging

            // Check if the VelvPay class exists
            if (!class_exists('Digikraaft\VelvPay\VelvPay')) {
                error_log('VelvPay class not found!');
                wc_add_notice(__('Payment error: VelvPay integration failed.', 'woocommerce'), 'error');
                return;
            }

            try {
                // Set VelvPay keys
                VelvPay::setKeys(
                    $this->private_key,
                    $this->publishable_key,
                    $this->encryption_key
                );

                // Set custom reference to the order ID
                VelvPay::setRequestReference('ORDER_' . $order->get_id());

                // Initiate payment using the VelvPay SDK
                $response = VelvPay::initiatePayment(
                    amount: $order->get_total(),
                    isNaira: true,
                    title: __('Order Payment', 'woocommerce'),
                    description: __('Order #', 'woocommerce') . $order->get_id(),
                    chargeCustomer: false,
                    postPaymentInstructions: __('Thank you for your order.', 'woocommerce')
                );

                // Log the response for debugging
                error_log(print_r($response, true));

                // Check if the payment was successful
                if ($response && $response->status === 'success') {
                    // Store the successful response in the order
                    $order->update_meta_data('_velvpay_response', json_encode($response));
                    $order->save();

                    // Redirect customer to payment link
                    return array(
                        'result' => 'success',
                        'redirect' => $response->link,
                    );
                } else {
                    wc_add_notice(__('Payment failed. Please try again.', 'woocommerce'), 'error');
                    return;
                }
            } catch (Exception $e) {
                wc_add_notice(__('Payment error: ', 'woocommerce') . $e->getMessage(), 'error');
                return;
            }
        }

        public function webhook() {
            // Check if the request method is POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                wp_die('Invalid request', 'Invalid Request', array('response' => 400));
            }

            // Get the incoming data from the webhook
            $payload = json_decode(file_get_contents('php://input'), true);

            // Ensure we have the link in the payload
            if (isset($payload['link'])) {
                $short_link = $payload['link']; // The short link sent in the payload
                
                // Get all orders to find the one with the matching short link
                $args = array(
                    'limit' => -1, // Retrieve all orders
                    'meta_key' => '_velvpay_response',
                    'meta_query' => array(
                        array(
                            'key' => '_velvpay_response',
                            'value' => '"' . $short_link . '"',
                            'compare' => 'LIKE'
                        )
                    )
                );

                $orders = wc_get_orders($args);

                if (!empty($orders)) {
                    // Assuming we only need to update the first matching order
                    $order = $orders[0]; // Get the first matching order

                    // Update order status based on the webhook payload
                    $status = $payload['status']; // Assuming the status is sent in the payload

                    // Map VelvPay status to WooCommerce order statuses, if necessary
                    switch ($status) {
                        case 'successful':
                            $order->payment_complete();
                            $order->reduce_order_stock();
                            WC()->cart->empty_cart();
                            $order->add_order_note(__('Payment was successful via Velvpay.', 'woocommerce'));
                            return array(
                                'result' => 'success',
                                'redirect' => $this->get_return_url($order),
                            );
                        case 'failed':
                            $order->update_status('cancelled', __('Payment failed via Velvpay.', 'woocommerce'));
                            wc_add_notice(__('Please try again.', 'woocommerce'), 'error');
                            return;
                        case 'pending':
                            $order->update_status('on-hold', __('Payment is pending via Velvpay.', 'woocommerce'));
                            wc_add_notice(__('Payment is pending via Velvpay.', 'woocommerce'), 'error');
                            return;
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