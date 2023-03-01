<?php

/**
 * Copyright (c) 2023 CardGate
 * Author: Richard Schoots
 * For more infomation about CardGate: http://www.cardgate.com
 * Released under the GNU General Public License
 * Zen-Cart version Copyright (c) 2011 GetZenned: http://www.getzenned.nl
 */

foreach ($_GET as $key =>$value){
    $cardgatekey = $key=='hash'? 'cardgatehash': $key;
    $_POST[$cardgatekey] = $value;
    unset($_GET[$key]);
}


chdir( '../' );
require_once( 'includes/application_top.php' );
require_once('includes/defined_paths.php');

$language_page_directory = DIR_WS_LANGUAGES . $_SESSION['language'] . '/';
include ($language_page_directory . 'checkout_process.php');

// check if the order is meant to be cancelled
if ( isset( $_POST['status'] ) && $_POST['status'] == 'failure' ) {
    $reference = ( int ) $_POST['reference'];
    $order_query = $db->Execute( "select * from CGP_orders_table where ref_id = '" . $reference . "'" );
    if ( $order_query->RecordCount() > 0 ) {

        $order = $order_query->fields;

        if ( $order['status'] == 0 && $order['transaction_id'] == 0 ) {
            $sql_data_array = array( 'transaction_id' => -1,
                'status' => 0 );

            zen_db_perform( 'CGP_orders_table', $sql_data_array, 'update', 'ref_id=' . $reference );

            require(DIR_WS_CLASSES . 'shipping.php');
            require(DIR_WS_CLASSES . 'payment.php');
            $_SESSION = unserialize( $order['sessionstr'] );
            $products = $_SESSION['cart']->get_products();

            // include the constants for the payment module
            include (DIR_WS_MODULES . 'payment/' . $_SESSION['payment'] . '.php');
            $payment_modules = new payment( $_SESSION['payment'] );
            $payment_module = $payment_modules->paymentClass;
            $shipping_modules = new shipping( $_SESSION['shipping'] );
            require(DIR_WS_CLASSES . 'order.php');
            $order = new order;
            require(DIR_WS_CLASSES . 'order_total.php');
            $order_total_modules = new order_total();
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS' );
            $order_totals = $order_total_modules->process();
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS' );
            $insert_id = $order->create( $order_totals );
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE' );

            // status = cancelled
            $status_cancelled = 1001;
            $sql = "UPDATE " . TABLE_ORDERS . "
	                 	SET orders_status = " . ( int ) $status_cancelled . "
	                 	WHERE orders_id = '" . ( int ) $insert_id . "'";
            $db->Execute( $sql );

            $sql_data_array = array( 'orders_id' => ( int ) $insert_id,
                'orders_status_id' => ( int ) $status_cancelled,
                'date_added' => 'now()',
                'comments' => 'CardGate status: ' . $_POST['code'],
                'customer_notified' => 0
            );

            zen_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );
            $order->create_add_products( $insert_id, 2 );
            $_SESSION['order_number_created'] = $insert_id;
            $GLOBALS[$_SESSION['payment']]->transaction_id = $_POST['transactionid'];
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS' );
            $order->send_order_email( $insert_id, 2 );
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL' );
            // Prepare sales-tracking data for use by notifier class
            $ototal = $order_subtotal = $credits_applied = 0;
            for ( $i = 0, $n = sizeof( $order_totals ); $i < $n; $i++ ) {
                if ( $order_totals[$i]['code'] == 'ot_subtotal' )
                    $order_subtotal = $order_totals[$i]['value'];
                if ( $$order_totals[$i]['code']->credit_class == true )
                    $credits_applied += $order_totals[$i]['value'];
                if ( $order_totals[$i]['code'] == 'ot_total' )
                    $ototal = $order_totals[$i]['value'];
            }
            $commissionable_order = ($order_subtotal - $credits_applied);
            $commissionable_order_formatted = $currencies->format( $commissionable_order );
            $_SESSION['order_summary']['order_number'] = $insert_id;
            $_SESSION['order_summary']['order_subtotal'] = $order_subtotal;
            $_SESSION['order_summary']['credits_applied'] = $credits_applied;
            $_SESSION['order_summary']['order_total'] = $ototal;
            $_SESSION['order_summary']['commissionable_order'] = $commissionable_order;
            $_SESSION['order_summary']['commissionable_order_formatted'] = $commissionable_order_formatted;
            $_SESSION['order_summary']['coupon_code'] = $order->info['coupon_code'];
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES', 'cardgateipn' );

            // add products that have been subtracted by the order.
            for ( $i = 0, $n = sizeof( $products ); $i < $n; $i++ ) {

                // check if product is not virtual
                $sql = 'SELECT products_virtual FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . $products[$i]['id'];
                $product_result = $db->Execute( $sql );
                $virtual = $product_result->fields['products_virtual'];
                if ( STOCK_LIMITED == 'true' ) {
                    $sql = "UPDATE " . TABLE_PRODUCTS . "
		                 	SET products_quantity = products_quantity+" . ( int ) $products[$i]['quantity'] . "
		                 	WHERE products_id = " . ( int ) $products[$i]['id'];
                    $db->Execute( $sql );
                }
            }
        }
    }
    zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL' ) );
    exit;
} else {

    // Check if it is a callback from CardGate and process
    $reference= ( int ) $_POST['reference'];
    $amount = ( int ) $_POST['amount'];
    $currency = $_POST['currency'];
    $code = $_POST['code'];
    $transaction = $_POST['transaction'];
    $is_test = ( int ) $_POST['is_test'];
    $status = ( int ) $_POST['status'];


    $order_query = $db->Execute( "select * from CGP_orders_table where ref_id = '" . $reference. "'" );

    if ( $order_query->RecordCount() > 0 ) {

        $order = $order_query->fields;
        $order_info = unserialize( $order['orderstr'] );

        $_SESSION = unserialize( $order['sessionstr'] );
        $cart = $_SESSION['cart'];
        include (DIR_WS_MODULES . 'payment/' . $_SESSION['payment'] . '.php');
        require(DIR_WS_CLASSES . 'shipping.php');
        require(DIR_WS_CLASSES . 'payment.php');
        $payment_modules = new payment( $_SESSION['payment'] );
        $payment_module = $payment_modules->paymentClass;
        $cg_test = $order['is_test'] == 1 ? 'TEST' : '';

        $hashVerify = md5( $cg_test .
                           $transaction .
                           $currency .
                           $amount .
                           $reference .
                           $code .
                           constant( strtoupper( 'MODULE_PAYMENT_CG_CARDGATE_HASHKEY' ) )
        );

        if ( $hashVerify != $_POST['cardgatehash'] ) {
            // Transaction not OK
            exit( 'Hash verification failed.' );
        }

        // transaction data has been verified and is correct.
        if ( $order['amount'] == $amount && $order['status'] == 0 && $order['transaction_id'] == 0 ) {
            $sql_data_array = array( 'transaction_id' => $transaction,
                'status' => $_POST['code'] );

            zen_db_perform( 'CGP_orders_table', $sql_data_array, 'update', 'ref_id=' . $reference);

            // include the constants for the payment module

            $shipping_modules = new shipping( $_SESSION['shipping'] );
            require(DIR_WS_CLASSES . 'order.php');
            $order = new order;
            require(DIR_WS_CLASSES . 'order_total.php');
            $order_total_modules = new order_total();
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS' );
            $order_totals = $order_total_modules->process();
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS' );
            $insert_id = $order->create( $order_totals );

            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE' );

            $status_paid = constant( $payment_module->module_payment_type . '_ORDER_PAID_STATUS_ID' );
            if ( $status_paid == 0 )
                $status_paid = 1000;
            if ( !isset( $status_paid ) ) {
                $status_paid = 2;
            }
            if ( $_POST['code'] == '200' ) {

                $sql = "UPDATE " . TABLE_ORDERS . "
	                 SET orders_status = " . ( int ) $status_paid . "
	                 WHERE orders_id = '" . ( int ) $insert_id . "'";
                $db->Execute( $sql );

                $sql_data_array = array( 'orders_id' => ( int ) $insert_id,
                    'orders_status_id' => ( int ) $status_paid,
                    'date_added' => 'now()',
                    'comments' => 'Card Gate status: ' . $_POST['code'],
                    'customer_notified' => 0
                );

                zen_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );
                $order->create_add_products( $insert_id, 2 );
                $_SESSION['order_number_created'] = $insert_id;
                $GLOBALS[$_SESSION['payment']]->transaction_id = $_POST['transaction'];
                $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS' );
                $order->send_order_email( $insert_id, 2 );
                $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL' );
                // Prepare sales-tracking data for use by notifier class
                $ototal = $order_subtotal = $credits_applied = 0;
                for ( $i = 0, $n = sizeof( $order_totals ); $i < $n; $i++ ) {
                    if ( $order_totals[$i]['code'] == 'ot_subtotal' )
                        $order_subtotal = $order_totals[$i]['value'];
                    if ( $$order_totals[$i]['code']->credit_class == true )
                        $credits_applied += $order_totals[$i]['value'];
                    if ( $order_totals[$i]['code'] == 'ot_total' )
                        $ototal = $order_totals[$i]['value'];
                }
                $commissionable_order = ($order_subtotal - $credits_applied);

                $commissionable_order_formatted = $currencies->format( $commissionable_order );
                $_SESSION['order_summary']['order_number'] = $insert_id;
                $_SESSION['order_summary']['order_subtotal'] = $order_subtotal;
                $_SESSION['order_summary']['credits_applied'] = $credits_applied;
                $_SESSION['order_summary']['order_total'] = $ototal;
                $_SESSION['order_summary']['commissionable_order'] = $commissionable_order;
                $_SESSION['order_summary']['commissionable_order_formatted'] = $commissionable_order_formatted;
                $_SESSION['order_summary']['coupon_code'] = $order->info['coupon_code'];
                $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES', 'cardgateipn' );
            }
            echo ($_POST['transaction'] . '.' . $_POST['code']);
        }
    }
}

require( 'includes/application_bottom.php' );
?>
