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
use Thelia\Core\Translation\Translator;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use \Axepta\Util\Axepta as AxeptaPayment;
use Thelia\Tools\URL;

class Axepta extends AbstractPaymentModule
{
    /** @var string */
    public const DOMAIN_NAME = 'axepta';

    public const MERCHANT_ID = 'merchant_id';
    public const HMAC = 'hmac';
    public const CRYPT_KEY = 'crypt_key';
    public const MODE = 'run_mode';
    public const ALLOWED_IP_LIST = 'allowed_ip_list';
    public const MINIMUM_AMOUNT = 'minimum_amount';
    public const MAXIMUM_AMOUNT = 'maximum_amount';

    public const TEST_MERCHANT_ID = 'BNP_DEMO_AXEPTA';
    public const TEST_HMAC = '4n!BmF3_?9oJ2Q*z(iD7q6[RSb5)a]A8';
    public const TEST_CRYPT_KEY = 'Tc5*2D_xs7B[6E?w';
    public const SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID = 'send_confirmation_message_only_if_paid';

    public function pay(Order $order)
    {
        $hmac = self::getConfigValue(self::HMAC, null);
        $merchantId = self::getConfigValue(self::MERCHANT_ID, null);
        $cryptKey = self::getConfigValue(self::CRYPT_KEY, null);
        $mode = self::getConfigValue(self::MODE, null);

        if ($mode === 'TEST') {
            $hmac = self::TEST_HMAC;
            $merchantId = self::TEST_MERCHANT_ID;
            $cryptKey = self::TEST_CRYPT_KEY;
        }

        $urlAnnulation   = $this->getPaymentFailurePageUrl($order->getId(), Translator::getInstance()->trans('Vous avez annulé le paiement', [], Axepta::DOMAIN_NAME));
        $urlNotification = URL::getInstance()->absoluteUrl('/axepta/notification');

        $paymentRequest = new AxeptaPayment($hmac);
        $paymentRequest->setCryptKey($cryptKey);

        $paymentRequest->setUrl(AxeptaPayment::PAYSSL);
        $paymentRequest->setMerchantID($merchantId);
        $paymentRequest->setTransID($order->getId());
        $paymentRequest->setAmount((int)$order->getTotalAmount()*100);
        $paymentRequest->setCurrency($order->getCurrency()->getCode());
        $paymentRequest->setRefNr($order->getRef());
        $paymentRequest->setURLSuccess($urlNotification);
        $paymentRequest->setURLFailure($urlNotification);
        $paymentRequest->setURLNotify($urlNotification);
        $paymentRequest->setURLBack($urlAnnulation);
        $paymentRequest->setReponse('encrypt');
        $paymentRequest->setLanguage($this->getRequest()->getSession()->getLang()->getLocale());

        if ($mode === 'TEST') {
            // See https://docs.axepta.bnpparibas/display/DOCBNP/Test+environment
            // In the encrypted data request, use the default parameter OrderDesc with the value "Test:0000". This will give you a correspondingly successful authorization after successful authentication.
            $paymentRequest->setOrderDesc('Test:0000');
        } else {
            $paymentRequest->setOrderDesc($order->getCustomer()->getFirstname() . ' ' . $order->getCustomer()->getLastname());
        }

        $paymentRequest->validate();

        $mac = $paymentRequest->getShaSign();
        $data = $paymentRequest->getBfishCrypt();
        $len = $paymentRequest->getLen();

        $transmit = [
            'MerchantID' => $paymentRequest->getMerchantID(),
            'Len' => $len,
            'Data' => $data,
            'URLBack' => $urlAnnulation
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

        if (($hmac === null || $merchantId === null || $cryptKey === null) && $mode !== 'TEST') {
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
