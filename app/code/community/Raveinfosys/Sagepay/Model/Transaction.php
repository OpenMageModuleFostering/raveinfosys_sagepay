<?php

class Raveinfosys_Sagepay_Model_Transaction extends Mage_Core_Model_Abstract
{

    public function _construct()
    {

        parent::_construct();
        $this->_init('sagepay/transaction');
    }

    public function saveTransactionDetail($order_id, $result, $vendor_tx_code, $mode, $sagepay_id)
    {
        $transaction_detail = array();
        $card_detail = Mage::getModel('sagepay/sagepay')->load($sagepay_id);
        $transaction_detail['order_id'] = $order_id;
        $transaction_detail['vendor_tx_code'] = $vendor_tx_code;
        $transaction_detail['vps_tx_id'] = $result['VPSTxId'];
        $transaction_detail['security_key'] = $result['SecurityKey'];
        $transaction_detail['tx_auth_no'] = $result['TxAuthNo'];
        $transaction_detail['order_status'] = $result['Status'];
        $transaction_detail['customer_name'] = $card_detail['customer_name'];
        $transaction_detail['customer_email'] = $card_detail['customer_email'];
        $transaction_detail['customer_contact'] = $card_detail['customer_contact'];
        $transaction_detail['card_holder_name'] = $card_detail['card_holder_name'];
        $transaction_detail['card_type'] = $card_detail['card_type'];
        $transaction_detail['mode'] = $mode;
        $transaction_detail['threed_secure'] = $result['3DSecureStatus'];
        $this->setData($transaction_detail)->save();
    }

}

?>