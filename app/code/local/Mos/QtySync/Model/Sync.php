<?php
class Mos_QtySync_Model_Sync extends Mos_QtySync_Model_Api_Client
{
    protected $idShop = 1;
    protected $sFieldSku = 'customSku';
    protected $nBuff = 100;
    protected $bEnable = false;
    protected $bShowBtn = false;

    function __construct() {
        static $a_names = array(
            'api_key', 'api_id', 'api_url_account', 'api_url_session',
            'shop_id', 'sku_field', 'read_buffer', 'show_import_btn',
        );
        $a_vals = array_fill(0, count($a_names), '');
        foreach ($a_names as $i => $name)
            if ($v = self::getConfig($name)) $a_vals[$i] = $v;
        list($key, $id, $url_acct, $url_sess, $v1, $v2, $v3, $v4) = $a_vals;
        if ($v1)
            $this->idShop = intval($v1);
        if ($v2)
            $this->sFieldSku = $v2;
        if ($v3)
            $this->nBuff = intval($v3);
        if ($v4)
            $this->bShowBtn = $v4;
        $this->bEnable = intval(self::getConfig('enable'));
        if (!$this->bEnable) return;
        parent::__construct($key, $id, $url_acct, $url_sess);
        if (!$id && ($id = $this->getAcctId())) {
            self::setConfig('api_id', $id);
        }
    }

    /**
     * @return bool
     */
    public function isEnabled() {
        return $this->bEnable;
    }

    /**
     * @return bool
     */
    public function isButtonEnabled() {
        return $this->bEnable && $this->bShowBtn;
    }

    /**
     * @return bool
     */
    public function runImport() {
        if (!$this->bEnable) return false;
        return $this->_syncImport($this->_loadExtQtysAll());
    }

    /**
     * @return bool
     */
    public function runExport($idOrder) {
        if (!$this->bEnable) return false;
        return $this->_syncExportOrder($idOrder);
    }

    /**
     * @param array $aExtQtys - MerchantOS item quantities
     * @param bool $bDeduct - deduct (true) or assign (false) MerchantOS quantity from/to Magento product
     * @return bool
     */
    protected function _syncImport($aExtQtys, $bDeduct = false) {
        if (!is_array($aExtQtys) || !count($aExtQtys)) return false;
        $a_skus = array_keys($aExtQtys);
        $o_coll = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('sku', array('in'=>$a_skus));
        foreach ($o_coll->getAllIds() as $id) {
            $sku = Mage::getModel('catalog/product')->load($id)->getSku();
            $n_extqty = intval($aExtQtys[$sku]);
            $o_stockitem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($id);
            if (!$sku || !$o_stockitem) continue;
            $n_qty0 = $o_stockitem->getQty();
            $o_stockitem->setData('qty',
                $n_qty_new = $bDeduct? $n_qty0- min($n_extqty, $n_qty0) : $n_extqty
            );
            $o_stockitem->save();
        }
        return true;
    }

    /**
     * @param array $idOrder - ID of the last order in Magento
     * @return bool
     */
    protected function _syncExportOrder($idOrder) {
        if (!$idOrder || !$this->getApiStatus()) return false;
        $order = Mage::getModel('sales/order')->load($idOrder);
        $a_qtys = array();
        foreach ($order->getAllItems() as $item) {
            $o_prod = Mage::getModel('catalog/product')->load($item->getProductId());
            $sku = $o_prod->getSku();
            if (!$sku) continue;
            $a_qtys[$sku] = intval($item->getQtyToInvoice());
        }
        if (!count($a_qtys)) return false;
        $a_data = array('Item', 'ItemShops');
        foreach ($a_qtys as $sku => $n_qty) {
            $o_shop = $this->_loadExtItemshop($sku);
            if (!$sku || !$n_qty || !$o_shop || !$o_shop->itemID) continue;
            $n = intval((string)$o_shop->qoh);
            $o_shop->qoh = ($n- $n_qty > 0)? $n- $n_qty : 0;
            $a_data[2] = $o_shop;
            $s_resp = $this->request('Item', 'Update', 0, $o_shop->itemID, $a_data);
        }
    }

    /**
     * @return SimpleXMLElement|false
     */
    protected function _loadExtItemshop($sSku) {
        if (!$this->getApiStatus()) return false;
        $a_qry = array(
            'limit' => 1,
            'load_relations' => '["ItemShops"]',
            $this->sFieldSku => $sSku,
        );
        $s_resp = $this->request('Item', 'Read', $a_qry);
        if (!$s_resp) return false;
        $o_xml = self::createXMLElem($s_resp);
        if (!$o_xml) return false;
        foreach ($o_xml->Item[0]->ItemShops->ItemShop as $o_shop) {
            if ($o_shop->shopID == $this->idShop) return $o_shop;
        }
        return false;
    }

    /**
     * @return array|false
     */
    protected function _loadExtQtysAll() {
        if (!$this->getApiStatus()) return false;
        $n_buff = $this->nBuff;
        $tag_sku = $this->sFieldSku;
        $a_qry = array(
            'limit' => $n_buff,
            'load_relations' => '["ItemShops"]',
            $tag_sku => '>,0',
        );
        $a_ret = array();
        $i_pg = 0; $n_cnt = 0;
        for (; !$n_cnt || $i_pg* $n_buff < $n_cnt; $i_pg++) {
            $a_qry['offset'] = $n_offset = $i_pg* $n_buff;
            $s_resp = $this->request('Item', 'Read', $a_qry);
            if (!$s_resp) break;
            $o_xml = self::createXMLElem($s_resp);
            if (!$n_cnt) $n_cnt = $o_xml['count'];
            if (!$o_xml->Item) break;
            foreach ($o_xml->Item as $o_item) {
                if (!$o_item->itemID) continue;
                if (!$o_item->$tag_sku) continue;
                $sku = (string)$o_item->$tag_sku;
                if (!$sku) continue;
                $qty = false;
                foreach ($o_item->ItemShops->ItemShop as $o_shop) {
                    if ($o_shop->shopID != $this->idShop) continue;
                    $qty = (string)$o_shop->qoh; break;
                }
                if ($sku && $qty !== false) $a_ret[$sku] = $qty;
            }
        }
        return $a_ret;
    }

    /**
     * @param string $key
     * @return mixed
     */
    static function getConfig($key) {
        return Mage::getStoreConfig("cataloginventory/qtysync/$key");
    }

    /**
     * @param string $key
     * @param mixed $val
     * @return mixed
     */
    static function setConfig($key, $val) {
        Mage::getConfig()->saveConfig("cataloginventory/qtysync/$key", $val)
            ->cleanCache();
        Mage::app()->reinitStores();
    }
}
