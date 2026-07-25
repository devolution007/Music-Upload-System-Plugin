<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end song upload form: title, description, MP3 file.
 * On successful submission, creates a "mus_pending_payment" post and
 * hands off to MUS_Stripe to start the checkout flow.
 */
class MUS_Upload {

	const MAX_FILE_SIZE = 26214400; // 25MB.

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'mus_upload_form', array( $this, 'render_upload_form' ) );
		add_action( 'init', array( $this, 'handle_upload_submission' ) );
	}

	public function render_upload_form() {
		if ( ! is_user_logged_in() ) {
			return '<p class="mus-notice">' . esc_html__( 'Please log in or register as an artist to upload a song.', 'music-upload-system' ) . '</p>';
		}

		if ( ! current_user_can( 'mus_submit_song' ) && ! current_user_can( 'manage_options' ) ) {
			return '<p class="mus-notice">' . esc_html__( 'Your account does not have permission to submit songs.', 'music-upload-system' ) . '</p>';
		}

		ob_start();
		$errors = get_transient( 'mus_upload_errors_' . get_current_user_id() );
		delete_transient( 'mus_upload_errors_' . get_current_user_id() );
		$fee = MUS_Settings::get( 'upload_fee' );
		?>
		<div class="mus-form-wrap mus-upload-form">
			<?php if ( $errors ) : ?>
				<div class="mus-notice mus-error">
					<ul>
						<?php foreach ( (array) $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<p class="mus-fee-notice">
				<?php
				printf(
					/* translators: %s: formatted fee amount. */
					esc_html__( 'A one-time submission fee of $%s applies. You will be redirected to Stripe to complete secure payment after uploading your song.', 'music-upload-system' ),
					esc_html( $fee )
				);
				?>
			</p>

			<form method="post" enctype="multipart/form-data">
				<p>
					<label for="mus_song_title"><?php esc_html_e( 'Song Title', 'music-upload-system' ); ?></label>
					<input type="text" id="mus_song_title" name="mus_song_title" required>
				</p>
				<p>
					<label for="mus_song_description"><?php esc_html_e( 'Description', 'music-upload-system' ); ?></label>
					<textarea id="mus_song_description" name="mus_song_description" rows="5" required></textarea>
				</p>
				<p>
					<label for="mus_song_file"><?php esc_html_e( 'MP3 Audio File', 'music-upload-system' ); ?></label>
					<input type="file" id="mus_song_file" name="mus_song_file" accept="audio/mpeg,.mp3" required>
				</p>
				<?php wp_nonce_field( 'mus_upload_action', 'mus_upload_nonce' ); ?>
				<p>
					<button type="submit" name="mus_upload_submit" class="mus-button">
						<?php esc_html_e( 'Upload & Continue to Payment', 'music-upload-system' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public function handle_upload_submission() {
		if ( empty( $_POST['mus_upload_submit'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_POST['mus_upload_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['mus_upload_nonce'] ), 'mus_upload_action' ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! current_user_can( 'mus_submit_song' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$title       = isset( $_POST['mus_song_title'] ) ? sanitize_text_field( wp_unslash( $_POST['mus_song_title'] ) ) : '';
		$description = isset( $_POST['mus_song_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mus_song_description'] ) ) : '';

		$errors = array();

		if ( empty( $title ) ) {
			$errors[] = __( 'Please enter a song title.', 'music-upload-system' );
		}
		if ( empty( $description ) ) {
			$errors[] = __( 'Please enter a description.', 'music-upload-system' );
		}

		if ( empty( $_FILES['mus_song_file'] ) || UPLOAD_ERR_NO_FILE === $_FILES['mus_song_file']['error'] ) {
			$errors[] = __( 'Please choose an MP3 file to upload.', 'music-upload-system' );
		} elseif ( UPLOAD_ERR_OK !== $_FILES['mus_song_file']['error'] ) {
			$errors[] = __( 'There was an error uploading your file. Please try again.', 'music-upload-system' );
		} else {
			$file = $_FILES['mus_song_file'];

			if ( $file['size'] > self::MAX_FILE_SIZE ) {
				$errors[] = __( 'File is too large. Maximum size is 25MB.', 'music-upload-system' );
			}

			$file_type = wp_check_filetype( $file['name'] );
			$finfo     = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : false;
			$mime      = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : $file['type'];
			if ( $finfo ) {
				finfo_close( $finfo );
			}

			$allowed_mimes = array( 'audio/mpeg', 'audio/mp3' );
			if ( 'mp3' !== strtolower( $file_type['ext'] ) || ! in_array( $mime, $allowed_mimes, true ) ) {
				$errors[] = __( 'Only MP3 audio files are allowed.', 'music-upload-system' );
			}
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'mus_upload_errors_' . $user_id, $errors, 60 );
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Create the submission post first so we can attach the file as its child.
		$post_id = wp_insert_post(
			array(
				'post_type'   => MUS_POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'mus_pending_payment',
				'post_author' => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			set_transient( 'mus_upload_errors_' . $user_id, array( __( 'Could not create your submission. Please try again.', 'music-upload-system' ) ), 60 );
			return;
		}

		$attachment_id = media_handle_upload( 'mus_song_file', $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_post( $post_id, true );
			set_transient( 'mus_upload_errors_' . $user_id, array( __( 'Could not process your audio file. Please try again.', 'music-upload-system' ) ), 60 );
			return;
		}

		update_post_meta( $post_id, 'mus_artist_id', $user_id );
		update_post_meta( $post_id, 'mus_description', $description );
		update_post_meta( $post_id, 'mus_audio_attachment_id', $attachment_id );
		update_post_meta( $post_id, 'mus_stripe_payment_status', 'unpaid' );

		$checkout_url = MUS_Stripe::instance()->create_checkout_session( $post_id );

		if ( is_wp_error( $checkout_url ) ) {
			set_transient( 'mus_upload_errors_' . $user_id, array( $checkout_url->get_error_message() ), 60 );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
			exit;
		}

		wp_safe_redirect( $checkout_url );
		exit;
	}
}
