<?php
class Mos_QtySync_Block_Adminhtml_Catalog_Product extends Mage_Adminhtml_Block_Catalog_Product
{
    public function __construct() {
        parent::__construct();

        if (Mage::getModel('qtysync/sync')->isButtonEnabled()) {
            $this->_addButton('runsync', array(
                'label'     => Mage::helper('catalog')->__('Run MerchantOS Qty Sync'),
                'onclick'   => 'setLocation(\'' . $this->getUrl('qtysync/syncman/run') .'\')'
            ));
        }
    }
}
