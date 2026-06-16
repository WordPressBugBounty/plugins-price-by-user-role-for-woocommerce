<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * REST API for WordPress user roles.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/API/Roles
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Api;

use Tyche\PBUR\Helpers\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Roles REST API.
 *
 * Returns all registered WordPress user roles so the React rules editor
 * can render custom roles alongside the built-in ones.
 *
 * @since 2.0
 */
class User_Roles extends \Tyche\PBUR\Api\Api {

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @since 2.0
	 */
	public static function register_routes() {

		register_rest_route(
			self::REST_BASE_ENDPOINT,
			'/roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'fetch_roles' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
			)
		);
	}

	/**
	 * Return all registered user roles.
	 *
	 * Includes a synthetic "guest" entry for non-logged-in visitors.
	 *
	 * @return \WP_REST_Response
	 * @since 2.0
	 */
	public static function fetch_roles() {

		$roles = array_map(
			fn( $role ) => array(
				'key'   => $role['key'],
				'label' => $role['name'],
			),
			Utils::get_user_roles()
		);

		return self::response( 'success', $roles );
	}
}
