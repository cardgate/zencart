<?php

/**
 * Copyright (c) 2013 Card Gate Plus
 * Author: Richard Schoots
 * For more infomation about Card Gate Plus: http://www.cardgate.com
 * Released under the GNU General Public License
 * Zen-Cart version Copyright (c) 2011 GetZenned: http://www.getzenned.nl
 */
abstract class cgp_generic {

    var $debug = false;
    var $order_status = 0;

    const version = '1.5.8';

    function dump( $str ) {
        echo '<div style="color: #FFFFFF; background: #A0c0c0; border: 1px solid #000000;">';
        echo '<pre style="margin-left: 20px;">';
        var_dump( $str );
        echo '</pre>';
        echo '</div>';
    }

// class constructor

    function __construct() {
        return false;
    }

    function cg_action_url() {
        $cgp_test = (constant( $this->module_payment_type . '_MODE' ) === 'Test' ? true : false);
        if ( $cgp_test ) {
            return "https://secure-staging.curopayments.net/gateway/cardgate/";
        } else {
            return "https://secure.curopayments.net/gateway/cardgate/";
        }
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        $selection = array();
        $selection['id'] = $this->code;
        $selection['module'] = $this->title;

        if ( $this->module_payment_type == 'MODULE_PAYMENT_CGP_IDEAL' ) {
            $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

            $selection['fields'] = array(
                array(
                    'field' => zen_draw_pull_down_menu( 'suboption', $this->get_banks(), '', $onFocus )
                ) );
        }
        return $selection;
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        global $order;

        $confirmation = array( 'title' => constant( $this->module_payment_type . '_CONFIRMATION_TITLE' ),
            'fields' => array( array( 'title' => '',
                    'field' => constant( $this->module_payment_type . '_CONFIRMATION_TEXT' ) )
            )
        );

        return $confirmation;
    }

    /**
     * calculate zone matches and flag settings to determine whether this module should display to customers or not
     *
     */
    function update_status() {
        global $order, $db;
        if ( ($this->enabled == true) && (constant( $this->module_payment_type . '_ZONE' ) > 0) ) {
            $check_flag = false;
            $check_query = $db->Execute( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . constant( $this->module_payment_type . '_ZONE' ) . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id" );
            while ( !$check_query->EOF ) {
                if ( $check_query->fields['zone_id'] < 1 ) {
                    $check_flag = true;
                    break;
                } elseif ( $check_query->fields['zone_id'] == $order->billing['zone_id'] ) {
                    $check_flag = true;
                    break;
                }
                $check_query->MoveNext();
            }

            if ( $check_flag == false ) {
                $this->enabled = false;
            }
        }
    }

    function get_error() {
        if ( isset( $_GET['ErrMsg'] ) && zen_not_null( $_GET['ErrMsg'] ) ) {
            $error = stripslashes( urldecode( $_GET['ErrMsg'] ) );
        } elseif ( isset( $_GET['Err'] ) && zen_not_null( $_GET['Err'] ) ) {
            $error = stripslashes( urldecode( $_GET['Err'] ) );
        } elseif ( isset( $_GET['error'] ) && zen_not_null( $_GET['error'] ) ) {
            $error = stripslashes( urldecode( $_GET['error'] ) );
        } else {
            $error = constant( $this->module_payment_type . '_TEXT_ERROR_MESSAGE' );
        }

        return array( 'title' => constant( $this->module_payment_type . '_TEXT_ERROR' ),
            'error' => $error );
    }

    function check() {
        global $db;
        if ( !isset( $this->check ) ) {
            $check_query = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $this->module_payment_type . '_MODE' . "'" );
            $this->check = $check_query->RecordCount();
        }
        return (0 < $this->check);
    }

    function output_error() {
        return false;
    }

    function before_process() {
        return false;
    }

    function process_button() {
        global $order, $currencies;
        global $db;

        $current_sinfo = PROJECT_VERSION_NAME . ' v' . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR . '/';

        $customer_id = $_SESSION['customer_id'];
        $cgp_test = (constant( $this->module_payment_type . '_MODE' ) === 'Test' ? 1 : 0);
        $cgp_amount = round( $order->info['total'] * 100 );

        $sql_data_array = array( 'module' => $this->code,
            'date_ordered' => 'now()',
            'sessionstr' => str_replace( '\'', '\\\'', serialize( $_SESSION ) ),
            'customer_id' => $customer_id,
            'is_test' => $cgp_test,
            'amount' => $cgp_amount
        );

        zen_db_perform( 'CGP_orders_table', $sql_data_array );
        $ref = $db->Insert_ID();
        $cardgate_ref = $customer_id . '|' . $db->Insert_ID() . '|' . 'O' . time();

        $order_info = array();
        $order_info['amount'] = $cgp_amount;
        $order_info['currency'] = $order->info['currency'];
        $order_info['ref'] = $cardgate_ref;

        $sql = "update CGP_orders_table SET orderstr='" . str_replace( '\'', '\\\'', serialize( $order_info ) ) . "' where ref_id= :ref:";
        $sql = $db->bindVars( $sql, ':ref:', $ref, 'integer' );
        $db->Execute( $sql );

        $protocol = (!empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain_name = $_SERVER['HTTP_HOST'] . '/';
        $file = 'cardgateplus/cgp_process.php?status=cancelled&ref_id=' . $ref;

        if ( isset( $_COOKIE['zenid'] ) && !empty( $_COOKIE['zenid'] ) ) {
            $zenid = $_COOKIE['zenid'];
        } else {
            if ( isset( $_REQUEST['zenid'] ) && !empty( $_REQUEST['zenid'] ) ) {
                $zenid = $_REQUEST['zenid'];
            }
        }

        $products = $order->products;
        $tax_total = 0;

        foreach ( $products as $product ) {

            $aProductid = split( ':', $product['id'] );
            $productid = $aProductid[0];

            $result = $db->execute( "SELECT * FROM products WHERE products_id='" . $product['id'] . "'" );

            $item = array();
            $item['stock'] = $result->fields['products_quantity'];
            $item['quantity'] = $product['qty'];
            $item['sku'] = 'product_' . $productid;
            $item['name'] = $product['name'];
            $item['price'] = round( $product['final_price'] / $item['quantity'] * 100, 0 );
            $item['vat_amount'] = round( $product['tax'] / 100 * $item['price'], 0 );
            $item['vat_inc'] = 0;
            $item['type'] = 1;
            $cartitems[] = $item;

            $tax_total += $item['vat_amount'];
        }

        if ( $order->info['shipping_cost'] > 0 ) {
            $shipping_tax = 0;
            foreach ( $cart['taxes'] as $tax ) {
                $shipping_tax += $tax['tax_cost_shipping'];
            }
            $item = array();
            $item['quantity'] = 1;
            $item['sku'] = $order->info['shipping_module_code'];
            $item['name'] = $order->info['shipping_method'];
            $item['price'] = round( $order->info['shipping_cost'] * 100, 0 );
            $item['vat_amount'] = round( $order->info['shipping_tax'] * 100, 0 );
            $item['vat_inc'] = 0;
            $item['type'] = 2;
            $cartitems[] = $item;

            $tax_total += $item['vat_amount'];
        }

        // correct for rounding error
        $tax_difference = $tax_total - round( $order->info['tax'] * 100, 0 );
        if ( $tax_difference != 0 ) {
            reset( $cartitems );
            $cartitems[key( $cartitems )]['vat_amount'] = $cartitems[key( $cartitems )]['vat_amount'] - $tax_difference;
        }

        $sHashkey = '';
        if ( '' != constant( $this->module_payment_type . '_KEYCODE' ) )
            $sHashKey = md5( ($cgp_test == 1 ? "TEST" : "") . constant( $this->module_payment_type . '_SITEID' ) . $cgp_amount . $cardgate_ref . constant( $this->module_payment_type . '_KEYCODE' ) );

        $process_button_string = zen_draw_hidden_field( 'siteid', constant( $this->module_payment_type . '_SITEID' ) ) .
                zen_draw_hidden_field( 'currency', $order->info['currency'] ) .
                zen_draw_hidden_field( 'description', constant( $this->module_payment_type . '_DESCRIPTION' ) . ' - customer id:' . $customer_id ) .
                zen_draw_hidden_field( 'option', $this->payment_option ) .
                zen_draw_hidden_field( 'test', $cgp_test ) .
                zen_draw_hidden_field( 'amount', $cgp_amount ) .
                zen_draw_hidden_field( 'ref', $cardgate_ref ) .
                zen_draw_hidden_field( 'extra', $zenid ) .
                zen_draw_hidden_field( 'language', constant( $this->module_payment_type . '_LANGUAGE' ) ) .
                zen_draw_hidden_field( 'email', $order->customer['email_address'] ) .
                zen_draw_hidden_field( 'first_name', $order->customer['firstname'] ) .
                zen_draw_hidden_field( 'last_name', $order->customer['lastname'] ) .
                zen_draw_hidden_field( 'address', $order->customer['street_address'] ) .
                zen_draw_hidden_field( 'postal_code', $order->customer['postcode'] ) .
                zen_draw_hidden_field( 'city', $order->customer['city'] ) .
                zen_draw_hidden_field( 'state', $order->customer['state'] ) .
                zen_draw_hidden_field( 'phone_number', $order->customer['telephone'] ) .
                zen_draw_hidden_field( 'country', $order->customer['country']['iso_code_2'] ) .
                zen_draw_hidden_field( 'zencart_order', serialize( $order->products ) ) .
                zen_draw_hidden_field( 'return_url', zen_href_link( FILENAME_CHECKOUT_SUCCESS, '', 'SSL' ) . '&action=empty_cart' ) .
                zen_draw_hidden_field( 'return_url_failed', $protocol . $domain_name . $file ) .
                zen_draw_hidden_field( 'plugin_name', $this->code ) .
                zen_draw_hidden_field( 'plugin_version', self::version ) .
                zen_draw_hidden_field( 'shop_version', PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR ) .
                zen_draw_hidden_field( 'shop_name', 'ZenCart' ) .
                zen_draw_hidden_field( 'hash', $sHashKey );
        if ( $this->payment_option == 'ideal' ) {
            $process_button_string .= zen_draw_hidden_field( 'suboption', $_POST['suboption'] );
        }

        if ( count( $cartitems ) > 0 ) {
            $process_button_string .= zen_draw_hidden_field( 'cartitems', json_encode( $cartitems, JSON_HEX_APOS | JSON_HEX_QUOT ) );
        }

        return $process_button_string;
    }

    function after_process() {
        return;
    }

    function install() {
        global $db;

        $protocol = (!empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain_name = $_SERVER['HTTP_HOST'] . '/';
        $cgp_control = $protocol . $domain_name . 'cardgateplus/cgp_process.php';
        // mode (test or active)
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('CGP Status Mode', '" . $this->module_payment_type . "_MODE', 'Test', 'Status mode for CGP payments? (test or active)', '6', '21','zen_cfg_select_option(array(\'Test\', \'Active\'), ', now())" );
        // siteid
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CGP Merchant Siteid', '" . $this->module_payment_type . "_SITEID', '', 'Card Gate plus Site ID', '6', '22', now())" );
        // hash key
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CGP Key Code', '" . $this->module_payment_type . "_KEYCODE', '', 'Card Gate Plus Security Code', '6', '23', now())" );
        // language
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CGP Client Language', '" . $this->module_payment_type . "_LANGUAGE', 'en', 'Client language', '6', '23', now())" );
        // order status
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Initial Order Status', '" . $this->module_payment_type . "_ORDER_INITIAL_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Paid Order Status', '" . $this->module_payment_type . "_ORDER_PAID_STATUS_ID', '0', 'Set the status of orders paid with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())" );
        // zone
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', '" . $this->module_payment_type . "_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '9', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())" );
        // sort order
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', '" . $this->module_payment_type . "_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0' , now())" );
        // DROP TABLE ON UNINSTALL
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Drop table on deinstall', '" . $this->module_payment_type . "_DROP_TABLE', 'False', 'Drop the CGP_orders_table on deinstall of this module. ONLY DO THIS IF ALL THE ORDERS HAVE BEEN PROCESSED AND YOU ARE INSTALLING A NEWER VERSION OF THIS MODULE!', '6', '0', 'zen_cfg_select_option(array(\'False\', \'True\'), ', now())" );
        // Remember to rename the callback
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Control URL', '" . $this->module_payment_type . "_REMEMBER', '', 'Set the Control URL in your Card Gate Merchant back-office to: <br><b>" . $cgp_control . "</b>', '6', '0',true, now())" );

        $query = 'CREATE TABLE IF NOT EXISTS `CGP_orders_table` (' .
                'ref_id INT(11)  NOT NULL AUTO_INCREMENT PRIMARY KEY,' .
                'date_ordered datetime NOT NULL,' .
                'module VARCHAR(32)  NOT NULL,' .
                'customer_id INT(11) NOT NULL DEFAULT 0,' .
                'orderstr TEXT NOT NULL,' .
                'sessionstr TEXT NOT NULL,' .
                'amount INT(11) NOT NULL DEFAULT 0,' .
                'status INT(11) NOT NULL DEFAULT 0,' .
                'transaction_id INT(11) NOT NULL DEFAULT 0,' .
                'is_test INT(11) NOT NULL DEFAULT 0,' .
                'order_id INT(11) NOT NULL' .
                ')';

        $db->Execute( $query );

        // make sure order status exist
        $pm_status = array();
        $pm_status[0] = array( 1000, 'Payment success' );
        $pm_status[1] = array( 1001, 'Payment cancelled' );
        for ( $x = 0; $x < 2; $x++ ) {
            $language_result = $db->Execute( 'SELECT languages_id FROM ' . TABLE_LANGUAGES );
            while ( !$language_result->EOF ) {
                $language_id = $language_result->fields['languages_id'];
                $sql = 'SELECT orders_status_id FROM ' . TABLE_ORDERS_STATUS . ' WHERE orders_status_id = ' . $pm_status[$x][0] . ' and language_id = ' . $language_id;
                $status_result = $db->Execute( $sql );
                $id = $status_result->fields['orders_status_id'];
                if ( $id != $pm_status[$x][0] ) {
                    $db->Execute( 'INSERT INTO ' . TABLE_ORDERS_STATUS . ' (orders_status_id, language_id, orders_status_name) VALUES (' . $pm_status[$x][0] . ',' . $language_id . ',"' . $pm_status[$x][1] . '")' );
                }
                $language_result->MoveNext();
            }
        }
    }

    function remove() {
        global $db;

        $keys = '';
        $keys_array = $this->keys();
        for ( $i = 0; $i < sizeof( $keys_array ); $i++ ) {
            $keys .= "'" . $keys_array[$i] . "',";
        }
        $keys = substr( $keys, 0, -1 );

        if ( 'True' === constant( $this->module_payment_type . '_DROP_TABLE' ) ) {
            $db->Execute( "DROP TABLE IF EXISTS `CGP_orders_table`" );
        }

        $db->Execute( "delete from " . TABLE_CONFIGURATION . " where configuration_key in (" . $keys . ")" );
    }

    function keys() {
        return array(
            $this->module_payment_type . '_MODE',
            $this->module_payment_type . '_SITEID',
            $this->module_payment_type . '_KEYCODE',
            $this->module_payment_type . '_LANGUAGE',
            $this->module_payment_type . '_ZONE',
            $this->module_payment_type . '_SORT_ORDER',
            $this->module_payment_type . '_ORDER_INITIAL_STATUS_ID',
            $this->module_payment_type . '_ORDER_PAID_STATUS_ID',
            $this->module_payment_type . '_DROP_TABLE',
            $this->module_payment_type . '_REMEMBER'
        );
    }

    function get_banks() {
        $aBankOptions = $this->getBankOptions();
        $aBanks = array();
        foreach ( $aBankOptions as $id => $text ) {
            $aBanks[] = array(
                "id" => $id,
                "text" => $text
            );
        }
        return $aBanks;
    }

    private function getBankOptions() {
        $url = 'https://secure.curopayments.net/cache/idealDirectoryCUROPayments.dat';
        if ( !ini_get( 'allow_url_fopen' ) || !function_exists( 'file_get_contents' ) ) {
            $result = false;
        } else {
            $result = file_get_contents( $url );
        }
        if ( $result ) {
            $aBanks = unserialize( $result );
            $aBanks[0] = '-Maak uw keuze a.u.b.-';
            return $aBanks;
        }
        return $result;
    }

    function logo( $payment_option ) {

        $protocol = (!empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain_name = $_SERVER['HTTP_HOST'];
        $file = $protocol . $domain_name . '/cardgateplus/images/' . $payment_option . '.jpg';

        return '<img style="max-width: 80px;" src="' . $file . '" />&nbsp;';
    }

}

?>