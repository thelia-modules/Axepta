# Axepta

This module adds the payment solution Axepta.

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is Axepta.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/axepta-module:~1.0
```

## Usage
* Contact Axepta to create an account.
* Go to the module configuration and add your HMAC key, Blowfish encryption key, and your merchant id.
* Set the operation mode to production

Documentation : https://docs.axepta.bnpparibas

If you want to test your configuration you can use this credit cards : https://docs.axepta.bnpparibas/display/DOCBNP/Test+Cards