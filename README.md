emerchantpay Gateway Module for Prestashop
=============================

This is a Payment Module for Prestashop that gives you the ability to process payments through emerchantpay's Payment Gateway - Genesis.

Requirements
------------

* Prestashop 1.6.x - 1.7.x (Tested up to __1.7.4__)
* [GenesisPHP v1.17.0](https://github.com/GenesisGateway/genesis_php/releases/tag/1.17.0) - (Integrated in Module)
* PCI-certified server in order to use ```emerchantpay Direct```

GenesisPHP Requirements
------------

* PHP version 5.5.9 or newer
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
* Locate ```emerchantpay Payment Gateway``` in the list and click ```Install```
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
* If you are using Prestashop 1.7.x, then the plugin will be automatically installed. If you are using an older version of Prestashop, find our ```emerchantpay Payment Gateway``` Module below and install it
* After the Module is installed, you could ```Configure``` the newly installed ```emerchantpay Payment Gateway``` to your needs and click ```Save``` button when ready
* You can find the new payment methods in the ```Checkout``` section in your Store Front
* Clear the cache via ```Advanced Parameters``` -> ```Performance``` -> ```Clear cache```

__Note__: If you have payment restrictions in place, you'll have to add the newly installed payment method to the ```Currencies``` / ```Countries``` / ```Groups``` you wish to appear on.

Enable PrestaShop SSL
------------
This steps should be followed if you wish to use the ```emerchantpay Direct``` Method.

* Ensure you have installed a valid __SSL Certificate__ on your __PCI-DSS Certified__ Web Server and you have configured your __Virtual Host__ properly.
* Login to your PrestaShop Admin Panel
* Navigate to ```Shop Parameters``` -> ```General```
* Click the link ```Please click here to check if your shop supports HTTPS``` near to the ```Enable SSL``` option
* Set ```Enable SSL``` to __Yes__
* Click ```Save```
* Set ```Enable SSL on all pages``` to __Yes__
* Click again the ```Save``` button
* It is recommended to add a __Rewrite Rule__ from ```http``` to ```https``` or to add a __Permanent Redirect__ to ```https``` in your virtual host

_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[support]: mailto:tech-support@emerchantpay.net

Supported Transactions
------------
* ```emerchantpay Direct``` Payment Method
	* __Authorize__
	* __Authorize (3D-Secure)__
	* __Sale__
	* __Sale (3D-Secure)__

* ```emerchantpay Checkout``` Payment Method
    * __ABN iDeal__
    * __Alipay__
    * __Aura__
    * __Authorize__
    * __Authorize (3D-Secure)__
    * __Baloto_
    * __Banamex__
    * __Banco de Occidente__
    * __Banco do Brasil__
    * __Bancomer__
    * __Boleto__
    * __Bradesco__
    * __Cabal__
    * __CashU__
    * __Cencosud__
    * __Efecty__
    * __Elo__
    * __Entercash__
    * __eZeeWallet__
    * __Fashioncheque__
    * __iDebit__
    * __INPay__
    * __InstaDebit__
    * __InstantTransfer__
    * __Intersolve__
    * __Itau__
    * __Multibanco__
    * __MyBank__
    * __Neteller__
    * __Online Banking__
    * __OXXO__
    * __P24__
    * __PayByVoucher (Sale)__
    * __PayPal Express__
    * __PaySafeCard__
    * __PayU__
    * __POLi__
    * __PPRO__
        * __eps__
        * __GiroPay__
        * __Qiwi__
        * __Przelewy24__
        * __SafetyPay__
        * __TrustPay__
        * __Mr.Cash__
    * __Sale__
    * __Sale (3D-Secure)__
    * __Santander__
    * __Sepa Direct Debit__
    * __SOFORT__
    * __TCS__
    * __Trustly__
    * __WebMoney__
    * __WeChat__
    * __Zimpler__
