<?php

class Raveinfosys_Sagepay_Model_Config extends Mage_Core_Model_Abstract {
     private $config = array();
  
     public function _construct(){
		 $sage_pay_urls = array(
			'live' => array(
				'register' => 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp',
				'refund' => 'https://live.sagepay.com/gateway/service/refund.vsp',
				'void' => 'https://live.sagepay.com/gateway/service/void.vsp',
				'cancel' => 'https://live.sagepay.com/gateway/service/cancel.vsp',
				'authorise' => 'https://live.sagepay.com/gateway/service/authorise.vsp',
				'3dsecure' => 'https://live.sagepay.com/gateway/service/direct3dcallback.vsp'
			),
			'test' => array(
				'register' => 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp',
				'refund' => 'https://test.sagepay.com/gateway/service/refund.vsp',
				'void' => 'https://test.sagepay.com/gateway/service/void.vsp',
				'cancel' => 'https://test.sagepay.com/gateway/service/cancel.vsp',
				'authorise' => 'https://test.sagepay.com/gateway/service/authorise.vsp',
				'3dsecure' => 'https://test.sagepay.com/gateway/service/direct3dcallback.vsp'
			),
			'simulator' => array(
				'register' => 'https://test.sagepay.com/Simulator/VSPDirectGateway.asp',
				'refund' => 'https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorRefundTx',
				'void' => 'https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorVoidTx',
				'cancel' => 'https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorCancelTx',
				'release' => 'https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorReleaseTx',
				'authorise' => 'https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorAuthoriseTx',
				'3dsecure' => 'https://test.sagepay.com/Simulator/VSPDirectCallback.asp'
			)
		);
		$this->config = $sage_pay_urls;
     }
	
	 public function getVendor(){
	  return Mage::getStoreConfig('payment/sagepay/vendor_name');
	 }
	 
	 public function getGatewayURL(){
	  $mode = Mage::getStoreConfig('payment/sagepay/mode');
	  if(in_array($mode, array('live', 'test', 'simulator')))
	  return $this->config[$mode] ;
	  else
	  return $this->config['test']	;
	 }
	 
	 public function getExpectedError(){
	    return array('INVALID', 'MALFORMED', 'REJECTED', 'NOTAUTHED', 'ERROR', 'FAIL');
	 }
	 
	 public function getExpectedSuccess(){
	    return array('OK','AUTHENTICATED','REGISTERED');
	 }
	 
	 public function getCcCode($code){
		  $data = array(
						 'VI'=> 'VISA',
						 'AE'=> 'AMEX',
						 'MC'=> 'MC',
						 'DI'=> 'DC',
						 'JCB'=> 'JCB',
						 'SM'=> 'MAESTRO',
						 'SO'=> 'SOLO',
						 'OT'=> 'Other',
						); 
			return $data[$code];	
	 }
  
  
}	
?>	