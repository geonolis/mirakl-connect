<?php

/**
 * Fired during plugin activation
 *
 * @link       www.gipapamanolis.gr
 * @since      1.0.0
 *
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/includes
 * @author     GeoNolis <info@gipapamanolis.gr>
 */
class Mirakl_Connect_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

	    //Add cron schedules filter with defined schedule.
	    //	    echo 'ready to activate';
//	    add_filter( 'cron_schedules', 'mirakl_connect_schedules' );
	    //	    echo 'add filter';
	    
	    if ( ! wp_get_schedule( 'mirakl_connect_cron_hook' ) ) {
//		    echo '<div class="notice notice-error">
//        <p>Warning notice! actibate</p>
//    </div>';
	        wp_schedule_event( time(), 'hourly', 'mirakl_connect_cron_hook' );
	    }
	}



	/**
	 * Add a custom schedule to wp.
	 * @param $schedules array The  existing schedules
	 *
	 * @return mixed The existing + new schedules.
	 */
	static function mirakl_connect_schedules( $schedules ) {
	    if ( ! isset( $schedules["10m"] ) ) {
	        $schedules["10m"] = array(
	            'interval' => 600,
	            'display'  => __( 'Once every 10 minutes' )
	        );
	    }
	    return $schedules;
	}
}
