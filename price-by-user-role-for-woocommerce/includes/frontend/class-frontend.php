<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Frontend price-adjustment class. Handles all WooCommerce price filters,
 * cart recalculation, mini-cart display, price-filter widget, messaging,
 * and third-party plugin integrations.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Frontend
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

use Tyche\PBUR\Helpers\Utils;
use Tyche\PBUR\Helpers\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend Class.
 *
 * @since 2.0
 */
class Frontend {

	/**
	 * Option key for rules (v2 format).
	 *
	 * @var string
	 */
	const RULES_OPTION = 'pbur_rules';

	/**
	 * Request-level rules cache.
	 *
	 * @var array|null
	 */
	private ?array $rules = null;

	/**
	 * Return rules, fetching from DB only once per request.
	 *
	 * @return array
	 * @since 2.0
	 */
	private function get_rules(): array {
		if ( null === $this->rules ) {
			$this->rules = get_option( self::RULES_OPTION, array() );
		}
		return $this->rules;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'load_hooks' ) );
	}

	/**
	 * Register hooks after WooCommerce and all plugins are ready.
	 *
	 * Formerly `add_hooks()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function load_hooks() {
		if ( ! Settings::get( 'pluginEnabled' ) ) {
			return;
		}

		if ( Settings::get( 'disableForBots' ) && Utils::is_bot() ) {
			return;
		}

		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$this->register_price_hooks();
		}

		// WooCommerce Product Table plugin integration (runs in both admin and frontend).
		add_filter( 'wc_product_table_data_price', array( $this, 'prices_in_product_table' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/**
	 * Register all WooCommerce price and cart hooks.
	 *
	 * @return void
	 * @since 2.0
	 */
	private function register_price_hooks() {
		$price_hooks = array(
			'woocommerce_product_get_price',
			'woocommerce_product_get_sale_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_variation_prices_price',
			'woocommerce_variation_prices_regular_price',
			'woocommerce_variation_prices_sale_price',
			'woocommerce_product_variation_get_price',
			'woocommerce_product_variation_get_regular_price',
			'woocommerce_product_variation_get_sale_price',
		);

		// Flexible Quantity Free modifies prices before WooCommerce resolves them;
		// running after it (9999) ensures we see the correct base price.
		$priority = class_exists( '\WPDesk\FlexibleQuantityFree\Plugin' ) ? 9999 : 10;

		foreach ( $price_hooks as $hook ) {
			add_filter( $hook, array( $this, 'change_price_by_role' ), $priority, 2 );
		}

		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'get_variation_prices_hash' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_package_rates', array( $this, 'change_price_by_role_shipping' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_get_price_including_tax', array( $this, 'change_price_by_role_grouped' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'change_price_by_role_grouped' ), PHP_INT_MAX, 3 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 20, 3 );
		add_action( 'loop_shop_post_in', array( $this, 'filter_products_by_price' ), 10, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20, 1 );
		add_filter( 'woocommerce_price_filter_widget_min_amount', array( $this, 'get_min_price' ), 10, 1 );
		add_filter( 'woocommerce_price_filter_widget_max_amount', array( $this, 'get_max_price' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 1 );
		add_filter( 'posts_join', array( $this, 'catalog_orderby_join' ), 10, 2 );

		add_filter( 'wc_epo_add_cart_item_original_price', array( $this, 'tm_epo_price' ), 11, 2 );
		add_filter( 'alg_wc_pbur_update_product_price_wcdpd', array( $this, 'wcdpd_update_product_price' ), 12, 1 );

		$theme = wp_get_theme();
		if ( 'Flatsome' === $theme->name || 'Flatsome' === $theme->parent_theme ) {
			add_filter( 'woocommerce_widget_cart_item_quantity', array( $this, 'mini_cart_price' ), 10, 3 );
		} else {
			add_filter( 'woocommerce_before_mini_cart', array( $this, 'force_cart_calculation' ), 10, 3 );
		}


	}

	// -------------------------------------------------------------------------
	// Core price-adjustment logic
	// -------------------------------------------------------------------------

	/**
	 * Main price filter — applies rule-based or per-product pricing for the current user role.
	 *
	 * Priority order:
	 *   1. Product-specific rules (from pbur_rules option).
	 *   2. Category / all-products rules.
	 *   3. Per-product meta (_alg_wc_price_by_user_role_*).
	 *   4. Global multiplier.
	 *
	 * @param mixed            $price    Current price.
	 * @param \WC_Product|null $_product Product object.
	 * @return mixed
	 * @since 2.0
	 */
	public function change_price_by_role( $price, $_product ) {
		if ( ! $_product instanceof \WC_Product ) {
			return $price;
		}

		$current_user_role   = Utils::get_current_user_role();
		$rules               = $this->get_rules();
		$product_id          = Utils::get_product_id_or_parent_id( $_product );
		$per_product_enabled = Settings::get( 'perProductEnabled' ) && Utils::is_per_product_enabled( $product_id );
		$cart_qty            = $this->get_cart_qty( $product_id );

		// Pass 1: product-specific rules.
		foreach ( $rules as $rule ) {
			if ( $per_product_enabled && empty( $rule['override_product_level'] ) ) {
				continue;
			}
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$rule_products = array_map( 'intval', $rule['products'] ?? array() );
			if ( ! in_array( (int) $product_id, $rule_products, true ) ) {
				continue;
			}
			$role_data = $rule['role_pricing'][ $current_user_role ] ?? null;
			if ( ! $role_data ) {
				continue;
			}

			return $this->apply_role_pricing( $role_data, $price, $cart_qty );
		}

		// Pass 2: category / all-products rules.
		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if ( $per_product_enabled && empty( $rule['override_product_level'] ) ) {
				continue;
			}

			$product_match = false;

			if ( ! empty( $rule['apply_to_all'] ) ) {
				$product_match = true;
			} elseif ( ! empty( $rule['categories'] ) ) {
				if ( Utils::product_belongs_to_categories( $product_id, $rule['categories'] ) ) {
					$product_match = true;
				}
			}

			if ( ! $product_match ) {
				continue;
			}

			$role_data = $rule['role_pricing'][ $current_user_role ] ?? null;
			if ( ! $role_data ) {
				continue;
			}

			if ( ! empty( $role_data['empty_price'] ) ) {
				return '';
			}

			return $this->apply_role_pricing( $role_data, $price, $cart_qty );
		}

		// Theme-specific guard: don't alter price on cart/checkout for some themes.
		$theme = wp_get_theme();
		if ( 'the7dtchild' !== $theme->name && ( is_checkout() || is_page( 'cart' ) || is_cart() ) ) {
			return $price;
		}

		// Pass 3: per-product meta.
		if ( $per_product_enabled ) {
			$_product_id = $_product->get_id();

			// For variations $product_id is the parent; for simple/variable it equals $_product_id.
			// Use the parent ID for category exclusion so exclusions honour the product's categories,
			// not the variation (which has none).
			$meta_parent_id = ( $_product_id !== $product_id ) ? $product_id : $_product_id;

			if ( $this->is_excluded_from_category( $meta_parent_id ) ) {
				return $price;
			}


			// Resolve the meta source: prefer the variation's own meta; fall back to the parent
			// when neither empty_price nor regular_price is set on the variation.
			// This ensures v1-migrated data (stored on the parent product) still works.
			$meta_id = $_product_id;
			if ( $meta_parent_id !== $_product_id ) {
				$v_empty = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true );
				$v_price = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
				if ( '' === $v_empty && '' === $v_price ) {
					$meta_id = $meta_parent_id;
				}
			}

			if ( 'yes' === get_post_meta( $meta_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
				return '';
			}

			$regular_price = get_post_meta( $meta_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );

			// User Role Editor plugin: intersect roles to find lowest price.
			if ( Utils::is_user_role_editor_active() ) {
				$regular_price  = $this->get_ure_price( $meta_id, 'regular' );
				$sale_price_ure = $this->get_ure_price( $meta_id, 'sale' );
			}

			if ( '' !== $regular_price ) {
				$adjustment_type = 'fixed_price';
				$sale_price      = isset( $sale_price_ure ) ? $sale_price_ure : get_post_meta( $meta_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
				$current_filter  = current_filter();

				return $this->apply_per_product_price_in_filter( $price, $regular_price, $sale_price, $adjustment_type, $current_filter, $_product );
			}
		}

		// Pass 4: global multiplier.
		if ( Settings::get( 'multipliersEnabled' ) ) {
			$_product_id = Utils::get_product_id_or_parent_id( $_product );
			if ( ! $this->is_excluded_from_category( $_product_id ) ) {
				if ( Settings::is_empty_price( $current_user_role ) ) {
					return '';
				}
				$koef = Settings::get_multiplier( $current_user_role );
				if ( 1.0 !== $koef ) {
					return '' === $price ? $price : $price * $koef;
				}
			}
		}

		return $price;
	}

	/**
	 * Apply a rule's role_pricing entry for the given cart quantity.
	 *
	 * Respects qty-range guards: if qty rules are configured and the current
	 * cart qty is outside the range, the original price is returned.
	 *
	 * @param array $role_data Role pricing data from pbur_rules.
	 * @param mixed $price     Current price.
	 * @param int   $cart_qty  Cart quantity for this product.
	 * @return mixed
	 * @since 2.0
	 */
	private function apply_role_pricing( array $role_data, $price, int $cart_qty ) {
		if ( ! empty( $role_data['empty_price'] ) ) {
			return '';
		}

		$min_qty = absint( $role_data['min_qty'] ?? 0 );
		$max_qty = absint( $role_data['max_qty'] ?? 0 );

		if ( $min_qty || $max_qty ) {
			if ( $min_qty && $cart_qty < $min_qty ) {
				return $price;
			}
			if ( $max_qty && $cart_qty > $max_qty ) {
				return $price;
			}
		}

		$regular_price   = $role_data['regular_price'] ?? '';
		$sale_price      = $role_data['sale_price'] ?? '';
		$adjustment_type = $role_data['adjustment_type'] ?? 'fixed_price';

		return $this->apply_price_in_filter_context( $price, $regular_price, $sale_price, $adjustment_type );
	}

	/**
	 * Return the adjusted price based on the current WooCommerce filter (regular, sale, or effective).
	 *
	 * @param mixed  $price           Current price.
	 * @param string $regular_price   Configured regular price.
	 * @param string $sale_price      Configured sale price.
	 * @param string $adjustment_type Adjustment type key.
	 * @return mixed
	 * @since 2.0
	 */
	private function apply_price_in_filter_context( $price, string $regular_price, string $sale_price, string $adjustment_type ) {
		$filter          = current_filter();
		$regular_filters = array(
			'woocommerce_get_regular_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_variation_prices_regular_price',
			'woocommerce_product_variation_get_regular_price',
		);
		$sale_filters    = array(
			'woocommerce_get_sale_price',
			'woocommerce_product_get_sale_price',
			'woocommerce_variation_prices_sale_price',
			'woocommerce_product_variation_get_sale_price',
		);

		if ( in_array( $filter, $regular_filters, true ) ) {
			return '' !== $regular_price
				? Utils::apply_price_adjustment( (float) $price, $regular_price, $adjustment_type )
				: $price;
		}

		if ( in_array( $filter, $sale_filters, true ) ) {
			if ( '' !== $sale_price ) {
				return Utils::apply_price_adjustment( (float) $price, $sale_price, $adjustment_type );
			}
			if ( '' !== $regular_price ) {
				return '' !== $price ? $price : '';
			}
			return $price;
		}

		// get_price filters — return effective price (sale takes precedence).
		if ( '' !== $sale_price ) {
			return Utils::apply_price_adjustment( (float) $price, $sale_price, $adjustment_type );
		}
		if ( '' !== $regular_price ) {
			return Utils::apply_price_adjustment( (float) $price, $regular_price, $adjustment_type );
		}

		return $price;
	}

	/**
	 * Apply per-product meta pricing respecting the current WooCommerce filter and multipliers.
	 *
	 * @param mixed       $price           Original price.
	 * @param string      $regular_price   Per-product regular price meta value.
	 * @param string      $sale_price      Per-product sale price meta value.
	 * @param string      $adjustment_type Adjustment type from meta.
	 * @param string      $current_filter  Current filter hook name.
	 * @param \WC_Product $_product        Product object.
	 * @return mixed
	 * @since 2.0
	 */
	private function apply_per_product_price_in_filter( $price, $regular_price, $sale_price, $adjustment_type, $current_filter, $_product ) {
		$current_user_role = Utils::get_current_user_role();
		$tax_filters       = array( 'woocommerce_get_price_including_tax', 'woocommerce_get_price_excluding_tax' );
		$regular_filters   = array( 'woocommerce_get_regular_price', 'woocommerce_variation_prices_regular_price', 'woocommerce_product_get_regular_price', 'woocommerce_product_variation_get_regular_price' );
		$price_filters     = array( 'woocommerce_get_price', 'woocommerce_variation_prices_price', 'woocommerce_product_get_price', 'woocommerce_product_variation_get_price' );
		$sale_filters      = array( 'woocommerce_get_sale_price', 'woocommerce_variation_prices_sale_price', 'woocommerce_product_get_sale_price', 'woocommerce_product_variation_get_sale_price' );

		if ( in_array( $current_filter, $tax_filters, true ) ) {
			return wc_get_price_to_display( $_product );
		}

		if ( in_array( $current_filter, $price_filters, true ) ) {
			if ( 'fixed_price' !== $adjustment_type ) {
				if ( '' !== $sale_price ) {
					$sale_price = Utils::apply_price_adjustment( (float) $price, $sale_price, $adjustment_type );
				}
				if ( '' !== $regular_price ) {
					$regular_price = Utils::apply_price_adjustment( (float) $price, $regular_price, $adjustment_type );
				}
			}
			$effective = ( '' !== $sale_price ) ? $sale_price : $regular_price;
			if ( Settings::get( 'multipliersEnabled' ) ) {
				if ( Settings::is_empty_price( $current_user_role ) ) {
					return '';
				}
				$koef = Settings::get_multiplier( $current_user_role );
				if ( 1.0 !== $koef ) {
					return '' === $effective ? $effective : $effective * $koef;
				}
			}
			return $effective;
		}

		if ( in_array( $current_filter, $regular_filters, true ) ) {
			if ( 'fixed_price' !== $adjustment_type ) {
				$regular_price = Utils::apply_price_adjustment( (float) $price, $regular_price, $adjustment_type );
			}
			if ( Settings::get( 'multipliersEnabled' ) ) {
				$koef = Settings::get_multiplier( $current_user_role );
				if ( 1.0 !== $koef ) {
					return '' === $regular_price ? $regular_price : $regular_price * $koef;
				}
			}
			return $regular_price;
		}

		if ( in_array( $current_filter, $sale_filters, true ) ) {
			return '' !== $sale_price ? $sale_price : '';
		}

		return $price;
	}

	/**
	 * Busts WooCommerce variation price cache per user role.
	 *
	 * @param array       $price_hash Hash array.
	 * @param \WC_Product $_product   Product object.
	 * @param bool        $_display   Display flag.
	 * @return array
	 * @since 2.0
	 */
	public function get_variation_prices_hash( $price_hash, $_product, $_display ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WooCommerce filter signature.
		$user_role                   = Utils::get_current_user_role();
		$price_hash['alg_user_role'] = array(
			$user_role,
			Settings::get_multiplier( $user_role ),
			Settings::is_empty_price( $user_role ),
			Settings::get( 'perProductEnabled' ),
			Settings::get( 'multipliersEnabled' ),
			get_option( 'pbur_rules_version', 0 ),
		);
		return $price_hash;
	}

	// -------------------------------------------------------------------------
	// Shipping
	// -------------------------------------------------------------------------

	/**
	 * Apply role multiplier to shipping rates.
	 *
	 * @param array $package_rates Shipping rates.
	 * @param array $_package      Shipping package.
	 * @return array
	 * @since 2.0
	 */
	public function change_price_by_role_shipping( $package_rates, $_package ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WooCommerce filter signature.
		if ( ! Settings::get( 'multipliersShipping' ) ) {
			return $package_rates;
		}

		$current_user_role      = Utils::get_current_user_role();
		$koef                   = Settings::get_multiplier( $current_user_role );
		$modified_package_rates = array();

		foreach ( $package_rates as $id => $package_rate ) {
			if ( 1 !== $koef && isset( $package_rate->cost ) ) {
				$package_rate->cost = $package_rate->cost * $koef;
				if ( isset( $package_rate->taxes ) && ! empty( $package_rate->taxes ) ) {
					foreach ( $package_rate->taxes as $tax_id => $tax ) {
						$package_rate->taxes[ $tax_id ] = $package_rate->taxes[ $tax_id ] * $koef;
					}
				}
			}
			$modified_package_rates[ $id ] = $package_rate;
		}

		return $modified_package_rates;
	}

	// -------------------------------------------------------------------------
	// Grouped products
	// -------------------------------------------------------------------------

	/**
	 * Adjust price for grouped product children.
	 *
	 * @param mixed       $price    Price.
	 * @param mixed       $qty      Quantity.
	 * @param \WC_Product $_product Product object.
	 * @return mixed
	 * @since 2.0
	 */
	public function change_price_by_role_grouped( $price, $qty, $_product ) {
		if ( ! $_product->is_type( 'grouped' ) ) {
			return $price;
		}

		if ( Settings::get( 'perProductEnabled' ) ) {
			foreach ( $_product->get_children() as $child_id ) {
				$the_product = wc_get_product( $child_id );
				if ( ! $the_product instanceof \WC_Product ) {
					continue;
				}
				$the_price = get_post_meta( $child_id, '_price', true );
				if ( empty( $the_price ) || ! is_numeric( $the_price ) ) {
					continue;
				}
				$the_price = wc_get_price_to_display( $the_product, array( 'price' => (float) $the_price ) );
				if ( $the_price === $price ) {
					return $this->change_price_by_role( $price, $the_product );
				}
			}
		} elseif ( Settings::get( 'multipliersEnabled' ) ) {
			$current_user_role = Utils::get_current_user_role();
			if ( Settings::is_empty_price( $current_user_role ) ) {
				return '';
			}
			$koef = Settings::get_multiplier( $current_user_role );
			return 1.0 !== $koef ? (float) $price * $koef : $price;
		}

		return $price;
	}

	// -------------------------------------------------------------------------
	// Cart
	// -------------------------------------------------------------------------

	/**
	 * Store the role-based price in cart item data so it survives page loads.
	 *
	 * Formerly `alg_wc_pbur_add_cart_item_data()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param array  $cart_item_data Cart item data.
	 * @param string $product_id     Product ID.
	 * @param string $variation_id   Variation ID.
	 * @return array
	 * @since 2.0
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$_product          = wc_get_product( $product_id );
		$current_user_role = Utils::get_current_user_role();

		if ( ! Settings::get( 'perProductEnabled' ) ) {
			return $cart_item_data;
		}
		if ( ! Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $_product ) ) ) {
			return $cart_item_data;
		}

		$_product_id = $_product->get_id();
		if ( 0 < $variation_id ) {
			$_product_id = $variation_id;
		}

		if ( 'yes' === get_post_meta( $_product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
			$cart_item_data['pur_price'] = '';
			return $cart_item_data;
		}

		$regular_price = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );

		if ( Utils::is_user_role_editor_active() ) {
			$regular_price = $this->get_ure_price( $_product_id, 'regular' );
			$sale_price    = $this->get_ure_price( $_product_id, 'sale' );
		}

		if ( '' !== $regular_price ) {
			$sale_price = isset( $sale_price ) ? $sale_price : get_post_meta( $_product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
			$new_price  = '' !== $sale_price ? $sale_price : $regular_price;

			$cart_item_data['pur_price'] = $new_price;
		}

		return $cart_item_data;
	}

	/**
	 * Recalculate cart item prices using the stored role price or current product price.
	 *
	 * Formerly `alg_wc_pbur_before_calculate_totals()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param \WC_Cart $cart_obj Cart object.
	 * @return void
	 * @since 2.0
	 */
	public function before_calculate_totals( $cart_obj ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() ) {
			if ( did_action( 'woocommerce_before_calculate_totals' ) >= 1 ) {
				return;
			}
		}

		foreach ( $cart_obj->get_cart() as $key => $value ) {
			$product_id        = $value['product_id'];
			$_product          = wc_get_product( $product_id );
			$current_user_role = Utils::get_current_user_role();
			$new_price         = '';

			if ( $this->is_rule_based_price_applied( $product_id ) ) {
				continue;
			}

			if ( class_exists( 'WC_Memberships' ) && ! $_product->is_on_sale() ) {
				$old_price = $_product->get_regular_price();
			} else {
				$old_price = $_product->get_price();
			}

			if ( $this->is_excluded_from_category( $product_id ) ) {
				continue;
			}

			if ( Settings::get( 'perProductEnabled' ) ) {
				if ( Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $_product ) ) ) {
					$_product_id  = $_product->get_id();
					$variation_id = $value['variation_id'];
					if ( 0 < $variation_id ) {
						$_product_id = $variation_id;
					}

					if ( 'yes' === get_post_meta( $_product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
						$new_price = '';
					}

					$adjustment_types = 'fixed_price';
					$regular_price    = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );

					if ( Utils::is_user_role_editor_active() ) {
						$regular_price = $this->get_ure_price( $_product_id, 'regular' );
						$sale_price    = $this->get_ure_price( $_product_id, 'sale' );
					}

					if ( '' !== $regular_price ) {
						$new_price  = $regular_price;
						$sale_price = isset( $sale_price ) ? $sale_price : get_post_meta( $_product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );

						if ( $adjustment_types ) {
							$new_price = Utils::apply_price_adjustment( (float) $old_price, $new_price, $adjustment_types );
							if ( Settings::get( 'multipliersEnabled' ) ) {
								if ( Settings::is_empty_price( $current_user_role ) ) {
									return;
								}
								$koef = Settings::get_multiplier( $current_user_role );
								if ( 1.0 !== $koef && ! empty( $new_price ) ) {
									$new_price = $new_price * $koef;
								}
							}
						}
						if ( '' !== $sale_price ) {
							if ( $adjustment_types ) {
								$new_price = Utils::apply_price_adjustment( (float) $old_price, $sale_price, $adjustment_types );
								if ( Settings::get( 'multipliersEnabled' ) ) {
									$koef = Settings::get_multiplier( $current_user_role );
									if ( 1.0 !== $koef && ! empty( $new_price ) ) {
										$new_price = $new_price * $koef;
									}
								}
							} else {
								$new_price = $sale_price;
							}
						}
					} else {
						$new_price = get_post_meta( $_product_id, '_price', true );
						if ( 'bundle' === $_product->get_type() ) {
							$new_price = '';
						}
						if ( Settings::get( 'multipliersEnabled' ) ) {
							if ( Settings::is_empty_price( $current_user_role ) ) {
								return;
							}
							$koef = Settings::get_multiplier( $current_user_role );
							if ( 1.0 !== $koef && ! empty( $new_price ) ) {
								$new_price = $new_price * $koef;
							}
						}
					}
				} else {
					$variation_id = $value['variation_id'];
					$new_price    = 0 < $variation_id
						? get_post_meta( $variation_id, '_price', true )
						: $old_price;

					if ( Settings::get( 'multipliersEnabled' ) ) {
						if ( Settings::is_empty_price( $current_user_role ) ) {
							return;
						}
						$koef = Settings::get_multiplier( $current_user_role );
						if ( 1.0 !== $koef && ! empty( $new_price ) ) {
							$new_price = $new_price * $koef;
						}
					}
				}
			}

			// Avada theme compatibility.
			$theme = wp_get_theme();
			if ( 'Avada Child' === $theme->name || 'Avada' === $theme->parent_theme ) {
				$avada_price = null;
				if ( Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $_product ) ) ) {
					$avada_price = get_post_meta( $value['variation_id'], '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
					if ( empty( $avada_price ) ) {
						$avada_price = get_post_meta( $product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
					}
				}
				if ( empty( $avada_price ) ) {
					$multiplier = Settings::get_multiplier( $current_user_role );
					$new_price  = $old_price * $multiplier;
				} else {
					$new_price = $avada_price;
					if ( Settings::get( 'multipliersEnabled' ) ) {
						$koef = Settings::get_multiplier( $current_user_role );
						if ( 1.0 !== $koef ) {
							$new_price = $new_price * $koef;
						}
					}
				}
			}

			// PW Gift Cards compatibility.
			if ( in_array( 'pw-gift-cards/pw-gift-cards.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) && isset( $value['pw_gift_cards_custom_amount'] ) ) {
				$new_price = $value['pw_gift_cards_custom_amount'];
			}

			// Ensure new_price has a value; fall back to old price + multiplier.
			if ( '' === $new_price || null === $new_price ) {
				$new_price = $old_price;
				if ( Settings::get( 'multipliersEnabled' ) ) {
					if ( Settings::is_empty_price( $current_user_role ) ) {
						return;
					}
					$koef = Settings::get_multiplier( $current_user_role );
					if ( 1.0 !== $koef ) {
						$new_price = $new_price * $koef;
					}
				}
			}

			$price = $new_price;

			// Respect stored pur_price and addon prices.
			if ( isset( $value['pur_price'] ) ) {
				$price = $value['pur_price'] !== $new_price ? $new_price : $value['pur_price'];
				$price = $this->add_product_addon_prices( $price, $value );
			} else {
				$price = $this->add_product_addon_prices( $price, $value, false );
			}


			// Flexible Quantity – Measurement Price Calculator compatibility: scale price by measurement quantity.
			if ( class_exists( '\WPDesk\FlexibleQuantityFree\Plugin' ) ) {
				if ( isset( $value['pricing_item_meta_data']['_measurement_needed'] ) ) {
					$measurement_qty = (float) $value['pricing_item_meta_data']['_measurement_needed'];
					if ( $measurement_qty > 0 ) {
						$price = $price * $measurement_qty;
					}
				}
			}

			$value['data']->set_price( $price );
			$value['data']->set_regular_price( $price );
		}
	}

	/**
	 * Check whether at least one active rule applies to the given product.
	 *
	 * Used to skip the per-product / multiplier fallback when a rule already
	 * governs pricing.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 * @since 2.0
	 */
	private function is_rule_based_price_applied( int $product_id ): bool {
		$rules               = $this->get_rules();
		$per_product_enabled = Settings::get( 'perProductEnabled' ) && Utils::is_per_product_enabled( $product_id );

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return false;
		}

		foreach ( $rules as $rule ) {
			if ( $per_product_enabled && empty( $rule['override_product_level'] ) ) {
				continue;
			}
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if ( ! empty( $rule['apply_to_all'] ) ) {
				return true;
			}
			$rule_products = array_map( 'intval', $rule['products'] ?? array() );
			if ( ! empty( $rule_products ) && in_array( (int) $product_id, $rule_products, true ) ) {
				return true;
			}
			if ( Utils::product_belongs_to_categories( $product_id, $rule['categories'] ?? array() ) ) {
				return true;
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Price filter widget
	// -------------------------------------------------------------------------

	/**
	 * Filter product list by role-adjusted price range.
	 *
	 * Formerly `alg_wc_pbur_products_by_price_filter()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param array $product_ids Product IDs.
	 * @return array
	 * @since 2.0
	 */
	public function filter_products_by_price( $product_ids ) {
		if ( ! is_main_query() || ! isset( $_GET['min_price'] ) || ! isset( $_GET['max_price'] ) || apply_filters( 'alg_wc_pbur_products_by_price_filter', false ) ) { // phpcs:ignore
			return $product_ids;
		}

		$min_price = ! empty( $_GET['min_price'] ) ? sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$max_price = ! empty( $_GET['max_price'] ) ? sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		$new_ids = array();
		foreach ( wc_get_products(
			array(
				'return' => 'ids',
				'limit'  => -1,
			)
		) as $product_id ) {
			$product   = wc_get_product( $product_id );
			$price     = $product->get_price();
			$price_new = $this->change_price_by_role( $price, $product );
			if ( $price_new >= $min_price && $price_new <= $max_price ) {
				$new_ids[] = $product_id;
			}
		}

		$product_ids = $new_ids;

		add_filter(
			'posts_clauses',
			function ( $clauses ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$_GET['__min_price'] = sanitize_text_field( wp_unslash( $_GET['min_price'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					$_GET['__max_price'] = sanitize_text_field( wp_unslash( $_GET['max_price'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
				unset( $_GET['min_price'], $_GET['max_price'] ); // phpcs:ignore WordPress.Security.NonceVerification
				return $clauses;
			},
			5
		);

		add_filter(
			'posts_clauses',
			function ( $clauses ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( isset( $_GET['__min_price'] ) || isset( $_GET['__max_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$_GET['min_price'] = sanitize_text_field( wp_unslash( $_GET['__min_price'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					$_GET['max_price'] = sanitize_text_field( wp_unslash( $_GET['__max_price'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
				unset( $_GET['__min_price'], $_GET['__max_price'] ); // phpcs:ignore WordPress.Security.NonceVerification
				return $clauses;
			},
			55
		);

		return $product_ids;
	}

	/**
	 * Override the price-filter widget minimum with the role-adjusted price.
	 *
	 * Formerly `alg_wc_pbur_min_price()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param int $min_price Current minimum.
	 * @return float
	 * @since 2.0
	 */
	public function get_min_price( $min_price ) {
		if ( ! apply_filters( 'alg_wc_pbur_min_price', true ) ) {
			return $min_price;
		}
		$bound = $this->get_price_bound( 'min' );
		return 0.0 !== $bound ? $bound : $min_price;
	}

	/**
	 * Override the price-filter widget maximum with the role-adjusted price.
	 *
	 * Formerly `alg_wc_pbur_max_price()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param int $max_price Current maximum.
	 * @return float
	 * @since 2.0
	 */
	public function get_max_price( $max_price ) {
		if ( ! apply_filters( 'alg_wc_pbur_max_price', true ) ) {
			return $max_price;
		}
		$bound = $this->get_price_bound( 'max' );
		return 0.0 !== $bound ? $bound : $max_price;
	}

	/**
	 * Compute the floor (min) or ceiling (max) role-adjusted price across all products.
	 *
	 * @param string $type 'min' or 'max'.
	 * @return float 0.0 when no prices are found.
	 * @since 2.0
	 */
	private function get_price_bound( string $type ): float {
		$prices = array();
		foreach ( wc_get_products(
			array(
				'return' => 'ids',
				'limit'  => -1,
			)
		) as $product_id ) {
			$product = wc_get_product( $product_id );
			$price   = $product->is_type( 'variable' )
				? $product->get_variation_price( 'max' === $type ? 'max' : 'min' )
				: $product->get_price();
			if ( '' !== $price ) {
				$prices[] = $price;
			}
		}
		if ( empty( $prices ) ) {
			return 0.0;
		}
		$steps = max( (int) apply_filters( 'woocommerce_price_filter_widget_step', 10 ), 1 );
		return 'max' === $type
			? ceil( max( $prices ) / $steps ) * $steps
			: floor( min( $prices ) / $steps ) * $steps;
	}

	/**
	 * Remove WC's default price-filter clause when custom min/max GET params are set.
	 *
	 * Formerly `alg_pre_get_posts()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function pre_get_posts() {
		if ( isset( $_GET['max_price'] ) && isset( $_GET['min_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			remove_filter( 'posts_clauses', array( 'WC_Query', 'price_filter_post_clauses' ), 10, 2 );
		}
	}

	/**
	 * Add a JOIN clause to allow ordering by per-role price meta in the catalog.
	 *
	 * Triggered only when WP Grid Builder sends a valid nonce.
	 * Formerly `alg_wc_pbur_woocommerce_catalog_orderby()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param string    $join  SQL JOIN clause.
	 * @param \WP_Query $query WP_Query instance.
	 * @return string
	 * @since 2.0
	 */
	public function catalog_orderby_join( $join, $query ) {
		global $wpdb;

		if ( empty( $_POST['wpgb'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wpgb'] ), 'wpgb' ) ) {
			return $join;
		}

		$current_user_role = Utils::get_current_user_role();
		$meta_key          = '_alg_wc_price_by_user_role_regular_price_' . $current_user_role;

		if ( isset( $query->query['orderby'] ) && in_array( $query->query['orderby'], array( 'price', 'price-desc' ), true ) ) {
			$join .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} as algwcprice ON algwcprice.post_id = {$wpdb->posts}.id AND algwcprice.meta_key = %s ",
				$meta_key
			);
		}

		return $join;
	}

	// -------------------------------------------------------------------------
	// Mini cart
	// -------------------------------------------------------------------------

	/**
	 * Show role-adjusted price in the Flatsome mini-cart widget.
	 *
	 * Formerly `alg_wc_pbur_mini_cart_price()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param string $output       Price HTML.
	 * @param array  $cart_item    Cart item data.
	 * @param string $_cart_item_key Cart item key.
	 * @return string
	 * @since 2.0
	 */
	public function mini_cart_price( $output, $cart_item, $_cart_item_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WooCommerce filter signature.
		$product_id        = $cart_item['product_id'];
		$variation_id      = $cart_item['variation_id'];
		$_product          = wc_get_product( $product_id );
		$current_user_role = Utils::get_current_user_role();
		$reg_price         = get_post_meta( $product_id, '_regular_price', true );
		$sale_price        = get_post_meta( $product_id, '_sale_price', true );
		$old_price         = '' !== $sale_price ? $sale_price : $reg_price;

		if ( $this->is_excluded_from_category( $product_id ) ) {
			return $output;
		}
		if ( ! Settings::get( 'perProductEnabled' ) ) {
			return $output;
		}
		if ( ! Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $_product ) ) ) {
			return $output;
		}

		$_product_id = $_product->get_id();
		if ( 0 < $variation_id ) {
			$_product_id = $variation_id;
		}

		if ( 'yes' === get_post_meta( $_product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
			return $output;
		}

		$regular_price = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
		if ( '' === $regular_price ) {
			return $output;
		}

		$pur_price       = $regular_price;
		$sale_price_meta = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
		if ( '' !== $sale_price_meta ) {
			$pur_price = $sale_price_meta;
		}


		if ( Settings::get( 'multipliersEnabled' ) ) {
			if ( Settings::is_empty_price( $current_user_role ) ) {
				return $output;
			}
			$koef = Settings::get_multiplier( $current_user_role );
			if ( 1.0 !== $koef && ! empty( $pur_price ) ) {
				$pur_price = $pur_price * $koef;
			}
		}

		$pur_price = $this->add_product_addon_prices( $pur_price, $cart_item );

		return sprintf(
			'<span class="quantity">%s &times; <span class="woocommerce-Price-amount amount">%s <span class="woocommerce-Price-currencySymbol">%s</span></span></span>',
			absint( $cart_item['quantity'] ),
			esc_html( number_format( (float) $pur_price, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) ),
			esc_html( get_woocommerce_currency_symbol() )
		);
	}

	/**
	 * Force cart recalculation before the mini-cart is displayed (non-Flatsome themes).
	 *
	 * @return void
	 * @since 2.0
	 */
	public function force_cart_calculation() {
		if ( is_cart() || is_checkout() || wp_doing_ajax() ) {
			return;
		}
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		WC()->cart->calculate_totals();
	}

	// -------------------------------------------------------------------------
	// Third-party plugin integrations
	// -------------------------------------------------------------------------

	/**
	 * Pass role-based price to TM Extra Product Options plugin.
	 *
	 * Formerly `alg_wc_pbur_tm_epo_price()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param mixed $price     Cart item price.
	 * @param array $cart_item Cart item data.
	 * @return mixed
	 * @since 2.0
	 */
	public function tm_epo_price( $price, $cart_item ) {
		$_product = wc_get_product( $cart_item['product_id'] );

		if ( ! Settings::get( 'perProductEnabled' ) ) {
			return $price;
		}
		if ( ! Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $_product ) ) ) {
			return $price;
		}

		$pbur_price = $this->get_per_product_role_price( $_product->get_id(), Utils::get_current_user_role() );
		return null !== $pbur_price ? $pbur_price : $price;
	}

	/**
	 * Update product price for WC Dynamic Pricing & Discounts (WCDPD) plugin.
	 *
	 * Formerly `alg_wc_pbur_wcdpd_update_product_price()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param \WC_Product $product Product object.
	 * @return \WC_Product
	 * @since 2.0
	 */
	public function wcdpd_update_product_price( $product ) {
		$product_price = (int) $this->get_wcdpd_price( '', $product );
		if ( $product_price > 0 ) {
			$product->set_price( $product_price );
			$product->set_regular_price( $product_price );
		}
		return $product;
	}

	/**
	 * Retrieve the per-product role price for use by the WCDPD plugin.
	 *
	 * Formerly `alg_wc_pbur_wcdpd_price()` (public) in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param mixed       $price   Fallback price.
	 * @param \WC_Product $product Product object.
	 * @return mixed
	 * @since 2.0
	 */
	private function get_wcdpd_price( $price, $product ) {
		if ( ! $product ) {
			return $price;
		}
		if ( ! Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $product ) ) ) {
			return $price;
		}

		$pbur_price = $this->get_per_product_role_price( $product->get_id(), Utils::get_current_user_role() );
		return null !== $pbur_price ? $pbur_price : $price;
	}

	/**
	 * Return the effective per-product role price (sale over regular), or null if not set.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $role       User role key.
	 * @return string|null Effective price string, or null when no per-product price is configured.
	 * @since 2.0
	 */
	private function get_per_product_role_price( int $product_id, string $role ): ?string {
		$data = Utils::get_role_price_data( $product_id, $role );
		if ( '' === $data['reg_price'] ) {
			return null;
		}
		return '' !== $data['sale_price'] ? $data['sale_price'] : $data['reg_price'];
	}

	/**
	 * Show role-adjusted price in WooCommerce Product Table plugin.
	 *
	 * Formerly `pbur_prices_in_woo_product_table()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param string      $price    Price HTML.
	 * @param \WC_Product $_product Product object.
	 * @return string
	 * @since 2.0
	 */
	public function prices_in_product_table( $price, $_product ) {
		if ( ! Settings::get( 'perProductEnabled' ) ) {
			return $price;
		}
		if ( ! Utils::is_per_product_enabled( Utils::get_product_id_or_parent_id( $_product ) ) ) {
			return $price;
		}

		$data = Utils::get_role_price_data( $_product->get_id(), Utils::get_current_user_role() );

		if ( '' === $data['reg_price'] ) {
			return $price;
		}
		if ( $data['empty_price'] ) {
			return '';
		}

		$display_price = $data['reg_price'];
		if ( '' !== $data['sale_price'] && $data['sale_price'] < $data['reg_price'] ) {
			$display_price = $data['sale_price'];
		}

		return wc_price( $display_price );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------


	/**
	 * Sum the current cart quantity for a given product or variation ID.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return int
	 * @since 2.0
	 */
	private function get_cart_qty( int $product_id ): int {
		$cart_qty = 0;
		if ( ! WC()->cart ) {
			return $cart_qty;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if (
				(int) $cart_item['product_id'] === $product_id
				|| ( isset( $cart_item['variation_id'] ) && (int) $cart_item['variation_id'] === $product_id )
			) {
				$cart_qty += (int) $cart_item['quantity'];
			}
		}
		return $cart_qty;
	}

	/**
	 * Return true if the product belongs to a category excluded from price changes.
	 *
	 * Formerly `exclude_products_from_categories()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 * @since 2.0
	 */
	private function is_excluded_from_category( int $product_id ): bool {
		return false;
	}

	/**
	 * Get lowest regular or sale price across User Role Editor plugin's role intersections.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       'regular' or 'sale'.
	 * @return string
	 * @since 2.0
	 */
	private function get_ure_price( int $product_id, string $type ): string {
		$alg_price_roles = Settings::get( 'perProductRoles', array() );
		$current_user    = wp_get_current_user();
		if ( empty( $current_user->roles ) ) {
			$current_user->roles = array( 'guest' );
		}
		$price_roles = ! empty( array_filter( $alg_price_roles ) )
			? array_intersect( $alg_price_roles, $current_user->roles )
			: $current_user->roles;

		$prices = array();
		foreach ( $price_roles as $role ) {
			$meta_key = '_alg_wc_price_by_user_role_' . $type . '_price_' . $role;
			$val      = get_post_meta( $product_id, $meta_key, true );
			if ( '' !== $val ) {
				$prices[] = $val;
			}
		}

		return ! empty( array_filter( $prices ) ) ? (string) min( array_filter( $prices ) ) : '';
	}

	/**
	 * Add WooCommerce Product Addons or Extendons addon prices to a base price.
	 *
	 * @param mixed $price      Base price.
	 * @param array $cart_item  Cart item data.
	 * @param bool  $all_addons Whether to include Extendons options (false on the fallback path).
	 * @return mixed
	 * @since 2.0
	 */
	private function add_product_addon_prices( $price, array $cart_item, bool $all_addons = true ) {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

		if ( in_array( 'woocommerce-product-addons/woocommerce-product-addons.php', $active_plugins, true ) ) {
			foreach ( $cart_item['addons'] ?? array() as $option ) {
				$price += $option['price'];
			}
		}

		if ( $all_addons && in_array( 'extendons_product_addons/extendons-product-addons.php', $active_plugins, true ) ) {
			foreach ( $cart_item['options'] ?? array() as $option ) {
				$price += $option['price'];
			}
		}

		return $price;
	}
}
