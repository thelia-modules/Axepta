# Axepta

This module adds the payment solution Axepta from BNP Paribas

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is Axepta.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/axepta-module
```

## Usage
* Contact Axepta to create an account, or get test environment parameters (see below).
* Go to the module configuration and add your HMAC key, Blowfish encryption key, and your merchant id (MID).
* Set the operation mode to production

Documentation : https://docs.axepta.bnpparibas

Test environment parameters : https://docs.axepta.bnpparibas/display/DOCBNP/3DSV2+Test+environment

If you want to test your configuration you can use this credit cards : https://docs.axepta.bnpparibas/display/DOCBNP/Test+Cards+-+Authentication
