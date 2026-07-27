<?php
/**
 * Fires when the plugin is deleted from wp-admin > Plugins. Removes all
 * plugin data: settings, the artist role, the guidelines page, and all
 * song submissions along with their attached audio files.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove the custom role and capability.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$admin_role->remove_cap( 'mus_manage_songs' );
}
remove_role( 'mus_artist' );

// Remove the guidelines page.
$settings = get_option( 'mus_settings', array() );
if ( ! empty( $settings['guidelines_page_id'] ) ) {
	wp_delete_post( (int) $settings['guidelines_page_id'], true );
}

// Remove all song submissions and their attached audio files.
$submissions = get_posts(
	array(
		'post_type'      => 'mus_song',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $submissions as $post_id ) {
	$attachment_id = get_post_meta( $post_id, 'mus_audio_attachment_id', true );
	if ( $attachment_id ) {
		wp_delete_attachment( $attachment_id, true );
	}
	$epk_attachment_id = get_post_meta( $post_id, 'mus_epk_attachment_id', true );
	if ( $epk_attachment_id ) {
		wp_delete_attachment( $epk_attachment_id, true );
	}
	wp_delete_post( $post_id, true );
}

delete_option( 'mus_settings' );
