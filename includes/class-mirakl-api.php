<?php

class Mirakl_API {

	private string $mirakl_url;
	private string $mirakl_key;

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
	 * Hooked to product stock reduced after order
	 * @param $order
	 * Calls:   products2json to create JSON from products
	 *          send2mirakl to send JSON to Mirakl
	 */
    public function mirakl_sync_sold($order) {
	    if ($order->get_created_via()=='mirakl') return;
	    foreach( $order->get_items() as $item_id => $line_item ) {
		    $item_data = $line_item->get_data();
		    $product   = $line_item->get_product();
		    $items[]=$product;
	    }
	    $json_products = self::products2json($items);
	    $result=$this->send2mirakl($json_products);
	    if (is_numeric($result)) $order->add_order_note( 'Το Public ενημερώθηκε για τη μεταβολή του αποθέματος των προϊόντων' );
	    else $order->add_order_note( $result . '. - Απέτυχε η ενημέρωση του Public για μεταβολή του αποθέματος των προϊόντων' );
//	    self::write_log($result);
    }

	/**
	 * Hooked to product update - checks product changes and sends update to Mirakl
	 * @param $product_id
	 * @param $product
	 * @param $changes
	 * Calls:   products2json to create JSON
	 *          send2mirakl to send JSON to Mirakl
	 */
	public function mirakl_update_product( $product_id, $product, $changes ) {
		// If there was NO change in price, stock, etc then RETURN
		if ( ! array_intersect( array( 'stock_quantity', 'sale_price', 'price', 'tax_class', 'stock_status', 'shipping_class_id', 'regular_price' ), array_keys( $changes ) ) )
			return;
		$items[]=$product;
		$json_products= self::products2json($items);
		$this->send2mirakl($json_products);
	}


	/**
	 *  OF24 - Create, update, or delete offers
	 *  Sends to Mirakl API the offers JSON to update/create offers
	 *  Called by hooked functions:  mirakl_update_product + mirakl_sync_sold
	 * @param $json_offers
	 *
	 * @return string
	 */
	private function send2mirakl($json_offers): string {
		try {
			$curl = curl_init();
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $this->mirakl_url . '/api/offers',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => $json_offers,

				CURLOPT_HTTPHEADER => array(
					'Authorization: ' . $this->mirakl_key,
					'Accept: application/json',
					'Content-Type: application/json',
					'charset=utf-8'
				),
			) );

			$response = curl_exec( $curl );
			curl_close( $curl );

			self::write_log( $json_offers );
			self::write_log( $response );
			$ans_array = json_decode( $response, true );
		}
		catch(Exception $e){
			$ans_array['import_id']=$e->getMessage();
		}
		if(is_null($ans_array['import_id'])) return $ans_array['message'];
		else return $ans_array['import_id'];
	}


	/**
	 * Maps Woocommerce Vat class to Mirakl Vat categories
	 * @param $data
	 *
	 * @return string
	 */
	private static function map_vat($data): string {
		$map = array(
			'standard' => '24',
			'parent' => '24',
			'reduced-rate' => '6',
		);
		return $map[ $data ] ?? '24';
	}

	/**
	 * Maps Woocommerce Shipping CLass to Mirakl logistic categories
	 * @param $data
	 *
	 * @return string
	 */
	private static function map_ship( $data ): string {
		$map = array(
			'Μειωμένα μεταφορικά'	=> 'small',
			'Τυπική' 	=> 'small',
			'Μεσαίο' 	=> 'medium',
			'Μεγάλο'		=> 'large',
			'Ογκώδες'		=> 'extra-large',
			'Έξτρα ογκώδες'	=> 'oversized',
			'Δωρεάν μεταφορικά' => 'free',
		);
		return $map[ $data ] ?? 'small';
	}

	/**
	 * For the given array of products creates a JSON string according to mirakl
	 * @param $items
	 *
	 * @return string
	 */
	private static function products2json($items): string {
		foreach ($items as $product) {
			//prepare product details
			$offer_description = $product->get_name();
			$logistic_class    = self::map_ship( $product->get_shipping_class() );
			$ean               = strval( $product->get_attribute( "EAN" ) );
			$vat_class         = self::map_vat( $product->get_tax_class() );
			$price             = $product->get_price();
			$regular_price     = $product->get_regular_price();
			$sale_price        = empty( $product->get_sale_price() ) ? null : $product->get_sale_price();
			$stock             = strval( $product->get_stock_quantity() );
			$sku               = strval( $product->get_sku() );

			$array_offer[ "offers" ][] = [
				"allow_quote_requests"    => false,
				"description"             => $offer_description,
				"internal_description"    => $offer_description,
				"leadtime_to_ship"        => 3,
				"logistic_class"          => $logistic_class,
				"offer_additional_fields" => [
					[ "code" => "offervat", "type" => "LIST", "value" => $vat_class ],
					[ "code" => "shippingvat", "type" => "LIST", "value" => "24" ]
				],
				"price"                   => $regular_price,
				"all_prices"              => [
					[
						"channel_code"        => null,
						"discount_end_date"   => null,
						"discount_start_date" => null,
						"price"               => $price,
						"unit_discount_price" => $sale_price,
						"unit_origin_price"   => $regular_price
					]
				],
				"product_id"              => $ean,
				"product_id_type"         => "EAN",
				"quantity"                => $stock,
				"shop_sku"                => $sku,
				"state_code"              => "11",
				"update_delete"           => "update"
			];
		}
		return json_encode($array_offer, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Saves JSON string to log file
	 * @param $json
	 */
	private static function write_log( $json ) {
		$txt = $json;

// open file and write hook message
		$mylasthook=fopen(plugin_dir_path( dirname( __FILE__ ) )  ."/last-hook.txt", "w") or die("Unable to open file!");
		fwrite($mylasthook,$txt);
		fclose($mylasthook);

//open log file to append
		$mylogfile = fopen(plugin_dir_path( dirname( __FILE__ ) )  ."/logfile.txt", "a") or die("Unable to open file!");

		$rec= date('d/m/Y h:i:s a', time());
		$rec.=' ' .$txt;
		$rec.="\n";
		fwrite($mylogfile,$rec);
		fclose($mylogfile);
	}

}