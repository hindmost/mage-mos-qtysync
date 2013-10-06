<?php
class Mos_QtySync_Model_Api_Client
{
    const AUTH_PWD = 'apikey';
    const XML_DECL = '<?xml version="1.0" encoding="utf-8"?>';
    static protected $ACTIONS = array(
        'Create' => 'POST', 'Read' => 'GET', 'Update' => 'PUT', 'Delete' => 'DELETE'
    );

    protected $urlAcct = 'https://api.merchantos.com/API/Account';
    protected $urlInit = 'https://api.merchantos.com/API/Session';
    protected $idAcct = '';
    protected $oHttp = 0;

    /**
     * @param string $key
     * @param int|string $idAcct
     * @param string $urlAcct
     * @param string $urlInit
     * @param string $pwd
     */
    function __construct($key, $idAcct = '', $urlAcct = '', $urlInit = '', $pwd = '') {
        if ($urlInit)
            $this->urlInit = $urlInit;
        if ($urlAcct)
            $this->urlAcct = $urlAcct;
        if (!$key) return;
        $this->oHttp = new Zend_Http_Client(null, array(
            'timeout' => 40,
            'adapter' => 'Zend_Http_Client_Adapter_Curl',
            'curloptions' => array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $key. ':'. ($pwd? $pwd : self::AUTH_PWD),
            )
        ));
        if ($idAcct)
            $this->idAcct = $idAcct;
        else if ($idAcct = $this->loadAcctId())
            $this->idAcct = $idAcct;
    }

    /**
     * @return string|false
     */
    public function loadAcctId() {
        $s_resp = $this->request(0);
        if (!$s_resp) return false;
        $o_xml = self::createXMLElem($s_resp);
        return $o_xml->systemCustomerID;
    }

    /**
     * @return string|false
     */
    public function getAcctId() {
        return $this->idAcct;
    }

    /**
     * @return bool
     */
    public function getApiStatus() {
        return (bool)$this->oHttp;
    }

    /**
     * @param string $s
     * @return SimpleXMLElement|false
     */
    static function createXMLElem($s) {
        static $n_cnt = 0;
        if (!$n_cnt++ && function_exists('libxml_use_internal_errors'))
            libxml_use_internal_errors();
        else
            libxml_clear_errors();
        try {
            return new SimpleXMLElement($s);
        }
        catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }

    /**
     * @param string $sObject
     * @param string $sAction
     * @param array $aQuery
     * @param string $sUniqId
     * @param SimpleXMLElement|array $data
     * @return string|false
     */
    public function request($sObject, $sAction = 0, $aQuery = 0, $sUniqId = 0, $data = 0) {
        for ($i = 0; $i < 2; $i++) {
            $s_resp = $this->_request($sObject, $sAction, $aQuery, $sUniqId, $data);
            if (!$s_resp || !preg_match('/<httpCode(\s+[^<>]+|)\s*>\s*503\s*</', $s_resp))
                return $s_resp;
            sleep(60); continue;
        }
        return false;
    }

    /**
     * @param string $sObject
     * @param string $sAction
     * @param array $aQuery
     * @param string $sUniqId
     * @param SimpleXMLElement|array $data
     * @return string|false
     */
    protected function _request($sObject, $sAction = 0, $aQuery = 0, $sUniqId = 0, $data = 0) {
        if (!$this->oHttp) return false;
        if (((bool)$this->idAcct) ^ ((bool)$sObject)) return false;
        $url = $sObject?
            $this->urlAcct. '/'. $this->idAcct. '/'. $sObject.
                ($sUniqId? '/'. $sUniqId : '').
                (is_array($aQuery)? '?'. http_build_query($aQuery) : '')
            : $this->urlInit;
        $this->oHttp->setUri($url);
        if (isset(self::$ACTIONS[$sAction]))
            $this->oHttp->setMethod(self::$ACTIONS[$sAction]);
        if ($data) {
            $s_xml = (is_object($data) && $data instanceof SimpleXMLElement)?
                $data->asXML()
                : is_array($data)? $this->_buildReqData($data) : $data;
            if ($s_xml)
                $this->oHttp->setRawData($s_xml)
                ->setHeaders('Content-Type', 'text/xml');
        }
        $s_resp = $this->oHttp->request()->getBody();
        static $n_calls = 0; ++$n_calls;
        if (Mage::getIsDeveloperMode())
            file_put_contents("api_response-$sObject-$sAction_(".date('y-m-d-H-i').").xml", $s_resp);
        return $s_resp? $s_resp : false;
    }

    /**
     * @param array $aData
     * @return string|false
     */
    protected function _buildReqData($aData) {
        if (!is_array($aData) || count($aData) < 2) return false;
        $n = count($aData);
        $s_xml = (($val = $aData[$n- 1]) instanceof SimpleXMLElement)?
            $val->asXML() : $val;
        for ($i = $n- 2; $i >= 0; $i--) {
            $tag = $aData[$i];
            if (is_string($tag))
                $s_xml = "<$tag>$s_xml</$tag>";
        }
        $o_xml = self::createXMLElem(self::XML_DECL. $s_xml);
        return $o_xml? $o_xml->asXML() : false;
    }
}
