<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * General plugin helper functions.
 *
 * @package PBUR
 * @since   2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Function alg_get_product_display_price.
 *
 * @deprecated 2.0 Use wc_get_price_to_display() directly.
 * @param WC_Product $product Product.
 * @param int|string $price   Price.
 * @param int        $qty     Quantity.
 * @version 1.1.0
 * @since   1.1.0
 */
function alg_get_product_display_price( $product, $price = '', $qty = 1 ) {
	_deprecated_function( __FUNCTION__, '2.0', 'wc_get_price_to_display()' );
	return wc_get_price_to_display(
		$product,
		array(
			'price' => (float) $price,
			'qty'   => $qty,
		)
	);
}

/**
 * Function alg_get_product_formatted_variation.
 *
 * @deprecated 2.0 Use wc_get_formatted_variation() directly.
 * @param WC_Product_Variation $variation     Variation.
 * @param bool                 $flat          Whether to format as a flat string.
 * @param bool                 $include_names Whether to include attribute names.
 * @version 1.1.0
 * @since   1.1.0
 */
function alg_get_product_formatted_variation( $variation, $flat = false, $include_names = true ) {
	_deprecated_function( __FUNCTION__, '2.0', 'wc_get_formatted_variation()' );
	return wc_get_formatted_variation( $variation, $flat, $include_names );
}

/**
 * Function alg_get_product_id.
 *
 * @deprecated 2.0 Use WC_Product::get_id() directly.
 * @param WC_Product $product Product.
 * @version 1.1.0
 * @since   1.1.0
 */
function alg_get_product_id( $product ) {
	_deprecated_function( __FUNCTION__, '2.0', 'WC_Product::get_id()' );
	return $product->get_id();
}

/**
 * Function alg_get_product_id_or_variation_parent_id.
 *
 * @deprecated 2.0 Use Tyche\PBUR\Helpers\Utils::get_product_id_or_parent_id().
 * @param WC_Product $product Product.
 * @version 1.1.0
 * @since   1.1.0
 */
function alg_get_product_id_or_variation_parent_id( $product ) {
	_deprecated_function( __FUNCTION__, '2.0', 'Tyche\PBUR\Helpers\Utils::get_product_id_or_parent_id()' );
	return \Tyche\PBUR\Helpers\Utils::get_product_id_or_parent_id( $product );
}

/**
 * Function alg_get_user_roles.
 *
 * @deprecated 2.0 Use Tyche\PBUR\Helpers\Utils::get_user_roles().
 * @version 1.0.0
 * @since   1.0.0
 */
function alg_get_user_roles() {
	_deprecated_function( __FUNCTION__, '2.0', 'Tyche\PBUR\Helpers\Utils::get_user_roles()' );
	return array();
}

/**
 * Function alg_get_user_roles_options.
 *
 * @deprecated 2.0 Use Tyche\PBUR\Helpers\Utils::get_user_roles().
 * @version 1.0.0
 * @since   1.0.0
 */
function alg_get_user_roles_options() {
	_deprecated_function( __FUNCTION__, '2.0', 'Tyche\PBUR\Helpers\Utils::get_user_roles()' );
	return array();
}

/**
 * Function alg_is_bot.
 *
 * @deprecated 2.0 Use Tyche\PBUR\Helpers\Utils::is_bot().
 * @version 1.0.0
 * @since   1.0.0
 */
function alg_is_bot() {
	_deprecated_function( __FUNCTION__, '2.0', 'Tyche\PBUR\Helpers\Utils::is_bot()' );
	return \Tyche\PBUR\Helpers\Utils::is_bot();
}

/**
 * Function alg_get_current_user_first_role.
 *
 * @deprecated 2.0 Use Tyche\PBUR\Helpers\Utils::get_current_user_role().
 * @version 1.0.0
 * @since   1.0.0
 */
function alg_get_current_user_first_role() {
	_deprecated_function( __FUNCTION__, '2.0', 'Tyche\PBUR\Helpers\Utils::get_current_user_role()' );
	return \Tyche\PBUR\Helpers\Utils::get_current_user_role();
}

/**
 * Alg_wc_cpp_get_terms.
 *
 * @deprecated 2.0 Use Tyche\PBUR\Helpers\Utils::get_product_categories() for product categories.
 * @param array|string $args Taxonomy slug or args array.
 * @version 1.0.0
 * @since   1.0.0
 */
function alg_wc_price_by_user_role_get_terms( $args ) {
	_deprecated_function( __FUNCTION__, '2.0', 'Tyche\PBUR\Helpers\Utils::get_product_categories()' );

	if ( ! is_array( $args ) ) {
		$args = array(
			'taxonomy'   => $args,
			'orderby'    => 'name',
			'hide_empty' => false,
		);
	}

	$terms         = get_terms( $args );
	$terms_options = array();
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$terms_options[ $term->term_id ] = $term->name;
		}
	}
	return $terms_options;
}
