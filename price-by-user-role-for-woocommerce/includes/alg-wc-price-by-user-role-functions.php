<?php
/**
 * Price by User Role for WooCommerce - Functions
 *
 * @package PriceByUserRole
 * @version 1.1.0
 * @since   1.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'alg_get_product_display_price' ) ) {
	/**
	 * Alg_get_product_display_price.
	 *
	 * @param Object $_product Product Object.
	 * @param string $price Price (Optional).
	 * @param int    $qty Quantity (Optional).
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function alg_get_product_display_price( $_product, $price = '', $qty = 1 ) {
		if ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ) {
			return $_product->get_display_price( $price, $qty );
		} else {
			return wc_get_price_to_display(
				$_product,
				array(
					'price' => $price,
					'qty'   => $qty,
				)
			);
		}
	}
}

if ( ! function_exists( 'alg_get_product_formatted_variation' ) ) {
	/**
	 * Alg_get_product_formatted_variation.
	 *
	 * @param obj     $variation Variable Object.
	 * @param boolean $flat Flat.
	 * @param boolean $include_names Should it include the name or not.
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function alg_get_product_formatted_variation( $variation, $flat = false, $include_names = true ) {
		if ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ) {
			return $variation->get_formatted_variation_attributes( $flat );
		} else {
			return wc_get_formatted_variation( $variation, $flat, $include_names );
		}
	}
}

if ( ! function_exists( 'alg_get_product_id' ) ) {
	/**
	 * Alg_get_product_id.
	 *
	 * @param obj $_product Object of Product.
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function alg_get_product_id( $_product ) {
		if ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ) {
			return ( isset( $_product->variation_id ) ) ? $_product->variation_id : $_product->id;
		} else {
			return $_product->get_id();
		}
	}
}

if ( ! function_exists( 'alg_get_product_id_or_variation_parent_id' ) ) {
	/**
	 * Alg_get_product_id_or_variation_parent_id.
	 *
	 * @param obj $_product Object of Product.
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function alg_get_product_id_or_variation_parent_id( $_product ) {
		if ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ) {
			return $_product->id;
		} else {
			return ( $_product->is_type( 'variation' ) ) ? $_product->get_parent_id() : $_product->get_id();
		}
	}
}

if ( ! function_exists( 'alg_get_user_roles' ) ) {
	/**
	 * Alg_get_user_roles.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_get_user_roles() {
		global $wp_roles;
		$all_roles = ( isset( $wp_roles ) && is_object( $wp_roles ) ) ? $wp_roles->roles : array();
		// For not adding this filter when WP Bakery plugin is active.
		$plugincheck = 'js_composer/js_composer.php';
		if ( ! in_array( $plugincheck, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) && ! ( is_multisite() && array_key_exists( $plugincheck, get_site_option( 'active_sitewide_plugins', array() ) ) )
		) {
			$all_roles = apply_filters( 'alg_woocommerce_shop_manager_editable_roles', $all_roles );
		}
		$all_roles = array_merge(
			array(
				'guest' => array(
					'name'         => __( 'Guest', 'price-by-user-role-for-woocommerce' ),
					'capabilities' => array(),
				),
			),
			$all_roles
		);
		return $all_roles;
	}
}

if ( ! function_exists( 'alg_get_user_roles_options' ) ) {
	/**
	 * Alg_get_user_roles_options.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_get_user_roles_options() {
		$all_roles         = alg_get_user_roles();
		$all_roles_options = array();
		foreach ( $all_roles as $_role_key => $_role ) {
			if ( ! empty( $_role['name'] ) ) {
				$all_roles_options[ $_role_key ] = $_role['name'];
			}
		}
		return $all_roles_options;
	}
}

if ( ! function_exists( 'alg_is_bot' ) ) {
	/**
	 * Alg_is_bot.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_is_bot() {
		return (
			isset( $_SERVER['HTTP_USER_AGENT'] ) &&
			preg_match( '/Google-Structured-Data-Testing-Tool|bot|crawl|slurp|spider/i', sanitize_title( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
		);
	}
}

if ( ! function_exists( 'alg_get_current_user_first_role' ) ) {
	/**
	 * Alg_get_current_user_first_role.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @todo    (maybe) check all user roles instead of first one?
	 */
	function alg_get_current_user_first_role() {
		$current_user = wp_get_current_user();
		return ( isset( $current_user->roles[0] ) && '' !== $current_user->roles[0] ) ? $current_user->roles[0] : 'guest';
	}
}
