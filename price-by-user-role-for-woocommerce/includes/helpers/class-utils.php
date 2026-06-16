<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Utility Helper Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Helpers
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Utility Helper Class.
 *
 * Generic helpers reusable anywhere in the plugin.
 *
 * @since 2.0
 */
class Utils {

	/**
	 * Get all registered WordPress user roles.
	 *
	 * Prepends a synthetic Guest entry for non-logged-in visitors and applies
	 * the standard filter so shop managers can restrict editable roles.
	 *
	 * @return array<array{key: string, name: string}>
	 * @since 2.0
	 */
	public static function get_user_roles() {

		global $wp_roles;

		$all_roles = ( isset( $wp_roles ) && is_object( $wp_roles ) ) ? $wp_roles->roles : array();
		$all_roles = apply_filters( 'alg_woocommerce_shop_manager_editable_roles', $all_roles );

		$roles = array(
			array(
				'key'  => 'guest',
				'name' => __( 'Guest', 'price-by-user-role-for-woocommerce' ),
			),
		);

		foreach ( $all_roles as $role_key => $role_data ) {
			$roles[] = array(
				'key'  => $role_key,
				'name' => translate_user_role( $role_data['name'] ),
			);
		}

		return $roles;
	}

	/**
	 * Check whether WooCommerce HPOS (Custom Order Tables) is active.
	 *
	 * @return bool
	 * @since 2.0
	 */
	public static function is_hpos_enabled(): bool {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	/**
	 * Apply a price adjustment and return the resulting price.
	 *
	 * @param float|string $base_price     Base price value.
	 * @param float|string $value          Configured price / adjustment amount.
	 * @param string       $adjustment_type One of fixed_price, fixed_increase, fixed_decrease,
	 *                                      percentage_increase, percentage_decrease.
	 * @return float
	 * @since 2.0
	 */
	public static function apply_price_adjustment( $base_price, $value, string $adjustment_type ): float {
		$base  = (float) ( '' !== $base_price ? $base_price : 0 );
		$value = (float) ( '' !== $value ? $value : 0 );

		switch ( $adjustment_type ) {
			case 'fixed_increase':
				return $base + $value;
			case 'fixed_decrease':
				return $base - $value;
			case 'percentage_increase':
				return $base + ( $base * $value / 100 );
			case 'percentage_decrease':
				return $base - ( $base * $value / 100 );
			default: // fixed_price.
				return $value;
		}
	}

	/**
	 * Get all published product categories as id/name pairs.
	 *
	 * @return array<array{id: int, name: string}>
	 * @since 2.0
	 */
	public static function get_product_categories() {

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'orderby'    => 'name',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		return array_values(
			array_map(
				fn( $term ) => array(
					'id'   => $term->term_id,
					'name' => wp_specialchars_decode( $term->name, ENT_QUOTES ),
				),
				$terms
			)
		);
	}

	/**
	 * Return the current user's first role, or 'guest' for unauthenticated visitors.
	 *
	 * @return string
	 * @since 2.0
	 */
	public static function get_current_user_role(): string {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include ABSPATH . 'wp-includes/pluggable.php';
		}
		$current_user = wp_get_current_user();
		return ( isset( $current_user->roles[0] ) && '' !== $current_user->roles[0] ) ? $current_user->roles[0] : 'guest';
	}

	/**
	 * Check whether the current request comes from a known bot/crawler.
	 *
	 * @return bool
	 * @since 2.0
	 */
	public static function is_bot(): bool {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) &&
			(bool) preg_match( '/Google-Structured-Data-Testing-Tool|bot|crawl|slurp|spider/i', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	}

	/**
	 * Return the canonical product ID: parent ID for variations, own ID for all others.
	 *
	 * @param \WC_Product $product Product object.
	 * @return int
	 * @since 2.0
	 */
	public static function get_product_id_or_parent_id( \WC_Product $product ): int {
		return $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	}

	/**
	 * Check whether per-product pricing is enabled for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 * @since 2.0
	 */
	public static function is_per_product_enabled( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_per_product_settings_enabled', true );
	}

	/**
	 * Read all per-product pricing meta for a given product and role.
	 *
	 * @param int    $product_id Product or variation post ID.
	 * @param string $role       Role key.
	 * @return array{adj_type: string, reg_price: string, sale_price: string, empty_price: bool, min_qty: string, max_qty: string}
	 * @since 2.0
	 */
	public static function get_role_price_data( int $product_id, string $role ): array {
		$raw_adj = get_post_meta( $product_id, '_alg_wc_price_by_user_role_adjusment_type_' . $role, true );
		return array(
			'adj_type'    => $raw_adj ? $raw_adj : 'fixed_price',
			'reg_price'   => get_post_meta( $product_id, '_alg_wc_price_by_user_role_regular_price_' . $role, true ),
			'sale_price'  => get_post_meta( $product_id, '_alg_wc_price_by_user_role_sale_price_' . $role, true ),
			'empty_price' => 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_empty_price_' . $role, true ),
			'min_qty'     => get_post_meta( $product_id, '_alg_wc_price_by_user_role_min_qty_' . $role, true ),
			'max_qty'     => get_post_meta( $product_id, '_alg_wc_price_by_user_role_max_qty_' . $role, true ),
		);
	}

	/**
	 * Check whether the User Role Editor plugin is active.
	 *
	 * @return bool
	 * @since 2.0
	 */
	public static function is_user_role_editor_active(): bool {
		return in_array( 'user-role-editor/user-role-editor.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true );
	}

	/**
	 * Return the display name for a given role key.
	 *
	 * @param string $role Role key.
	 * @return string
	 * @since 2.0
	 */
	public static function get_role_display_name( string $role ): string {
		return 'guest' === $role
			? __( 'Guest', 'price-by-user-role-for-woocommerce' )
			: ( wp_roles()->get_names()[ $role ] ?? $role );
	}

	/**
	 * Check whether a product belongs to any of the given categories.
	 *
	 * @param int   $product_id   Product ID.
	 * @param array $category_ids List of category term IDs.
	 * @return bool
	 * @since 2.0
	 */
	public static function product_belongs_to_categories( int $product_id, array $category_ids ): bool {
		if ( empty( $category_ids ) ) {
			return false;
		}
		$product_cats = wc_get_product_term_ids( $product_id, 'product_cat' );
		return ! empty( array_intersect( array_map( 'intval', $category_ids ), $product_cats ) );
	}
}
