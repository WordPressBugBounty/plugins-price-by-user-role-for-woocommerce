<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Per-product meta box on the Edit Product admin page.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

use Tyche\PBUR\Helpers\Settings;
use Tyche\PBUR\Helpers\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Product_Meta_Box class.
 *
 * @since 2.0
 */
class Product_Meta_Box {

	const NONCE_KEY    = 'pbur_product_metabox';
	const NONCE_ACTION = 'pbur_product_metabox_save';


	/**
	 * Constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		if ( ! Settings::get( 'perProductEnabled' ) ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta_box' ), PHP_INT_MAX, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices_lite' ) );
	}

	/**
	 * Register the meta box.
	 *
	 * @since 2.0
	 */
	public function add_meta_box() {
		add_meta_box(
			'pbur_per_product',
			__( 'Product Prices by User Roles: Per Product Settings', 'price-by-user-role-for-woocommerce' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue styles and toggle script on product edit pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @since 2.0
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $post_id && 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		wp_enqueue_style(
			'pbur-product-meta-box',
			PBUR_PLUGIN_URL . '/assets/css/product-meta-box.css',
			array(),
			PBUR_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'pbur-product-meta-box',
			PBUR_PLUGIN_URL . '/assets/js/product-settings-admin.js',
			array( 'jquery' ),
			PBUR_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param \WP_Post $post Current product post.
	 * @since 2.0
	 */
	public function render_meta_box( $post ) {
		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		$meta          = get_post_meta( $post->ID, '_alg_wc_price_by_user_role_per_product_settings_enabled', true );
		$enabled       = $meta ? $meta : 'no';
		$display_style = 'yes' === $enabled ? 'block' : 'none';
		$roles         = $this->get_visible_roles();
		$products      = $this->get_product_map( $product );
		$is_variable   = $product->is_type( 'variable' );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_KEY );
		?>
<div class="pbur-metabox">

	<div class="pbur-metabox-enable-row">
		<div class="pbur-metabox-enable-label">
			<strong><?php esc_html_e( 'Enable per-product pricing', 'price-by-user-role-for-woocommerce' ); ?></strong>
			<span><?php esc_html_e( 'Configure individual role pricing for this product', 'price-by-user-role-for-woocommerce' ); ?></span>
		</div>
		<label class="pbur-toggle">
			<input type="checkbox" id="pbur-metabox-enabled" name="alg_wc_price_by_user_role_per_product_settings_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?>>
			<span class="pbur-toggle-slider"></span>
		</label>
	</div>

	<div id="pbur-metabox-pricing" style="display:<?php echo esc_attr( $display_style ); ?>">
		<?php foreach ( $products as $prod_id => $variation_label ) : ?>
		<div class="pbur-variation-block">
			<?php if ( $is_variable ) : ?>
			<div class="pbur-variation-header">
				<?php echo esc_html( ltrim( $variation_label, ' ' ) ); ?>
			</div>
			<?php endif; ?>

			<div class="pbur-role-pricing-table-wrap">
				<table class="pbur-role-pricing-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User Role', 'price-by-user-role-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Regular Price', 'price-by-user-role-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Sale Price', 'price-by-user-role-for-woocommerce' ); ?></th>
							<th class="pbur-col-center"><?php esc_html_e( 'Empty Price', 'price-by-user-role-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $roles as $role ) : ?>
							<?php $this->render_role_row( (int) $prod_id, $role ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

</div>
		<?php
	}

	/**
	 * Render a single role row inside the pricing table body.
	 *
	 * @param int   $product_id Product or variation post ID.
	 * @param array $role       Role array with 'key' and 'name'.
	 * @since 2.0
	 */
	private function render_role_row( int $product_id, array $role ) {
		$role_key    = $role['key'];
		$s           = $role_key . '_' . $product_id;
		$reg_price   = get_post_meta( $product_id, '_alg_wc_price_by_user_role_regular_price_' . $role_key, true );
		$sale_price  = get_post_meta( $product_id, '_alg_wc_price_by_user_role_sale_price_' . $role_key, true );
		$empty_price = 'yes' === get_post_meta( $product_id, '_alg_wc_price_by_user_role_empty_price_' . $role_key, true );
		?>
<tr>
	<td class="pbur-role-name"><?php echo esc_html( $role['name'] ); ?></td>
	<td>
		<input type="number" step="0.0001" name="pbur_regular_price_<?php echo esc_attr( $s ); ?>"
			value="<?php echo esc_attr( $reg_price ); ?>" class="pbur-price-input">
	</td>
	<td>
		<input type="number" step="0.0001" name="pbur_sale_price_<?php echo esc_attr( $s ); ?>"
			value="<?php echo esc_attr( $sale_price ); ?>" class="pbur-price-input">
	</td>
	<td class="pbur-col-center">
		<input type="checkbox" name="pbur_empty_price_<?php echo esc_attr( $s ); ?>" value="yes"
			<?php checked( $empty_price ); ?>>
	</td>
</tr>
		<?php
	}

	/**
	 * Save the meta box values.
	 *
	 * @param int $post_id Post ID.
	 * @since 2.0
	 */
	public function save_meta_box( int $post_id ) {
		if ( empty( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_KEY ] ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_products' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$enabled = isset( $_POST['alg_wc_price_by_user_role_per_product_settings_enabled'] )
			? sanitize_key( wp_unslash( $_POST['alg_wc_price_by_user_role_per_product_settings_enabled'] ) )
			: 'no';

		if ( 'yes' === $enabled ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_alg_wc_price_by_user_role_per_product_settings_enabled', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
				'post__not_in'   => array( $post_id ),
				'fields'         => 'ids',
			);
			$loop = new \WP_Query( $args );
			if ( $loop->found_posts >= 1 ) {
				add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var_lite' ), 99 );
				$enabled = 'no';
			}
		}

		update_post_meta( $post_id, '_alg_wc_price_by_user_role_per_product_settings_enabled', $enabled );

		$product  = wc_get_product( $post_id );
		$products = $this->get_product_map( $product );

		foreach ( $products as $prod_id => $_ ) {
			foreach ( $this->get_visible_roles() as $role ) {
				$role_key = $role['key'];
				$s        = $role_key . '_' . $prod_id;

				$reg_price  = sanitize_text_field( wp_unslash( $_POST[ 'pbur_regular_price_' . $s ] ?? '' ) );
				$sale_price = sanitize_text_field( wp_unslash( $_POST[ 'pbur_sale_price_' . $s ] ?? '' ) );
				$empty      = isset( $_POST[ 'pbur_empty_price_' . $s ] ) ? 'yes' : 'no';

				update_post_meta( (int) $prod_id, '_alg_wc_price_by_user_role_regular_price_' . $role_key, $reg_price );
				update_post_meta( (int) $prod_id, '_alg_wc_price_by_user_role_sale_price_' . $role_key, $sale_price );
				update_post_meta( (int) $prod_id, '_alg_wc_price_by_user_role_empty_price_' . $role_key, $empty );
			}
		}
	}

	/**
	 * Return the roles to display, respecting the perProductRoles filter setting.
	 *
	 * @return array
	 * @since 2.0
	 */
	private function get_visible_roles(): array {
		$all_roles    = Utils::get_user_roles();
		$filter_roles = Settings::get( 'perProductRoles', array() );

		if ( empty( $filter_roles ) ) {
			return $all_roles;
		}

		return array_values(
			array_filter(
				$all_roles,
				function ( $role ) use ( $filter_roles ) {
					return in_array( $role['key'], $filter_roles, true );
				}
			)
		);
	}

	/**
	 * Build the product → label map.
	 *
	 * Simple products return a single entry keyed by the product ID.
	 * Variable products return one entry per variation, labelled with
	 * the variation's attribute combination.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array<int, string>
	 * @since 2.0
	 */
	private function get_product_map( $product ): array {
		$map = array();

		if ( ! $product ) {
			return $map;
		}

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation            = wc_get_product( $variation_id );
				$map[ $variation_id ] = $variation
					? $this->get_variation_label( $variation )
					: '#' . $variation_id;
			}
		} else {
			$map[ $product->get_id() ] = '';
		}

		return $map;
	}

	/**
	 * Build a human-readable label for a variation, always showing "Any" for unset attributes.
	 *
	 * @param \WC_Product_Variation $variation Variation object.
	 * @return string
	 * @since 2.0
	 */
	private function get_variation_label( \WC_Product_Variation $variation ): string {
		$parts = array();

		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			$attribute_name = str_replace( 'attribute_', '', $key );
			$label          = wc_attribute_label( $attribute_name );

			if ( '' === $value ) {
				$display = __( 'Any', 'price-by-user-role-for-woocommerce' );
			} elseif ( taxonomy_exists( $attribute_name ) ) {
				$term    = get_term_by( 'slug', $value, $attribute_name );
				$display = $term ? $term->name : $value;
			} else {
				$display = $value;
			}

			$parts[] = $label . ': ' . $display;
		}

		return implode( ', ', $parts );
	}

	/**
	 * Append the upgrade-notice query var to the post-save redirect URL.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 * @since 2.0
	 */
	public function add_notice_query_var_lite( string $location ): string {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var_lite' ), 99 );
		return add_query_arg( array( 'pbur_per_product_limit' => '1' ), $location );
	}

	/**
	 * Show admin notice when the Lite per-product product limit is reached.
	 *
	 * @since 2.0
	 */
	public function admin_notices_lite() {
		if ( ! isset( $_GET['pbur_per_product_limit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php echo wp_kses_post( sprintf( __( 'The free version is limited to one product with per-product pricing enabled at a time. <a href="%s" target="_blank">Upgrade to Pro</a> for unlimited products.', 'price-by-user-role-for-woocommerce' ), esc_url( 'https://www.tychesoftwares.com/products/product-prices-by-user-roles-for-woocommerce/' ) ) ); ?></p>
		</div>
		<?php
	}
}