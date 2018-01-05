<?php

class Datapaga_Payment_Gateway extends WC_Payment_Gateway {

  function __construct() {
    // global ID
    $this->id = "datapaga_woo";

    // Show Title
    $this->method_title = __( "Datapaga Payment Gateway", 'datapaga-woo' );

    // Show Description
    $this->method_description = __( "Plugin to integrate Woocommerce with DataPaga payment gateway.", 'datapaga-woo' );

    // vertical tab title
    $this->title = __( "Datapaga Payment Gateway", 'datapaga-woo' );

    $this->icon = null;

    // payment fields to show on the checkout 
    $this->has_fields = true;

    // support default form with credit card
    $this->supports = array( 'default_credit_card_form' );

    // setting defines
    $this->init_form_fields();

    // load time variable setting
    $this->init_settings();
    
    // Turn these settings into variables we can use
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }
    
    // check of SSL if you want
    add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );
    
    // Save settings
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }   
  } // end construct()


  // administration fields for specific Gateway
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __( 'Enable / Disable', 'datapaga-woo' ),
        'label'   => __( 'Enable this payment gateway', 'datapaga-woo' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title' => array(
        'title'   => __( 'Title', 'datapaga-woo' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Payment title of checkout process.', 'datapaga-woo' ),
        'default' => __( 'Credit card', 'datapaga-woo' ),
      ),
      'description' => array(
        'title'   => __( 'Description', 'datapaga-woo' ),
        'type'    => 'textarea',
        'desc_tip'  => __( 'Payment description of checkout process.', 'datapaga-woo' ),
        'default' => __( 'Successfully payment through credit card.', 'datapaga-woo' ),
        'css'   => 'max-width:450px;'
      ),
      'api_key' => array(
        'title'   => __( 'Api Key', 'datapaga-woo' ),
        'type'    => 'text',
        'desc_tip'  => __( 'This is the API Key provided by Datapaga when you signed up for an account.', 'datapaga-woo' ),
        'default' => '',
      ),
      'api_secret' => array(
        'title'   => __( 'Api Secret', 'datapaga-woo' ),
        'type'    => 'text',
        'desc_tip'  => __( 'This is the API Secret provided by Datapaga when you signed up for an account.', 'datapaga-woo' ),
        'default' => '',
      ),
    );    
    
  }


  // Response handled for payment gateway
  public function process_payment( $order_id ) {
    global $woocommerce;
    
    $customer_order = new WC_Order( $order_id );

    $environment_url = 'https://datapaga-staging.herokuapp.com/v1/account_movements/charge';
    // $environment_url = 'http://25c198f3.ngrok.io/v1/account_movements/charge';

    $api_key = $this->api_key;
    $api_secret = $this->api_secret;

    // get card-expiry
    $date = $_POST['datapaga_woo-card-expiry'];
    list($month, $year) = array_map('trim', explode('/', $date));


    // get website url
    // $checkout_url = $woocommerce->cart->get_checkout_url();
    $url = site_url();

    // get customer ip
    $ip = $_SERVER['REMOTE_ADDR'];
    $dataArray = json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip));

    //get product description
    $items = $woocommerce->cart->get_cart();
    foreach($items as $item => $values) { 
      $_product =  wc_get_product( $values['data']->get_id());
    } 

    // get credit card
    $number= str_replace( array(' ', '-' ), '', $_POST['datapaga_woo-card-number'] );
    $expirationdate= $_POST['datapaga_woo-card-expiry'];
    $securitycode= ( isset( $_POST['datapaga_woo-card-cvc'] ) ) ? $_POST['datapaga_woo-card-cvc'] : '';

    function validatecard($number)
     {
        global $type;

        $cardtype = array(
            "VI" => "/^4[0-9]{12}(?:[0-9]{3})?$/",
            "MC" => "/^5[1-5][0-9]{14}$/",
            "AE" => "/^3[47][0-9]{13}$/"
        );

        if (preg_match($cardtype['VI'],$number))
        {
            $type= "VI";
            return 'VI';
      
        }
        else if (preg_match($cardtype['MC'],$number))
        {
            $type= "MC";
            return 'MC';
        }
        else if (preg_match($cardtype['AE'],$number))
        {
            $type= "AE";
            return 'AE';
      
        }
        else
        {
            return false;
        } 
     }

    // order_total in cents
    $total = $customer_order->order_total;
    $dollars = str_replace('$', '', $total);
    $cents = bcmul($dollars, 100);


    // This is where the fun stuff begins
    $payload = array("account_movement" => array(
      "api_key"  => $api_key,
      "api_secret" => $api_secret,
      "first_name" => $customer_order->billing_first_name,
      "last_name" => $customer_order->billing_last_name,
      "web_site_url" => $url,
      "phone" => $customer_order->billing_phone,
      "country" => $dataArray->{'geoplugin_countryCode'},
      "city" => $customer_order->billing_city,
      "email" => $customer_order->billing_email,
      "customer_ip" => $ip,
      "region" => $customer_order->billing_state,
      "zip" => $customer_order->billing_postcode,
      "street" => $customer_order->billing_state,
      "total_amount" => $cents,
      "product_description" => $_product->get_title(),
      "card_holder_name" => $customer_order->billing_first_name. ' ' .$customer_order->billing_last_name,
      "card_number" => $number,
      "card_expire_month" => $month,
      "card_expire_year" => $year,
      "card_type" => validatecard($number),
      "card_security_code" => $securitycode,
      "type" => "auth"
     ));

    // Send payload
    $response = wp_remote_post( $environment_url, array(
      'method'    => 'POST',
      'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'      => json_encode( $payload ),
      'timeout'   => 90,
      'sslverify' => false,
    ) );


    if ( is_wp_error( $response ) ) 
      throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.'.$payload, 'datapaga-woo' ) );

    if ( empty( $response['body'] ) )
      throw new Exception( __( 'Datapaga\'s Response was empty.', 'datapaga-woo' ) );
      
    // get body response while get not error
    $response_body = wp_remote_retrieve_body( $response );

    $obj = json_decode($response_body);

      // Payment has been successful
    if ( $obj->{'code'} == 201 &&  $obj->{'response'} == 'APPROVED') {
      $customer_order->add_order_note( __( 'Datapaga payment completed.', 'datapaga-woo' ) );
                         
      // Mark order as Paid
      $customer_order->payment_complete();

      // Empty the cart (Very important step)
      $woocommerce->cart->empty_cart();

      // Redirect to thank you page
      return array(
        'result'   => 'success',
        'redirect' => $this->get_return_url( $customer_order )
      );
    } else {
      // Transaction was not succesful
      // Add notice to the cart
      wc_add_notice( $obj->{'errors'}, 'error' );
      // Add note to the order for your reference
      $customer_order->add_order_note( 'Error: '. $obj->{'errors'} );
    }
   

  }//end process payment

  public function validate_fields() {
    return true;
  }
  
  // Check if we are forcing SSL on checkout pages
  // Custom function not required by the Gateway
  public function do_ssl_check() {
    if( $this->enabled == "yes" ) {
      if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
      }
    }   
  }

}

?>