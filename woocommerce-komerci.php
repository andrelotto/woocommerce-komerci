<?php
/**
 * Plugin Name: WooCommerce Komerci
 * Plugin URI: http://github.com/claudiosmweb/woocommerce-komerci
 * Description: Gateway de pagamento Komerci para WooCommerce.
 * Author: claudiosanches, Gabriel Reguly
 * Author URI: http://claudiosmweb.com/
 * Version: 0.0.1
 * License: GPLv2 or later
 * Text Domain: wckomerci
 * Domain Path: /languages/
 */

define( 'WOO_KOMERCI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_KOMERCI_URL', plugin_dir_url( __FILE__ ) );

/**
 * WooCommerce fallback notice.
 */
function wckomerci_woocommerce_fallback_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Komerci Gateway depends on the last version of %s to work!', 'wckomerci' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce' ) . '</a>' ) . '</p></div>';
}

/**
 * Load functions.
 */
function wckomerci_gateway_load() {

    // Checks with WooCommerce is installed.
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wckomerci_woocommerce_fallback_notice' );

        return;
    }

    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'wckomerci', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /**
     * Add the gateway to WooCommerce.
     *
     * @param  array $methods WooCommerce payment methods.
     *
     * @return array          Payment methods with Komerci.
     */
    function wckomerci_add_gateway( $methods ) {
        $methods[] = 'WC_Komerci_Gateway';

        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'wckomerci_add_gateway' );

    // Include the WC_Komerci_Gateway class.
    require_once WOO_KOMERCI_PATH . 'includes/class-wc-komerci-gateway.php';
}

add_action( 'plugins_loaded', 'wckomerci_gateway_load', 0 );

/**
 * Hides the Komerci with payment method with the customer lives outside Brazil
 *
 * @param  array $available_gateways Default Available Gateways.
 *
 * @return array                    New Available Gateways.
 */
function wckomerci_hides_when_is_outside_brazil( $available_gateways ) {

    // Remove standard shipping option.
    if ( isset( $_REQUEST['country'] ) && 'BR' != $_REQUEST['country'] )
        unset( $available_gateways['komerci'] );

    return $available_gateways;
}

add_filter( 'woocommerce_available_payment_gateways', 'wckomerci_hides_when_is_outside_brazil' );

/**
 * Adds custom settings url in plugins page.
 *
 * @param  array $links Default links.
 *
 * @return array        Default links and settings link.
 */
function wckomerci_action_links( $links ) {

    $settings = array(
        'settings' => sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Komerci_Gateway' ),
            __( 'Settings', 'wckomerci' )
        )
    );

    return array_merge( $settings, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wckomerci_action_links' );
