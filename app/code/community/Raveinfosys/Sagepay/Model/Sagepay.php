<?php

class Raveinfosys_Sagepay_Model_Sagepay extends Mage_Core_Model_Abstract {

  public function _construct(){
  
        parent::_construct();
        $this->_init('sagepay/sagepay');
    }

  public function saveOrderDetail($result,$order_id){ 
	$data['order_id'] = $order_id;
	$data['vps_tx_id'] = $result['VPSTxId'];
	$data['vendor_tx_code'] = $result['VendorTxCode'];
	$data['security_key'] = $result['SecurityKey'];
	$data['tx_auth_no'] = $result['TxAuthNo'];
	$data['order_status'] = $result['StatusDetail'];
	$data['threed_auth'] = $result['3DSecureStatus'];
	$data['payment_type'] = 1;
	$data['authorised'] = 1;
	$data['customer_email'] = $result['CustomerEmail'];
	$model = Mage::getModel('sagepay/sagepay');
	$model->setData($data)
		         ->save();
	return $model->getId();
	
  }
  
  public function saveCardDetail($id,$data){
	  $model = Mage::getModel('sagepay/sagepay')->load($id);
	  $model->setCustomerName($data['CustomerName'])
			->setCardType($data['CardType'])
			->setCardHolderName($data['CardHolderName'])
			->setCustomerContact($data['CustomerContact'])
		    ->save();
			
	}
  
  public function saveAuthDetail($result,$order_id){ 
	$data['order_id'] = $order_id;
	$data['vps_tx_id'] = $result['VPSTxId'];
	$data['vendor_tx_code'] = $result['VendorTxCode'];
	$data['security_key'] = $result['SecurityKey'];
	if($result['TxAuthNo'])
	$data['tx_auth_no'] = $result['TxAuthNo'];
	else
	$data['tx_auth_no'] = '0';
	$data['order_status'] = $result['StatusDetail'];
	$data['threed_auth'] = $result['3DSecureStatus'];
	$data['payment_type'] = 2;
	$data['customer_email'] = $result['CustomerEmail'];
	$model = Mage::getModel('sagepay/sagepay');
	$model->setData($data)
		         ->save();
	return $model->getId();			 
	
  }
  
  public function saveAuthorisedDetail($result,$vendor_tx_code,$parent_data,$order_id){ 
	
	$parent_model = Mage::getModel('sagepay/sagepay')->load($parent_data->getSagepayId());
	$parent_model->setAuthorised(1)
		    ->save()->unsetData();
			
	$data['order_id'] = $order_id;
	$data['parent_id'] = $parent_data->getSagepayId();
	$data['vps_tx_id'] = $result['VPSTxId'];
	$data['vendor_tx_code'] = $vendor_tx_code;
	$data['security_key'] = $result['SecurityKey'];
	$data['tx_auth_no'] = $result['TxAuthNo'];
	$data['order_status'] = $result['StatusDetail'];
	$data['threed_auth'] = $result['3DSecureStatus'];
	$data['authorised'] = 1;
	$data['customer_email'] = $parent_data->getCustomerEmail();
	$data['customer_name'] = $parent_data->getCustomerName();
	$data['card_type'] = $parent_data->getCardType();
	$data['card_holder_name'] = $parent_data->getCardHolderName();
	$data['customer_contact'] = $parent_data->getCustomerContact();
	
	$model = Mage::getModel('sagepay/sagepay');
	$model->setData($data)
		         ->save();
	
	return $model->getId();				
	
  }
  
  public function saveRefundDetail($result,$id){
	$model = Mage::getModel('sagepay/sagepay')->load($id);
	$model->setIsRefund(1)
			->setRefundStatus($result['StatusDetail'])
		    ->save();
  }
  
  public function saveVoidDetail($result,$id){
	$model = Mage::getModel('sagepay/sagepay')->load($id);
	$model->setIsVoid(1)
			->setVoidStatus($result['StatusDetail'])
		    ->save();
  }
  
  public function canVoid($order){
    $data = $this->getCollection()->addFieldToFilter('order_id',$order->getIncrementId())->getFirstItem();
	if($data->getPaymentType()==2 && $data->getIsVoid()!=1 && $data->getAuthorised() !=1)
	return true;
	else
	return false;
  }
  
  public function logTransaction($data){
    if(Mage::getStoreConfig('payment/sagepay/debug')){
		if($data["request"]["CardNumber"]!=''){
		  $data["request"]["CardNumber"] = '****';
		  $data["request"]["IssueNumber"] = '****';
		  $data["request"]["CV2"] = '****';
		  $data["request"]["CardType"] = '****';
		  $data["request"]["CardHolder"] = '****';
		}
		$data["request"]["Vendor"] = '****';
		Mage::log($data, null, 'raveinfosys_sagepay.log',1);
	}
  }
  
  
}	
?>	