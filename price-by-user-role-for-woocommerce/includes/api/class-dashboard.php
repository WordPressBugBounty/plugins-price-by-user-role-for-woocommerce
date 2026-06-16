<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * REST API for Dashboard.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/API/Dashboard
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Api;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * REST API.
 *
 * @since 2.0
 */
class Dashboard extends \Tyche\PBUR\Api\Api {

	/**
	 * Construct
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_action(
			'rest_api_init',
			array( __CLASS__, 'register_routes' )
		);
	}

	/**
	 * Function for registering the API routes.
	 *
	 * @since 2.0
	 */
	public static function register_routes() {

		register_rest_route(
			self::REST_BASE_ENDPOINT,
			'/dashboard',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'fetch_data' ),
					'permission_callback' => array( __CLASS__, 'permissions' ),
				),
			)
		);
	}

	/**
	 * Get Dashboard Data.
	 *
	 * @since 2.0
	 */
	public static function fetch_data() {

		global $wpdb;

		$settings = get_option( Settings::OPTION_KEY, array() );
		$settings = wp_parse_args( $settings, Settings::default_settings() );

		$plugin_enabled      = ! empty( $settings['pluginEnabled'] );
		$multipliers_enabled = ! empty( $settings['multipliersEnabled'] );
		$per_product_enabled = ! empty( $settings['perProductEnabled'] );

		// Pricing method configured = rules exist, OR a multiplier has a non-default value, OR per-product roles are selected.
		$multipliers_configured = false;
		if ( ! empty( $settings['multipliers'] ) && is_array( $settings['multipliers'] ) ) {
			foreach ( $settings['multipliers'] as $role_data ) {
				$value = isset( $role_data['value'] ) ? (float) $role_data['value'] : 1.0;
				if ( abs( $value - 1.0 ) > 0.0001 ) {
					$multipliers_configured = true;
					break;
				}
			}
		}
		$pricing_method_configured = $multipliers_configured || ! empty( $settings['perProductRoles'] );

		// Prices assigned = at least one rule exists, OR at least one product has a per-product role price saved.
		$has_per_product_price = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE '_alg_wc_price_by_user_role_regular_price_%' AND meta_value != '' LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$prices_assigned       = $has_per_product_price;


		return self::response(
			'success',
			array(
				'pluginEnabled'           => $plugin_enabled,
				'multipliersEnabled'      => $multipliers_enabled,
				'perProductEnabled'       => $per_product_enabled,
				'pricingMethodConfigured' => $pricing_method_configured,
				'pricesAssigned'          => $prices_assigned,
			)
		);
	}
}
