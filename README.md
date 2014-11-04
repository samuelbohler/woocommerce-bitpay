#WooCommerce BitPay Payment Gateway

##About
Add the ability to accept bitcoin in WooCommerce via BitPay.

##Features

 * Easy pairing with BitPay
 * Customizable order states

##Setup

###Requirements
 * NodeJS & NPM
 * Grunt
 * Composer
 
Clone the repo:
```bash
$ git clone https://github.com/bitpay/woocommerce-bitpay
$ cd woocommerce-bitpay
```
Install the dependencies:
```bash
$ sudo npm install
$ composer install
```
##Build
Perform the [setup](#Setup), then:
```bash
$ grunt build
# Outputs plugin at dist/woocommerce-bitpay
# Outputs plugin archive at dist/woocommerce-bitpay.zip
```

##Install
Perform the [build](#Build), then:

From the WordPress admin panel go to:
Plugins->Add Plugin->Upload Plugin

Select the plugin archive, install it, then activate it.