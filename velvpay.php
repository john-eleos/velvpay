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
use Digikraaft\VelvPay\Payment;
use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;

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
            add_action('woocommerce_admin_field_regenerate_webhook_token', array($this, 'regenerate_webhook_token'));
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
                'webhook_url' => array(
                    'title' => __('Webhook URL', 'woocommerce'),
                    'type' => 'text',
                    'description' => sprintf(__('Copy this URL to your VelvPay account for webhook notifications: %s', 'woocommerce'), esc_url($this->get_webhook_url())),
                    'default' => $this->get_webhook_url(),
                    'custom_attributes' => array('readonly' => 'readonly'), // Make it read-only
                ),
                'webhook_token' => array(
                    'title' => __('Webhook Token', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This token is used to authenticate webhook requests. You can regenerate it if needed.', 'woocommerce'),
                    'default' => $this->generate_webhook_token(),
                    'custom_attributes' => array('readonly' => 'readonly'), // Make it read-only
                ),
                'regenerate_webhook_token' => array(
                    'title' => '',
                    'type' => 'button',
                    'class' => 'button regenerate-token',
                    'description' => '',
                    'desc_tip' => true,
                    'custom_attributes' => array('onclick' => 'location.href=\'' . admin_url('admin-ajax.php?action=regenerate_webhook_token&gateway=' . $this->id) . '\''),
                ),
            );
        }

        public function get_webhook_url() {
            // Generate the webhook URL
            return esc_url(get_site_url() . '/?wc-api=' . strtolower($this->id));
        }

        public function generate_webhook_token() {
            // Generate a unique token if it doesn't exist
            if (!$this->webhook_token) {
                $this->webhook_token = bin2hex(random_bytes(16)); // Generate a random token
                $this->update_option('webhook_token', $this->webhook_token); // Save the token
            }
            return $this->webhook_token;
        }


        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
        
            // Debugging output for order details
            debug_output($order); // Uncomment for debugging
        
            // Set the VelvPay keys
            try {
                VelvPay::setKeys(
                    $this->private_key,
                    $this->publishable_key,
                    $this->encryption_key
                );
            } catch (InvalidArgumentException $e) {
                error_log('Invalid API keys: ' . $e->getMessage());
                wc_add_notice(__('Payment error: Invalid API keys.', 'woocommerce'), 'error');
                return;
            }
        
            // Set custom reference for the order
            VelvPay::setRequestReference('ORDER_' . $order->get_id());
        
            // Initiate payment using the VelvPay SDK
            try {
                $response = Payment::initiatePayment(
                    amount: $order->get_total(),
                    isNaira: true,
                    title: __('Order Payment', 'woocommerce'),
                    description: __('Order #', 'woocommerce') . $order->get_id(),
                    chargeCustomer: false,
                    postPaymentInstructions: __('Thank you for your order.', 'woocommerce')
                );
        
                // Debugging output for the response
                debug_output($response); // Uncomment for debugging
        
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
                    error_log('Payment response error: ' . json_encode($response));
                    wc_add_notice(__('Payment failed. Please try again.', 'woocommerce'), 'error');
                    return;
                }
            } catch (Exception $e) {
                error_log('Payment processing error: ' . $e->getMessage());
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

        public function regenerate_webhook_token() {
            // Generate a new webhook token
            $new_token = bin2hex(random_bytes(16));
            $this->update_option('webhook_token', $new_token);

            // Redirect back to settings page with a notice
            wc_add_notice(__('Webhook token regenerated successfully.', 'woocommerce'), 'success');
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id));
            exit;
        }
    }


}


// AJAX action to handle the regenerate request
add_action('wp_ajax_regenerate_webhook_token', 'velvpay_regenerate_webhook_token');
function velvpay_regenerate_webhook_token() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user', 'Unauthorized', array('response' => 403));
    }

    $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
    if ($gateway === 'velvpay') {
        $gateway_instance = new WC_VELVPAY_PLATFORM(); // Create an instance of the gateway
        $gateway_instance->regenerate_webhook_token();
    }

    wp_die(); // Terminate the script
}