<?php

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Util\Axepta as AxeptaPayment;
use OpenApi\Controller\Front\BaseFrontOpenApiController;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\Base\OrderQuery;
use Thelia\Module\BasePaymentModuleController;

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

        $orderRef = $paymentResponse->getRefNr();
        $order = OrderQuery::create()->filterByRef($orderRef)->findOne();

        $frontOfficeRouter = $this->getContainer()->get('router.front');


        if ($paymentResponse->isValid() && $paymentResponse->isSuccessful()) {
            $TransID = $paymentResponse->getPayID();
            $PCNr = $paymentResponse->getPCNr();
            $CCBrand = $paymentResponse->getCCBrand();
            $CCExpiry = $paymentResponse->getCCExpiry();

        }


    }

}