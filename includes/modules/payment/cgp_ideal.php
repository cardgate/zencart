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

class cgp_ideal extends cgp_generic{

	var $debug 					= false;
	var $module_payment_type 	= 'MODULE_PAYMENT_CGP_IDEAL';
	var $payment_option 		= 'ideal';

	var $code, $title, $description, $enabled;

	// class constructor
	function __construct() {
		global $order;
		$this->code 			= 'cgp_ideal';
		$this->title   = $this->checkoutDisplay();
		$this->enabled = $this->module_payment_type . '_STATUS';
		$this->order_status = $this->getOrderStatus();
		$this->sort_order   = $this->getSortOrder();
		$this->description  = $this->getDescription();
		$this->form_action_url 	= $this->cg_action_url();

		if (is_object($order)) $this->update_status();
	}
	
	/**
	 * Check if configuration_value is stored in DB.
	 *
	 * @return boolean
	 */
	function check() {
	    global $db;
	    
	    $this->resetIssuers();
	    if ( !isset( $this->check ) ) {
	        $check_query = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $this->module_payment_type . '_MODE' . "'" );
	        $this->check = $check_query->RecordCount();
	    }
	    return (0 < $this->check);
	}
	
	function resetIssuers() {
	    global $db;
	    
	    $oResult = $db->Execute("SELECT configuration_id FROM ". TABLE_CONFIGURATION ." WHERE configuration_key='MODULE_PAYMENT_CGP_IDEAL_ISSUER_REFRESH'");
	    $sConfigurationId = $oResult->fields['configuration_id'];
	    if ($sConfigurationId === null ){
	        $resultId = $db->Execute("INSERT INTO ". TABLE_CONFIGURATION ."(configuration_title, configuration_key, configuration_value)
                        VALUES ( 'Issuer Refresh', 'MODULE_PAYMENT_CGP_IDEAL_ISSUER_REFRESH',0)");
	    } else {
	        $resultId = $db->Execute("UPDATE ". TABLE_CONFIGURATION ." SET configuration_value = '0' WHERE configuration_key = 'MODULE_PAYMENT_CGP_IDEAL_ISSUER_REFRESH'");
	    }
	}
}
?>
