<?php

/**
 * Copyright (c) 2023 CardGate
 * Author: Richard Schoots
 * For more infomation about CardGate: http://www.cardgate.com
 * Released under the GNU General Public License
 * Zen-Cart version Copyright (c) 2011 GetZenned: http://www.getzenned.nl
 */

require_once dirname( __FILE__ ) . '/cardgate-clientlib-php/init.php';

abstract class cg_generic {

    var $debug = false;
    var $order_status = 0;
    var $code, $title, $description, $enabled, $module_payment_type;

    var $version = '1.5.18';

// class constructor

    function __construct() {
        global $order;
        $this->code                = 'cg_' . $this->payment_option;
        $this->module_payment_type = 'MODULE_PAYMENT_CG_' . strtoupper( $this->payment_option );
        $this->title               = $this->checkoutDisplay();
        $this->description         = $this->getDescription();
        $this->enabled             = $this->payment_option == 'cardgate' ? false : true;
        $this->order_status        = $this->getOrderStatus();
        $this->sort_order          = $this->getSortOrder();
        $this->form_action_url     = $this->cg_action_url();

        if ( is_object( $order ) ) {
            $this->update_status();
        }
    }

    function cg_action_url() {
        $protocol    = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ) ? "https://" : "http://";
        $domain_name = $_SERVER['HTTP_HOST'] . '/';

        return $protocol . $domain_name . 'cardgate/cg_redirect.php';
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        $selection           = array();
        $selection['id']     = $this->code;
        $selection['module'] = $this->title;
        $show_issuers = false;
        if ( defined( $this->module_payment_type . '_SHOW_ISSUERS' ) && constant( $this->module_payment_type . '_SHOW_ISSUERS' ) == 'With issuers' ) {
            $show_issuers = true;
        }
        if ( $this->module_payment_type == 'MODULE_PAYMENT_CG_IDEAL' && $show_issuers ) {
            $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
            $selection['fields'] = array(
                array(
                    'field' => zen_draw_pull_down_menu( 'suboption', $this->get_banks(), '', $onFocus )
                )
            );
        }

        return $selection;
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        global $order;

        $confirmation = array(
            'title'  => constant( $this->module_payment_type . '_CONFIRMATION_TITLE' ),
            'fields' => array(
                array(
                    'title' => '',
                    'field' => constant( $this->module_payment_type . '_CONFIRMATION_TEXT' )
                )
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
        if ( ( $this->enabled == true ) && ( constant( $this->module_payment_type . '_ZONE' ) > 0 ) ) {
            $check_flag  = false;
            $check_query = $db->Execute( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . constant( $this->module_payment_type . '_ZONE' ) . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id" );
            while ( ! $check_query->EOF ) {
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

        return array(
            'title' => constant( $this->module_payment_type . '_TEXT_ERROR' ),
            'error' => $error
        );
    }

    function check() {
        global $db;
        if ( ! isset( $this->check ) ) {
            if ( $this->payment_option == 'cardgate' ) {
                $check_query = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $this->module_payment_type . '_MODE' . "'" );
            } else {
                $check_query = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $this->module_payment_type . '_ZONE' . "'" );
            }
            $this->check = $check_query->RecordCount();
        }

        return ( 0 < $this->check );
    }

    function output_error() {
        return false;
    }

    function before_process() {
        return false;
    }

    function process_button() {
        global $order;
        global $db;

        $customer_id = $_SESSION['customer_id'];
        $cg_test     = ( constant( 'MODULE_PAYMENT_CG_CARDGATE_MODE' ) === 'Test' ? 1 : 0 );
        $cg_amount   = (int) round( $order->info['total'] * 100, 0 );

        $sql_data_array = array(
            'module'       => $this->code,
            'date_ordered' => 'now()',
            'sessionstr'   => str_replace( '\'', '\\\'', serialize( $_SESSION ) ),
            'customer_id'  => $customer_id,
            'is_test'      => $cg_test,
            'amount'       => $cg_amount
        );

        zen_db_perform( 'CGP_orders_table', $sql_data_array );

        $reference = $db->Insert_ID();

        $order_info             = array();
        $order_info['amount']   = $cg_amount;
        $order_info['currency'] = $order->info['currency'];
        $order_info['ref']      = $reference;

        $sql = "update CGP_orders_table SET orderstr='" . str_replace( '\'', '\\\'', serialize( $order_info ) ) . "' where ref_id= :ref:";
        $sql = $db->bindVars( $sql, ':ref:', $reference, 'integer' );
        $db->Execute( $sql );

        $products  = $order->products;
        $tax_total = 0;

        foreach ( $products as $product ) {

            $aProductid = explode( ':', $product['id'] );
            $productid  = $aProductid[0];

            $result = $db->execute( "SELECT * FROM products WHERE products_id='" . $product['id'] . "'" );

            $item               = array();
            $item['stock']      = $result->fields['products_quantity'];
            $item['quantity']   = $product['qty'];
            $item['sku']        = 'product_' . $productid;
            $item['name']       = $product['name'];
            $item['price']      = (int) round( $product['final_price'] * 100, 0 );
            $item['vat']        = (int) round( $product['tax'], 0 );
            $item['vat_amount'] = (int) round( $product['final_price'] * $item['vat'] );
            $item['vat_inc']    = 0;
            $item['type']       = 1;
            $cartitems[]        = $item;

            $tax_total += $item['vat_amount'] * $item['quantity'];
        }

        if ( $order->info['shipping_cost'] > 0 ) {
            $item               = array();
            $item['quantity']   = 1;
            $item['sku']        = $order->info['shipping_module_code'];
            $item['name']       = $order->info['shipping_method'];
            $item['price']      = intval( round( $order->info['shipping_cost'] * 100, 0 ) );
            $item['vat_amount'] = intval( round( $order->info['shipping_tax'] * 100, 0 ) );
            $item['vat_inc']    = 0;
            $item['type']       = 2;
            $cartitems[]        = $item;

            $tax_total += $item['vat_amount'];
        }

        // correct for rounding error
        $tax_difference = $tax_total - round( $order->info['tax'] * 100, 0 );
        if ( $tax_difference != 0 ) {
            reset( $cartitems );
            $cartitems[ key( $cartitems ) ]['vat_amount'] = $cartitems[ key( $cartitems ) ]['vat_amount'] - $tax_difference;
        }

        $sCardgateHash = '';
        if ( '' != constant( 'MODULE_PAYMENT_CG_CARDGATE_HASHKEY' ) ) {
            $sCardgateHash = md5( ( $cg_test == 1 ? "TEST" : "" ) . constant( 'MODULE_PAYMENT_CG_CARDGATE_SITEID' ) . $cg_amount . $reference . constant( 'MODULE_PAYMENT_CG_CARDGATE_HASHKEY' ) );
        }

        $zen_order                     = [];
        $zen_order['description']      = 'CGOrder ' . $reference;
        $zen_order['reference']        = $reference;
        $zen_order['paymentmethod']    = $this->payment_option;
        $zen_order['testmode']         = $cg_test;
        $zen_order['amount']           = $cg_amount;
        $zen_order['currency']         = $order->info['currency'];
        $zen_order['language']         = constant( 'MODULE_PAYMENT_CG_CARDGATE_LANGUAGE' );
        $zen_order['billing']          = $order->billing;
        $zen_order['shipping']         = $order->delivery;
        $zen_order['customer']         = $order->customer;
        $zen_order['cartitems']        = $cartitems;
        $zen_order['plugin_name']      = $this->code;
        $zen_order['plugin_version']   = $this->version;
        $zen_order['platform_name']    = 'ZenCart';
        $zen_order['platform_version'] = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
        $zen_order['cardgatehash']     = $sCardgateHash;

        if ( $this->payment_option == 'ideal' ) {
            $zen_order['suboption'] = $_POST['suboption'];
        }

        $process_button_string =
            zen_draw_hidden_field( 'zen_order', json_encode( serialize( $zen_order ), JSON_HEX_APOS | JSON_HEX_QUOT ) ) .
            zen_draw_hidden_field( 'cardgateredirect', 'true' );

        return $process_button_string;
    }

    function after_process() {
        return;
    }

    function install() {
        global $db;

        if ( $this->module_payment_type == "MODULE_PAYMENT_CG_CARDGATE" ) {
            // mode (test or active)
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('CardGate Mode', '" . $this->module_payment_type . "_MODE', 'Test', 'Status mode for CGP payments (test or active)', '6', '1','zen_cfg_select_option(array(\'Test\', \'Active\'), ', now())" );
            // siteid
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Site ID', '" . $this->module_payment_type . "_SITEID', '', 'CardGate Site ID', '6', '2', now())" );
            // hash key
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Hash key', '" . $this->module_payment_type . "_HASHKEY', '', 'CardGate Hash key', '6', '3', now())" );
            // merchant id
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', '" . $this->module_payment_type . "_MERCHANTID', '', 'CardGate Merchant ID', '6', '4', now())" );
            // api key
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('API key', '" . $this->module_payment_type . "_APIKEY', '', 'CardGate API key', '6', '5', now(), 'zen_cfg_password_display')" );
            // language
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Gateway language', '" . $this->module_payment_type . "_LANGUAGE', 'en', 'Gateway language', '6', '6', now())" );
        } else {
            // checkout display
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Checkout Display', '" . $this->module_payment_type . "_CHECKOUT_DISPLAY', 'Text', 'Display in the checkout', '6', '7','zen_cfg_select_option(array(\'Text\', \'Logo\', \'Text and Logo\'), ', now())" );
            // initial order status
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Initial Order Status', '" . $this->module_payment_type . "_ORDER_INITIAL_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '8', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())" );
            // paid order status
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Paid Order Status', '" . $this->module_payment_type . "_ORDER_PAID_STATUS_ID', '0', 'Set the status of orders paid with this payment module to this value', '6', '9', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())" );
            // zone
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', '" . $this->module_payment_type . "_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '10', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())" );
            // sort order
            $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', '" . $this->module_payment_type . "_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '11' , now())" );

            if ( $this->module_payment_type == "MODULE_PAYMENT_CG_IDEAL" ) {
                // show iDEAL issuers
                $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Show ideal issuers.', '" . $this->module_payment_type . "_SHOW_ISSUERS', 'Without issuers', 'iDEAL v2 will not show issuers any more by default (Mandatory by iDEAL).', '6', '12','zen_cfg_select_option(array(\'Without issuers\', \'With issuers\'), ', now())" );
            }
        }

        $query = 'CREATE TABLE IF NOT EXISTS `CGP_orders_table` (' .
                 'ref_id INT(11)  NOT NULL AUTO_INCREMENT PRIMARY KEY,' .
                 'date_ordered datetime NOT NULL,' .
                 'module VARCHAR(32)  NOT NULL,' .
                 'customer_id INT(11) NOT NULL DEFAULT 0,' .
                 'orderstr TEXT NOT NULL,' .
                 'sessionstr TEXT NOT NULL,' .
                 'amount INT(11) NOT NULL DEFAULT 0,' .
                 'status INT(11) NOT NULL DEFAULT 0,' .
                 'transaction_id INT(32) NOT NULL DEFAULT 0,' .
                 'is_test INT(11) NOT NULL DEFAULT 0,' .
                 'order_id INT(11) NOT NULL' .
                 ')';

        $db->Execute( $query );

        // make sure order status exist
        $pm_status    = array();
        $pm_status[0] = array( 1000, 'Payment success' );
        $pm_status[1] = array( 1001, 'Payment cancelled' );
        for ( $x = 0; $x < 2; $x ++ ) {
            $language_result = $db->Execute( 'SELECT languages_id FROM ' . TABLE_LANGUAGES );
            while ( ! $language_result->EOF ) {
                $language_id   = $language_result->fields['languages_id'];
                $sql           = 'SELECT orders_status_id FROM ' . TABLE_ORDERS_STATUS . ' WHERE orders_status_id = ' . $pm_status[ $x ][0] . ' and language_id = ' . $language_id;
                $status_result = $db->Execute( $sql );
                $id            = $status_result->fields['orders_status_id'];
                if ( $id != $pm_status[ $x ][0] ) {
                    $db->Execute( 'INSERT INTO ' . TABLE_ORDERS_STATUS . ' (orders_status_id, language_id, orders_status_name) VALUES (' . $pm_status[ $x ][0] . ',' . $language_id . ',"' . $pm_status[ $x ][1] . '")' );
                }
                $language_result->MoveNext();
            }
        }
    }

    function remove() {
        global $db;

        $keys       = '';
        $keys_array = $this->keys();
        for ( $i = 0; $i < sizeof( $keys_array ); $i ++ ) {
            $keys .= "'" . $keys_array[ $i ] . "',";
        }
        $keys = substr( $keys, 0, - 1 );

        if ( defined( $this->module_payment_type . '_DROP_TABLE' ) && 'True' === constant( $this->module_payment_type . '_DROP_TABLE' ) ) {
            $db->Execute( "DROP TABLE IF EXISTS `CGP_orders_table`" );
        }

        $db->Execute( "delete from " . TABLE_CONFIGURATION . " where configuration_key in (" . $keys . ")" );
    }

    function keys() {
        return array(
            $this->module_payment_type . '_MODE',
            $this->module_payment_type . '_SITEID',
            $this->module_payment_type . '_HASHKEY',
            $this->module_payment_type . '_MERCHANTID',
            $this->module_payment_type . '_APIKEY',
            $this->module_payment_type . '_LANGUAGE',
            $this->module_payment_type . '_STATUS',
            $this->module_payment_type . '_CHECKOUT_DISPLAY',
            $this->module_payment_type . '_ZONE',
            $this->module_payment_type . '_SORT_ORDER',
            $this->module_payment_type . '_SHOW_ISSUERS',
            $this->module_payment_type . '_ORDER_INITIAL_STATUS_ID',
            $this->module_payment_type . '_ORDER_PAID_STATUS_ID'
        );
    }

    public function get_banks() {
        if ( $this->genericModuleSet() ) {
            $this->checkBanks();
            $aBankOptions = $this->fetchBankOptions();
            $aBanks       = array();
            foreach ( $aBankOptions as $id => $text ) {
                $aBanks[] = array(
                    "id"   => $id,
                    "text" => $text
                );
            }

            return $aBanks;
        } else {
            return false;
        }
    }

    private function checkBanks() {
        global $db;

        $oResult        = $db->execute( "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH'" );
        $sIssuerRefresh = $oResult->fields['configuration_value'];

        if ( $sIssuerRefresh === null ) {
            $db->execute( "INSERT INTO " . TABLE_CONFIGURATION . "(configuration_title, configuration_key, configuration_value)
                        VALUES ( 'Issuer Refresh', 'MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH',0)" );
        }

        $oResult = $db->execute( "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH'" );

        $iIssuerRefresh = (int) $oResult->fields['configuration_value'];

        if ( $iIssuerRefresh < time() ) {
            $this->cacheBankOptions();
        }
    }

    private function cacheBankOptions() {
        global $db;

        $testMode   = ( constant( 'MODULE_PAYMENT_CG_CARDGATE_MODE' ) === 'Test' ? true : false );
        $merchantId = (int) constant( 'MODULE_PAYMENT_CG_CARDGATE_MERCHANTID' );
        $apiKey     = constant( 'MODULE_PAYMENT_CG_CARDGATE_APIKEY' );
        $oCardGate  = new cardgate\api\Client( $merchantId, $apiKey, $testMode );
        $aIssuers   = $oCardGate->methods()->get( \cardgate\api\Method::IDEAL )->getIssuers();

        $aBanks    = [];
        $aBanks[0] = '-Maak uw keuze a.u.b.-';

        if ( $aIssuers ) {
            foreach ( $aIssuers as $aIsssuer ) {
                $aBanks[ $aIsssuer['id'] ] = $aIsssuer['name'];
            }
        }

        $oResult          = $db->execute( "SELECT configuration_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='MODULE_PAYMENT_CG_IDEAL_ISSUERS'" );
        $iConfigurationId = $oResult->fields['configuration_id'];

        if ( array_key_exists( "INGBNL2A", $aBanks ) ) {
            $sIssuers = serialize( $aBanks );
            if ( $iConfigurationId === null ) {
                $resultId = $db->execute( "INSERT INTO " . TABLE_CONFIGURATION . "(configuration_title, configuration_key, configuration_value)
                        VALUES ( 'Issuers', 'MODULE_PAYMENT_CG_IDEAL_ISSUERS','" . $sIssuers . "')" );
            } else {
                $resultId = $db->execute( "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $sIssuers . "' WHERE configuration_key = 'MODULE_PAYMENT_CG_IDEAL_ISSUERS'" );
            }
            $iIssuerRefresh = ( 24 * 60 * 60 ) + time();
            $db->execute( "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $iIssuerRefresh . "' WHERE configuration_key = 'MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH'" );
        }
    }

    function fetchBankOptions() {
        global $db;

        $oResult = $db->execute( "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='MODULE_PAYMENT_CG_IDEAL_ISSUERS'" );
        $sBanks  = $oResult->fields['configuration_value'];
        $aBanks  = unserialize( $sBanks );

        return $aBanks;
    }

    function logo( $payment_option ) {
        $file = 'https://cdn.curopayments.net/images/paymentmethods/' . $payment_option . '.svg';

        return '<img style="max-width:70px;max-height:30px;" width="40px;" src="' . $file . '" />&nbsp;';
    }

    function checkoutDisplay() {
        $option = ( defined( $this->module_payment_type . '_CHECKOUT_DISPLAY' ) ? constant( $this->module_payment_type . '_CHECKOUT_DISPLAY' ) : 'Text and Logo' );
        $text   = constant( $this->module_payment_type . '_TEXT_TITLE' );
        $logo   = $this->logo( $this->payment_option );

        switch ( $option ) {
            case 'Text':
                $display = $text;
                break;
            case 'Logo':
                $display = $logo;
                break;
            case 'Text and Logo':
                $display = $logo . ' ' . $text;
                break;
            default:
                $display = $logo . ' ' . $text;
        }
        if ( ! $this->genericModuleSet() ) {
            $display .= '<span class="alert"> (Please configure CardGate module first.) </span>';
        }

        return $display;
    }

    function getOrderStatus() {
        $status = $this->module_payment_type . '_ORDER_INITIAL_STATUS_ID';

        return defined( $status ) ? constant( $status ) : null;
    }

    function getSortOrder() {
        $sortOrder = $this->module_payment_type . '_SORT_ORDER';

        return defined( $sortOrder ) ? constant( $sortOrder ) : null;
    }

    function getDescription() {
        $description = $this->module_payment_type . '_TEXT_DESCRIPTION';
        $message     = defined( $description ) ? '<b>module version: ' . $this->version . '</b></br>' . constant( $description ) : null;
        if ( ! $this->genericModuleSet() && ! ( $this->payment_option == 'cardgate' ) ) {
            $message .= '<br>Install the Generic CardGate module  first please.';
        }

        return $message;
    }

    function genericModuleSet() {
        return defined( 'MODULE_PAYMENT_CG_CARDGATE_APIKEY' );
    }

}

?>