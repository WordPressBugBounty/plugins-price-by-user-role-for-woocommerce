<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Settings Helper Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Helpers
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Helper Class.
 *
 * Thin wrapper around the single pbur_settings option.
 * Provides typed accessors for per-role multiplier data.
 *
 * @since 2.0
 */
class Settings extends Helper {

	/**
	 * Get the option key used to retrieve settings.
	 *
	 * @return string
	 * @since 2.0
	 */
	protected static function option_key() {
		return \Tyche\PBUR\Api\Settings::OPTION_KEY;
	}

	/**
	 * Get default configuration values.
	 *
	 * @return array
	 * @since 2.0
	 */
	protected static function defaults() {
		return \Tyche\PBUR\Api\Settings::default_settings();
	}

	/**
	 * Get the multiplier value for a user role.
	 *
	 * @param string $role User role slug.
	 * @return float
	 * @since 2.0
	 */
	public static function get_multiplier( string $role ): float {
		$multipliers = static::get( 'multipliers', array() );
		return isset( $multipliers[ $role ]['value'] ) ? (float) $multipliers[ $role ]['value'] : 1.0;
	}

	/**
	 * Check if the empty-price flag is set for a user role.
	 *
	 * @param string $role User role slug.
	 * @return bool
	 * @since 2.0
	 */
	public static function is_empty_price( string $role ): bool {
		$multipliers = static::get( 'multipliers', array() );
		return ! empty( $multipliers[ $role ]['emptyPrice'] );
	}
}
