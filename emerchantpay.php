<?php
/**
 * Copyright (C) 2018 emerchantpay Ltd.
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
 * @author      emerchantpay
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The main Emerchantpay class that handles
 * all the logic related to the payment module
 */
class Emerchantpay extends PaymentModule
{
    /**
     * Name of the BO orders controller
     */
    public const PS_CONTROLLER_ADMIN_ORDERS = 'AdminOrders';

    /**
     * List supported languages
     *
     * @var array
     */
    private $languages = [];

    /**
     * Configurable module settings
     */
    public const SETTING_EMERCHANTPAY_USERNAME = 'EMERCHANTPAY_USERNAME';
    public const SETTING_EMERCHANTPAY_PASSWORD = 'EMERCHANTPAY_PASSWORD';
    public const SETTING_EMERCHANTPAY_ENVIRONMENT = 'EMERCHANTPAY_ENVIRONMENT';
    public const SETTING_EMERCHANTPAY_CHECKOUT = 'EMERCHANTPAY_CHECKOUT';
    public const SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES = 'EMERCHANTPAY_CHECKOUT_TRX_TYPES';
    public const SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE = 'EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE';
    public const SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND = 'EMERCHANTPAY_ALLOW_PARTIAL_REFUND';
    public const SETTING_EMERCHANTPAY_ALLOW_VOID = 'EMERCHANTPAY_ALLOW_VOID';
    public const SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT = 'EMERCHANTPAY_ADD_JQUERY_CHECKOUT';
    public const SETTING_EMERCHANTPAY_WPF_TOKENIZATION = 'EMERCHANTPAY_WPF_TOKENIZATION';
    public const SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES = 'EMERCHANTPAY_CHECKOUT_BANK_CODES';
    public const SETTING_EMERCHANTPAY_THREEDS_ALLOWED = 'EMERCHANTPAY_THREEDS_ALLOWED';
    public const SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR = 'EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR';
    public const SETTING_EMERCHANTPAY_SCA_EXEMPTION = 'EMERCHANTPAY_SCA_EXEMPTION';
    public const SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT = 'EMERCHANTPAY_SCA_EXEMPTION_AMOUNT';

    /**
     * Transaction Type specifics
     */
    public const PPRO_TRANSACTION_SUFFIX = '_ppro';
    public const GOOGLE_PAY_TRANSACTION_PREFIX = 'google_pay_';
    public const GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE = 'authorize';
    public const GOOGLE_PAY_PAYMENT_TYPE_SALE = 'sale';
    public const PAYPAL_TRANSACTION_PREFIX = 'pay_pal_';
    public const PAYPAL_PAYMENT_TYPE_AUTHORIZE = 'authorize';
    public const PAYPAL_PAYMENT_TYPE_SALE = 'sale';
    public const PAYPAL_PAYMENT_TYPE_EXPRESS = 'express';
    public const APPLE_PAY_TRANSACTION_PREFIX = 'apple_pay_';
    public const APPLE_PAY_PAYMENT_TYPE_AUTHORIZE = 'authorize';
    public const APPLE_PAY_PAYMENT_TYPE_SALE = 'sale';

    /**
     * Custom prefix
     */
    public const PLATFORM_TRANSACTION_PREFIX = 'ps-';

    /**
     * Constructor
     */
    public function __construct()
    {
        /* Initial Module Setup */
        $this->name = 'emerchantpay';
        $this->tab = 'payments_gateways';
        $this->displayName = 'emerchantpay Payment Gateway';
        $this->controllers = ['checkout', 'notification', 'redirect', 'validation'];
        $this->version = '2.0.3';
        $this->author = 'emerchantpay Ltd.';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        $this->bootstrap = true;
        $this->module_key = '944a03157ee547ee63f641035f559380';

        /* The parent construct is required for translations */
        $this->page = basename(__FILE__, '.php');
        $this->description = $this->l('Accept payments through emerchantpay Payment Gateway - Genesis');

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
            [
                'name' => [
                    'module' => $this->name,
                    'display' => $this->displayName,
                    'store' => Configuration::get('PS_SHOP_NAME'),
                ],
                'path' => $this->getPathUri(),
                'presta' => [
                    'url' => Tools::getHttpHost(true) . __PS_BASE_URI__,
                    'version' => _PS_VERSION_,
                ],
                'version' => $this->version,
                'warning' => $this->warning,
            ]
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

        $install = new EmerchantpayInstall();

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

        $uninstall = new EmerchantpayInstall();

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
     * Is Checkout payment method available?
     *
     * @return bool
     */
    public function isCheckoutPaymentMethodAvailable()
    {
        return $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_CHECKOUT);
    }

    /**
     * Is WPF Tokenization enabled?
     *
     * @return bool
     */
    public function isWpfTokenizationEnabled()
    {
        return $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_WPF_TOKENIZATION);
    }

    /**
     * Is 3DS v2 enabled?
     *
     * @return bool
     */
    public function isThreedsAllowed()
    {
        return $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_THREEDS_ALLOWED);
    }

    /**
     * Hook AdminOrder to display the saved transactions,
     *
     * @param array $params
     *
     * @return string HTML source
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrder($params)
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

        $order = new Order((int) $params['id_order']);

        if ($order->payment != $this->displayName) {
            return '';
        }

        if (!$this->isPrestaVersion177()) {
            $this->addAssets();
        }

        $currency = new Currency((int) $order->id_currency);

        $this->context->smarty->append(
            'emerchantpay',
            [
                'transactions' => [
                    'order' => [
                        'id' => $order->id,
                        'amount' => $order->getTotalPaid(),
                        'currency' => [
                            'iso_code' => $currency->iso_code,
                            'sign' => $currency->sign,
                            'decimalPlaces' => 2,
                            'decimalSeparator' => '.',
                            'thousandSeparator' => '',
                            /* must be empty, otherwise exception could be thrown from Genesis */
                        ],
                    ],
                    'options' => [
                        'allow_partial_capture' => $this->getBoolConfigurationValue(
                            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE
                        ),
                        'allow_partial_refund' => $this->getBoolConfigurationValue(
                            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND
                        ),
                        'allow_void' => $this->getBoolConfigurationValue(
                            self::SETTING_EMERCHANTPAY_ALLOW_VOID
                        ),
                    ],
                    'text' => [
                        'denied_partial_capture' => $this->l(
                            'Partial Capture is currently disabled! You can enable this option in the Module Settings.'
                        ),
                        'denied_partial_refund' => $this->l(
                            'Partial Refund is currently disabled! You can enable this option in the Module Settings.'
                        ),
                        'denied_void' => $this->l(
                            'Cancel Transaction are currently disabled! You can enable this option in the Module Settings.'
                        ),
                    ],
                    'error' => $this->getSessVar('error_transaction'),
                    'tree' => EmerchantpayTransaction::getTransactionTree((int) $params['id_order']),
                ],
            ],
            true
        );

        return
            $this->isPrestaVersion177() ?
                $this->display(__FILE__, '/views/templates/admin/admin_order/transactions-bootstrap-4.tpl') :
                $this->display(__FILE__, '/views/templates/admin/admin_order/transactions.tpl');
    }

    /**
     * Add needed CSS & JavaScript files in the BO.
     *
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        if ($this->isPrestaVersion177()) {
            $this->addAssets();
        }
    }

    /**
     * Hook DisplayOrderDetail to display the saved transactions,
     * related to the order
     *
     * @param array $params
     *
     * @return string HTML source
     */
    public function hookDisplayOrderDetail($params)
    {
        $order = $params['order'];

        if ($order->payment != $this->displayName) {
            return '';
        }

        $this->context->controller->addCSS(
            $this->getPathUri() . 'views/css/font-awesome.min.css',
            'all'
        );

        if ($this->isPrestaVersion17() && $this->getBoolConfigurationValue(self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT)) {
            $this->registerCore17Javascript(
                $this->getJQueryUri()
            );
        }

        $this->context->controller->addCSS(
            $this->getPathUri() . 'views/css/treegrid.min.css',
            'all'
        );
        $this->context->controller->addJS(
            $this->getPathUri() . 'views/js/treegrid/treegrid.min.js'
        );

        $this->context->smarty->append(
            'emerchantpay',
            [
                'transactions' => [
                    'tree' => EmerchantpayTransaction::getTransactionTree((int) $order->id),
                ],
            ],
            true
        );

        return $this->display(__FILE__, '/views/templates/hook/orderdetails.tpl');
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

        $this->context->smarty->append(
            'emerchantpay',
            [
                'payment' => [
                    'option' => [
                        'selected_id' => Tools::getIsset('select_payment_option')
                                ? Tools::getValue('select_payment_option')
                                : '',
                    ],
                    'methods' => [
                        'checkout' => $this->isCheckoutPaymentMethodAvailable(),
                    ],
                    'errors' => [
                        'checkout' => $this->getSessVar('error_checkout'),
                    ],
                ],
                'ssl' => [
                    'enabled' => $this->getIsSSLEnabled(),
                ],
                'legal' => [
                    'year' => date('Y'),
                ],
            ],
            true
        );

        $self = $this;
        $paymentMethods = [
            [
                'title' => 'Pay safely with emerchantpay Checkout',
                'name' => 'checkout',
                'clientSideEvents' => [
                    'onFormSubmit' => 'return doBeforeSubmitEMerchantPayCheckoutPaymentForm(this);',
                ],
                'availabilityClosure' => function () use ($self) {
                    return $self->isCheckoutPaymentMethodAvailable();
                },
            ],
        ];

        $paymentOptions = [];

        foreach ($paymentMethods as $paymentMethod) {
            $availabilityClosure = $paymentMethod['availabilityClosure'];
            if (!is_callable($availabilityClosure) || $availabilityClosure()) {
                $paymentMethodOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $paymentMethodOption
                    ->setCallToActionText($paymentMethod['title'])
                    ->setForm($this->generateMethodForm($paymentMethod))
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
     * Works only with version <= 1.6.x
     *
     * @return mixed
     */
    public function hookPayment()
    {
        if (!$this->isAvailable()) {
            return $this->display(__FILE__, 'blank.tpl');
        }

        $this->context->smarty->append(
            'emerchantpay',
            [
                'payment' => [
                    'urls' => [
                        'checkout' => $this->context->link->getModuleLink($this->name, 'checkout'),
                    ],
                    'errors' => [
                        'checkout' => $this->getSessVar('error_checkout'),
                    ],
                    'methods' => [
                        'checkout' => $this->isCheckoutPaymentMethodAvailable(),
                    ],
                ],
                'ssl' => [
                    'enabled' => $this->getIsSSLEnabled(),
                ],
                'legal' => [
                    'year' => date('Y'),
                ],
            ],
            true
        );

        return $this->display(__FILE__, 'payment.tpl');
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

        $this->context->controller->addJS(
            $this->getPathUri() . 'views/js/card/card.min.js'
        );
    }

    /**
     * Show an information about the customers order
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

            $this->smarty->assign(
                'order',
                [
                    'reference' => $reference,
                    'valid' => $params['objOrder']->valid,
                ]
            );
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
            [
                'confirmation' => [
                    'status' => $status,
                ],
            ],
            true
        );

        return $this->display(__FILE__, 'confirmation.tpl');
    }

    /**
     * Generate transaction id
     *
     * @param $length
     *
     * @return string
     */
    private function generateTransactionId($length = 30)
    {
        return self::PLATFORM_TRANSACTION_PREFIX . Tools::substr(
            md5(mt_rand() . microtime(true) . uniqid()),
            0,
            $length
        );
    }

    /**
     * Collect and process the data required for the initial payment.
     *
     * @return stdClass Processed data
     */
    public function populateTransactionData()
    {
        /** @var CartCore $cart */
        $cart = new Cart((int) $this->context->cart->id);

        /** @var AddressCore $shipping */
        $shipping = new Address((int) $cart->id_address_delivery);
        /** @var AddressCore $invoice */
        $invoice = new Address((int) $cart->id_address_invoice);
        /** @var CustomerCore $customer */
        $customer = new Customer((int) $cart->id_customer);
        /** @var CurrencyCore $currency */
        $currency = new Currency((int) $cart->id_currency);

        $data = new stdClass();

        // Parameters
        $data->id = $this->generateTransactionId();
        $data->usage = $this->l('Payment via') . ' ' . Configuration::get('PS_SHOP_NAME');

        $description = '';

        foreach ($cart->getProducts() as $product) {
            if (isset($product['name']) && isset($product['quantity'])) {
                $quantity_text = $product['quantity'] > 1 ? $this->l('pcs') : $this->l('pc');

                $description .= $product['name'] . ' x' . $product['quantity'] . $quantity_text . PHP_EOL;
            }
        }

        $data->description = $description;

        $data->remote_ip = Tools::getRemoteAddr();
        $data->currency = $currency->iso_code;
        $data->amount = $cart->getOrderTotal();

        $data->customer = $customer;
        $data->customer_email = $customer->email;
        $data->customer_phone = (empty($invoice->phone) ? $invoice->phone_mobile : $invoice->phone);

        // Billing
        if ($invoice) {
            $countryCode = $this->getCountryIsoCodeById($invoice->id_country);

            $data->billing = new stdClass();
            $data->billing->firstname = $invoice->firstname;
            $data->billing->lastname = $invoice->lastname;
            $data->billing->address1 = $invoice->address1;
            $data->billing->address2 = $invoice->address2;
            $data->billing->postcode = $invoice->postcode;
            $data->billing->city = $invoice->city;
            $data->billing->state = $this->getStateIsoCodeById($invoice->id_state);
            $data->billing->country = $countryCode;
        }

        // Shipping
        if ($shipping) {
            if ($this->shouldGetCountryIsoCode($invoice, $shipping)) {
                $countryCode = $this->getCountryIsoCodeById($shipping->id_country);
            }

            $data->shipping = new stdClass();
            $data->shipping->firstname = $shipping->firstname;
            $data->shipping->lastname = $shipping->lastname;
            $data->shipping->address1 = $shipping->address1;
            $data->shipping->address2 = $shipping->address2;
            $data->shipping->postcode = $shipping->postcode;
            $data->shipping->city = $shipping->city;
            $data->shipping->state = $this->getStateIsoCodeById($shipping->id_state);
            $data->shipping->country = $countryCode;
        }

        // URL endpoints (Async transactions)
        $data->url = new stdClass();
        $data->url->notification = $this->getNotificationURL();
        $data->url->return_success = $this->getAsyncSuccessURL();
        $data->url->return_failure = $this->getAsyncFailureURL();
        $data->url->return_cancel = $this->getAsyncCancelURL();

        // Set WPF language
        if (in_array($this->context->language->iso_code, $this->languages)) {
            $data->language = $this->context->language->iso_code;
        }

        // Set WPF transaction types
        $data->transaction_types = $this->getCheckoutTransactionTypes($cart);

        // Set WPF tokenization flag
        $data->is_wpf_tokenization_enabled = $this->isWpfTokenizationEnabled();

        // Threeds
        $data->is_guest = Cart::isGuestCartByCartId($cart->id);

        // Order parameters
        $data->is_threeds_allowed = $this->isThreedsAllowed();
        $data->threeds_challenge_indicator = Configuration::get(
            self::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR
        );
        $data->threeds_purchase_category = $cart->isVirtualCart() ?
            Genesis\API\Constants\Transaction\Parameters\Threeds\V2\Purchase\Categories::SERVICE :
            Genesis\API\Constants\Transaction\Parameters\Threeds\V2\Purchase\Categories::GOODS;
        $data->threeds_delivery_timeframe = $cart->isVirtualCart() ?
            Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\DeliveryTimeframes::ELECTRONICS :
            Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\DeliveryTimeframes::ANOTHER_DAY;
        $data->threeds_shipping_indicator = EmerchantpayThreeds::getShippingIndicator(
            $cart,
            $invoice,
            $shipping,
            Cart::isGuestCartByCartId($cart->id)
        );
        $data->threeds_reorder_items_indicator = EmerchantpayThreeds::getReorderItemsIndicator(
            $cart,
            $customer,
            Cart::isGuestCartByCartId($cart->id)
        );
        $data->threeds_registration_indicator =
            Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\RegistrationIndicators::GUEST_CHECKOUT;

        // Cardholder parameters
        if (!Cart::isGuestCartByCartId($cart->id)) {
            $data->threeds_creation_date = $customer->date_add;
            $data->threeds_registration_date = EmerchantpayThreeds::findFirstCustomerOrderDate(
                $customer
            );
            $data->threeds_registration_indicator = EmerchantpayThreeds::getRegistrationIndicator($customer);
            $data->threeds_last_change_date = EmerchantpayThreeds::findLastChangeDate(
                $customer,
                $cart->id_lang
            );
            $data->threeds_update_indicator = EmerchantpayThreeds::getUpdateIndicator(
                $customer,
                $cart->id_lang
            );
            $data->threeds_password_change_date = $customer->last_passwd_gen;
            $data->threeds_password_change_indicator = EmerchantpayThreeds::getPasswordIndicator($customer);

            $shippingAddressDateFirstUsed = EmerchantpayThreeds::findShippingAddressDateFirstUsed(
                $customer,
                $cart
            );
            $data->threeds_shipping_address_date_first_used = $shippingAddressDateFirstUsed;
            $data->threeds_shipping_address_usage_indicator = EmerchantpayThreeds::getShippingAddressUsageIndicator(
                $shippingAddressDateFirstUsed
            );
            $data->transactions_activity_last_24_hours = EmerchantpayThreeds::findNumberOfOrdersForAPeriod(
                $customer->id,
                EmerchantpayThreeds::ACTIVITY_24_HOURS
            );
            $data->transactions_activity_previous_year = EmerchantpayThreeds::findNumberOfOrdersForAPeriod(
                $customer->id,
                EmerchantpayThreeds::ACTIVITY_1_YEAR
            );
            $data->purchases_count_last_6_months = EmerchantpayThreeds::findNumberOfOrdersForLastSixMonths(
                $customer->id
            );
        }

        // SCA Exemption
        $data->sca_exemption_value = Configuration::get(self::SETTING_EMERCHANTPAY_SCA_EXEMPTION);
        $data->sca_exemption_amount = Configuration::get(self::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT);

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
     * if successful - we redirect the customer to the newly created instance
     * if unsuccessful - we show them an error message
     *
     * @return string url
     */
    public function doCheckout()
    {
        // Apply settings
        $this->applyGenesisConfig();

        try {
            $data = $this->populateTransactionData();

            if ($this->isWpfTokenizationEnabled() && !$this->context->customer->isLogged()) {
                throw new Exception('WPF Tokenization is available only for logged users');
            }

            $responseObj = EmerchantpayTransactionProcess::checkout($data);

            $response = $responseObj->getResponseObject();

            $message = 'Unique Id: ' . $response->unique_id . PHP_EOL .
                       'Transaction Id: ' . $this->transaction_data->id . PHP_EOL;

            $this->validateOrder(
                (int) $this->context->cart->id,
                (int) Configuration::get('PS_OS_PREPARATION'),
                (float) $response->amount,
                $this->displayName,
                $message,
                [],
                null,
                false,
                $this->context->customer->secure_key
            );

            // Add Transaction Info to the original Order
            $new_order = new Order((int) $this->currentOrder);

            // Save the transaction to Db
            $transaction = new EmerchantpayTransaction();
            $transaction->id_parent = 0;
            $transaction->ref_order = $new_order->reference;
            $transaction->transaction_id = $this->transaction_data->id;
            $transaction->type = 'checkout';
            $transaction->importResponse($response);
            $transaction->add();

            if (!empty($response->consumer_id)) {
                EmerchantpayConsumer::createConsumer(
                    \Genesis\Config::getUsername(),
                    $data->customer_email,
                    $response->consumer_id
                );
            }

            return $response->redirect_url;
        } catch (\Genesis\Exceptions\ErrorAPI $api) {
            $this->logError($api);

            $this->setSessVar('error_checkout', $api->getMessage());
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar(
                'error_checkout',
                'Please, make sure you\'ve entered correct credentials for accessing the gateway and all of the required data, e.g. Email, Phone, Billing/Shipping Address.'
            );
        }

        return null;
    }

    /**
     * Perform a Capture on a Genesis Transaction
     *
     * @return bool
     */
    public function doCapture()
    {
        $id_unique = Tools::getValue($this->name . '_transaction_id');
        $amount = Tools::getValue($this->name . '_transaction_amount');
        $usage = Tools::getValue($this->name . '_transaction_usage');

        $ip_addr = Tools::getRemoteAddr();

        // Apply settings
        $this->applyGenesisConfig();

        try {
            $transaction = EmerchantpayTransaction::getByUniqueId($id_unique);
            $items = null;

            if ($transaction->terminal) {
                \Genesis\Config::setToken($transaction->terminal);
            }

            if ($transaction->type === Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE) {
                $cart = new Cart($this->context->cart->id);
                $items = $this->getKlarnaCustomParamItems($cart);
            }

            $data = [
                'transaction_type' => $transaction->type,
                'transaction_id' => md5(uniqid() . mt_rand() . microtime(true)),
                'usage' => $usage,
                'remote_ip' => $ip_addr,
                'reference_id' => $transaction->id_unique,
                'currency' => $transaction->currency,
                'amount' => $amount,
                'items' => $items,
            ];

            $response = EmerchantpayTransactionProcess::capture($data);

            $transaction_response = new EmerchantpayTransaction();
            $transaction_response->id_parent = $transaction->id_unique;
            $transaction_response->ref_order = $transaction->ref_order;
            $transaction_response->importResponse($response->getResponseObject());
            if ($transaction->terminal) {
                $transaction_response->terminal = $transaction->terminal;
            }

            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_WS_PAYMENT'),
                true
            );
            $transaction_response->add();
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar(
                'error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Perform a Refund on a Genesis Transaction
     *
     * @return bool
     */
    public function doRefund()
    {
        $id_unique = Tools::getValue($this->name . '_transaction_id');
        $amount = Tools::getValue($this->name . '_transaction_amount');
        $usage = Tools::getValue($this->name . '_transaction_usage');
        $ip_addr = Tools::getRemoteAddr();

        // Apply settings
        $this->applyGenesisConfig();

        try {
            $transaction = EmerchantpayTransaction::getByUniqueId($id_unique);
            $items = null;

            if ($transaction->terminal) {
                \Genesis\Config::setToken($transaction->terminal);
            }

            if ($transaction->type === Genesis\API\Constants\Transaction\Types::KLARNA_CAPTURE) {
                $cart = new Cart($this->context->cart->id);
                $items = $this->getKlarnaCustomParamItems($cart);
            }

            $data = [
                'transaction_type' => $transaction->type,
                'transaction_id' => md5(uniqid() . mt_rand() . microtime(true)),
                'usage' => $usage,
                'remote_ip' => $ip_addr,
                'reference_id' => $transaction->id_unique,
                'currency' => $transaction->currency,
                'amount' => $amount,
                'items' => $items,
            ];

            $response = EmerchantpayTransactionProcess::refund($data);

            $transaction_response = new EmerchantpayTransaction();
            $transaction_response->id_parent = $transaction->id_unique;
            $transaction_response->ref_order = $transaction->ref_order;
            $transaction_response->importResponse($response->getResponseObject());
            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_REFUND'),
                true
            );
            $transaction_response->add();

            $transaction_response->changeParentStatus();
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar(
                'error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Perform Void (cancellation) on a Genesis Transaction
     *
     * @return bool
     */
    public function doVoid()
    {
        $id_unique = Tools::getValue($this->name . '_transaction_id');
        $usage = Tools::getValue($this->name . '_transaction_usage');
        $ip_addr = Tools::getRemoteAddr();

        // Apply settings
        $this->applyGenesisConfig();

        try {
            $transaction = EmerchantpayTransaction::getByUniqueId($id_unique);

            if ($transaction->terminal) {
                \Genesis\Config::setToken($transaction->terminal);
            }

            $data = [
                'transaction_id' => md5(uniqid() . mt_rand() . microtime(true)),
                'usage' => $usage,
                'remote_ip' => $ip_addr,
                'reference_id' => $transaction->id_unique,
            ];

            $response = EmerchantpayTransactionProcess::void($data);

            $transaction_response = new EmerchantpayTransaction();
            $transaction_response->id_parent = $transaction->id_unique;
            $transaction_response->ref_order = $transaction->ref_order;
            $transaction_response->importResponse($response->getResponseObject());
            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_CANCELED'),
                true
            );
            $transaction_response->add();

            $transaction_response->changeParentStatus();
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar(
                'error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Try to match a CreditCard Number to their CreditCard Brand
     *
     * @param $number string
     *
     * @return string
     **/
    public static function getCardTypeByNumber($number)
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
     *
     * @return mixed
     */
    public function getPrestaStatus($status)
    {
        switch ($status) {
            case \Genesis\API\Constants\Transaction\States::APPROVED:
                return Configuration::get('PS_OS_WS_PAYMENT');
            case \Genesis\API\Constants\Transaction\States::REFUNDED:
                return Configuration::get('PS_OS_REFUND');
            case \Genesis\API\Constants\Transaction\States::PENDING:
            case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                return Configuration::get('PS_OS_PREPARATION');
            default:
                return Configuration::get('PS_OS_ERROR');
        }
    }

    /**
     * Get Prestashop status based on Genesis transaction type
     *
     * Useful for: Capture, Refund, Void
     *
     * @param string $transaction_type
     *
     * @return int
     */
    public function getPrestaBackendStatus($transaction_type)
    {
        switch ($transaction_type) {
            case Types::CAPTURE:
                return Configuration::get('PS_OS_WS_PAYMENT');
            case Genesis\API\Constants\Transaction\Types::REFUND:
                return Configuration::get('PS_OS_REFUND');
            case Genesis\API\Constants\Transaction\Types::VOID:
                return Configuration::get('PS_OS_CANCELED');
            default:
                return Configuration::get('PS_OS_PREPARATION');
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
        $currency_order = new Currency((int) $cart->id_currency);
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

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
    public function redirectToPage($page, $args = [])
    {
        Tools::redirect($this->getPageLink($page, $args));
    }

    /**
     * Get page link
     *
     * @param string $page Prestashop Page
     * @param array $args Optional GET arguments
     *
     * @return string Page link
     */
    public function getPageLink($page, $args = [])
    {
        $default = [
            'id_cart' => (int) $this->context->cart->id,
            'id_module' => (int) $this->id,
            'id_order' => (int) $this->currentOrder,
            'key' => $this->context->customer->secure_key,
        ];

        $params = array_merge($default, $args);

        return $this->context->link->getPageLink($page, true, null, $params);
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
        $content = '';
        $cookie = $this->context->cookie->__get($this->name);
        $variables = json_decode($cookie, true);

        if (is_array($variables) && array_key_exists($key, $variables)) {
            $content = $variables[$key];

            unset($variables[$key]);
            $this->context->cookie->__set($this->name, json_encode($variables));
            $this->context->cookie->write();
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
        $cookie = $this->context->cookie->__get($this->name);
        $variables = json_decode($cookie, true);

        if (!$variables) {
            $variables = [];
        }

        $variables[$key] = trim($value);

        $this->context->cookie->__set($this->name, json_encode($variables));
        $this->context->cookie->write();
    }

    /**
     * Get Notification URL
     *
     * @return mixed Http URL
     */
    private function getNotificationURL()
    {
        return $this->context->link->getModuleLink($this->name, 'notification', []);
    }

    /**
     * Get Success URL for Async Transactions
     *
     * @return mixed Http URL
     */
    private function getAsyncSuccessURL()
    {
        return $this->getPageLink('order-confirmation.php');
    }

    /**
     * Get Failure URL for Async Transactions
     *
     * @return mixed Http URL
     */
    private function getAsyncFailureURL()
    {
        return $this->context->link->getModuleLink(
            $this->name,
            'redirect',
            [
                'action' => 'failure',
                'id_cart' => (int) $this->context->cart->id,
            ]
        );
    }

    /**
     * Get Cancel URL for Async Transactions
     *
     * @return mixed Http URL
     */
    private function getAsyncCancelURL()
    {
        return $this->context->link->getModuleLink(
            $this->name,
            'redirect',
            [
                'action' => 'cancel',
                'id_cart' => (int) $this->context->cart->id,
            ]
        );
    }

    /**
     * @param $cart
     *
     * @return array
     *
     * @throws \Genesis\Exceptions\ErrorParameter
     */
    private function getCheckoutTransactionTypes($cart)
    {
        $processedList = [];
        $types = $this->getTransactionTypes();

        foreach ($types as $transactionParams) {
            if (is_array($transactionParams)) {
                $processedList[$transactionParams['name']]['name'] = $transactionParams['name'];
                $processedList[$transactionParams['name']]['parameters'] = $transactionParams['parameters'];

                continue;
            }

            $attributes = $this->getCustomRequiredAttributes($transactionParams, $cart);

            if (empty($attributes)) {
                $processedList[$transactionParams] = $transactionParams;
            } else {
                $processedList[$transactionParams]['name'] = $transactionParams;
                $processedList[$transactionParams]['parameters'] = $attributes;
            }
        }

        return $processedList;
    }

    /**
     * @return array
     */
    private function getTransactionTypes()
    {
        $processedList = [];
        $aliasMap = [];

        $selectedTypes = $this->orderCardTransactionTypes(
            json_decode(
                Configuration::get(self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES)
            )
        );

        $pproSuffix = self::PPRO_TRANSACTION_SUFFIX;
        $methods = \Genesis\API\Constants\Payment\Methods::getMethods();

        foreach ($methods as $method) {
            $aliasMap[$method . $pproSuffix] = Genesis\API\Constants\Transaction\Types::PPRO;
        }

        $aliasMap = array_merge($aliasMap, [
            self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE => Genesis\API\Constants\Transaction\Types::GOOGLE_PAY,
            self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_SALE => Genesis\API\Constants\Transaction\Types::GOOGLE_PAY,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_AUTHORIZE => Genesis\API\Constants\Transaction\Types::PAY_PAL,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_SALE => Genesis\API\Constants\Transaction\Types::PAY_PAL,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_EXPRESS => Genesis\API\Constants\Transaction\Types::PAY_PAL,
            self::APPLE_PAY_TRANSACTION_PREFIX . self::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE => Genesis\API\Constants\Transaction\Types::APPLE_PAY,
            self::APPLE_PAY_TRANSACTION_PREFIX . self::APPLE_PAY_PAYMENT_TYPE_SALE => Genesis\API\Constants\Transaction\Types::APPLE_PAY,
        ]);

        foreach ($selectedTypes as $selectedType) {
            if (array_key_exists($selectedType, $aliasMap)) {
                $transactionType = $aliasMap[$selectedType];

                $processedList[$transactionType]['name'] = $transactionType;

                $key = $this->getCustomParameterKey($transactionType);

                $processedList[$transactionType]['parameters'][] = [
                    $key => str_replace(
                        [
                            $pproSuffix,
                            self::GOOGLE_PAY_TRANSACTION_PREFIX,
                            self::PAYPAL_TRANSACTION_PREFIX,
                            self::APPLE_PAY_TRANSACTION_PREFIX,
                        ],
                        '',
                        $selectedType
                    ),
                ];
            } else {
                $processedList[] = $selectedType;
            }
        }

        return $processedList;
    }

    /**
     * @param string $transactionType
     * @param CartCore $cart
     *
     * @return array
     *
     * @throws \Genesis\Exceptions\ErrorParameter
     */
    private function getCustomRequiredAttributes($transactionType, $cart)
    {
        $attributes = [];
        $userIdHash = $this->getCurrentUserIdHash();

        switch ($transactionType) {
            case Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE:
                $attributes = [
                    'card_type' => \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes::VIRTUAL,
                    'redeem_type' => \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes::INSTANT,
                ];
                break;
            case Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN:
            case Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYOUT:
                $attributes = [
                    'customer_account_id' => $userIdHash,
                ];
                break;
            case Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE:
                $userId = $this->getCurrentUserId();
                $trustlyUser = empty($userId) ? $userIdHash : $userId;

                $attributes = [
                    'user_id' => $trustlyUser,
                ];
                break;
            case Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE:
                $attributes = $this->getKlarnaCustomParamItems($cart)->toArray();
                break;
            case Genesis\API\Constants\Transaction\Types::ONLINE_BANKING_PAYIN:
                $selectedBankCodes = json_decode(
                    Configuration::get(self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES)
                );
                if (Genesis\Utils\Common::isValidArray($selectedBankCodes)) {
                    $attributes['bank_codes'] = array_map(
                        function ($value) {
                            return ['bank_code' => $value];
                        },
                        $selectedBankCodes
                    );
                }
                break;
        }

        return $attributes;
    }

    /**
     * @param CartCore $cart
     *
     * @return \Genesis\API\Request\Financial\Alternatives\Klarna\Items
     *
     * @throws \Genesis\Exceptions\ErrorParameter
     */
    private function getKlarnaCustomParamItems($cart)
    {
        /** @var CurrencyCore $currency */
        $currency = new Currency((int) $cart->id_currency);
        $cartSummary = $cart->getSummaryDetails();
        $items = new \Genesis\API\Request\Financial\Alternatives\Klarna\Items($currency->iso_code);

        foreach ($cartSummary['products'] as $product) {
            $type = $product['is_virtual'] ?
                \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_DIGITAL :
                \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_PHYSICAL;

            $klarnaItem = new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                $product['name'],
                $type,
                $product['quantity'],
                $product['price_with_reduction_without_tax']
            );
            $items->addItem($klarnaItem);
        }

        $discount = (float) $cartSummary['total_discounts'];
        if ($discount) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Discount',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_DISCOUNT,
                    1,
                    -$discount
                )
            );
        }

        $tax = (float) $cartSummary['total_tax'];
        if ($tax) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Tax',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_SURCHARGE,
                    1,
                    $tax
                )
            );
        }

        $shippingCost = (float) $cartSummary['total_shipping'];
        if ($shippingCost) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Shipping Cost',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_SHIPPING_FEE,
                    1,
                    $shippingCost
                )
            );
        }

        return $items;
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
     *
     * @return string
     */
    private function getCurrentUserIdHash($length = 20)
    {
        $userId = self::getCurrentUserId();
        $userHash = $userId > 0 ? sha1($userId) : $this->generateTransactionId();

        return Tools::substr($userHash, 0, $length);
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
            $description .= sprintf(
                "%s (%s) x %d\r\n",
                $product['name'],
                $product['category'],
                $product['cart_quantity']
            );
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
        return [
            self::SETTING_EMERCHANTPAY_USERNAME,
            self::SETTING_EMERCHANTPAY_PASSWORD,
            self::SETTING_EMERCHANTPAY_ENVIRONMENT,
            self::SETTING_EMERCHANTPAY_CHECKOUT,
            self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE,
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND,
            self::SETTING_EMERCHANTPAY_ALLOW_VOID,
            self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT,
            self::SETTING_EMERCHANTPAY_WPF_TOKENIZATION,
            self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
            self::SETTING_EMERCHANTPAY_THREEDS_ALLOWED,
            self::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR,
            self::SETTING_EMERCHANTPAY_SCA_EXEMPTION,
            self::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT,
        ];
    }

    /**
     * Get the configuration keys and their respective values
     *
     * @return array Key => Value array
     */
    private function getConfigValues()
    {
        $config_key_value = [];

        foreach ($this->getConfigKeys() as $config_key) {
            if (in_array($config_key, [
                self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
                self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
                ])) {
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

                if (in_array(
                    $key,
                    [
                    self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
                    self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
                    ]
                )) {
                    $value = json_encode($value);
                }

                if (!Validate::isConfigName($key)) {
                    $message = $this->l('Invalid config name: %s');
                    $output = $this->displayError(sprintf($message, $key));
                } elseif (is_string($value) && strlen($value) == 0) {
                    $message = $this->l('Invalid content for: %s');
                    $output = $this->displayError(sprintf($message, $key));
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
    private function getFormCredentialsFields()
    {
        return [
            [
                'type' => 'text',
                'label' => $this->l('Username'),
                'desc' => $this->l(
                    'Enter your Username, required for accessing the Genesis Gateway'
                ),
                'name' => self::SETTING_EMERCHANTPAY_USERNAME,
                'size' => 20,
                'required' => true,
            ],
            [
                'type' => 'text',
                'label' => $this->l('Password'),
                'desc' => $this->l(
                    'Enter your Password, required for accessing the Genesis Gateway'
                ),
                'name' => self::SETTING_EMERCHANTPAY_PASSWORD,
                'size' => 20,
                'required' => true,
            ],
            [
                'type' => 'select',
                'label' => $this->l('Environment'),
                'desc' => $this->l('Select the environment you wish to use for processing your transactions.') .
                    PHP_EOL . $this->l('Note: Its recommended to use the Sandbox environment every-time you alter ') .
                    $this->l('your settings, in order to ensure everything works as intended.'),
                'name' => self::SETTING_EMERCHANTPAY_ENVIRONMENT,
                'options' => [
                    'query' => [
                        [
                            'id' => 'sandbox',
                            'name' => $this->l('Sandbox'),
                        ],
                        [
                            'id' => 'production',
                            'name' => $this->l('Production'),
                        ],
                    ],
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
        ];
    }

    /**
     * Generate form select options from array.
     * Array key will be used as id attribute, value as name
     *
     * @param $options
     * @param string $id
     * @param string $name
     *
     * @return mixed
     */
    private function generateOptionsFromArray($options, $id = 'id', $name = 'name')
    {
        foreach ($options as $key => &$value) {
            $value = [
                $id => $key,
                $name => $value,
            ];
        }

        return $options;
    }

    /**
     * Get form transactions fields
     *
     * @return array
     */
    private function getFormTransactionFields()
    {
        return [
            [
                'type' => 'switch',
                'label' => 'Checkout (Remote) Payment Method',
                'desc' => $this->l('Enable/Disable the Checkout payment method - ') .
                    $this->l('receive credit-card payments, without the need of PCI-DSS certificate or HTTPS.') .
                    PHP_EOL .
                    $this->l('Note: Upon checkout, the customer will be redirected to a secure payment form, ') .
                    $this->l('located on our servers and we will notify you, once the payment reached a final status'),
                'name' => self::SETTING_EMERCHANTPAY_CHECKOUT,
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => '1',
                    ],
                    [
                        'id' => 'active_off',
                        'value' => '0',
                    ],
                ],
            ],
            [
                'type' => 'select',
                'label' => $this->l('Checkout Transaction Types'),
                'desc' => $this->l(
                    'Select the transaction types you want to use during Checkout session.'
                ),
                'id' => self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
                'name' => self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES . '[]',
                'multiple' => true,
                'options' => [
                    'query' => $this->generateOptionsFromArray(self::getSupportedWpfTransactionTypes()),
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
            [
                'type' => 'select',
                'label' => $this->l('Checkout Bank codes for Online banking'),
                'desc' => $this->l(
                    'Select Bank code(s) to use with Online banking transaction type.'
                ),
                'id' => self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
                'name' => self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES . '[]',
                'multiple' => true,
                'options' => [
                    'query' => $this->generateOptionsFromArray(self::getAvailableBankCodes()),
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
            [
                'type' => 'switch',
                'label' => 'WPF Tokenization',
                'desc' => $this->l('Enable/Disable tokenization for Web Payment Form. Guest checkout has to be ') .
                    $this->l('disabled when tokenization is enabled'),
                'name' => self::SETTING_EMERCHANTPAY_WPF_TOKENIZATION,
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => '1',
                    ],
                    [
                        'id' => 'active_off',
                        'value' => '0',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get form threeds fields
     *
     * @return array
     */
    private function getFormThreedsFields()
    {
        return [
            [
                'type' => 'switch',
                'label' => 'Enable 3DSv2',
                'desc' => $this->l('Enable 3DSv2 optional parameters'),
                'name' => self::SETTING_EMERCHANTPAY_THREEDS_ALLOWED,
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => '1',
                    ],
                    [
                        'id' => 'active_off',
                        'value' => '0',
                    ],
                ],
            ],
            [
                'type' => 'select',
                'label' => $this->l('3DSv2 Challenge'),
                'desc' => $this->l(
                    'The value has weight and might impact the decision whether a challenge will be required for the transaction or not.'
                ),
                'id' => self::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR,
                'name' => self::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR,
                'multiple' => false,
                'options' => [
                    'query' => $this->generateOptionsFromArray(EmerchantpayThreeds::getChallengeIndicators()),
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
        ];
    }

    /**
     * Get SCA options form fields
     *
     * @return array
     */
    private function getFormScaFields()
    {
        return [
            [
                'type' => 'select',
                'label' => $this->l('SCA Exemption'),
                'desc' => $this->l('Exemption for the Strong Customer Authentication.'),
                'id' => self::SETTING_EMERCHANTPAY_SCA_EXEMPTION,
                'name' => self::SETTING_EMERCHANTPAY_SCA_EXEMPTION,
                'multiple' => false,
                'options' => [
                    'query' => $this->generateOptionsFromArray($this->getScaExemptionOptions()),
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->l('Exemption Amount'),
                'desc' => $this->l(
                    'Exemption Amount determinate if the SCA Exemption should be included in the request to the Gateway.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT,
                'required' => true,
            ],
        ];
    }

    public function getSupportedWpfTransactionTypes()
    {
        $data = [];

        $transactionTypes = Genesis\API\Constants\Transaction\Types::getWPFTransactionTypes();
        $excludedTypes = [
            Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE,
            Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE_3D,
            Genesis\API\Constants\Transaction\Types::SDD_INIT_RECURRING_SALE,
            Genesis\API\Constants\Transaction\Types::PPRO,
            Genesis\API\Constants\Transaction\Types::GOOGLE_PAY,
            Genesis\API\Constants\Transaction\Types::PAY_PAL,
            Genesis\API\Constants\Transaction\Types::APPLE_PAY,
        ];

        $transactionTypes = array_diff($transactionTypes, $excludedTypes);

        // Add PPRO specific Types
        $pproTypes = array_map(
            function ($type) {
                return $type . self::PPRO_TRANSACTION_SUFFIX;
            },
            \Genesis\API\Constants\Payment\Methods::getMethods()
        );

        // Add Google Pay Transaction Methods
        $googlePayMethods = array_map(
            function ($type) {
                return self::GOOGLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                self::GOOGLE_PAY_PAYMENT_TYPE_SALE,
            ]
        );

        // Add PayPal Transaction Methods
        $payPalMethods = array_map(
            function ($type) {
                return self::PAYPAL_TRANSACTION_PREFIX . $type;
            },
            [
                self::PAYPAL_PAYMENT_TYPE_AUTHORIZE,
                self::PAYPAL_PAYMENT_TYPE_SALE,
                self::PAYPAL_PAYMENT_TYPE_EXPRESS,
            ]
        );

        // Add Apple Pay Transaction Methods
        $applePayMethods = array_map(
            function ($type) {
                return self::APPLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                self::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                self::APPLE_PAY_PAYMENT_TYPE_SALE,
            ]
        );

        $transactionTypes = array_merge(
            $transactionTypes,
            $pproTypes,
            $googlePayMethods,
            $payPalMethods,
            $applePayMethods
        );
        asort($transactionTypes);

        foreach ($transactionTypes as $type) {
            $name = Genesis\API\Constants\Transaction\Names::getName($type);
            if (!Genesis\API\Constants\Transaction\Types::isValidTransactionType($type)) {
                $name = Tools::strtoupper($type);
            }

            $data[$type] = $this->l($name);
        }

        return $data;
    }

    /**
     * List of available Bank codes for Online banking
     *
     * @return array
     */
    public static function getAvailableBankCodes()
    {
        return [
            Genesis\API\Constants\Banks::CPI => 'Interac Combined Pay-in',
            Genesis\API\Constants\Banks::BCT => 'Bancontact',
        ];
    }

    /**
     * Generate the Module Settings HTML via HelperForm()
     *
     * @return mixed HTML Content
     */
    private function _displayForm()
    {
        $form_structure = [
            'form' => [
                'legend' => [
                    'title' => $this->l('emerchantpay Configuration'),
                    'icon' => 'icon-cog',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => 'Partial Capture',
                        'desc' => $this->l(
                            'Use this option to allow / deny Partial Capture Transactions'
                        ),
                        'name' => self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => '1',
                            ],
                            [
                                'id' => 'active_off',
                                'value' => '0',
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => 'Partial Refund',
                        'desc' => $this->l(
                            'Use this option to allow / deny Partial Refund Transactions'
                        ),
                        'name' => self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => '1',
                            ],
                            [
                                'id' => 'active_off',
                                'value' => '0',
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => 'Cancel Transaction',
                        'desc' => $this->l(
                            'Use this option to allow / deny Cancel Transactions'
                        ),
                        'name' => self::SETTING_EMERCHANTPAY_ALLOW_VOID,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => '1',
                            ],
                            [
                                'id' => 'active_off',
                                'value' => '0',
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        /* Add form fields */
        $form_structure['form']['input'] = array_merge(
            $this->getFormCredentialsFields(),
            $this->getFormTransactionFields(),
            $this->getFormThreedsFields(),
            $this->getFormScaFields(),
            $form_structure['form']['input']
        );

        /*
         * Option for registering jQuery to Checkout Page
         *
         * Note: 1.7.x does not register jQuery on the Checkout Page, so we are adding this option
         * in order to be disabled if jQuery has been added from other module
         */
        if ($this->isPrestaVersion17()) {
            $form_structure['form']['input'][] = [
                'type' => 'switch',
                'label' => 'Include jQuery Plugin to Checkout Page',
                'desc' => $this->l(
                    'Use this option to allow / deny jQuery Plugin Registration. This option should be enabled unless jQuery has already been registered.'
                ),
                'name' => self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT,
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => '1',
                    ],
                    [
                        'id' => 'active_off',
                        'value' => '0',
                    ],
                ],
            ];
        }

        $helper = new HelperForm();
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = false;
        // Module, token and currentIndex
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink(
            'AdminModules',
            false
        ) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;

        // Language
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->submit_action = 'submit' . $this->name;

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigValues(),
            'id_language' => $this->context->language->id,
            'languages' => $this->context->controller->getLanguages(),
        ];

        return $helper->generateForm(
            [$form_structure]
        );
    }

    /**
     * Initialize the module and check for compatibility
     *
     * @return void
     */
    private function init()
    {
        /* Bootstrap Genesis */
        include_once dirname(__FILE__) . '/vendor/autoload.php';

        /* emerchantpay Install Helper */
        include_once dirname(__FILE__) . '/classes/EmerchantpayInstall.php';

        /* emerchantpay Consumer Model */
        include_once dirname(__FILE__) . '/classes/EmerchantpayConsumer.php';

        /* emerchantpay Transaction Model */
        include_once dirname(__FILE__) . '/classes/EmerchantpayTransaction.php';

        /* emerchantpay Transaction Processor */
        include_once dirname(__FILE__) . '/classes/EmerchantpayTransactionProcess.php';

        /* emerchantpay Threeds helper */
        include_once dirname(__FILE__) . '/classes/EmerchantpayThreeds.php';

        /* Check if Genesis Library is initialized */
        if (!class_exists('\Genesis\Genesis')) {
            $this->warning = 'Sorry, there was a problem initializing Genesis client, please verify your installation!';
        }

        /* Catch Block added -> Prestashop 1.6.0 calls Model Constructor even when the Module is not yet installed */
        try {
            /* Check and update database if necessary */
            EmerchantpayInstall::doProcessSchemaUpdate();
        } catch (\Exception $e) {
            /* just ignore and log exception - Init Method is called on Upload Module (it should be called after Module is installed) */
            $this->logError($e);
        }

        /* Verify system requirements */
        try {
            \Genesis\Utils\Requirements::verify();
        } catch (\Exception $e) {
            $this->warning = $this->l('Your server does not meet the minimum system requirements! Contact your hosting provider for assistance!');
        }

        /* Check if the module is configured */
        if (Configuration::get('EMERCHANTPAY_USERNAME') && Configuration::get('EMERCHANTPAY_PASSWORD')) {
            $this->applyGenesisConfig();
        } else {
            $this->warning = $this->l('You need to set your credentials (username, password), in order to use Genesis Payment Gateway!');
        }

        // Load Available WPF Languages
        $this->languages = \Genesis\API\Constants\i18n::getAll();
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

        \Genesis\Config::setEnvironment(
            Configuration::get('EMERCHANTPAY_ENVIRONMENT')
        );
    }

    /**
     * Determines if the Store is running over secured connection
     *
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
		FROM `' . _DB_PREFIX_ . 'state`
		WHERE `id_state` = ' . (int) $state_id);
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
		FROM `' . _DB_PREFIX_ . 'country`
		WHERE `id_country` = ' . (int) $country_id);
    }

    /**
     * Migrates old Toggle Button values (true => 1; false => 0)
     *
     * @return void
     */
    protected function doMigrateSettings()
    {
        $toggleSettingKeys = [
            self::SETTING_EMERCHANTPAY_CHECKOUT,
        ];

        foreach ($toggleSettingKeys as $toggleSettingKey) {
            $settingValue = Tools::strtolower(Configuration::get($toggleSettingKey));
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
        $defaultConfigItems = [
            self::SETTING_EMERCHANTPAY_CHECKOUT => '0',
            self::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES => [
                Genesis\API\Constants\Transaction\Types::AUTHORIZE,
                Genesis\API\Constants\Transaction\Types::SALE,
            ],
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE => '1',
            self::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND => '1',
            self::SETTING_EMERCHANTPAY_ALLOW_VOID => '1',
            self::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT => '1',
            self::SETTING_EMERCHANTPAY_WPF_TOKENIZATION => '0',
            self::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES => [],
            self::SETTING_EMERCHANTPAY_THREEDS_ALLOWED => '1',
            self::SETTING_EMERCHANTPAY_SCA_EXEMPTION => 'low_risk',
            self::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT => '100',
        ];

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
     *
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
    protected function registerCore17Javascript($relativePath, $params = ['position' => 'head'])
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
     * Retrieves if the current PrestaShop Version is 1.7.x
     *
     * @return bool
     */
    protected function isPrestaVersion17()
    {
        return version_compare(_PS_VERSION_, '1.7', '>=');
    }

    /**
     * Retrieves if the current PrestaShop Version is 1.7.7.x
     *
     * @return bool
     */
    protected function isPrestaVersion177()
    {
        return version_compare(_PS_VERSION_, '1.7.7.0', '>=');
    }

    /**
     * Retrieves the current jQuery path
     *
     * @return string|null
     */
    protected function getJQueryUri()
    {
        if (defined('_PS_JQUERY_VERSION_')) {
            return _PS_JS_DIR_ . 'jquery/jquery-' . _PS_JQUERY_VERSION_ . '.min.js';
        }

        return null;
    }

    /**
     * @param $transactionType
     *
     * @return string
     */
    private function getCustomParameterKey($transactionType)
    {
        switch ($transactionType) {
            case Genesis\API\Constants\Transaction\Types::PPRO:
                $result = 'payment_method';
                break;
            case Genesis\API\Constants\Transaction\Types::PAY_PAL:
                $result = 'payment_type';
                break;
            case Genesis\API\Constants\Transaction\Types::GOOGLE_PAY:
            case Genesis\API\Constants\Transaction\Types::APPLE_PAY:
                $result = 'payment_subtype';
                break;
            default:
                $result = 'unknown';
        }

        return $result;
    }

    /**
     * Helper function to add js and css where is needed
     *
     * @return void
     */
    private function addAssets()
    {
        if (Tools::getValue('controller') == self::PS_CONTROLLER_ADMIN_ORDERS) {
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/font-awesome.min.css', 'all');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/treegrid.min.css', 'all');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/bootstrap/bootstrapValidator.min.css');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/treegrid/cookie.min.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/treegrid/treegrid.min.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/bootstrap/bootstrapValidator.min.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/jQueryExtensions/jquery.number.min.js');
        }
    }

    /**
     * Get the HTML method form string
     *
     * @param array $paymentMethod
     *
     * @return string
     */
    private function generateMethodForm($paymentMethod)
    {
        $submitFormAction = $this->context->link->getModuleLink(
            $this->name,
            'validation',
            [],
            true
        );

        $this->context->smarty->assign([
            'method_name' => $this->name,
            'method_input_name' => 'submit' . $this->name . Tools::ucfirst($paymentMethod['name']),
            'submit_form_action' => $submitFormAction,
            'on_submit_callback' => $paymentMethod['clientSideEvents']['onFormSubmit'],
            'all_conditions_approved' => true,
        ]);

        return $this->context->smarty->fetch("module:{$this->name}/views/templates/front/methodform.tpl");
    }

    /**
     * Returns SCA Exemption options
     *
     * @return array
     */
    private function getScaExemptionOptions()
    {
        return [
            Genesis\API\Constants\Transaction\Parameters\ScaExemptions::EXEMPTION_LOW_RISK => 'Low risk',
            Genesis\API\Constants\Transaction\Parameters\ScaExemptions::EXEMPTION_LOW_VALUE => 'Low value',
        ];
    }

    /**
     * Order transaction types with Card Transaction types in front
     *
     * @param array $selectedTypes Selected transaction types
     *
     * @return array
     */
    protected function orderCardTransactionTypes($selectedTypes)
    {
        $creditCardTypes = \Genesis\API\Constants\Transaction\Types::getCardTransactionTypes();

        asort($selectedTypes);

        $sortedArray = array_intersect($creditCardTypes, $selectedTypes);

        return array_merge(
            $sortedArray,
            array_diff($selectedTypes, $sortedArray)
        );
    }
}
