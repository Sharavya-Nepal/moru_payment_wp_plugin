<?php
/**
 * Plugin Name: Moru Payment Gateway
 * Plugin URI: https://moru.com.np
 * Description: Moru Payment Gateway integration for WooCommerce.
 * Version: 1.0.0
 * Author: Sharavya Technologies
 * Author URI: https://sharavya.com
 * Text Domain: moru-payment-gateway
 * Domain Path: /i18n/languages/
 *
 * @package Moru_Payment_Gateway
 */

defined( 'ABSPATH' ) || exit;

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WooCommerce
 */
function add_moru_gateway_class( $gateways ) {
	$gateways[] = 'WC_Moru_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'add_moru_gateway_class' );

/**
 * Initialize Gateway Class
 */
function init_moru_gateway_class() {
	require_once plugin_dir_path( __FILE__ ) . 'class-wc-moru-gateway.php';
}
add_action( 'plugins_loaded', 'init_moru_gateway_class' );

/**
 * Initialize Blocks Integration
 */
function moru_gateway_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-wc-moru-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Moru_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'moru_gateway_block_support' );
