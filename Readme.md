# Axepta

This module adds the Axepta payment solution from BNP Paribas to your Thelia shop.

This module supports two payment features :

  - One time payment.
  - Recurring card payments (Subscription), for variable amount and/or frequency.

/!\ Recurring payments for a fixed amount and frequency is not yet supported.

You have to select the operating mode you want to use in the module configuration.
The module does not support both operating modes at the same time

## Installation

Add it in your main thelia composer.json file

```
composer require thelia/axepta-module
```

## Usage
* Contact Axepta to create an account, or get test environment parameters (see below).
* Go to the module configuration and add your HMAC key, Blowfish encryption key, and your merchant id (MID).
* Select the payment feature you want to use (One-time payment / Subscription)
* Set the operation mode to production

Documentation : https://docs.axepta.bnpparibas

Test environment parameters : https://docs.axepta.bnpparibas/display/DOCBNP/3DSV2+Test+environment

If you want to test your configuration you can use this credit cards : https://docs.axepta.bnpparibas/display/DOCBNP/Test+Cards+-+Authentication

Axepta is based on Computop Paygate solution, a very detailed documentation is here : https://developer.computop.com/display/EN/Paygate+EN

## Command

When in subscription mode, you can use the `create-axcepta-payment` command to create en new payment. You have to
provide the first order of the subscription, and the order of the new payment.

Exemple : `create-axcepta-payment ORD000000000031 ORD000000000046`

ORD000000000031 is the first payement of the subscription, ORD000000000046 is the new
payment.

The result of the payment, and the potential error message is displayed on, the console.
