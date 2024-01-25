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

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 24/01/2024.
 */

namespace Axepta\EventListener;

use Axepta\Axepta;
use Axepta\Event\CreatePaymentEvent;
use Axepta\Model\AxceptaSchemeQuery;
use Axepta\Service\PaymentService;
use Axepta\Util\Axepta as AxeptaPayment;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;

class SubscriptionPaymentListener implements EventSubscriberInterface
{
    public function __construct(protected PaymentService $paymentService, protected Translator $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Axepta::AXCEPTA_CREATE_PAYMENT_EVENT => ['createPayment', 128],
        ];
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function createPayment(CreatePaymentEvent $event): void
    {
        // Get schemeReferenceID
        if (null === $schemeData = AxceptaSchemeQuery::create()->findOneByOrderId($event->getOriginalOrder()->getId())) {
            throw new TheliaProcessException('Failed to get schemeReferenceID for order '.$event->getOriginalOrder());
        }

        [$paymentRequest, $data] = $this->paymentService->createPaymentData(
            $event->getCurrentOrder(),
            $schemeData
        );

        $event->getCurrentOrder()
            ->setTransactionRef($paymentRequest->getTransID())
            ->save();

        try {
            $result = $this->sendPaymentRequest($data);

            parse_str($result, $resultArray);

            $action = $this->paymentService->processNotification(
                $resultArray,
                Tlog::getInstance(),
                $order,
                $paymentResponse
            );

            $event->setSuccess($action === 'success');
        } catch (\Exception $ex) {
            Tlog::getInstance()->error(
                'Failed to validate payment for order '
                .$event->getCurrentOrder()->getRef()
                .' : '
                .$ex->getMessage()
            );

            $event->setErrorMessage(
                $this->translator->trans(
                    'Failed to validate payment for order %ref, error is: %err',
                    [
                        '%ref' => $event->getCurrentOrder()->getRef(),
                        '%err' => $ex->getMessage(),
                    ]
                )
            );
        }
    }

    protected function sendPaymentRequest(array $data): string
    {
        $ch = curl_init();

        $postData = http_build_query($data);

        curl_setopt($ch, \CURLOPT_URL, AxeptaPayment::DIRECT);
        curl_setopt($ch, \CURLOPT_POST, 1);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, \CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, \CURLOPT_FAILONERROR, 1);


        if (false === $result = curl_exec($ch)) {
            throw new TheliaProcessException(curl_error($ch).' (errno '.curl_errno($ch).')');
        }

        return $result;
    }
}
