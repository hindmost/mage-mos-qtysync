<?php
class Mos_QtySync_Model_Config_Cron extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH = 'crontab/jobs/qtysync_import/schedule/cron_expr';
    const CRON_MODEL_PATH  = 'crontab/jobs/qtysync_import/run/model';

    protected function _afterSave() {
        Mage::app()->getStore()->resetConfig();
        $n_on = intval(Mos_QtySync_Model_Sync::getConfig('enable'));
        $freq = Mos_QtySync_Model_Sync::getConfig('cron_frequency');
        $time = Mos_QtySync_Model_Sync::getConfig('cron_time');
        $a_time = is_array($time)? $time :
            ($time? array_filter(preg_split('/[,.:]/', $time, 3), 'trim') : 0);
        if (!is_array($a_time) || count($a_time) < 2 || !$freq)
            return $this;

        $freq_m = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;
        $freq_w = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;

        $s_cronexpr = '';
        if ($n_on) {
            $a_cronexpr = array(
                intval($a_time[1]),                    # Minute
                intval($a_time[0]),                    # Hour
                ($freq == $freq_m) ? '1' : '*',        # Day of the Month
                '*',                                   # Month of the Year
                ($freq == $freq_w) ? date('N') : '*',  # Day of the Week
            );
            $s_cronexpr = implode(' ', $a_cronexpr);
        }

        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($s_cronexpr)
                ->setPath(self::CRON_STRING_PATH)
                ->save();

            Mage::getModel('core/config_data')
                ->load(self::CRON_MODEL_PATH, 'path')
                ->setValue((string) Mage::getConfig()->getNode(self::CRON_MODEL_PATH))
                ->setPath(self::CRON_MODEL_PATH)
                ->save();
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('adminhtml')->__('Unable to save the cron expression.'));
        }

        return $this;
    }
}
