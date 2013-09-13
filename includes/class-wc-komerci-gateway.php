<?php
/**
 * WC Komerci Gateway Class.
 *
 * Built the Komerci method.
 *
 * @since 1.0.0
 */
class WC_Komerci_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id             = 'komerci';
        $this->icon           = apply_filters( 'woocommerce_komerci_icon', WOO_KOMERCI_URL . 'images/komerci.png' );
        $this->has_fields     = false;
        $this->method_title   = __( 'Komerci', 'wckomerci' );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->get_option( 'title' );
        $this->description    = $this->get_option( 'description' );
        $this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
        $this->debug          = $this->get_option( 'debug' );

        // Actions.
        add_action( 'woocommerce_api_wc_komerci_gateway', array( $this, 'check_ipn_response' ) );
        add_action( 'valid_komerci_ipn_request', array( $this, 'successful_request' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Active logs.
        if ( 'yes' == $this->debug )
            $this->log = $woocommerce->logger();

        // Valid for use.
        if ( ! $this->is_valid_for_use() )
            $this->enabled = false;
    }

    /**
     * Check if this gateway is enabled and available in the user's country.
     *
     * @return bool
     */
    public function is_valid_for_use() {
        if ( ! in_array( get_woocommerce_currency(), array( 'BRL' ) ) )
            return false;

        return true;
    }

    /**
     * Admin Panel Options.
     */
    public function admin_options() {
        echo '<h3>' . __( 'Komerci standard', 'wckomerci' ) . '</h3>';
        echo '<p>' . __( 'Komerci standard works by sending the user to Komerci to enter their payment information.', 'wckomerci' ) . '</p>';

        // Checks if is valid for use.
        if ( ! $this->is_valid_for_use() ) {
            echo '<div class="inline error"><p><strong>' . __( 'Komerci Disabled', 'wckomerci' ) . '</strong>: ' . __( 'Works only with Brazilian Real.', 'wckomerci' ) . '</p></div>';
        } else {
            // Generate the HTML For the settings form.
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'wckomerci' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Komerci standard', 'wckomerci' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Title', 'wckomerci' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wckomerci' ),
                'desc_tip' => true,
                'default' => __( 'Komerci', 'wckomerci' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wckomerci' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wckomerci' ),
                'default' => __( 'Pay via Komerci', 'wckomerci' )
            ),
            'invoice_prefix' => array(
                'title' => __( 'Invoice Prefix', 'wckomerci' ),
                'type' => 'text',
                'description' => __( 'Please enter a prefix for your invoice numbers. If you use your Komerci account for multiple stores ensure this prefix is unqiue as Komerci will not allow orders with the same invoice number.', 'wckomerci' ),
                'desc_tip' => true,
                'default' => 'WC-'
            ),
            'testing' => array(
                'title' => __( 'Gateway Testing', 'wckomerci' ),
                'type' => 'title',
                'description' => ''
            ),
            'debug' => array(
                'title' => __( 'Debug Log', 'wckomerci' ),
                'type' => 'checkbox',
                'label' => __( 'Enable logging', 'wckomerci' ),
                'default' => 'no',
                'description' => sprintf( __( 'Log Komerci events, such as API requests, inside %s', 'wckomerci' ), '<code>woocommerce/logs/komerci-' . sanitize_file_name( wp_hash( 'komerci' ) ) . '.txt</code>' )
            )
        );
    }

    /**
     * Add error message in checkout.
     *
     * @param string $message Error message.
     *
     * @return string         Displays the error message.
     */
    protected function add_error( $message ) {
        global $woocommerce;

        if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) )
            wc_add_error( $message );
        else
            $woocommerce->add_error( $message );
    }

    /**
     * Send email notification.
     *
     * @param  string $subject Email subject.
     * @param  string $title   Email title.
     * @param  string $message Email message.
     *
     * @return void
     */
    protected function send_email( $subject, $title, $message ) {
        global $woocommerce;

        $mailer = $woocommerce->mailer();

        $mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $title, $message ) );
    }

    /**
     * Generate the payment xml.
     *
     * @param object  $order Order data.
     *
     * @return string        Payment xml.
     */
    protected function generate_payment_xml( $order ) {
        // Include the WC_Komerci_SimpleXML class.
        require_once WOO_KOMERCI_PATH . 'includes/class-wc-komerci-simplexml.php';

        // Creates the payment xml.
        $xml = new WC_Komerci_SimpleXML( '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><checkout></checkout>' );

        // TODO: generate payment xml here!

        // Filter the XML.
        $xml = apply_filters( 'woocommerce_komerci_payment_xml', $xml, $order );

        return $xml->asXML();
    }

    /**
     * Generate Payment Token.
     *
     * @param object $order Order data.
     *
     * @return bool
     */
    public function generate_payment_token( $order ) {
        global $woocommerce;

        // TODO: Generate payment token here!

        return false;
    }

    /**
     * Process the payment and return the result.
     *
     * @param int    $order_id Order ID.
     *
     * @return array           Redirect.
     */
    public function process_payment( $order_id ) {
        global $woocommerce;

        $order = new WC_Order( $order_id );

        $token = $this->generate_payment_token( $order );

        if ( $token ) {
            // Remove cart.
            $woocommerce->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => esc_url_raw( $this->payment_url . $token )
            );
        }
    }

    /**
     * Process the IPN.
     *
     * @return bool
     */
    public function process_ipn_request( $data ) {

        if ( 'yes' == $this->debug )
            $this->log->add( 'komerci', 'Checking IPN request...' );

        // TODO: process IPN here!

        return false;
    }

    /**
     * Check API Response.
     *
     * @return void
     */
    public function check_ipn_response() {
        @ob_clean();

        $ipn = $this->process_ipn_request( $_POST );

        if ( $ipn ) {
            header( 'HTTP/1.1 200 OK' );
            do_action( 'valid_komerci_ipn_request', $ipn );
        } else {
            wp_die( __( 'Komerci Request Failure', 'wckomerci' ) );
        }
    }

    /**
     * Successful Payment!
     *
     * @param array $posted Komerci post data.
     *
     * @return void
     */
    public function successful_request( $posted ) {

        if ( isset( $posted->reference ) ) {
            $order_id = (int) str_replace( $this->invoice_prefix, '', $posted->reference );

            $order = new WC_Order( $order_id );

            // Checks whether the invoice number matches the order.
            // If true processes the payment.
            if ( $order->id === $order_id ) {
                // TODO: Update order status!
            } else {
                if ( 'yes' == $this->debug )
                    $this->log->add( 'komerci', 'Error: Order Key does not match with Komerci reference.' );
            }
        }
    }
}
