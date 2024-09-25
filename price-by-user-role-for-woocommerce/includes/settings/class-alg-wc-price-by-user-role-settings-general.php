<?php
/**
 * Price by User Role for WooCommerce - General Section Settings
 *
 * @package PriceByUserRole
 * @version 1.2.0
 * @since   1.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Price_By_User_Role_Settings_General' ) ) :

	/**
	 * Alg_WC_Price_By_User_Role_Settings_General Class
	 *
	 * @class   Alg_WC_Price_By_User_Role_Settings_General
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Price_By_User_Role_Settings_General extends Alg_WC_Price_By_User_Role_Settings_Section {
		/**
		 * ID
		 *
		 * @var $id
		 * @since 1.0.0
		 */
		public $id = '';
		/**
		 * Desc
		 *
		 * @var $desc
		 * @since 1.0.0
		 */
		public $desc = '';
		/**
		 * Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function __construct() {
			$this->id   = '';
			$this->desc = __( 'General', 'price-by-user-role-for-woocommerce' );
			parent::__construct();
		}

		/**
		 * Get_section_settings.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function get_section_settings() {
			$settings = array(
				array(
					'title' => __( 'Price by User Role Options', 'price-by-user-role-for-woocommerce' ),
					'type'  => 'title',
					'id'    => 'alg_wc_price_by_user_role_options',
				),
				array(
					'title'    => __( 'Product Prices by User Roles', 'price-by-user-role-for-woocommerce' ),
					'desc'     => '<strong>' . __( 'Enable plugin', 'price-by-user-role-for-woocommerce' ) . '</strong>',
					'desc_tip' => __( 'Product Prices by User Roles for WooCommerce.', 'price-by-user-role-for-woocommerce' ) . '<br><a class="button" href="https://www.tychesoftwares.com/docs/docs/price-based-on-user-role-for-woocommerce/" target="_blank">Documentation</a>',
					'id'       => 'alg_wc_price_by_user_role_enabled',
					'default'  => 'yes',
					'type'     => 'checkbox',
				),
				array(
					'title'   => __( 'Search engine bots', 'price-by-user-role-for-woocommerce' ),
					'desc'    => __( 'Disable "Product Prices by User Roles" for bots', 'price-by-user-role-for-woocommerce' ),
					'id'      => 'alg_wc_price_by_user_role_for_bots_disabled',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_wc_price_by_user_role_options',
				),
			);
			return $settings;
		}

	}

endif;

return new Alg_WC_Price_By_User_Role_Settings_General();
