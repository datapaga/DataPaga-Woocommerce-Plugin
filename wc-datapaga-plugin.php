<?php
/*
Plugin Name: DataPaga Woocommerce Plugin
Plugin URI: https://github.com/elaniin/DataPaga-Wocommerce-Plugin
Description: WordPress plugin to integrate Woocommerce with DataPaga payment gateway.
Version: 1
Author: Elaniin
Author URI: https://elaniin.com/
*/

  // Include our Gateway Class and Register Payment Gateway with WooCommerce
  add_action( 'plugins_loaded', 'datapaga_woo_init', 0 );
  function datapaga_woo_init() {
    //if condition use to do nothin while WooCommerce is not installed
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
      include_once( 'wc-datapaga-payment.php' );
    // class add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'datapaga_payment_gateway' );
    function datapaga_payment_gateway( $methods ) {
      $methods[] = 'Datapaga_Payment_Gateway';
      return $methods;
    }
  }


  // Add custom action links
  add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'datapaga_action_links' );
  function datapaga_action_links( $links ) {
    $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'datapaga-woo' ) . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );
  }