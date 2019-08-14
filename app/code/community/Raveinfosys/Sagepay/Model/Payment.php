<?php

class Raveinfosys_Sagepay_Model_Payment extends Mage_Payment_Model_Method_Ccsave
{

    protected $_code = 'sagepay';
    protected $_isGateway = true;   //Is this payment method a gateway (online auth/charge) ?
    protected $_canAuthorize = true;   //Can authorize online?
    protected $_canCapture = true;   //Can capture funds online?
    protected $_canCapturePartial = true;   //Can capture partial amounts online?
    protected $_canRefund = true;   //Can refund online?
    protected $_canRefundInvoicePartial = true;   //Can refund invoices partially?
    protected $_canVoid = true;   //Can void transactions online?
    protected $_canUseInternal = true;   //Can use this payment method in administration panel?
    protected $_canUseCheckout = true;   //Can show this payment method as an option on checkout payment page?
    protected $_canUseForMultishipping = true;   //Is this payment method suitable for multi-shipping checkout?
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = true;
    protected $_infoBlockType = 'payment/info_cc';
    protected $_canSaveCc = false;

    const ACTION_PAYMENT = 'PAYMENT';
    const ACTION_VOID = 'VOID';
    const ACTION_AUTHORISE = 'AUTHORISE';
    const ACTION_AUTHENTICATE = 'AUTHENTICATE';
    const ACTION_REFUND = 'REFUND';
    const ACTION_CANCEL = 'CANCEL';

    private $urls;
    private $Vendor;
    private $Basket = array();
    public $AccountType = 'E';
    public $GiftAidPayment = 0;
    public $ApplyAVSCV2 = 0;
    public $Apply3DSecure = 0;
    public $Description = "Sagepay Direct Transaction.";
    public $VendorTxCode;
    public $TxType;
    public $Amount;
    public $Currency;
    public $CardHolder;
    public $CardNumber;
    public $StartDate;
    public $ExpiryDate;
    public $IssueNumber;
    public $CV2;
    public $CardType;
    public $BillingSurname;
    public $BillingFirstnames;
    public $BillingAddress1;
    public $BillingAddress2;
    public $BillingCity;
    public $BillingPostCode;
    public $BillingCountry;
    public $BillingState;
    public $BillingPhone;
    public $DeliverySurname;
    public $DeliveryFirstnames;
    public $DeliveryAddress1;
    public $DeliveryAddress2;
    public $DeliveryCity;
    public $DeliveryPostCode;
    public $DeliveryCountry;
    public $DeliveryState;
    public $DeliveryPhone;
    public $CustomerEmail;
    public $OrderIncrementId;
    public $result = array('Status' => 'BEGIN');

    public function __construct($vendor, $mode = 'simulator')
    {

        $this->urls = $this->getConfigModel()->getGatewayURL();
        $this->Vendor = $this->getConfigModel()->getVendor();
    }

    public function isMsOnOverview()
    {
        return ($this->_getQuote()->getIsMultiShipping() && $this->getMsActiveStep() == 'multishipping_overview');
    }

    public function getMsActiveStep()
    {
        return Mage::getSingleton('checkout/type_multishipping_state')->getActiveStep();
    }

    protected function _getReservedOid()
    {

        if ($this->isMsOnOverview() && ($this->_getQuote()->getPayment()->getMethod() == 'sagepay')) {
            return null;
        }

        $orderId = $this->getSagepayModel()->getReservedId();

        if (!$orderId) {

            if (!$this->_getQuote()->getReservedOrderId() || $this->_orderIdAlreadyUsed($this->_getQuote()->getReservedOrderId())) {
                $this->_getQuote()->unsReservedOrderId();
                $this->_getQuote()->reserveOrderId()->save();
            }
            $orderId = $this->_getQuote()->getReservedOrderId();
            $this->getSagepayModel()->setReservedId($orderId);
        }

        if ($this->isMsOnOverview()) {
            $this->getSagepayModel()->setReservedId(null);
        }
        return $orderId;
    }

    protected function _orderIdAlreadyUsed($orderId)
    {
        if (!$orderId)
            return false;

        $existingOrder = Mage::getModel("sales/order")->loadByIncrementId($orderId);

        if (!$existingOrder->getId()) {
            return false;
        }
        return true;
    }

    protected function _getQuote()
    {

        $opQuote = Mage::getSingleton('checkout/type_onepage')->getQuote();
        $adminQuote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        $rqQuoteId = Mage::app()->getRequest()->getParam('qid');
        if ($adminQuote->hasItems() === false && (int) $rqQuoteId) {
            $opQuote->setQuote(Mage::getModel('sales/quote')->loadActive($rqQuoteId));
        }
        return ($adminQuote->hasItems() === true) ? $adminQuote : $opQuote;
    }

    protected function _getTrnVendorTxCode()
    {
        return $this->_getReservedOid();
    }

    public function validate()
    {
        $info = $this->getInfoInstance();
        $order_amount = 0;
        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            $order_amount = (double) $info->getQuote()->getBaseGrandTotal();
        } elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
            $order_amount = (double) $info->getOrder()->getQuoteBaseGrandTotal();
        }

        $order_min = $this->getConfigData('min_order_total');
        $order_max = $this->getConfigData('max_order_total');
        if (!empty($order_max) && (double) $order_max < $order_amount) {
            Mage::throwException("Order amount greater than permissible Maximum order amount.");
        }
        if (!empty($order_min) && (double) $order_min > $order_amount) {
            Mage::throwException("Order amount less than required Minimum order amount.");
        }

        parent::validate();
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        if (Mage::app()->getStore()->isAdmin()) {
            if ($amount <= 0) {
                Mage::throwException(Mage::helper('paygate')->__('Invalid amount for transaction.'));
            }

            $this->Amount = number_format($amount, 2, '.', '');
            $this->TxType = self::ACTION_AUTHENTICATE;
            $this->AccountType = 'M';
            $this->Apply3DSecure = 2;
            $result = $this->__processPayment($payment);

            if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedSuccess())) {
                $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
                $orderId = $payment->getOrder()->getIncrementId();
                $this->result['CustomerEmail'] = $this->CustomerEmail;
                $this->result['CustomerName'] = $this->BillingFirstnames . ' ' . $this->BillingSurname;
                $this->result['CardType'] = $this->CardType;
                $this->result['CardHolderName'] = $this->CardHolder;
                $this->result['CustomerContact'] = $this->BillingPhone;
                $this->result['VendorTxCode'] = $this->VendorTxCode;
                $sagepay_id = $this->getSagepayModel()->saveAuthDetail($this->result, $orderId);
                $this->getSagepayModel()->saveCardDetail($sagepay_id, $this->result);
                $this->getTransactionModel()->saveTransactionDetail($orderId, $this->result, $this->VendorTxCode, self::ACTION_AUTHENTICATE, $sagepay_id);
                $this->_addTransaction(
                        $payment, $this->VendorTxCode, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, array('is_transaction_closed' => 0));

                $payment->setSkipTransactionCreation(true);
                return $this;
            } else {
                $payment->setSkipTransactionCreation(true);
                if ($result == 'ERROR') {
                    $error = $this->result;
                    Mage::throwException($error['Errors'][0]);
                } else if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
                    Mage::throwException("Gateway error : {" . (string) $this->result['Errors'][0] . "}");
                }
            }
        } else {
            $session = Mage::getSingleton('core/session');
            if ($data = $session->getGatewayResult()) {
                $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
                $orderId = $payment->getOrder()->getIncrementId();
                $sagepay_id = $this->getSagepayModel()->saveAuthDetail($data, $orderId);
                $this->getSagepayModel()->saveCardDetail($sagepay_id, $data);
                $this->getTransactionModel()->saveTransactionDetail($orderId, $data, $data['VendorTxCode'], self::ACTION_AUTHENTICATE, $sagepay_id);
                $this->_addTransaction(
                        $payment, $data['VendorTxCode'], Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, array('is_transaction_closed' => 0));
                $session->unsGatewayResult()->unsPaymentdata();
                $payment->setSkipTransactionCreation(true);
            }
            return $this;
        }
    }

    public function capture(Varien_Object $payment, $amount)
    {
        if (Mage::app()->getStore()->isAdmin()) {
            if ($amount <= 0) {
                Mage::throwException(Mage::helper('paygate')->__('Invalid amount for transaction.'));
            }

            $data = $this->getSagepayModel()->getCollection()->addFieldToFilter('order_id', $payment->getOrder()->getIncrementId())->getFirstItem();

            if ($data->getOrderId() == $payment->getOrder()->getIncrementId()) {
                $this->authorizePayment($payment, number_format($amount, 2, '.', ''), $data);

                if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedSuccess())) {
                    $orderId = $payment->getOrder()->getIncrementId();
                    $sagepay_id = $this->getSagepayModel()->saveAuthorisedDetail($this->result, $this->VendorTxCode, $data, $payment->getOrder()->getIncrementId());
                    $this->getTransactionModel()->saveTransactionDetail($orderId, $this->result, $this->VendorTxCode, self::ACTION_PAYMENT, $sagepay_id);
                    $payment->setStatus(self::STATUS_APPROVED);
                    $payment->setLastTransId((string) $this->VendorTxCode);
                    if (!$payment->getParentTransactionId() || (string) $this->VendorTxCode != $payment->getParentTransactionId()) {
                        $payment->setTransactionId((string) $this->VendorTxCode);
                    }
                    return $this;
                } else {
                    $payment->setSkipTransactionCreation(true);
                    if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
                        $error = $this->result;
                        Mage::throwException($error['Errors'][0]);
                    }
                }
            }
            $this->Amount = number_format($amount, 2, '.', '');
            $this->TxType = self::ACTION_PAYMENT;
            $this->AccountType = 'M';
            $this->Apply3DSecure = 2;
            $result = $this->__processPayment($payment);

            if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedSuccess())) {
                $orderId = $payment->getOrder()->getIncrementId();
                $this->result['CustomerEmail'] = $this->CustomerEmail;
                $this->result['CustomerName'] = $this->BillingFirstnames . ' ' . $this->BillingSurname;
                $this->result['CardType'] = $this->CardType;
                $this->result['CardHolderName'] = $this->CardHolder;
                $this->result['CustomerContact'] = $this->BillingPhone;
                $this->result['VendorTxCode'] = $this->VendorTxCode;
                $sagepay_id = $this->getSagepayModel()->saveOrderDetail($this->result, $orderId);
                $this->getSagepayModel()->saveCardDetail($sagepay_id, $this->result);
                $this->getTransactionModel()->saveTransactionDetail($orderId, $this->result, $this->VendorTxCode, self::ACTION_PAYMENT, $sagepay_id);
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setLastTransId((string) $this->VendorTxCode);
                if (!$payment->getParentTransactionId() || (string) $this->VendorTxCode != $payment->getParentTransactionId()) {
                    $payment->setTransactionId((string) $this->VendorTxCode);
                }
                return $this;
            } else {
                $payment->setSkipTransactionCreation(true);
                if ($result == 'ERROR') {
                    $error = $this->result;
                    Mage::throwException($error['Errors'][0]);
                } else if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
                    Mage::throwException("Gateway error : {" . (string) $this->result['Errors'][0] . "}");
                }
            }
        } else {
            $session = Mage::getSingleton('core/session');
            if ($data = $session->getGatewayResult()) {
                $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
                $orderId = $payment->getOrder()->getIncrementId();
                $sagepay_id = $this->getSagepayModel()->saveOrderDetail($data, $orderId);
                $this->getSagepayModel()->saveCardDetail($sagepay_id, $data);
                $this->getTransactionModel()->saveTransactionDetail($orderId, $data, $data['VendorTxCode'], $data['TxType'], $sagepay_id);
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setLastTransId((string) $data['VendorTxCode']);
                if (!$payment->getParentTransactionId() || (string) $data['VendorTxCode'] != $payment->getParentTransactionId()) {
                    $payment->setTransactionId((string) $data['VendorTxCode']);
                }
                $session->unsGatewayResult()->unsPaymentdata();
            }
            return $this;
        }
    }

    public function authorizePayment(Varien_Object $payment, $amount, $data)
    {
        $params = array();
        $params['VPSProtocol'] = urlencode('2.23');
        $params['TxType'] = self::ACTION_AUTHORISE;
        $params['Vendor'] = urlencode(Mage::getStoreConfig('payment/sagepay/vendor_name'));
        $params['VendorTxCode'] = time() . rand(0, 9999) . '-' . $payment->getOrder()->getIncrementId();
        $this->VendorTxCode = $params['VendorTxCode'];
        $params['Amount'] = urlencode($amount);
        $params['Description'] = $this->Description;
        $params['RelatedVPSTxId'] = $data->getVpsTxId();     //VPSTxId of main transaction
        $params['RelatedVendorTxCode'] = urlencode($data->getVendorTxCode());         //VendorTxCode of main transaction
        $params['RelatedSecurityKey'] = urlencode($data->getSecurityKey());       //securitykey of main transaction

        $this->result = $this->requestPost($this->urls['authorise'], $params);
        if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
            $this->result['Errors'] = array();
            foreach (preg_split("\n", $this->result['StatusDetail']) as $error) {
                $this->result['Errors'] = array_merge($this->result['Errors'], $this->getError($error));
            }
        }
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if ($payment->getRefundTransactionId() && $amount > 0) {
            $data = $this->getSagepayModel()->getCollection()->addFieldToFilter('vendor_tx_code', trim($payment->getRefundTransactionId()))->getFirstItem();

            $params = array();
            $params['VPSProtocol'] = urlencode('2.23');
            $params['TxType'] = self::ACTION_REFUND;
            $params['Vendor'] = urlencode(Mage::getStoreConfig('payment/sagepay/vendor_name'));
            $params['VendorTxCode'] = time() . rand(0, 9999) . '-' . $payment->getOrder()->getIncrementId();
            $params['Amount'] = urlencode(number_format($amount, 2, '.', ''));
            $params['Currency'] = urlencode(Mage::app()->getStore()->getCurrentCurrencyCode());
            $params['Description'] = $this->Description;
            $params['RelatedVPSTxId'] = $data->getVpsTxId();
            $params['RelatedVendorTxCode'] = urlencode($data->getVendorTxCode());
            $params['RelatedSecurityKey'] = urlencode($data->getSecurityKey());
            $params['RelatedTxAuthNo'] = urlencode($data->getTxAuthNo());

            $this->result = $this->requestPost($this->urls['refund'], $params);

            if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
                $this->result['Errors'] = array();
                foreach (preg_split("\n", $this->result['StatusDetail']) as $error) {
                    $this->result['Errors'] = array_merge($this->result['Errors'], $this->getError($error));
                }
            }
            if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedSuccess())) {
                $orderId = $payment->getOrder()->getIncrementId();
                $this->getSagepayModel()->saveRefundDetail($this->result, $data->getSagepayId());
                $this->getTransactionModel()->saveTransactionDetail($orderId, $this->result, $params['VendorTxCode'], self::ACTION_REFUND, $data->getSagepayId());
                $payment->setStatus(Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
                $payment->setLastTransId((string) $params['VendorTxCode']);
                if (!$payment->getParentTransactionId() || (string) $params['VendorTxCode'] != $payment->getParentTransactionId()) {
                    $payment->setTransactionId((string) $params['VendorTxCode']);
                }
                return $this;
            } else {
                if ($result == 'ERROR') {
                    $error = $this->result;
                    Mage::throwException($error['Errors'][0]);
                } else if (in_array($result, $this->getConfigModel()->getExpectedError())) {
                    Mage::throwException("Gateway error : {" . (string) $this->result['Errors'][0] . "}");
                }
            }
        }
        Mage::throwException(Mage::helper('paygate')->__('Error in refunding the payment.'));
    }

    public function void(Varien_Object $payment)
    {

        $orderId = $payment->getOrder()->getIncrementId();
        $data = $this->getSagepayModel()->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
        $params = array();
        $params['VPSProtocol'] = urlencode('2.23');
        $params['TxType'] = self::ACTION_VOID;
        $params['Vendor'] = urlencode(Mage::getStoreConfig('payment/sagepay/vendor_name'));
        $params['VendorTxCode'] = urlencode($data->getVendorTxCode());
        $params['VPSTxId'] = $data->getVpsTxId();
        $params['SecurityKey'] = urlencode($data->getSecurityKey());
        $params['TxAuthNo'] = urlencode($data->getTxAuthNo());

        $this->result = $this->requestPost($this->urls['void'], $params);
        if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
            $this->result['Errors'] = array();
            foreach (preg_split("\n", $this->result['StatusDetail']) as $error) {
                $this->result['Errors'] = array_merge($this->result['Errors'], $this->getError($error));
            }
        }

        if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedSuccess())) {
            $this->getSagepayModel()->saveVoidDetail($this->result, $data->getSagepayId());
            $this->getTransactionModel()->saveTransactionDetail($orderId, $this->result, $params['VendorTxCode'], self::ACTION_VOID, $data->getSagepayId());
            $payment->setStatus(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID);
            $payment->setLastTransId((string) $params['VendorTxCode']);
            if (!$payment->getParentTransactionId() || (string) $params['VendorTxCode'] != $payment->getParentTransactionId()) {
                $payment->setTransactionId((string) $params['VendorTxCode']);
            }
            return $this;
        } else {
            if ($result == 'ERROR') {
                $error = $this->result;
                Mage::throwException($error['Errors'][0]);
            } else if (in_array($result, $this->getConfigModel()->getExpectedError())) {
                Mage::throwException("Gateway error : {" . (string) $this->result['Errors'][0] . "}");
            }
        }
        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $orderId = $payment->getOrder()->getIncrementId();
        $data = $this->getSagepayModel()->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
        $params = array();
        $params['VPSProtocol'] = urlencode('2.23');
        $params['TxType'] = self::ACTION_CANCEL;
        $params['Vendor'] = urlencode(Mage::getStoreConfig('payment/sagepay/vendor_name'));
        $params['VendorTxCode'] = urlencode($data->getVendorTxCode());
        $params['VPSTxId'] = $data->getVpsTxId();
        $params['SecurityKey'] = urlencode($data->getSecurityKey());
        $params['TxAuthNo'] = urlencode($data->getTxAuthNo());

        $this->result = $this->requestPost($this->urls['cancel'], $params);
        if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
            $this->result['Errors'] = array();
            foreach (preg_split("\n", $this->result['StatusDetail']) as $error) {
                $this->result['Errors'] = array_merge($this->result['Errors'], $this->getError($error));
            }
        }

        if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedSuccess())) {
            $this->getSagepayModel()->saveVoidDetail($this->result, $data->getSagepayId());
            $this->getTransactionModel()->saveTransactionDetail($orderId, $this->result, $params['VendorTxCode'], self::ACTION_CANCEL, $data->getSagepayId());
            $payment->setStatus(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID);
            $payment->setLastTransId((string) $params['VendorTxCode']);
            if (!$payment->getParentTransactionId() || (string) $params['VendorTxCode'] != $payment->getParentTransactionId()) {
                $payment->setTransactionId((string) $params['VendorTxCode']);
            }
            return $this;
        } else {
            if ($result == 'ERROR') {
                $error = $this->result;
                Mage::throwException($error['Errors'][0]);
            } else if (in_array($result, $this->getConfigModel()->getExpectedError())) {
                Mage::throwException("Gateway error : {" . (string) $this->result['Errors'][0] . "}");
            }
        }
        return $this;
    }

    public function __processPayment(Varien_Object $payment)
    {

        $this->Currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        $payment->setAmount($amount);
        $this->CardHolder = $payment->getCcOwner();
        $this->CardNumber = $payment->getCcNumber();
        $this->ExpiryDate = date("my", strtotime('01-' . $payment->getCcExpMonth() . '-' . $payment->getCcExpYear()));
        $this->IssueNumber = $payment->getCcSsIssue();
        $this->CV2 = $payment->getCcCid();
        $this->CardType = $this->getConfigModel()->getCcCode($payment->getCcType());
        $order = $this->_getQuote();
        $this->setGatewayOrderData($order);
        return $this->register();
    }

    public function setGatewayOrderData($order)
    {

        if (!empty($order)) {
            $BillingAddress = $order->getBillingAddress();
            $this->BillingSurname = $BillingAddress->getLastname();
            $this->BillingFirstnames = $BillingAddress->getFirstname();
            $this->BillingAddress1 = $BillingAddress->getStreet(1);
            $this->BillingAddress2;
            $this->BillingCity = $BillingAddress->getCity();
            $this->BillingPostCode = $BillingAddress->getPostcode();
            $this->BillingCountry = $BillingAddress->getCountry();
            $this->BillingState = Mage::getModel('directory/region')->load($BillingAddress->getRegionId())->getCode();
            $this->BillingPhone = $BillingAddress->getTelephone();
            if (!$order->getIsVirtual())
                $ShippingAddress = $order->getShippingAddress();
            else
                $ShippingAddress = $order->getBillingAddress();
            $this->DeliverySurname = $ShippingAddress->getLastname();
            $this->DeliveryFirstnames = $ShippingAddress->getFirstname();
            $this->DeliveryAddress1 = $ShippingAddress->getStreet(1);
            $this->DeliveryAddress2;
            $this->DeliveryCity = $ShippingAddress->getCity();
            $this->DeliveryPostCode = $ShippingAddress->getPostcode();
            $this->DeliveryCountry = $ShippingAddress->getCountry();
            $this->DeliveryState = Mage::getModel('directory/region')->load($ShippingAddress->getRegionId())->getCode();
            $this->DeliveryPhone = $ShippingAddress->getTelephone();

            $this->CustomerEmail = $order->getCustomerEmail();
            $this->VendorTxCode = time() . rand(0, 9999) . '-' . $this->_getTrnVendorTxCode(); //$this->_getQuote()->getReservedOrderId();
            $product_array = array();
            foreach ($order->getAllItems() as $item) {
                if (!$item->getParentItemId())
                    $this->addLine($item->getName(), $item->getQty(), $item->getPrice(), $item->getTaxAmount());
            }
            foreach ($order->getAllAddresses() as $address) {
                //Add shipping amount
                if ($ship_amount = $address->getData('shipping_amount'))
                    $this->addLine($address->getData('shipping_description'), 1, $ship_amount, 0);

                //Add discount amount
                if ($address->getData('discount_amount') < 0)
                    $this->addLine('Discount', 1, $address->getData('discount_amount'), 0);
            }
        }
    }

    public function register()
    {

        $errors = array();
        if (!$this->Vendor)
            $errors[] = 'The Vendor must be provided';
        if (!$this->VendorTxCode)
            $errors[] = 'The VendorTxCode must be provided';
        if (!is_numeric($this->Amount))
            $errors[] = 'The Amount field must be specified, and must be numeric.';
        if (!$this->Currency)
            $errors[] = 'Currency must be specified, eg GBP.';
        if (!$this->CardHolder)
            $errors[] = 'CardHolder must be specified.';
        if (!$this->CardNumber)
            $errors[] = 'CardNumber must be specified.';
        if (!$this->ExpiryDate)
            $errors[] = 'ExpiryDate must be specified.';
        if ($this->IssueNumber and ! preg_match("/^\d{1,2}$/", $this->IssueNumber))
            $errors[] = 'IssueNumber is invalid.';
        if ($this->CardType == 'AMEX' and ! preg_match("/^\d{4}$/", $this->CV2))
            $errors[] = 'CV2 must be 4 numbers long.';
        if ($this->CardType != 'AMEX' and ! preg_match("/^\d{3}$/", $this->CV2))
            $errors[] = 'CV2 must be 3 numbers long.';
        if (!in_array($this->CardType, array('VISA', 'MC', 'DELTA', 'SOLO', 'MAESTRO', 'UKE', 'AMEX', 'DC', 'JCB', 'LASER')))
            $errors[] = 'CardType must be one of VISA, MC, DELTA, SOLO, MAESTRO, UKE, AMEX, DC, JCB, LASER';
        if (!$this->BillingSurname)
            $errors[] = 'BillingSurname must be specified.';
        if (!$this->BillingFirstnames)
            $errors[] = 'BillingFirstnames must be specified.';
        if (!$this->BillingAddress1)
            $errors[] = 'BillingAddress1 must be specified.';
        if (!$this->BillingCity)
            $errors[] = 'BillingCity must be specified.';
        if (!$this->BillingPostCode)
            $errors[] = 'BillingPostCode must be specified.';
        if (!$this->BillingCountry)
            $errors[] = 'BillingCountry must be specified.';
        if ($this->BillingCountry == 'US' and ! $this->BillingState)
            $errors['BillingState'] = 'BillingState mut be specified.';
        if (!$this->DeliverySurname)
            $errors[] = 'DeliverySurname must be specified.';
        if (!$this->DeliveryFirstnames)
            $errors[] = 'DeliveryFirstnames must be specified.';
        if (!$this->DeliveryAddress1)
            $errors[] = 'DeliveryAddress1 must be specified.';
        if (!$this->DeliveryCity)
            $errors[] = 'DeliveryCity must be specified.';
        if (!$this->DeliveryPostCode)
            $errors[] = 'DeliveryPostCode must be specified.';
        if (!$this->DeliveryCountry)
            $errors[] = 'DeliveryCountry must be specified.';
        if ($this->DeliveryCountry == 'US' and ! $this->DeliveryState)
            $errors[] = 'DeliveryState mut be specified.';
        if ($this->CustomerEmail and ! preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+\.([a-zA-Z0-9\._-]+)+$/", $this->CustomerEmail))
            $errors[] = 'CustomerEmail is invalid.';

        if (count($errors)) {
            $this->result = array('Status' => 'ERRORCHECKFAIL', 'Errors' => $errors);
            return 'ERROR';
        }

        $data = array(
            'VPSProtocol' => 2.23,
            'TxType' => $this->TxType,
            'Vendor' => $this->Vendor,
            'VendorTxCode' => $this->VendorTxCode,
            'Amount' => number_format($this->Amount, 2, '.', ''),
            'Currency' => $this->Currency,
            'Description' => $this->Description,
            'CardHolder' => $this->CardHolder,
            'CardNumber' => $this->CardNumber,
            'ExpiryDate' => $this->ExpiryDate,
            'IssueNumber' => $this->IssueNumber,
            'CV2' => $this->CV2,
            'CardType' => $this->CardType,
            'BillingSurname' => $this->BillingSurname,
            'BillingFirstnames' => $this->BillingFirstnames,
            'BillingAddress1' => $this->BillingAddress1,
            'BillingAddress2' => $this->BillingAddress2,
            'BillingCity' => $this->BillingCity,
            'BillingPostCode' => $this->BillingPostCode,
            'BillingCountry' => $this->BillingCountry,
            'BillingState' => $this->BillingCountry == 'US' ? $this->BillingState : '',
            'BillingPhone' => $this->BillingPhone,
            'DeliverySurname' => $this->DeliverySurname,
            'DeliveryFirstnames' => $this->DeliveryFirstnames,
            'DeliveryAddress1' => $this->DeliveryAddress1,
            'DeliveryAddress2' => $this->DeliveryAddress2,
            'DeliveryCity' => $this->DeliveryCity,
            'DeliveryPostCode' => $this->DeliveryPostCode,
            'DeliveryCountry' => $this->DeliveryCountry,
            'DeliveryState' => $this->DeliveryCountry == 'US' ? $this->DeliveryState : '',
            'DeliveryPhone' => $this->DeliveryPhone,
            'CustomerEmail' => $this->CustomerEmail,
            'GiftAidPayment' => $this->GiftAidPayment,
            'AccountType' => $this->AccountType,
            'ClientIPAddress' => $_SERVER['REMOTE_ADDR'],
            'ApplyAVSCV2' => $this->ApplyAVSCV2,
            'Apply3DSecure' => $this->Apply3DSecure
        );

        if (sizeof($this->Basket)) {
            $data['Basket'] = count($this->Basket);
            foreach ($this->Basket as $line) {
                $data['Basket'] .= ':' . $line['description'];
                $data['Basket'] .= ':' . $line['quantity'];
                $data['Basket'] .= ':' . number_format($line['value'], 2, '.', '');
                $data['Basket'] .= ':' . number_format($line['tax'], 2, '.', '');
                $data['Basket'] .= ':' . number_format(($line['value'] + $line['tax']), 2, '.', '');
                $data['Basket'] .= ':' . number_format(($line['quantity'] * ($line['value'] + $line['tax'])), 2, '.', '');
            }
        }

        $this->result = $this->requestPost($this->urls['register'], $data);

        if (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
            $this->result['Errors'] = array();
            foreach (preg_split("\n", $this->result['StatusDetail']) as $error) {
                $this->result['Errors'] = array_merge($this->result['Errors'], $this->getError($error));
            }
        }
        if ($this->result['Status'] == '3DAUTH') {

            $this->result['VendorTxCode'] = $this->VendorTxCode;
            return $this->result;
        }
        return $this->result;
    }

    public function addLine($description, $quantity, $value, $tax = 0)
    {
        $this->Basket[] = array(
            'description' => $description,
            'quantity' => $quantity,
            'value' => $value,
            'tax' => $tax
        );
    }

    public static function recover3d()
    {
        $sagepay = unserialize($_SESSION['sagepay_obj']);
        unset($_SESSION['sagepay_obj']);
        return $sagepay;
    }

    public static function is3dResponse()
    {
        if (isset($_REQUEST['PaRes']) and isset($_REQUEST['MD']) and isset($_SESSION['sagepay_obj'])) {
            return true;
        } else {
            return false;
        }
    }

    public function complete3d($post_data)
    {
        $session = Mage::getSingleton('core/session');
        $data = array(
            'PARes' => $post_data['PaRes'],
            'MD' => $post_data['MD']
        );

        $result = $this->requestPost($this->urls['3dsecure'], $data);
        $vendor = $session->getGatewayResult();
        $result['VendorTxCode'] = $vendor['VendorTxCode'];
        $result['CustomerEmail'] = $vendor['CustomerEmail'];
        $result['CustomerName'] = $vendor['CustomerName'];
        $result['CardType'] = $vendor['CardType'];
        $result['CardHolderName'] = $vendor['CardHolderName'];
        $result['CustomerContact'] = $vendor['CustomerContact'];
        $result['TxType'] = $vendor['TxType'];
        return $result;
    }

    public function status()
    {
        return $this->result['Status'];
    }

    private function getError($message)
    {
        $chunks = preg_split(' : ', $message, 2);
        if ($chunks[0] == '3048') {
            return array('CardNumber' => 'The card number is invalid.');
        }
        if ($chunks[0] == '4022') {
            return array('CardNumber' => 'The card number is not valid for the card type selected.');
        }
        if ($chunks[0] == '4023') {
            return array('CardNumber' => 'The issue number must be provided.');
        }
        return array($message);
    }

    private function requestPost($url, $data)
    {
        $fields_string = http_build_query($data);
        $log['request'] = $data;
        set_time_limit(60);
        $output = array();
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_HEADER, 0);
        curl_setopt($curlSession, CURLOPT_POST, 1);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlSession, CURLOPT_TIMEOUT, 30);
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 2);
        $response = explode(chr(10), curl_exec($curlSession));
        $log['response'] = $response;
        $this->getSagepayModel()->logTransaction($log);
        unset($this->CardNumber);
        unset($this->ExpiryDate);
        unset($this->CV2);

        if (curl_error($curlSession)) {
            $output['Status'] = "FAIL";
            $output['StatusDetail'] = curl_error($curlSession);
        }
        curl_close($curlSession);
        for ($i = 0; $i < count($response); $i++) {
            $splitAt = strpos($response[$i], "=");
            $output[trim(substr($response[$i], 0, $splitAt))] = trim(substr($response[$i], ($splitAt + 1)));
        }
        return $output;
    }

    public static function country($code)
    {
        $countries = $this->getSagepayModel()->countries();
        return $countries[$code];
    }

    public function registerTransaction($data)
    {
        if (Mage::getStoreConfig('payment/sagepay/payment_action') == 'authorize') {
            $this->TxType = self::ACTION_AUTHENTICATE;
        } elseif (Mage::getStoreConfig('payment/sagepay/payment_action') == 'authorize_capture') {
            $this->TxType = self::ACTION_PAYMENT;
        }
        return $this->sagePayRegisterPayment($data);
    }

    public function sagePayRegisterPayment($data)
    {

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $amount = $quote->getData('grand_total');

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for transaction.'));
        }

        $this->Currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        $this->Amount = $amount;
        $this->CardHolder = $data['cc_owner'];
        $this->CardNumber = $data['cc_number'];
        $this->ExpiryDate = date("my", strtotime('01-' . $data['cc_exp_month'] . '-' . $data['cc_exp_year']));
        $this->CV2 = $data['cc_cid'];
        $this->CardType = $this->getConfigModel()->getCcCode($data['cc_type']);

        $this->setGatewayOrderData($quote);
        $result = $this->register();
        if ($result == 'ERROR') {
            $error = $this->result;
            Mage::throwException($error['Errors'][0]);
        } elseif (in_array($this->result['Status'], $this->getConfigModel()->getExpectedError())) {
            if (!$this->result['Errors'][0])
                Mage::throwException("Gateway error : {" . (string) $this->result['StatusDetail'] . "}");
            else
                Mage::throwException("Gateway error : {" . (string) $this->result['Errors'][0] . "}");
        }
        else {
            $result['CustomerEmail'] = $this->CustomerEmail;
            $result['CustomerName'] = $this->BillingFirstnames . ' ' . $this->BillingSurname;
            $result['CardType'] = $this->CardType;
            $result['CardHolderName'] = $this->CardHolder;
            $result['CustomerContact'] = $this->BillingPhone;
            $result['TxType'] = $this->TxType;
            $result['VendorTxCode'] = $this->VendorTxCode;
        }
        return $result;
    }

    protected function _addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType, array $transactionDetails = array(), $message = false)
    {

        $payment->setTransactionId($transactionId);
        $payment->resetTransactionAdditionalInfo();
        foreach ($transactionDetails as $key => $value) {
            $payment->setData($key, $value);
        }

        $transaction = $payment->addTransaction($transactionType, null, false, $message);
        foreach ($transactionDetails as $key => $value) {
            $payment->unsetData($key);
        }
        $payment->unsLastTransId();

        $transaction->setMessage($message);

        return $transaction;
    }

    public function getConfigModel()
    {
        return Mage::getSingleton('sagepay/config');
    }

    public function getSagepayModel()
    {
        return Mage::getSingleton('sagepay/sagepay');
    }

    public function getTransactionModel()
    {
        return Mage::getSingleton('sagepay/transaction');
    }

}

?>