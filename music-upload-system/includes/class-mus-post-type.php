<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Song Submission custom post type, custom statuses, and admin meta boxes.
 */
class MUS_Post_Type {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_statuses' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'manage_' . MUS_POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . MUS_POST_TYPE . '_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
	}

	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Song Submissions', 'music-upload-system' ),
			'singular_name'      => __( 'Song Submission', 'music-upload-system' ),
			'menu_name'          => __( 'Song Uploads', 'music-upload-system' ),
			'add_new_item'       => __( 'Add New Submission', 'music-upload-system' ),
			'edit_item'          => __( 'Edit Submission', 'music-upload-system' ),
			'new_item'           => __( 'New Submission', 'music-upload-system' ),
			'view_item'          => __( 'View Submission', 'music-upload-system' ),
			'search_items'       => __( 'Search Submissions', 'music-upload-system' ),
			'not_found'          => __( 'No submissions found', 'music-upload-system' ),
			'not_found_in_trash' => __( 'No submissions found in Trash', 'music-upload-system' ),
			'all_items'          => __( 'All Submissions', 'music-upload-system' ),
		);

		register_post_type(
			MUS_POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => true,
				'publicly_queryable' => true,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-format-audio',
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => array( 'slug' => 'song' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'show_in_rest'    => false,
			)
		);
	}

	/**
	 * Registers the custom workflow statuses used for song submissions.
	 *
	 * publish        => Approved & Published (core status, used by the front-end player).
	 * mus_pending_payment => Awaiting Stripe payment.
	 * mus_awaiting_review => Paid, waiting for admin approval.
	 * mus_rejected   => Reviewed and rejected by admin.
	 */
	public function register_statuses() {
		register_post_status(
			'mus_pending_payment',
			array(
				'label'                     => _x( 'Pending Payment', 'song status', 'music-upload-system' ),
				'public'                    => false,
				'internal'                  => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of submissions. */
				'label_count'               => _n_noop( 'Pending Payment <span class="count">(%s)</span>', 'Pending Payment <span class="count">(%s)</span>', 'music-upload-system' ),
			)
		);

		register_post_status(
			'mus_awaiting_review',
			array(
				'label'                     => _x( 'Awaiting Review', 'song status', 'music-upload-system' ),
				'public'                    => false,
				'internal'                  => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Awaiting Review <span class="count">(%s)</span>', 'Awaiting Review <span class="count">(%s)</span>', 'music-upload-system' ),
			)
		);

		register_post_status(
			'mus_rejected',
			array(
				'label'                     => _x( 'Rejected', 'song status', 'music-upload-system' ),
				'public'                    => false,
				'internal'                  => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'music-upload-system' ),
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'mus_song_details',
			__( 'Submission Details', 'music-upload-system' ),
			array( $this, 'render_details_meta_box' ),
			MUS_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mus_song_review',
			__( 'Admin Review', 'music-upload-system' ),
			array( $this, 'render_review_meta_box' ),
			MUS_POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_details_meta_box( $post ) {
		$artist_id     = (int) get_post_meta( $post->ID, 'mus_artist_id', true );
		$description   = get_post_meta( $post->ID, 'mus_description', true );
		$attachment_id = (int) get_post_meta( $post->ID, 'mus_audio_attachment_id', true );
		$payment_status = get_post_meta( $post->ID, 'mus_stripe_payment_status', true );
		$amount_paid   = get_post_meta( $post->ID, 'mus_amount_paid', true );
		$artist        = $artist_id ? get_userdata( $artist_id ) : false;
		$audio_url     = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		?>
		<p>
			<strong><?php esc_html_e( 'Artist:', 'music-upload-system' ); ?></strong>
			<?php echo $artist ? esc_html( $artist->display_name . ' (' . $artist->user_email . ')' ) : esc_html__( 'Unknown', 'music-upload-system' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Description:', 'music-upload-system' ); ?></strong><br>
			<?php echo nl2br( esc_html( $description ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Payment Status:', 'music-upload-system' ); ?></strong>
			<?php echo esc_html( $payment_status ? ucfirst( $payment_status ) : __( 'Unpaid', 'music-upload-system' ) ); ?>
			<?php if ( $amount_paid ) : ?>
				(<?php echo esc_html( $amount_paid ); ?>)
			<?php endif; ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Audio File:', 'music-upload-system' ); ?></strong><br>
			<?php if ( $audio_url ) : ?>
				<audio controls preload="none" style="max-width:100%;">
					<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
				</audio>
			<?php else : ?>
				<?php esc_html_e( 'No audio file attached.', 'music-upload-system' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	public function render_review_meta_box( $post ) {
		$status          = get_post_status( $post->ID );
		$rejection_reason = get_post_meta( $post->ID, 'mus_rejection_reason', true );
		?>
		<p>
			<strong><?php esc_html_e( 'Current Status:', 'music-upload-system' ); ?></strong>
			<?php echo esc_html( get_post_status_object( $status ) ? get_post_status_object( $status )->label : $status ); ?>
		</p>

		<?php if ( 'mus_awaiting_review' === $status ) : ?>
			<div class="mus-review-actions">
				<a href="<?php echo esc_url( MUS_Admin::get_review_action_url( $post->ID, 'approve' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Approve & Publish', 'music-upload-system' ); ?>
				</a>
				<a href="<?php echo esc_url( MUS_Admin::get_review_action_url( $post->ID, 'reject' ) ); ?>" class="button" style="margin-top:6px;display:inline-block;">
					<?php esc_html_e( 'Reject', 'music-upload-system' ); ?>
				</a>
			</div>
		<?php elseif ( 'publish' === $status ) : ?>
			<p><em><?php esc_html_e( 'This song has been approved and is publicly published.', 'music-upload-system' ); ?></em></p>
			<a href="<?php echo esc_url( MUS_Admin::get_review_action_url( $post->ID, 'reject' ) ); ?>" class="button">
				<?php esc_html_e( 'Unpublish / Reject', 'music-upload-system' ); ?>
			</a>
		<?php elseif ( 'mus_rejected' === $status ) : ?>
			<?php if ( $rejection_reason ) : ?>
				<p><strong><?php esc_html_e( 'Reason:', 'music-upload-system' ); ?></strong><br><?php echo esc_html( $rejection_reason ); ?></p>
			<?php endif; ?>
			<a href="<?php echo esc_url( MUS_Admin::get_review_action_url( $post->ID, 'approve' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Approve & Publish', 'music-upload-system' ); ?>
			</a>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Waiting for the artist to complete payment.', 'music-upload-system' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	public function add_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['mus_artist']  = __( 'Artist', 'music-upload-system' );
				$new_columns['mus_payment'] = __( 'Payment', 'music-upload-system' );
			}
		}
		return $new_columns;
	}

	public function render_columns( $column, $post_id ) {
		if ( 'mus_artist' === $column ) {
			$artist_id = (int) get_post_meta( $post_id, 'mus_artist_id', true );
			$artist    = $artist_id ? get_userdata( $artist_id ) : false;
			echo $artist ? esc_html( $artist->display_name ) : esc_html__( 'Unknown', 'music-upload-system' );
		}

		if ( 'mus_payment' === $column ) {
			$payment_status = get_post_meta( $post_id, 'mus_stripe_payment_status', true );
			echo esc_html( $payment_status ? ucfirst( $payment_status ) : __( 'Unpaid', 'music-upload-system' ) );
		}
	}
}
