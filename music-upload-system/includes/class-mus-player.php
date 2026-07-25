<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public audio player grid for approved/published songs, and a per-artist
 * dashboard listing their own submissions and statuses.
 */
class MUS_Player {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'mus_published_songs', array( $this, 'render_published_songs' ) );
		add_shortcode( 'mus_dashboard', array( $this, 'render_dashboard' ) );
	}

	public function render_published_songs( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page' => 20,
			),
			$atts,
			'mus_published_songs'
		);

		$query = new WP_Query(
			array(
				'post_type'      => MUS_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['per_page'],
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( ! $query->have_posts() ) {
			return '<p class="mus-notice">' . esc_html__( 'No songs have been published yet. Check back soon!', 'music-upload-system' ) . '</p>';
		}

		ob_start();
		?>
		<div class="mus-song-grid">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$post_id       = get_the_ID();
				$attachment_id = (int) get_post_meta( $post_id, 'mus_audio_attachment_id', true );
				$audio_url     = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
				$description   = get_post_meta( $post_id, 'mus_description', true );
				$artist        = get_userdata( (int) get_post_meta( $post_id, 'mus_artist_id', true ) );
				?>
				<div class="mus-song-card">
					<h3 class="mus-song-title"><?php the_title(); ?></h3>
					<?php if ( $artist ) : ?>
						<p class="mus-song-artist"><?php echo esc_html( $artist->display_name ); ?></p>
					<?php endif; ?>
					<?php if ( $audio_url ) : ?>
						<audio controls preload="none" class="mus-audio-player">
							<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
							<?php esc_html_e( 'Your browser does not support the audio element.', 'music-upload-system' ); ?>
						</audio>
					<?php endif; ?>
					<?php if ( $description ) : ?>
						<p class="mus-song-description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
			<?php endwhile; ?>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	public function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p class="mus-notice">' . esc_html__( 'Please log in to view your submissions.', 'music-upload-system' ) . '</p>';
		}

		$user_id = get_current_user_id();

		$this->maybe_handle_retry_payment( $user_id );

		$query = new WP_Query(
			array(
				'post_type'      => MUS_POST_TYPE,
				'post_status'    => array( 'mus_pending_payment', 'mus_awaiting_review', 'mus_rejected', 'publish' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => 'mus_artist_id',
						'value' => $user_id,
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '<p class="mus-notice">' . esc_html__( "You haven't submitted any songs yet.", 'music-upload-system' ) . '</p>';
		}

		$status_labels = array(
			'mus_pending_payment' => __( 'Payment Required', 'music-upload-system' ),
			'mus_awaiting_review' => __( 'Awaiting Review', 'music-upload-system' ),
			'mus_rejected'        => __( 'Not Approved', 'music-upload-system' ),
			'publish'             => __( 'Published', 'music-upload-system' ),
		);

		ob_start();
		?>
		<table class="mus-dashboard-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Song', 'music-upload-system' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'music-upload-system' ); ?></th>
					<th><?php esc_html_e( 'Status', 'music-upload-system' ); ?></th>
					<th><?php esc_html_e( 'Action', 'music-upload-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id = get_the_ID();
					$status  = get_post_status( $post_id );
					$reason  = get_post_meta( $post_id, 'mus_rejection_reason', true );
					?>
					<tr>
						<td><?php the_title(); ?></td>
						<td><?php echo esc_html( get_the_date() ); ?></td>
						<td>
							<span class="mus-status mus-status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status ); ?>
							</span>
							<?php if ( 'mus_rejected' === $status && $reason ) : ?>
								<br><small><?php echo esc_html( $reason ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'mus_pending_payment' === $status ) : ?>
								<a class="mus-button mus-button-small" href="<?php echo esc_url( add_query_arg( 'mus_retry_payment', $post_id, get_permalink() ) ); ?>">
									<?php esc_html_e( 'Retry Payment', 'music-upload-system' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
		<?php
		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * If the artist clicked "Retry Payment" on a still-pending submission,
	 * generate a fresh Stripe Checkout Session and redirect them to it.
	 */
	private function maybe_handle_retry_payment( $user_id ) {
		if ( empty( $_GET['mus_retry_payment'] ) ) {
			return;
		}

		$post_id = absint( $_GET['mus_retry_payment'] );
		if ( MUS_POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if ( (int) get_post_meta( $post_id, 'mus_artist_id', true ) !== $user_id ) {
			return;
		}

		if ( 'mus_pending_payment' !== get_post_status( $post_id ) ) {
			return;
		}

		$checkout_url = MUS_Stripe::instance()->create_checkout_session( $post_id );

		if ( ! is_wp_error( $checkout_url ) ) {
			wp_safe_redirect( $checkout_url );
			exit;
		}
	}
}
