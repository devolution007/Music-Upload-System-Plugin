<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Song Upload Guidelines" page: auto-created on activation and rendered
 * via the [mus_guidelines] shortcode so it stays editable from Pages.
 */
class MUS_Guidelines {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'mus_guidelines', array( $this, 'render_guidelines' ) );
	}

	public function render_guidelines() {
		$fee = MUS_Settings::get( 'upload_fee' );

		ob_start();
		?>
		<div class="mus-guidelines">
			<h2><?php esc_html_e( 'Song Upload Guidelines', 'music-upload-system' ); ?></h2>
			<p><?php esc_html_e( 'Follow these steps to submit your song for consideration in the upcoming LuxProductions movie.', 'music-upload-system' ); ?></p>

			<h3><?php esc_html_e( '1. Create an Artist Account', 'music-upload-system' ); ?></h3>
			<p><?php esc_html_e( 'Register using the artist registration form with your stage name, email address, and a password.', 'music-upload-system' ); ?></p>

			<h3><?php esc_html_e( '2. Prepare Your Submission', 'music-upload-system' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'An MP3 audio file of your song (max 25MB).', 'music-upload-system' ); ?></li>
				<li><?php esc_html_e( 'A song title.', 'music-upload-system' ); ?></li>
				<li><?php esc_html_e( 'A short description of the song.', 'music-upload-system' ); ?></li>
			</ul>

			<h3><?php esc_html_e( '3. Upload Your Song', 'music-upload-system' ); ?></h3>
			<p><?php esc_html_e( 'Log in and use the song upload form to submit your title, description, and MP3 file.', 'music-upload-system' ); ?></p>

			<h3><?php esc_html_e( '4. Pay the Submission Fee', 'music-upload-system' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: formatted fee amount. */
					esc_html__( 'After uploading, you will be redirected to a secure Stripe checkout page to pay the $%s submission fee.', 'music-upload-system' ),
					esc_html( $fee )
				);
				?>
			</p>

			<h3><?php esc_html_e( '5. Await Admin Review', 'music-upload-system' ); ?></h3>
			<p><?php esc_html_e( 'Once payment is confirmed, your submission is sent to our team for review. You will receive an email once a decision has been made.', 'music-upload-system' ); ?></p>

			<h3><?php esc_html_e( '6. Publication', 'music-upload-system' ); ?></h3>
			<p><?php esc_html_e( 'Approved songs are published and featured with an audio player for the upcoming movie.', 'music-upload-system' ); ?></p>

			<p><?php esc_html_e( 'You can track the status of your submissions at any time from your artist dashboard.', 'music-upload-system' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Creates the Song Upload Guidelines page on plugin activation, if it
	 * does not already exist.
	 */
	public static function create_guidelines_page() {
		$existing_page_id = (int) MUS_Settings::get( 'guidelines_page_id' );

		if ( $existing_page_id && 'publish' === get_post_status( $existing_page_id ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Song Upload Guidelines', 'music-upload-system' ),
				'post_content' => '[mus_guidelines]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$settings = MUS_Settings::get_settings();
			$settings['guidelines_page_id'] = $page_id;
			update_option( MUS_Settings::OPTION_KEY, $settings );
		}
	}
}
