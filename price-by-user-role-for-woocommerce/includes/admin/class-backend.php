<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Backend Handler Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

use Tyche\PBUR\Helpers\Utils;
use Tyche\PBUR\Helpers\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Backend Handler Class.
 *
 * Manages all WooCommerce admin-side integrations: order role selection,
 * order recalculation with role-based pricing, and related AJAX callbacks.
 *
 * @since 2.0
 */
class Backend {

	const RULES_OPTION    = 'pbur_rules';
	const META_CHECKBOX   = 'alg_wc_price_by_user_role_order_page_checkbox';
	const META_ORDER_ROLE = 'alg_wc_price_by_user_role_order_role';
	const NONCE_ACTION    = 'alg_wc_pbur_order_action';

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		// Register order-pricing hooks for all request contexts (admin, AJAX, REST API).
		// The callbacks guard themselves with a pluginEnabled check.
		add_action( 'woocommerce_order_before_calculate_taxes', array( $this, 'recalculate_order_prices' ), PHP_INT_MAX, 2 );
		add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'apply_role_price_on_item_add' ), PHP_INT_MAX, 3 );

		add_action( 'admin_init', array( $this, 'admin_hooks' ) );
		add_filter( 'pbur_ts_tracker_data', array( $this, 'tracker_data' ), 10, 1 );
	}

	/**
	 * Load admin hooks.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function admin_hooks() {
		if ( ! Settings::get( 'pluginEnabled' ) ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_admin' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render_order_role_selector' ), PHP_INT_MAX );
		add_action( 'wp_ajax_alg_wc_pbur_order_role', array( $this, 'save_order_role_callback' ) );
		add_action( 'wp_ajax_alg_wc_pbur_order_detect_role', array( $this, 'detect_order_role_callback' ) );
		add_action( 'wp_ajax_alg_wc_pbur_checkbox_value', array( $this, 'save_checkbox_callback' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'update_order_role_on_save' ), PHP_INT_MAX, 1 );
	}

	/**
	 * Enqueue admin scripts for the order edit screen.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function enqueue_scripts_admin() {
		global $theorder, $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$screen      = get_current_screen();
		$action      = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$screen_type = Utils::is_hpos_enabled() ? $screen->id : $screen->post_type;

		if (
			'shop_order' !== $screen_type
			&& ! ( 'woocommerce_page_wc-orders' === $screen_type && 'new' === $action )
			&& 'edit' !== $action
		) {
			return;
		}

		if ( 'woocommerce_page_wc-orders' === $screen_type && isset( $theorder ) && is_object( $theorder ) ) {
			$order_id = $theorder->get_id();
		} elseif ( isset( $post ) && is_object( $post ) ) {
			$order_id = $post->ID;
		} else {
			$order_id = 0;
		}

		wp_enqueue_script(
			'pbur-admin-order-role',
			PBUR_PLUGIN_URL . '/assets/js/admin-order-role.js',
			array( 'jquery' ),
			PBUR_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'pbur-admin-order-role',
			'alg_wc_pbur',
			array(
				'order_id'    => $order_id,
				'screen_type' => $screen_type,
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Render the role selection checkbox and dropdown on the order edit page.
	 *
	 * Formerly `alg_wc_pbur_order_role_selection_option()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param \WC_Order $order Current order object.
	 * @return void
	 * @since 2.0
	 */
	public function render_order_role_selector( $order ) {
		global $pagenow;

		$pagenow1 = Utils::is_hpos_enabled() && isset( $_GET['action'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_text_field( wp_unslash( $_GET['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: '';

		$order_role_checkbox_selected = $this->get_order_meta_value( $order, self::META_CHECKBOX );
		$order_role_selected          = $this->get_order_meta_value( $order, self::META_ORDER_ROLE );

		$checked = ( 'true' === $order_role_checkbox_selected || 'on' === $order_role_checkbox_selected || 'post-new.php' === $pagenow || 'new' === $pagenow1 )
			? 'checked'
			: '';
		?>
<div class="order_data_column" style="width:100%">
	<p>
		<input type="checkbox" name="checkbox_pbur" id="checkbox_pbur" <?php echo esc_attr( $checked ); ?>>
		<?php esc_html_e( 'Set a user role for this order?', 'price-by-user-role-for-woocommerce' ); ?>
	</p>
	<label for="alg_wc_pbur_select_role">
		<?php esc_html_e( 'Select a role:', 'price-by-user-role-for-woocommerce' ); ?>
		<select name="alg_wc_pbur_select_role" id="alg_wc_pbur_select_role">
			<option value="not_selected"><?php esc_html_e( 'Select a role', 'price-by-user-role-for-woocommerce' ); ?>
			</option>
			<?php foreach ( Utils::get_user_roles() as $role ) : ?>
			<option value="<?php echo esc_attr( $role['key'] ); ?>"
				<?php selected( $role['key'], $order_role_selected ); ?>>
				<?php echo esc_html( $role['name'] ); ?>
			</option>
			<?php endforeach; ?>
		</select>
	</label>
</div>
		<?php
		wp_nonce_field( 'pbur_userole_checkbox_nonce', 'pbur_userole_checkbox_nonce' );
	}

	/**
	 * AJAX: save role and checkbox selection for an order.
	 *
	 * Formerly `alg_wc_pbur_order_role_callback()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function save_order_role_callback() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( -1, 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$checkbox = isset( $_POST['pbur_check'] ) ? sanitize_key( wp_unslash( $_POST['pbur_check'] ) ) : '';
		$role     = isset( $_POST['admin_choice'] ) ? sanitize_key( wp_unslash( $_POST['admin_choice'] ) ) : '';

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->save_order_meta(
				$order,
				array(
					self::META_CHECKBOX   => $checkbox,
					self::META_ORDER_ROLE => $role,
				)
			);
		}
		wp_die();
	}

	/**
	 * AJAX: detect the role from a given user ID and save to the order.
	 *
	 * Formerly `alg_wc_pbur_order_detect_role_callback()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function detect_order_role_callback() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( -1, 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$checkbox = isset( $_POST['pbur_check'] ) ? sanitize_key( wp_unslash( $_POST['pbur_check'] ) ) : '';
		$role     = '';

		if ( isset( $_POST['user_id'] ) ) {
			$user_data = get_userdata( absint( $_POST['user_id'] ) );
			if ( $user_data && ! empty( $user_data->roles ) ) {
				$role = $user_data->roles[0];
			}
		}

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->save_order_meta(
				$order,
				array(
					self::META_CHECKBOX   => $checkbox,
					self::META_ORDER_ROLE => $role,
				)
			);
		}

		echo esc_attr( $role );
		wp_die();
	}

	/**
	 * AJAX: save only the checkbox value for an order.
	 *
	 * Formerly `alg_wc_pbur_checkbox_value_callback()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @return void
	 * @since 2.0
	 */
	public function save_checkbox_callback() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( -1, 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$checkbox = isset( $_POST['pbur_check'] ) ? sanitize_key( wp_unslash( $_POST['pbur_check'] ) ) : '';

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->save_order_meta( $order, array( self::META_CHECKBOX => $checkbox ) );
		}
		wp_die();
	}

	/**
	 * Persist the role selection when an order is saved from the admin edit screen.
	 *
	 * Fires via woocommerce_process_shop_order_meta in both classic and HPOS modes.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 * @since 2.0
	 */
	public function update_order_role_on_save( $order_id ) {
		if ( empty( $_POST['pbur_userole_checkbox_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pbur_userole_checkbox_nonce'] ), 'pbur_userole_checkbox_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}
		if ( ! isset( $_POST['alg_wc_pbur_select_role'] ) || ! isset( $_POST['checkbox_pbur'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->save_order_meta(
				$order,
				array(
					self::META_CHECKBOX   => sanitize_key( wp_unslash( $_POST['checkbox_pbur'] ) ),
					self::META_ORDER_ROLE => sanitize_key( wp_unslash( $_POST['alg_wc_pbur_select_role'] ) ),
				)
			);
		}
	}

	/**
	 * Woocommerce_ajax_add_order_item_meta hook: apply role-based price when an item is added via AJAX.
	 *
	 * Formerly `alg_wc_pbur_product_prices_as_user_role_in_order()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param int            $item_id Item ID.
	 * @param \WC_Order_Item $item    Order item object.
	 * @param \WC_Order      $order   Order object.
	 * @return void
	 * @since 2.0
	 */
	public function apply_role_price_on_item_add( $item_id, $item, $order ) {
		if ( ! Settings::get( 'pluginEnabled' ) ) {
			return;
		}

		$checkbox = $order->get_meta( self::META_CHECKBOX, true );
		if ( 'true' !== $checkbox && 'on' !== $checkbox ) {
			return;
		}

		$role       = $order->get_meta( self::META_ORDER_ROLE, true );
		$product_id = $item->get_product_id();

		if ( $product_id <= 0 ) {
			return;
		}

		$rules = get_option( self::RULES_OPTION, array() );
		$rule  = $this->find_matching_rule( $rules, $product_id );

		if ( $rule ) {
			$this->apply_role_based_price( $rule, $role, $item );
		} else {
			$role_data = Utils::get_role_price_data( $product_id, $role );
			if ( '' === $role_data['reg_price'] ) {
				return;
			}
			$pbur_price = $this->pbur_check_price_by_role_admin_order( $product_id, $role, $role_data['reg_price'] );
			if ( '' !== $role_data['sale_price'] ) {
				$pbur_price = $this->pbur_check_price_by_role_admin_order( $product_id, $role, $role_data['sale_price'] );
			}
			$quantity    = $item->get_quantity();
			$total_price = $quantity * (float) $pbur_price;
			$item->set_subtotal( $total_price );
			$item->set_total( $total_price );
			$item->calculate_taxes();
			$item->save();
		}

		$order->save();
		$order->calculate_totals();
	}

	/**
	 * Woocommerce_order_before_calculate_taxes hook: recalculate all item prices using the stored role.
	 *
	 * Formerly `alg_wc_price_by_user_role_update_order()` in Alg_WC_Price_By_User_Role_Core.
	 *
	 * @param bool      $and_taxes Whether to recalculate taxes.
	 * @param \WC_Order $order     Order object.
	 * @return void
	 * @since 2.0
	 */
	public function recalculate_order_prices( $and_taxes, $order ) {
		if ( ! Settings::get( 'pluginEnabled' ) ) {
			return;
		}

		// Prefer values injected by our JS into the Recalculate POST data.
		// Avoids a race condition with the separate role-save AJAX and works in WP 7+
		// where the meta box DOM may be isolated from the enqueued script frame.
		// WooCommerce already verified the calc-totals nonce before this point.
		if ( // phpcs:ignore WordPress.Security.NonceVerification.Missing
			defined( 'DOING_AJAX' ) && DOING_AJAX
			&& isset( $_POST['pbur_check'], $_POST['pbur_role'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			$checkbox = sanitize_key( wp_unslash( $_POST['pbur_check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$role     = sanitize_key( wp_unslash( $_POST['pbur_role'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			$checkbox = $order->get_meta( self::META_CHECKBOX, true );
			$role     = $order->get_meta( self::META_ORDER_ROLE, true );
		}

		if ( 'true' !== $checkbox && 'on' !== $checkbox ) {
			return;
		}

		if ( ! $role || 'not_selected' === $role ) {
			return;
		}

		$rules = get_option( self::RULES_OPTION, array() );

		foreach ( $order->get_items() as $item ) {
			$base_product_id = $item->get_product_id();
			$variation_id    = $item->get_variation_id();
			$product_id      = $variation_id > 0 ? $variation_id : $base_product_id;

			$rule                = $this->find_matching_rule( $rules, $base_product_id );
			$per_product_enabled = Utils::is_per_product_enabled( $base_product_id );

			if ( $rule && ! ( $per_product_enabled && empty( $rule['override_product_level'] ) ) ) {
				$this->apply_role_based_price( $rule, $role, $item );
				continue;
			}

			if ( ! $per_product_enabled ) {
				// No rule and no per-product pricing — apply global multiplier if enabled.
				if ( Settings::get( 'multipliersEnabled' ) ) {
					if ( Settings::is_empty_price( $role ) ) {
						continue;
					}
					$koef = Settings::get_multiplier( $role );
					if ( 1.0 !== $koef ) {
						$base_price = (float) get_post_meta( $product_id, '_price', true );
						if ( $base_price > 0 ) {
							$total_price = $item->get_quantity() * ( $base_price * $koef );
							$item->set_subtotal( $total_price );
							$item->set_total( $total_price );
							$item->calculate_taxes();
							$item->save();
						}
					}
				}
				continue;
			}

			$role_data = Utils::get_role_price_data( $product_id, $role );

			if ( $role_data['empty_price'] ) {
				continue;
			}

			if ( '' === $role_data['reg_price'] ) {
				continue;
			}

			$pbur_price = $this->pbur_check_price_by_role_admin_order( $product_id, $role, $role_data['reg_price'] );
			if ( '' !== $role_data['sale_price'] ) {
				$pbur_price = $this->pbur_check_price_by_role_admin_order( $product_id, $role, $role_data['sale_price'] );
			}

			if ( '' === (string) $pbur_price ) {
				continue;
			}

			$product = $item->get_product();
			if ( 'yes' === get_option( 'woocommerce_prices_include_tax', '' ) ) {
				$pbur_price = wc_get_price_excluding_tax( $product, array( 'price' => $pbur_price ) );
			}

			$quantity    = $item->get_quantity();
			$total_price = $quantity * (float) $pbur_price;
			$item->set_subtotal( $total_price );
			$item->set_total( $total_price );
			$item->calculate_taxes();
			$item->save();
		}

		$order->save();
	}

	/**
	 * Apply the role-based price from a rule to an order item (new pbur_rules format).
	 *
	 * @param array          $rule Rule data (new format: role_pricing associative by role slug).
	 * @param string         $role Target role slug.
	 * @param \WC_Order_Item $item Order item to update.
	 * @return void
	 * @since 2.0
	 */
	private function apply_role_based_price( array $rule, string $role, $item ) {
		$role_pricing = $rule['role_pricing'] ?? array();

		if ( ! isset( $role_pricing[ $role ] ) ) {
			return;
		}

		$rp              = $role_pricing[ $role ];
		$adjustment_type = $rp['adjustment_type'] ?? 'fixed_price';

		if ( ! empty( $rp['empty_price'] ) ) {
			return;
		}

		$product    = $item->get_product();
		$product_id = $item->get_product_id();

		$raw_regular = get_post_meta( $product_id, '_regular_price', true );
		$raw_sale    = get_post_meta( $product_id, '_sale_price', true );
		$raw_regular = ( '' !== $raw_regular ) ? $raw_regular : $product->get_regular_price();
		$raw_sale    = ( '' !== $raw_sale ) ? $raw_sale : $product->get_sale_price();

		if ( '' !== ( $rp['sale_price'] ?? '' ) ) {
			$configured_value = $rp['sale_price'];
		} elseif ( '' !== ( $rp['regular_price'] ?? '' ) ) {
			$configured_value = $rp['regular_price'];
		} else {
			return;
		}

		$base       = ( '' !== $raw_sale && (float) $raw_sale > 0 ) ? (float) $raw_sale : (float) $raw_regular;
		$pbur_price = Utils::apply_price_adjustment( $base, $configured_value, $adjustment_type );

		$quantity    = $item->get_quantity();
		$total_price = $quantity * $pbur_price;
		$item->set_subtotal( $total_price );
		$item->set_total( $total_price );
		$item->calculate_taxes();
		$item->save();
	}

	/**
	 * Resolve the per-product meta price, applying adjustment type and global multiplier.
	 *
	 * @param int    $product_id        Product ID.
	 * @param string $current_user_role Current user role slug.
	 * @param mixed  $pbur_price        Configured price value.
	 * @return float|string
	 * @since 2.0
	 */
	private function pbur_check_price_by_role_admin_order( int $product_id, string $current_user_role, $pbur_price ) {
		$adjustment_type = get_post_meta( $product_id, '_alg_wc_price_by_user_role_adjusment_type_' . $current_user_role, true );

		if ( 'fixed_price' !== $adjustment_type && '' !== $adjustment_type ) {
			$base_price = get_post_meta( $product_id, '_price', true );
			$pbur_price = Utils::apply_price_adjustment( $base_price, $pbur_price, $adjustment_type );
		}

		if ( Settings::get( 'multipliersEnabled' ) ) {
			if ( Settings::is_empty_price( $current_user_role ) ) {
				return '';
			}
			$koef = Settings::get_multiplier( $current_user_role );
			if ( 1.0 !== $koef && ! empty( $pbur_price ) ) {
				$pbur_price = (float) $pbur_price * $koef;
			}
		}

		return $pbur_price;
	}

	/**
	 * Read a single meta value from an order.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $key   Meta key.
	 * @return string
	 * @since 2.0
	 */
	private function get_order_meta_value( \WC_Order $order, string $key ): string {
		return (string) $order->get_meta( $key );
	}

	/**
	 * Persist one or more meta values on an order.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $data  Key => value pairs to store.
	 * @return void
	 * @since 2.0
	 */
	private function save_order_meta( \WC_Order $order, array $data ): void {
		foreach ( $data as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}
		$order->save();
	}

	/**
	 * Return the first enabled rule matching the given product ID, or null.
	 *
	 * @param array $rules      Rules from the pbur_rules option.
	 * @param int   $product_id Product or variation ID.
	 * @return array|null
	 * @since 2.0
	 */
	/**
	 * Append plugin-specific data to the Tyche tracking payload.
	 *
	 * @param array $data Tracking data assembled by Plugin_Tracking.
	 * @return array
	 * @since 2.0
	 */
	public function tracker_data( array $data ): array {

		$data = array_merge(
			$data,
			array(
				'ts_meta_data_table_name' => 'ts_tracking_pbur_meta_data',
				'ts_plugin_name'          => PBUR_PLUGIN_NAME,
				'version'                 => PBUR_PLUGIN_VERSION,
				'data'                    => array(),
			)
		);

		$data['data']['plugin_settings'] = array(
			'pluginEnabled'           => (bool) Settings::get( 'pluginEnabled' ),
			'disableForBots'          => (bool) Settings::get( 'disableForBots' ),
			'multipliersEnabled'      => (bool) Settings::get( 'multipliersEnabled' ),
			'multipliersShipping'     => (bool) Settings::get( 'multipliersShipping' ),
			'multipliersConfigured'   => count( (array) Settings::get( 'multipliers', array() ) ),
			'perProductEnabled'       => (bool) Settings::get( 'perProductEnabled' ),
			'perProductRolesCount'    => count( (array) Settings::get( 'perProductRoles', array() ) ),
		);

		return $data;
	}

	/**
	 * Return the first active rule that applies to a given product, or null if none match.
	 *
	 * @param array $rules      Rules from the pbur_rules option.
	 * @param int   $product_id Product or variation ID.
	 * @return array|null
	 * @since 2.0
	 */
	private function find_matching_rule( array $rules, int $product_id ): ?array {
		// Pass 1: product-specific rules take priority, matching frontend Pass 1 behaviour.
		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if ( in_array( $product_id, array_map( 'intval', (array) ( $rule['products'] ?? array() ) ), true ) ) {
				return $rule;
			}
		}
		// Pass 2: all-products and category rules.
		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if (
				! empty( $rule['apply_to_all'] )
				|| Utils::product_belongs_to_categories( $product_id, $rule['categories'] ?? array() )
			) {
				return $rule;
			}
		}
		return null;
	}
}