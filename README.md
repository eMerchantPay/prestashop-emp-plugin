Genesis client for Prestashop
=============================

This is a Payment Module for Prestashop that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* Prestashop 1.5.x - 1.6.x
* [GenesisPHP v1.4](https://github.com/GenesisGateway/genesis_php) - (Integrated in Module)
* PCI-certified server in order to use ```eMerchantPay Direct```

GenesisPHP Requirements
------------

* PHP version 5.3.2 or newer
* PHP Extensions:
    * [BCMath](https://php.net/bcmath)
    * [CURL](https://php.net/curl) (required, only if you use the curl network interface)
    * [Filter](https://php.net/filter)
    * [Hash](https://php.net/hash)
    * [XMLReader](https://php.net/xmlreader)
    * [XMLWriter](https://php.net/xmlwriter)

Installation
------------

* Upload the contents of folder (excluding ```README.md```) to the ```<root>``` folder of your Prestashop installation
* Login into your ```Prestashop Admin Panel```
* Navigate to ```Modules``` -> ```Payment```
* Locate ```eMerchantPay Payment Gateway``` in the list and click ```Install```
* Tweak the settings to your liking and click the ```Save``` button when ready
* You can find the new payment methods in the ```Checkout``` section in your Store Front
* Clear the cache via ```Advanced Parameters``` -> ```Performance``` -> ```Clear cache```

__Note__: If you have payment restrictions in place, you'll have to add the newly installed payment method to the ```Currencies``` / ```Countries``` / ```Groups``` you wish to appear on.

You're now ready to process payments through our gateway.
