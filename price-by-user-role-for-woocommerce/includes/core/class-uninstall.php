<?php
/**
 * Uninstallation functions and actions for Product Prices by User Roles for WooCommerce
 *
 * @author      Tyche Softwares
 * @package     PBUR/Uninstall
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * PBUR Uninstall Class.
 *
 * @since 2.0
 */
class Uninstall {

	/**
	 * Run all uninstall routines.
	 *
	 * On multisite, iterates over every sub-site so each site's data is
	 * cleaned independently before returning to the network admin context.
	 *
	 * @return void
	 * @since 2.0
	 */
	public static function init() {

		if ( is_multisite() ) {
			$sites = get_sites( array( 'number' => 0 ) );
			foreach ( $sites as $site ) {
				switch_to_blog( (int) $site->blog_id );
				self::delete_site_data();
				restore_current_blog();
			}
		} else {
			self::delete_site_data();
		}

		self::remove_cron_jobs();
		wp_cache_flush();
	}

	/**
	 * Delete all plugin data for the current site context.
	 *
	 * Must be called after switch_to_blog() on multisite so that $wpdb uses
	 * the correct table prefix for the target sub-site.
	 *
	 * @return void
	 * @since 2.0
	 */
	private static function delete_site_data() {

		global $wpdb;

		// Delete all v2 plugin options (pbur_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'pbur_' ) . '%'
			)
		);

		// Delete all legacy v1 options (alg_wc_price_by_user_role_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'alg_wc_price_by_user_role_' ) . '%'
			)
		);

		// Delete all per-product meta (_alg_wc_price_by_user_role_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_alg_wc_price_by_user_role_' ) . '%'
			)
		);

		// Delete license options (edd_ prefix, not covered by pbur_ wildcard).
		delete_option( 'edd_license_key_pbur' );
		delete_option( 'edd_license_key_pbur_status' );

		// Delete tracking option not covered by the pbur_ prefix.
		delete_option( 'ts_tracker_last_send' );
	}

	/**
	 * Remove scheduled cron jobs.
	 *
	 * @return void
	 * @since 2.0
	 */
	public static function remove_cron_jobs() {
		wp_clear_scheduled_hook( 'pbur_tracker_send_event' );
		wp_clear_scheduled_hook( 'pbur_ts_tracker_send_event' );
		wp_clear_scheduled_hook( 'pbur_pro_ts_tracker_send_event' );
		wp_clear_scheduled_hook( 'pbur_license_status_check' );
	}

	/**
	 * Plugin Deactivation.
	 *
	 * @return void
	 * @since 2.0
	 */
	public static function deactivate_plugin() {
		self::remove_cron_jobs();
	}
}
