<?php

$installer = $this;

$installer->startSetup();

$installer->run("

-- DROP TABLE IF EXISTS {$this->getTable('sagepay')};
	CREATE TABLE {$this->getTable('sagepay')} (
  `sagepay_id` int(11) NOT NULL auto_increment,
  `parent_id` int(11),
  `order_id` int(11) NOT NULL default 0,
  `vps_tx_id` varchar(200) NOT NULL default '',
  `vendor_tx_code` varchar(200) NOT NULL default '',
  `security_key` varchar(200) NOT NULL default '',
  `threed_auth` varchar(200) NOT NULL default '',
  `tx_auth_no` varchar(200) NOT NULL default 0,
  `order_status` text NOT NULL default '',
  `payment_type` int(2) NOT NULL default 0,
  `authorised` int(2) NOT NULL default 0,
  `is_refund` int(2) NOT NULL default 0,
  `refund_status` text NOT NULL default '',
  `is_void` int(2) NOT NULL default 0,
  `void_status` text NOT NULL default '',
  `customer_name` varchar(200) NOT NULL default '',
  `customer_email` varchar(200) NOT NULL default '',
  `card_type` varchar(200) NOT NULL default '',
  `cv2` varchar(50) NOT NULL default '',
  `card_holder_name` varchar(200) NOT NULL default '',
  `customer_contact` varchar(200) NOT NULL default '',
  PRIMARY KEY  (`sagepay_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- DROP TABLE IF EXISTS {$this->getTable('sagepay_transaction_detail')};
CREATE TABLE {$this->getTable('sagepay_transaction_detail')} (
  `transaction_id` int(11) NOT NULL auto_increment,
  `sagepay_id` int(11) NOT NULL default 0,
  `order_id` int(11) NOT NULL default 0,
  `vps_tx_id` varchar(200) NOT NULL default '',
  `vendor_tx_code` varchar(200) NOT NULL default '',
  `security_key` varchar(200) NOT NULL default '',
  `order_status` text NOT NULL default '',
  `customer_name` varchar(200) NOT NULL default '',
  `customer_email` varchar(200) NOT NULL default '',
  `card_type` varchar(200) NOT NULL default '',
  `mode` varchar(50) NOT NULL default '',
  `card_holder_name` varchar(200) NOT NULL default '',
  `threed_secure` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();
