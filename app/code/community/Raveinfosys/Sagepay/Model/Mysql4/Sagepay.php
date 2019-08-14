<?php

class Raveinfosys_Sagepay_Model_Mysql4_Sagepay extends Mage_Core_Model_Mysql4_Abstract
{

    public function _construct()
    {
        $this->_init('sagepay/sagepay', 'sagepay_id');
    }

}
