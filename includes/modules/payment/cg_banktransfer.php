<?php
/**
 * Copyright (c) 2023 CardGate B.V.
 * Author: Richard Schoots
 * For more infomation about CardGate: http://www.cardgate.com
 * Released under the GNU General Public License
 * Zen-Cart version Copyright (c) 2011 GetZenned: http://www.getzenned.nl
 */
require_once( __DIR__ . '/../../../cardgate/cg_generic.php' );

class cg_banktransfer extends cg_generic{

	var $payment_option = 'banktransfer';
}
