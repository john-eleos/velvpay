<?php
/*
 * Plugin Name: Velvpay Official
 * Plugin URI: 
 * Description: Allow for seamless payment using the Velvpay platform.
 * Author: Velv Technologies Limited
 * Author URI: https://velvpay.com
 * Version: 1.0.2
 * License: GPLv3
 */

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

use Digikraaft\VelvPay\VelvPay;
use Digikraaft\VelvPay\Payment;
use Digikraaft\VelvPay\Exceptions\InvalidArgumentException;
use Digikraaft\VelvPay\Util\Util;

// Register the payment gateway
add_filter('woocommerce_payment_gateways', 'velvpay_add_platform_class');
function velvpay_add_platform_class($gateways) {
    $gateways[] = 'WC_VELVPAY_PLATFORM';
    return $gateways;
}

// Initialize the payment class
add_action('plugins_loaded', 'velvpay_init_payment_class');
function velvpay_init_payment_class() {
    class WC_VELVPAY_PLATFORM extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'velvpay';
            $this->icon = 'https://res.cloudinary.com/figo-payment/image/upload/v1714866146/xb7esgdalpgckbitztob.png';
            $this->has_fields = true;
            $this->method_title = 'Velvpay';
            $this->method_description = 'Pay with Velvpay';

            $this->supports = array('products');
            $this->init_form_fields();
            $this->init_settings();

            // Load settings
            $this->title = sanitize_text_field($this->get_option('title'));
            $this->description = sanitize_textarea_field($this->get_option('description'));
            $this->enabled = $this->get_option('enabled') === 'yes' ? 'yes' : 'no';
            $this->charge_customer = $this->get_option('charge_customer') === 'yes' ? 'yes' : 'no';
            $this->private_key = sanitize_text_field($this->get_option('private_key'));
            $this->publishable_key = sanitize_text_field($this->get_option('publishable_key'));
            $this->encryption_key = sanitize_text_field($this->get_option('encryption_key'));
            $this->webhook_token = sanitize_text_field($this->get_option('webhook_token'));
            $this->postPaymentInstructions = sanitize_text_field($this->get_option('postPaymentInstructions'));
            $this->webhook_url = $this->get_webhook_url();

            // Action hooks for settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower($this->id), array($this, 'webhook'));


        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array('title' => 'Enable/Disable', 'label' => 'Enable Velvpay', 'type' => 'checkbox', 'default' => 'no'),
                'charge_customer' => array('title' => 'Charge Customer', 'label' => 'Allow your customer to bear the fee', 'type' => 'checkbox', 'default' => 'no'),
                'title' => array('title' => 'Title', 'type' => 'text', 'description' => 'Title seen during checkout.', 'default' => 'VelvPay Payment', 'desc_tip' => true),
                'description' => array('title' => 'Description', 'type' => 'textarea', 'description' => 'Description seen during checkout.', 'default' => 'Secure payment via VelvPay.'),
                'publishable_key' => array('title' => 'Publishable Key', 'type' => 'text'),
                'postPaymentInstructions' => array('title' => 'Post Payment Instructions', 'type' => 'textarea', 'description' => 'Instructions for your customer after payment is made.', 'default' => 'Thank you for your order.'),
                'private_key' => array('title' => 'Private Key', 'type' => 'password'),
                'encryption_key' => array('title' => 'Encryption Key', 'type' => 'password', 'description' => 'VelvPay encryption key for security.'),
                'webhook_url' => array('title' => 'Webhook URL', 'type' => 'text', 'description' => sprintf('Copy this URL to your VelvPay account for webhook notifications: %s', esc_url($this->get_webhook_url())), 'default' => esc_url($this->get_webhook_url()), 'custom_attributes' => array('readonly' => 'readonly')),
                'webhook_token' => array('title' => 'Webhook Token', 'type' => 'text', 'description' => 'Token for authenticating webhook requests.', 'default' => $this->generate_webhook_token(), 'custom_attributes' => array('readonly' => 'readonly')),

            );

        }

        public function get_webhook_url() {
            return esc_url(get_site_url() . '/?wc-api=' . strtolower($this->id));
        }

        public function generate_webhook_token() {
            $webhookKey = $this->get_option('webhook_token'); 
        
            if (!$webhookKey) {
                $webhookKey = bin2hex(random_bytes(16)); // Generate a secure random token
                $this->update_option('webhook_token', $webhookKey); // Save the token
            }
        
            return $webhookKey; // Return the token
        }
        



        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            //error_log("Starting payment process for order ID: $order_id");

            if (!$order || !$order->get_total() || $order->get_status() !== 'pending') {
                wc_add_notice('Invalid order or already processed.', 'error');
                return;
            }

            try {
                VelvPay::setKeys($this->private_key, $this->publishable_key, $this->encryption_key);
                //error_log("VelvPay keys set successfully.");

                VelvPay::setRequestReference('ORDER_' . $order->get_id());
                //error_log("Request reference set to: ORDER_" . $order->get_id());

                $item_descriptions = [];
                foreach ($order->get_items() as $item) {
                    $item_descriptions[] = $item->get_name() . ' (Qty: ' . $item->get_quantity() . ')';
                }
                $description = implode(', ', $item_descriptions);
                $chargeCustomer = $this->get_option('charge_customer') === 'yes'; 
                $postPaymentInstructions = $this->get_option('postPaymentInstructions');

                // $orderLink = $order->get_view_order_url();
                $orderLink = $this->get_return_url($order);

                $response = Payment::initiatePayment(
                    amount: $order->get_total(),
                    isNaira: true,
                    title: 'Payment for Order #' . $order->get_id(),
                    description: $description,
                    redirectUrl: $orderLink,
                    chargeCustomer: $chargeCustomer,
                    postPaymentInstructions: $postPaymentInstructions
                );

                //error_log("Response received from VelvPay: " . json_encode($response));

                if ($response && $response->status === 'success') {
                    $order->update_meta_data('_velvpay_response', json_encode($response));
                    $order->save();
                    //error_log("Payment successful for order ID: $order_id.");

                    $paymentUrl = $response->link;

                    // Return success
                    return array(
                        'result' => 'success',
                        'redirect' => $paymentUrl,
                    );

                } else {
                    //error_log("Payment failed for order ID: $order_id. Response: " . json_encode($response));
                    wc_add_notice('Payment failed. Please try again.', 'error');
                    return;
                }
            } catch (Exception $e) {
                //error_log('Payment processing error for order ID: ' . $order_id . ' - ' . $e->getMessage());
                wc_add_notice('Payment error: ' . esc_html($e->getMessage()), 'error');
                return;
            }
        }

        public function webhook() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                wp_die('Invalid request', 'Invalid Request', array('response' => 400));
            }

            // Validate Authorization header
            $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';
            $expected_token = 'Bearer ' . $this->get_option('webhook_token');

            if ($auth_header !== $expected_token) {
                wp_die('Unauthorized request', 'Unauthorized', array('response' => 401));
            }
            
            // Step 1: Read the raw input
            $raw_input = file_get_contents('php://input');
            //error_log('Raw input: ' . $raw_input);

            // Step 2: Decode the JSON payload
            $payload = json_decode($raw_input, true);

            // Step 3: Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                //error_log('JSON Decode Error: ' . json_last_error_msg());
                wp_die('Invalid JSON received', 'Invalid Payload', array('response' => 400));
            }

            // Step 4: Check if 'link' is set and not empty
            if (isset($payload['data']['link']) && !empty($payload['data']['link'])) {
                $paymentLink = $payload['data']['link'];
                $short_link = sanitize_text_field($paymentLink);
                
                $args = array(
                    'limit' => -1,
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
                    $order = $orders[0];
                    $status = sanitize_text_field($payload['data']['status']);

                    switch ($status) {
                        case 'successful':
                            $order->payment_complete();
                            $order->reduce_order_stock();
                            WC()->cart->empty_cart();
                            $order->add_order_note('Payment was successful via Velvpay.');
                            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
                        case 'failed':
                            $order->update_status('cancelled', 'Payment failed via Velvpay.');
                            wc_add_notice('Please try again.', 'error');
                            return array(
                                'result'   => 'failure',
                                'redirect' => wc_get_cart_url(), // Redirect on failure
                            );
                        case 'pending':
                            $order->update_status('on-hold', 'Payment is pending via Velvpay.');
                            wc_add_notice('Payment is pending via Velvpay.', 'error');
                            return;
                    }
                } else {
                    wp_die('Order with the specified short link not found', 'Order Not Found', array('response' => 404));
                }
            } else {
                //error_log('Payment link is missing or empty');
                wp_die('Payment link is missing', 'Invalid Payload', array('response' => 400));
            }

            http_response_code(200);
        }

        public function regenerate_webhook_token() {
            // Force generation of a new token
            $new_token = bin2hex(random_bytes(16));
            
            // Update the option directly
            update_option('woocommerce_velvpay_webhook_token', $new_token);
            
            // Also update the instance variable
            $this->webhook_token = $new_token;
            
            // Add a success notice
            wc_add_notice('Webhook token regenerated successfully.', 'success');
            
            // Optional: Log the token regeneration
            //error_log('Velvpay Webhook Token Regenerated: ' . $new_token);
            
            return $new_token;
        }
    }
}



