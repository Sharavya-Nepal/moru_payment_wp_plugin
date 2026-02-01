<?php
/**
 * Moru Payment Gateway Class
 */
class WC_Moru_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'moru'; // unique ID
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields         = false;
		$this->method_title       = __( 'Moru Payment', 'moru-payment-gateway' );
		$this->method_description = __( 'Accept payments via Moru Digital Wallet.', 'moru-payment-gateway' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->enabled      = $this->get_option( 'enabled' );
		$this->testmode     = 'yes' === $this->get_option( 'testmode' );
		$this->access_key   = $this->testmode ? $this->get_option( 'test_access_key' ) : $this->get_option( 'live_access_key' );
		$this->secret_key   = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'live_secret_key' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_moru_gateway', array( $this, 'return_handler' ) );
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'moru-payment-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Moru Payment', 'moru-payment-gateway' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'moru-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'moru-payment-gateway' ),
				'default'     => __( 'Moru Digital Wallet', 'moru-payment-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'moru-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'moru-payment-gateway' ),
				'default'     => __( 'Pay with your Moru Digital Wallet.', 'moru-payment-gateway' ),
			),
			'testmode' => array(
				'title'       => __( 'Test Mode', 'moru-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Mode', 'moru-payment-gateway' ),
				'default'     => 'yes',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'moru-payment-gateway' ),
			),
			'test_access_key' => array(
				'title'       => __( 'Test Access Key', 'moru-payment-gateway' ),
				'type'        => 'text',
			),
			'test_secret_key' => array(
				'title'       => __( 'Test Secret Key', 'moru-payment-gateway' ),
				'type'        => 'password',
			),
			'live_access_key' => array(
				'title'       => __( 'Live Access Key', 'moru-payment-gateway' ),
				'type'        => 'text',
			),
			'live_secret_key' => array(
				'title'       => __( 'Live Secret Key', 'moru-payment-gateway' ),
				'type'        => 'password',
			),
		);
	}

	/**
	 * Output for the order received page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			if ( $this->testmode ) {
				$this->description .= ' ' . __( 'TEST MODE ENABLED. In test mode, you can use the test credentials.', 'moru-payment-gateway' );
			}
			echo wpautop( wp_kses_post( $this->description ) );
		}
	}

	/**
	 * Process the payment and return the result
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Return URL (callback)
		$return_url = WC()->api_request_url( 'wc_moru_gateway' );

		// API Endpoint
		$api_url = $this->testmode ? 'https://test.moru-gateway.pnpl.com.np/gateway/v2/initiate' : 'https://api.payment-gateway.moru.com.np/gateway/v2/initiate';

		// Prepare payload
		$body = array(
			'return_url'     => $return_url,
			'amount'         => (string) round( $order->get_total() ), // Moru often expects integer amount strings
			'transaction_id' => (string) $order->get_id() . '-' . time(), // Ensure uniqueness for dev/test
			'merchant_info'  => array(
				'email'      => $order->get_billing_email(),
				'name'       => $order->get_formatted_billing_full_name(),
			),
		);

		$headers = array(
			'Authorization' => "AUTH_KEY " . $this->access_key,
			'Content-Type'  => 'application/json',
		);

		$response = wp_remote_post( $api_url, array(
			'headers' => $headers,
			'body'    => json_encode( $body ),
			'timeout' => 45,
		) );

		if ( is_wp_error( $response ) ) {
			wc_add_notice( 'Connection error: ' . $response->get_error_message(), 'error' );
			return;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( isset( $data['data']['payment_url'] ) ) {
			return array(
				'result'   => 'success',
				'redirect' => $data['data']['payment_url'],
			);
		} else {
			$error_msg = isset( $data['message'] ) ? $data['message'] : 'Payment initiation failed.';
			wc_add_notice( 'Moru Info: ' . $error_msg, 'error' );
			return;
		}
	}

	/**
	 * Handle Return / Callback from Moru
	 */
	public function return_handler() {
		$transaction_id = isset( $_GET['transaction_id'] ) ? sanitize_text_field( $_GET['transaction_id'] ) : '';
		$moru_txn_identifier = isset( $_GET['moru_txn_identifier'] ) ? sanitize_text_field( $_GET['moru_txn_identifier'] ) : '';

		if ( ! $transaction_id || ! $moru_txn_identifier ) {
			wp_die( 'Missing parameters', 'Moru Payment', array( 'response' => 400 ) );
		}

		// Extract order ID if transaction ID has a suffix (e.g. order_id-timestamp)
		$order_id = $transaction_id;
		if ( strpos( $transaction_id, '-' ) !== false ) {
			$parts    = explode( '-', $transaction_id );
			$order_id = $parts[0];
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'Invalid Order', 'Moru Payment', array( 'response' => 400 ) );
		}

		// Verify payment via Check Payment API
		$api_url = $this->testmode ? 'https://test.moru-gateway.pnpl.com.np/gateway/v1/check-payment' : 'https://api.payment-gateway.moru.com.np/gateway/v1/check-payment';
		
		$body = array(
			'secret_key'     => $this->secret_key,
			'txn_identifier' => $moru_txn_identifier,
		);

		$headers = array(
			'Content-Type'  => 'application/json',
		);

		$response = wp_remote_post( $api_url, array(
			'headers' => $headers, // Though API spec didn't strictly say headers for check-payment, it's safer.
			'body'    => json_encode( $body ),
			'timeout' => 45,
		) );

		if ( is_wp_error( $response ) ) {
			wp_die( 'Verification connection failed: ' . $response->get_error_message() );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		// Check status
		// "state": "Completed"
		if ( isset( $data['data']['state'] ) && 'Completed' === $data['data']['state'] ) {
			// Success
			$order->payment_complete( $moru_txn_identifier );
			$order->add_order_note( sprintf( __( 'Moru Payment Success. Transaction Identifier: %s', 'moru-payment-gateway' ), $moru_txn_identifier ) );
			$order->reduce_order_stock(); // Optional, usually handled by payment_complete/status change
			
			// Redirect to Thank You page
			wp_redirect( $this->get_return_url( $order ) );
			exit;
		} else {
			// Failed or Pending
			$msg = 'Payment validation failed.';
			if ( isset( $data['data']['message'] ) ) {
				$msg = $data['data']['message'];
			} elseif ( isset( $data['error_message']['message'] ) ) {
				$msg = $data['error_message']['message'];
			}
			$order->update_status( 'failed', 'Moru validation failed: ' . $msg );
			
			wc_add_notice( 'Payment Failed: ' . $msg, 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}
