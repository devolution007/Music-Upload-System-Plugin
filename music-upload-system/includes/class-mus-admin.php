<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-side approve/reject actions for song submissions, plus a rejection
 * reason prompt and row actions in the submissions list table.
 */
class MUS_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_mus_review_action', array( $this, 'handle_review_action' ) );
		add_filter( 'post_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		global $post_type;
		if ( MUS_POST_TYPE === $post_type ) {
			wp_enqueue_style( 'mus-admin', MUS_PLUGIN_URL . 'assets/css/mus-admin.css', array(), MUS_VERSION );
		}
	}

	/**
	 * Builds a nonce-protected admin-post URL for approve/reject actions.
	 */
	public static function get_review_action_url( $post_id, $decision ) {
		$url = add_query_arg(
			array(
				'action'   => 'mus_review_action',
				'post_id'  => $post_id,
				'decision' => $decision,
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'mus_review_action_' . $post_id );
	}

	public function add_row_actions( $actions, $post ) {
		if ( MUS_POST_TYPE !== $post->post_type || ! current_user_can( 'mus_manage_songs' ) ) {
			return $actions;
		}

		$status = get_post_status( $post->ID );

		if ( in_array( $status, array( 'mus_awaiting_review', 'mus_rejected' ), true ) ) {
			$actions['mus_approve'] = '<a href="' . esc_url( self::get_review_action_url( $post->ID, 'approve' ) ) . '">' . esc_html__( 'Approve', 'music-upload-system' ) . '</a>';
		}

		if ( in_array( $status, array( 'mus_awaiting_review', 'publish' ), true ) ) {
			$actions['mus_reject'] = '<a href="' . esc_url( self::get_review_action_url( $post->ID, 'reject' ) ) . '">' . esc_html__( 'Reject', 'music-upload-system' ) . '</a>';
		}

		return $actions;
	}

	public function handle_review_action() {
		if ( ! isset( $_GET['post_id'], $_GET['decision'], $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'music-upload-system' ) );
		}

		$post_id  = absint( $_GET['post_id'] );
		$decision = sanitize_text_field( wp_unslash( $_GET['decision'] ) );

		if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'mus_review_action_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'music-upload-system' ) );
		}

		if ( ! current_user_can( 'mus_manage_songs' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'music-upload-system' ) );
		}

		if ( MUS_POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Submission not found.', 'music-upload-system' ) );
		}

		$message = '';

		if ( 'approve' === $decision ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
			MUS_Emails::notify_artist_approved( $post_id );
			$message = 'approved';
		} elseif ( 'reject' === $decision ) {
			$reason = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : '';
			update_post_meta( $post_id, 'mus_rejection_reason', $reason );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'mus_rejected',
				)
			);
			MUS_Emails::notify_artist_rejected( $post_id, $reason );
			$message = 'rejected';
		} else {
			wp_die( esc_html__( 'Unknown action.', 'music-upload-system' ) );
		}

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . MUS_POST_TYPE );
		$redirect = add_query_arg( 'mus_review_result', $message, remove_query_arg( array( 'mus_review_result' ), $redirect ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	public function render_admin_notices() {
		global $post_type;
		if ( MUS_POST_TYPE !== $post_type || empty( $_GET['mus_review_result'] ) ) {
			return;
		}

		$result = sanitize_text_field( wp_unslash( $_GET['mus_review_result'] ) );

		if ( 'approved' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Submission approved and published.', 'music-upload-system' ) . '</p></div>';
		} elseif ( 'rejected' === $result ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Submission rejected.', 'music-upload-system' ) . '</p></div>';
		}
	}
}
