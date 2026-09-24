<?php
/**
 * Plugin Name:       Rating Glance
 * Description:       Show your Google and Tripadvisor rating at a glance: score, number of reviews and a link. Fetched via SerpApi and cached, so it never slows your pages down.
 * Plugin URI:        https://github.com/visco-horeca/rating-glance
 * x-release-please-start-version
 * Version:           1.1.2
 * x-release-please-end
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Visco Horeca
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       rating-glance
 */

defined( 'ABSPATH' ) || exit;

define( 'RATING_GLANCE_VERSION', '1.1.2' ); // x-release-please-version
define( 'RATING_GLANCE_FILE', __FILE__ );
define( 'RATING_GLANCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RATING_GLANCE_URL', plugin_dir_url( __FILE__ ) );

require_once RATING_GLANCE_DIR . 'includes/class-rating-glance.php';
require_once RATING_GLANCE_DIR . 'includes/class-rating-glance-widget.php';

Rating_Glance::init();

if ( is_admin() ) {
	require_once RATING_GLANCE_DIR . 'includes/class-rating-glance-admin.php';
	Rating_Glance_Admin::init();
}

register_deactivation_hook( __FILE__, array( 'Rating_Glance', 'deactivate' ) );
