<?php

namespace Axepta\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class HookManager extends BaseHook
{
    public function onModuleConfigure(HookRenderEvent $event)
    {
        $event->add(
            $this->render('module-configuration.html')
        );
    }

    public function onOrderPaymentGatewayJavascript(HookRenderEvent $event)
    {
        $event->add(
            $this->render('hook/order-payment-gateway-javascript.html')
        );
    }
}
