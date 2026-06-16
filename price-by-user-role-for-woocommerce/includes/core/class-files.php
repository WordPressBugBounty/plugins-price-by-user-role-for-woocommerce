<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Class for including files for the Admin.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/Files
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * PBUR Files.
 *
 * @since 2.0
 */
class Files {

	/**
	 * Include files.
	 *
	 * @since 2.0
	 */
	public static function include() {

		self::frontend();
		self::api();
		self::common();

		if ( is_admin() ) {
			self::admin();
		}
	}

	/**
	 * API files — loaded on every request so REST routes are always registered.
	 *
	 * @since 2.0
	 */
	public static function api() {

		PBUR()::include_file( 'admin/class-admin.php' );
		PBUR()::include_file( 'api/class-api.php' );

		PBUR()::include_file( 'helpers/class-utils.php' );
		PBUR()::include_file( 'helpers/class-helper.php' );

		PBUR()::include_file( 'api/class-settings.php' );
		new \Tyche\PBUR\Api\Settings();

		PBUR()::include_file( 'helpers/class-settings.php' );

		PBUR()::include_file( 'api/class-dashboard.php' );
		new \Tyche\PBUR\Api\Dashboard();



		PBUR()::include_file( 'api/class-user-roles.php' );
		new \Tyche\PBUR\Api\User_Roles();

	}

	/**
	 * Common files — loaded on every request (admin, frontend, REST, WP-Cron).
	 *
	 * Use this for classes whose hooks must be registered regardless of context,
	 * such as order-pricing callbacks and scheduled cron handlers.
	 *
	 * @since 2.0
	 */
	public static function common() {

		// Order-pricing hooks needed in admin, AJAX, and REST contexts.
		PBUR()::include_file( 'admin/class-backend.php' );
		new Backend();

	}

	/**
	 * Admin-only files.
	 *
	 * @since 2.0
	 */
	public static function admin() {

		// Functions.
		PBUR()::include_file( 'functions/functions.php' );

		// Notices.
		PBUR()::include_file( 'admin/class-notices.php' );

		// Uninstallation.
		PBUR()::include_file( 'core/class-uninstall.php' );

		// Tyche Admin Components.
		PBUR()::include_file( 'components/class-admin-component.php' );
		new Admin_Component();

		// Menu.
		PBUR()::include_file( 'admin/class-admin.php' );
		PBUR()::include_file( 'admin/class-menu.php' );
		new Menu();

		PBUR()::include_file( 'core/class-migration.php' );

		// Installation.
		PBUR()::include_file( 'core/class-install.php' );
		Install::run();

		// Per-product meta box on Edit Product page.
		PBUR()::include_file( 'admin/class-product-meta-box.php' );
		new Product_Meta_Box();

		// Scripts.
		PBUR()::include_file( 'admin/class-scripts.php' );
		new Scripts();
	}

	/**
	 * Frontend files.
	 *
	 * @since 2.0
	 */
	public static function frontend() {
		PBUR()::include_file( 'frontend/class-frontend.php' );
		new Frontend();
	}
}
