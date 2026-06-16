<?php
/**
 * Product Prices by User Roles for WooCommerce.
 *
 * Main Class.
 *
 * @author      Tyche Softwares
 * @package     PBUR/Main
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\PBUR;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Delivery Notes Core Class.
 *
 * @class Price_By_User_Role_For_WooCommerce.
 */
final class Price_By_User_Role_For_WooCommerce {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected static $plugin_version = '2.0.0';

	/**
	 * Minimum version of WordPress required.
	 *
	 * @var string
	 */
	private static $wordpress_version = '5.2';

	/**
	 * Minimum version of WooCommerce required.
	 *
	 * @var string
	 */
	private static $woocommerce_version = '3.3.0';

	/**
	 * Minimum version of PHP required.
	 *
	 * @var string
	 */
	private static $php_version = '7.4';

	/**
	 * Slug.
	 *
	 * @var string
	 */
	protected static $slug = 'pbur';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected static $plugin_slug = 'price-by-user-role-for-woocommerce';

	/**
	 * Plugin Name.
	 *
	 * @var string
	 */
	protected static $plugin_name = 'Product Prices by User Roles for WooCommerce';

	/**
	 * The single instance of the class.
	 *
	 * @var Price_By_User_Role_For_WooCommerce
	 */
	protected static $instance = null;

	/**
	 * Retrieve the instance of the class and ensures only one instance is loaded or can be loaded.
	 *
	 * @return Price_By_User_Role_For_WooCommerce
	 *
	 * @since 2.0
	 */
	public static function instance() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof Price_By_User_Role_For_WooCommerce ) ) {
			self::$instance = new Price_By_User_Role_For_WooCommerce();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Remove v1 files that conflict with the v2 structure when upgrading via FTP or zip-over-zip.
	 *
	 * TODO: Implemented in 2.0.0. Remove this in a future major release once most users have upgraded.
	 *
	 * @since 2.0
	 */
	public static function cleanup_v1_files() {
		$dir = dirname( PBUR_FILE );
		if ( ! \file_exists( $dir . '/includes/settings' ) ) {
			return;
		}
		$rmrf = static function ( string $path ) use ( &$rmrf ): void {
			if ( \is_file( $path ) ) {
				\wp_delete_file( $path );
				return;
			}
			foreach ( (array) \glob( $path . '/*' ) as $child ) {
				$rmrf( $child );
			}
			if ( \is_dir( $path ) ) {
				\rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP_Filesystem is not initialized at plugin bootstrap.
			}
		};
		foreach ( array(
			'includes/settings',
			'includes/tyche',
			'includes/class-alg-wc-price-by-user-role-core.php',
			'includes/alg-wc-price-by-user-role-functions.php',
			'includes/class-tyche-pbur-license.php',
			'includes/class-tyche-pbur-updater.php',
		) as $rel ) {
			$rmrf( $dir . '/' . $rel );
		}
	}

	/**
	 * Register the textdomain and schedule plugin initialisation on plugins_loaded.
	 *
	 * Called once from the main plugin file. Keeps bootstrap logic out of
	 * the global scope.
	 *
	 * @since 2.0
	 */
	public static function bootstrap() {
		// Must be registered before plugins_loaded so it fires before WooCommerce
		// boots and triggers before_woocommerce_init.
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					$plugin = plugin_basename( PBUR_FILE );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $plugin, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', $plugin, true );
				}
			}
		);

		add_action(
			'plugins_loaded',
			function () {
				$domain = 'price-by-user-role-for-woocommerce';
				$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
				if ( ! load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '-' . $locale . '.mo' ) ) {
					load_plugin_textdomain( $domain, false, dirname( plugin_basename( PBUR_FILE ) ) . '/languages/' );
				}
			},
			1
		);
		add_action( 'plugins_loaded', __NAMESPACE__ . '\\PBUR' );
	}

	/**
	 * A dummy constructor to prevent PBUR from being loaded more than once.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {}

	/**
	 * A dummy magic method to prevent PBUR from being cloned.
	 *
	 * @since 2.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed.', 'price-by-user-role-for-woocommerce' ), '2.0.0' );
	}

	/**
	 * A dummy magic method to prevent PBUR from being un-serialized.
	 *
	 * @since 2.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed.', 'price-by-user-role-for-woocommerce' ), '2.0.0' );
	}

	/**
	 * Default constructor
	 *
	 * @since 2.0
	 */
	private function setup() {

		/**
		 * Define Constants.
		 */
		self::define_constants();

		if ( ! self::check_requirements() ) {
			return;
		}

		/**
		 * Hooks.
		 */
		self::init_hooks();

		/**
		 * Include Files.
		 */
		self::maybe_include_files();
	}

	/**
	 * Action Hooks.
	 *
	 * @since 2.0
	 */
	private static function init_hooks() {
		register_activation_hook( PBUR_FILE, array( 'Tyche\\PBUR\\Install', 'install' ) );
		register_deactivation_hook( PBUR_FILE, array( 'Tyche\\PBUR\\Uninstall', 'deactivate_plugin' ) );

		// PBUR Hooks.
		self::include_file( 'core/class-hooks.php' );
		Hooks::init();
	}

	/**
	 * Function for defining constants.
	 *
	 * @param string $variable Constant which is to be defined.
	 * @param string $value Value of the Constant.
	 *
	 * @since 2.0
	 */
	public static function define( $variable, $value ) {
		if ( ! defined( $variable ) ) {
			define( $variable, $value );
		}
	}

	/**
	 * Include File.
	 *
	 * @param string $file Relative path under includes/ to include.
	 * @since 2.0
	 */
	public static function include_file( $file ) {
		$base     = realpath( PBUR_PLUGIN_PATH . '/includes' );
		$resolved = realpath( PBUR_PLUGIN_PATH . '/includes/' . $file );

		if ( false === $base || false === $resolved || strpos( $resolved, $base . DIRECTORY_SEPARATOR ) !== 0 ) {
			return;
		}

		include_once $resolved; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- nosemgrep: audit.php.lang.security.file.inclusion-arg
	}

	/**
	 * Define constants to be used ac ross the plugin.
	 *
	 * @since 2.0
	 */
	public static function define_constants() {
		self::define( 'PBUR_PLUGIN_NAME', self::$plugin_name );
		self::define( 'PBUR_SLUG', self::$slug );
		self::define( 'PBUR_PLUGIN_SLUG', self::$plugin_slug );
		self::define( 'PBUR_PLUGIN_VERSION', self::$plugin_version );
		self::define( 'PBUR_PLUGIN_PATH', untrailingslashit( plugin_dir_path( PBUR_FILE ) ) );
		self::define( 'PBUR_PLUGIN_URL', untrailingslashit( plugins_url( '/', PBUR_FILE ) ) );
		self::define( 'PBUR_IMAGE_URL', PBUR_PLUGIN_URL . '/assets/images' );
		self::define( 'PBUR_AJAX_URL', get_admin_url() . 'admin-ajax.php' );
	}

	/**
	 * Checks that all requirements are met.
	 *
	 * @return bool
	 */
	public static function check_requirements() {

		$messages = array();

		// Check WordPress version.
		if ( version_compare( get_bloginfo( 'version' ), self::$wordpress_version, '<' ) ) {
			/* translators: 1. Plugin Name, 2. WordPress Version */
			$messages[] = sprintf( esc_html__( 'You are using an outdated version of WordPress. %1$s requires WP version %2$s or higher.', 'price-by-user-role-for-woocommerce' ), self::$plugin_name, self::$wordpress_version );
		}

		// Check PHP version.
		if ( version_compare( phpversion(), self::$php_version, '<' ) ) {
			/* translators: 1. Plugin Name, 2. PHP Version */
			$messages[] = sprintf( esc_html__( '%1$s requires PHP version %2$s or above. Please update PHP to run this plugin.', 'price-by-user-role-for-woocommerce' ), self::$plugin_name, self::$php_version );
		}

		// Check WooCommerce is active.
		if ( ! self::is_woocommerce_active() ) {
			/* translators: 1. Plugin Name, 2. WooCommerce Version */
			$messages[] = sprintf( esc_html__( 'WooCommerce not found. %1$s requires WooCommerce v%2$s or higher.', 'price-by-user-role-for-woocommerce' ), self::$plugin_name, self::$woocommerce_version );
		} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::$woocommerce_version, '<' ) ) {
			/* translators: 1. Plugin Name, 2. WooCommerce Version */
			$messages[] = sprintf( esc_html__( 'You are using an outdated version of WooCommerce. %1$s requires WooCommerce v%2$s or higher.', 'price-by-user-role-for-woocommerce' ), self::$plugin_name, self::$woocommerce_version );
		}

		if ( empty( $messages ) ) {
			return true;
		}

		self::include_file( 'class-pbur-notices.php' );

		if ( class_exists( __NAMESPACE__ . '\\Notices', false ) ) {
			foreach ( $messages as $index => $message ) {
				Notices::add_notice(
					'plugin_installation_error_notice_' . $index,
					$message
				);
			}
		}

		add_action( 'admin_init', array( __CLASS__, 'deactivate' ) );

		return false;
	}

	/**
	 * Auto-deactivate plugin if requirements are not met.
	 */
	public static function deactivate() {
		if ( is_plugin_active( plugin_basename( PBUR_FILE ) ) ) {
			deactivate_plugins( plugin_basename( PBUR_FILE ) );
		}

		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore
			unset( $_GET['activate'] ); // phpcs:ignore
		}
	}

	/**
	 * Checks if WooCommerce is installed and active.
	 *
	 * @since 7.0
	 */
	public static function is_woocommerce_active() {

		// WooCommerce is required.
		$woocommerce_path = 'woocommerce/woocommerce.php';
		$active_plugins   = (array) get_option( 'active_plugins', array() );
		$active           = false;

		if ( is_multisite() ) {
			$plugins = get_site_option( 'active_sitewide_plugins' );
			$active  = isset( $plugins[ $woocommerce_path ] );
		}

		return in_array( $woocommerce_path, $active_plugins, true ) || array_key_exists( $woocommerce_path, $active_plugins ) || $active;
	}

	/**
	 * Checks whether to include the plugin files.
	 *
	 * @since 2.0
	 */
	public static function maybe_include_files() {
		self::include_file( 'core/class-files.php' );
		Files::include();
	}

	/**
	 * Return path/URL for asset file.
	 *
	 * @param string $path Path to the asset file.
	 * @param string $plugin The plugin file path to be relative to. Blank string if no plugin is specified.
	 * @since 2.0
	 */
	public static function get_asset_url( $path, $plugin = '' ) {
		$debug = ( defined( 'PBUR_SCRIPT_DEBUG' ) && PBUR_SCRIPT_DEBUG )
			|| ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
			|| self::is_local();

		$is_build = ( 0 === strpos( ltrim( $path, '/' ), 'build/' ) );

		if ( ! $debug && ! $is_build ) {
			$path = preg_replace( '/\.(js|css)$/', '.min.$1', $path );
		}

		return '' === $plugin ? plugins_url( $path ) : plugins_url( $path, $plugin );
	}

	/**
	 * Check if the site is running on a local environment.
	 *
	 * @return bool
	 * @since 2.0
	 */
	protected static function is_local() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.test' )
			|| str_ends_with( $host, '.localhost' );
	}
}
