<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * REST API for Admin.
 *
 * Will be used to fetch data that will be passed to the frontend.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/API
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Api;

defined( 'ABSPATH' ) || exit;

/**
 * REST API.
 *
 * @since 2.0
 */
class Api extends \Tyche\PBUR\Admin {

	/**
	 * REST API namespace used by all PBUR endpoints.
	 *
	 * @var string
	 */
	const REST_BASE_ENDPOINT = 'pbur/v1';

	/**
	 * Returns the REST API response.
	 *
	 * @param string $status Status of response.
	 * @param string $data Data.
	 *
	 * @since 2.0
	 */
	public static function response( $status, $data ) {
		return self::return_response(
			array(
				'status' => $status,
				'data'   => $data,
			)
		);
	}

	/**
	 * Returns the REST API response.
	 *
	 * @param string|array $response Response data.
	 * @return WP_REST_Response
	 * @since 2.0
	 */
	public static function return_response( $response ) {
		return rest_ensure_response( $response );
	}

	/**
	 * Returns an error message.
	 *
	 * @since 2.0
	 */
	public static function error() {
		return self::return_response( 'error' );
	}

	/**
	 * Verify nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param bool            $stop_execution TRUE - stops execution, FALSE - return status of nonce verification.
	 *
	 * @since 2.0
	 */
	public static function verify_nonce( $request, $stop_execution = true ) {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {

			if ( $stop_execution ) {
				wp_send_json(
					array(
						'status' => 'error',
						'data'   => 'error',
					),
					403
				);
			}

			return false;
		}

		return true;
	}

	/**
	 * Permissions
	 *
	 * @since 2.0
	 */
	public static function permissions() {
		return current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}

	/**
	 * Sanitize an array of strings.
	 *
	 * @param mixed $value Input value.
	 * @return array
	 * @since 2.0
	 */
	public static function sanitize_string_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_map( 'sanitize_text_field', $value ) );
	}

	/**
	 * Sanitize Individual Field.
	 *
	 * @param mixed  $value Field value.
	 * @param string $type  Field type.
	 *
	 * @since 2.0
	 */
	public static function sanitize_field( $value, $type ) {

		switch ( $type ) {

			case 'text':
				return sanitize_text_field( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'email':
				return sanitize_email( $value );

			case 'slug':
				return sanitize_title( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'bool':
				return ! empty( $value );

			case 'integer':
			case 'number':
				return intval( $value );

			case 'float':
				return floatval( $value );

			case 'array':
				return is_array( $value )
				? $value
				: array();

			default:
				return sanitize_text_field( $value );

		}
	}
}
