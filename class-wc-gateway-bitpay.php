<?php
/*
	Plugin Name: BitPay WooCommerce Payment Gateway
	Plugin URI:  https://bitpay.com
	Description: BitPay WooCommerce Payment Gateway allows you to accept bitcoins on your WooCommerce store.
	Author:      BitPay
	Author URI:  https://bitpay.com

	Version: 	2.0.0
	License:           Copyright 2011-2014 BitPay Inc., MIT License
	License URI:       https://github.com/bitpay/woocommerce-plugin/blob/master/LICENSE
	GitHub Plugin URI: https://github.com/bitpay/woocommerce-plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', 'woocommerce_bitpay_init', 0);

function woocommerce_bitpay_init()
{
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

        class WC_Gateway_Bitpay extends WC_Payment_Gateway
        {
            /**
		     * Constructor for the gateway.
		     */
            public function __construct()
            {

                $this->id                 = 'bitpay';
                $this->icon               = plugin_dir_url(__FILE__).'bitpay.png';
                $this->has_fields         = false;
                $this->method_title       = 'BitPay';
                $this->method_description = 'BitPay allows you to accept bitcoin on your WooCommerce store.';

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables
                $this->title              = $this->get_option( 'title' );
                $this->description        = $this->get_option( 'description' );

                // Define BitPay settings
                $this->api_key            = unserialize($this->get_option( 'api_key' ));
                $this->api_pub            = unserialize($this->get_option( 'api_pub' ));
                $this->api_sin            = $this->get_option( 'api_sin' );
                $this->api_token          = unserialize($this->get_option( 'api_token' ));
                $this->api_token_label    = $this->get_option( 'api_token_label' );
                $this->api_network        = $this->get_option( 'api_network' );

                $this->redirect_url       = WC()->api_request_url( 'WC_Gateway_Bitpay' );

                // Actions
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'wp_ajax_bitpay_pair_code', array( $this, 'pair_code' ) );
				add_action( 'wp_ajax_bitpay_revoke_token', array( $this, 'revoke_token' ) );
				add_action( 'wp_ajax_bitpay_create_invoice', array( $this, 'create_invoice' ) );
            }

            public function is_valid_for_use()
            {
            	// TODO: Check for valid settings and the ability to create invoices (account not over limit, correct currency, etc)
                return true;
            }

            /**
		     * Initialise Gateway Settings Form Fields
		     */
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'woocommerce' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Enable Bitcoin via BitPay', 'bitpay' ),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default'     => __( 'Bitcoin - BitPay', 'bitpay' ),
                    ),
                    'message' => array(
                        'title' => __( 'Customer Message', 'woothemes' ),
                        'type' => 'textarea',
                        'description' => __( 'Message to explain how the customer will be paying for the purchase.', 'woothemes' ),
                        'default' => 'You will be redirected to bitpay.com to complete your purchase.'
                    ),
                    'api_token' => array(
                        'type'        => 'api_token'
                    ),
                    'transactionSpeed' => array(
                        'title' => __('Risk/Speed', 'woothemes'),
                        'type' => 'select',
                        'description' => 'Choose a transaction speed.  For details, see the API documentation at bitpay.com',
                        'options' => array(
                            'high' => 'High',
                            'medium' => 'Medium',
                            'low' => 'Low',
                        ),
                        'default' => 'high',
                    ),
                    'fullNotifications' => array(
                        'title' => __('Full Notifications', 'woothemes'),
                        'type' => 'checkbox',
                        'description' => 'Yes: receive an email for each status update on a payment.<br>No: receive an email only when payment is confirmed.',
                        'default' => 'no',
                    ),
                    'order_states' => array(
                        'type'        => 'order_states'
                    )
                );
            }

         /**
	     * generate_api_token_html function.
	     */
        public function generate_api_token_html()
        {
            ob_start();

            wp_enqueue_style( 'font-awesome', '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css' );

            $pairing_form = file_get_contents(plugin_dir_url(__FILE__).'pairing.tpl');
            $token_format = file_get_contents(plugin_dir_url(__FILE__).'token.tpl');

            ?>
		    <tr valign="top">
	            <th scope="row" class="titledesc">API Token:</th>
	            <td class="forminp" id="bitpay_api_token">
	            	<style>
	            	<?php include('token.css'); ?>
	            	</style>
	            	<div id="bitpay_api_token_form">
		            	<?php
                        if (!$this->api_token) {
                            echo $pairing_form;
                        } else {
                            echo $token_format;
                        }
                        ?>
				    </div>
			       	<script type="text/javascript">
						jQuery(function () {

							// Select Network
							jQuery('#bitpay_api_token_form').on('change', '#bitpay_api_network', function (e) {
								console.log(e);
								var live = "https://bitpay.com/api-tokens";
								var test = "https://test.bitpay.com/api-tokens";
								if (jQuery("#bitpay_api_network").val() === 'livenet') {
									jQuery("#bitpay_api_token_link").attr("href", live).html(live);
								} else {
									jQuery("#bitpay_api_token_link").attr("href", test).html(test);
								}
							});

							// Pairing Code
							jQuery('#bitpay_api_token_form').on('click', '#bitpay_find', function (e) {
								e.preventDefault();
								jQuery("#bitpay_pairing_code").attr('disabled','disabled').hide();
								jQuery("#bitpay_find").attr('disabled','disabled').addClass('button-disabled').hide();
								jQuery("#bitpay_find").after('<div id="bitpay_pair_loading" style="width: 20em; text-align: center"><img src="<?= plugins_url( 'woocommerce/assets/images/ajax-loader.gif' ); ?>"></div>');
								jQuery.post(ajaxurl, {
									'action': 'bitpay_pair_code',
									'pairing_code': jQuery('#bitpay_pairing_code').val(),
									'network': jQuery("#bitpay_api_network").val()
								})
								.done(function (data) {
									if (data && data.sin && data.label) {
										var testnet = (data.network === 'testnet') ? ' testnet' : '';
										var Testnet = (data.network === 'testnet') ? ' Testnet' : 'Livenet';
										var token = '<?= $token_format; ?>'.replace('%s', testnet).replace('%s', testnet).replace('%s', Testnet).replace('%s', data.label).replace('%s', data.sin);
										jQuery("#bitpay_api_token_form").html(token);
										jQuery(".bitpay-token").hide().fadeIn(500);
										jQuery("#message").remove();
										jQuery("h2.woo-nav-tab-wrapper").after('<div id="message" class="updated fade"><p><strong>You have been paired with your BitPay account!</strong></p></div>');
									} else {
										jQuery("#bitpay_pairing_code").val('');
										jQuery("#bitpay_pairing_code").attr('disabled', null).show();
										jQuery("#bitpay_find").attr('disabled', null).removeClass('button-disabled').show();
									}
									jQuery("#bitpay_pair_loading").remove();
								})
								.fail(function () {
									jQuery("#bitpay_pairing_code").attr('disabled', null).show();
									jQuery("#bitpay_find").attr('disabled', null).removeClass('button-disabled').show();
									jQuery("#bitpay_pair_loading").remove();
								});
							});

							// Revoking Token
							jQuery('#bitpay_api_token_form').on('click', '#bitpay_revoke', function (e) {
								e.preventDefault();
								if (confirm("Are you sure you want to revoke the token?")) {
									jQuery.post(ajaxurl, {
										'action': 'bitpay_revoke_token'
									})
									.always(function (data) {
										jQuery(".bitpay-token").fadeOut(500, function () {
											jQuery("#bitpay_api_token_form").html('<?= $pairing_form; ?>');
											jQuery("#message").remove();
											jQuery("h2.woo-nav-tab-wrapper").after('<div id="message" class="updated fade"><p><strong>You have revoked your token!</strong></p></div>');
											jQuery("#bitpay_pair_loading").remove();
										});
									});
								}

							});
						});
					</script>
	            </td>
		    </tr>
	        <?php

            return ob_get_clean();
        }

        /**
	     * Output for the order received page.
	     */
        public function thankyou_page($order_id)
        {
        }

        /**
	     * Add content to the WC emails.
	     *
	     * @access public
	     * @param WC_Order $order
	     * @param bool $sent_to_admin
	     * @param bool $plain_text
	     * @return void
	     */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
        }

        /**
	     * Process the payment and return the result
	     *
	     * @param int $order_id
	     * @return array
	     */
        public function process_payment($order_id)
        {
            $order = wc_get_order( $order_id );

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status( 'on-hold', 'Awaiting payment confirmation.' );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'    => $this->get_return_url( $order )
            );
        }
    }

    /**
 	* Add BitPay Payment Gateway to WooCommerce
 	**/
    function wc_add_bitpay($methods)
    {
        $methods[] = 'WC_Gateway_Bitpay';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'wc_add_bitpay' );

    /**
	* Add Settings link to the plugin entry in the plugins menu for WC below 2.1
	**/
    if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

        add_filter('plugin_action_links', 'bitpay_plugin_action_links', 10, 2);

        function bitpay_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=wc_gateway_bitpay">Settings</a>';
                array_unshift($links, $settings_link);
            }

            return $links;
        }
    }
    /**
	* Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
	**/
    else{
        add_filter('plugin_action_links', 'bitpay_plugin_action_links', 10, 2);

        function bitpay_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_bitpay">Settings</a>';
                array_unshift($links, $settings_link);
            }

            return $links;
        }
    }
}

function woocommerce_bitpay_ajax_pair_code()
{
    // Validate the Pairing Code
    $pairing_code = $_POST['pairing_code'];
    if (!preg_match('/^[a-zA-Z0-9]{7}$/',$pairing_code)) {
        wp_send_json(array("error"=>"Invalid Pairing Code"));
    }

    // Validate the Network
    $network = ($_POST['network'] === 'livenet') ? 'livenet' : 'testnet';

    // Generate Private Key
    $key = new \Bitpay\PrivateKey();
    $key->generate();

    // Generate Public Key
    $pub = new \Bitpay\PublicKey();
    $pub->setPrivateKey($key);
    $pub->generate();

    // Get SIN Format
    $sin = new \Bitpay\SinKey();
    $sin->setPublicKey($pub);
    $sin->generate();

    // Create an API Client
    $client = new \Bitpay\Client\Client();
    if ($network === 'livenet') {
        $client->setNetwork(new \Bitpay\Network\Livenet());
    } else {
        $client->setNetwork(new \Bitpay\Network\Testnet());
    }

    $client->setAdapter(new \Bitpay\Client\Adapter\CurlAdapter());
    $client->setPrivateKey($key);
    $client->setPublicKey($pub);

    try {
        $token = $client->createToken(
            array(
                'id'          => (string) $sin,
                'pairingCode' => $pairing_code,
                'label'       => "WooCommerce - {$_SERVER['SERVER_NAME']}",
            )
        );
    } catch (Exception $e) {
        wp_send_json(array("error"=>$e->getMessage()));
    }

    update_option('woocommerce_bitpay_key', serialize($key));
    update_option('woocommerce_bitpay_pub', serialize($pub));
    update_option('woocommerce_bitpay_sin', (string) $sin);
    update_option('woocommerce_bitpay_token', serialize($token));
    update_option('woocommerce_bitpay_label', "WooCommerce - {$_SERVER['SERVER_NAME']}");
    update_option('woocommerce_bitpay_network', $network);
    wp_send_json(array('sin'=>(string) $sin, 'label'=>"WooCommerce - {$_SERVER['SERVER_NAME']}", 'network'=>$network));
}

function woocommerce_bitpay_ajax_revoke_token()
{
    update_option('woocommerce_bitpay_key', null);
    update_option('woocommerce_bitpay_pub', null);
    update_option('woocommerce_bitpay_sin', null);
    update_option('woocommerce_bitpay_token', null);
    update_option('woocommerce_bitpay_label', null);
    update_option('woocommerce_bitpay_network', null);
    wp_send_json(array('success'=>'Token Revoked!'));
}

function woocommerce_bitpay_ajax_create_invoice()
{
    $key            = unserialize(get_option('woocommerce_bitpay_key'));
    $pub            = unserialize(get_option('woocommerce_bitpay_pub'));
    $sin            = get_option('woocommerce_bitpay_sin');
    $token            = unserialize(get_option('woocommerce_bitpay_token'));

    $client = new \Bitpay\Client\Client();
    $client->setNetwork(new \Bitpay\Network\Livenet());
    $client->setAdapter(new \Bitpay\Client\Adapter\CurlAdapter());
    $client->setPrivateKey($key);
    $client->setPublicKey($pub);
    $client->setToken($token);

    $invoice = new \Bitpay\Invoice();
    $invoice->setOrderId('TEST-01');

    $currency = new \Bitpay\Currency();
    $currency->setCode('USD');
    $invoice->setCurrency($currency);

    $item = new \Bitpay\Item();
    $item->setPrice('19.95');
    $invoice->setItem($item);
    try {
        $invoice = $client->createInvoice($invoice);
    } catch (Exception $e) {
        echo "Sin: $sin\n";
        echo "Key: $key\n";
        echo "Pub: $pub\n";
    }
    //var_dump($invoice);
}
