<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Moru Payment Blocks Integration
 *
 * @package Moru_Payment_Gateway
 */
final class WC_Moru_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'moru';

	/**
	 * Initialize the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_moru_settings', [] );
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_url = plugin_dir_url( __FILE__ ) . 'assets/js/moru-blocks-integration.js';
		$script_asset_path = plugin_dir_path( __FILE__ ) . 'assets/js/moru-blocks-integration.asset.php';
		
		// Determine dependencies
		$script_asset = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(
					'wc-blocks-registry',
					'wc-settings',
					'wp-element',
					'wp-html-entities',
					'wp-i18n',
				),
				'version'      => '1.0.0'
			);

		wp_register_script(
			'wc-moru-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		); // Register script

		return [ 'wc-moru-blocks-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->get_setting( 'supports', [] ) ),
		];
	}

	public function filter_supported_features( $feature ) {
		return true;
	}
}
