<?php

class Elgentos_CodebaseExceptions_Helper_Data extends Mage_Core_Helper_Abstract {

    public function __construct() {
        $apiKey  = Mage::getStoreConfig('codebaseexceptions/general/apikey');

        if(Mage::getStoreConfig('codebaseexceptions/general/disabled') || strlen($apiKey) == 0) return;

        require_once Mage::getBaseDir('lib') . '/Airbrake/Client.php';
        require_once Mage::getBaseDir('lib') . '/Airbrake/Configuration.php';

        $options = array();
        $requestUri = explode("/",$_SERVER['REQUEST_URI']);
        $options['action'] = array_pop($requestUri);
        $options['component'] = implode('/',array_slice($requestUri,-2));
        $projectRoot = explode('/',$_SERVER['PHP_SELF']);
        array_pop($projectRoot);
        $options['projectRoot'] = implode('/',$projectRoot).'/';
        $options['host'] = Mage::getStoreConfig('codebaseexceptions/general/host');
        $options['secure'] = Mage::getStoreConfig('codebaseexceptions/general/secure');
        $options['environmentName'] = Mage::getStoreConfig('codebaseexceptions/general/environment');
        $config = new Airbrake\Configuration($apiKey,$options);
        $this->client = new Airbrake\Client($config);
    }

    public function insertException($reportData) {
        if(Mage::getStoreConfig('codebaseexceptions/general/disabled')) return;
        $backtraceLines = explode("\n",$reportData[1]);
        foreach($backtraceLines as $backtrace) {
            $temp = array();
            $parts = explode(': ',$backtrace);

            if (isset($parts[1])) {
                $temp['function'] = $parts[1];
            }

            $temp['file'] = substr($parts[0],0,stripos($parts[0],'('));
            $temp['line'] = substr($parts[0],stripos($parts[0],'(')+1,(stripos($parts[0],')')-1)-stripos($parts[0],'('));

            if(!empty($temp['function'])) {
                $backtraces[] = $temp;
            }
        }

        $this->client->notifyOnError($reportData[0],$backtraces);
    }

    /**
     * @param string $message
     */
    public function sendToAirbrake($message) {
        $backtraceArray = explode("\n", $message);
        if (count($backtraceArray) < 1) {
            return;
        }
        $errorMessage = '';
        //reformatting exception
        if (isset($backtraceArray[0])) {
            $errorMessage .= $backtraceArray[0];
            unset($backtraceArray[0]);
        }
        if (empty($errorMessage) && isset($backtraceArray[1])) {
            $errorMessage .= $backtraceArray[1];
            unset($backtraceArray[1]);
        }
        foreach($backtraceArray as $key => $line) {
            $backtraceArray[$key] = array('file' => $line);
        }
        $notice = new \Airbrake\Notice;
        $notice->load(array(
            'errorClass'   => 'PHP Error',
            'backtrace'    => $backtraceArray,
            'errorMessage' => $errorMessage,
        ));

        $this->client->notify($notice);
    }
}
