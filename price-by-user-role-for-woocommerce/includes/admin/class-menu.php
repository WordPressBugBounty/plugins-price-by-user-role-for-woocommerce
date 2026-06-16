<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Admin Menu.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/Menu
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * Class for adding the Menu.
 */
class Menu {

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_pbur', array( $this, 'settings_tab' ) );
	}

	/**
	 * Inject the plugin tab into WooCommerce Settings just before the Advanced tab.
	 *
	 * @param array $tabs Existing WooCommerce settings tabs.
	 * @return array
	 * @since 2.0
	 */
	public function add_settings_tab( array $tabs ): array {
		$new_tabs = array();
		foreach ( $tabs as $key => $label ) {
			if ( 'advanced' === $key ) {
				$new_tabs['pbur'] = __( 'Product Prices by User Roles', 'price-by-user-role-for-woocommerce' );
			}
			$new_tabs[ $key ] = $label;
		}
		// Fallback: append if Advanced tab is not present.
		if ( ! isset( $new_tabs['pbur'] ) ) {
			$new_tabs['pbur'] = __( 'Product Prices by User Roles', 'price-by-user-role-for-woocommerce' );
		}
		return $new_tabs;
	}

	/**
	 * Render the tab content.
	 *
	 * @since 2.0
	 */
	public function settings_tab() {
		// Hide WooCommerce's "Save changes" button; the plugin manages saving via its own REST API.
		echo '<style>.woocommerce-save-button { display: none; }</style>';
		static::admin_page();
	}

	/**
	 * Outputs the React app mount point.
	 *
	 * @since 2.0
	 */
	public static function admin_page() {
		?>
<div id="price-by-user-role-for-woocommerce"></div>
		<?php
	}
}