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

use Axepta\Util\Axepta as AxeptaPayment;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\MoneyFormat;
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

    public const SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID = 'send_confirmation_message_only_if_paid';

    public function pay(Order $order)
    {
        $hmac = self::getConfigValue(self::HMAC, null);
        $merchantId = self::getConfigValue(self::MERCHANT_ID, null);
        $cryptKey = self::getConfigValue(self::CRYPT_KEY, null);
        $mode = self::getConfigValue(self::MODE, null);

        $urlAnnulation   = $this->getPaymentFailurePageUrl($order->getId(), Translator::getInstance()->trans('Vous avez annulé le paiement', [], Axepta::DOMAIN_NAME));
        $urlNotification = URL::getInstance()->absoluteUrl(path:'/axepta/notification');

        $paymentRequest = new AxeptaPayment($hmac);
        $paymentRequest->setCryptKey($cryptKey);

        $transId = time().$order->getId();

        $paymentRequest->setMsgVer('2.0');
        $paymentRequest->setUrl(AxeptaPayment::PAYSSL);
        $paymentRequest->setMerchantID($merchantId);
        $paymentRequest->setTransID($transId);
        $paymentRequest->setAmount((int) ($order->getTotalAmount()*100));
        $paymentRequest->setCurrency($order->getCurrency()->getCode());
        $paymentRequest->setRefNr($order->getId());
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
            'URLBack' => $urlAnnulation,
            'CustomField1' => sprintf(
                "%s, %s",
                MoneyFormat::getInstance($this->getRequest())->format($order->getTotalAmount(), 2),
                $order->getCurrency()->getCode()
            ),
            'CustomField2' => $order->getRef()
        ];

        TLog::getInstance()->error("Données Axcepta : " . print_r($paymentRequest->parameters, 1));
        TLog::getInstance()->error("URL Axcepta : " . $paymentRequest->getUrl());

        $order
            ->setTransactionRef($transId)
            ->save();

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
            Tlog::getInstance()->error("Axepta module is not properly configured, some configuration data are missing.");
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

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
