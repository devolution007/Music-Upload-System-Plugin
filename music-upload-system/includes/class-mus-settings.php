<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page: Stripe keys, upload fee amount/currency, and page assignments.
 */
class MUS_Settings {

	const OPTION_KEY = 'mus_settings';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function get_settings() {
		$defaults = array(
			'stripe_publishable_key' => '',
			'stripe_secret_key'      => '',
			'stripe_webhook_secret'  => '',
			'upload_fee'             => '25.00',
			'currency'               => 'usd',
			'guidelines_page_id'     => 0,
		);
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	public static function get( $key ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}

	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . MUS_POST_TYPE,
			__( 'Music Upload System Settings', 'music-upload-system' ),
			__( 'Settings', 'music-upload-system' ),
			'manage_options',
			'mus-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'mus_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$output = self::get_settings();

		$output['stripe_publishable_key'] = isset( $input['stripe_publishable_key'] ) ? sanitize_text_field( $input['stripe_publishable_key'] ) : '';
		$output['stripe_secret_key']      = isset( $input['stripe_secret_key'] ) ? sanitize_text_field( $input['stripe_secret_key'] ) : '';
		$output['stripe_webhook_secret']  = isset( $input['stripe_webhook_secret'] ) ? sanitize_text_field( $input['stripe_webhook_secret'] ) : '';
		$output['currency']               = isset( $input['currency'] ) ? strtolower( sanitize_text_field( $input['currency'] ) ) : 'usd';

		$fee = isset( $input['upload_fee'] ) ? (float) $input['upload_fee'] : 25;
		$output['upload_fee'] = number_format( max( 0, $fee ), 2, '.', '' );

		return $output;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings    = self::get_settings();
		$webhook_url = rest_url( 'mus/v1/stripe-webhook' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Music Upload System Settings', 'music-upload-system' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'mus_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mus_upload_fee"><?php esc_html_e( 'Upload Fee', 'music-upload-system' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" id="mus_upload_fee" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[upload_fee]" value="<?php echo esc_attr( $settings['upload_fee'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Amount charged per song submission (default 25.00).', 'music-upload-system' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mus_currency"><?php esc_html_e( 'Currency', 'music-upload-system' ); ?></label></th>
						<td>
							<input type="text" id="mus_currency" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Three-letter ISO currency code, e.g. usd.', 'music-upload-system' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mus_stripe_publishable_key"><?php esc_html_e( 'Stripe Publishable Key', 'music-upload-system' ); ?></label></th>
						<td><input type="text" id="mus_stripe_publishable_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stripe_publishable_key]" value="<?php echo esc_attr( $settings['stripe_publishable_key'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="mus_stripe_secret_key"><?php esc_html_e( 'Stripe Secret Key', 'music-upload-system' ); ?></label></th>
						<td><input type="password" autocomplete="off" id="mus_stripe_secret_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stripe_secret_key]" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="mus_stripe_webhook_secret"><?php esc_html_e( 'Stripe Webhook Signing Secret', 'music-upload-system' ); ?></label></th>
						<td>
							<input type="password" autocomplete="off" id="mus_stripe_webhook_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stripe_webhook_secret]" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Create a webhook in your Stripe Dashboard for the "checkout.session.completed" event pointing to:', 'music-upload-system' ); ?>
								<code><?php echo esc_html( $webhook_url ); ?></code>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcodes', 'music-upload-system' ); ?></h2>
			<table class="widefat striped" style="max-width:700px;">
				<tbody>
					<tr><td><code>[mus_register]</code></td><td><?php esc_html_e( 'Artist registration form.', 'music-upload-system' ); ?></td></tr>
					<tr><td><code>[mus_login]</code></td><td><?php esc_html_e( 'Artist login form.', 'music-upload-system' ); ?></td></tr>
					<tr><td><code>[mus_upload_form]</code></td><td><?php esc_html_e( 'Song upload form (requires login).', 'music-upload-system' ); ?></td></tr>
					<tr><td><code>[mus_dashboard]</code></td><td><?php esc_html_e( "Artist's own submissions and statuses.", 'music-upload-system' ); ?></td></tr>
					<tr><td><code>[mus_published_songs]</code></td><td><?php esc_html_e( 'Grid of approved songs with an audio player.', 'music-upload-system' ); ?></td></tr>
					<tr><td><code>[mus_guidelines]</code></td><td><?php esc_html_e( 'Song Upload Guidelines content.', 'music-upload-system' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
