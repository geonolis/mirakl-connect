<?php

/**
 * Fired during plugin deactivation
 *
 * @link       www.gipapamanolis.gr
 * @since      1.0.0
 *
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/includes
 * @author     GeoNolis <info@gipapamanolis.gr>
 */
class Mirakl_Connect_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
	    wp_clear_scheduled_hook('mirakl_connect_cron_hook');
	    
	}

}
