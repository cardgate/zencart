<?php
/**
 * Copyright (c) 2023 CardGate B.V.
 * Author: Richard Schoots
 * For more infomation about CardGate: http://www.cardgate.com
 * Released under the GNU General Public License
 * Zen-Cart version Copyright (c) 2011 GetZenned: http://www.getzenned.nl
 */
require_once( __DIR__ . '/../../../cardgate/cg_generic.php' );

class cg_ideal extends cg_generic{

	var $payment_option = 'ideal';

	public function __construct() {
		parent::__construct();
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
	        $check_query = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $this->module_payment_type . '_ZONE' . "'" );
	        $this->check = $check_query->RecordCount();
	    }
	    return (0 < $this->check);
	}
	
	function resetIssuers() {
	    global $db;
	    
	    $oResult = $db->Execute("SELECT configuration_id FROM ". TABLE_CONFIGURATION ." WHERE configuration_key='MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH'");
	    $sConfigurationId = $oResult->fields['configuration_id'];
	    if ($sConfigurationId === null ){
	        $resultId = $db->Execute("INSERT INTO ". TABLE_CONFIGURATION ."(configuration_title, configuration_key, configuration_value)
                        VALUES ( 'Issuer Refresh', 'MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH',0)");
	    } else {
	        $resultId = $db->Execute("UPDATE ". TABLE_CONFIGURATION ." SET configuration_value = '0' WHERE configuration_key = 'MODULE_PAYMENT_CG_IDEAL_ISSUER_REFRESH'");
	    }
	}
}
?>
