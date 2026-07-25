<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin deactivation. Data and the artist role are intentionally
 * left intact (removed only on uninstall) so deactivating does not lose work.
 */
class MUS_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
