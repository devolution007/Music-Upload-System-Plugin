<?php
/**
 * Plugin Name:       Music Upload System
 * Plugin URI:        https://luxproductions.example
 * Description:       Lets independent artists register, pay a submission fee via Stripe, and upload songs for admin review before they are published for LuxProductions' upcoming movie.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Devolution
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       music-upload-system
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MUS_VERSION', '1.0.0' );
define( 'MUS_PLUGIN_FILE', __FILE__ );
define( 'MUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MUS_ARTIST_ROLE', 'mus_artist' );
define( 'MUS_POST_TYPE', 'mus_song' );

require_once MUS_PLUGIN_DIR . 'includes/class-mus-roles.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-post-type.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-settings.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-emails.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-auth.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-upload.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-stripe.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-admin.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-player.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-guidelines.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-activator.php';
require_once MUS_PLUGIN_DIR . 'includes/class-mus-deactivator.php';

register_activation_hook( __FILE__, array( 'MUS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MUS_Deactivator', 'deactivate' ) );

/**
 * Boots all plugin components.
 */
function mus_init_plugin() {
	MUS_Post_Type::instance();
	MUS_Settings::instance();
	MUS_Auth::instance();
	MUS_Upload::instance();
	MUS_Stripe::instance();
	MUS_Admin::instance();
	MUS_Player::instance();
	MUS_Guidelines::instance();
}
add_action( 'plugins_loaded', 'mus_init_plugin' );

/**
 * Registers and enqueues public-facing assets.
 */
function mus_enqueue_public_assets() {
	wp_enqueue_style( 'mus-public', MUS_PLUGIN_URL . 'assets/css/mus-public.css', array(), MUS_VERSION );
	wp_enqueue_script( 'mus-public', MUS_PLUGIN_URL . 'assets/js/mus-public.js', array(), MUS_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'mus_enqueue_public_assets' );
