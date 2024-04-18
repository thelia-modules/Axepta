<?php

namespace Axepta\EventListener;

use Axepta\Axepta;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Thelia\Model\ModuleConfigQuery;

class ConfigListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'module.config' => ['onModuleConfig', 128],
        ];
    }

    public function onModuleConfig(GenericEvent $event)
    {
        $subject = $event->getSubject();

        if ($subject !== 'HealthStatus') {
            throw new \RuntimeException('Event subject does not match expected value');
        }

        $fieldsToCheck = ['run_mode', 'merchant_id', 'hmac', 'crypt_key', 'minimum_amount', 'maximum_amount', 'active_payment_feature'];

        $configModule = ModuleConfigQuery::create()
            ->filterByModuleId(Axepta::getModuleId())
            ->filterByName($fieldsToCheck)
            ->find();

        $configModule = $configModule->toArray();

        $moduleConfig = [];
        $moduleConfig['module'] = Axepta::getModuleCode();
        $configsCompleted = true;

        foreach ($fieldsToCheck as $field) {
            $fieldExists = false;

            foreach ($configModule as $config) {
                if ($config['Name'] === $field) {
                    $fieldExists = true;
                    break;
                }
            }

            if (!$fieldExists) {
                $configsCompleted = false;
                break;
            }
        }

        $moduleConfig['completed'] = $configsCompleted;

        $event->setArgument('axepta.module.config', $moduleConfig);
    }

}