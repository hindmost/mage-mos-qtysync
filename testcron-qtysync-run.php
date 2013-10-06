<?php
require_once('app/Mage.php');

Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1); 

Mage::app();

umask(0);

try {
    Mage::getModel('qtysync/sync')->runImport();
    echo "OK";
}
catch (Exception $e) {
    Mage::logException($e);
    Mage::printException($e);
}
