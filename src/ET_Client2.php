<?php

namespace AppBundle\Services\Channel\Salesforce;

use DOMDocument;
use DOMXPath;
use FuelSdk\ET_Client as FuelSdk_ET_Client;
use FuelSdk\ET_Util;

/**
 * This is the Squeezely variant of the ET_Client. They haven't updated the SDK for oauth 2.
 * Class ET_Client
 * @package AppBundle\Services\Channel\Salesforce
 */
class ET_Client2 extends FuelSdk_ET_Client {
    /**
     * @var string APIs hostname
     */
    public $baseUrl;

    protected $tenantKey = [];

    protected $debugSOAP, $lastHTTPCode, $endpoint, $baseSoapUrl;

    /**
     * @inheritDoc
     */
    public function __construct($getWSDL = false, $debug = false, $params = null) {

        $params['sslverifypeer'] = true;

        $this->tenantKey = $params['tenantKey'];

        parent::__construct($getWSDL, $debug, $params);

        $this->endpoint = $this->baseSoapUrl . 'Service.asmx';

        parent::__setLocation($this->endpoint);
    }

    /**
     * @inheritDoc
     *
     * Squeezely version shouldn't do anything
     */
    public function refreshToken($forceRefresh = false) {
        //do nothing
    }

    /**
     * @inheritDoc
     */
    public function __doRequest($request, $location, $saction, $version, $one_way = 0) {
        $doc = new DOMDocument();
        $doc->loadXML($request);
        $this->addOAuth($doc, $this->getInternalAuthToken($this->tenantKey));

        $content = $doc->saveXML();
        if ($this->debugSOAP) {
            error_log('FuelSDK SOAP Request: ');
            error_log(str_replace($this->getInternalAuthToken($this->tenantKey), "REMOVED", $content));
        }

        $headers = ["Content-Type: text/xml", "SOAPAction: " . $saction, "User-Agent: " . ET_Util::getSDKVersion()];

		$location = "https://mc9r0b9qpsrtt0j17w1666dz6j81.soap.marketingcloudapis.com/".$location;
		
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $location);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ET_Util::shouldVerifySslPeer($this->sslVerifyPeer));
        curl_setopt($ch, CURLOPT_USERAGENT, ET_Util::getSDKVersion());

        if (!empty($this->proxyHost)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyHost);
        }
        if (!empty($this->proxyPort)) {
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxyPort);
        }
        if (!empty($this->proxyUserName) && !empty($this->proxyPassword)) {
            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyUserName . ':' . $this->proxyPassword);
        }

        $output = curl_exec($ch);
        $this->lastHTTPCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function addOAuth($doc, $token) {
        $soapDoc = $doc;
        $envelope = $doc->documentElement;
        $soapNS = $envelope->namespaceURI;
        $soapPFX = $envelope->prefix;
        $SOAPXPath = new DOMXPath($doc);
        $SOAPXPath->registerNamespace('wssoap', $soapNS);

        $headers = $SOAPXPath->query('//wssoap:Envelope/wssoap:Header');
        $header = $headers->item(0);
        if (!$header) {
            $header = $soapDoc->createElementNS($soapNS, $soapPFX . ':Header');
            $envelope->insertBefore($header, $envelope->firstChild);
        }

        $authnode = $soapDoc->createElementNS('http://exacttarget.com', 'fueloauth', $token);
        $header->appendChild($authnode);

    }
}