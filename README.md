Genesis client for Prestashop
=============================

This is a Payment Module for eMerchantPay that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* Prestashop 1.5.x - 1.6.x
* GenesisPHP 1.0
* SSL (if you want to process CreditCards, directly on your website)

GenesisPHP Requirements
------------

* PHP version >= 5.3 (however since 5.3 is EoL, we recommend at least PHP v5.4)
* PHP with libxml
* PHP ext: cURL (optionally you can use StreamContext)
* Composer

Installation
------------

* Upload the contents of folder (excluding README.md) to the <root> folder of your Prestashop installation
* Login into your Prestashop Admin Panel
* Navigate to Modules -> Payment
* Locate *eMerchantPay Payment Gateway* in the list and click Install
* Tweak the settings to your liking and click the *Save* button when ready
* You can find the new payment methods in the *Checkout* section in your Store Front

You're now ready to process payments through our gateway.