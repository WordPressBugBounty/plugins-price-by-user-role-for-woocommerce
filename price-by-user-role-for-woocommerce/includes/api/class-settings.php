<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * REST API for Settings.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/API/Settings
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Api;

use WP_REST_Request;
use Tyche\PBUR\Helpers\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * REST API.
 *
 * @since 2.0
 */
class Settings extends \Tyche\PBUR\Api\Api {

	/**
	 * Option key.
	 */
	const OPTION_KEY = PBUR_SLUG . '_settings';

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
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'fetch_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions' ),
				),
			)
		);

		register_rest_route(
			self::REST_BASE_ENDPOINT,
			'/settings/reset',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'reset_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions' ),
				),
			)
		);

		register_rest_route(
			self::REST_BASE_ENDPOINT,
			'/settings/reset-tracking',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'reset_tracking' ),
					'permission_callback' => array( __CLASS__, 'permissions' ),
				),
			)
		);
	}

	/**
	 * Settings Schema.
	 *
	 * Defines field types for sanitization.
	 *
	 * @since 2.0
	 */
	private static function schema() {
		return array(
			'pluginEnabled'       => 'bool',
			'disableForBots'      => 'bool',
			'multipliersEnabled'  => 'bool',
			'multipliersShipping' => 'bool',
			'multipliers'         => 'multipliers',
			'perProductEnabled'   => 'bool',
			'perProductRoles'     => 'array',
		);
	}

	/**
	 * Get Settings.
	 *
	 * @since 2.0
	 */
	public static function fetch_settings() {

		$saved    = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( $saved, self::default_settings() );

		$settings['availableRoles']      = Utils::get_user_roles();
		if ( current_user_can( 'manage_options' ) ) {
			$settings['trackingStatus'] = get_option( 'pbur_allow_tracking', '' );
		}

		return self::response( 'success', $settings );
	}

	/**
	 * Maps each settings section to its owned keys.
	 *
	 * @return array<string, string[]>
	 * @since 2.0
	 */
	private static function section_keys() {
		return array(
			'general'     => array( 'pluginEnabled', 'disableForBots' ),
			'multipliers' => array( 'multipliersEnabled', 'multipliersShipping', 'multipliers' ),
			'per_product' => array( 'perProductEnabled', 'perProductRoles' ),
		);
	}

	/**
	 * Reset Plugin Usage Tracking.
	 *
	 * Deletes the tracking consent option so the notice is shown again on the next admin load.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @since 2.0
	 */
	public static function reset_tracking( WP_REST_Request $request ) {

		if ( ! self::verify_nonce( $request, false ) ) {
			return self::response(
				'error',
				array( 'error_description' => __( 'Authentication has failed.', 'price-by-user-role-for-woocommerce' ) )
			);
		}

		delete_option( 'pbur_allow_tracking' );
		delete_option( 'ts_tracker_last_send' );

		return self::response(
			'success',
			array(
				'message'        => __( 'Plugin usage tracking has been reset.', 'price-by-user-role-for-woocommerce' ),
				'trackingStatus' => '',
			)
		);
	}

	/**
	 * Reset Settings.
	 *
	 * Restores default values for all keys belonging to the given section.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return JSON
	 * @since 2.0
	 */
	public static function reset_settings( WP_REST_Request $request ) {

		if ( ! self::verify_nonce( $request, false ) ) {
			return self::response(
				'error',
				array(
					'error_description' => __( 'Authentication has failed.', 'price-by-user-role-for-woocommerce' ),
				)
			);
		}

		$params  = $request->get_json_params();
		$section = isset( $params['section'] ) ? sanitize_key( $params['section'] ) : '';

		$section_map = self::section_keys();

		if ( ! isset( $section_map[ $section ] ) ) {
			return self::response(
				'error',
				array(
					'error_description' => __( 'Invalid section.', 'price-by-user-role-for-woocommerce' ),
				)
			);
		}

		$section_labels = array(
			'general'     => __( 'General', 'price-by-user-role-for-woocommerce' ),
			'multipliers' => __( 'Multipliers', 'price-by-user-role-for-woocommerce' ),
			'per_product' => __( 'Per Product', 'price-by-user-role-for-woocommerce' ),
		);

		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::default_settings();

		foreach ( $section_map[ $section ] as $key ) {
			$saved[ $key ] = $defaults[ $key ];
		}

		update_option( self::OPTION_KEY, $saved );

		$settings                        = wp_parse_args( $saved, $defaults );
		$settings['availableRoles']      = Utils::get_user_roles();

		return self::response(
			'success',
			array(
				/* translators: %s: settings section name */
				'message'  => sprintf( __( '%s Settings have been reset to default.', 'price-by-user-role-for-woocommerce' ), $section_labels[ $section ] ),
				'settings' => $settings,
			)
		);
	}

	/**
	 * Save Settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return JSON
	 *
	 * @since 2.0
	 */
	public static function save_settings( WP_REST_Request $request ) {

		if ( ! self::verify_nonce( $request, false ) ) {
			return self::response(
				'error',
				array(
					'error_description' => __( 'Authentication has failed.', 'price-by-user-role-for-woocommerce' ),
				)
			);
		}

		$params = $request->get_json_params();

		if ( ! $params ) {
			return self::response(
				'error',
				array(
					'error_description' => __( 'No data received.', 'price-by-user-role-for-woocommerce' ),
				)
			);
		}

		// Remove the section key — it's just for JS routing, not stored.
		unset( $params['section'] );

		// Merge over existing so any field absent from the payload keeps its saved value.
		$existing = get_option( self::OPTION_KEY, array() );
		$merged   = array_merge( $existing, (array) $params );

		$settings = self::sanitize_settings( $merged );

		update_option( self::OPTION_KEY, $settings );

		return self::response(
			'success',
			array(
				'message'  => __( 'Settings saved successfully.', 'price-by-user-role-for-woocommerce' ),
				'settings' => $settings,
			)
		);
	}

	/**
	 * Sanitize settings from request params.
	 *
	 * @param array $params Raw params.
	 * @return array
	 * @since 2.0
	 */
	private static function sanitize_settings( $params ) {

		$schema   = self::schema();
		$defaults = self::default_settings();
		$result   = array();

		foreach ( $schema as $field => $type ) {

			$value = array_key_exists( $field, $params ) ? $params[ $field ] : $defaults[ $field ];

			if ( 'multipliers' === $type ) {
				$result[ $field ] = self::sanitize_multipliers( $value );
			} elseif ( 'term_ids' === $type ) {
				$result[ $field ] = is_array( $value )
					? array_values( array_filter( array_map( 'absint', $value ) ) )
					: array();
			} elseif ( 'array' === $type ) {
				$result[ $field ] = is_array( $value )
					? array_map( 'sanitize_text_field', $value )
					: array();
			} else {
				$result[ $field ] = self::sanitize_field( $value, $type );
			}
		}

		return $result;
	}

	/**
	 * Sanitize multipliers structure.
	 *
	 * @param mixed $value Raw multipliers value.
	 * @return array
	 * @since 2.0
	 */
	private static function sanitize_multipliers( $value ) {

		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $value as $role => $data ) {
			$role = sanitize_key( $role );

			if ( ! is_array( $data ) ) {
				continue;
			}

			$sanitized[ $role ] = array(
				'value'      => isset( $data['value'] ) ? (float) $data['value'] : 1.0,
				'emptyPrice' => ! empty( $data['emptyPrice'] ),
			);
		}

		return $sanitized;
	}

	/**
	 * Default Settings Data.
	 *
	 * @since 2.0
	 */
	public static function default_settings() {

		return array(
			'pluginEnabled'       => true,
			'disableForBots'      => false,
			'multipliersEnabled'  => false,
			'multipliersShipping' => false,
			'multipliers'         => array(),
			'perProductEnabled'   => true,
			'perProductRoles'     => array(),
		);
	}

}
