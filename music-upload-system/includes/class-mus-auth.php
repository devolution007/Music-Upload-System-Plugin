<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end artist registration and login.
 */
class MUS_Auth {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'mus_register', array( $this, 'render_register_shortcode' ) );
		add_shortcode( 'mus_login', array( $this, 'render_login_shortcode' ) );
		add_shortcode( 'mus_logout', array( $this, 'render_logout_shortcode' ) );

		add_action( 'init', array( $this, 'handle_register_submission' ) );
		add_action( 'init', array( $this, 'handle_login_submission' ) );
		add_action( 'user_register', array( $this, 'assign_artist_role_on_native_registration' ) );
	}

	/**
	 * Visitors who sign up through WordPress's own wp-login.php?action=register
	 * form (instead of the [mus_register] shortcode) would otherwise get the
	 * site's default new-user role (usually Subscriber). This makes sure any
	 * front-end self-registration ends up with the Artist role too, so they
	 * can access the upload form and dashboard either way.
	 */
	public function assign_artist_role_on_native_registration( $user_id ) {
		global $pagenow;

		if ( 'wp-login.php' !== $pagenow ) {
			return; // Not a front-end self-registration (e.g. created from wp-admin).
		}

		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->set_role( MUS_ARTIST_ROLE );
		}
	}

	public function render_register_shortcode() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return '<p class="mus-notice">' . sprintf(
				/* translators: %s: display name. */
				esc_html__( 'You are already logged in as %s.', 'music-upload-system' ),
				esc_html( $user->display_name )
			) . '</p>';
		}

		ob_start();
		$errors = get_transient( 'mus_register_errors_' . self::visitor_token() );
		delete_transient( 'mus_register_errors_' . self::visitor_token() );
		?>
		<div class="mus-form-wrap mus-register-form">
			<?php if ( $errors ) : ?>
				<div class="mus-notice mus-error">
					<ul>
						<?php foreach ( (array) $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<form method="post">
				<p>
					<label for="mus_display_name"><?php esc_html_e( 'Artist / Stage Name', 'music-upload-system' ); ?></label>
					<input type="text" id="mus_display_name" name="mus_display_name" required>
				</p>
				<p>
					<label for="mus_email"><?php esc_html_e( 'Email Address', 'music-upload-system' ); ?></label>
					<input type="email" id="mus_email" name="mus_email" required>
				</p>
				<p>
					<label for="mus_password"><?php esc_html_e( 'Password', 'music-upload-system' ); ?></label>
					<input type="password" id="mus_password" name="mus_password" required>
				</p>
				<?php wp_nonce_field( 'mus_register_action', 'mus_register_nonce' ); ?>
				<p>
					<button type="submit" name="mus_register_submit" class="mus-button"><?php esc_html_e( 'Create Artist Account', 'music-upload-system' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public function handle_register_submission() {
		if ( empty( $_POST['mus_register_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['mus_register_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['mus_register_nonce'] ), 'mus_register_action' ) ) {
			return;
		}

		$display_name = isset( $_POST['mus_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mus_display_name'] ) ) : '';
		$email        = isset( $_POST['mus_email'] ) ? sanitize_email( wp_unslash( $_POST['mus_email'] ) ) : '';
		$password     = isset( $_POST['mus_password'] ) ? (string) $_POST['mus_password'] : '';

		$errors = array();

		if ( empty( $display_name ) ) {
			$errors[] = __( 'Please enter your artist name.', 'music-upload-system' );
		}
		if ( empty( $email ) || ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'music-upload-system' );
		} elseif ( email_exists( $email ) ) {
			$errors[] = __( 'An account with this email already exists. Please log in instead.', 'music-upload-system' );
		}
		if ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Password must be at least 8 characters long.', 'music-upload-system' );
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'mus_register_errors_' . self::visitor_token(), $errors, 60 );
			return;
		}

		$username = sanitize_user( $email, true );
		if ( username_exists( $username ) ) {
			$username = sanitize_user( $email . wp_generate_password( 4, false ), true );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $display_name,
				'role'         => MUS_ARTIST_ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			set_transient( 'mus_register_errors_' . self::visitor_token(), array( $user_id->get_error_message() ), 60 );
			return;
		}

		wp_new_user_notification( $user_id, null, 'user' );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( add_query_arg( 'mus_registered', '1', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	public function render_login_shortcode() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return '<p class="mus-notice">' . sprintf(
				/* translators: %s: display name. */
				esc_html__( 'You are logged in as %s.', 'music-upload-system' ),
				esc_html( $user->display_name )
			) . '</p>';
		}

		ob_start();
		$error = get_transient( 'mus_login_error_' . self::visitor_token() );
		delete_transient( 'mus_login_error_' . self::visitor_token() );
		?>
		<div class="mus-form-wrap mus-login-form">
			<?php if ( $error ) : ?>
				<div class="mus-notice mus-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['mus_registered'] ) ) : ?>
				<div class="mus-notice mus-success"><p><?php esc_html_e( 'Account created successfully! You are now logged in.', 'music-upload-system' ); ?></p></div>
			<?php endif; ?>
			<form method="post">
				<p>
					<label for="mus_login_email"><?php esc_html_e( 'Email or Username', 'music-upload-system' ); ?></label>
					<input type="text" id="mus_login_email" name="mus_login_email" required>
				</p>
				<p>
					<label for="mus_login_password"><?php esc_html_e( 'Password', 'music-upload-system' ); ?></label>
					<input type="password" id="mus_login_password" name="mus_login_password" required>
				</p>
				<?php wp_nonce_field( 'mus_login_action', 'mus_login_nonce' ); ?>
				<p>
					<button type="submit" name="mus_login_submit" class="mus-button"><?php esc_html_e( 'Log In', 'music-upload-system' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public function handle_login_submission() {
		if ( empty( $_POST['mus_login_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['mus_login_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['mus_login_nonce'] ), 'mus_login_action' ) ) {
			return;
		}

		$creds = array(
			'user_login'    => isset( $_POST['mus_login_email'] ) ? sanitize_text_field( wp_unslash( $_POST['mus_login_email'] ) ) : '',
			'user_password' => isset( $_POST['mus_login_password'] ) ? (string) $_POST['mus_login_password'] : '',
			'remember'      => true,
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			set_transient( 'mus_login_error_' . self::visitor_token(), __( 'Invalid username/email or password.', 'music-upload-system' ), 60 );
			return;
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		exit;
	}

	public function render_logout_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$url = wp_logout_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		return '<a class="mus-button mus-logout" href="' . esc_url( $url ) . '">' . esc_html__( 'Log Out', 'music-upload-system' ) . '</a>';
	}

	/**
	 * A lightweight per-visitor token (session-less) used to store transient
	 * form errors between the redirect-free POST and the next page render.
	 */
	public static function visitor_token() {
		if ( empty( $_COOKIE['mus_visitor_token'] ) ) {
			$token = wp_generate_password( 20, false );
			setcookie( 'mus_visitor_token', $token, time() + HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
			$_COOKIE['mus_visitor_token'] = $token;
		}
		return sanitize_text_field( $_COOKIE['mus_visitor_token'] );
	}
}
