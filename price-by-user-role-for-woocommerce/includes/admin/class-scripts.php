<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Class for loading asset files for the Admin.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Admin/Files
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Scripts.
 *
 * @since 2.0
 */
class Scripts extends Admin {

	/**
	 * Construct
	 *
	 * @since 2.0
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_css' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_js' ) );
		add_filter( 'pbur_ts_tracker_display_notice', array( &$this, 'display_tracking_notice' ) );
	}

	/**
	 * CSS.
	 *
	 * @since 2.0
	 */
	public static function enqueue_css() {

		if ( ! self::is_on_pbur_page() ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-edit-blocks' );

		wp_enqueue_style(
			'pbur-main',
			PBUR()::get_asset_url( '/build/admin.css', PBUR_FILE ),
			array(),
			PBUR_PLUGIN_VERSION,
			false
		);
	}

	/**
	 * JS.
	 *
	 * @since 2.0
	 */
	public static function enqueue_js() {

		// Required globally — tyche.js backs the deactivation modal on plugins.php,
		// and the dismiss script handles the tracking notice on any admin page.
		wp_enqueue_script(
			'tyche',
			PBUR()::get_asset_url( '/assets/js/tyche.js', PBUR_FILE ),
			array( 'jquery' ),
			PBUR_PLUGIN_VERSION,
			true
		);

		wp_enqueue_script(
			'pbur_ts_dismiss_notice',
			PBUR()::get_asset_url( '/assets/js/dismiss-tracking-notice.js', PBUR_FILE ),
			array( 'jquery' ),
			PBUR_PLUGIN_VERSION,
			false
		);

		wp_localize_script(
			'pbur_ts_dismiss_notice',
			'pbur_ts_dismiss_notice',
			array(
				'ts_prefix_of_plugin' => 'pbur',
				'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
				'tracking_notice'     => wp_create_nonce( 'tracking_notice' ),
			)
		);

		if ( ! self::is_on_pbur_page() ) {
			return;
		}

		// Load WordPress Media Uploader.
		wp_enqueue_media();

		if ( file_exists( PBUR_PLUGIN_PATH . '/build/admin.asset.php' ) ) {

			$asset = include PBUR_PLUGIN_PATH . '/build/admin.asset.php';

			if ( $asset ) {
				wp_enqueue_script(
					'pbur-main',
					PBUR()::get_asset_url( '/build/admin.js', PBUR_FILE ),
					$asset['dependencies'],
					$asset['version'],
					true
				);

				wp_set_script_translations( 'pbur-main', 'pbur-main', PBUR_PLUGIN_PATH . '/languages/' );
			}
		}
	}

	/**
	 * Display Tracking Notice.
	 *
	 * @param bool $display Whether to display.
	 * @since 2.0
	 */
	public static function display_tracking_notice( $display ) {
		return self::is_on_pbur_page();
	}
}
