<?php

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Util\Axepta as AxeptaPayment;
use OpenApi\Controller\Front\BaseFrontOpenApiController;
use Symfony\Component\Routing\Router;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;
use Thelia\Tools\URL;

class NotificationController extends BasePaymentModuleController
{
    protected function getModuleCode()
    {
        return 'Axepta';
    }


    public function notificationAction()
    {
        $paymentResponse = new AxeptaPayment(Axepta::getConfigValue(Axepta::HMAC));
        $paymentResponse->setCryptKey(Axepta::getConfigValue(Axepta::CRYPT_KEY));
        $paymentResponse->setResponse($_GET);

        $orderRef = $paymentResponse->getrefnr();
        $order = OrderQuery::create()->filterByRef($orderRef)->findOne();

        $frontOfficeRouter = $this->getContainer()->get('router.front');

        $event = new OrderEvent($order);
        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {

            if (!$order->isPaid()) {
                $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());
                $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
            }

            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.placed",
                        ["order_id" => $order->getId()],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        }

        $event->setStatus(OrderStatusQuery::getCancelledStatus()->getId());
        $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl(
                $frontOfficeRouter->generate(
                    "order.failed",
                    [
                        "order_id" => $order->getId(),
                        "message" => $paymentResponse->getDescription()
                    ],
                    Router::ABSOLUTE_URL
                )
            )
        );
    }

}