<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin activation: registers the artist role, creates the
 * default guidelines page, and flushes rewrite rules for the CPT.
 */
class MUS_Activator {

	public static function activate() {
		require_once MUS_PLUGIN_DIR . 'includes/class-mus-post-type.php';
		require_once MUS_PLUGIN_DIR . 'includes/class-mus-settings.php';
		require_once MUS_PLUGIN_DIR . 'includes/class-mus-guidelines.php';

		MUS_Roles::add_artist_role();

		$post_type = MUS_Post_Type::instance();
		$post_type->register_post_type();
		$post_type->register_statuses();

		MUS_Guidelines::create_guidelines_page();

		flush_rewrite_rules();
	}
}
