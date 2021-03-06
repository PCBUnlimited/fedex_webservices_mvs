<?php
/*
  FedEx Webservice Version 9.4.2 for osCommerce 2.2rc2a and later(?) -by Roaddoctor 5/20/2012
  New contributed code and the hard work credit to Jeff Lew. Thanks Jeff and Numinex!
  
  Support: http://forums.oscommerce.com/topic/375063-fedex-web-services-v9/page__view__findpost__p__1636568
  
*/
class fedexwebservices {
 var $code, $title, $description, $icon, $sort_order, $enabled, $tax_class, $fedex_key, $fedex_pwd, $fedex_act_num, $fedex_meter_num, $country;

//Class Constructor
  function fedexwebservices() {
    global $order, $customer_id;

    @define('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INSURE', 0);
    $this->code             = "fedexwebservices";
    $this->title            = MODULE_SHIPPING_FEDEX_WEB_SERVICES_TEXT_TITLE;
    $this->description      = MODULE_SHIPPING_FEDEX_WEB_SERVICES_TEXT_DESCRIPTION;
    // $this->sort_order       = constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_SORT_ORDER_' . $vendors_id);
    // $this->handling_fee     = constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_HANDLING_FEE_' . $vendors_id);
    $this->icon       = DIR_WS_ICONS . 'shipping_fedex.gif';
    $this->enabled = ((defined('MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATUS_' . $vendors_id) and constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATUS_' . $vendors_id) == 'true') ? true : false);

    if (defined("SHIPPING_ORIGIN_COUNTRY")) {
      if ((int)SHIPPING_ORIGIN_COUNTRY > 0) {
        $countries_array = $this->get_countries(SHIPPING_ORIGIN_COUNTRY, true);
        $this->country = $countries_array['countries_iso_code_2'];
      } else {
        $this->country = SHIPPING_ORIGIN_COUNTRY;
      }
    } else {
      $this->country = STORE_ORIGIN_COUNTRY;
    }
    if ( ($this->enabled == true) && ((int) constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id) > 0) ) {
      $check_flag = false;
      $check_query = tep_db_query ("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id) . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
      while( $check = tep_db_fetch_array($check_query)) {
        if ($check ['zone_id'] < 1) {
          $check_flag = true;
          break;
        } elseif ($check ['zone_id'] == $order->delivery['zone_id']) {
          $check_flag = true;
          break;
        }
        $check->MoveNext();
      }

      if ($check_flag == false) {
        $this->enabled = false;
      }
    }
  }
  function zones($vendors_id = '1') {
    if ( ($this->enabled == true) && ((int) constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id) > 0) ) {
      $check_flag = false;
      $check_query = tep_db_query ("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id) . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
      while( $check = tep_db_fetch_array($check_query)) {
        if ($check ['zone_id'] < 1) {
          $check_flag = true;
          break;
        } elseif ($check ['zone_id'] == $order->delivery['zone_id']) {
          $check_flag = true;
          break;
        }
        $check->MoveNext();
      }

      if ($check_flag == false) {
        $this->enabled = false;
      }
    }
    return $this->enabled;
  }
  function enabled($vendors_id = '1') {
    return true;
    $this->enabled = false;
    $status = @constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATUS_' . $vendors_id);
    if (isset ($status) && $status != '') {
      $this->enabled = (($status == 'True') ? true : false);
    }
    if ( ($this->enabled == true) && ((int)constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id) > 0) ) {
      $check_flag = false;
      $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . (int)constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id) . "' and zone_country_id = '" . $this->delivery_country_id . "' order by zone_id");
      while ($check = tep_db_fetch_array($check_query)) {
        if ($check['zone_id'] < 1) {
          $check_flag = true;
          break;
        } elseif ($check['zone_id'] == $this->delivery_zone_id) {
          $check_flag = true;
          break;
        }
      }

      if ($check_flag == false) {
        $this->enabled = false;
      }//if
    }//if

    return $this->enabled;
  }

  //Class Methods

  function quote($method = '', $module = '', $vendors_id = '1') {
    /* FedEx integration starts */
    global $shipping_weight, $shipping_num_boxes, $cart, $order;

    require_once(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'library/fedex-common.php5');
    //if (MODULE_SHIPPING_FEDEX_WEB_SERVICES_SERVER == 'test') {
      //$request['Version'] = array('ServiceId' => 'crs', 'Major' => '7', 'Intermediate' => '0', 'Minor' => '0');
      //$path_to_wsdl = DIR_WS_INCLUDES . "wsdl/RateService_v7_test.wsdl";
    //} else {
    $path_to_wsdl = DIR_FS_CATALOG . DIR_WS_INCLUDES . "wsdl/RateService_v10.wsdl";

    $this->tax_class        = defined('MODULE_SHIPPING_FEDEX_WEB_SERVICES_TAX_CLASS_' . $vendors_id)? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_TAX_CLASS_' . $vendors_id) : null;
    $this->fedex_key        = defined('MODULE_SHIPPING_FEDEX_WEB_SERVICES_KEY_' . $vendors_id)? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_KEY_' . $vendors_id) : null;
    $this->fedex_pwd        = defined('MODULE_SHIPPING_FEDEX_WEB_SERVICES_PWD_' . $vendors_id)? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_PWD_' . $vendors_id) : null;
    $this->fedex_act_num    = defined('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ACT_NUM_' . $vendors_id)? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ACT_NUM_' . $vendors_id): null;
    $this->fedex_meter_num  = defined('MODULE_SHIPPING_FEDEX_WEB_SERVICES_METER_NUM_' . $vendors_id)? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_METER_NUM_' . $vendors_id) : null;

    //}
    ini_set("soap.wsdl_cache_enabled", "0");
    $client = new SoapClient($path_to_wsdl, array('trace' => 1));
    $this->types = array();
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_PRIORITY_' . $vendors_id) == 'true') {
      $this->types['INTERNATIONAL_PRIORITY'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['EUROPE_FIRST_INTERNATIONAL_PRIORITY'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_ECONOMY_' . $vendors_id) == 'true') {
      $this->types['INTERNATIONAL_ECONOMY'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }  
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_STANDARD_OVERNIGHT_' . $vendors_id) == 'true') {
      $this->types['STANDARD_OVERNIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_FIRST_OVERNIGHT_' . $vendors_id) == 'true') {
      $this->types['FIRST_OVERNIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_PRIORITY_OVERNIGHT_' . $vendors_id) == 'true') {
      $this->types['PRIORITY_OVERNIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_2DAY_' . $vendors_id) == 'true') {
      $this->types['FEDEX_2_DAY'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }
    // because FEDEX_GROUND also is returned for Canadian Addresses, we need to check if the country matches the store country and whether international ground is enabled
    if ((constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_GROUND_' . $vendors_id) == 'true' && $order->delivery['country']['id'] == STORE_COUNTRY) || (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_GROUND_' . $vendors_id) == 'true' && ($order->delivery['country']['id'] != STORE_COUNTRY) && constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_GROUND_' . $vendors_id) == 'true')) {
      $this->types['FEDEX_GROUND'] = array('icon' => '', 'handling_fee' => ($order->delivery['country']['id'] == STORE_COUNTRY ? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_HANDLING_FEE_' . $vendors_id) : constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_HANDLING_FEE_' . $vendors_id)));
      $this->types['GROUND_HOME_DELIVERY'] = array('icon' => '', 'handling_fee' => ($order->delivery['country']['id'] == STORE_COUNTRY ? constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_HOME_DELIVERY_HANDLING_FEE_' . $vendors_id) : constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_HANDLING_FEE_' . $vendors_id)));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_GROUND_' . $vendors_id) == 'true') {
      $this->types['INTERNATIONAL_GROUND'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_HANDLING_FEE_' . $vendors_id));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_SAVER_' . $vendors_id) == 'true') {
      $this->types['FEDEX_EXPRESS_SAVER'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_FREIGHT_' . $vendors_id) == 'true') {
      $this->types['FEDEX_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['FEDEX_NATIONAL_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['FEDEX_1_DAY_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['FEDEX_2_DAY_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['FEDEX_3_DAY_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['INTERNATIONAL_ECONOMY_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_' . $vendors_id));
      $this->types['INTERNATIONAL_PRIORITY_FREIGHT'] = array('icon' => '', 'handling_fee' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_' . $vendors_id));
    }

//     $this->types[] = 'SMART_POST';

    // customer details
    $street_address = $order->delivery['street_address'];
    $street_address2 = $order->delivery['suburb'];
    $city = $order->delivery['city'];
    $state = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], '');
    if ($state == "QC") $state = "PQ";
    $postcode = str_replace(array(' ', '-'), '', $order->delivery['postcode']);
    $country_id = $order->delivery['country']['iso_code_2'];

    $totals = $order->info['subtotal'] = $_SESSION['cart']->show_total();
    $this->_setInsuranceValue($totals, $vendors_id);

    $request['WebAuthenticationDetail'] = array(
      'UserCredential' => array(
        'Key' => $this->fedex_key,
        'Password' => $this->fedex_pwd
      )
    );
    $request['ClientDetail'] = array(
      'AccountNumber' => $this->fedex_act_num,
      'MeterNumber' => $this->fedex_meter_num
    );
    $request['TransactionDetail'] = array('CustomerTransactionId' => ' *** Rate Request v10 using PHP ***');
//    $request['RequestedShipment']['SmartPostDetail'] = array(
//        'Indicia' => 'MEDIA_MAIL',
//        'AncillaryEndorsement' => 'CARRIER_LEAVE_IF_NO_RESPONSE',
//        'SpecialServices' => 'USPS_DELIVERY_CONFIRMATION',
//        'HubId' => '5254',
//        'CustomerManifestId' => 1101);
//    $request['RequestedShipment']['ServiceType'] = 'SMART_POST';

    $request['Version'] = array(
      'ServiceId' => 'crs',
      'Major' => '10',
      'Intermediate' => '0',
      'Minor' => '0'
    );
    $request['ReturnTransitAndCommit'] = true;
    $request['RequestedShipment']['DropoffType'] = $this->_setDropOff($vendors_id); // valid values REGULAR_PICKUP, REQUEST_COURIER, ...
    $request['RequestedShipment']['ShipTimestamp'] = date('c');
    $request['RequestedShipment']['PackagingType'] = 'YOUR_PACKAGING'; // valid values FEDEX_BOX, FEDEX_PAK, FEDEX_TUBE, YOUR_PACKAGING, ...
    $request['RequestedShipment']['TotalInsuredValue']=array('Ammount'=> $this->insurance, 'Currency' => $_SESSION['currency']);

  // print_r($request['WebAuthenticationDetail']);
  // print_r($request['ClientDetail']);
  // exit;                    
    $request['RequestedShipment']['Shipper'] = array(
      'Address' => array(
        'StreetLines' => array(constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ADDRESS_1_' . $vendors_id), constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_ADDRESS_2_' . $vendors_id)), // Origin details
        'City' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_CITY_' . $vendors_id),
        'StateOrProvinceCode' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATE_' . $vendors_id),
        'PostalCode' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_POSTAL_' . $vendors_id),
        'CountryCode' => $this->country
      )
    );
    $request['RequestedShipment']['Recipient'] = array(
      'Address' => array (
        'StreetLines' => array($street_address, $street_address2), // customer street address
        'City' => $city, //customer city
        // 'StateOrProvinceCode' => $state, //customer state
        'PostalCode' => $postcode, //customer postcode
        'CountryCode' => $country_id,
        'Residential' => ($order->delivery['company'] != '' ? false : true)
      )
    ); //customer county code
    if (in_array($country_id, array('US', 'CA'))) {
      $request['RequestedShipment']['Recipient']['StateOrProvinceCode'] = $state;
    }
    // print_r($request['RequestedShipment']['Recipient'])  ;
    // exit;
    $request['RequestedShipment']['ShippingChargesPayment'] = array('PaymentType' => 'SENDER',
                                                                    'Payor' => array('AccountNumber' => $this->fedex_act_num, // payor's account number
                                                                    'CountryCode' => $this->country));
    $request['RequestedShipment']['RateRequestTypes'] = 'LIST';
    $request['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';
    $request['RequestedShipment']['RequestedPackageLineItems'] = array();
    
    $dimensions_failed = false;
    
    // check for ready to ship field
    if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_READY_TO_SHIP_' . $vendors_id) == 'true') {      
      $products = $_SESSION['cart']->get_products();
      $packages = array('default' => 0);
      $product_dim_type = 'in';
      $new_shipping_num_boxes = 0;
      foreach ($products as $product) {
        $dimensions_query = "SELECT products_ready_to_ship, products_ship_sep FROM " . TABLE_PRODUCTS . " 
                             WHERE products_id = " . (int)$product['id'] . " 
                             LIMIT 1;";
        $dimensions = tep_db_query($dimensions_query);
        if ($product_dimensions = tep_db_fetch_array($dimensions)) {
          if ($product_dimensions['products_ready_to_ship'] == 1 || $product_dimensions['products_ship_sep'] == 1) {
            for ($i = 1; $i <= $product['quantity']; $i++) {
              $packages[] = array('weight' => $product['weight']);
            }    
          } else {
            $packages['default'] += $product['weight'] * $product['quantity']; 
          }                                                                    
        }
      }
      if (count($packages) > 1) {
        $za_tare_array = preg_split("/[:,]/" , SHIPPING_BOX_WEIGHT);
        $zc_tare_percent= $za_tare_array[0];
        $zc_tare_weight= $za_tare_array[1];

        $za_large_array = preg_split("/[:,]/" , SHIPPING_BOX_PADDING);
        $zc_large_percent= $za_large_array[0];
        $zc_large_weight= $za_large_array[1];
      }                                                                                                            
      foreach ($packages as $id => $values) {
        if ($id === 'default') {
          // divide the weight by the max amount to be shipped (can be done inside loop as this occurance should only ever happen once
          // note $values is not an array
          if ($values == 0) continue;
          $shipping_num_boxes = ceil((float)$values / (float)SHIPPING_MAX_WEIGHT);
          if ($shipping_num_boxes < 1) $shipping_num_boxes = 1;
          $shipping_weight = round((float)$values / $shipping_num_boxes, 2); // 2 decimal places max
          for ($i=0; $i<$shipping_num_boxes; $i++) {
            $new_shipping_num_boxes++;
            if (SHIPPING_MAX_WEIGHT <= $shipping_weight) {
              $shipping_weight = $shipping_weight + ($shipping_weight*($zc_large_percent/100)) + $zc_large_weight;
            } else {
              $shipping_weight = $shipping_weight + ($shipping_weight*($zc_tare_percent/100)) + $zc_tare_weight;
            }
            if ($shipping_weight <= 0) $shipping_weight = 0.1; 
            $new_shipping_weight += $shipping_weight;           
            $request['RequestedShipment']['RequestedPackageLineItems'][] = array(
              'GroupPackageCount' => 1,
              'Weight' => array(
                'Value' => $shipping_weight,
                'Units' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_WEIGHT_' . $vendors_id)
              )
            );
          }
        } else {
          // note $values is an array
          $new_shipping_num_boxes++;
          if ($values['weight'] <= 0) $values['weight'] = 0.1;
          $new_shipping_weight += $values['weight'];
          $request['RequestedShipment']['RequestedPackageLineItems'][] = array(
            'GroupPackageCount' => 1,
            'Weight' => array(
              'Value' => $values['weight'],
              'Units' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_WEIGHT_' . $vendors_id)
            )
          );
        }
      }
      $shipping_num_boxes = $new_shipping_num_boxes;
      if (!$shipping_num_boxes || $shipping_num_boxes == 0) {
        $shipping_num_boxes = 1;
      }
      $shipping_weight = round($new_shipping_weight / $shipping_num_boxes, 2);
    } else {
      // Zen Cart default method for calculating number of packages
      if ($shipping_weight == 0) $shipping_weight = 0.1;
        for ($i=0; $i<$shipping_num_boxes; $i++) {
          $request['RequestedShipment']['RequestedPackageLineItems'][] = array(
            'GroupPackageCount'=>1,
            'Weight' => array(
              'Value' => $shipping_weight,
              'Units' => constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_WEIGHT_' . $vendors_id)
            )
          );
        }
      }
      $request['RequestedShipment']['PackageCount'] = $shipping_num_boxes;
    
      if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_SATURDAY_' . $vendors_id) == 'true') {
        $request['RequestedShipment']['ServiceOptionType'] = 'SATURDAY_DELIVERY';
      }
      
      if (constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_SIGNATURE_OPTION_' . $vendors_id) >= 0 && $totals >= MODULE_SHIPPING_FEDEX_WEB_SERVICES_SIGNATURE_OPTION) { 
        $request['RequestedShipment']['SpecialServicesRequested'] = 'SIGNATURE_OPTION'; 
      }

      // echo '<!-- shippingWeight: ' . $shipping_weight . ' ' . $shipping_num_boxes . ' -->';
      // echo '<!-- ';
      // echo '<pre>';
      // print_r($request);
      // echo '</pre>';
      // echo ' -->';
      $response = $client->getRates($request);
      // echo '<!-- ';
      // echo '<pre>';
      // print_r($response);
      // echo '</pre>';
      // echo ' -->';
      if ($response->HighestSeverity != 'FAILURE' && $response->HighestSeverity != 'ERROR' && is_array($response->RateReplyDetails) || is_object($response->RateReplyDetails)) {
      //if ($response->HighestSeverity != 'FAILURE' && $response->HighestSeverity != 'ERROR') {
        if (is_object($response->RateReplyDetails)) {
          $response->RateReplyDetails = get_object_vars($response->RateReplyDetails);
        }
        // echo '<pre>';
        // print_r($response->RateReplyDetails);
        // echo '</pre>';

        $show_box_weight = " (Total items: " . $shipping_num_boxes . ' pcs. Total weight: '.number_format($shipping_weight * $shipping_num_boxes,2).' '.strtolower(constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_WEIGHT_' . $vendors_id)).'s.)';
        $this->quotes = array('id' => $this->code,
                            'module' => $this->title . $show_box_weight,
                            'info' => $this->info());


       // echo '<pre>';
       // print_r($response->RateReplyDetails);
       // echo '</pre>';

       // EXIT();


      $methods = array();
      // echo '<pre>';                     
      // print_r($this->types);
      // echo '</pre>';
      foreach ($response->RateReplyDetails as $rateReply)
      {
        if (array_key_exists($rateReply->ServiceType, $this->types) && ($method == '' || str_replace('_', '', $rateReply->ServiceType) == $method))
        {
          if(constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_RATES_' . $vendors_id) =='LIST')
          {
            foreach($rateReply->RatedShipmentDetails as $ShipmentRateDetail)
            {
              if($ShipmentRateDetail->ShipmentRateDetail->RateType=='PAYOR_LIST_PACKAGE')
              {
                $cost = $ShipmentRateDetail->ShipmentRateDetail->TotalNetCharge->Amount;
                $cost = (float)round(preg_replace('/[^0-9.]/', '',  $cost), 2);
              }
            }
          }
          else
          {
            $cost = $rateReply->RatedShipmentDetails[0]->ShipmentRateDetail->TotalNetCharge->Amount;
            $cost = (float)round(preg_replace('/[^0-9.]/', '',  $cost), 2);
          }
          if (in_array($rateReply->ServiceType, array('GROUND_HOME_DELIVERY', 'FEDEX_GROUND', 'INTERNATIONAL_GROUND'))) {
      // print_r($rateReply);
            $transitTime = ' (' . str_replace(array('_', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteeen'), array(' ', 1,2,3,4,5,6,7,8,9,10,11,12,13,14), strtolower($rateReply->TransitTime)) . ')';
          }
          $methods[] = array('id' => str_replace('_', '', $rateReply->ServiceType),                                                   
                             'title' => ucwords(strtolower(str_replace('_', ' ', $rateReply->ServiceType))) . $transitTime,     
                             'cost' => $cost + (strpos($this->types[$rateReply->ServiceType]['handling_fee'], '%') ? ($cost * (float)$this->types[$rateReply->ServiceType]['handling_fee'] / 100) : (float)$this->types[$rateReply->ServiceType]['handling_fee']));
        }
      }

    // usort($methods, 'cmp');
      $this->quotes['methods'] = $methods;
      
      if ($this->tax_class > 0) {
        $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
      }
    } else {
      $message = 'Error in processing transaction.<br /><br />';
      foreach ($response -> Notifications as $notification) {
        if(is_array($response -> Notifications)) {
          $message .= $notification->Severity;
          $message .= ': ';
          $message .= $notification->Message . '<br />';
        } else {
          $message .= $notification->Message . '<br />';
        }
      }
      $this->quotes = array('module' => $this->title,
                            'error'  => $message);
    }
// po box hack by JD
            if (eregi("^P(.+)O(.+)BOX",$order->delivery['street_address']) ||eregi("^PO BOX",$order->delivery['street_address']) || eregi("^P(.+)O(.+)BOX",$order->delivery['suburb']) || eregi("^[A-Z]PO",$order->delivery['street_address']) || eregi("^[A-Z]PO",$order->delivery['suburb'])) {
        $this->quotes = array('module' => $this->title,
                              'error' => '<font size=+2 color=red><b>Federal Express cannot ship to Post Office Boxes.<b></font><br>Use the Change Address button above to use a FedEx accepted street address.'); }
// end po box hack by JD
    if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
    // echo '<!-- Quotes: ';
    // print_r($this->quotes);
    // print_r($_SESSION['shipping']);
    // echo ' -->';
    return $this->quotes;
  }
  
  function cmp($a, $b) {
    if ($a['cost'] == $b['cost']) {
        return 0;
    }
    return ($a['cost'] < $b['cost']) ? -1 : 1;
  }

  // method added for expanded info in FEAC
  function info() {
    return MODULE_SHIPPING_FEDEX_WEB_SERVICES_INFO; // add a description here or leave blank to disable
  }
    
  function _setInsuranceValue($order_amount, $vendors_id){
    if ($order_amount > (float) MODULE_SHIPPING_FEDEX_WEB_SERVICES_INSURE) {
      $this->insurance = sprintf("%01.2f", $order_amount);
    } else {
      $this->insurance = 0;
    }
  }

  function objectToArray($object) {
    if( !is_object( $object ) && !is_array( $object ) ) {
      return $object;
    }
    if( is_object( $object ) ) {
      $object = get_object_vars( $object );
    }
    return array_map( 'objectToArray', $object );
  }

  function _setDropOff($vendors_id) {
    switch(constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_DROPOFF_' . $vendors_id)) {
      case '1':
        return 'REGULAR_PICKUP';
        break;
      case '2':
        return 'REQUEST_COURIER';
        break;
      case '3':
        return 'DROP_BOX';
        break;
      case '4':
        return 'BUSINESS_SERVICE_CENTER';
        break;
      case '5':
        return 'STATION';
        break;
    }
  }

  function check($vendors_id = '1'){
    if(!isset($this->_check)){
      $check_query  = tep_db_query("SELECT configuration_value FROM ". TABLE_VENDOR_CONFIGURATION ." WHERE configuration_key = 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATUS_$vendors_id'");
      $this->_check = tep_db_num_rows ($check_query);
    }
    return $this->_check;
  }

  function install($vendors_id = '1') {

    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) VALUES ('Enable FedEx Web Services','MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATUS_$vendors_id','true','Do you want to offer FedEx shipping?','6','0','tep_cfg_select_option(array(\'true\',\'false\'),',now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('FedEx Web Services Key', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_KEY_$vendors_id', '', 'Enter FedEx Web Services Key', '6', '3', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('FedEx Web Services Password', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_PWD_$vendors_id', '', 'Enter FedEx Web Services Password', '6', '3', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('FedEx Account Number', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ACT_NUM_$vendors_id', '', 'Enter FedEx Account Number', '6', '3', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('FedEx Meter Number', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_METER_NUM_$vendors_id', '', 'Enter FedEx Meter Number', '6', '4', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Weight Units', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_WEIGHT_$vendors_id', 'LB', 'Weight Units:', '6', '10', 'tep_cfg_select_option(array(\'LB\', \'KG\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('First line of street address', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ADDRESS_1_$vendors_id', '', 'Enter the first line of your ship-from street address, required', '6', '20', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Second line of street address', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ADDRESS_2_$vendors_id', '', 'Enter the second line of your ship-from street address, leave blank if you do not need to specify a second line', '6', '21', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('City name', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_CITY_$vendors_id', '', 'Enter the city name for the ship-from street address, required', '6', '22', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('State or Province name', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATE_$vendors_id', '', 'Enter the 2 letter state or province name for the ship-from street address, required for Canada and US', '6', '23', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Postal code', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_POSTAL_$vendors_id', '', 'Enter the postal code for the ship-from street address, required', '6', '24', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Phone number', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_PHONE_$vendors_id', '', 'Enter a contact phone number for your company, required', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Drop off type', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_DROPOFF_$vendors_id', '1', 'Dropoff type (1 = Regular pickup, 2 = request courier, 3 = drop box, 4 = drop at BSC, 5 = drop at station)?', '6', '30', 'tep_cfg_select_option(array(\'1\',\'2\',\'3\',\'4\',\'5\'),', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Express Saver', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_SAVER_$vendors_id', 'true', 'Enable FedEx Express Saver', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Standard Overnight', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_STANDARD_OVERNIGHT_$vendors_id', 'true', 'Enable FedEx Express Standard Overnight', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable First Overnight', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_FIRST_OVERNIGHT_$vendors_id', 'true', 'Enable FedEx Express First Overnight', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Priority Overnight', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_PRIORITY_OVERNIGHT_$vendors_id', 'true', 'Enable FedEx Express Priority Overnight', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable 2 Day', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_2DAY_$vendors_id', 'true', 'Enable FedEx Express 2 Day', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable International Priority', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_PRIORITY_$vendors_id', 'true', 'Enable FedEx Express International Priority', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable International Economy', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_ECONOMY_$vendors_id', 'true', 'Enable FedEx Express International Economy', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Ground', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_GROUND_$vendors_id', 'true', 'Enable FedEx Ground', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable International Ground', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_GROUND_$vendors_id', 'true', 'Enable FedEx International Ground', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Freight', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_FREIGHT_$vendors_id', 'true', 'Enable FedEx Freight', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Saturday Delivery', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_SATURDAY_$vendors_id', 'false', 'Enable Saturday Delivery', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Domestic Ground Handling Fee', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_HANDLING_FEE_$vendors_id', '', 'Add a domestic handling fee or leave blank (example: 15 or 15%)', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Home Delivery Handling Fee', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_HOME_DELIVERY_HANDLING_FEE_$vendors_id', '', 'Add a home delivery handling fee or leave blank (example: 15 or 15%)', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Domestic Express Handling Fee', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_$vendors_id', '', 'Add a domestic handling fee or leave blank (example: 15 or 15%)', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('International Ground Handling Fee', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_HANDLING_FEE_$vendors_id', '', 'Add an international handling fee or leave blank (example: 15 or 15%)', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('International Express Handling Fee', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_$vendors_id', '', 'Add an international handling fee or leave blank (example: 15 or 15%)', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) VALUES ('FedEx Rates','MODULE_SHIPPING_FEDEX_WEB_SERVICES_RATES_$vendors_id','LIST','FedEx Rates','6','0','tep_cfg_select_option(array(\'LIST\',\'ACCOUNT\'),',now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Signature Option', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_SIGNATURE_OPTION_$vendors_id', '-1', 'Require a signature on orders greater than or equal to (set to -1 to disable):', '6', '25', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added, vendors_id) values ('Enable Ready to Ship', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_READY_TO_SHIP_$vendors_id', 'false', 'Enable products_ship_sep or products_ready_to_ship field (required to identify products which ship separately', '6', '10', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added, vendors_id) values ('Shipping Zone', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_$vendors_id', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '98', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added, vendors_id) values ('Tax Class', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_TAX_CLASS_$vendors_id', '0', 'Use the following tax class on the shipping fee.', '6', '25', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now(), '" . $vendors_id . "')");
    tep_db_query ("insert into " . TABLE_VENDOR_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, vendors_id) values ('Sort Order', 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_SORT_ORDER_$vendors_id', '0', 'Sort order of display.', '6', '99', now(), '" . $vendors_id . "')");
  }

  function remove($vendors_id = '1') {
    tep_db_query ("DELETE FROM ". TABLE_VENDOR_CONFIGURATION ." WHERE configuration_key in ('". implode("','",$this->keys($vendors_id)). "')");
  }

  function keys($vendors_id = '1') {
    return array('MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATUS_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_KEY_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_PWD_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ACT_NUM_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_METER_NUM_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_WEIGHT_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ADDRESS_1_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ADDRESS_2_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_CITY_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_STATE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_POSTAL_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_PHONE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_DROPOFF_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_SAVER_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_STANDARD_OVERNIGHT_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_FIRST_OVERNIGHT_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_PRIORITY_OVERNIGHT_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_2DAY_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_PRIORITY_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_ECONOMY_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_GROUND_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_FREIGHT_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INTERNATIONAL_GROUND_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_SATURDAY_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_TAX_CLASS_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_HANDLING_FEE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_HOME_DELIVERY_HANDLING_FEE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_EXPRESS_HANDLING_FEE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_HANDLING_FEE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_INT_EXPRESS_HANDLING_FEE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_RATES_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_SIGNATURE_OPTION_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_READY_TO_SHIP_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_ZONE_' . $vendors_id,
                 'MODULE_SHIPPING_FEDEX_WEB_SERVICES_SORT_ORDER_' . $vendors_id
                 );
  }

  function sort_order($vendors_id='1') {
    $sort_order = @constant('MODULE_SHIPPING_FEDEX_WEB_SERVICES_SORT_ORDER_' . $vendors_id);
    if (tep_not_null($sort_order)) {
      $this->sort_order = $sort_order;
    } else {
      $this->sort_order = '-';
    }
    return $this->sort_order;
  }

    function get_countries($countries_id = '', $with_iso_codes = false) {
      $countries_array = array();
      if (tep_not_null($countries_id)) {
        if ($with_iso_codes == true) {
          $countries = tep_db_query("select countries_name, countries_iso_code_2, countries_iso_code_3 from " . TABLE_COUNTRIES . " where countries_id = '" . (int)$countries_id . "' order by countries_name");
          $countries_values = tep_db_fetch_array($countries);
          $countries_array = array('countries_name' => $countries_values['countries_name'],
                                   'countries_iso_code_2' => $countries_values['countries_iso_code_2'],
                                   'countries_iso_code_3' => $countries_values['countries_iso_code_3']);
        } else {
          $countries = tep_db_query("select countries_name from " . TABLE_COUNTRIES . " where countries_id = '" . (int)$countries_id . "'");
          $countries_values = tep_db_fetch_array($countries);
          $countries_array = array('countries_name' => $countries_values['countries_name']);
        }
      } else {
        $countries = tep_db_query("select countries_id, countries_name from " . TABLE_COUNTRIES . " order by countries_name");
        while ($countries_values = tep_db_fetch_array($countries)) {
          $countries_array[] = array('countries_id' => $countries_values['countries_id'],
                                     'countries_name' => $countries_values['countries_name']);
        }
      }

      return $countries_array;
    }
}
