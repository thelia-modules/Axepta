<?php

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Util\Axepta as AxeptaPayment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
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
    public function notificationAction(Request $request, EventDispatcherInterface $dispatcher)
    {
        $this->getLog()->addInfo("Processing Axcepta notification");

        $paymentResponse = new AxeptaPayment(Axepta::getConfigValue(Axepta::HMAC));
        $paymentResponse->setCryptKey(Axepta::getConfigValue(Axepta::CRYPT_KEY));

        $parameters = $request->request->all();

        if (!isset($parameters[AxeptaPayment::DATA_FIELD])) {
            $parameters = $request->query->all();
        }

        $paymentResponse->setResponse($parameters);

        $this->getLog()->addError("Notification parameters: ".print_r($paymentResponse->parameters, 1));

        $transId = $paymentResponse->getTransID();


        if (null === $order = OrderQuery::create()->filterByTransactionRef($transId)->findOne()) {
            $this->getLog()->addInfo("Failed to fin order for transaction ID $transId. Aborting.");

            throw new TheliaProcessException(
                Translator::getInstance()->trans("Failed to find order for transaction ID %id", ['id' => $transId], Axepta::DOMAIN_NAME)
            );
        }

        $this->getLog()->addInfo("Processing payment of order " . $order->getRef());

        $event = new OrderEvent($order);

        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {
            $this->getLog()->addInfo("Payment of order " . $order->getRef() . " is successful.");
            if (!$order->isPaid()) {
                $this->getLog()->addInfo("Setting order status to 'paid'.");
                $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());
                $dispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
            }

            $this->redirectToSuccessPage($order->getId());
        }

        $this->getLog()->addInfo("Payment failed, cancelling order " . $order->getRef());

        $event->setStatus(OrderStatusQuery::getCancelledStatus()->getId());
        $dispatcher->dispatch($event,TheliaEvents::ORDER_UPDATE_STATUS);

        $this->getLog()->addInfo("Failure cause:" . $paymentResponse->getDescription() . ' (' . $paymentResponse->getCode());
        $this->redirectToFailurePage($order->getId(), $paymentResponse->getDescription() . ' (' . $paymentResponse->getCode() . ')');
    }
}
