<?php

class Raveinfosys_Sagepay_Block_Adminhtml_Sagepay_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('sagepayGrid');
      $this->setDefaultSort('transaction_id');
      $this->setDefaultDir('ASC');
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      $collection = Mage::getModel('sagepay/transaction')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
     
	  $this->addColumn('order_id', array(
          'header'    => Mage::helper('sagepay')->__('Order Id'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'order_id',
      ));
	  
	  $this->addColumn('vendor_tx_code', array(
          'header'    => Mage::helper('sagepay')->__('Vendor Tx Code'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'vendor_tx_code',
      ));
	  
	 /*  $this->addColumn('security_key', array(
          'header'    => Mage::helper('sagepay')->__('Security Key'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'security_key',
      )); */
	  
	  $this->addColumn('order_status', array(
          'header'    => Mage::helper('sagepay')->__('Status'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'order_status',
      ));
	  
	  $this->addColumn('customer_name', array(
          'header'    => Mage::helper('sagepay')->__('Customer Name'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'customer_name',
      ));
	  
	  $this->addColumn('mode', array(
          'header'    => Mage::helper('sagepay')->__('Transaction Type'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'mode',
      ));
	  
	  $this->addColumn('customer_email', array(
          'header'    => Mage::helper('sagepay')->__('Email'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'customer_email',
      ));
	  
	  $this->addColumn('threed_secure', array(
          'header'    => Mage::helper('sagepay')->__('3D Auth'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'threed_secure',
      ));
	  
	  $this->addColumn('card_holder_name', array(
          'header'    => Mage::helper('sagepay')->__('Card Holder Name'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'card_holder_name',
      ));
	  
	  $this->addColumn('card_type', array(
          'header'    => Mage::helper('sagepay')->__('Card Type'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'card_type',
      ));
      
		
		$this->addExportType('*/*/exportCsv', Mage::helper('sagepay')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('sagepay')->__('XML'));
	  
      return parent::_prepareColumns();
  }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('sagepay_id');
        $this->getMassactionBlock()->setFormFieldName('sagepay');

        $this->getMassactionBlock()->addItem('delete', array(
             'label'    => Mage::helper('sagepay')->__('Delete'),
             'url'      => $this->getUrl('*/*/massDelete'),
             'confirm'  => Mage::helper('sagepay')->__('Are you sure?')
        ));

        
        return $this;
    }

  public function getRowUrl($row)
  {
      return '';
  }

}