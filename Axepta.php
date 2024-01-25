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

/*      Copyright (c) OpenStudio */
/*      email : dev@thelia.net */
/*      web : http://www.thelia.net */

/*      For the full copyright and license information, please view the LICENSE.txt */
/*      file that was distributed with this source code. */

namespace Axepta;

use Axepta\Service\PaymentService;
use Axepta\Util\Axepta as AxeptaPayment;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Install\Database;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;

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
    public const LOG_AXCEPTA_RESPONSE = 'log_axcepta_response';

    public const PAYMENT_FEATURE = 'active_payment_feature';

    public const PAYMENT_FEATURE_UNIQUE = 'unique';
    public const PAYMENT_FEATURE_FIXED_AMOUNT_SUBSCRIPTION = 'fas';
    public const PAYMENT_FEATURE_VARIABLE_AMOUNT_SUBSCRIPTION = 'vas';

    public const SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID = 'send_confirmation_message_only_if_paid';

    public const AXCEPTA_CREATE_PAYMENT_EVENT = 'axepta.axcepta_create_payment_event';

    /**
     * @throws \JsonException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function pay(Order $order): ?Response
    {
        /** @var PaymentService $paymentService */
        $paymentService = $this->getContainer()->get('axepta_payment_service');

        /** @var AxeptaPayment $paymentRequest, array $data */
        [$paymentRequest, $data] = $paymentService->createPaymentData($order);

        $order
            ->setTransactionRef($paymentRequest->getTransID())
            ->save();

        return $this->generateGatewayFormResponse($order, $paymentRequest->getUrl(), $data);
    }

    public function isValidPayment()
    {
        $pf = self::getConfigValue(self::PAYMENT_FEATURE, null);
        $hmac = self::getConfigValue(self::HMAC, null);
        $merchantId = self::getConfigValue(self::MERCHANT_ID, null);
        $cryptKey = self::getConfigValue(self::CRYPT_KEY, null);
        $mode = self::getConfigValue(self::MODE, null);
        $valid = true;

        if (($pf === null || $hmac === null || $merchantId === null || $cryptKey === null) && $mode !== 'TEST') {
            Tlog::getInstance()->error('Axepta module is not properly configured, some configuration data are missing.');

            return false;
        }

        if ($mode === 'TEST') {
            $raw_ips = explode("\n", self::getConfigValue(self::ALLOWED_IP_LIST, ''));
            $allowed_client_ips = [];

            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }

            $client_ip = $this->getRequest()->getClientIp();

            $valid = \in_array($client_ip, $allowed_client_ips) || \in_array('*', $allowed_client_ips);
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

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR.ucfirst(self::getModuleCode()).'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }

    public function preActivation(ConnectionInterface $con = null)
    {
        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);

            self::setConfigValue('is_initialized', true);
        }

        return true;
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__.DS.'Config'.DS.'update');

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
