<?php
/**
* Copyright (c)2013 Card Gate Plus
* Author: Richard Schoots
* For more infomation about Card Gate Plus: http://www.cardgate.com
* Released under the GNU General Public License
* Zen-Cart version Copyright (c) 2011 GetZenned: http://www.getzenned.nl
*/

$pos = strpos(__FILE__,'includes/modules/payment/'); 
$base_path = substr(__FILE__,0,$pos);
require_once($base_path.'cardgateplus/cgp_generic.php');

class cgp_paypal extends cgp_generic{

    var $debug 					= false;
    var $module_payment_type 	= 'MODULE_PAYMENT_CGP_PAYPAL';
    var $payment_option 		= 'paypal';
    
    var $code, $title, $description, $enabled;

	// class constructor
    function __construct() {
    	global $order;
      	$this->code 			= 'cgp_paypal';
	    $this->title   = $this->checkoutDisplay();
	    $this->enabled = $this->module_payment_type . '_STATUS';
	    $this->order_status = $this->getOrderStatus();
	    $this->sort_order   = $this->getSortOrder();
	    $this->description  = $this->getDescription();
	    $this->form_action_url 	= $this->cg_action_url();

	    if (is_object($order)) $this->update_status();
    }    
}
?>
