<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Axepta;

use GuzzleHttp\Client;
use SmartyRedirection\Smarty\Plugins\Redirect;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use \Axepta\Util\Axepta as AxeptaPayment;
use Thelia\Tools\URL;

class Axepta extends AbstractPaymentModule
{
    /** @var string */
    const DOMAIN_NAME = 'axepta';

    const MERCHANT_ID = 'merchant_id';
    const HMAC = 'hmac';
    const CRYPT_KEY = 'crypt_key';
    const MODE = 'run_mode';
    const ALLOWED_IP_LIST = 'allowed_ip_list';
    const MINIMUM_AMOUNT = 'minimum_amount';
    const MAXIMUM_AMOUNT = 'maximum_amount';

    const TEST_MERCHANT_ID = 'BNP_DEMO_AXEPTA';
    const TEST_HMAC = '4n!BmF3_?9oJ2Q*z(iD7q6[RSb5)a]A8';
    const TEST_CRYPT_KEY = 'Tc5*2D_xs7B[6E?w';

    public function pay(Order $order)
    {
        $hmac = self::getConfigValue(self::HMAC, null);
        $merchantId = self::getConfigValue(self::MERCHANT_ID, null);
        $cryptKey = self::getConfigValue(self::CRYPT_KEY, null);
        $mode = self::getConfigValue(self::MODE, null);

        if ($mode === 'TEST'){
            $hmac = self::TEST_HMAC;
            $merchantId = self::TEST_MERCHANT_ID;
            $cryptKey = self::TEST_CRYPT_KEY;
        }

        $paymentRequest = new AxeptaPayment($hmac);
        $paymentRequest->setCryptKey($cryptKey);

        $paymentRequest->setUrl(AxeptaPayment::PAYSSL);
        $paymentRequest->setMerchantID($merchantId);
        $paymentRequest->setTransID($order->getId());
        $paymentRequest->setAmount((int)$order->getTotalAmount()*100);
        $paymentRequest->setCurrency($order->getCurrency()->getCode());
        $paymentRequest->setRefNr($order->getRef());
        $paymentRequest->setURLSuccess(URL::getInstance()->absoluteUrl('/axepta/notification'));
        $paymentRequest->setURLFailure(URL::getInstance()->absoluteUrl('/axepta/notification'));
        $paymentRequest->setURLNotify(URL::getInstance()->absoluteUrl('/axepta/notification'));
        $paymentRequest->setURLBack(URL::getInstance()->absoluteUrl('/order/invoice'));
        $paymentRequest->setReponse('encrypt');
        $paymentRequest->setLanguage('fr');
        $paymentRequest->setOrderDesc($order->getCustomer()->getFirstname() . ' ' . $order->getCustomer()->getLastname());

        $paymentRequest->validate();

        $mac = $paymentRequest->getShaSign();
        $data = $paymentRequest->getBfishCrypt();
        $len = $paymentRequest->getLen();

        $transmit = [
            'MerchantID' => $paymentRequest->getMerchantID(),
            'Len' => $len,
            'Data' => $data,
            'URLBack' => URL::getInstance()->absoluteUrl('/order/invoice'),
            'URLSuccess' => URL::getInstance()->absoluteUrl('/axepta/notification')
        ];

        return $this->generateGatewayFormResponse($order, $paymentRequest->getUrl(), $transmit);
    }

    public function isValidPayment()
    {
        $hmac = self::getConfigValue(self::HMAC, null);
        $merchantId = self::getConfigValue(self::MERCHANT_ID, null);
        $cryptKey = self::getConfigValue(self::CRYPT_KEY, null);
        $mode = self::getConfigValue(self::MODE, null);
        $valid = true;

        if (($hmac === null || $merchantId === null || $cryptKey === null) && $mode !== 'TEST')
        {
            return false;
        }

        if ($mode === 'TEST') {
            $raw_ips = explode("\n", self::getConfigValue(self::ALLOWED_IP_LIST, ''));
            $allowed_client_ips = array();

            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }

            $client_ip = $this->getRequest()->getClientIp();

            $valid = in_array($client_ip, $allowed_client_ips) || in_array('*', $allowed_client_ips);
        }

        if ($valid) {
            // Check if total order amount is in the module's limits
            $valid = $this->checkMinMaxAmount(self::MINIMUM_AMOUNT, self::MAXIMUM_AMOUNT);
        }

        return $valid;
    }

    protected function checkMinMaxAmount($min, $max)
    {
        $order_total = $this->getCurrentOrderTotalAmount();

        $min_amount = self::getConfigValue($min, 0);
        $max_amount = self::getConfigValue($max, 0);

        return $order_total > 0 && ($min_amount <= 0 || $order_total >= $min_amount) && ($max_amount <= 0 || $order_total <= $max_amount);
    }

}
