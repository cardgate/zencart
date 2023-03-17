<?php

require_once dirname(__FILE__).'/cardgate-clientlib-php/init.php';
chdir( '../' );
require( 'includes/application_top.php' );

 class Redirect{
     private $_siteID;
     private $_merchantID;
     private $_hashKey;
     private $_apiKey;
     private $_testMode;

    public function __construct(){
       $this->_testMode = $this->setTestMode();
       $this->_apiKey = $this->setApiKey();
       $this->_hashKey = $this->setHashKey();
       $this->_merchantID = $this->setMerchantId();
       $this->_siteID = $this->setSiteId();

    }
    private function setTestMode(){
       return (bool)(constant( 'MODULE_PAYMENT_CG_CARDGATE_MODE' ) === 'Test' ? 1 : 0);
    }
    private function setSiteId(){
        return (integer) constant( 'MODULE_PAYMENT_CG_CARDGATE_SITEID');
    }
    private function setMerchantId(){
         return (int) constant( 'MODULE_PAYMENT_CG_CARDGATE_MERCHANTID');
    }
    private function setApiKey(){
         return (string) constant( 'MODULE_PAYMENT_CG_CARDGATE_APIKEY');
    }
    private function setHashKey(){
         return (string) constant( 'MODULE_PAYMENT_CG_CARDGATE_HASHKEY');
    }
     private function setAddress($aAddress, \cardgate\api\Consumer &$oConsumer_, $sMethod_)
     {
         $oConsumer_->$sMethod_()->setFirstName($aAddress['firstname']);
         $oConsumer_->$sMethod_()->setLastName($aAddress['lastname']);
         $oConsumer_->$sMethod_()->setAddress($aAddress['street_address']);
         $oConsumer_->$sMethod_()->setCity($aAddress['city']);
         if (!( $aAddress['state'] === null || empty($aAddress['state']) )) {
             $oConsumer_->$sMethod_()->setState($aAddress['state']);
         }
         $oConsumer_->$sMethod_()->setZipCode($aAddress['postcode']);
         $oConsumer_->$sMethod_()->setCountry($aAddress['country']['iso_code_2']);
     }
     private function hasValue ($aArray, $key){
        return  !(($aArray[$key] === null) || empty($aArray[$key]));
     }
    public function registerOrder($order) {

        if (!$this->hashCheck($order)){
            echo 'hashcheck failed!';
            exit;
        }

        try {
            $oCardGate = new cardgate\api\Client( $this->_merchantID, $this->_apiKey, $this->_testMode );

            $oCardGate->setIp( $_SERVER['REMOTE_ADDR'] );
            $oCardGate->setLanguage( 'nl' );
            $oCardGate->version()->setPlatformName( $order['platform_name'] );
            $oCardGate->version()->setPlatformVersion( $order['platform_version'] );
            $oCardGate->version()->setPluginName( $order['plugin_name'] );
            $oCardGate->version()->setPluginVersion( $order['plugin_version'] );

            $transaction = $oCardGate->transactions()->create(
                $this->_siteID,
                (int) $order['amount'],
                $order['currency']
            );

            $transaction->setPaymentMethod( $order['paymentmethod'] );
            $transaction->setCallbackUrl( $this->getUrl( 'cardgate/cg_process.php' ) );
            $transaction->setRedirectUrl($this->getUrl('?main_page=checkout_success&action=empty_cart'));
            $transaction->setFailureUrl($this->getUrl('?main_page=checkout_payment'));
            $transaction->setReference( (string) $order['reference'] );
            $transaction->setDescription( $order['description'] );


            // Add the consumer data to the transaction.
            $consumer = $transaction->getConsumer();
            $consumer->setEmail($order['customer']['email_address']);
            $customer = $order['customer'];
            if ($this->hasValue($customer, 'telephone')) {
                $consumer->setPhone($customer['telephone']);
            }
            $this->setAddress($order['billing'], $consumer, 'address');
            if ($this->hasValue($order, 'shipping')){
                $this->setAddress($order['shipping'], $consumer, 'shippingAddress');
            }

            $cart = $transaction->getCart();
            foreach ($order['cartitems'] as $item){
                $cartItem = $cart->addItem(
                    $item['type'],
                    $item['sku'],
                    $item['name'],
                    $item['quantity'],
                    $item['price']
                );
                if ($item['vat'] > 0) {
                    $cartItem->setVat( $item['vat'] );
                }
                $cartItem->setVatIncluded($item['vat_inc']);
                $cartItem->setVatAmount($item['vat_amount']);
            }
            $transaction->register();

            $actionUrl = $transaction->getActionUrl();
            if (null !== $actionUrl) {
                // Redirect the consumer to the CardGate payment gateway.
                zen_redirect($actionUrl);
            } else {
                // Payment methods without user interaction are not yet supported.
                throw new StartException('unsupported payment action');
            }

            // Add extra debug info during development
            $oCardGate->setDebugLevel( $oCardGate::DEBUG_RESULTS );

        } catch ( \cardgate\api\Exception $oException_ ) {
            echo 'something went wrong: ' . $oException_->getCode() . ': ' . $oException_->getMessage();
        }
    }
    private function hashCheck($order){
        $sCardgateHash = md5( ($this->_testMode ? "TEST" : "") . $this->_siteID . $order['amount'] .$order['reference'] . $this->_hashKey );
        return $order['cardgatehash'] === $sCardgateHash;
    }
    public function getUrl($parameters){
        $protocol = (!empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain_name = $_SERVER['HTTP_HOST'] . '/';
        return $protocol.$domain_name.$parameters;
    }
 }

if ($_POST['cardgateredirect'] === 'true'){
    $aOrder = unserialize(json_decode($_POST['zen_order']));
    $oRedirect = new Redirect();
    $oRedirect->registerOrder($aOrder);
}

