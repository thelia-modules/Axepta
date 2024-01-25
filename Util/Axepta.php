<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Axepta\Util;

class Axepta extends Blowfish
{
    public const PAYSSL = 'https://paymentpage.axepta.bnpparibas/payssl.aspx';
    public const DIRECT = 'https://paymentpage.axepta.bnpparibas/direct.aspx';

    public const DIRECT3D = 'https://paymentpage.axepta.bnpparibas/direct3d.aspx';
    public const CAPTURE = 'https://paymentpage.axepta.bnpparibas/capture.aspx';
    public const CREDIT = 'https://paymentpage.axepta.bnpparibas/credit.aspx';

    public const INSTALMENT = 'INSTALMENT';

    private $secretKey;

    private $cryptKey;

    private $pspURL = self::PAYSSL;

    public $parameters = [];

    /** Axepta fields **/
    private $pspFields = [
        'MsgVer',
        'Debug',
        'PayID',
        'TransID',
        'MerchantID',
        'Amount',
        'Currency',
        'MAC',

        'RefNr',
        'Amount3D',
        'URLSuccess',
        'URLFailure',
        'URLNotify',
        'Response',
        'UserData',
        'Capture',
        'OrderDesc',
        'ReqID',
        'Plain',
        'Custom',
        'expirationTime',
        'AccVerify',
        'RTF',
        'ChDesc',

        'Len',
        'Data',

        'Template',
        'Language',
        'Background',
        'URLBack',
        'CCSelect',

        'MID',
        'mid',
        'refnr',
        'XID',
        'Status',
        'Description',
        'Code',
        'PCNr',
        'CCNr',
        'CCCVC',
        'CCBrand',
        'CCExpiry',
        'TermURL',
        'UserAgent',
        'HTTPAccept',
        'AboID',
        'ACSXID',
        'MaskedPan',
        'CAVV',
        'ECI',
        'DDD',
        'Type',
        'Plain',
        'Custom',
        'CustomField1', 'CustomField2', 'CustomField3', 'CustomField4', 'CustomField5', 'CustomField6', 'CustomField7',
        'CustomField8', 'CustomField9', 'CustomField10', 'CustomField11', 'CustomField12', 'CustomField13', 'CustomField14',
        'MsgVer',
        'credentialOnFile', 'threeDSPolicy', 'schemeReferenceID', 'Card',
    ];
    /** Axepta request hmac fields **/
    private $QHMACFields = [
        'PayID', 'TransID', 'MerchantID', 'Amount', 'Currency',
    ];
    /** Axepta response hmac fields **/
    private $RHMACFields = [
        'PayID', 'TransID', 'MerchantID', 'Status', 'Code',
    ];

    /** Axepta blowfish crypt fields **/
    private $BfishFields = [
        'PayID', 'TransID', 'Amount', 'Currency', 'MAC',
        'RefNr', 'Amount3D', 'URLSuccess', 'URLFailure', 'URLNotify', 'Response', 'UserData', 'Capture', 'OrderDesc', 'ReqID',
        'Plain', 'Custom', 'expirationTime', 'AccVerify', 'RTF', 'ChDesc',
        'MID', 'XID', 'Status', 'Description', 'Code', 'PCNr', 'CCNr', 'CCCVC', 'CCBrand', 'CCExpiry', 'TermURL', 'UserAgent',
        'HTTPAccept', 'AboID', 'ACSXID', 'MaskedPan', 'CAVV', 'ECI', 'DDD', 'Type', 'Plain', 'Custom', 'MsgVer',
        'credentialOnFile', 'threeDSPolicy', 'schemeReferenceID', 'Card'
        // 'CustomField1','CustomField2','CustomField3','CustomField4','CustomField5','CustomField6','CustomField7',
        // 'CustomField8','CustomField9','CustomField10','CustomField11','CustomField12','CustomField13','CustomField14'
    ];

    /** Axepta request required fields **/
    private $requiredFields = [
        // 'MerchantID', 'TransID', 'Amount', 'Currency','URLSuccess','URLFailure','URLNotify','OrderDesc'
        'MerchantID', 'TransID', 'Amount', 'Currency', 'OrderDesc',
    ];

    public $allowedlanguages = [
        'nl', 'fr', 'de', 'it', 'es', 'cy', 'en',
    ];

    public function __construct($secret)
    {
        $this->secretKey = $secret;				// HMAC key
    }

    public function setCryptKey($secret): void
    {
        $this->cryptKey = $secret;				// blowfish crypt key
    }

    /** hack to retrieve response field **/
    public function setReponse($encrypt = 'encrypt'): void
    {
        $this->parameters['Response'] = $encrypt;
    }

    /** HMAC compute and store in MAC field**/
    public function shaCompose(array $parameters)
    {
        // compose SHA string
        $shaString = '';
        foreach ($parameters as $key) {
            if (\array_key_exists($key, $this->parameters) && !empty($this->parameters[$key])) {
                $value = $this->parameters[$key];
                $shaString .= $value;
            }
            $shaString .= (array_search($key, $parameters) != (\count($parameters) - 1)) ? '*' : '';
        }
        $this->parameters['MAC'] = hash_hmac('sha256', $shaString, $this->secretKey);

        return $this->parameters['MAC'];
    }

    /** @return string */
    public function getShaSign()
    {
        $this->validate();

        return $this->shaCompose($this->QHMACFields);
    }

    public function BfishCompose(array $parameters)
    {
        // compose Blowfish hex string
        $blowfishString = '';

        foreach ($parameters as $key) {
            if (\array_key_exists($key, $this->parameters) && !empty($this->parameters[$key])) {
                $value = $this->parameters[$key];
                $blowfishString .= $key.'='.$value.'&';
            }
        }

        $blowfishString = rtrim($blowfishString, '&');

        $this->parameters['Debug'] = $blowfishString;
        $this->parameters['Len'] = \strlen($blowfishString);

        $plaintext = $this->expand($blowfishString);
        $this->bf_set_key($this->cryptKey);
        $this->parameters[self::DATA_FIELD] = bin2hex($this->encrypt($plaintext));

        return $this->parameters[self::DATA_FIELD];
    }

    /** @return string */
    public function getBfishCrypt()
    {
        $this->validate();

        return $this->BFishCompose($this->BfishFields);
    }

    /** @return string */
    public function getUrl()
    {
        return $this->pspURL;
    }

    public function setUrl($pspUrl): void
    {
        $this->validateUri($pspUrl);
        $this->pspURL = $pspUrl;
    }

    public function setURLSuccess($url): void
    {
        $this->validateUri($url);
        $this->parameters['URLSuccess'] = $url;
    }

    public function setURLFailure($url): void
    {
        $this->validateUri($url);
        $this->parameters['URLFailure'] = $url;
    }

    public function setURLNotify($url): void
    {
        $this->validateUri($url);
        $this->parameters['URLNotify'] = $url;
    }

    public function setTransID($transactionReference): void
    {
        if (preg_match('/[^a-zA-Z0-9_-]/', $transactionReference)) {
            throw new \InvalidArgumentException('TransactionReference cannot contain special characters');
        }
        $this->parameters['TransID'] = $transactionReference;
    }

    /**
     * Set amount in cents, eg EUR 12.34 is written as 1234.
     */
    public function setAmount($amount): void
    {
        if (!\is_int($amount)) {
            throw new \InvalidArgumentException('Integer expected. Amount is always in cents');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be a positive number');
        }
        $this->parameters['Amount'] = $amount;
    }

    public function setCaptureDay($number): void
    {
        if (\strlen($number) > 2) {
            throw new \InvalidArgumentException('captureDay is too long');
        }
        $this->parameters['captureDay'] = $number;
    }

    // Methodes liees a la lutte contre la fraude

    public function setFraudDataBypass3DS($value): void
    {
        if (\strlen($value) > 128) {
            throw new \InvalidArgumentException('fraudData.bypass3DS is too long');
        }
        $this->parameters['fraudData.bypass3DS'] = $value;
    }

    // Methodes liees au paiement one-click

    public function setMerchantWalletId($wallet): void
    {
        if (\strlen($wallet) > 21) {
            throw new \InvalidArgumentException('merchantWalletId is too long');
        }
        $this->parameters['merchantWalletId'] = $wallet;
    }

    public function setPaymentPattern($paymentPattern): void
    {
        $this->parameters['paymentPattern'] = $paymentPattern;
    }

    public function __call($method, $args)
    {
        if (substr($method, 0, 3) === 'set') {
            // $field = lcfirst(substr($method, 3));
            $field = substr($method, 3);
            if (\in_array($field, $this->pspFields)) {
                $this->parameters[$field] = $args[0];

                return;
            }

            $field = lcfirst($field);
            if (\in_array($field, $this->pspFields)) {
                $this->parameters[$field] = $args[0];

                return;
            }
        }
        if (substr($method, 0, 3) === 'get') {
            $field = substr($method, 3);
            if (\array_key_exists($field, $this->parameters)) {
                return $this->parameters[$field];
            }

            $field = lcfirst($field);
            if (\array_key_exists($field, $this->parameters)) {
                return $this->parameters[$field];
            }
        }

        throw new \BadMethodCallException("Unknown method $method");
    }

    public function toArray()
    {
        return $this->parameters;
    }

    public function toParameterString()
    {
        $parameterString = '';
        foreach ($this->parameters as $key => $value) {
            $parameterString .= $key.'='.$value;
            $parameterString .= (array_search($key, array_keys($this->parameters)) != (\count($this->parameters) - 1)) ? '|' : '';
        }

        return $parameterString;
    }

    public static function createFromArray($shaComposer, array $parameters)
    {
        $instance = new static($shaComposer);
        foreach ($parameters as $key => $value) {
            $instance->{"set$key"}($value);
        }

        return $instance;
    }

    public function validate(): void
    {
        foreach ($this->requiredFields as $field) {
            if (empty($this->parameters[$field])) {
                throw new \RuntimeException($field.' can not be empty');
            }
        }
    }

    protected function validateUri($uri): void
    {
        if (!filter_var($uri, \FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Uri is not valid');
        }
        if (\strlen($uri) > 200) {
            throw new \InvalidArgumentException('Uri is too long');
        }
    }

    // Traitement des reponses d'Axepta
    // -----------------------------------

    /** @var string */
    public const SHASIGN_FIELD = 'MAC';

    /** @var string */
    public const DATA_FIELD = 'Data';

    public function setResponse(array $httpRequest): void
    {
        // use lowercase internally
        // $httpRequest = array_change_key_case($httpRequest, CASE_UPPER);

        // set sha sign
        // $this->shaSign = $this->extractShaSign($httpRequest);

        // filter request for Sips parameters
        $this->parameters = $this->filterRequestParameters($httpRequest);
    }

    /**
     * @var string
     */
    private $shaSign;

    private $dataString;

    /**
     * Filter http request parameters.
     *
     * @param array $requestParameters
     */
    private function filterRequestParameters(array $httpRequest)
    {
        // filter request for Sips parameters
        $parameters = $this->parameters;
        if (!\array_key_exists(self::DATA_FIELD, $httpRequest) || $httpRequest[self::DATA_FIELD] == '') {
            // throw new InvalidArgumentException('Data parameter not present in parameters.');
            $parameters['Debug'] = implode('&', $httpRequest);
            foreach ($httpRequest as $key => $value) {
                $key = ($key == 'mid') ? 'MerchantID' : $key;
                $parameters[$key] = $value;
            }
        } else {
            $parameters[self::DATA_FIELD] = $httpRequest[self::DATA_FIELD];
            $this->bf_set_key($this->cryptKey);
            $this->dataString = $this->decrypt(hex2bin($parameters[self::DATA_FIELD]));
            $parameters['Debug'] = $this->dataString;
            $dataParams = explode('&', $this->dataString);
            foreach ($dataParams as $dataParamString) {
                $dataKeyValue = explode('=', $dataParamString, 2);
                $key = ($dataKeyValue[0] == 'mid') ? 'MerchantID' : $dataKeyValue[0];
                $parameters[$key] = $dataKeyValue[1];
            }
        }

        return $parameters;
    }

    public function getSeal()
    {
        return $this->shaSign;
    }

    private function extractShaSign(array $parameters)
    {
        if (!\array_key_exists(self::SHASIGN_FIELD, $parameters) || $parameters[self::SHASIGN_FIELD] == '') {
            throw new \InvalidArgumentException('SHASIGN parameter not present in parameters.');
        }

        return $parameters[self::SHASIGN_FIELD];
    }

    public function isValid()
    {
        // return $this->shaCompose($this->RHMACFields) == $this->shaSign;
        return $this->shaCompose($this->RHMACFields) == $this->parameters['MAC'];
    }

    /**
     * Retrieves a response parameter.
     *
     * @param string $param
     *
     * @throws \InvalidArgumentException
     */
    public function getParam($key)
    {
        if (method_exists($this, 'get'.$key)) {
            return $this->{'get'.$key}();
        }

        // always use uppercase
        // $key = strtoupper($key);
        // $parameters = array_change_key_case($this->parameters,CASE_UPPER);
        $parameters = $this->parameters;
        if (!\array_key_exists($key, $parameters)) {
            throw new \InvalidArgumentException('Parameter '.$key.' does not exist.');
        }

        return $parameters[$key];
    }

    /**
     * @return int Amount in cents
     */
    public function getAmount()
    {
        $value = trim($this->parameters['Amount']);

        return (int) $value;
    }

    public function isSuccessful()
    {
        return \in_array($this->getParam('Status'), ['OK', 'AUTHORIZED']);
    }

    public function getDataString()
    {
        return $this->dataString;
    }
}
