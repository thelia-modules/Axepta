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

/*      web : https://www.openstudio.fr */

/*      For the full copyright and license information, please view the LICENSE */
/*      file that was distributed with this source code. */

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 24/01/2024.
 */

namespace Axepta\Service;

use Axepta\Axepta;
use Axepta\Model\AxceptaScheme;
use Axepta\Model\AxceptaSchemeQuery;
use Axepta\Util\Axepta as AxeptaPayment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class PaymentService
{
    public function __construct(protected RequestStack $requestStack, protected EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * @throws \JsonException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createPaymentData(Order $order, AxceptaScheme $schemeData = null): array
    {
        $hmac = Axepta::getConfigValue(Axepta::HMAC, null);
        $merchantId = Axepta::getConfigValue(Axepta::MERCHANT_ID, null);
        $cryptKey = Axepta::getConfigValue(Axepta::CRYPT_KEY, null);
        $mode = Axepta::getConfigValue(Axepta::MODE, null);

        $urlAnnulation = URL::getInstance()->absoluteUrl('/axepta/cancel/'.$order->getId());
        $urlNotification = URL::getInstance()->absoluteUrl(path: '/axepta/notification');

        $paymentRequest = new AxeptaPayment($hmac);
        $paymentRequest->setCryptKey($cryptKey);

        $transId = time().$order->getId();

        $paymentRequest->setMsgVer('2.0');
        $paymentRequest->setUrl(AxeptaPayment::PAYSSL);
        $paymentRequest->setMerchantID($merchantId);
        $paymentRequest->setTransID($transId);
        $paymentRequest->setAmount((int) ($order->getTotalAmount() * 100));
        $paymentRequest->setCurrency($order->getCurrency()->getCode());
        $paymentRequest->setRefNr($order->getRef());
        $paymentRequest->setURLSuccess($urlNotification);
        $paymentRequest->setURLFailure($urlNotification);
        $paymentRequest->setURLNotify($urlNotification);
        $paymentRequest->setURLBack($urlAnnulation);
        $paymentRequest->setReponse('encrypt');
        $paymentRequest->setLanguage($this->requestStack->getCurrentRequest()?->getSession()->getLang()->getLocale());
        $paymentRequest->setUserData($order->getCustomer()->getFirstname() . ' ' . $order->getCustomer()->getLastname());

        // Customer info mail or mobile phone or landphone required
        $btc = [
            'customerNumber' => $order->getCustomer()->getRef(),
            'consumer' => [
                'salutation' => match ($order->getCustomer()->getTitleId()) {
                    3 => 'Miss',
                    2 => 'Mrs',
                    default => 'Mr',
                },
                'firstName' => $order->getCustomer()->getFirstname(),
                'lastName' => $order->getCustomer()->getLastname(),
            ],
            'email' => $order->getCustomer()->getEmail(),
        ];

        $paymentRequest->setBillToCustomer(base64_encode(json_encode($btc)));

        // Recurring payment request
        $feature = Axepta::getConfigValue(Axepta::PAYMENT_FEATURE, Axepta::PAYMENT_FEATURE_UNIQUE);

        if ($feature !== Axepta::PAYMENT_FEATURE_UNIQUE) {
            $cof = $schemeData ?
                [
                    'type' => [
                        'unscheduled' => 'MIT',
                    ],
                    'initialPayment' => false,
                ] :
                [
                    'type' => [
                        'unscheduled' => 'CIT',
                    ],
                    'initialPayment' => true,
                    'useCase' => 'ucof',
                ];

            $paymentRequest->setCredentialOnFile(base64_encode(json_encode($cof)));

            if ($schemeData) {
                $paymentRequest->setSchemeReferenceID($schemeData->getSchemeReferenceId());

                $cardParams = [
                    'number' => $schemeData->getNumber(),
                    'brand' => $schemeData->getBrand(),
                    'expiryDate' => $schemeData->getExpiryDate(),
                    'cardholderName' => $schemeData->getName(),
                ];

                $paymentRequest->setCard(base64_encode(json_encode($cardParams)));
            }
        }

        // See https://docs.axepta.bnpparibas/display/DOCBNP/Test+environment
        // In the encrypted data request, use the default parameter OrderDesc with the value "Test:0000".
        // This will give you a correspondingly successful authorization after successful authentication.
        $paymentRequest->setOrderDesc(
            $mode === 'TEST' ?
                'Test:0000' :
                $order->getCustomer()->getFirstname().' '.$order->getCustomer()->getLastname()
        );

        $data = $paymentRequest->getBfishCrypt();
        $len = $paymentRequest->getLen();

        $transmit = [
            'MerchantID' => $paymentRequest->getMerchantID(),
            'Len' => $len,
            'Data' => $data,
            'URLBack' => $urlAnnulation,
            'CustomField1' => sprintf(
                '%s, %s',
                MoneyFormat::getInstance($this->requestStack->getCurrentRequest())->format($order->getTotalAmount(), 2),
                $order->getCurrency()->getCode()
            ),
            'CustomField2' => $order->getRef(),
        ];

        return [$paymentRequest, $transmit];
    }

    public function processNotification(array $parameters, Tlog $log, &$order, &$paymentResponse): string
    {
        $paymentResponse = new AxeptaPayment(Axepta::getConfigValue(Axepta::HMAC));
        $paymentResponse->setCryptKey(Axepta::getConfigValue(Axepta::CRYPT_KEY));

        $paymentResponse->setResponse($parameters);

        if ((bool) Axepta::getConfigValue(Axepta::LOG_AXCEPTA_RESPONSE, false)) {
            $log->addError('Notification parameters: '.print_r($paymentResponse->parameters, 1));
        }

        $transId = '<undefined>';

        try {
            $transId = $paymentResponse->getTransID();

            if (null === $order = OrderQuery::create()->filterByTransactionRef($transId)->findOne()) {
                throw new \Exception();
            }
        } catch (\Exception $ex) {
            $log->addInfo("Failed to find order for transaction ID $transId. Aborting.");

            throw new TheliaProcessException(
                Translator::getInstance()->trans('Failed to find order for transaction ID %id', ['%id' => $transId], Axepta::DOMAIN_NAME)
            );
        }

        $event = new OrderEvent($order);

        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {
            if (!$order->isPaid()) {
                $log->addInfo('Payment of order '.$order->getRef()." is successful, setting order status to 'paid'");
                $event->setStatus(OrderStatusQuery::getPaidStatus()?->getId());
                $this->dispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

                // Store recurring payment data for subscriptions
                if (isset($paymentResponse->parameters['schemeReferenceID'], $paymentResponse->parameters['card'])
                    &&
                    Axepta::isSubscriptionMode()
                ) {
                    $schemeId = $paymentResponse->parameters['schemeReferenceID'];

                    // Store SchemeReferenceId the first time we get it. See transaction chaining here
                    // https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=41585166#Recurringcardpayments(Subscription)-Principles
                    if (null === AxceptaSchemeQuery::create()->findOneBySchemeReferenceId($schemeId)) {
                        (new AxceptaScheme())
                            ->setOrderId($order->getId())
                            ->setSchemeReferenceId($schemeId)
                            ->setNumber($paymentResponse->parameters['PCNr'])
                            ->setExpiryDate($paymentResponse->parameters['CCExpiry'])
                            ->setBrand($paymentResponse->parameters['CCBrand'])
                            ->setName($paymentResponse->parameters['CardHolder'])
                            ->save();

                        $log->addInfo('Saved scheme '.$schemeId.' for order '.$order->getRef());
                    }
                }
            } else {
                $log->addInfo('Order '.$order->getRef()." is already in 'paid' status");
            }

            return 'success';
        }

        $log->addInfo('Payment failed, cancelling order '.$order->getRef());

        $event->setStatus(OrderStatusQuery::getCancelledStatus()?->getId());
        $this->dispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        $log->addInfo('Failure cause:'.$paymentResponse->getDescription().' ('.$paymentResponse->getCode());

        return 'failure';
    }
}
