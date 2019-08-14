<?php
class Raveinfosys_Sagepay_Block_Adminhtml_Sagepay extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_sagepay';
    $this->_blockGroup = 'sagepay';
    $this->_headerText = Mage::helper('sagepay')->__('Sagepay Transaction Details');
    $this->_addButtonLabel = Mage::helper('sagepay')->__('Add Item');
    parent::__construct();
	$this->_removeButton('add');
  }
}