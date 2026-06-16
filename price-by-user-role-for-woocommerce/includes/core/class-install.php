<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Install Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Core
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

use Tyche\PBUR\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Install Class.
 *
 * @since 2.0
 */
class Install {

	/**
	 * Hooks.
	 *
	 * @since 2.0
	 */
	public static function run() {
		add_action( 'init', array( __CLASS__, 'init' ), 5 );
	}

	/**
	 * Initialize install/update process.
	 *
	 * @since 2.0
	 */
	public static function init() {

		if ( ! PBUR()::check_requirements() ) {
			return;
		}

		self::maybe_update();
	}

	/**
	 * Run update checks and migrations.
	 *
	 * @since 2.0
	 */
	private static function maybe_update() {

		static $ran = false;

		if ( $ran ) {
			return;
		}
		$ran      = true;
		$lock_key = PBUR_SLUG . '_migration_lock';

		// Try atomic lock first.
		if ( ! add_option( $lock_key, time(), '', false ) ) {

			$lock = get_option( $lock_key );

			// If lock exists and is recent → exit.
			if ( $lock && ( time() - (int) $lock ) < 300 ) {
				return;
			}

			// Stale lock → take over.
			update_option( $lock_key, time(), false );
		}

		$current_db_version = get_option( PBUR_SLUG . '_db_version', '0.0.0' );
		$plugin_version     = PBUR_PLUGIN_VERSION;

		// Fresh install.
		if ( '0.0.0' === $current_db_version ) {

			do_action( PBUR_SLUG . '_new_installation' );

			$success = Migration::run( $current_db_version );

			if ( $success ) {
				update_option( PBUR_SLUG . '_db_version', $plugin_version );
				update_option( PBUR_SLUG . '_version', $plugin_version );
			}

			delete_option( $lock_key );
			return;
		}

		// No upgrade needed.
		if ( version_compare( $current_db_version, $plugin_version, '>=' ) ) {
			delete_option( $lock_key );
			return;
		}

		// Run migration.
		$success = Migration::run( $current_db_version );

		if ( $success ) {
			update_option( PBUR_SLUG . '_db_version', $plugin_version );
			update_option( PBUR_SLUG . '_version', $plugin_version );

			do_action( PBUR_SLUG . '_updated' );
		}

		delete_option( $lock_key );
	}


	/**
	 * Run install actions.
	 *
	 * @since 2.0
	 */
	public static function install() {

		if ( ! is_blog_installed() || get_transient( PBUR_SLUG . '_installing' ) ) {
			return;
		}

		set_transient( PBUR_SLUG . '_installing', 'yes', 600 );

		Notices::reset_notices();

		// Set initial versions if not present.
		if ( ! get_option( PBUR_SLUG . '_db_version' ) ) {
			update_option( PBUR_SLUG . '_db_version', '0.0.0' );
		}

		update_option( PBUR_SLUG . '_version', PBUR_PLUGIN_VERSION );

		delete_transient( PBUR_SLUG . '_installing' );

		set_transient( PBUR_SLUG . '_installed', true, 600 );
		set_transient( PBUR_SLUG . '_show_welcome_installation_notice', true, 600 );

		add_option( PBUR_SLUG . '_admin_installation_timestamp', time() );

		do_action( PBUR_SLUG . '_installed' );

		// Clear v1 cron jobs that may still be scheduled after an upgrade.
		wp_clear_scheduled_hook( 'pbur_tracker_send_event' );
		wp_clear_scheduled_hook( 'pbur_ts_tracker_send_event' );
		wp_clear_scheduled_hook( 'pbur_pro_ts_tracker_send_event' );
	}
}
