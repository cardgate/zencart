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

class cgp_creditcard extends cgp_generic{

    var $debug 					= false;
    var $module_payment_type 	= 'MODULE_PAYMENT_CGP_CREDITCARD';
    var $payment_option 		= 'creditcard';
    
    var $code, $title, $description, $enabled;

	// class constructor
    function __construct() {
    	global $order;
      	$this->code 			= 'cgp_creditcard';
      	$this->title 			= $this->checkoutDisplay();
      	$this->order_status		= constant($this->module_payment_type.'_ORDER_INITIAL_STATUS_ID');
      	$this->sort_order 		= constant($this->module_payment_type.'_SORT_ORDER');
      	$this->description 		= constant($this->module_payment_type.'_TEXT_DESCRIPTION').' <br><b>module version: '.parent::version.'</b>';
      	$this->enabled 			= $this->module_payment_type.'_STATUS';
      	$this->form_action_url 	= $this->cg_action_url();
      
      	if (is_object($order)) $this->update_status();
    }    
}
?>
