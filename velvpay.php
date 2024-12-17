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

// Ensure proper inclusion of core WordPress files
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

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
            $this->method_title = 'Velvpay';
            $this->method_description = 'Pay with Velvpay'; // displayed on the options page

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


            // Initialize the webhook token and URL
            $this->webhook_token = $this->generate_webhook_token(); // Ensure it's initialized
            $this->webhook_url = $this->get_webhook_url(); // Initialize webhook URL

            // Action hook to save the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_admin_field_regenerate_webhook_token', array($this, 'regenerate_webhook_token'));
            // Webhook for payment status updates
            add_action('woocommerce_api_' . strtolower($this->id), array($this, 'webhook'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Velvpay',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'VelvPay Payment',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Secure payment via VelvPay.',
                ),
                'publishable_key' => array(
                    'title' => 'Publishable Key',
                    'type' => 'text',
                ),
                'private_key' => array(
                    'title' => 'Private Key',
                    'type' => 'password',
                ),
                'encryption_key' => array(
                    'title' => 'Encryption Key',
                    'type' => 'password',
                    'description' => 'Your VelvPay encryption key for added security.',
                ),
                'webhook_url' => array(
                    'title' => 'Webhook URL',
                    'type' => 'text',
                    'description' => sprintf('Copy this URL to your VelvPay account for webhook notifications: %s', esc_url($this->get_webhook_url())),
                    'default' => $this->get_webhook_url(),
                    'custom_attributes' => array('readonly' => 'readonly'), // Make it read-only
                ),
                'webhook_token' => array(
                    'title' => 'Webhook Token',
                    'type' => 'text',
                    'description' => 'This token is used to authenticate webhook requests. You can regenerate it if needed.',
                    'default' => $this->generate_webhook_token(),
                    'custom_attributes' => array('readonly' => 'readonly'), // Make it read-only
                ),
                'regenerate_webhook_token' => array(
                    'title' => 'Regenrate Token',
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
            // Get the order object
            $order = wc_get_order($order_id);
        
            // Log the start of the payment process
            error_log("Starting payment process for order ID: $order_id");
        
            try {
                // Set the VelvPay API keys
                VelvPay::setKeys($this->private_key, $this->publishable_key, $this->encryption_key);
                error_log("VelvPay keys set successfully.");
        
                // Set a custom reference for the order
                VelvPay::setRequestReference('ORDER_' . $order->get_id());
                error_log("Request reference set to: ORDER_" . $order->get_id());
        
                // Initiate payment using the VelvPay SDK
                $response = Payment::initiatePayment(
                    amount: $order->get_total(),
                    isNaira: true,
                    title: 'Order Payment',
                    description: 'Order #' . $order->get_id(),
                    chargeCustomer: false,
                    postPaymentInstructions: 'Thank you for your order.'
                );
        
                // Log the response from VelvPay
                error_log("Response received from VelvPay: " . json_encode($response));
        
                // Check if the payment was successful
                if ($response && $response->status === 'success') {
                    // Store the successful response in the order
                    $order->update_meta_data('_velvpay_response', json_encode($response));
                    $order->save();
                    error_log("Payment successful for order ID: $order_id.");
        
                    // Redirect customer to payment link
                    return array(
                        'result' => 'success',
                        'redirect' => $response->link,
                    );
                } else {
                    // Log payment failure
                    error_log("Payment failed for order ID: $order_id. Response: " . json_encode($response));
                    wc_add_notice('Payment failed. Please try again.', 'error');
                    return;
                }
            } catch (Exception $e) {
                // Log any exceptions that occur
                error_log('Payment processing error for order ID: ' . $order_id . ' - ' . $e->getMessage());
                wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
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
                            $order->add_order_note('Payment was successful via Velvpay.');
                            return array(
                                'result' => 'success',
                                'redirect' => $this->get_return_url($order),
                            );
                        case 'failed':
                            $order->update_status('cancelled', 'Payment failed via Velvpay.');
                            wc_add_notice('Please try again.', 'error');
                            return;
                        case 'pending':
                            $order->update_status('on-hold', 'Payment is pending via Velvpay.');
                            wc_add_notice('Payment is pending via Velvpay.', 'error');
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
            wc_add_notice('Webhook token regenerated successfully.', 'success');
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