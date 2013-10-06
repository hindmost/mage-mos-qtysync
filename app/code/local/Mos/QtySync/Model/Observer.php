<?php
class Mos_QtySync_Model_Observer
{
    /**
     * Handler for the 'checkout_submit_all_after' event
     */
    public function handleOrderSubmit($observer) {
        $o_order = $observer->getData('order');
        if (!is_object($o_order)) return;
        $id = $o_order->getId();
        if (!$id) return;
        try {
            Mage::getModel('qtysync/sync')->runExport($id);
        }
        catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Handler for the 'checkout_onepage_controller_success_action' event
     */
    public function handleOrderSuccess($observer) {
        $a_ids = $observer->getData('order_ids');
        if (!is_array($a_ids) && count($a_ids)) return;
        try {
            Mage::getModel('qtysync/sync')->runExport($a_ids[0]);
        }
        catch (Exception $e) {
            Mage::logException($e);
        }
        
    }
}
