<?php

/**
 * The settings of the plugin.
 *
 * @link       www.gipapamanolis.gr
 * @since      1.0.0
 *
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/admin/partials
 */

/**
 * Class WordPress_Plugin_Template_Settings
 *
 */
class Mirakl_Connect_Admin_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * This function introduces the theme options into the 'Appearance' menu and into a top-level
	 * 'WPPB Demo' menu.
	 */
	public function setup_plugin_options_menu() {

		//Add the menu to the Plugins set of menu items
		add_submenu_page(
			'woocommerce', 				// The title to be displayed in the browser window for this page.
			'Mirakl CONNECT',					// The text to be displayed for this menu item
			'Mirakl CONNECT',					// Which type of users can see this menu item
			'manage_options',
            'mirakl_connect_options',   // The unique ID - that is, the slug - for this menu item
			array( $this, 'render_settings_page_content')				// The name of the function to call when rendering this menu's page
		);

	}

	/**
	 * Provides default values for the Display Options.
	 *
	 * @return array
	 */
	public function default_display_options() {

		$defaults = array(
			'mirakl_url'		=>	'https://',
			'mirakl_key'		=>	'xxx',
		);

		return $defaults;

	}


	/**
	 * Renders a simple page to display for the theme menu defined above.
	 */
	public function render_settings_page_content( $active_tab = '' ) {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">

			<h2><?php _e( 'Mirakl CONNECT Options', 'mirakl-connect' ); ?></h2>
			<?php settings_errors(); ?>


			
			<form method="post" action="options.php">
				<?php

					settings_fields( 'mirakl_connect_display_options' );
					do_settings_sections( 'mirakl_connect_display_options' );

				submit_button();

				?>
			</form>

		</div><!-- /.wrap -->
	<?php
	}


	/**
	 * This function provides a simple description for the General Options page.
	 *
	 * It's called from the 'wppb-demo_initialize_theme_options' function by being passed as a parameter
	 * in the add_settings_section function.
	 */
	public function general_options_callback() {
		$options = get_option('mirakl_connect_display_options');
//		var_dump($options);
		echo '<p>' . __( 'Συμπληρώστε τα παρακάτω που σας έχουν δωθεί από τη Mirakl:', 'mirakl-connect' ) . '</p>';
	} // end general_options_callback

	/**
	 * Initializes the theme's display options page by registering the Sections,
	 * Fields, and Settings.
	 *
	 * This function is registered with the 'admin_init' hook.
	 */
	public function initialize_display_options() {

		// If the theme options don't exist, create them.
		if( false == get_option( 'mirakl_connect_display_options' ) ) {
			$default_array = $this->default_display_options();
			add_option( 'mirakl_connect_display_options', $default_array );
		}


		add_settings_section(
			'general_settings_section',			            // ID used to identify this section and with which to register options
			__( 'Βασικές Επιλογές', 'mirakl-connect' ),		        // Title to be displayed on the administration page
			array( $this, 'general_options_callback'),	    // Callback used to render the description of the section
			'mirakl_connect_display_options'		                // Page on which to add this section of options
		);


		add_settings_field(
			'mirakl_url',							// ID used to identify the field throughout the theme
			__( 'Mirakl URL με https://', 'mirakl-connect' ),
			array( $this, 'mirakl_url_callback'),
			'mirakl_connect_display_options',
			'general_settings_section'
		);
		add_settings_field(
			'mirakl_key',							// ID used to identify the field throughout the theme	
			__( 'API Key', 'mirakl-connect' ),  	// The label to the left of the option interface element
			array( $this, 'mirakl_key_callback'), 	// The name of the function responsible for rendering the option interface
			'mirakl_connect_display_options',			// The page on which this option will be displayed
			'general_settings_section'					 // The name of the section to which this field belongs
		);
		
		
		// Finally, we register the fields with WordPress
		register_setting(
			'mirakl_connect_display_options',
			'mirakl_connect_display_options',
			array( $this, 'validate_input_examples')
		);

	} // end wppb-demo_initialize_theme_options





	public function mirakl_url_callback() {

		$options = get_option( 'mirakl_connect_display_options' );

		// Render the output
		echo '<input type="text" name="mirakl_connect_display_options[mirakl_url]" value="' . $options['mirakl_url'] . '" />';

	} // end input_element_callback	
	
	public function mirakl_key_callback() {

		$options = get_option( 'mirakl_connect_display_options' );

		// Render the output
		echo '<input type="text"  name="mirakl_connect_display_options[mirakl_key]" value="' . $options['mirakl_key'] . '" />';

	} // end input_element_callback



	public function validate_input_examples( $input ) {

		// Create our array for storing the validated options
		$output = array();

		// Loop through each of the incoming options
		foreach( $input as $key => $value ) {

			// Check to see if the current option has a value. If so, process it.
			if( isset( $input[$key] ) ) {

				// Strip all HTML and PHP tags and properly handle quoted strings
				$output[$key] = strip_tags( stripslashes( $input[ $key ] ) );

			} // end if

		} // end foreach

		// Return the array processing any additional functions filtered by this action
		return apply_filters( 'validate_input_examples', $output, $input );

	} // end validate_input_examples




}