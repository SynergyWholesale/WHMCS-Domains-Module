# Contributing
---

Please note that the following requirements are only required if you wish to contribute to the module and develop locally. If you wish to only use the module, please refer to the [installation section](README.md#installation).

### Requirements
- Node
- NPM
- GNU make
- GNU sed
- PHP >= 5.6
- Composer
- Git
- Curl

## Setting up your environment
Please ensure you have cloned the repository and have the [required tools](#requirements) installed. You can then use `make tools` to install the composer and node dependencies. Alternatively, you can run `composer install` and `npm install` individually.

### Building the CSS/JS assets
To build the CSS and JS assets, please run `make build-assets`

### Updating `whois.json`
To pull down the latest `whois.json`, please run `make update-whois`.

**Please note:** your IP Address must be whitelisted in our management console. [See here](https://synergywholesale.com/faq/article/does-synergy-wholesale-have-a-whois-domain-availability-checker-i-can-integrate-to-my-whmcs/) for more information

### Running tests
Before running the tests please ensure you've 

We have three different types of tests;
- JavaScript Syntax Check `npm run-script check-syntax`
- PHP Syntax/Sniffer `vendor/bin/phpcs`
- PHP Unit Tests `vendor/bin/phpunit`

To run all three at once, you can run `make test`.