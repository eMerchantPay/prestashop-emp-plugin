Genesis client for Prestashop
=============================

This is a Payment Module for Prestashop that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* Prestashop 1.5.x - 1.6.x - 1.7.x
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

Installation (Manual)
------------
* Upload the contents of folder (excluding ```README.md```) to the ```<root>``` folder of your Prestashop installation
* Login into your ```Prestashop Admin Panel```
* Navigate to ```Modules``` -> ```Payment```
* Locate ```eMerchantPay Payment Gateway``` in the list and click ```Install```
* Tweak the settings to your liking and click the ```Save``` button when ready
* You can find the new payment methods in the ```Checkout``` section in your Store Front
* Clear the cache via ```Advanced Parameters``` -> ```Performance``` -> ```Clear cache```

Installation (Upload via Admin Panel)
------------
__Recommended if you do not have FTP account to upload the plugin code directly to your Prestashop__

* Download the Source code of the Plugin as zip file
* Decompress the zip archive and create a new archive of the folder ```emerchantpay```, which is inside of ```modules``` folder
* Login into your ```Prestashop Admin Panel```
* Navigate to ```Modules and Services``` in the main menu
* Click the button ```Upload a module``` or ```Add a new module``` (depending on the version of Prestashop) and choose the manually created ```zip``` file.
* If you are using Prestashop 1.7.x, then the plugin will be automatically installed. If you are using an older version of Prestashop, find our ```eMerchantPay Payment Gateway``` Module below and install it
* After the Module is installed, you could ```Configure``` the newly installed ```eMerchantPay Payment Gateway``` to your needs and click ```Save``` button when ready
* You can find the new payment methods in the ```Checkout``` section in your Store Front
* Clear the cache via ```Advanced Parameters``` -> ```Performance``` -> ```Clear cache```

__Note__: If you have payment restrictions in place, you'll have to add the newly installed payment method to the ```Currencies``` / ```Countries``` / ```Groups``` you wish to appear on.


_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[support]: mailto:tech-support@emerchantpay.net
