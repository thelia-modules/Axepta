<?php

namespace Axepta\Form;

use Axepta\Axepta;
use Payline\Payline;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                Axepta::MODE,
                ChoiceType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'choices' => [
                        'TEST' => 'Test',
                        'PRODUCTION' => 'Production',
                    ],
                    'label' => $this->trans('Mode de fonctionnement', []),
                    'data' => Axepta::getConfigValue(Axepta::MODE),
                ]
            )
            ->add(
                Axepta::MERCHANT_ID,
                TextType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => $this->trans('Merchant ID'),
                    'data' => Axepta::getConfigValue(Axepta::MERCHANT_ID, 'BNP_DEMO_AXEPTA'),
                ]
            )
            ->add(
                Axepta::HMAC,
                TextType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => $this->trans('HMAC key'),
                    'data' => Axepta::getConfigValue(Axepta::HMAC, '4n!BmF3_?9oJ2Q*z(iD7q6[RSb5)a]A8'),
                ]
            )
            ->add(
                Axepta::CRYPT_KEY,
                TextType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => $this->trans('Blowfish encryption key'),
                    'data' => Axepta::getConfigValue(Axepta::CRYPT_KEY, 'Tc5*2D_xs7B[6E?w'),
                ]
            )
            ->add(
                Axepta::ALLOWED_IP_LIST,
                TextareaType::class,
                [
                    'required' => false,
                    'label' => $this->trans('Allowed IPs in test mode'),
                    'data' => Axepta::getConfigValue(Axepta::ALLOWED_IP_LIST),
                    'label_attr' => array(
                        'for' => Axepta::ALLOWED_IP_LIST,
                        'help' => $this->trans(
                            'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                            array('%ip' => $this->getRequest()->getClientIp())
                        ),
                        'rows' => 3
                    )
                ]
            )
            ->add(
                Axepta::MINIMUM_AMOUNT,
                NumberType::class,
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Minimum order total'),
                    'data' => Axepta::getConfigValue(Axepta::MINIMUM_AMOUNT, 0),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $this->trans('Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
            ->add(
                Axepta::MAXIMUM_AMOUNT,
                NumberType::class,
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Maximum order total'),
                    'data' => Axepta::getConfigValue(Axepta::MAXIMUM_AMOUNT, 0),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $this->trans('Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
            ->add(
                Axepta::SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID,
                'checkbox',
                [
                    'value' => 1,
                    'required' => false,
                    'label' => $this->trans('Send order confirmation on payment success'),
                    'data' => (boolean)(Axepta::getConfigValue(Axepta::SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID, true)),
                    'label_attr' => [
                        'help' => $this->trans(
                            'If checked, the order confirmation message is sent to the customer only when the payment is successful. The order notification is always sent to the shop administrator'
                        )
                    ]
                ]
            )

        ;
    }

    public function getName()
    {
        return 'axepta_configuration';
    }

    protected function trans($str, $params = [])
    {
        return Translator::getInstance()->trans($str, $params, Axepta::DOMAIN_NAME);
    }
}
