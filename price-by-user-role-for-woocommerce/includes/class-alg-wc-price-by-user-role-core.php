<?php
/**
 * Price by User Role for WooCommerce - Core Class
 *
 * @package PriceByUserRole
 * @version 1.1.0
 * @since   1.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'Alg_WC_Price_By_User_Role_Core' ) ) :

	/**
	 * Alg_WC_Price_By_User_Role_Core Class
	 *
	 * @class   Alg_WC_Price_By_User_Role_Core
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Price_By_User_Role_Core {

		/**
		 * Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function __construct() {
			if ( 'yes' === get_option( 'alg_wc_price_by_user_role_enabled', 'yes' ) ) {
				if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
					if ( 'no' === get_option( 'alg_wc_price_by_user_role_for_bots_disabled', 'no' ) || ! alg_is_bot() ) {
						$this->add_hooks();
					}
				}
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_admin' ) );
				add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'alg_wc_pbur_product_prices_as_user_role_in_order' ), PHP_INT_MAX, 3 );
				add_action( 'woocommerce_order_before_calculate_taxes', array( $this, 'alg_wc_price_by_user_role_update_order' ), PHP_INT_MAX, 2 );
				add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'alg_wc_pbur_order_role_selection_option' ), PHP_INT_MAX );
				add_action( 'wp_ajax_alg_wc_pbur_order_role', array( $this, 'alg_wc_pbur_order_role_callback' ) );
				add_action( 'wp_ajax_alg_wc_pbur_order_detect_role', array( $this, 'alg_wc_pbur_order_detect_role_callback' ) );
				add_action( 'wp_ajax_alg_wc_pbur_checkbox_value', array( $this, 'alg_wc_pbur_checkbox_value_callback' ) );
				add_action( 'save_post', array( $this, 'alg_wc_pbur_update_order_role_options' ), PHP_INT_MAX, 2 );
			}
		}

		/**
		 * Hook add_hooks.
		 *
		 * @version 1.1.0
		 * @since   1.0.0
		 */
		public function add_hooks() {
			$price_hooks = array();
			// Prices.
			if ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ) {
				$price_hooks = array_merge(
					$price_hooks,
					array(
						'woocommerce_get_price',
						'woocommerce_get_sale_price',
						'woocommerce_get_regular_price',
					)
				);
			} else {
				$price_hooks = array_merge(
					$price_hooks,
					array(
						'woocommerce_product_get_price',
						'woocommerce_product_get_sale_price',
						'woocommerce_product_get_regular_price',
					)
				);
			}
			// Variations.
			$price_hooks = array_merge(
				$price_hooks,
				array(
					'woocommerce_variation_prices_price',
					'woocommerce_variation_prices_regular_price',
					'woocommerce_variation_prices_sale_price',
				)
			);
			if ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '>=' ) ) {
				$price_hooks = array_merge(
					$price_hooks,
					array(
						'woocommerce_product_variation_get_price',
						'woocommerce_product_variation_get_regular_price',
						'woocommerce_product_variation_get_sale_price',
					)
				);
			}
			// Hooking...
			foreach ( $price_hooks as $price_hook ) {
				add_filter( $price_hook, array( $this, 'change_price_by_role' ), 10, 2 );
			}
			// Variations Hash.
			add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'get_variation_prices_hash' ), PHP_INT_MAX, 3 );
			// Shipping.
			add_filter( 'woocommerce_package_rates', array( $this, 'change_price_by_role_shipping' ), PHP_INT_MAX, 2 );
			// Grouped products.
			add_filter( 'woocommerce_get_price_including_tax', array( $this, 'change_price_by_role_grouped' ), PHP_INT_MAX, 3 );
			add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'change_price_by_role_grouped' ), PHP_INT_MAX, 3 );
			// Price filter widgets.
			add_filter( 'woocommerce_product_query', array( $this, 'alg_wc_price_by_user_role_products_by_price_filter' ), PHP_INT_MAX, 3 );
			add_filter( 'woocommerce_price_filter_widget_min_amount', array( $this, 'alg_wc_price_by_user_role_min_price' ), PHP_INT_MAX );
			add_filter( 'woocommerce_price_filter_widget_max_amount', array( $this, 'alg_wc_price_by_user_role_max_price' ), PHP_INT_MAX );
		}

		/**
		 * Function to add the ID's of the products to show on the product page as per the price filter widget.
		 *
		 * @param array $query Main Query.
		 */
		public function alg_wc_price_by_user_role_products_by_price_filter( $query ) {
			if ( $query->is_main_query() && isset( $_GET['max_price'] ) && isset( $_GET['min_price'] ) && ! apply_filters( 'alg_wc_price_by_user_role_products_by_price_filter', false ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$product_ids = wc_get_products(
					array(
						'return' => 'ids',
						'limit'  => -1,
					)
				);
				$new_ids     = array();
				foreach ( $product_ids as $product_id ) {
					$product        = wc_get_product( $product_id );
					$product_status = $product->get_status();
					if ( 'publish' === $product_status ) {
						$price = $product->get_price();
						if ( $price >= $_GET['min_price'] && $price <= $_GET['max_price'] ) { // phpcs:ignore WordPress.Security.NonceVerification
							$new_ids[] = $product_id;
						}
					}
				}
				remove_filter( 'posts_clauses', array( WC()->query, 'price_filter_post_clauses' ), 10 );
				$query->set( 'post__in', (array) $new_ids );
			}
		}

		/**
		 * Function to set the Min price in Price filter widgets.
		 *
		 * @param int $min_price Min Price.
		 */
		public function alg_wc_price_by_user_role_min_price( $min_price ) {

			if ( ! apply_filters( 'alg_wc_price_by_user_role_min_price', true ) ) {
				return $min_price;
			}

			$product_ids = wc_get_products(
				array(
					'return' => 'ids',
					'limit'  => -1,
				)
			);
			$min         = array();
			foreach ( $product_ids as $product_id ) {
				$product        = wc_get_product( $product_id );
				$product_status = $product->get_status();
				if ( 'publish' === $product_status ) {
					if ( $product->is_type( 'variable' ) ) {
						$price = $product->get_variation_price();
					} else {
						$price = $product->get_price();
					}
					if ( '' !== $price ) {
						$min[] = $price;
					}
				}
			}

			$min_price = min( $min );
			$steps     = max( apply_filters( 'woocommerce_price_filter_widget_step', 10 ), 1 );

			return ( floor( $min_price / $steps ) * $steps );
		}

		/**
		 * Function to set the Min price in Price filter widgets.
		 *
		 * @param int $max_price Max price.
		 */
		public function alg_wc_price_by_user_role_max_price( $max_price ) {

			if ( ! apply_filters( 'alg_wc_price_by_user_role_max_price', true ) ) {
				return $max_price;
			}

			$product_ids = wc_get_products(
				array(
					'return' => 'ids',
					'limit'  => -1,
				)
			);
			$max         = array();
			foreach ( $product_ids as $product_id ) {
				$product        = wc_get_product( $product_id );
				$product_status = $product->get_status();
				if ( 'publish' === $product_status ) {
					if ( $product->is_type( 'variable' ) ) {
						$price = $product->get_variation_price( 'max' );
					} else {
						$price = $product->get_price();
					}
					if ( '' !== $price ) {
						$max[] = $price;
					}
				}
			}

			$max_price = max( $max );
			$steps     = max( apply_filters( 'woocommerce_price_filter_widget_step', 10 ), 1 );

			return ( ceil( $max_price / $steps ) * $steps );
		}

		/**
		 * Change_price_by_role_shipping.
		 *
		 * @param array  $package_rates Package of Rates.
		 * @param string $package Package.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function change_price_by_role_shipping( $package_rates, $package ) {
			if ( 'yes' === get_option( 'alg_wc_price_by_user_role_shipping_enabled', 'no' ) ) {
				$current_user_role      = alg_get_current_user_first_role();
				$koef                   = get_option( 'alg_wc_price_by_user_role_' . $current_user_role, 1 );
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
			return $package_rates;
		}

		/**
		 * Change_price_by_role_grouped.
		 *
		 * @param string $price Price.
		 * @param int    $qty Quantity.
		 * @param obj    $_product Object of Product.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function change_price_by_role_grouped( $price, $qty, $_product ) {
			if ( $_product->is_type( 'grouped' ) ) {
				if ( 'yes' === get_option( 'alg_wc_price_by_user_role_per_product_enabled', 'yes' ) ) {
					foreach ( $_product->get_children() as $child_id ) {
						$the_price   = get_post_meta( $child_id, '_price', true );
						$the_product = wc_get_product( $child_id );
						$the_price   = alg_get_product_display_price( $the_product, $the_price );
						if ( $the_price === $price ) {
							return $this->change_price_by_role( $price, $the_product );
						}
					}
				} elseif ( 'yes' === get_option( 'alg_wc_price_by_user_role_multipliers_enabled', 'yes' ) ) {
					return $this->change_price_by_role( $price, null );
				}
			}
			return $price;
		}

		/**
		 * Check if HPOS is enabled or not.
		 *
		 * @since 1.8.0
		 * return boolean true if enabled else false
		 */
		public function pbur_wc_hpos_enabled() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Change_price_by_role.
		 *
		 * @param string $price Price.
		 * @param obj    $_product Object of Product.
		 *
		 * @version 1.1.0
		 * @since   1.0.0
		 */
		public function change_price_by_role( $price, $_product ) {

			$current_user_role = alg_get_current_user_first_role();

			// Per product.
			if ( 'yes' === get_option( 'alg_wc_price_by_user_role_per_product_enabled', 'yes' ) ) {
				if ( 'yes' === get_post_meta( alg_get_product_id_or_variation_parent_id( $_product ), '_alg_wc_price_by_user_role_per_product_settings_enabled', true ) ) {
					$_product_id = alg_get_product_id( $_product );
					if ( 'yes' === get_post_meta( $_product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
						return '';
					}
					$regular_price_per_product = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
					if ( '' !== $regular_price_per_product ) {
						$_current_filter = current_filter();
						if ( in_array(
							$_current_filter,
							array(
								'woocommerce_get_price_including_tax',
								'woocommerce_get_price_excluding_tax',
							),
							true
						) ) {
							return alg_get_product_display_price( $_product );
						} elseif ( in_array(
							$_current_filter,
							array(
								'woocommerce_get_price',
								'woocommerce_variation_prices_price',
								'woocommerce_product_get_price',
								'woocommerce_product_variation_get_price',
							),
							true
						) ) {
							$sale_price_per_product = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
							if ( '' === $sale_price_per_product || $sale_price_per_product > $regular_price_per_product ) {
								$sale_price_per_product = $regular_price_per_product;
							}
							if ( 'yes' === get_option( 'alg_wc_price_by_user_role_multipliers_enabled', 'yes' ) ) {
								if ( 'yes' === get_option( 'alg_wc_price_by_user_role_empty_price_' . $current_user_role, 'no' ) ) {
									return '';
								}
								$koef = get_option( 'alg_wc_price_by_user_role_' . $current_user_role, 1 );

								if ( 1 !== ( $koef ) ) {
									return ( '' === $sale_price_per_product ) ? $sale_price_per_product : $sale_price_per_product * (float) $koef;
								}
							}
							return ( '' !== $sale_price_per_product && $sale_price_per_product < $regular_price_per_product ) ?
								$sale_price_per_product : $regular_price_per_product;
						} elseif ( in_array(
							$_current_filter,
							array(
								'woocommerce_get_regular_price',
								'woocommerce_variation_prices_regular_price',
								'woocommerce_product_get_regular_price',
								'woocommerce_product_variation_get_regular_price',
							),
							true
						) ) {
							return $regular_price_per_product;
						} elseif ( in_array(
							$_current_filter,
							array(
								'woocommerce_get_sale_price',
								'woocommerce_variation_prices_sale_price',
								'woocommerce_product_get_sale_price',
								'woocommerce_product_variation_get_sale_price',
							),
							true
						) ) {
							$sale_price_per_product = get_post_meta( $_product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
							return ( '' !== $sale_price_per_product ) ?
								$sale_price_per_product : $price;
						}
					}
				}
			}

			// Global.
			if ( 'yes' === get_option( 'alg_wc_price_by_user_role_multipliers_enabled', 'yes' ) ) {
				if ( 'yes' === get_option( 'alg_wc_price_by_user_role_empty_price_' . $current_user_role, 'no' ) ) {
					return '';
				}
				$koef = get_option( 'alg_wc_price_by_user_role_' . $current_user_role, 1 );
				if ( 1 !== $koef ) {
					return ( '' === $price ) ? $price : $price * $koef;
				}
			}

			// No changes.
			return $price;
		}

		/**
		 * Get_variation_prices_hash.
		 *
		 * @param array  $price_hash Price.
		 * @param obj    $_product Object of Product.
		 * @param string $display Display.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function get_variation_prices_hash( $price_hash, $_product, $display ) {
			$user_role                   = alg_get_current_user_first_role();
			$koef                        = get_option( 'alg_wc_price_by_user_role_' . $user_role, 1 );
			$is_empty                    = get_option( 'alg_wc_price_by_user_role_empty_price_' . $user_role, 'no' );
			$price_hash['alg_user_role'] = array(
				$user_role,
				$koef,
				$is_empty,
				get_option( 'alg_wc_price_by_user_role_per_product_enabled', 'yes' ),
				get_option( 'alg_wc_price_by_user_role_multipliers_enabled', 'yes' ),
			);
			return $price_hash;
		}

		/**
		 * Function enqueue_scripts_admin.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function enqueue_scripts_admin() {
			global $theorder,$post;
			if ( ! is_admin() ) {
				return;
			}
			$screen = get_current_screen();
			$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $this->pbur_wc_hpos_enabled() ) {
				$screen_type = $screen->id;
			} else {
				$screen_type = $screen->post_type;
			}
			if ( 'shop_order' === $screen_type || 'woocommerce_page_wc-orders' === $screen_type && 'new' === $action || 'edit' === $action ) {
				if ( 'woocommerce_page_wc-orders' === $screen_type ) {
					$order_id = $theorder->get_id();
				} else {
					$order_id = $post->ID;
				}
				wp_enqueue_script(
					'alg-wc-price-by-user-role-admin',
					plugins_url() . '/price-by-user-role-for-woocommerce/assets/js/alg-wc-price-by-user-role-admin.js',
					array( 'jquery' ),
					alg_wc_price_by_user_role()->version,
					true
				);
				wp_localize_script(
					'alg-wc-price-by-user-role-admin',
					'pbur_order_id',
					array(
						'order_id'    => $order_id,
						'screen_type' => $screen_type,
					)
				);
				wp_localize_script(
					'alg-wc-price-by-user-role-admin',
					'pbur_order_role',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
					)
				);
				wp_localize_script(
					'alg-wc-price-by-user-role-admin',
					'pbur_nonce_param',
					array(
						'select_user_role_nonce' => wp_create_nonce( 'select-user-role' ),
					)
				);
			} else {
				return;
			}
		}

		/**
		 * Ajax callback function when role is selected.
		 */
		public function alg_wc_pbur_order_role_callback() {
			if ( ! is_admin() ) {
				check_ajax_referer( 'alg_wc_pbur_select_role', 'security' );
			}
			if ( isset( $_POST['order_id'] ) ) {
				$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
			}
			if ( isset( $_POST['pbur_check'] ) ) {
				$pbur_checkbox_selected = sanitize_text_field( wp_unslash( $_POST['pbur_check'] ) );
			}
			if ( isset( $_POST['admin_choice'] ) ) {
				$pbur_order_role_selected = sanitize_text_field( wp_unslash( $_POST['admin_choice'] ) );
			}
			if ( $this->pbur_wc_hpos_enabled() ) {
				$order = wc_get_order( $order_id );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_role', $pbur_order_role_selected );
				$order->save();
			} else {
				update_post_meta( $order_id, 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				update_post_meta( $order_id, 'alg_wc_price_by_user_role_order_role', $pbur_order_role_selected );
			}
		}
		/**
		 * Ajax callback function when role is selected.
		 */
		public function alg_wc_pbur_order_detect_role_callback() {
			if ( ! is_admin() ) {
				check_ajax_referer( 'alg_wc_pbur_select_role', 'security' );
			}
			if ( isset( $_POST['order_id'] ) ) {
				$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
			}
			if ( isset( $_POST['pbur_check'] ) ) {
				$pbur_checkbox_selected = sanitize_text_field( wp_unslash( $_POST['pbur_check'] ) );
			}
			if ( isset( $_POST['user_id'] ) ) {
				$pbur_detected_user_id    = sanitize_text_field( wp_unslash( $_POST['user_id'] ) );
				$pbur_detected_user_role  = get_userdata( $pbur_detected_user_id );
				$pbur_detected_user_role  = $pbur_detected_user_role->roles;
				$pbur_order_role_selected = $pbur_detected_user_role[0];
			}
			if ( $this->pbur_wc_hpos_enabled() ) {
				$order = wc_get_order( $order_id );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_role', $pbur_order_role_selected );
				$order->save();
			} else {
				update_post_meta( $order_id, 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				update_post_meta( $order_id, 'alg_wc_price_by_user_role_order_role', $pbur_order_role_selected );
			}
			echo esc_attr( $pbur_order_role_selected );
		}

		/**
		 * Ajax callback function when role is selected.
		 */
		public function alg_wc_pbur_checkbox_value_callback() {
			if ( ! is_admin() ) {
				check_ajax_referer( 'alg_wc_pbur_select_role', 'security' );
			}
			if ( isset( $_POST['order_id'] ) ) {
				$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
			}
			if ( isset( $_POST['pbur_check'] ) ) {
				$pbur_checkbox_selected = sanitize_text_field( wp_unslash( $_POST['pbur_check'] ) );
			}
			if ( $this->pbur_wc_hpos_enabled() ) {
				$order = wc_get_order( $order_id );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				$order->save();
			} else {
				update_post_meta( $order_id, 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
			}
		}

		/**
		 * Checkbox and dropdown option to select the user role for the order on the edit/add order page.
		 *
		 * @param Object $order Order object.
		 */
		public function alg_wc_pbur_order_role_selection_option( $order ) {

			global $pagenow;
			$pagenow1 = '';
			if ( $this->pbur_wc_hpos_enabled() ) {
				$pagenow1                     = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$order_role_checkbox_selected = $order->get_meta( 'alg_wc_price_by_user_role_order_page_checkbox' );
				$order_role_selected          = $order->get_meta( 'alg_wc_price_by_user_role_order_role' );
			} else {
				$order_id                     = $order->get_id();
				$order_role_checkbox_selected = get_post_meta( $order_id, 'alg_wc_price_by_user_role_order_page_checkbox', true );
				$order_role_selected          = get_post_meta( $order_id, 'alg_wc_price_by_user_role_order_role', true );
			}
			if ( 'true' === $order_role_checkbox_selected || 'on' === $order_role_checkbox_selected || 'post-new.php' === $pagenow || 'new' === $pagenow1 ) {
				$checked = 'checked';
			} else {
				$checked = '';
			}
			?>
			<div class="order_data_column" style = "width:100%">
			<p>
				<input type = "checkbox" name ="checkbox_pbur" id = "checkbox_pbur" <?php echo esc_attr( $checked ); ?>> <?php esc_html_e( 'Set a user role for this order?', 'price-by-user-role-for-woocommerce' ); ?> </input>
			</p>
			<label for = "alg_wc_pbur_select_role"> <?php esc_html_e( 'Select a role:', 'price-by-user-role-for-woocommerce' ); ?>
			<select name="alg_wc_pbur_select_role" id="alg_wc_pbur_select_role">
				<option value ="not_selected"> <?php esc_html_e( 'Select a role', 'price-by-user-role-for-woocommerce' ); ?> </option>
			<?php
			foreach ( alg_get_user_roles() as $role_key => $role_data ) {
				if ( $role_key === $order_role_selected ) {
					$selected = 'selected';
				} else {
					$selected = '';
				}
				?>
				<option value="<?php echo esc_attr( $role_key ); ?>" <?php echo esc_attr( $selected ); ?> ><?php echo esc_attr( $role_data['name'] ); ?></option>
				<?php
			}
			?>
			</select>
			</label>
			</div>
			<?php
			wp_nonce_field( 'pbur_userole_checkbox_nonce', 'pbur_userole_checkbox_nonce' );
		}

		/**
		 *  Function to update the order user role option in the DB as per the user role selected in the dropdwon on the Edit/Add order page.
		 *
		 * @param int    $post_id Post ID.
		 * @param object $post Post object.
		 */
		public function alg_wc_pbur_update_order_role_options( $post_id, $post ) {
			if ( 'shop_order' !== $post->post_type ) {
				return;
			}
			if ( empty( $_POST['pbur_userole_checkbox_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pbur_userole_checkbox_nonce'] ), 'pbur_userole_checkbox_nonce' ) ) {
				return;
			}
			if ( ! isset( $_POST['alg_wc_pbur_select_role'] ) || ! isset( $_POST['checkbox_pbur'] ) ) {
				return;
			}
			$pbur_checkbox_selected   = sanitize_text_field( wp_unslash( $_POST['checkbox_pbur'] ) );
			$pbur_order_role_selected = sanitize_text_field( wp_unslash( $_POST['alg_wc_pbur_select_role'] ) );
			if ( $this->pbur_wc_hpos_enabled() ) {
				$order = wc_get_order( $post_id );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				$order->update_meta_data( 'alg_wc_price_by_user_role_order_role', $pbur_order_role_selected );
				$order->save();

			} else {
				update_post_meta( $post_id, 'alg_wc_price_by_user_role_order_page_checkbox', $pbur_checkbox_selected );
				update_post_meta( $post_id, 'alg_wc_price_by_user_role_order_role', $pbur_order_role_selected );
			}
		}

		/**
		 * Function to update the product prices as per user role selcted.
		 *
		 * @param string $item_id Item id.
		 * @param array  $item Items array.
		 * @param object $order Order object.
		 */
		public function alg_wc_pbur_product_prices_as_user_role_in_order( $item_id, $item, $order ) {
			$order_id                     = $order->get_id();
			$order_role_checkbox_selected = get_post_meta( $order_id, 'alg_wc_price_by_user_role_order_page_checkbox', true );
			if ( 'true' === $order_role_checkbox_selected || 'on' === $order_role_checkbox_selected ) {
				$current_user_role = get_post_meta( $order_id, 'alg_wc_price_by_user_role_order_role', true );
				$product_id        = $item->get_product_id();
				if ( $product_id > 0 ) {
					foreach ( $order->get_items() as $item_id => $item ) {
						$product    = $item->get_product();
						$product_id = $item->get_product_id();
						if ( 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_per_product_settings_enabled', true ) ) {
							$variation_id = $item->get_variation_id();
							if ( $variation_id > 0 ) {
								$product_id = $variation_id;
							}
							// If user role prices are set to empty.
							if ( 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
								$pbur_price = '';
							}
							// Regular price set for user role.
							$regular_price_per_product = get_post_meta( $product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
							if ( '' !== ( $regular_price_per_product ) ) {
								$pbur_price             = $regular_price_per_product;
								$sale_price_per_product = get_post_meta( $product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
								$pbur_price             = $this->pbur_check_price_by_role_admin_order( $product_id, $current_user_role, $pbur_price );
								// checking if sale price set for user role is not null and to set sale price.
								if ( '' !== $sale_price_per_product ) {
									$pbur_price = $sale_price_per_product;
									$pbur_price = $this->pbur_check_price_by_role_admin_order( $product_id, $current_user_role, $pbur_price );
								}
							} else {
								$pbur_price = get_post_meta( $product_id, '_price', true );
							}
							$quantity   = $item->get_quantity();
							$pbur_price = $quantity * $pbur_price;
							$item->set_subtotal( $pbur_price );
							$item->set_total( $pbur_price );
							// Make new taxes calculations.
							$item->calculate_taxes();
							$item->save(); // Save line item data.
						}
						$order->save();
						$order->calculate_totals();
					}
				}
			}
		}

		/**
		 * Function to update the product prices in the order as per the role selected when Recalculate button is clicked.
		 *
		 * @param string $and_taxes Taxes in the order.
		 * @param object $order Order object.
		 */
		public function alg_wc_price_by_user_role_update_order( $and_taxes, $order ) {
			$order_id                     = $order->get_id();
			$order_role_checkbox_selected = get_post_meta( $order_id, 'alg_wc_price_by_user_role_order_page_checkbox', true );
			if ( 'on' === $order_role_checkbox_selected ) {
				$current_user_role = get_post_meta( $order_id, 'alg_wc_price_by_user_role_order_role', true );
				foreach ( $order->get_items() as $item_id => $item ) {
					$product    = $item->get_product();
					$product_id = $item->get_product_id();
					if ( 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_per_product_settings_enabled', true ) ) {
						$variation_id = $item->get_variation_id();
						if ( $variation_id > 0 ) {
							$product_id = $variation_id;
						}
						// If user role prices are set to empty.
						if ( 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_empty_price_' . $current_user_role, true ) ) {
							$pbur_price = '';
						}
						// Regular price set for user role.
						$regular_price_per_product = get_post_meta( $product_id, '_alg_wc_price_by_user_role_regular_price_' . $current_user_role, true );
						if ( '' !== ( $regular_price_per_product ) ) {
							$pbur_price             = $regular_price_per_product;
							$sale_price_per_product = get_post_meta( $product_id, '_alg_wc_price_by_user_role_sale_price_' . $current_user_role, true );
							$pbur_price             = $this->pbur_check_price_by_role_admin_order( $product_id, $current_user_role, $pbur_price );
							// checking if sale price set for user role is not null and to set sale price.
							if ( '' !== $sale_price_per_product ) {
								$pbur_price = $sale_price_per_product;
								$pbur_price = $this->pbur_check_price_by_role_admin_order( $product_id, $current_user_role, $pbur_price );
							}
						} else {
							$pbur_price = get_post_meta( $product_id, '_price', true );
						}
						if ( 'yes' === get_option( 'woocommerce_prices_include_tax', '' ) ) {
							$pbur_price = wc_get_price_excluding_tax(
								$product,
								array( 'price' => $pbur_price )
							);
						}
						$quantity   = $item->get_quantity();
						$pbur_price = $quantity * $pbur_price;
						$item->set_subtotal( $pbur_price );
						$item->set_total( $pbur_price );
						// Make new taxes calculations.
						$item->calculate_taxes();
						$item->save(); // Save li-ne item data.
					}
					$order->save();
				}
			}
		}

		/**
		 * Function to check the product prices in the order as per the role selected when add product button is clicked.
		 *
		 * @param object $product_id product id.
		 * @param string $current_user_role current user role.
		 * @param float  $pbur_price product price.
		 */
		public function pbur_check_price_by_role_admin_order( $product_id, $current_user_role, $pbur_price ) {
			if ( 'fixed_price' !== get_post_meta( $product_id, '_alg_wc_price_by_user_role_adjusment_type_' . $current_user_role, true ) ) {
					$adjustment_types = get_post_meta( $product_id, '_alg_wc_price_by_user_role_adjusment_type_' . $current_user_role, true );
					$price            = get_post_meta( $product_id, '_price', true );
					$pbur_price       = $this->change_price_by_role_adjucement_type( $price, $pbur_price, $adjustment_types );
			}
			if ( 'yes' === get_option( 'alg_wc_price_by_user_role_multipliers_enabled', 'yes' ) ) {
				if ( 'yes' === get_option( 'alg_wc_price_by_user_role_empty_price_' . $current_user_role, 'no' ) ) {
					return '';
				}
				$koef = get_option( 'alg_wc_price_by_user_role_' . $current_user_role, 1 );
				if ( 1 !== ( $koef ) ) {
					if ( ! empty( $pbur_price ) ) {
						$pbur_price = $pbur_price * (float) $koef;
					}
				}
			}
			return $pbur_price;
		}
	}

endif;

return new Alg_WC_Price_By_User_Role_Core();
