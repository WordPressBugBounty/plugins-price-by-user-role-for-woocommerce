<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Migration Class for older versions of the plugin to 2.0 and above.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Core
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

use Tyche\PBUR\Api\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Migration Class.
 *
 * @since 2.0
 */
class Migration {

	/**
	 * Run migrations.
	 *
	 * @param string $from_version Previous plugin version.
	 * @return bool
	 * @since 2.0
	 */
	public static function run( $from_version ) {

		$success = true;

		// Migrate rules from v1.x (idempotent via own flag).
		if ( version_compare( (string) $from_version, '2.0', '<' ) ) {
			$success = self::migrate_rules_to_v2();
		}

		// Settings migration runs on every update until completed.
		$success = $success && self::migrate_settings_to_v2();

		return $success;
	}

	/**
	 * Migrate general settings to v2.0 format.
	 *
	 * Fresh installs: seeds defaults into pbur_settings.
	 * Upgrades from v1.x: reads legacy per-option values and converts them to
	 * the single camelCase pbur_settings option.
	 * Upgrades from early v2.x: merges any missing keys from defaults without
	 * overwriting already-saved values.
	 *
	 * Legacy option → new key mapping:
	 *   alg_wc_price_by_user_role_enabled                       → pluginEnabled (bool)
	 *   alg_wc_price_by_user_role_for_bots_disabled             → disableForBots (bool)
	 *   alg_wc_price_by_user_role_multipliers_enabled           → multipliersEnabled (bool)
	 *   alg_wc_price_by_user_role_shipping_enabled              → multipliersShipping (bool)
	 *   alg_wc_price_by_user_role_{role}                        → multipliers[role].value (float)
	 *   alg_wc_price_by_user_role_empty_price_{role}            → multipliers[role].emptyPrice (bool)
	 *   alg_wc_price_by_user_role_per_product_enabled           → perProductEnabled (bool)
	 *   alg_wc_price_by_user_role_per_product_show_roles        → perProductRoles (array)
	 *   alg_wc_price_by_user_role_show_all_prices_by_user_role  → showMultipleRolePrices (bool)
	 *   alg_wc_price_by_user_role_display_messages              → messagesEnabled (bool)
	 *   alg_wc_price_by_user_role_product_page_message          → messageProductPage (string)
	 *   alg_wc_price_by_user_role_cart_page_message             → messageCartPage (string)
	 *   alg_wc_price_by_user_role_checkout_page_message         → messageCheckoutPage (string)
	 *
	 * @return bool
	 * @since 2.0
	 */
	private static function migrate_settings_to_v2() {

		if ( get_option( 'pbur_migration_settings_v2_completed' ) ) {
			return true;
		}

		$defaults = Settings::default_settings();

		// Check whether legacy individual options were ever saved.
		// get_option returns the $default when the key is absent, so null means not saved.
		$legacy_enabled = get_option( 'alg_wc_price_by_user_role_enabled', null );

		if ( null !== $legacy_enabled ) {
			// Upgrade from v1.x — convert per-option values to the new single option.
			update_option( Settings::OPTION_KEY, self::build_settings_from_legacy( $defaults ) );
		} else {
			$existing = get_option( Settings::OPTION_KEY, null );

			if ( null === $existing ) {
				// Fresh install — seed defaults.
				update_option( Settings::OPTION_KEY, $defaults );
			} else {
				// Early v2 upgrade — fill in any keys added since they installed.
				update_option( Settings::OPTION_KEY, wp_parse_args( $existing, $defaults ) );
			}
		}

		update_option( 'pbur_migration_settings_v2_completed', true );

		return true;
	}

	/**
	 * Build a pbur_settings array from legacy individual options.
	 *
	 * @param array $defaults Default settings values.
	 * @return array
	 * @since 2.0
	 */
	private static function build_settings_from_legacy( array $defaults ) {

		global $wp_roles;

		$all_roles   = ( isset( $wp_roles ) && is_object( $wp_roles ) )
			? array_keys( $wp_roles->roles )
			: array();
		$all_roles[] = 'guest';

		$multipliers = array();

		foreach ( $all_roles as $role ) {
			$value       = (float) get_option( 'alg_wc_price_by_user_role_' . $role, 1 );
			$empty_price = 'yes' === get_option( 'alg_wc_price_by_user_role_empty_price_' . $role, 'no' );

			if ( 1.0 !== $value || $empty_price ) {
				$multipliers[ $role ] = array(
					'value'      => $value,
					'emptyPrice' => $empty_price,
				);
			}
		}

		return array(
			'pluginEnabled'       => 'yes' === get_option( 'alg_wc_price_by_user_role_enabled', 'yes' ),
			'disableForBots'      => 'yes' === get_option( 'alg_wc_price_by_user_role_for_bots_disabled', 'no' ),
			'multipliersEnabled'  => 'yes' === get_option( 'alg_wc_price_by_user_role_multipliers_enabled', 'yes' ),
			'multipliersShipping' => 'yes' === get_option( 'alg_wc_price_by_user_role_shipping_enabled', 'no' ),
			'multipliers'         => $multipliers,
			'perProductEnabled'   => 'yes' === get_option( 'alg_wc_price_by_user_role_per_product_enabled', 'yes' ),
			'perProductRoles'     => self::migrate_per_product_roles( $defaults['perProductRoles'] ),
		);
	}

	/**
	 * Read and normalise the legacy per-product roles option.
	 *
	 * @param array $fallback Fallback value if the option is absent or invalid.
	 * @return array
	 * @since 2.0
	 */
	private static function migrate_per_product_roles( array $fallback ): array {
		$raw = get_option( 'alg_wc_price_by_user_role_per_product_show_roles', null );

		if ( null === $raw ) {
			return $fallback;
		}

		// Legacy value is a serialized PHP array of role slugs.
		$roles = maybe_unserialize( $raw );

		return is_array( $roles )
			? array_values( array_filter( array_map( 'sanitize_key', $roles ) ) )
			: $fallback;
	}

	/**
	 * Migrate rules from v1.x format to v2.0 format.
	 *
	 * Old storage: option `alg_wc_price_by_user_role_rules`, associative array
	 * keyed by integer ID, each rule using different field names and value types.
	 * New storage: option `pbur_rules`, sequential array with `id` field inside
	 * each rule and normalised field names.
	 *
	 * @return bool
	 * @since 2.0
	 */
	private static function migrate_rules_to_v2() {

		if ( get_option( 'pbur_migration_rules_v2_completed' ) ) {
			return true;
		}

		$old_rules = get_option( 'alg_wc_price_by_user_role_rules', array() );

		if ( ! empty( $old_rules ) && is_array( $old_rules ) ) {
			$new_rules = array();

			foreach ( $old_rules as $old_id => $old_rule ) {
				$new_rules[] = self::transform_rule( (int) $old_id, $old_rule );
			}

			update_option( 'pbur_rules', $new_rules );
		}

		update_option( 'pbur_migration_rules_v2_completed', true );

		return true;
	}

	/**
	 * Transform a single v1.x rule into the v2.0 shape.
	 *
	 * Field mapping:
	 *   status (0/1)        → enabled (bool)
	 *   all_products (yes/no) → apply_to_all (bool)
	 *   override (yes/no)   → override_product_level (bool)
	 *   role_based_price[]  → role_pricing{} keyed by role slug
	 *   type                → adjustment_type
	 *   empty_price (yes/no)→ empty_price (bool)
	 *
	 * @param int   $old_id  Associative array key used as ID in v1.x.
	 * @param array $old_rule Rule data in v1.x format.
	 * @return array Rule in v2.0 format.
	 * @since 2.0
	 */
	private static function transform_rule( int $old_id, array $old_rule ) {

		$role_pricing = array();

		foreach ( (array) ( $old_rule['role_based_price'] ?? array() ) as $role_data ) {
			$slug = $role_data['role'] ?? '';
			if ( '' === $slug ) {
				continue;
			}
			$role_pricing[ $slug ] = array(
				'adjustment_type' => $role_data['type'] ?? 'fixed_price',
				'regular_price'   => $role_data['regular_price'] ?? '',
				'sale_price'      => $role_data['sale_price'] ?? '',
				'empty_price'     => 'yes' === ( $role_data['empty_price'] ?? '' ),
				'min_qty'         => (string) ( $role_data['min_qty'] ?? '0' ),
				'max_qty'         => (string) ( $role_data['max_qty'] ?? '0' ),
			);
		}

		return array(
			'id'                     => $old_id,
			'name'                   => $old_rule['name'] ?? '',
			'enabled'                => 1 === (int) ( $old_rule['status'] ?? 1 ),
			'apply_to_all'           => 'yes' === ( $old_rule['all_products'] ?? '' ),
			'products'               => array_map( 'strval', (array) ( $old_rule['products'] ?? array() ) ),
			'categories'             => array_map( 'strval', (array) ( $old_rule['categories'] ?? array() ) ),
			'override_product_level' => 'yes' === ( $old_rule['override'] ?? '' ),
			'role_pricing'           => $role_pricing,
		);
	}
}
