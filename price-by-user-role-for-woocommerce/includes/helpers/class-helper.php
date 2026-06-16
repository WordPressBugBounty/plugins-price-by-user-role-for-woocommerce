<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Configuration Helper Base Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Helpers
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Configuration Helper Base Class.
 *
 * @since 2.0
 */
abstract class Helper {

	/**
	 * Cached settings (merged with defaults).
	 *
	 * @var array|null
	 * @since 2.0
	 */
	protected static $settings = null;

	/**
	 * Get the option key used to retrieve settings.
	 *
	 * @return string
	 * @since 2.0
	 */
	abstract protected static function option_key();

	/**
	 * Get default configuration values.
	 *
	 * @return array
	 * @since 2.0
	 */
	protected static function defaults() {
		return array();
	}

	/**
	 * Retrieve all settings merged with defaults.
	 *
	 * @return array
	 * @since 2.0
	 */
	public static function all() {

		if ( null !== static::$settings ) {
			return static::$settings;
		}

		$saved = get_option( static::option_key(), array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		static::$settings = wp_parse_args( $saved, static::defaults() );

		return static::$settings;
	}

	/**
	 * Retrieve a configuration value.
	 *
	 * @param string|null $key     Configuration key.
	 * @param mixed       $fallback Default value if key is not found.
	 * @return mixed
	 * @since 2.0
	 */
	public static function get( $key = null, $fallback = null ) {

		$data = static::all();

		if ( ! $key ) {
			return $data;
		}

		if ( ! array_key_exists( $key, $data ) ) {
			return $fallback;
		}

		return $data[ $key ];
	}

	/**
	 * Bust the settings cache so the next call to all() re-reads from the database.
	 *
	 * @return void
	 * @since 2.0
	 */
	public static function flush() {
		static::$settings = null;
	}
}
