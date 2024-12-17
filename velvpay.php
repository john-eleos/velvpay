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
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->charge_customer = $this->get_option('charge_customer');
            $this->private_key = $this->get_option('private_key');
            $this->publishable_key = $this->get_option('publishable_key');
            $this->encryption_key = $this->get_option('encryption_key');
            $this->webhook_token = $this->get_option('webhook_token');
            $this->webhook_url = $this->get_webhook_url();

            // Action hooks for settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower($this->id), array($this, 'webhook'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array('title' => 'Enable/Disable', 'label' => 'Enable Velvpay', 'type' => 'checkbox', 'default' => 'no'),
                'charge_customer' => array('title' => 'Charge Customer', 'label' => 'Allow your customer bear the fee', 'type' => 'checkbox', 'default' => 'no'),
                'title' => array('title' => 'Title', 'type' => 'text', 'description' => 'Title seen during checkout.', 'default' => 'VelvPay Payment', 'desc_tip' => true),
                'description' => array('title' => 'Description', 'type' => 'textarea', 'description' => 'Description seen during checkout.', 'default' => 'Secure payment via VelvPay.'),
                'publishable_key' => array('title' => 'Publishable Key', 'type' => 'text'),
                'private_key' => array('title' => 'Private Key', 'type' => 'password'),
                'encryption_key' => array('title' => 'Encryption Key', 'type' => 'password', 'description' => 'VelvPay encryption key for security.'),
                'webhook_url' => array('title' => 'Webhook URL', 'type' => 'text', 'description' => sprintf('Copy this URL to your VelvPay account for webhook notifications: %s', esc_url($this->get_webhook_url())), 'default' => $this->get_webhook_url(), 'custom_attributes' => array('readonly' => 'readonly')),
                'webhook_token' => array('title' => 'Webhook Token', 'type' => 'text', 'description' => 'Token for authenticating webhook requests.', 'default' => $this->generate_webhook_token(), 'custom_attributes' => array('readonly' => 'readonly')),
            );
        }

        public function get_webhook_url() {
            return esc_url(get_site_url() . '/?wc-api=' . strtolower($this->id));
        }

        public function generate_webhook_token() {
            if (!$this->webhook_token) {
                $this->webhook_token = bin2hex(random_bytes(16));
                $this->update_option('webhook_token', $this->webhook_token);
            }
            return $this->webhook_token;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            error_log("Starting payment process for order ID: $order_id");

            try {
                VelvPay::setKeys($this->private_key, $this->publishable_key, $this->encryption_key);
                error_log("VelvPay keys set successfully.");

                VelvPay::setRequestReference('ORDER_' . $order->get_id());
                error_log("Request reference set to: ORDER_" . $order->get_id());

                $item_descriptions = [];
                foreach ($order->get_items() as $item) {
                    $item_descriptions[] = $item->get_name() . ' (Qty: ' . $item->get_quantity() . ')';
                }
                $description = implode(', ', $item_descriptions);
                $chargeCustomer = $this->get_option('charge_customer') === 'yes';

                $response = Payment::initiatePayment(
                    amount: $order->get_total(),
                    isNaira: true,
                    title: 'Payment for Order #' . $order->get_id(),
                    description: $description,
                    chargeCustomer: $chargeCustomer,
                    postPaymentInstructions: 'Thank you for your order.'
                );

                error_log("Response received from VelvPay: " . json_encode($response));

                if ($response && $response->status === 'success') {
                    $order->update_meta_data('_velvpay_response', json_encode($response));
                    $order->save();
                    error_log("Payment successful for order ID: $order_id.");

                    return array(
                        'result' => 'success',
                        'redirect' => add_query_arg('payment_link', esc_url($response->link), wc_get_cart_url()),
                    );
                } else {
                    error_log("Payment failed for order ID: $order_id. Response: " . json_encode($response));
                    wc_add_notice('Payment failed. Please try again.', 'error');
                    return;
                }
            } catch (Exception $e) {
                error_log('Payment processing error for order ID: ' . $order_id . ' - ' . $e->getMessage());
                wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
                return;
            }
        }

        public function webhook() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                wp_die('Invalid request', 'Invalid Request', array('response' => 400));
            }

            $payload = json_decode(file_get_contents('php://input'), true);
            if (isset($payload['link'])) {
                $short_link = $payload['link'];
                
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
                    $status = $payload['status'];

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

            http_response_code(200);
        }

        public function regenerate_webhook_token() {
            $new_token = bin2hex(random_bytes(16));
            $this->update_option('webhook_token', $new_token);
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
        $gateway_instance = new WC_VELVPAY_PLATFORM();
        $gateway_instance->regenerate_webhook_token();
    }

    wp_die();
}

// Add JavaScript for redirecting after payment
add_action('wp_footer', 'velvpay_checkout_redirect');
function velvpay_checkout_redirect() {
    if (is_checkout()) {
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            var urlParams = new URLSearchParams(window.location.search);
            var paymentLink = urlParams.get('payment_link');

            if (paymentLink) {
                window.open(paymentLink, '_blank');
                window.location.href = '<?php echo esc_url(wc_get_cart_url()); ?>';
            }
        });
        </script>
        <?php
    }
}