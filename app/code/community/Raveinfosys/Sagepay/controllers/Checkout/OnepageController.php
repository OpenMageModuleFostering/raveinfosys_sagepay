<?php
require_once 'Mage/Checkout/controllers/OnepageController.php';
class Raveinfosys_Sagepay_Checkout_OnepageController extends Mage_Checkout_OnepageController
{
    
    public function threedsecureAction(){
         if ($this->_expireAjax()) {
            return;
        }

        $result = array();
        try {
            if ($requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }
			if ($data = $this->getRequest()->getPost('payment', false)) {
                $this->getOnepage()->getQuote()->getPayment()->importData($data);
            }
			$gateway_result = Mage::getModel('sagepay/payment')->registerTransaction($data);
			$session = Mage::getSingleton('core/session');
			$session->setGatewayResult($gateway_result)->setPaymentdata($data);
			if($gateway_result['Status'] == '3DAUTH' && $gateway_result["PAReq"]!='' && $gateway_result["MD"]!=''){
				$redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
				$url = Mage::getUrl('checkout/onepage/sagepay3dCheck');
				Mage::register('url',$url);
				
				if (!isset($result['error'])) {
				$result['goto_section'] = 'threedsecure';
				$html = Mage::app()->getLayout()->createBlock('checkout/onepage')->setTemplate('sagepay/checkout/3dredirect.phtml')->toHtml();
				$result['update_section'] = array(
						'name' => 'threedsecure',
						'html' => $html
						#'html' => "<script>alert('hello');window.open('".$url."','mypopup','status=1,width=500,height=500,scrollbars=1');</script>"
				);
				
				}
		   }
		  elseif(in_array($gateway_result['Status'],Mage::getSingleton('sagepay/config')->getExpectedSuccess())) {
			$this->_forward('saveOrder');
		  }
		  		  
        } catch (Exception $e) {
            Mage::logException($e);
			$result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
	
	public function sagepay3dcheckAction(){
	  
	    $this->loadLayout();
		$block = $this->getLayout()->createBlock('Mage_Core_Block_Template','sagepay_3d_form',array('template' => 'sagepay/sagepay.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
		$this->renderLayout();
	 
	}
	
	public function threedsuccessAction(){
		$this->loadLayout();
		try {
		$result = Mage::getModel('sagepay/payment')->complete3d($_POST);
		if (in_array($result['Status'],Mage::getSingleton('sagepay/config')->getExpectedSuccess())) {
			$session = Mage::getSingleton('core/session');
			$session->setGatewayResult($result);
			Mage::register('status',1);
			Mage::register('url',Mage::getUrl('checkout/onepage/savesagepayorder'));
		}
	   } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success']  = false;
            $result['error']    = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
			
		$this->renderLayout();	
			
	}
	
	public function saveSagePayOrderAction(){
		try {
		    $session = Mage::getSingleton('core/session');
			if ($data =  $session->getPaymentdata()) {
                $this->getOnepage()->getQuote()->getPayment()->importData($data);
            }
			$this->getOnepage()->saveOrder();
			$this->getOnepage()->getQuote()->save();
		} catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
           
        }
		$this->_redirect('checkout/onepage/success');
		
	}	
	
	
}
