<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Boilerplate components.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Components
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * It will Add all the Boilerplate component when we activate the plugin.
 */
class Admin_Component {

	/**
	 * It will Add all the Boilerplate component when we activate the plugin.
	 */
	public function __construct() {

		if ( ! is_admin() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$post_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( strpos( $request_uri, 'plugins.php' ) !== false || strpos( $request_uri, 'action=deactivate' ) !== false || ( strpos( $request_uri, 'admin-ajax.php' ) !== false && 'tyche_plugin_deactivation_submit_action' === $post_action ) ) {
			PBUR()::include_file( 'components/plugin-deactivation/class-plugin-deactivation.php' );
			new Plugin_Deactivation(
				array(
					'plugin_name'       => PBUR_PLUGIN_NAME,
					'plugin_base'       => plugin_basename( PBUR_FILE ),
					'script_file'       => PBUR()::get_asset_url( '/assets/js/plugin-deactivation.js', PBUR_FILE ),
					'plugin_short_name' => 'pbur',
					'version'           => PBUR_PLUGIN_VERSION,
					'plugin_locale'     => 'price-by-user-role-for-woocommerce',
				)
			);
		}

		PBUR()::include_file( 'components/plugin-tracking/class-plugin-tracking.php' );
		new Plugin_Tracking(
			array(
				'plugin_name'       => PBUR_PLUGIN_NAME,
				'plugin_locale'     => 'price-by-user-role-for-woocommerce',
				'plugin_short_name' => 'pbur',
				'version'           => PBUR_PLUGIN_VERSION,
				'blog_link'         => 'https://www.tychesoftwares.com/price-by-user-role-for-woocommerce-plugin-usage-tracking/',
			)
		);
	}
}
