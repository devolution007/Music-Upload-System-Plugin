<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple transactional email notifications for the submission workflow.
 */
class MUS_Emails {

	public static function notify_admin_new_submission( $post_id ) {
		$post   = get_post( $post_id );
		$artist = get_userdata( (int) get_post_meta( $post_id, 'mus_artist_id', true ) );
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf( /* translators: %s: song title. */ __( '[Music Upload System] New paid submission: %s', 'music-upload-system' ), $post->post_title );
		$message = sprintf(
			/* translators: 1: artist name, 2: edit link. */
			__( "A new song submission has been paid for and is awaiting review.\n\nArtist: %1\$s\nSong: %2\$s\n\nReview it here: %3\$s", 'music-upload-system' ),
			$artist ? $artist->display_name : __( 'Unknown', 'music-upload-system' ),
			$post->post_title,
			admin_url( 'post.php?post=' . $post_id . '&action=edit' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	public static function notify_artist_approved( $post_id ) {
		$post   = get_post( $post_id );
		$artist = get_userdata( (int) get_post_meta( $post_id, 'mus_artist_id', true ) );
		if ( ! $artist ) {
			return;
		}

		$subject = __( 'Your song submission has been approved!', 'music-upload-system' );
		$message = sprintf(
			/* translators: 1: artist name, 2: song title. */
			__( "Hi %1\$s,\n\nGreat news! Your song \"%2\$s\" has been approved and published.\n\nThank you for your submission.", 'music-upload-system' ),
			$artist->display_name,
			$post->post_title
		);

		wp_mail( $artist->user_email, $subject, $message );
	}

	public static function notify_artist_rejected( $post_id, $reason = '' ) {
		$post   = get_post( $post_id );
		$artist = get_userdata( (int) get_post_meta( $post_id, 'mus_artist_id', true ) );
		if ( ! $artist ) {
			return;
		}

		$subject = __( 'Update on your song submission', 'music-upload-system' );
		$message = sprintf(
			/* translators: 1: artist name, 2: song title, 3: rejection reason. */
			__( "Hi %1\$s,\n\nYour song \"%2\$s\" was not approved for publication at this time.\n\n%3\$s\n\nThank you for your submission.", 'music-upload-system' ),
			$artist->display_name,
			$post->post_title,
			$reason ? sprintf( /* translators: %s: reason. */ __( 'Reason: %s', 'music-upload-system' ), $reason ) : ''
		);

		wp_mail( $artist->user_email, $subject, $message );
	}

	public static function notify_artist_payment_confirmed( $post_id ) {
		$post   = get_post( $post_id );
		$artist = get_userdata( (int) get_post_meta( $post_id, 'mus_artist_id', true ) );
		if ( ! $artist ) {
			return;
		}

		$subject = __( 'Payment received - your song is now under review', 'music-upload-system' );
		$message = sprintf(
			/* translators: 1: artist name, 2: song title. */
			__( "Hi %1\$s,\n\nWe've received your payment for \"%2\$s\". Your submission is now awaiting admin review. We'll email you as soon as a decision has been made.", 'music-upload-system' ),
			$artist->display_name,
			$post->post_title
		);

		wp_mail( $artist->user_email, $subject, $message );
	}
}
