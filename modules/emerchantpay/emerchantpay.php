<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      eMerchantPay
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The main eMerchantPay class that handles
 * all the logic related to the payment module
 */
class eMerchantPay extends PaymentModule
{
    /**
     * List supported languages
     *
     * @var array
     */
    private $languages = array('en', 'it', 'es', 'fr', 'de', 'pl', 'ja', 'zh', 'ar', 'pt', 'tr', 'ru', 'hi', 'bg');

    /**
     * Constructor
     */

    /**
     * Configurable module settings
     */
    const SETTING_EMERCHANTPAY_USERNAME              = 'EMERCHANTPAY_USERNAME';
    const SETTING_EMERCHANTPAY_PASSWORD              = 'EMERCHANTPAY_PASSWORD';
    const SETTING_EMERCHANTPAY_TOKEN                 = 'EMERCHANTPAY_TOKEN';
    const SETTING_EMERCHANTPAY_ENVIRONMENT           = 'EMERCHANTPAY_ENVIRONMENT';
    const SETTING_EMERCHANTPAY_DIRECT                = 'EMERCHANTPAY_DIRECT';
    const SETTING_EMERCHANTPAY_DIRECT_TRX_TYPE       = 'EMERCHANTPAY_DIRECT_TRX_TYPE';
    const SETTING_EMERCHANTPAY_CHECKOUT              = 'EMERCHANTPAY_CHECKOUT';
    const SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES    = 'EMERCHANTPAY_CHECKOUT_TRX_TYPES';
    const SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE = 'EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE';
    const SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND  = 'EMERCHANTPAY_ALLOW_PARTIAL_REFUND';
    const SETTING_EMERCHANTPAY_ALLOW_VOID            = 'EMERCHANTPAY_ALLOW_VOID';
    const SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT   = 'EMERCHANTPAY_ADD_JQUERY_CHECKOUT';

    public function __construct()
    {
        /* Initial Module Setup */
        $this->name                   = 'emerchantpay';
        $this->tab                    = 'payments_gateways';
        $this->displayName            = 'eMerchantPay Payment Gateway';
        $this->controllers            = array('checkout', 'notification', 'redirect', 'validation');
        $this->version                = '1.4.2';
        $this->author                 = 'eMerchantPay Ltd.';
        $this->need_instance          = 1;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_); 
        $this->bootstrap              = true;
        $this->module_key             = '30288d67740c20403574a3bc800965aa';

        /* The parent construct is required for translations */
        $this->page         = basename(__FILE__, '.php');
        $this->description  = 'Accept payments through eMerchantPay\'s Payment Gateway - Genesis';

        /* Use Bootstrap */
        $this->bootstrap = true;

        /* Storage for transaction data to avoid init/call every-time */
        $this->transaction_data = null;

        /* Store warnings during init */
        $this->warning = '';

        /* Initialize Genesis Client */
        $this->init();

        /* Error if conditions are not met */
        $this->isAvailable();

        /* Run all parent constructors */
        parent::__construct();

        /* Smarty Module constants */
        $this->context->smarty->assign(
            'emerchantpay',
            array(
                'name'      => array(
                    'module'    => $this->name,
                    'display'   => $this->displayName,
                    'store'     => Configuration::get('PS_SHOP_NAME')
                ),
                'path'      => $this->getPathUri(),
                'presta'    => array(
                    'url'       => Tools::getHttpHost(true) . __PS_BASE_URI__,
                    'version'   => _PS_VERSION_,
                ),
                'version'   => $this->version,
                'warning'   => $this->warning
            )
        );

        $this->doMigrateSettings();
    }

    /**
     * Install logic
     *
     * Install and create/register the require hooks
     *
     * @return bool
     */
    public function install()
    {
        // Call PaymentModule default install function
        $pre_install = parent::install();

        $install = new eMerchantPayInstall();

        // Register Hooks
        $install->registerHooks($this);

        // Create Tables
        $install->createSchema();

        return $pre_install && $install->isSuccessful() && $this->setDefaultSettingsToDB();
    }

    /**
     * Uninstall logic
     *
     * Remove all the set Configuration keys and un-register all hooks
     *
     * @return bool
     */
    public function uninstall()
    {
        $pre_uninstall = parent::uninstall();

        $uninstall = new eMerchantPayInstall();

        // Clear the transaction database
        $uninstall->dropSchema();

        // Remove the current configuration
        $uninstall->dropKeys($this);

        // Remove attached Hooks
        $uninstall->dropHooks($this);

        return $pre_uninstall && $uninstall->isSuccessful();
    }

    /**
     * Is this module available for use?
     *
     * @return bool
     */
    public function isAvailable()
    {
        if (isset($this->warning) && !empty($this->warning)) {
            return false;
        }

        return true;
    }

    /**
     * Is Direct payment method enabled?
     *
     * @return bool
     */
    public function isDirectPaymentMethodAvailable()
    {
        return $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_DIRECT);
    }

    /**
     * Is Checkout payment method available?
     *
     * @return bool
     */
    public function isCheckoutPaymentMethodAvailable()
    {
        return $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_CHECKOUT);
    }

    /**
     * Is the current transaction type async?
     *
     * Note: This takes into account only Direct
     * transaction methods as Checkout is inherently
     * asynchronous
     *
     * @return bool
     */
    public function isAsyncTransaction()
    {
        if ($this->isDirectPaymentMethodAvailable()) {
            return (stripos(Configuration::get(self::SETTING_EMERCHANTPAY_DIRECT_TRX_TYPE), '3d') !== false) ? true : false;
        }

        return false;
    }

    /**
     * Hook AdminOrder to display the saved transactions,
     * related to the order (Used for 1.5.x and 1.6.x)
     *
     * @param array $params
     *
     * @return string HTML source
     */
    public function hookAdminOrder($params)
    {
        if (Tools::isSubmit($this->name . '_transaction_id')) {
            switch (Tools::getValue($this->name . '_transaction_type')) {
                case 'capture':
                    $this->doCapture();
                    break;
                case 'refund':
                    $this->doRefund();
                    break;
                case 'void':
                    $this->doVoid();
                    break;
            }

            // Prevent re-submission by refresh
            // Some browsers are re-POST happy
            Tools::redirect($_SERVER['HTTP_REFERER']);
        }

        $order = new Order((int)$params['id_order']);

        if ($order->payment != $this->displayName) {
            return '';
        }

        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $this->context->controller->addCSS(
                $this->getPathUri() . 'assets/css/font-awesome.min.css', 'all'
            );
        }

        $this->context->controller->addCSS(
            $this->getPathUri() . 'assets/css/treegrid.min.css', 'all'
        );

        $this->context->controller->addCSS(
            $this->getPathUri() . 'assets/js/bootstrap/bootstrapValidator.min.css'
        );

        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/treegrid/cookie.min.js'
        );
        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/treegrid/treegrid.min.js'
        );

        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/bootstrap/bootstrapValidator.min.js'
        );

        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/jQueryExtensions/jquery.number.min.js'
        );

        $currency = new Currency((int)$order->id_currency);

        $this->context->smarty->append(
            'emerchantpay',
            array(
                'transactions'  => array(
                    'order'             => array(
                        'id'          	=> $order->id,
                        'amount'      	=> $order->getTotalPaid(),
                        'currency'    	=> array(
                            'iso_code' => $currency->iso_code,
                            'sign' 		 => $currency->sign,
                            'decimalPlaces' 	 => 2,
                            'decimalSeparator' => '.',
                            'thousandSeparator' => '' /* must be empty, otherwise exception could be trown from Genesis */
                        )
                    ),
                    'options' => array(
                       'allow_partial_capture' => $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE),
                       'allow_partial_refund'  => $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND),
                       'allow_void'            => $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_ALLOW_VOID)
                    ),
                    'text' => array(
                       'denied_partial_capture' => $this->l('Partial Capture is currently disabled! You can enable this option in the Module Settings.'),
                       'denied_partial_refund' => $this->l('Partial Refund is currently disabled! You can enable this option in the Module Settings.'),
                       'denied_void' => $this->l('Cancel Transaction are currently disabled! You can enable this option in the Module Settings.'),
                    ),
                    'error' => $this->getSessVar('error_transaction'),
                    'tree'  => eMerchantPayTransaction::getTransactionTree((int)$params['id_order']),
                ),
            ),
            true
        );

        return $this->fetchTemplate('/views/templates/admin/admin_order/transactions.tpl');
    }

    /**
     * Hook AdminOrder to display the saved transactions,
     * related to the order (Used for 1.7.x)
     *
     * @param array $params
     *
     * @return string HTML source
     */
    public function hookDisplayAdminOrder($params)
    {
        return $this->hookAdminOrder($params);
    }

    /**
     * Hook Payment Options to display the payment methods on the checkout page,
     * (Used for 1.7.x)
     *
     * @param array $params
     *
     * @return array
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if (!isset($_SESSION)) {
            session_start();
        }

        $this->context->smarty->append(
            'emerchantpay',
            array(
                'payment'   => array(
                    'option' => array(
                        'selected_id' =>
                            Tools::getIsset('select_payment_option')
                                ? Tools::getValue('select_payment_option')
                                : ''
                    ),
                    'methods'       => array(
                        'direct'    => $this->isDirectPaymentMethodAvailable(),
                        'checkout'  => $this->isCheckoutPaymentMethodAvailable()
                    ),
                    'errors'        => array(
                        'direct'    => $this->getSessVar('error_direct'),
                        'checkout'  => $this->getSessVar('error_checkout')
                    )
                ),
                'ssl' => array(
                    'enabled'       => $this->getIsSSLEnabled()
                ),
            ),
            true
        );

        $paymentMethods = array(
            array(
                'title' => 'Pay safely with eMerchantPay Checkout',
                'name'  => 'checkout',
                'clientSideEvents' => array(
                    'onFormSubmit' => 'return doBeforeSubmitEMerchantPayCheckoutPaymentForm(this);"'
                ),
                'availabilityClosure' => function() {
                    return $this->isCheckoutPaymentMethodAvailable();
                }
            ),
            array(
                'title' => 'Pay safely with eMerchantPay Direct',
                'name'  => 'direct',
                'clientSideEvents' => array(
                    'onFormSubmit' => 'return doBeforeSubmitEMerchantPayDirectPaymentForm(this);"'
                ),
                'availabilityClosure' => function() {
                    return $this->isDirectPaymentMethodAvailable() && $this->getIsSSLEnabled();
                }
            ),
        );

        $paymentOptions = array();

        foreach ($paymentMethods as $paymentMethod) {
            $availabilityClosure = $paymentMethod['availabilityClosure'];
            if (!is_callable($availabilityClosure) || $availabilityClosure()) {
                $submitFormAction = $this->context->link->getModuleLink(
                    $this->name,
                    'validation',
                    array(),
                    true
                );
                $paymentMethodInputName = 'submit' . $this->name . ucfirst($paymentMethod['name']);
                $paymentMethodOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $paymentMethodOption
                    ->setCallToActionText($paymentMethod['title'])
                    ->setForm(
                        '<form
                            class="payment-option-form-"' . $this->name . '"
                            method="post"
                            action="' . $submitFormAction . '"
                            onsubmit="' . $paymentMethod['clientSideEvents']['onFormSubmit'] . '">
                            <input type="hidden" name="' . $paymentMethodInputName .'" value="1" />
                         </form>'
                    )
                    ->setAdditionalInformation(
                        $this->context->smarty->fetch(
                            "module:{$this->name}/views/templates/hook/payment/{$paymentMethod['name']}.tpl"
                        )
                    );

                $paymentOptions[] = $paymentMethodOption;
            }
        }

        return $paymentOptions;
    }

    /**
     * List our payment methods
     *
     * @return mixed
     */
    public function hookPayment()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $this->context->controller->addCSS(
                $this->getPathUri() . 'assets/css/font-awesome.min.css', 'all'
            );
        }

        $this->context->smarty->append(
            'emerchantpay',
            array(
                'payment'   => array(
                    'urls'          => array(
                        'direct'    => $this->context->link->getModuleLink($this->name, 'validation'),
                        'checkout'  => $this->context->link->getModuleLink($this->name, 'checkout'),
                    ),
                    'errors'        => array(
                        'direct'    => $this->getSessVar('error_direct'),
                        'checkout'  => $this->getSessVar('error_checkout')
                    ),
                    'methods'       => array(
                        'direct'    => $this->isDirectPaymentMethodAvailable(),
                        'checkout'  => $this->isCheckoutPaymentMethodAvailable()
                    ),
                ),
                'ssl' => array(
                    'enabled'   	=> $this->getIsSSLEnabled()
                ),
            ),
            true
        );

        if (!$this->isAvailable()) {
            return $this->fetchTemplate('blank.tpl');
        } else {
            return $this->fetchTemplate('payment.tpl');
        }
    }

    /**
     * Load the CSS/JS needed in advance to ensure that
     * when the form is called through hookPayment we
     * have loaded the CSS/JS.
     *
     * @return void
     */
    public function hookPaymentTop()
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $this->context->controller->addCSS(
                $this->getPathUri() . 'assets/css/bootstrap-custom.min.css', 'all'
            );
            $this->context->controller->addJS(
                $this->getPathUri() . 'assets/js/bootstrap/bootstrap.alert.min.js'
            );
        }

        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/card/card.min.js'
        );
    }

    /**
     * Load the CSS/JS needed in advance to ensure that
     * when the form is called through hookPayment we
     * have loaded the CSS/JS.
     * (Used for 1.7.x)
     *
     * @return void
     */
    public function hookHeader()
    {
        if (!$this->isAvailable()) {
            return;
        }

        if (!$this->isDirectPaymentMethodAvailable()) {
            return;
        }

        $cardJSUri = $this->getPathUri() . 'assets/js/card/card.min.js';

        if ($this->isPrestaVersion17()) {
            if ($this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT)) {
                $this->registerCore17Javascript(
                    $this->getJQueryUri()
                );
            }
            $this->registerCore17Javascript($cardJSUri);
        } else {
            $this->context->controller->addJS($cardJSUri);
        }
    }

    /**
     * Show a information about the customers order
     *
     * @param $params
     *
     * @return bool|mixed
     */
    public function hookOrderConfirmation($params)
    {
        if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name)) {
            return false;
        }

        if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid)) {

            $reference = isset($params['objOrder']->reference)
                ? $params['objOrder']->reference
                : '#' . sprintf('%06d', $params['objOrder']->id);


            $this->smarty->assign('order',
                array(
                    'reference' => $reference,
                    'valid'     => $params['objOrder']->valid
                ));
        }

        switch ($params['objOrder']->current_state) {
            case Configuration::get('PS_OS_PREPARATION'):
                $status = 'pending';
                break;
            case Configuration::get('PS_OS_WS_PAYMENT'):
                $status = 'success';
                break;
            default:
                $status = 'failure';
                break;
        }

        $this->context->smarty->append(
            'emerchantpay',
            array(
                'confirmation' => array(
                    'status' => $status,
                )
            ),
            true
        );

        return $this->fetchTemplate('confirmation.tpl');
    }

    /**
     * Generate transaction id
     *
     * @param $length
     * @return string
     */
    private function generateTransactionId ($length = 30)
    {
        return substr(md5(mt_rand() . microtime(true) . uniqid()), 0, $length);
    }

    /**
     * Collect and process the data required for the initial payment.
     *
     * @return stdClass Processed data
     */
    public function populateTransactionData()
    {
        /** @var CartCore $cart */
        $cart = new Cart(intval($this->context->cart->id));

        /** @var AddressCore $shipping */
        $shipping = new Address(intval($cart->id_address_delivery));
        /** @var AddressCore $invoice */
        $invoice = new Address(intval($cart->id_address_invoice));
        /** @var CustomerCore $customer */
        $customer = new Customer(intval($cart->id_customer));
        /** @var CurrencyCore $currency */
        $currency = new Currency(intval($cart->id_currency));

        $data = new stdClass();

        // Parameters
        $data->id               = $this->generateTransactionId();
        $data->transaction_type = Configuration::get(self::SETTING_EMERCHANTPAY_DIRECT_TRX_TYPE);
        $data->usage            = $this->l('Prestashop Transaction');

        $description = '';

        foreach ($cart->getProducts() as $product) {
            if (isset($product['name']) && isset($product['quantity'])) {
                $quantity_text = ($product['quantity']) > 1 ? $this->l('pcs') : $this->l('pc');

                $description .= $product['name'] . ' x' . $product['quantity'] . $quantity_text . PHP_EOL;
            }
        }

        $data->description = $description;

        $data->remote_ip = Tools::getRemoteAddr();
        $data->currency  = $currency->iso_code;
        $data->amount    = $cart->getOrderTotal();

        $data->customer_email = $customer->email;
        $data->customer_phone = (empty($invoice->phone) ? $invoice->phone_mobile : $invoice->phone);

        if (Tools::getIsset('emerchantpay-number')) {
            $data->card_number = str_replace(' ', '', Tools::getValue('emerchantpay-number'));
            $data->card_type   = $this->getCardTypeByNumber($data->card_number);
            $data->card_last4  = substr($data->card_number, -4, 4);
        }

        if (Tools::getIsset('emerchantpay-name')) {
            $data->card_holder = Tools::getValue('emerchantpay-name');
        }

        if (Tools::getIsset('emerchantpay-cvc')) {
            $data->cvv = Tools::getValue('emerchantpay-cvc');
        }

        if (Tools::getIsset('emerchantpay-expiry')) {
            $data->expiration = Tools::getValue('emerchantpay-expiry');

            list($month, $year) = explode('/', $data->expiration);

            $data->expiration_month = trim($month);
            $data->expiration_year  = substr(date('Y'), 0, 2) . substr(trim($year), -2);
        }

        // Billing
        if ($invoice) {
            $countryCode = $this->getCountryIsoCodeById($invoice->id_country);

            $data->billing            = new stdClass();
            $data->billing->firstname = $invoice->firstname;
            $data->billing->lastname  = $invoice->lastname;
            $data->billing->address1  = $invoice->address1;
            $data->billing->address2  = $invoice->address2;
            $data->billing->postcode  = $invoice->postcode;
            $data->billing->city      = $invoice->city;
            $data->billing->state     = $this->getStateIsoCodeById($invoice->id_state);
            $data->billing->country   = $countryCode;
        }

        // Shipping
        if ($shipping) {
            if ($this->shouldGetCountryIsoCode($invoice, $shipping)) {
                $countryCode = $this->getCountryIsoCodeById($shipping->id_country);
            }

            $data->shipping            = new stdClass();
            $data->shipping->firstname = $shipping->firstname;
            $data->shipping->lastname  = $shipping->lastname;
            $data->shipping->address1  = $shipping->address1;
            $data->shipping->address2  = $shipping->address2;
            $data->shipping->postcode  = $shipping->postcode;
            $data->shipping->city      = $shipping->city;
            $data->shipping->state     = $this->getStateIsoCodeById($shipping->id_state);
            $data->shipping->country   = $countryCode;
        }

        // URL endpoints (Async transactions)
        $data->url                 = new stdClass();
        $data->url->notification   = $this->getNotificationURL();
        $data->url->return_success = $this->getAsyncSuccessURL();
        $data->url->return_failure = $this->getAsyncFailureURL();
        $data->url->return_cancel  = $this->getAsyncCancelURL();

        // Set WPF language
        if (in_array($this->context->language->iso_code, $this->languages)) {
            $data->language = $this->context->language->iso_code;
        }

        // Set WPF transaction types
        $data->transaction_types = $this->getCheckoutTransactionTypes();

        return $this->transaction_data = $data;
    }

    /**
     * @param Address $invoice
     * @param Address $shipping
     *
     * @return bool
     */
    protected function shouldGetCountryIsoCode($invoice, $shipping)
    {
        return !$invoice || $invoice->id_country !== $shipping->id_country;
    }

    /**
     * Process a checkout request
     *
     * This method will try to create a new WPF instance
     * if successful - we redirect the customer to the newly created insnace
     * if unsuccessful - we show them an error message
     *
     * @return string url
     */
    public function doCheckout()
    {
        // Apply settings
        $this->applyGenesisConfig();

        try {
            $responseObj = eMerchantPayTransactionProcess::checkout(
                $this->populateTransactionData()
            );

            $response = $responseObj->getResponseObject();

            $message = 'Unique Id: ' . $response->unique_id . PHP_EOL .
                       'Transaction Id: ' . $this->transaction_data->id . PHP_EOL;

            $this->validateOrder(
                (int) $this->context->cart->id,
                (int) Configuration::get('PS_OS_PREPARATION'),
                (float) $response->amount,
                $this->displayName,
                $message,
                array(),
                null,
                false,
                $this->context->customer->secure_key
            );

            // Add Transaction Info to the original Order
            $new_order = new Order((int)$this->currentOrder);

            // Save the transaction to Db
            $transaction = new eMerchantPayTransaction();
            $transaction->id_parent 		= 0;
            $transaction->ref_order = $new_order->reference;
            $transaction->transaction_id = $this->transaction_data->id;
            $transaction->type      = 'checkout';
            $transaction->importResponse($response);
            $transaction->add();

            return $response->redirect_url;
        } catch (\Genesis\Exceptions\ErrorAPI $api) {
            $this->logError($api);

            $this->setSessVar('error_checkout', $api->getMessage());
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar('error_checkout',
                'Please, make sure you\'ve entered correct credentials for accessing the gateway and all of the required data, e.g. Email, Phone, Billing/Shipping Address.'
            );
        }

        return null;
    }

    /**
     * Process the Payment
     *
     * This method will collect all the information it requires
     * for a transaction and it will try execute it.
     *
     * @return void
     */
    function doPayment()
    {
        // Apply settings
        $this->applyGenesisConfig();

        try {
            $responseObj = eMerchantPayTransactionProcess::pay(
                $this->populateTransactionData()
            );

            $response = $responseObj->getResponseObject();

            $positiveStates = array(
                \Genesis\API\Constants\Transaction\States::APPROVED,
                \Genesis\API\Constants\Transaction\States::PENDING_ASYNC
            );

            if (!in_array($response->status, $positiveStates)) {
                $this->setSessVar('error_direct',
                    isset($response->message)
                        ? $response->message
                        : $this->l('Your payment was declined! Please, check your card data and try again!')
                );

                $this->redirectToPage(
                    'order.php',
                    array(
                        'step'                  => '3',
                        'select_payment_option' => Tools::getValue('select_payment_option')
                    )
                );
                return;
            }

            $message = 'TransactionId: ' . $response->unique_id . PHP_EOL;

            $status = $this->getPrestaStatus($response->status);

            $this->validateOrder(
                (int) $this->context->cart->id,
                (int) $status,
                (float) $response->amount,
                $this->displayName, $message,
                array(),
                null,
                false,
                $this->context->customer->secure_key
            );

            // Add Transaction Info to the original Order
            $new_order = new Order((int)$this->currentOrder);

            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                if (Validate::isLoadedObject($new_order)) {
                    $payment = $new_order->getOrderPaymentCollection()->getFirst();

                    if (is_object($payment)) {
                        $payment->card_brand      = pSQL($this->transaction_data->card_type);
                        $payment->card_holder     = pSQL($this->transaction_data->card_holder);
                        $payment->card_number     = pSQL($this->transaction_data->card_last4);
                        $payment->card_expiration = pSQL($this->transaction_data->expiration);
                        $payment->transaction_id  = pSQL($response->unique_id);
                        $payment->save();
                    }
                }
            }

            // Save the transaction to Db
            $transaction  = new eMerchantPayTransaction();
            $transaction->id_parent = 0;
            $transaction->ref_order = $new_order->reference;
            $transaction->importResponse($response);
            $transaction->add();

            // Redirect the customer
            if (isset($response->redirect_url)) {
                Tools::redirect($response->redirect_url);
            } else {
                $this->redirectToPage('order-confirmation.php');
            }
        } catch (\Exception $e) {
            $this->logError($e);

            if ($e instanceof \Genesis\Exceptions\ErrorAPI) {
                $this->setSessVar('error_direct', $e->getMessage());
            } else {
                $this->setSessVar('error_direct',
                    $this->l('There was a problem processing your transaction, please try again! ' . $e->getMessage())
                );
            }

            $this->redirectToPage(
                'order.php',
                array(
                    'step'                  => '3',
                    'select_payment_option' => Tools::getValue('select_payment_option')
                )
            );
        }
    }

    /**
     * Perform a Capture on a Genesis Transaction
     *
     * @return bool
     */
    function doCapture()
    {
        $id_unique = Tools::getValue($this->name . '_transaction_id');
        $amount    = Tools::getValue($this->name . '_transaction_amount');
        $usage     = Tools::getValue($this->name . '_transaction_usage');
        
        $ip_addr   = Tools::getRemoteAddr();

        // Apply settings
        $this->applyGenesisConfig();

        try {
            $transaction = eMerchantPayTransaction::getByUniqueId($id_unique);

            if ($transaction->terminal) {
                \Genesis\Config::setToken($transaction->terminal);
            }

            $data = array(
                'transaction_id'    => md5(uniqid() . mt_rand() . microtime(true)),
                'usage'             => $usage,
                'remote_ip'         => $ip_addr,
                'reference_id'      => $transaction->id_unique,
                'currency'          => $transaction->currency,
                'amount'            => $amount,
            );

            $response = eMerchantPayTransactionProcess::capture($data);

            $transaction_response            = new eMerchantPayTransaction();
            $transaction_response->id_parent = $transaction->id_unique;
            $transaction_response->ref_order = $transaction->ref_order;
            $transaction_response->importResponse($response->getResponseObject());
            if ($transaction->terminal) {
                $transaction_response->terminal = $transaction->terminal;
            }

            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_WS_PAYMENT'), true
            );
            $transaction_response->add();
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar('error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Perform a Refund on a Genesis Transaction
     *
     * @return bool
     */
    function doRefund()
    {
        $id_unique = Tools::getValue($this->name . '_transaction_id');
        $amount    = Tools::getValue($this->name . '_transaction_amount');
        $usage     = Tools::getValue($this->name . '_transaction_usage');
        $ip_addr   = Tools::getRemoteAddr();

        // Apply settings
        $this->applyGenesisConfig();

        try {
            $transaction = eMerchantPayTransaction::getByUniqueId($id_unique);

            if ($transaction->terminal) {
                \Genesis\Config::setToken($transaction->terminal);
            }

            $data = array(
                'transaction_id'    => md5(uniqid() . mt_rand() . microtime(true)),
                'usage'             => $usage,
                'remote_ip'         => $ip_addr,
                'reference_id'      => $transaction->id_unique,
                'currency'          => $transaction->currency,
                'amount'            => $amount,
            );

            $response = eMerchantPayTransactionProcess::refund($data);

            $transaction_response            = new eMerchantPayTransaction();
            $transaction_response->id_parent = $transaction->id_unique;
            $transaction_response->ref_order = $transaction->ref_order;
            $transaction_response->importResponse($response->getResponseObject());
            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_REFUND'), true
            );
            $transaction_response->add();
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar('error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Perform Void (cancellation) on a Genesis Transaction
     *
     * @return bool
     */
    function doVoid()
    {
        $id_unique = Tools::getValue($this->name . '_transaction_id');
        $usage     = Tools::getValue($this->name . '_transaction_usage');
        $ip_addr   = Tools::getRemoteAddr();

        // Apply settings
        $this->applyGenesisConfig();

        try {
            $transaction = eMerchantPayTransaction::getByUniqueId($id_unique);

            if ($transaction->terminal) {
                \Genesis\Config::setToken($transaction->terminal);
            }

            $data = array(
                'transaction_id'    => md5(uniqid() . mt_rand() . microtime(true)),
                'usage'             => $usage,
                'remote_ip'         => $ip_addr,
                'reference_id'      => $transaction->id_unique,
            );

            $response = eMerchantPayTransactionProcess::void($data);

            $transaction_response            = new eMerchantPayTransaction();
            $transaction_response->id_parent = $transaction->id_unique;
            $transaction_response->ref_order = $transaction->ref_order;
            $transaction_response->importResponse($response->getResponseObject());
            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_CANCELED'), true
            );
            $transaction_response->add();
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar('error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Try to match a CreditCard Number to their CreditCard Brand
     *
     * @return string
     * @param $number string
     **/
    static function getCardTypeByNumber($number)
    {
        // Strip everything, but the digits
        $number = preg_replace('/[^\d]/', '', $number);

        if (preg_match('/^3[47][0-9]{13}$/', $number)) {
            return 'American Express';
        } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
            return 'Diners Club';
        } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
            return 'Discover';
        } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
            return 'JCB';
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
            return 'MasterCard';
        } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
            return 'Visa';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Get Prestashop status based on Genesis status
     *
     * @param string $status
     * @return mixed
     */
    public function getPrestaStatus($status)
    {
        switch ($status) {
            case \Genesis\API\Constants\Transaction\States::APPROVED:
                return Configuration::get('PS_OS_WS_PAYMENT');
                break;
            case \Genesis\API\Constants\Transaction\States::REFUNDED:
                return Configuration::get('PS_OS_REFUND');
                break;
            case \Genesis\API\Constants\Transaction\States::PENDING:
            case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                return Configuration::get('PS_OS_PREPARATION');
                break;
            default:
                return Configuration::get('PS_OS_ERROR');
                break;
        }
    }

    /**
     * Get Prestashop status based on Genesis transaction type
     *
     * Useful for: Capture, Refund, Void
     *
     * @param string $transaction_type
     * @return int
     */
    public function getPrestaBackendStatus($transaction_type)
    {
        switch ($transaction_type) {
            case \Genesis\API\Constants\Transaction\Types::CAPTURE:
                return Configuration::get('PS_OS_WS_PAYMENT');
                break;
            case \Genesis\API\Constants\Transaction\Types::REFUND:
                return Configuration::get('PS_OS_REFUND');
                break;
            case \Genesis\API\Constants\Transaction\Types::VOID:
                return Configuration::get('PS_OS_CANCELED');
                break;
            default:
                return Configuration::get('PS_OS_PREPARATION');
                break;
        }
    }

    /**
     * Check whether we can process the selected currency
     *
     * @param $cart
     *
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order    = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Redirect to page
     * Note: Keep in mind backward compatible page names
     *
     * @param string $page Prestashop Page
     * @param array $args Optional GET arguments
     */
    public function redirectToPage($page, $args = array())
    {
        $default = array(
            'id_cart'   => (int)$this->context->cart->id,
            'id_module' => (int)$this->id,
            'id_order'  => (int)$this->currentOrder,
            'key'       => $this->context->customer->secure_key
        );

        $params = array_merge($default, $args);

        Tools::redirect($this->context->link->getPageLink($page, true, null, $params));
    }

    /**
     * Get a session variable, unique to this module
     *
     * @param string $key Name of the variable
     *
     * @return mixed
     */
    public function getSessVar($key)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $content = '';

        if (isset($_SESSION[$this->name][$key])) {
            $content = $_SESSION[$this->name][$key];

            unset($_SESSION[$this->name][$key]);
        }

        return $content;
    }

    /**
     * Set a session variable, unique to this module
     *
     * @param string $key Name of the variable
     * @param mixed $value Value of the variable
     *
     * @return void
     */
    public function setSessVar($key = null, $value = null)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION[$this->name][$key] = trim($value);
    }

    /**
     * Get Notification URL
     *
     * @return mixed Http URL
     */
    private function getNotificationURL()
    {
        return $this->context->link->getModuleLink($this->name, 'notification', array());
    }

    /**
     * Get Success URL for Async Transactions
     *
     * @return mixed Http URL
     */
    private function getAsyncSuccessURL()
    {
        return $this->context->link->getModuleLink($this->name, 'redirect', array('action' => 'success'));
    }

    /**
     * Get Failure URL for Async Transactions
     *
     * @return mixed Http URL
     */
    private function getAsyncFailureURL()
    {
        return $this->context->link->getModuleLink($this->name, 'redirect', array('action' => 'failure'));
    }

    /**
     * Get Cancel URL for Async Transactions
     *
     * @return mixed Http URL
     */
    private function getAsyncCancelURL()
    {
        return $this->context->link->getModuleLink($this->name, 'redirect', array('action' => 'cancel'));
    }

    /**
     * Find/Fetch template from the templates directory
     *
     * @param $name
     *
     * @return mixed
     */
    private function fetchTemplate($name)
    {
        if (version_compare(_PS_VERSION_, '1.6', '<')) {

            $locations = array(
                '/views/templates/hook/' . $name,
                '/views/templates/front/' . $name,
                '/views/templates/admin/' . $name
            );

            foreach ($locations as $file) {
                if (@filemtime(dirname(__FILE__) . $file)) {
                    return $this->display(__FILE__, $file);
                }
            }
        }

        return $this->display(__FILE__, $name);
    }

    private function getCheckoutTransactionTypes()
    {
        $processed_list = array();

        $selected_types = json_decode(
            Configuration::get(self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES)
        );

        $alias_map = array(
            \Genesis\API\Constants\Payment\Methods::EPS         =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY    =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::QIWI        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TELEINGRESO =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY   =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::BCMC        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::MYBANK      =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::IDEAL       =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
        );

        $orderItemsList = $this->getItemList();
        $userIdHash = $this->getCurrentUserIdHash();

        $transactionsCustomParams = array(
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE => array(
                'card_type'   =>
                    \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes::VIRTUAL,
                'redeem_type' =>
                    \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes::INSTANT
            ),
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_YEEPAY => array(
                'card_type'        =>
                    \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes::VIRTUAL,
                'redeem_type'      =>
                    \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes::INSTANT,
                'product_name'     => $orderItemsList,
                'product_category' => $orderItemsList
            ),
            \Genesis\API\Constants\Transaction\Types::CITADEL_PAYIN => array(
                'merchant_customer_id' => $userIdHash
            ),
            \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN => array(
                'customer_account_id' => $userIdHash
            ),
            \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN => array(
                'customer_account_id' => $userIdHash
            )
        );

        foreach ($selected_types as $selected_type) {

            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $processed_list[$transaction_type]['parameters'][] = array(
                    'payment_method' => $selected_type
                );
                continue;
            }

            if (array_key_exists($selected_type, $transactionsCustomParams)) {

                $processed_list[$selected_type]['name'] = $selected_type;

                $processed_list[$selected_type]['parameters'] = $transactionsCustomParams[$selected_type];

                continue;
            }

            $processed_list[] = $selected_type;

        }

        return $processed_list;
    }

    /**
     * Get current user id. Returns user id or empty if no user is logged in.
     *
     * @return mixed
     */
    private function getCurrentUserId()
    {
        return $this->context->customer->id;
    }

    /**
     *  Get current user hash.
     *  Generate hash from transaction id if no user logged in
     *
     * @param int $length
     * @return string
     */
    private function getCurrentUserIdHash ($length = 20)
    {
        $userId = self::getCurrentUserId();
        $userHash = $userId > 0 ? sha1($userId) : $this->generateTransactionId();
        return substr($userHash, 0, $length);
    }

    /**
     * Get list of items in the order
     *
     * @return string Formatted List of Items
     */
    public function getItemList()
    {

        $description = '';

        foreach ($this->context->cart->getProducts() as $product) {
            $description .= sprintf("%s (%s) x %d\r\n", $product['name'], $product['category'], $product['cart_quantity']);
        }

        return $description;
    }

    /**
     * Get Module's configuration fields
     *
     * @return array field keys
     */
    public function getConfigKeys()
    {
        return array(
            self::SETTING_EMERCHANTPAY_USERNAME,
            self::SETTING_EMERCHANTPAY_PASSWORD,
            self::SETTING_EMERCHANTPAY_TOKEN,
            self::SETTING_EMERCHANTPAY_ENVIRONMENT,
            self::SETTING_EMERCHANTPAY_DIRECT,
            self::SETTING_EMERCHANTPAY_DIRECT_TRX_TYPE,
            self::SETTING_EMERCHANTPAY_CHECKOUT,
            self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE,
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND,
            self::SETTING_EMERCHANTPAY_ALLOW_VOID,
            self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT
        );
    }

    /**
     * Get the configuration keys and their respective values
     *
     * @return array Key => Value array
     */
    private function getConfigValues()
    {
        $config_key_value = array();

        foreach ($this->getConfigKeys() as $config_key) {
            if (in_array($config_key, array(self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES))) {
                $config_key_value[$config_key . '[]'] = json_decode(Configuration::get($config_key));
            } else {
                $config_key_value[$config_key] = Configuration::get($config_key);
            }
        }

        return $config_key_value;
    }

    /**
     * Get the Module Settings HTML code
     *
     * @return string HTML code
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {

            foreach ($this->getConfigKeys() as $key) {
                $value = Tools::getValue($key);

                if (in_array($key, array(self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES))) {
                    $value = json_encode($value);
                }

                if (!Validate::isConfigName($key)) {
                    $output = $this->displayError($this->l('Invalid config name: ' . $key));
                } elseif (is_string($value) && strlen($value) == 0) {
                    $output = $this->displayError($this->l('Invalid content for: ' . $key));
                } else {
                    Configuration::updateValue($key, $value);
                }
            }

            // If $output is empty - everything went fine
            if (empty($output)) {
                $output = $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->_displayForm();
    }

    /**
     * Log an exception in PHP's error log
     *
     * @param \Exception $exception
     */
    private function logError($exception)
    {
        error_log($exception->getMessage());

        if (class_exists('Logger')) {
            Logger::addLog(
                $exception->getMessage(),
                4,
                $exception->getCode(),
                $this->displayName,
                $this->id,
                true
            );
        }
    }

    /**
     * Get form credentials fields (User, Pass, Token ...)
     *
     * @return array
     */
    private function getFormCredentialsFields () {
        return array(
            array(
                'type' => 'text',
                'label' => $this->l('Username'),
                'desc' => $this->l(
                    'Enter your Username, required for accessing the Genesis Gateway'
                ),
                'name' => self::SETTING_EMERCHANTPAY_USERNAME,
                'size' => 20,
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Password'),
                'desc' => $this->l(
                    'Enter your Password, required for accessing the Genesis Gateway'
                ),
                'name' => self::SETTING_EMERCHANTPAY_PASSWORD,
                'size' => 20,
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Token'),
                'desc' => $this->l(
                    'Enter your Token, required for accessing the Genesis Gateway.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_TOKEN,
                'size' => 20,
                'required' => true
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Environment'),
                'desc' => $this->l(
                    'Select the environment you wish to use for processing your transactions.' . PHP_EOL .
                    'Note: Its recommended to use the Sandbox environment every-time you alter your settings, in order to ensure everything works as intended.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_ENVIRONMENT,
                'options' => array(
                    'query' => array(
                        array(
                            'id' => 'sandbox',
                            'name' => $this->l('Sandbox')
                        ),
                        array(
                            'id' => 'production',
                            'name' => $this->l('Production')
                        )
                    ),
                    'id' => 'id',
                    'name' => 'name',
                )
            ),
        );
    }

    /**
     * Generate form select options from array.
     * Array key will be used as id attribute, value as name
     *
     * @param $options
     * @param string $id
     * @param string $name
     * @return mixed
     */
    private function generateOptionsFromArray ($options, $id = 'id', $name = 'name') {
        foreach ($options as $key => &$value) {
            $value = array(
                $id => $key,
                $name => $value
            );
        }

        return $options;
    }

    /**
     * Get form transactions fields
     *
     * @return array
     */
    private function getFormTransactionFields () {
        return array(
            array(
                'type' => 'switch',
                'label' => 'Direct (Hosted) Payment Method',
                'desc' => $this->l(
                    'Enable/Disable the Direct API - allow customers to enter their CreditCard information on your website.' . PHP_EOL .
                    'Note: You need PCI-DSS certificate in order to enable this feature.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_DIRECT,
                'values' => array(
                    array(
                        'value' => '1',
                    ),
                    array(
                        'value' => '0'
                    )
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Direct Transaction Type'),
                'desc' => $this->l(
                    'Select the transaction type you want to use for Direct processing.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_DIRECT_TRX_TYPE,
                'options' => array(
                    'query' => $this->generateOptionsFromArray(
                        array(
                            \Genesis\API\Constants\Transaction\Types::AUTHORIZE    => $this->l('Authorize'),
                            \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D => $this->l('Authorize 3D'),
                            \Genesis\API\Constants\Transaction\Types::SALE         => $this->l('Sale'),
                            \Genesis\API\Constants\Transaction\Types::SALE_3D      => $this->l('Sale 3D')
                        )
                    ),
                    'id' => 'id',
                    'name' => 'name',
                )
            ),
            array(
                'type' => 'switch',
                'label' => 'Checkout (Remote) Payment Method',
                'desc' => $this->l(
                    'Enable/Disable the Checkout payment method - receive credit-card payments, without the need of PCI-DSS certificate or HTTPS.' . PHP_EOL .
                    'Note: Upon checkout, the customer will be redirected to a secure payment form, located on our servers and we will notify you, once the payment reached a final status'
                ),
                'name' => self::SETTING_EMERCHANTPAY_CHECKOUT,
                'values' => array(
                    array(
                        'value' => '1'
                    ),
                    array(
                        'value' => '0'
                    )
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Checkout Transaction Types'),
                'desc' => $this->l(
                    'Select the transaction types you want to use during Checkout session.'
                ),
                'id' => self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
                'name' => self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES . '[]',
                'multiple' => true,
                'options' => array(
                    'query' => $this->generateOptionsFromArray(
                        array(
                            \Genesis\API\Constants\Transaction\Types::ABNIDEAL            => $this->l('ABN iDEAL'),
                            \Genesis\API\Constants\Transaction\Types::AUTHORIZE           => $this->l('Authorize'),
                            \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D        => $this->l('Authorize 3D'),
                            \Genesis\API\Constants\Transaction\Types::CASHU               => $this->l('CashU'),
                            \Genesis\API\Constants\Transaction\Types::CITADEL_PAYIN       => $this->l('Citadel'),
                            \Genesis\API\Constants\Payment\Methods::EPS                   => $this->l('eps'),
                            \Genesis\API\Constants\Transaction\Types::EZEEWALLET          => $this->l('eZeeWallet'),
                            \Genesis\API\Constants\Payment\Methods::GIRO_PAY              => $this->l('GiroPay'),
                            \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN        => $this->l('iDebit'),
                            \Genesis\API\Constants\Transaction\Types::INPAY               => $this->l('INPay'),
                            \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN   => $this->l('InstaDebit'),
                            \Genesis\API\Constants\Payment\Methods::BCMC                  => $this->l('Mr.Cash'),
                            \Genesis\API\Constants\Payment\Methods::MYBANK                => $this->l('MyBank'),
                            \Genesis\API\Constants\Transaction\Types::NETELLER            => $this->l('Neteller'),
                            \Genesis\API\Constants\Transaction\Types::P24                 => $this->l('P24'),
                            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE   => $this->l('PayByVoucher (Sale)'),
                            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_YEEPAY => $this->l('PayByVoucher (oBeP)'),
                            \Genesis\API\Constants\Transaction\Types::PAYPAL_EXPRESS      => $this->l('PayPal Express'),
                            \Genesis\API\Constants\Transaction\Types::PAYSAFECARD         => $this->l('PaySafeCard'),
                            \Genesis\API\Constants\Transaction\Types::POLI                => $this->l('POLi'),
                            \Genesis\API\Constants\Payment\Methods::PRZELEWY24            => $this->l('Przelewy24'),
                            \Genesis\API\Constants\Payment\Methods::QIWI                  => $this->l('Qiwi'),
                            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY            => $this->l('SafetyPay'),
                            \Genesis\API\Constants\Transaction\Types::SALE                => $this->l('Sale'),
                            \Genesis\API\Constants\Transaction\Types::SALE_3D             => $this->l('Sale 3D'),
                            \Genesis\API\Constants\Transaction\Types::SDD_SALE            => $this->l('Sepa Direct Debit'),
                            \Genesis\API\Constants\Transaction\Types::SOFORT              => $this->l('SOFORT'),
                            \Genesis\API\Constants\Payment\Methods::TELEINGRESO           => $this->l('teleingreso'),
                            \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE        => $this->l('Trustly'),
                            \Genesis\API\Constants\Payment\Methods::TRUST_PAY             => $this->l('TrustPay'),
                            \Genesis\API\Constants\Transaction\Types::WEBMONEY            => $this->l('WebMoney'),
                        )
                    ),
                    'id' => 'id',
                    'name' => 'name',
                )
            ),
        );
    }

    /**
     * Generate the Module Settings HTML via HelperForm()
     *
     * @return mixed HTML Content
     */
    private function _displayForm()
    {
        $form_structure = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('eMerchantPay Configuration'),
                    'icon' => 'icon-cog'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => 'Partial Capture',
                        'desc' => $this->l(
                            'Use this option to allow / deny Partial Capture Transactions'
                        ),
                        'name' => self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE,
                        'values' => array(
                            array(
                                'value' => '1'
                            ),
                            array(
                                'value' => '0'
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => 'Partial Refund',
                        'desc' => $this->l(
                            'Use this option to allow / deny Partial Refund Transactions'
                        ),
                        'name' => self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND,
                        'values' => array(
                            array(
                                'value' => '1'
                            ),
                            array(
                                'value' => '0'
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => 'Cancel Transaction',
                        'desc' => $this->l(
                            'Use this option to allow / deny Cancel Transactions'
                        ),
                        'name' => self::SETTING_EMERCHANTPAY_ALLOW_VOID,
                        'values' => array(
                            array(
                                'value' => '1'
                            ),
                            array(
                                'value' => '0'
                            )
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

        /** Add form fields */
        $form_structure['form']['input'] = array_merge(
            $this->getFormCredentialsFields(),
            $this->getFormTransactionFields(),
            $form_structure['form']['input']
        );

        /**
         * Option for registering jQuery to Checkout Page
         *
         * Note: 1.7.x does not register jQuery on the Checkout Page, so we are adding this option
         * in order to be disabled if jQuery has been added from other module
         */
        if ($this->isPrestaVersion17()) {
            $form_structure['form']['input'][] = array(
                'type' => 'switch',
                'label' => 'Include jQuery Plugin to Checkout Page',
                'desc' => $this->l(
                    'Use this option to allow / deny jQuery Plugin Registration. This option should be enabled unless jQuery has already been registered.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT,
                'values' => array(
                    array(
                        'value' => '1'
                    ),
                    array(
                        'value' => '0'
                    )
                )
            );
        }

        $helper = new HelperForm();
        // Title and toolbar
        $helper->title          = $this->displayName;
        $helper->show_toolbar   = false;
        $helper->toolbar_scroll = false;
        // Module, token and currentIndex
        $helper->id           = (int)Tools::getValue('id_carrier');
        $helper->identifier   = $this->identifier;
        $helper->token        = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules',
                false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;

        // Language
        $helper->default_form_language    = intval(Configuration::get('PS_LANG_DEFAULT'));
        $helper->allow_employee_form_lang = intval(Configuration::get('PS_LANG_DEFAULT'));

        $helper->submit_action = 'submit' . $this->name;

        $helper->tpl_vars = array(
            'fields_value'  => $this->getConfigValues(),
            'id_language'   => $this->context->language->id,
            'languages'     => $this->context->controller->getLanguages(),
        );
        
        return $helper->generateForm(
            array($form_structure)
        );
    }

    /**
     * Initialize the module and check for compatibility
     *
     * @return void
     */
    private function init()
    {
        /* Backward compatibility */
        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            include_once dirname(__FILE__) . '/backward_compatibility/backward.php';
        }

        /** Check if SSL is enabled and only DirectPayment Method is Enabled */
        if (!$this->getIsSSLEnabled() && $this->isDirectPaymentMethodAvailable() && !$this->isCheckoutPaymentMethodAvailable()) {
        	$this->warning = $this->l( 'This plugin requires SSL enabled and PCI-DSS compliant server in order to accept customer\'s credit card information directly on your website!' );
        }

        /* Bootstrap Genesis */
        include_once dirname(__FILE__) . '/lib/genesis/vendor/autoload.php';

        /* eMerchantPay Install Helper */
        include_once dirname(__FILE__) . '/classes/eMerchantPayInstall.php';

        /* eMerchantPay Transaction Model */
        include_once dirname(__FILE__) . '/classes/eMerchantPayTransaction.php';

        /* eMerchantPay Transaction Processor */
        include_once dirname(__FILE__) . '/classes/eMerchantPayTransactionProcess.php';

        /* Check if Genesis Library is initialized */
        if (!class_exists('\Genesis\Genesis')) {
            $this->warning = 'Sorry, there was a problem initializing Genesis client, please verify your installation!';
        }
        
        /* Catch Block added -> Prestashop 1.6.0 calls Model Constructor even when the Module is not yet installed */
        try {
            /* Check and update database if necessary */
            eMerchantPayInstall::doProcessSchemaUpdate();
        }
        catch (\Exception $e) {
           /* just ignore and log exception - Init Method is called on Upload Module (it should be called after Module is installed) */
           $this->logError($e);
        } 
				
        /* Verify system requirements */
        try {
            \Genesis\Utils\Requirements::verify();
        } catch(\Exception $e) {
            $this->warning = $this->l('Your server does not meet the minimum system requirements! Contact your hosting provider for assistance!');
        }

        /* Check if the module is configured */
        if (Configuration::get('EMERCHANTPAY_USERNAME') && Configuration::get('EMERCHANTPAY_PASSWORD')) {
            $this->applyGenesisConfig();
        } else {
            $this->warning = $this->l('You need to set your credentials (username, password), in order to use Genesis Payment Gateway!');

        }
    }

    /**
     * Set Genesis Configuration based on the module settings
     *
     * @return void
     */
    public function applyGenesisConfig()
    {
        \Genesis\Config::setEndpoint(
            \Genesis\API\Constants\Endpoints::EMERCHANTPAY
        );

        \Genesis\Config::setUsername(
            Configuration::get('EMERCHANTPAY_USERNAME')
        );
        \Genesis\Config::setPassword(
            Configuration::get('EMERCHANTPAY_PASSWORD')
        );
        \Genesis\Config::setToken(
            Configuration::get('EMERCHANTPAY_TOKEN')
        );

        \Genesis\Config::setEnvironment(
            Configuration::get('EMERCHANTPAY_ENVIRONMENT')
        );
    }

    /**
     * Determines if the Store is running over secured connection
     * @return bool
     */
    protected function getIsSSLEnabled()
    {
        return Configuration::get('PS_SSL_ENABLED');
    }

    /**
     * Get a state iso by its id
     *
     * @param int $state_id
     *
     * @return string
     */
    public static function getStateIsoCodeById($state_id)
    {
        return Db::getInstance()->getValue('
		SELECT `iso_code`
		FROM `'._DB_PREFIX_.'state`
		WHERE `id_state` = ' . (int)$state_id);
    }

    /**
     * Get a country iso by its id
     *
     * @param int $country_id
     *
     * @return string
     */
    public static function getCountryIsoCodeById($country_id)
    {
        return Db::getInstance()->getValue('
		SELECT `iso_code`
		FROM `'._DB_PREFIX_.'country`
		WHERE `id_country` = ' . (int)$country_id);
    }

    /**
     * Migrates old Toggle Button values (true => 1; false => 0)
     * @return void
     */
    protected function doMigrateSettings()
    {
        $toggleSettingKeys = array(
            self::SETTING_EMERCHANTPAY_DIRECT,
            self::SETTING_EMERCHANTPAY_CHECKOUT
        );

        foreach ($toggleSettingKeys as $toggleSettingKey) {
            $settingValue = strtolower(Configuration::get($toggleSettingKey));
            if ($settingValue == 'true') {
                Configuration::updateValue($toggleSettingKey, '1');
            } elseif ($settingValue == 'false') {
                Configuration::updateValue($toggleSettingKey, '0');
            }
        }
    }

    /**
     * Prepares default values for some configuration keys
     *
     * @return bool
     */
    protected function setDefaultSettingsToDB()
    {
        $defaultConfigItems = array(
            self::SETTING_EMERCHANTPAY_DIRECT          => '0',
            self::SETTING_EMERCHANTPAY_CHECKOUT        => '0',
            self::SETTING_EMERCHANTPAY_DIRECT_TRX_TYPE =>
                \Genesis\API\Constants\Transaction\Types::AUTHORIZE,
            self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES => array(
                \Genesis\API\Constants\Transaction\Types::AUTHORIZE,
                \Genesis\API\Constants\Transaction\Types::SALE,
            ),
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE => '1',
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND  => '1',
            self::SETTING_EMERCHANTPAY_ALLOW_VOID            => '1',
            self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT   => '1'
        );

        try {
            foreach ($defaultConfigItems as $key => $value) {
                $value = is_array($value) ? json_encode($value) : $value;
                Configuration::updateValue($key, $value);
            }

            return true;
        } catch (Exception $e) {
            $this->logError($e);
            return false;
        }
    }

    /**
     * Retrieves a bool setting value by key
     *
     * @param string $key
     * @return bool
     */
    protected function getBoolConfigurationValue($key)
    {
        return Configuration::get($key) == '1';
    }

    /**
     * Registers Javascript File on the current page
     * Note: used for PrestaShop 1.7.x
     *
     * @param string|null $relativePath
     * @param array $params
     */
    protected function registerCore17Javascript($relativePath, $params = array('position' => 'head'))
    {
        if (!$relativePath) {
            return;
        }

        $this->context->controller->registerJavascript(
            sha1($relativePath),
            $relativePath,
            $params
        );
    }

    /**
     * Retrieves if the current PrestaSHop Version is 1.7.x
     *
     * @return bool
     */
    protected function isPrestaVersion17()
    {
        return
            version_compare(_PS_VERSION_, '1.7', '>=') &&
            version_compare(_PS_VERSION_, '1.8', '<');
    }

    /**
     * Retrieves the current jQuery path
     *
     * @return null|string
     */
    protected function getJQueryUri()
    {
        if (defined('_PS_JQUERY_VERSION_')) {
            return _PS_JS_DIR_. "jquery/jquery-" . _PS_JQUERY_VERSION_ . ".min.js";
        }

        return null;
    }
}
