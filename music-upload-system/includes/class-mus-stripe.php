<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe Checkout integration using direct REST API calls (no SDK/Composer
 * dependency required). Handles session creation, the customer return URL,
 * and the checkout.session.completed webhook.
 */
class MUS_Stripe {

	const API_BASE = 'https://api.stripe.com/v1';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
		add_action( 'template_redirect', array( $this, 'handle_payment_return' ) );
	}

	/**
	 * Creates a Stripe Checkout Session for a pending submission and
	 * returns the URL the artist should be redirected to.
	 *
	 * @param int $post_id Submission post ID.
	 * @return string|WP_Error Checkout URL or error.
	 */
	public function create_checkout_session( $post_id ) {
		$secret_key = MUS_Settings::get( 'stripe_secret_key' );
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'mus_stripe_not_configured', __( 'Payments are not configured yet. Please contact the site administrator.', 'music-upload-system' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'mus_invalid_post', __( 'Submission not found.', 'music-upload-system' ) );
		}

		$fee      = (float) MUS_Settings::get( 'upload_fee' );
		$currency = MUS_Settings::get( 'currency' );
		$amount_in_smallest_unit = (int) round( $fee * 100 );

		// add_query_arg() does not urlencode values, so Stripe's
		// {CHECKOUT_SESSION_ID} placeholder is preserved literally as required.
		$success_url = add_query_arg(
			array(
				'mus_action' => 'payment_return',
				'post_id'    => $post_id,
				'session_id' => '{CHECKOUT_SESSION_ID}',
			),
			home_url( '/' )
		);

		$cancel_url = add_query_arg(
			array(
				'mus_action' => 'payment_cancelled',
				'post_id'    => $post_id,
			),
			home_url( '/' )
		);

		$artist = get_userdata( (int) get_post_meta( $post_id, 'mus_artist_id', true ) );

		$body = array(
			'mode'                             => 'payment',
			'success_url'                      => $success_url,
			'cancel_url'                       => $cancel_url,
			'line_items'                       => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'    => $currency,
						'unit_amount' => $amount_in_smallest_unit,
						'product_data' => array(
							'name'        => sprintf( /* translators: %s: song title. */ __( 'Song Submission Fee: %s', 'music-upload-system' ), $post->post_title ),
							'description' => __( 'One-time review & publication submission fee.', 'music-upload-system' ),
						),
					),
				),
			),
			'metadata'                         => array(
				'mus_post_id' => $post_id,
			),
			'client_reference_id'              => (string) $post_id,
		);

		if ( $artist && is_email( $artist->user_email ) ) {
			$body['customer_email'] = $artist->user_email;
		}

		$response = wp_remote_post(
			self::API_BASE . '/checkout/sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => self::flatten_params( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $data['url'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unable to start payment session.', 'music-upload-system' );
			return new WP_Error( 'mus_stripe_error', $message );
		}

		update_post_meta( $post_id, 'mus_stripe_session_id', sanitize_text_field( $data['id'] ) );

		return esc_url_raw( $data['url'] );
	}

	/**
	 * Retrieves a Checkout Session from Stripe's API.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return array|WP_Error
	 */
	private function retrieve_checkout_session( $session_id ) {
		$secret_key = MUS_Settings::get( 'stripe_secret_key' );
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'mus_stripe_not_configured', __( 'Payments are not configured.', 'music-upload-system' ) );
		}

		$response = wp_remote_get(
			self::API_BASE . '/checkout/sessions/' . rawurlencode( $session_id ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['id'] ) ) {
			return new WP_Error( 'mus_stripe_error', __( 'Could not verify payment session.', 'music-upload-system' ) );
		}

		return $data;
	}

	/**
	 * Handles the artist's return from Stripe Checkout (success or cancel).
	 * The actual authoritative status change also happens via the webhook;
	 * this provides immediate feedback and a fallback if the webhook is delayed.
	 */
	public function handle_payment_return() {
		if ( empty( $_GET['mus_action'] ) ) {
			return;
		}

		$action  = sanitize_text_field( wp_unslash( $_GET['mus_action'] ) );
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id || MUS_POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if ( 'payment_cancelled' === $action ) {
			add_filter(
				'the_content',
				function ( $content ) {
					return '<div class="mus-notice mus-error"><p>' . esc_html__( 'Payment was cancelled. Your submission was saved — you can retry payment from your dashboard.', 'music-upload-system' ) . '</p></div>' . $content;
				}
			);
			return;
		}

		if ( 'payment_return' !== $action || empty( $_GET['session_id'] ) ) {
			return;
		}

		$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) );
		$this->mark_paid_if_confirmed( $post_id, $session_id );
	}

	/**
	 * Confirms payment directly with Stripe and, if paid, advances the
	 * submission from "mus_pending_payment" to "mus_awaiting_review".
	 */
	private function mark_paid_if_confirmed( $post_id, $session_id ) {
		$current_status = get_post_status( $post_id );
		if ( 'mus_pending_payment' !== $current_status ) {
			return; // Already processed (likely by the webhook).
		}

		$session = $this->retrieve_checkout_session( $session_id );
		if ( is_wp_error( $session ) ) {
			return;
		}

		$session_post_id = isset( $session['metadata']['mus_post_id'] ) ? absint( $session['metadata']['mus_post_id'] ) : 0;
		if ( $session_post_id !== $post_id ) {
			return;
		}

		if ( isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] ) {
			$this->complete_payment( $post_id, $session );
		}
	}

	public function register_webhook_route() {
		register_rest_route(
			'mus/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_webhook( WP_REST_Request $request ) {
		$payload         = $request->get_body();
		$signature_header = $request->get_header( 'stripe-signature' );
		$webhook_secret  = MUS_Settings::get( 'stripe_webhook_secret' );

		if ( empty( $webhook_secret ) ) {
			return new WP_REST_Response( array( 'error' => 'Webhook not configured' ), 400 );
		}

		if ( ! $this->verify_signature( $payload, $signature_header, $webhook_secret ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		if ( empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Malformed event' ), 400 );
		}

		if ( 'checkout.session.completed' === $event['type'] ) {
			$session = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
			$post_id = isset( $session['metadata']['mus_post_id'] ) ? absint( $session['metadata']['mus_post_id'] ) : 0;

			if ( $post_id && MUS_POST_TYPE === get_post_type( $post_id ) && isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] ) {
				$this->complete_payment( $post_id, $session );
			}
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Verifies the Stripe-Signature header per Stripe's documented HMAC scheme.
	 */
	private function verify_signature( $payload, $signature_header, $secret ) {
		if ( empty( $signature_header ) ) {
			return false;
		}

		$parts     = explode( ',', $signature_header );
		$timestamp = '';
		$signatures = array();

		foreach ( $parts as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			list( $key, $value ) = $pair;
			if ( 't' === $key ) {
				$timestamp = $value;
			} elseif ( 'v1' === $key ) {
				$signatures[] = $value;
			}
		}

		if ( empty( $timestamp ) || empty( $signatures ) ) {
			return false;
		}

		// Reject events older than 5 minutes to mitigate replay attacks.
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$signed_payload    = $timestamp . '.' . $payload;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected_signature, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Shared logic to move a submission from pending payment to awaiting
	 * review once Stripe confirms the charge succeeded. Idempotent.
	 */
	private function complete_payment( $post_id, $session ) {
		if ( 'mus_pending_payment' !== get_post_status( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, 'mus_stripe_payment_status', 'paid' );
		if ( ! empty( $session['id'] ) ) {
			update_post_meta( $post_id, 'mus_stripe_session_id', sanitize_text_field( $session['id'] ) );
		}
		if ( isset( $session['amount_total'] ) ) {
			$currency = isset( $session['currency'] ) ? strtoupper( $session['currency'] ) : strtoupper( MUS_Settings::get( 'currency' ) );
			update_post_meta( $post_id, 'mus_amount_paid', number_format( $session['amount_total'] / 100, 2 ) . ' ' . $currency );
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'mus_awaiting_review',
			)
		);

		MUS_Emails::notify_artist_payment_confirmed( $post_id );
		MUS_Emails::notify_admin_new_submission( $post_id );
	}

	/**
	 * Flattens a nested associative/indexed array into Stripe's
	 * bracketed form-encoding, e.g. line_items[0][price_data][currency]=usd.
	 */
	public static function flatten_params( $params, $prefix = '' ) {
		$flat = array();

		foreach ( $params as $key => $value ) {
			$new_key = $prefix ? $prefix . '[' . $key . ']' : $key;

			if ( is_array( $value ) ) {
				$flat = array_merge( $flat, self::flatten_params( $value, $new_key ) );
			} else {
				$flat[ $new_key ] = $value;
			}
		}

		return $flat;
	}
}
