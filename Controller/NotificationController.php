<?php

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Util\Axepta as AxeptaPayment;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Base\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;

class NotificationController extends BasePaymentModuleController
{
    protected function getModuleCode()
    {
        return 'Axepta';
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function notificationAction()
    {
        $this->getLog()->addInfo("Processing Axcepta notification");

        $paymentResponse = new AxeptaPayment(Axepta::getConfigValue(Axepta::HMAC));
        $paymentResponse->setCryptKey(Axepta::getConfigValue(Axepta::CRYPT_KEY));
        $paymentResponse->setResponse($this->getRequest()->query->all());

        $this->getLog()->addError("Notification parameters: ".print_r($paymentResponse->parameters, 1));

        $transId = $paymentResponse->getTransID();

        if (null === $order = OrderQuery::create()->filterByTransactionRef($transId)->findOne()) {
            $this->getLog()->addInfo("Failed to fin order for transaction ID $transId. Aborting.");

            throw new TheliaProcessException(
                Translator::getInstance()->trans("Failed to find order for transaction ID %id", ['id' => $transId ], Axepta::DOMAIN_NAME)
            );
        }

        $this->getLog()->addInfo("Processing payment of order " . $order->getRef());

        $event = new OrderEvent($order);

        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {
            $this->getLog()->addInfo("Payment of order ".$order->getRef()." is successful.");
            if (!$order->isPaid()) {
                $this->getLog()->addInfo("Setting order status to 'paid'.");
                $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());
                $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
            }

            $this->redirectToSuccessPage($order->getId());
        }

        $this->getLog()->addInfo("Payment failed, cancelling order " . $order->getRef());

        $event->setStatus(OrderStatusQuery::getCancelledStatus()->getId());
        $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);

        $this->getLog()->addInfo("Failure cause:".$paymentResponse->getDescription() . ' ('.$paymentResponse->getCode());
        $this->redirectToFailurePage($order->getId(), $paymentResponse->getDescription() . ' ('.$paymentResponse->getCode().')');
    }
}
