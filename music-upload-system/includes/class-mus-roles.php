<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and removes the "Artist" role used by the Music Upload System.
 */
class MUS_Roles {

	public static function add_artist_role() {
		if ( ! get_role( MUS_ARTIST_ROLE ) ) {
			add_role(
				MUS_ARTIST_ROLE,
				__( 'Artist', 'music-upload-system' ),
				array(
					'read'         => true,
					'mus_submit_song' => true,
				)
			);
		}

		// Make sure administrators can always manage submissions.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( 'mus_manage_songs' ) ) {
			$admin_role->add_cap( 'mus_manage_songs' );
		}
	}

	public static function remove_artist_role() {
		remove_role( MUS_ARTIST_ROLE );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( 'mus_manage_songs' );
		}
	}
}
