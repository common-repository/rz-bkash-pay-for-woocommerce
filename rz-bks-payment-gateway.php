<?php
/*
Plugin Name:       RZ bKash Pay for woo-commerce
Plugin URI:        https://github.com/Rezwan81/rz-bks-payment-gateway-plugin
Description:       Bangladesh Bkash payment gateway extention for woo-commerce 
Version:           1.0
Requires at least: 5.2
Requires PHP:      7.2
Author:            Rezwan Shiblu
Author URI:        http://devles.com
License:           GPL v2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:       janets
*/

defined('ABSPATH') or die(' you are cheating ');

/**
 * Check if WooCommerce is activated
 */

if ( ! function_exists( 'rz_is_woocommerce_activated' ) ) {

	function rz_is_woocommerce_activated() {

		if ( class_exists( 'woocommerce' ) ) { return true; } else { return false; }
	}
}

    add_filter( 'woocommerce_payment_gateways', 'rz_bkash_payment' );

    function rz_bkash_payment( $gateways ) {

        $gateways[] = 'RZ_Bkash';
        return $gateways;
    }

    add_action( 'plugins_loaded', 'rz_bkash_gateway' );

    function rz_bkash_gateway() {

    	class RZ_Bkash extends WC_Payment_Gateway {

    		public $instructions;

    		public function __construct() {

    			$this->id                     = 'rz_bkash';
                $this->title                  = $this->get_option('title', 'bKash');
                $this->description            = $this->get_option('description', 'rz bKash payment Gateway');
                $this->method_title           = esc_html__("bKash", "janets");
                $this->method_description     = esc_html__("RZ bKash Payment Gateway", "janets" );
                $this->icon                   = plugins_url('img/rzbks.png', __FILE__);
                $this->has_fields             = true;
                $this->instructions           = $this->get_option('instructions');
                $this->rz_bkash_options_fields();
                $this->init_settings();

                add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );

                add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'rz_bkash_thankyou_page' ) );
    		}

    		public function rz_bkash_options_fields() {

                $this->form_fields = array(

                    'enabled'     =>    array(
                        'title'        => esc_html__( 'Enable/Disable', "janets" ),
                        'type'         => 'checkbox',
                        'label'        => esc_html__( 'bKash Payment', "janets" ),
                        'default'    => 'yes'
                    ),
                    'title'     => array(
                        'title'     => esc_html__( 'Title', "janets" ),
                        'type'         => 'text',
                        'default'    => esc_html__( 'bKash', "janets" )
                    ),
                    'description' => array(
                        'title'        => esc_html__( 'Description', "janets" ),
                        'type'         => 'textarea',
                        'default'    => esc_html__( 'Please complete your bKash payment', "janets" ),
                        'desc_tip'    => true
                    ),
                );
            }

            public function payment_fields() {

                global $woocommerce;

                ?>
                    <table border="0">
                      <tr>
                        <td><label for="bkash_number"><?php esc_html_e( 'bKash Number', "janets" );?></label></td>
                        <td><input class="widefat" type="text" name="bkash_number" id="bkash_number" placeholder="type your bks number"></td>
                      </tr>
                      <tr>
                        <td><label for="bkash_transaction_id"><?php esc_html_e( 'bKash Transaction ID', "janets" );?></label></td>
                        <td><input class="widefat" type="text" name="bkash_transaction_id" id="bkash_transaction_id" placeholder="type your bks transaction number"></td>
                      </tr>
                    </table>
                <?php
            }

            public function process_payment( $order_id ) { 

                global $woocommerce;

                $order = new WC_Order( $order_id );

                $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
                // Mark as on-hold (we're awaiting the bKash)
                $order->update_status( $status, esc_html__( 'Checkout with bKash payment. ', "janets" ) );

                // Remove cart
                $woocommerce->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            }

            public function rz_bkash_thankyou_page() {

                $order_id = get_query_var( 'order-received' );
                $order = new WC_Order( $order_id );

                if( $order->get_payment_method() == $this->id ){

                    $thankyou = $this->instructions;
                    return $thankyou;
                } else {

                return esc_html__( 'Thank you. Your order has been received and we will send you confirm by email or mobile number .', "janets" );
                }
            }
    	} 
    }

    add_action( 'woocommerce_checkout_process', 'rz_bkash_payment_process' );

    function rz_bkash_payment_process() {

        if( $_POST['payment_method'] != 'rz_bkash' )
            return;

        $bkash_number = sanitize_text_field( $_POST['bkash_number'] );
        $bkash_transaction_id = sanitize_text_field( $_POST['bkash_transaction_id'] );

        $match_number = isset( $bkash_number ) ? $bkash_number : '';
        $match_id = isset( $bkash_transaction_id ) ? $bkash_transaction_id : '';

        $validate_number = preg_match( '/^01[1-9]\d{8}$/', $match_number );
        $validate_id = preg_match( '/[a-zA-Z0-9]+/',  $match_id );

        if( ! isset( $bkash_number ) || empty( $bkash_number ) )
            wc_add_notice( esc_html__( 'Please add your mobile number', 'janets' ), 'error' );

        if( ! empty( $bkash_number ) && $validate_number == false )
            wc_add_notice( esc_html__( 'Invalid mobile number. It must be bangladesh mobile number', 'janets' ), 'error' );

        if( ! isset( $bkash_transaction_id ) || empty( $bkash_transaction_id ) )
            wc_add_notice( esc_html__( 'Invalid bKash transaction ID', 'janets' ), 'error' );

        if( ! empty( $bkash_transaction_id ) && $validate_id == false )
            wc_add_notice( esc_html__( 'Only number or letter is acceptable', 'janets' ), 'error' );
    }

    add_action( 'woocommerce_checkout_update_order_meta', 'rz_bkash_fields_update' );

    function rz_bkash_fields_update( $order_id ) {

        if( $_POST['payment_method'] != 'rz_bkash' )
            return;

        $bkash_number = sanitize_text_field( $_POST['bkash_number'] );
        $bkash_transaction_id = sanitize_text_field( $_POST['bkash_transaction_id'] );

        $number = isset( $bkash_number ) ? $bkash_number : '';
        $transaction = isset( $bkash_transaction_id ) ? $bkash_transaction_id : '';

        update_post_meta( $order_id, '_bkash_number', $number );
        update_post_meta( $order_id, '_bkash_transaction', $transaction );
    }

    add_action( 'woocommerce_admin_order_data_after_billing_address', 'rz_bkash_admin_order_data' );
    function rz_bkash_admin_order_data( $order ) {

        if( $order->get_payment_method() != 'rz_bkash' )
            return;

        $number = ( get_post_meta( $_GET['post'], '_bkash_number', true ) ) ? get_post_meta( $_GET['post'], '_bkash_number', true ) : '';

        $transaction = ( get_post_meta( $_GET['post'], '_bkash_transaction', true ) ) ? get_post_meta( $_GET['post'], '_bkash_transaction', true ) : '';
        ?>

        <div class="form-field form-field-wide">
            <img src='<?php echo plugins_url("img/rzbks.png", __FILE__); ?>'>
            <table class="wp-list-table widefat fixed striped posts">
                <tbody>
                    <tr>
                        <th><strong><?php esc_html_e('bKash No.', 'janets') ;?></strong></th>
                        <td>: <?php echo esc_attr( $number );?></td>
                    </tr>
                    <tr>
                        <th><strong><?php esc_html_e('Transaction ID', 'janets') ;?></strong></th>
                        <td>: <?php echo esc_attr( $transaction );?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    add_action( 'woocommerce_order_details_after_customer_details', 'rz_bkash_info_order_review_fields' );

    function rz_bkash_info_order_review_fields( $order ) {

        if( $order->get_payment_method() != 'rz_bkash' )
            return;

        global $wp;

        // Get the order ID
        $order_id  = absint( $wp->query_vars['order-received'] );

        $number = ( get_post_meta( $order_id, '_bkash_number', true ) ) ? get_post_meta( $order_id, '_bkash_number', true ) : '';

        $transaction = ( get_post_meta($order_id, '_bkash_transaction', true ) ) ? get_post_meta($order_id, '_bkash_transaction', true ) : '';
        ?>

        <table>
            <tr>
                <th><?php esc_html_e('bKash No:', 'janets');?></th>
                <td><?php echo esc_attr( $number );?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Transaction ID:', 'janets');?></th>
                <td><?php echo esc_attr( $transaction );?></td>
            </tr>
        </table>
        <?php
    }

    add_filter( 'manage_edit-shop_order_columns', 'rz_bkash_admin_new_column' );

    function rz_bkash_admin_new_column( $columns ) {

        $new_columns = ( is_array($columns ) ) ? $columns : array();
        unset( $new_columns['order_actions'] );

        $new_columns['mobile_no']     = esc_html__('bKash No.', 'janets');
        $new_columns['tran_id']       = esc_html__('Tran. ID', 'janets');
        $new_columns['order_actions'] = $columns['order_actions'];

        return $new_columns;
    }

    add_action( 'manage_shop_order_posts_custom_column', 'rz_bkash_admin_column_value', 2 );

    function rz_bkash_admin_column_value( $column ) {

        global $post;

        $mobile_no = ( get_post_meta( $post->ID, '_bkash_number', true ) ) ? get_post_meta( $post->ID, '_bkash_number', true ) : '';

        $tran_id = ( get_post_meta( $post->ID, '_bkash_transaction', true ) ) ? get_post_meta( $post->ID, '_bkash_transaction', true ) : '';

        if ( $column == 'mobile_no' ) {
            echo esc_attr( $mobile_no );
        }

        if ( $column == 'tran_id' ) {
            echo esc_attr( $tran_id );
        }
    }








