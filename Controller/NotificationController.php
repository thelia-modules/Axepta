<?php

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Util\Axepta as AxeptaPayment;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
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

        $orderRef = $paymentResponse->getrefnr();
        if (null === $order = OrderQuery::create()->filterByRef($orderRef)->findOne()) {
            $this->redirectToFailurePage($order->getId(), Translator::getInstance()->trans("Failed ti find order reference %ref", ['ref' => $orderRef ], Axepta::DOMAIN_NAME));
        }

        $event = new OrderEvent($order);

        $this->getLog()->addInfo("Axcepta response: " . print_r($paymentResponse, 1));

        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {
            if (!$order->isPaid()) {
                $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());
                $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
            }

            $this->redirectToSuccessPage($order->getId());
        }

        $event->setStatus(OrderStatusQuery::getCancelledStatus()->getId());
        $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);

        $this->redirectToFailurePage($order->getId(), $paymentResponse->getDescription() . ' ('.$paymentResponse->getCode().')');
    }
}
