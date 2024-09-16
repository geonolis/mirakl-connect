<?php

/**
 * The importer.
 *
 * @link       www.gipapamanolis.gr
 * @since      1.0.0
 *
 * @package    Mirakl_Connect
 * @subpackage Mirakl_Connect/admin/partials
 */

/**
 * Class WordPress_Plugin_Template_Settings
 * 18-10
 */
class Mirakl_Connect_Import_Order {

	private string $mirakl_url;
	private string $mirakl_key;
	private const _WAITING_ACCEPTANCE = "WAITING_ACCEPTANCE";
	private const _SHIPPING = "SHIPPING";

	/**
	 * Class_Mirakl_Update_Offers constructor.
	 */
	public function __construct() {
		$mirakl_connect_options = get_option( 'mirakl_connect_display_options' ); // Array of All Options
		if(isset($mirakl_connect_options['mirakl_url'])) {
			$this->mirakl_url = $mirakl_connect_options['mirakl_url']; // Mirakl URL
			$this->mirakl_key = $mirakl_connect_options['mirakl_key']; // key
		} else {
			$this->mirakl_url = "https://";
			$this->mirakl_key="xxx";
		}
	}

	/**
	 * Hooked on Admin page load (admin_enqueue_scripts)
	 *
	 * @param $page
	 * If page is orders table -> looks for new orders Waiting Acceptance
	 * If page is edit order   -> looks for mirakl order details
	 *
	 * @throws WC_Data_Exception
	 */
	public function on_admin_orders_page_load( $page ) {
		$current_url = $_SERVER['REQUEST_URI'];
		// Check if the current page is the WooCommerce Orders page
		if ( $page == 'woocommerce_page_wc-orders' && ! str_contains( $current_url, 'edit' ) ) {
			$this->import_orders_from_mirakl();
		} elseif ( str_contains( $current_url, 'page=wc-orders&action=edit&id=' ) ) {
			$order_id = (int) explode( '=', explode( '&', $current_url )[2] )[1];
			$this->get_order_details_from_mirakl( $order_id );
		}
	}

	/**
	 * GET OR11 - List orders
	 * Hooked at mirakl_connect_cron_hook
	 * Called from on_admin_orders_page_load
	 * @throws WC_Data_Exception
	 */
	public function import_orders_from_mirakl() {
		// reads all mirakl orders that are WAITING_ACCEPTANCE
		$response = $this->get_mirakl_orders( self::_WAITING_ACCEPTANCE );
		self::write_log(json_encode($response, JSON_UNESCAPED_UNICODE));
		if ( isset($response->error_message) || (isset($response->status))) {//&& $response->status==400)) {
			echo '<div class="notice notice-error">
        <p>Αδυναμία επικοινωνίας με Public</p>
    	</div>';
			return;
		} elseif ($response->total_count == 0 ){
			return;
		}

		echo '<div class="notice notice-error">
        <p>Παραγγελίες σε αναμονή αποδοχής από Public Marketplace: ' . $response->total_count . ' </p>
    	</div>';

		foreach ( $response->orders as $mirakl_order ) {
			// check mirakl order with same transaction id does not exist in woo
			$new_order = empty( wc_get_orders( array(
				'transaction_id' => $mirakl_order->order_id,
				'created_via'    => 'mirakl',
			) ) );

			if ( $new_order ) {
				$order = new WC_Order();
				$order->set_transaction_id( $mirakl_order->order_id );
				$order->set_created_via( 'mirakl' );
				// set address
				$address = array(
					'first_name' => $mirakl_order->customer->firstname,
					'last_name'  => $mirakl_order->customer->lastname,
//			        'email'      => $mirakl_order->customer_notification_email,
					'country'    => 'GR'
				);
				$order->set_address( $address, 'billing' );
//			    $order->set_customer_id( $data['user_id'] );
				$order->set_currency( get_woocommerce_currency() );
				$order->set_payment_method( 'cheque' );

				// Line items
				foreach ( $mirakl_order->order_lines as $line_item ) {
					$args          = [
						'subtotal' => $line_item->price_unit * 1,
						24,
						// e.g. 32.95
						'total'    => $line_item->total_price + $line_item->taxes->amount - $line_item->shipping_price - $line_item->shipping_taxes->amount,
						// e.g. 32.95
					];
					$product       = wc_get_product( wc_get_product_id_by_sku( $line_item->offer_sku ) );
					$order_item_id = $order->add_product( $product, $line_item->quantity, $args );
					// store order line id for each product - we need to approve the order later
					wc_add_order_item_meta( $order_item_id, '_mirakl_line_num', $line_item->order_line_id, true );
				} //foreach

				$calculate_taxes_for = array(
					'country' => 'GR'
				);

				// Get a new instance of the WC_Order_Item_Shipping Object
				$fees = new WC_Order_Item_Shipping();
				$fees->set_method_title( "Αποστολή με Courier" );
				$fees->set_method_id( "flat_rate" ); // set an existing Shipping method rate ID
				$fees->set_total( $mirakl_order->shipping_price );
				$fees->calculate_taxes( $calculate_taxes_for );
				$order->add_item( $fees );

				// Set calculated totals
				$order->calculate_totals();

				// Set Public Attribution
				$meta = array(
					'_wc_order_attribution_origin'             => 'Public.gr',
					'_wc_order_attribution_utm_content'        => '/',
					'_wc_order_attribution_utm_medium'         => 'referral',
					'_wc_order_attribution_utm_source'         => 'Public.gr',
					'_wc_order_attribution_referrer'           => 'https://www.public.gr/',
					'_wc_order_attribution_source_type'        => 'referral',
				);

				foreach ( $meta as $key => $value ) {
					$order->add_meta_data( $key, $value );
				}

				// iterate mirakl order_additional_fields to see if needs invoice and get AFM
				foreach ($mirakl_order->order_additional_fields as $mirakl_additional_field) {
//					if($mirakl_additional_field->code=='billing_type')
//						$order_needs_invoice=($mirakl_additional_fields->value=='invoice');
					if($mirakl_additional_field->code=='taxcode') {
						$afm = $mirakl_additional_field->value;
						$order->update_meta_data( 'afm', $afm );
					}
				}

				// Update order status from pending to your defined status
				$order->update_status( 'on-hold' );
				$order->add_order_note( 'E-mail πελάτη: ' . $mirakl_order->customer_notification_email );

			} //if
		} // for each
	} //  import_orders_from_mirakl()

	function show_afm_field_admin_order_meta( $order ){
		if ( $order->meta_exists( 'afm' ))
		echo '<p style="color:red;"><strong>Προσοχή, θέλει τιμολόγιο!<br>' . esc_html__( 'ΑΦΜ' ) . ':</strong> ' . esc_html( $order->get_meta( 'afm', true ) ) . '</p>';
	}

	/**
	 * Gets order shipping details from mirakl after order acceptance
	 * Called from on_admin_orders_page_load
	 *
	 * @param $order_id
	 */
	public function get_order_details_from_mirakl( $order_id ) {
		$woo_order = wc_get_order( $order_id );
		if (!$woo_order->get_created_via() == 'mirakl'){
			return;
		}
		elseif ($woo_order->get_status()=='on-hold') {
			echo '<div class="notice notice-error">
        		<p>Τα στοιχεία αποστολής θα ενημερωθούν μετά την αποδοχή της παραγγελίας.</p>
    			</div>';
			return;
		}
		elseif ( !is_empty( $woo_order->get_shipping_postcode() )) {
			return;
		}

//		$start_update_date =  date("c",strtotime( $woo_order->get_date_created()));
//		$start_update_date =  $woo_order->get_date_created();
		// reads all mirakl orders that are SHIPPING

		$response = $this->get_mirakl_orders( self::_SHIPPING );
		if (isset($response->error_message) || (isset($response->status) && $response->status==400)) {
			echo '<div class="notice notice-error">
        		<p>Σφάλμα στην επικοινωνία με Public. Δοκιμάστε αργότερα.</p>
    			</div>';
			return;
		}
		elseif ( $response->total_count == 0 ) {
			echo '<div class="notice notice-error">
        		<p>Τα στοιχεία αποστολής δεν έχουν ακόμα ενημερωθεί. Δοκιμάστε αργότερα.</p>
    			</div>';
			return;
		}
		// check mirakl order with same transaction id does not exist in woo
		foreach ( $response->orders as $mirakl_order ) {
			// get mirakl order with same transaction id
			if ( $mirakl_order->order_id == $woo_order->get_transaction_id() ) {
				$mirakl_customer        = $mirakl_order->customer;
				$mirakl_billing_address = $mirakl_order->customer->billing_address;
				$woo_billing_address    = array(
					'first_name' => $mirakl_customer->firstname,
					'last_name'  => $mirakl_customer->lastname,
					'company'    => '',
					'email'      => '',
					'phone'      => $mirakl_billing_address->phone,
					'address_1'  => $mirakl_billing_address->street_1,
					'address_2'  => $mirakl_billing_address->street_2,
					'city'       => $mirakl_billing_address->city,
					'state'      => null,
					'postcode'   => $mirakl_billing_address->zip_code,
					'country'    => 'GR'
				);
				$woo_order->set_billing_address( $woo_billing_address );
				$mirakl_shipping_address = $mirakl_order->customer->shipping_address;
				$woo_shipping_address    = array(
					'first_name' => $mirakl_customer->firstname,
					'last_name'  => $mirakl_customer->lastname,
					'company'    => '',
//						'email'      => '',
					'phone'      => $mirakl_shipping_address->phone,
					'address_1'  => $mirakl_shipping_address->street_1,
					'address_2'  => $mirakl_shipping_address->street_2,
					'city'       => $mirakl_shipping_address->city,
					'state'      => null,
					'postcode'   => $mirakl_shipping_address->zip_code,
					'country'    => 'GR'
				);
				self::write_log( json_encode( $woo_shipping_address, JSON_UNESCAPED_UNICODE ) );
				$woo_order->set_shipping_address( $woo_shipping_address );
				$woo_order->save();
				return;
			} //if
		}
	}

	/**
	 * Sends message to mirakl about order acceptance
	 * Hooked at woocommerce_order_status_processing
	 *
	 * @param $order_id
	 */
	function mirakl_notify_accept_order( $order_id ) {
		// get order from order id
		$order = wc_get_order( $order_id );
		// if order was created via mirakl then...
		if ( $order->get_created_via() == 'mirakl' ) {
			$this->mirakl_notify_order( true, $order );
		}
		// do nothing else
	} // function mirakl_notify_accept_order()

	/**
	 * Notify MIRAKL order rejected.
	 * Hooked on order status changed to CANCELLED.
	 * @since    1.0.0
	 */
	public function mirakl_notify_reject_order( $order_id ) {
		/**
		 * ?> <script>alert("VAE VICTIS!");</script> <?php
		 */
		// get order from order id
		$order = wc_get_order( $order_id );
		// if order was created via mirakl then...
		if ( $order->get_created_via() == 'mirakl' ) {
			self::mirakl_notify_order( false, $order );
		}
		// do nothing else
	} // function mirakl_notify_reject_order()

	/**
	 * Sends to mirakl shipping details
	 * Hooked at woocommerce_order_status_completed
	 *
	 * @param $order_id
	 */
	function mirakl_notify_ship_order( $order_id ) {
		// get order from order id
		$order = wc_get_order( $order_id );

		// if order was created via mirakl then...
		if ( $order->get_created_via() == 'mirakl' ) {
			$mirakl_order_id = $order->get_transaction_id();
			// get voucher number from order meta data
			$tracking_nr = $order->get_meta( 'courier_voucher' );
			// prepare post field with tracking data
			$post_fields = "{ \"carrier_code\": \"Geniki Taxydromiki\",  \"carrier_name\": 
                \"Geniki Taxydromiki\", \"carrier_url\": \"https://www.taxydromiki.com/track/" . $tracking_nr . "\", \"tracking_number\": " . $tracking_nr . "} ";
			$url_options = $mirakl_order_id . "/tracking";

			// OR23 - Update courier tracking info
			$response=$this->mirakl_curl( $url_options, $post_fields );
			/*
			// init CURL
			$curl = curl_init();
			// prepair API array to register VOUCHER NR
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $this->mirakl_url . "/api/orders/" . $mirakl_order_id . "/tracking",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => "",
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => "PUT",
				CURLOPT_POSTFIELDS     => $post_fields,
				CURLOPT_HTTPHEADER     => array(
					"Authorization: " . $this->mirakl_key,
					"Content-Type: application/json"
				),
			) );
			$response = curl_exec( $curl );
			// store mirakl api response to order notes
			*/
			$order->add_order_note( $response );

			$url_options= $mirakl_order_id . "/ship";
			$post_fields='{}';
			$response=$this->mirakl_curl($url_options, $post_fields);

			/*
			// prepair API array to confirm shipping
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $this->mirakl_url . "/api/orders/" . $mirakl_order_id . "/ship",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => "",
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => "PUT",
				CURLOPT_HTTPHEADER     => array(
					"Authorization: " . $this->mirakl_key
				),
			) );
			$response = curl_exec( $curl );
			curl_close( $curl );
			// store mirakl api response to order notes
			*/
			$order->add_order_note( $response );
		} // if
	} // function notify shiped

	/**
	 * @param $url_options
	 * @param $post_fields
	 */
	private function mirakl_curl( $url_options, $post_fields = null) {
		try{
		// init CURL
		$curl = curl_init();
		// prepare API array
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $this->mirakl_url . "/api/orders/" . $url_options,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => ( $post_fields == null ) ? "GET" : "PUT",
			CURLOPT_POSTFIELDS     => $post_fields,
			CURLOPT_HTTPHEADER     => array(
				"Authorization: " . $this->mirakl_key,
				"Content-Type: application/json"
			),
		) );
		$response = json_decode(curl_exec( $curl ));
		curl_close( $curl );
		}
		catch(Exception $e){
			$response=array("error_message"=>$e->getMessage());
			self::write_log($e->getMessage());
		}
		return $response;
	}

	/**
	 * Notify MIRAKL order accepted or not.
	 * Sends the actual message to MIRAKL to reject or accept an order
	 * Called by the 2 hooked functions mirakl_notify... .
	 *
	 * @param boolean $accept true on accept / false on reject.
	 * @param object $order Woo order object.
	 *
	 * @since    1.0.0
	 */
	private function mirakl_notify_order( bool $accept, object $order ) {
		$true_false = $accept ? "true" : "false";
		// prepare post fields as JSON array
		$post_fields = '{ "order_lines": [ ';
		// iterate woo order items to get mirakl order line id from custom meta
		$item_lines=count($order->get_items());
		foreach ( $order->get_items() as $item_id => $item ):
			$custom_field = wc_get_order_item_meta( $item_id, '_mirakl_line_num', true );
			$post_fields  .= '{
                    "accepted": "' . $true_false . '",
                    "id":"' . $custom_field . '" }';
			if($item_lines>1) $post_fields  .= ',';
			$item_lines--;
		endforeach;
		// close post fields array
		$post_fields .= "] }";
		// get mirakl order id from woo
		$mirakl_order_id = $order->get_transaction_id();
		$order->add_order_note( $post_fields ); //test
		$response=$this->mirakl_curl( $mirakl_order_id . "/accept", $post_fields);
/*
		// init CURL
		$curl = curl_init();
		// prepair API array
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $this->mirakl_url . "/api/orders/" . $mirakl_order_id . "/accept",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "PUT",
			CURLOPT_POSTFIELDS     => $post_fields,
			CURLOPT_HTTPHEADER     => array(
				"Authorization: " . $this->mirakl_key,
				"Content-Type: application/json"
			),
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );
*/
		// store mirakl api response to order notes
		$order->add_order_note( json_encode($response), JSON_UNESCAPED_UNICODE );
	} // function notify_accept_order()

	/**
	 * function get_mirakl_orders()
	 *
	 *
	 */
	private function get_mirakl_orders( $status, $start_update_date = "2024-08-24T16:47:35Z" ) {
		$url_options = "?order_state_codes=" . $status . "&start_update_date=" . $start_update_date;
		/*
		$curl    = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $this->mirakl_url ."/api/orders". $url_options,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "GET",
			CURLOPT_HTTPHEADER     => array(
				"Authorization: " . $this->mirakl_key,
				"Accept: application/json"
			),
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );
*/
		return $this->mirakl_curl($url_options, null);
	}

	public function mirakl_order_link($order){
		if ($order->get_created_via() == 'mirakl'){
			$order_url=$this->mirakl_url . 'mmp/shop/order/' .  $order->get_transaction_id();
			echo '<p class="woocommerce-order-data__meta order_number">
			<a href="'. $order_url .'" target="_blank">Public Order Detais</a></p>';
		}

	}

	/**
	 * Saves JSON string to log file
	 *
	 * @param $json
	 */
	private static function write_log( $json ) {
		$txt = $json;

// open file and write hook message
		$mylasthook = fopen( plugin_dir_path( dirname( __FILE__ ) ) . "/last-hook.txt", "w" ) or die( "Unable to open file!" );
		fwrite( $mylasthook, $txt );
		fclose( $mylasthook );

//open log file to append
		$mylogfile = fopen( plugin_dir_path( dirname( __FILE__ ) ) . "/logfile.txt", "a" ) or die( "Unable to open file!" );

		$rec = date( 'd/m/Y h:i:s a', time() );
		$rec .= ' ' . $txt;
		$rec .= "\n";
		fwrite( $mylogfile, $rec );
		fclose( $mylogfile );
	}


	/**
	 * function create_wc_order( $data ){
	 * $gateways = WC()->payment_gateways->get_available_payment_gateways();
	 * $order    = new WC_Order();
	 *
	 * // Set Billing and Shipping adresses
	 * foreach( array('billing_', 'shipping_') as $type ) {
	 * foreach ( $data['address'] as $key => $value ) {
	 * if( $type === 'shipping_' && in_array( $key, array( 'email', 'phone' ) ) )
	 * continue;
	 *
	 * $type_key = $type.$key;
	 *
	 * if ( is_callable( array( $order, "set_{$type_key}" ) ) ) {
	 * $order->{"set_{$type_key}"}( $value );
	 * }
	 * }
	 * }
	 *
	 * // Set other details
	 * $order->set_created_via( 'mirakl' );
	 * $order->set_customer_id( $data['user_id'] );
	 * $order->set_currency( get_woocommerce_currency() );
	 * $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
	 * $order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
	 * $order->set_payment_method( isset( $gateways[ $data['payment_method'] ] ) ? $gateways[ $data['payment_method'] ] : $data['payment_method'] );
	 *
	 * // Line items
	 * foreach( $data['line_items'] as $line_item ) {
	 * $args = $line_item['args'];
	 * $product = wc_get_product( isset($args['variation_id']) && $args['variation_id'] > 0 ? $$args['variation_id'] : $args['product_id'] );
	 * $order->add_product( $product, $line_item['quantity'], $line_item['args'] );
	 * }
	 *
	 * $calculate_taxes_for = array(
	 * 'country'  => $data['address']['country'],
	 * 'state'    => $data['address']['state'],
	 * 'postcode' => $data['address']['postcode'],
	 * 'city'     => $data['address']['city']
	 * );
	 *
	 * // Coupon items
	 * if( isset($data['coupon_items'])){
	 * foreach( $data['coupon_items'] as $coupon_item ) {
	 * $order->apply_coupon(sanitize_title($coupon_item['code']));
	 * }
	 * }
	 *
	 * // Fee items
	 * if( isset($data['fee_items'])){
	 * foreach( $data['fee_items'] as $fee_item ) {
	 * $item = new WC_Order_Item_Fee();
	 *
	 * $item->set_name( $fee_item['name'] );
	 * $item->set_total( $fee_item['total'] );
	 * $tax_class = isset($fee_item['tax_class']) && $fee_item['tax_class'] != 0 ? $fee_item['tax_class'] : 0;
	 * $item->set_tax_class( $tax_class ); // O if not taxable
	 *
	 * $item->calculate_taxes($calculate_taxes_for);
	 *
	 * $item->save();
	 * $order->add_item( $item );
	 * }
	 * }
	 *
	 * // Set calculated totals
	 * $order->calculate_totals();
	 *
	 * // Save order to database (returns the order ID)
	 * $order_id = $order->save();
	 *
	 * // Update order status from pending to your defined status
	 * if( isset($data['order_status']) ) {
	 * $order->update_status($data['order_status']['status'], $data['order_status']['note']);
	 * }
	 *
	 * // Returns the order ID
	 * return $order_id;
	 * } //function create_wc_order()
	 */
} // class