<?php
/**
 * Product Prices by User Roles for WooCommerce
 *
 * @package      Price-by-user-role-for-woocommerce
 * @copyright    Copyright (C) 2026, Tyche Softwares - support@tychesoftwares.com
 * @link         https://www.tychesoftwares.com
 * @since        2.0
 *
 * @wordpress-plugin
 * Plugin Name:  Product Prices by User Roles for WooCommerce
 * Plugin URI:   https://www.tychesoftwares.com
 * Description:  Set role-based product prices in WooCommerce. Apply global multipliers or fixed per-product prices per role. Upgrade to Pro for rules, quantity discounts, adjustment types, and more.
 * Version:      2.0.0
 * Author:       Tyche Softwares
 * Author URI:   https://www.tychesoftwares.com
 * Text Domain:  price-by-user-role-for-woocommerce
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * WC requires at least: 5.0.0
 * WC tested up to: 10.8.0
 * Tested up to: 7.0
 * Requires Plugins: woocommerce
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright 2026 Tyche Softwares
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

// Pro takes priority: check active_plugins before claiming PBUR_FILE or calling bootstrap().
// get_option() is safe here — wp-config.php and the DB layer are already loaded.
$_pbur_pro_plugin = 'price-by-user-role-for-woocommerce-pro/price-by-user-role-for-woocommerce-pro.php';
$_pbur_pro_active = in_array( $_pbur_pro_plugin, (array) get_option( 'active_plugins', array() ), true );

if ( ! $_pbur_pro_active && is_multisite() ) {
	$_pbur_pro_active = array_key_exists(
		$_pbur_pro_plugin,
		(array) get_site_option( 'active_sitewide_plugins', array() )
	);
}
unset( $_pbur_pro_plugin );

if ( $_pbur_pro_active ) {
	// Pro is active — show a notice and bail out entirely so Pro can own
	// PBUR_FILE, the PBUR() function, and bootstrap() without any conflict.
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Product Prices by User Roles for WooCommerce Pro is active. You do not need the Lite version — please deactivate it to avoid conflicts.', 'price-by-user-role-for-woocommerce' ); ?></p>
			</div>
			<?php
		}
	);
} else {
	if ( ! defined( 'PBUR_FILE' ) ) {
		define( 'PBUR_FILE', __FILE__ );
	}

	// Include the PBUR class.
	if ( ! class_exists( 'Price_By_User_Role_For_WooCommerce', false ) ) {
		include_once dirname( PBUR_FILE ) . '/includes/class-price-by-user-role-for-woocommerce.php';
	}

	Price_By_User_Role_For_WooCommerce::cleanup_v1_files();

	if ( ! function_exists( __NAMESPACE__ . '\\PBUR' ) ) {
		/**
		 * Returns the instance of PBUR.
		 *
		 * @since  2.0
		 * @return PBUR
		 */
		function PBUR() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
			return Price_By_User_Role_For_WooCommerce::instance();
		}
	}

	Price_By_User_Role_For_WooCommerce::bootstrap();
}
unset( $_pbur_pro_active );
