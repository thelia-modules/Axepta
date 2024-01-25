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

namespace Axepta\Command;

use Axepta\Axepta;
use Axepta\Event\CreatePaymentEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\OrderQuery;

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 24/01/2024.
 */
class CreatePayment extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->setName('create-axcepta-payment')
            ->setDescription('Create a new Axepta payment for a given order')
            ->addArgument(
                'original_order_ref',
                InputArgument::REQUIRED,
                'Original order reference.'
            )
            ->addArgument(
                'new_order_ref',
                InputArgument::REQUIRED,
                'New order reference.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! Axepta::isSubscriptionMode()) {
            $output->writeln(
                '<error>This command is intended for use when the subscription payment feature is enabled.'
                       .'Subscription feature is not currently activated. ($feat is activated)</error>'
            );

            return Command::INVALID;
        }

        $this->initRequest();

        $orderRef = $input->getArgument('original_order_ref');

        if (null === $originalOrder = OrderQuery::create()->findOneByRef($orderRef)) {
            throw new TheliaProcessException("Unknown order $orderRef");
        }

        $newOrderRef = $input->getArgument('new_order_ref');

        if (null === $newOrder = OrderQuery::create()->findOneByRef($newOrderRef)) {
            throw new TheliaProcessException("Unknown order $newOrderRef");
        }

        $event = new CreatePaymentEvent($originalOrder, $newOrder);

        $this->getDispatcher()->dispatch($event, Axepta::AXCEPTA_CREATE_PAYMENT_EVENT);

        $output->writeln('Payment status : '.($event->isSuccess() ? 'success' : 'failure'));

        if (!$event->isSuccess()) {
            $output->writeln('<error> : '.$event->getErrorMessage().'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
