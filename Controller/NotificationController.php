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

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Service\PaymentService;
use Axepta\Util\Axepta as AxeptaPayment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;

class NotificationController extends BasePaymentModuleController
{
    protected function getModuleCode()
    {
        return 'Axepta';
    }

    /**
     * @throws \Exception
     */
    public function cancelPaymentAction($orderId, EventDispatcherInterface $dispatcher, Request $request): void
    {
        if (null !== $order = $this->getOrder($orderId)) {
            if ($request->getSession()?->getCustomerUser()?->getId() !== $order->getCustomerId()) {
                throw new \InvalidArgumentException('Customer is invalid.');
            }

            $event =
                (new OrderEvent($order))
                ->setStatus(OrderStatusQuery::getCancelledStatus()->getId())
            ;

            $dispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

            $this->getLog()->addInfo('Customer canceled payment, cancelling order '.$order->getRef());
        }

        $this->redirectToFailurePage(
            $orderId,
            Translator::getInstance()->trans('Vous avez annulé le paiement', [], Axepta::DOMAIN_NAME)
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function notificationAction(Request $request, PaymentService $paymentService): void
    {
        $this->getLog()->addInfo('Processing Axcepta notification');

        $parameters = $request->request->all();

        if (!isset($parameters[AxeptaPayment::DATA_FIELD])) {
            $parameters = $request->query->all();
        }

        $action = $paymentService->processNotification($parameters, $this->getLog(), $order, $paymentResponse);

        // No redirection if no user is connected (IPN call)
        if (null === $request->getSession()?->getCustomerUser()) {
            return;
        }

        if ($action === 'success') {
            $this->redirectToSuccessPage($order->getId());
        }

        $this->redirectToFailurePage($order->getId(), $paymentResponse->getDescription().' ('.$paymentResponse->getCode().')');
    }
}
