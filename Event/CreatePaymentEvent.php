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

namespace Axepta\Event;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

class CreatePaymentEvent extends ActionEvent
{
    protected Order $originalOrder;
    protected Order $currentOrder;
    protected bool $success = false;

    protected string $errorMessage;

    public function __construct(Order $originalOrder, Order $currentOrder)
    {
        $this->originalOrder = $originalOrder;
        $this->currentOrder = $currentOrder;
    }

    /**
     * @param bool $success
     *
     * @return $this
     */
    public function setSuccess($success)
    {
        $this->success = $success;

        return $this;
    }

    public function getOriginalOrder(): Order
    {
        return $this->originalOrder;
    }

    public function getCurrentOrder(): Order
    {
        return $this->currentOrder;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
}
