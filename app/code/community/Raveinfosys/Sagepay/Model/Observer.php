<?php

class Raveinfosys_Sagepay_Model_Observer {
	public function disableMethod(Varien_Event_Observer $observer){
		$moduleName="Raveinfosys_Sagepay";
		if('sagepay'==$observer->getMethodInstance()->getCode()){
			if(!Mage::getStoreConfigFlag('advanced/modules_disable_output/'.$moduleName)) {
				//nothing here, as module is ENABLE
			} else {
				$observer->getResult()->isAvailable=false;
			}
			
		}
	}
	
	public function layoutUpdate($observer)
	{
	    if(!Mage::getStoreConfigFlag('advanced/modules_disable_output/'.$moduleName) && !Mage::getStoreConfigFlag('payment/sagepay/active')){
			return $observer;
		}
		$quote = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getData();
		if($quote['method']=='sagepay'){
			$updates = $observer->getEvent()->getUpdates();
			$updates->addChild('sagepay_checkout_review')
					->file = 'sagepay_checkout2.xml';
		 }		
	}
}
?>