<?php
/**
 * Actions and Filters for Product Prices by User Roles for WooCommerce
 *
 * @author      Tyche Softwares
 * @package     PBUR/Hooks
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * PBUR Install Class.
 *
 * @since 2.0
 */
class Hooks {

	/**
	 * Hooks.
	 *
	 * @since 2.0
	 */
	public static function init() {
		add_filter(
			'plugin_action_links_' . plugin_basename( PBUR_FILE ),
			function ( $links ) {
				$url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=pbur' ) );

				$settings = sprintf(
					'<a href="%s" title="%s">%s</a>',
					$url,
					esc_attr__( 'Go to the settings page', 'price-by-user-role-for-woocommerce' ),
					esc_html__( 'Settings', 'price-by-user-role-for-woocommerce' )
				);

				array_unshift( $links, $settings );

				$links['upgrade'] = sprintf(
					'<a href="%s" title="%s" style="color:#d54e21;font-weight:600;">%s</a>',
					esc_url( 'https://woocommerce.com/products/product-prices-by-user-roles-for-woocommerce/?utm_source=lite-plugin&utm_medium=plugins-page&utm_campaign=upgrade-to-pro' ),
					esc_attr__( 'Upgrade to Pro', 'price-by-user-role-for-woocommerce' ),
					esc_html__( 'Upgrade to Pro', 'price-by-user-role-for-woocommerce' )
				);

				return $links;
			}
		);

	}
}
