<?php
/**
 * Price by User Roles for WooCommerce - Lite
 *
 * Uninstalling Price by User Roles for WooCommerce Plugin delete settings.
 *
 * @package     PriceByUserRole
 * @author      Tyche Softwares
 * @category    Core
 * @version     1.0
 * @since       1.3
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// check if the Pro version file is present. If yes, do not delete any settings irrespective of whether the plugin is active or no.
if ( file_exists( WP_PLUGIN_DIR . '/price-by-user-role-for-woocommerce-pro/price-by-user-role-for-woocommerce-pro.php' ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

global $wpdb;
/**
 * Delete the data for the WordPress Multisite.
 */
if ( is_multisite() ) {

	$pbur_blog_list = get_sites();

	foreach ( $pbur_blog_list as $pbur_blog_list_key => $pbur_blog_list_value ) {


		$pbur_blog_id = $pbur_blog_list_value->blog_id;

		/**
		 * It indicates the sub site id.
		 */
		$pbur_multisite_prefix = $pbur_blog_id > 1 ? $wpdb->prefix . "$pbur_blog_id_" : $wpdb->prefix;

		$pbur_multisite_prefix_postmeta = "{$pbur_multisite_prefix}postmeta";
		$pbur_multisite_prefix_options  = "{$pbur_multisite_prefix}options";

		// Product Settings.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $pbur_multisite_prefix_postmeta . '` WHERE meta_key LIKE %s', // phpcs:ignore
				'_alg_wc_price_by_user_role_%'
			)
		); // WPCS: db call ok, WPCS: cache ok.

		// General Settings.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $pbur_multisite_prefix_options . '` WHERE option_name LIKE %s',  // phpcs:ignore
				'alg_wc_price_by_user_role_%'
			)
		); // WPCS: db call ok, WPCS: cache ok.

		// Version Number.
		delete_blog_option( $pbur_blog_id, 'alg_wc_price_by_user_role_version' );

	}
} else {

	// Product Settings.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . 'postmeta` WHERE meta_key LIKE %s', '_alg_wc_price_by_user_role_%' ) ); // WPCS: db call ok, WPCS: cache ok.

	// General Settings.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . 'options` WHERE option_name LIKE %s', 'alg_wc_price_by_user_role_%' ) ); // WPCS: db call ok, WPCS: cache ok.

	// Version Number.
	delete_option( 'alg_wc_price_by_user_role_version' );

}
// Clear any cached data that has been removed.
wp_cache_flush();
