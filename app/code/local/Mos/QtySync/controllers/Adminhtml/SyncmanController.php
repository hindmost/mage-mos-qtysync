<?php
class Mos_QtySync_Adminhtml_SyncmanController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction() {
        $this->_initAction()->renderLayout();
    }

    public function runAction() {
        try {
            Mage::getModel('qtysync/sync')->runImport();
            $this->_getSession()->addSuccess($this->__('MerchantOS Sync Import completed'));
        }
        catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Error during Sync Import: %s', $e->getMessage()));
        }
        $this->_redirect('adminhtml/catalog_product/index');
    }
}
