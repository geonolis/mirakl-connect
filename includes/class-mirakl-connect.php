<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       www.gipapamanolis.gr
 * @since      1.0.0
 *
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/includes
 * @author     GeoNolis <info@gipapamanolis.gr>
 */
class Mirakl_Connect {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Mirakl_Connect_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected Mirakl_Connect_Loader $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected string $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected string $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'MIRAKL_CONNECT_VERSION' ) ) {
			$this->version = MIRAKL_CONNECT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'mirakl-connect';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();


		//	add_action( 'mirakl_connect_cron_hook', array( 'Mirakl_Connect_Import_Order', 'import_orders_from_mirakl' ));
		//      echo 'add action';


	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Mirakl_Connect_Loader. Orchestrates the hooks of the plugin.
	 * - Mirakl_Connect_i18n. Defines internationalization functionality.
	 * - Mirakl_Connect_Admin. Defines all hooks for the admin area.
	 * - Mirakl_Connect_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mirakl-connect-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mirakl-connect-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-mirakl-connect-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-mirakl-connect-public.php';



		/**
		 * The class responsible for importing orders from mirakl
		 *
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mirakl-connect-import-order.php';


		/**
		 * The class responsible for stock  sync
		 * see: https://wordpress.stackexchange.com/questions/263160/how-do-you-use-the-plugin-boilerplate-loader-class-to-hook-actions-and-filters
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/class-mirakl-api.php';

		$this->loader = new Mirakl_Connect_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Mirakl_Connect_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Mirakl_Connect_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Mirakl_Connect_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		//  admin settings added from:
		//	https://www.knowboard.de/how-to-create-a-wordpress-plugin-using-the-wppb-boilerplate-including-a-beautyful-settings-page/
		$plugin_settings = new Mirakl_Connect_Admin_Settings( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_menu', $plugin_settings, 'setup_plugin_options_menu' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_display_options' );
		//    $this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_social_options' );
		//    $this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_input_examples' );

		$mirakl_import = new Mirakl_Connect_Import_Order();
		// Order Sync
		$this->loader->add_action( 'admin_enqueue_scripts', $mirakl_import, 'on_admin_orders_page_load', 10, 1 );
		$this->loader->add_action( 'mirakl_connect_cron_hook', $mirakl_import, 'import_orders_from_mirakl' );
		$this->loader->add_action( 'woocommerce_order_status_processing', $mirakl_import, 'mirakl_notify_accept_order' );
		$this->loader->add_action( 'woocommerce_order_status_completed', $mirakl_import, 'mirakl_notify_ship_order', 20 );
		$this->loader->add_action( 'woocommerce_order_status_cancelled', $mirakl_import, 'mirakl_notify_reject_order', 10, 1 );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_payment_info', $mirakl_import, 'mirakl_order_link', 10, 1 );

		$mirakl_api=new Mirakl_API();
		// Stock Sync
//		$this->loader->add_action( 'woocommerce_product_set_stock', $mirakl_api, 'mirakl_sync_sold', 20, 1 );
//		$this->loader->add_action( 'woocommerce_variation_set_stock', $mirakl_api, 'update_offer', 10, 1 );
		$this->loader->add_action( 'woocommerce_reduce_order_stock', $mirakl_api, 'mirakl_sync_sold', 10, 1 );
		$this->loader->add_action( 'woocommerce_restore_order_stock', $mirakl_api, 'mirakl_sync_sold', 10, 1 );
		$this->loader->add_action( 'woocommerce_update_product', $mirakl_api, 'mirakl_update_product', 5,3);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Mirakl_Connect_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Plugin uses CHEQUE PAYMENT as PUBLIC MARKETPLACE PAYMENT
		// So we need to hide it from the frontend
		$this->loader->add_filter( 'woocommerce_available_payment_gateways', $plugin_public, 'mirakl_connect_filter_gateways', 1 );


	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 * @since     1.0.0
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Mirakl_Connect_Loader    Orchestrates the hooks of the plugin.
	 * @since     1.0.0
	 */
	public function get_loader(): Mirakl_Connect_Loader {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 * @since     1.0.0
	 */
	public function get_version(): string {
		return $this->version;
	}

}
