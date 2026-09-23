<?php
/**
 * Removes all plugin data when the plugin is deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rating_glance_settings' );
delete_option( 'rating_glance_data' );
wp_clear_scheduled_hook( 'rating_glance_refresh' );
