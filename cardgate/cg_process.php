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

$sLanguageDir = DIR_WS_LANGUAGES . $_SESSION['language'] . '/';
include_once ($sLanguageDir . 'checkout_process.php');
class cardgate
{
    protected $sHashKey;
    protected $iReference;
    protected $iAmount;
    protected $sCurrency;
    protected $iCode;
    protected $sTransaction;
    protected $bIsTestMode;
    protected $sHash;
    protected $aCardgateOrder;

    public function __construct($aCallback) {
        $this->setHashkey();
        $this->setReference ($aCallback['reference']);
        $this->setAmount ($aCallback['amount']);
        $this->setCurrency ($aCallback['currency']);
        $this->setCode ($aCallback['code']);
        $this->setTransaction($aCallback['transaction']);
        $this->setIsTestMode($aCallback['testmode']);
        $this->setHash($aCallback['cardgatehash']);
    }

    private function setHashkey(){
        $this->sHashKey = constant( 'MODULE_PAYMENT_CG_CARDGATE_HASHKEY');
    }

    private function getHashkey(){
       return $this->sHashKey;
    }

    private function setReference($reference){
        $this->iReference = (int) $reference;
    }

    private function getReference(){
        return $this->iReference;
    }

    private function setAmount($amount){
        $this->iAmount = (int) $amount;
    }

    private function getAmount(){
        return $this->iAmount;
    }

    private function setCurrency($currency){
        $this->sCurrency = (string) $currency;
    }

    private function getCurrency(){
        return $this->sCurrency;
    }

    private function setCode($code){
        $this->iCode =(int) $code;
    }

    private function getCode(){
       return $this->iCode;
    }

    private function setTransaction($transaction){
        $this->sTransaction = (string) $transaction;
    }

    private function getTransaction(){
        return $this->sTransaction;
    }

    private function setIsTestMode($testMode){
        $this->bIsTestMode = (bool) $testMode;
    }

    private function getIsTestMode(){
        return $this->bIsTestMode;
    }

    private function setHash($hash){
        $this->sHash = (string) $hash;
    }

    private function getHash(){
        return $this->sHash;
    }

    private function setCardgateOrder(){
        global $db;
        $aCardgateOrder = false;
        $qData = $db->Execute( "select * from CGP_orders_table where ref_id = '" . $this->iReference . "'" );
        if ( $qData->RecordCount() > 0 ) {
            $aCardgateOrder = $qData->fields;
        }
        $this->aCardgateOrder = $aCardgateOrder;
    }

    private function getCardgateOrder(){
        return $this->aCardgateOrder;
    }

    public function hashCheck(){
        $sTest = $this->getIsTestMode() ? 'TEST':'';
        $hashVerify = md5( $sTest .
                           $this->getTransaction() .
                           $this->getCurrency() .
                           $this->getAmount() .
                           $this->getReference() .
                           $this->getCode() .
                           $this->getHashkey()
        );
        return $this->getHash() === $hashVerify;
    }

    private function restoreStock($aProducts){
       global $db;
        foreach ($aProducts as $product){
            $oProduct = $db->Execute('SELECT products_virtual FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . $product['id'] );
            $virtual = (bool) $oProduct->fields['products_virtual'];
            if (!$virtual){
                $sql = "UPDATE " . TABLE_PRODUCTS .
                       " SET products_quantity = products_quantity+" . ( int ) $product['quantity'] .
                       " WHERE products_id = " . ( int ) $product['id'];
                $db->Execute( $sql );
            }
        }
    }

    private function getTransactionInfo($code){
        if ($code == 0){
            $sComment = 'Payment pending, waiting for acquirer.';
            $iStatusUpdate = 1;
        }elseif ($code >= 200 && $code < 300){
            $sComment = 'Payment completed.';
            $iStatusUpdate = 2;
        } elseif ($code >= 300 && $code < 400){
            $status = $code == 309 ? 'canceled' : 'failed;';
            $sComment = 'Payment '.$status.'';
            $iStatusUpdate = 4;
        }elseif ($code >= 700 && $code < 800){
            $sComment = 'Payment pending, waiting for confirmation from the bank.';
            $iStatusUpdate = 1;
        }
        $sComment .= '<br>CardGate status: '.$code;
        return ['comment' => $sComment, 'status' =>$iStatusUpdate];
    }

    private function updateOrder($aInfo, $orderId){
        $aData = ["orders_status" => $aInfo['status']];
        zen_db_perform( TABLE_ORDERS, $aData, 'update', 'orders_id=' . $orderId );

        $aData = array( 'orders_id' => $orderId,
                        'orders_status_id' => $aInfo['status'],
                        'date_added' => 'now()',
                        'comments' => $aInfo['comment'],
                        'customer_notified' => 1
        );
        zen_db_perform( TABLE_ORDERS_STATUS_HISTORY, $aData );
    }

    private function isProcessed(){
        $this->setCardgateOrder();
        $iStatus = $this->getCardgateOrder()['status'];
        return ($iStatus >=200 && $iStatus < 400);
    }

    private function process($code){
        require_once(DIR_WS_CLASSES . 'shipping.php');
        require_once(DIR_WS_CLASSES . 'payment.php');
        require_once(DIR_WS_CLASSES . 'order.php');
        require_once(DIR_WS_CLASSES . 'order_total.php');
        global $zco_notifier, $currencies, $order, $order_total_modules;

        $_SESSION = unserialize( $this->getCardgateOrder()['sessionstr'] );
        $sPayment = $_SESSION['payment'];
        $aShipping = $_SESSION['shipping'];
        $oCart = $_SESSION['cart'];
        $aProducts = $oCart->get_products();
        $aTransactionInfo = $this->getTransactionInfo($code);

        // include the constants for the payment module
        include_once (DIR_WS_MODULES . 'payment/' . $sPayment . '.php');
        $payment_modules = new payment( $sPayment );
        $payment_module = $payment_modules->paymentClass;
        $shipping_modules = new shipping( $aShipping );

        $orderId = $this->getCardgateOrder()['order_id'];
        if ($orderId == 0) {
            $order               = new order();
            $order->info['comment'] = $aTransactionInfo['comment'];
            $order_total_modules = new order_total();
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS' );
            $order_totals = $order_total_modules->process();
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS' );
            $orderId = (int) $order->create( $order_totals );
            $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE' );
        } else {
            $order = new order($orderId);
            $order->info['comment'] = $aTransactionInfo['comment'];
        }

        $aData= array( 'transaction_id' => $this->getTransaction(), 'status' => $code, 'order_id' => $orderId );
        zen_db_perform( 'CGP_orders_table', $aData, 'update', 'ref_id=' . $this->getReference() );

       $this->updateOrder($aTransactionInfo, $orderId);

        //process a pending payment
        $iPreviousStatus = $this->getCardgateOrder()['status'];
        if ($iPreviousStatus >=700 && $iPreviousStatus < 800){
            if ($code >= 300 && $code <400){
                $this->restoreStock($aProducts);
            }
            $order->send_order_email( $orderId, 2 );
            return;
        }

        $order->create_add_products( $orderId, 2 );
        $_SESSION['order_number_created'] = $orderId;
        $GLOBALS[$_SESSION['payment']]->transaction_id = $this->getTransaction();
        $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS' );
        $order->send_order_email( $orderId, 2 );
        $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL' );

        // Prepare sales-tracking data for use by notifier class
        $ototal = $order_subtotal = $credits_applied = 0;

        foreach ($order_totals as $order_total){
            if ($order_total['code'] == 'ot_subtotal') {$order_subtotal = $order_total['value'];}
            if ( $$order_total['code']->credit_class == true ) {$credits_applied += $order_total['value'];}
            if ( $order_total['code'] == 'ot_total' ) {$ototal = $order_total['value'];}
        }

        $commissionable_order = ($order_subtotal - $credits_applied);
        $commissionable_order_formatted = $currencies->format( $commissionable_order );
        $_SESSION['order_summary']['order_number'] = $orderId;
        $_SESSION['order_summary']['order_subtotal'] = $order_subtotal;
        $_SESSION['order_summary']['credits_applied'] = $credits_applied;
        $_SESSION['order_summary']['order_total'] = $ototal;
        $_SESSION['order_summary']['commissionable_order'] = $commissionable_order;
        $_SESSION['order_summary']['commissionable_order_formatted'] = $commissionable_order_formatted;
        $_SESSION['order_summary']['coupon_code'] = $order->info['coupon_code'];
        $zco_notifier->notify( 'NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES', 'cardgateipn' );

        // add products that have been subtracted by the order.
        if ($code >= 300 && $code < 400) {
            $this->restoreStock( $aProducts );
        }
    }

    public function processCallback(){
        if ($this->hashCheck()){
            $code = $this->getCode();
            if (!$this->isProcessed()){
                $this->process( $code );
            }
            return $this->getTransaction() . '.' . $code;
        } else {
            return 'Hashcheck failed!';
        }
    }
}

$oCardgate = new cardgate(($_POST));
echo $oCardgate->processCallback();

