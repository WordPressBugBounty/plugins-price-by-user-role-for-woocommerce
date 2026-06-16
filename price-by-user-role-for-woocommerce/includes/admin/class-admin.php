<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Admin Base Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Base Class.
 *
 * @since 2.0
 */
class Admin {

	/**
	 * Construct
	 *
	 * @since 2.0
	 */
	public function __construct() {
	}

	/**
	 * Checks if the user is on the Admin Section of the Plugin.
	 *
	 * @since 2.0
	 */
	public static function is_on_pbur_page() {
		global $pagenow;
		return 'admin.php' === $pagenow // phpcs:ignore WordPress.Security.NonceVerification
			&& isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
			&& isset( $_GET['tab'] ) && 'pbur' === $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Checks if the user is on theWP Plugin Page.
	 *
	 * @since 2.0
	 */
	public static function is_on_wp_plugin_page() {
		global $pagenow;
		return 'plugins.php' === $pagenow; // phpcs:ignore
	}
}
