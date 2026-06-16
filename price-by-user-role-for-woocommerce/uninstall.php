<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Uninstall functions.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Uninstall
 * @category    Classes
 * @since       2.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// If the free/lite version is present, preserve shared data.
if ( file_exists( WP_PLUGIN_DIR . '/price-by-user-role-for-woocommerce/price-by-user-role-for-woocommerce.php' ) ) {
	return;
}

if ( ! defined( 'PBUR_SLUG' ) ) {
	define( 'PBUR_SLUG', 'pbur' );
}

require_once __DIR__ . '/includes/core/class-uninstall.php';

\Tyche\PBUR\Uninstall::init();
