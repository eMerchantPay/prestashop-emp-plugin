<?php
/**
 * Copyright (C) 2015-2024 emerchantpay Ltd.
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
 * @copyright   2015-2024 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */
require_once __DIR__ . '/vendor/autoload.php';

use Emerchantpay\Genesis\EmerchantpayConsumer;
use Emerchantpay\Genesis\EmerchantpayInstall;
use Emerchantpay\Genesis\EmerchantpayThreeds;
use Emerchantpay\Genesis\EmerchantpayTransaction;
use Emerchantpay\Genesis\EmerchantpayTransactionProcess;
use Emerchantpay\Genesis\Exceptions\ErrorState;
use Emerchantpay\Genesis\Helpers\Constants\ConfigurationKeys;
use Emerchantpay\Genesis\Settings\Checkout\CheckoutSettings;
use Genesis\Api\Constants\Endpoints;
use Genesis\Api\Constants\i18n;
use Genesis\Api\Constants\Payment\Methods;
use Genesis\Api\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\RegistrationIndicators;
use Genesis\Api\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\DeliveryTimeframes;
use Genesis\Api\Constants\Transaction\Parameters\Threeds\V2\Purchase\Categories;
use Genesis\Api\Constants\Transaction\States;
use Genesis\Api\Constants\Transaction\Types;
use Genesis\Api\Request\Financial\Alternatives\Klarna\Item;
use Genesis\Api\Request\Financial\Alternatives\Klarna\Items;
use Genesis\Config;
use Genesis\Exceptions\ErrorParameter;
use Genesis\Exceptions\InvalidArgument;
use Genesis\Utils\Common;
use Genesis\Utils\Requirements;
use PrestaShopLogger as Logger;

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
    const PS_CONTROLLER_ADMIN_ORDERS = 'AdminOrders';

    /**
     * List supported languages
     *
     * @var array
     */
    private $languages = [];

    /**
     * Custom prefix
     */
    const PLATFORM_TRANSACTION_PREFIX = 'ps-';

    /**
     * @var stdClass
     */
    private $transaction_data;

    /**
     * Constructor
     */
    public function __construct()
    {
        /* Initial Module Setup */
        $this->name = 'emerchantpay';
        $this->tab = 'payments_gateways';
        $this->displayName = 'emerchantpay Payment Gateway';
        $this->controllers = ['frame', 'notification', 'redirect', 'validation'];
        $this->version = '2.1.5';
        $this->author = 'emerchantpay Ltd.';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;
        $this->module_key = '944a03157ee547ee63f641035f559380';

        $this->description = $this->l('Accept payments through emerchantpay Payment Gateway - Genesis');

        /* Storage for transaction data to avoid init/call every-time */
        $this->transaction_data = null;

        /* Store warnings during init */
        $this->warning = '';

        /* Initialize Genesis Client */
        $this->init();

        /* The parent construct is required for translations */
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
     * Install and create/register the required hooks
     *
     * @return bool
     *
     * @throws PrestaShopException
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

        $checkoutSettings = $this->getCheckoutSettings();

        return $pre_install && $install->isSuccessful() && $checkoutSettings->setDefaultSettingsToDB();
    }

    /**
     * Uninstall logic
     *
     * Remove all the set Configuration keys and un-register all hooks
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        $pre_uninstall = parent::uninstall();

        $uninstall = new EmerchantpayInstall();

        // Clear the transaction database
        $uninstall->dropSchema();

        // Remove the current configuration
        $uninstall->dropKeys($this->getCheckoutSettings());

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
        return empty($this->warning);
    }

    /**
     * Is Checkout payment method available?
     *
     * @return bool
     */
    public function isCheckoutPaymentMethodAvailable()
    {
        return $this->getBoolConfigurationValue(ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT);
    }

    /**
     * Is WPF Tokenization enabled?
     *
     * @return bool
     */
    public function isWpfTokenizationEnabled()
    {
        return $this->getBoolConfigurationValue(ConfigurationKeys::SETTING_EMERCHANTPAY_WPF_TOKENIZATION);
    }

    /**
     * Is 3DS v2 enabled?
     *
     * @return bool
     */
    public function isThreedsAllowed()
    {
        return $this->getBoolConfigurationValue(ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_ALLOWED);
    }

    /**
     * Check if iframe processing enabled
     *
     * @return bool
     */
    public function isIframeEnabled()
    {
        return $this->getBoolConfigurationValue(ConfigurationKeys::SETTING_EMERCHANTPAY_IFRAME_ALLOWED);
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
                            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE
                        ),
                        'allow_partial_refund' => $this->getBoolConfigurationValue(
                            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND
                        ),
                        'allow_void' => $this->getBoolConfigurationValue(
                            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_VOID
                        ),
                    ],
                    'text' => [
                        'denied_partial_capture' => $this->l(
                            'Partial Capture is currently disabled! You can enable this option in the Module Settings.'
                        ),
                        'denied_partial_refund' => $this->l(
                            'Partial Refund is currently disabled! You can enable this option in the Module Settings.'
                        ),
                        'denied_void' => $this->l('Cancel Transaction are currently disabled! You can enable this option in the Module Settings.'), // phpcs:ignore Generic.Files.LineLength.TooLong
                    ],
                    'error' => $this->getSessVar('error_transaction'),
                    'tree' => EmerchantpayTransaction::getTransactionTree(
                        (int) $params['id_order']
                    ),
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
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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

        if ($this->isPrestaVersion17() && $this->getBoolConfigurationValue(
            ConfigurationKeys::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT
        )) {
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
     * @return array|null
     *
     * @throws SmartyException
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return null;
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
                'iframe_enabled' => $this->isIframeEnabled(),
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
                    )
                    ->setModuleName("{$this->name}_{$paymentMethod['name']}");

                $paymentOptions[] = $paymentMethodOption;
            }
        }

        return $paymentOptions;
    }

    /**
     * Show an information about the customers order
     *
     * @param $params
     *
     * @return bool
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
     *
     * @throws ErrorParameter
     */
    public function populateTransactionData()
    {
        /** @var Cart $cart */
        $cart = new Cart((int) $this->context->cart->id);

        /** @var Address $shipping */
        $shipping = new Address((int) $cart->id_address_delivery);
        /** @var Address $invoice */
        $invoice = new Address((int) $cart->id_address_invoice);
        /** @var Customer $customer */
        $customer = new Customer((int) $cart->id_customer);
        /** @var Currency $currency */
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

        // Set web payment form ID
        $data->web_payment_form_id = Configuration::get(ConfigurationKeys::SETTING_EMERCHANTPAY_WEB_PAYMENT_FORM_ID);

        // Threeds
        $data->is_guest = Cart::isGuestCartByCartId($cart->id);

        // Order parameters
        $data->is_threeds_allowed = $this->isThreedsAllowed();
        $data->threeds_challenge_indicator = Configuration::get(
            ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR
        );
        $data->threeds_purchase_category = $cart->isVirtualCart() ?
            Categories::SERVICE :
            Categories::GOODS;
        $data->threeds_delivery_timeframe = $cart->isVirtualCart() ?
            DeliveryTimeframes::ELECTRONICS :
            DeliveryTimeframes::ANOTHER_DAY;
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
            RegistrationIndicators::GUEST_CHECKOUT;

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
        $data->sca_exemption_value = Configuration::get(ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION);
        $data->sca_exemption_amount =
            Configuration::get(ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT);

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
     *
     * @throws InvalidArgument
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
                    Config::getUsername(),
                    $data->customer_email,
                    $response->consumer_id
                );
            }

            return $response->redirect_url;
        } catch (ErrorState $error) {
            $this->logError($error);

            $this->setSessVar('error_checkout', $error->getMessage());
        } catch (Exception $e) {
            $this->logError($e);

            $this->setSessVar(
                'error_checkout',
                'Please, make sure you\'ve entered correct credentials for accessing the gateway and all of the ' .
                ' required data, e.g. Email, Phone, Billing/Shipping Address.'
            );
        }

        return null;
    }

    /**
     * Perform a Capture on a Genesis Transaction
     *
     * @return void
     *
     * @throws InvalidArgument
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
                Config::setToken($transaction->terminal);
            }

            if ($transaction->type === Types::KLARNA_AUTHORIZE) {
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
        } catch (ErrorState $error) {
            $this->logError($error);

            $this->setSessVar('error_transaction', $error->getMessage());
        } catch (Exception $e) {
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
     * @return void
     *
     * @throws InvalidArgument
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
                Config::setToken($transaction->terminal);
            }

            if ($transaction->type === Types::KLARNA_CAPTURE) {
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
        } catch (ErrorState $error) {
            $this->logError($error);

            $this->setSessVar('error_transaction', $error->getMessage());
        } catch (Exception $e) {
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
     * @return void
     *
     * @throws InvalidArgument
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
                Config::setToken($transaction->terminal);
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
        } catch (ErrorState $error) {
            $this->logError($error);

            $this->setSessVar('error_transaction', $error->getMessage());
        } catch (Exception $e) {
            $this->logError($e);

            $this->setSessVar(
                'error_transaction',
                $this->l('The transaction was unsuccessful, please check your Logs for more information')
            );
        }
    }

    /**
     * Get Prestashop status based on Genesis status
     *
     * @param string $status
     *
     * @return false|string
     */
    public function getPrestaStatus($status)
    {
        switch ($status) {
            case States::APPROVED:
                return Configuration::get('PS_OS_WS_PAYMENT');
            case States::REFUNDED:
                return Configuration::get('PS_OS_REFUND');
            case States::PENDING:
            case States::PENDING_ASYNC:
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
     * @return false|string
     */
    public function getPrestaBackendStatus($transaction_type)
    {
        switch ($transaction_type) {
            case Types::CAPTURE:
                return Configuration::get('PS_OS_WS_PAYMENT');
            case Types::REFUND:
                return Configuration::get('PS_OS_REFUND');
            case Types::VOID:
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
     *
     * @throws Exception
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
     *
     * @throws Exception
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
     * @return string Http URL
     */
    private function getNotificationURL()
    {
        return $this->context->link->getModuleLink($this->name, 'notification', []);
    }

    /**
     * Get Success URL for Async Transactions
     *
     * @return string Http URL
     */
    private function getAsyncSuccessURL()
    {
        $url = $this->getPageLink('order-confirmation.php');
        $controllerUrl = $this->getIframeControllerUrl($url);

        return $this->isIframeEnabled() ? $controllerUrl : $url;
    }

    /**
     * Get Failure URL for Async Transactions
     *
     * @return string Http URL
     */
    private function getAsyncFailureURL()
    {
        $url = $this->context->link->getModuleLink(
            $this->name,
            'redirect',
            [
                'action' => 'failure',
                'id_cart' => (int) $this->context->cart->id,
            ]
        );
        $controllerUrl = $this->getIframeControllerUrl($url);

        return $this->isIframeEnabled() ? $controllerUrl : $url;
    }

    /**
     * Get Cancel URL for Async Transactions
     *
     * @return string Http URL
     */
    private function getAsyncCancelURL()
    {
        $url = $this->context->link->getModuleLink(
            $this->name,
            'redirect',
            [
                'action' => 'cancel',
                'id_cart' => (int) $this->context->cart->id,
            ]
        );
        $controllerUrl = $this->getIframeControllerUrl($url);

        return $this->isIframeEnabled() ? $controllerUrl : $url;
    }

    /**
     * Get link to the iframe handling controller
     *
     * @param $url string
     *
     * @return string
     */
    public function getIframeControllerUrl($url)
    {
        return $this->context->link->getModuleLink(
            $this->name,
            'frame',
            [
                'url' => rawurlencode($url),
            ]
        );
    }

    /**
     * @param $cart
     *
     * @return array
     *
     * @throws ErrorParameter
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
                Configuration::get(ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES)
            )
        );

        $aliasMap = [
            CheckoutSettings::GOOGLE_PAY_TRANSACTION_PREFIX .
            CheckoutSettings::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE => Types::GOOGLE_PAY,
            CheckoutSettings::GOOGLE_PAY_TRANSACTION_PREFIX .
            CheckoutSettings::GOOGLE_PAY_PAYMENT_TYPE_SALE => Types::GOOGLE_PAY,
            CheckoutSettings::PAYPAL_TRANSACTION_PREFIX .
            CheckoutSettings::PAYPAL_PAYMENT_TYPE_AUTHORIZE => Types::PAY_PAL,
            CheckoutSettings::PAYPAL_TRANSACTION_PREFIX .
            CheckoutSettings::PAYPAL_PAYMENT_TYPE_SALE => Types::PAY_PAL,
            CheckoutSettings::PAYPAL_TRANSACTION_PREFIX .
            CheckoutSettings::PAYPAL_PAYMENT_TYPE_EXPRESS => Types::PAY_PAL,
            CheckoutSettings::APPLE_PAY_TRANSACTION_PREFIX .
            CheckoutSettings::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE => Types::APPLE_PAY,
            CheckoutSettings::APPLE_PAY_TRANSACTION_PREFIX .
            CheckoutSettings::APPLE_PAY_PAYMENT_TYPE_SALE => Types::APPLE_PAY,
        ];

        foreach ($selectedTypes as $selectedType) {
            if (array_key_exists($selectedType, $aliasMap)) {
                $transactionType = $aliasMap[$selectedType];

                $processedList[$transactionType]['name'] = $transactionType;

                $key = $this->getCustomParameterKey($transactionType);

                $processedList[$transactionType]['parameters'][] = [
                    $key => str_replace(
                        [
                            CheckoutSettings::GOOGLE_PAY_TRANSACTION_PREFIX,
                            CheckoutSettings::PAYPAL_TRANSACTION_PREFIX,
                            CheckoutSettings::APPLE_PAY_TRANSACTION_PREFIX,
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
     * @param Cart $cart
     *
     * @return array
     *
     * @throws ErrorParameter
     */
    private function getCustomRequiredAttributes($transactionType, $cart)
    {
        $attributes = [];
        $userIdHash = $this->getCurrentUserIdHash();

        switch ($transactionType) {
            case Types::IDEBIT_PAYIN:
            case Types::INSTA_DEBIT_PAYOUT:
                $attributes = [
                    'customer_account_id' => $userIdHash,
                ];
                break;
            case Types::TRUSTLY_SALE:
                $userId = $this->getCurrentUserId();
                $trustlyUser = empty($userId) ? $userIdHash : $userId;

                $attributes = [
                    'user_id' => $trustlyUser,
                ];
                break;
            case Types::KLARNA_AUTHORIZE:
                $attributes = $this->getKlarnaCustomParamItems($cart)->toArray();
                break;
            case Types::ONLINE_BANKING_PAYIN:
                $selectedBankCodes = json_decode(
                    Configuration::get(ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES)
                );
                if (Common::isValidArray($selectedBankCodes)) {
                    $attributes['bank_codes'] = array_map(
                        function ($value) {
                            return ['bank_code' => $value];
                        },
                        $selectedBankCodes
                    );
                }
                break;
            case Types::PAYSAFECARD:
                $userId = $this->getCurrentUserId();
                $customerId = empty($userId) ? $userIdHash : $userId;

                $attributes = [
                    'customer_id' => $customerId,
                ];
                break;
        }

        return $attributes;
    }

    /**
     * @param Cart $cart
     *
     * @return Items
     *
     * @throws ErrorParameter
     */
    private function getKlarnaCustomParamItems($cart)
    {
        /** @var Currency $currency */
        $currency = new Currency((int) $cart->id_currency);
        $cartSummary = $cart->getSummaryDetails();
        $items = new Items($currency->iso_code);

        foreach ($cartSummary['products'] as $product) {
            $type = $product['is_virtual'] ?
                Item::ITEM_TYPE_DIGITAL :
                Item::ITEM_TYPE_PHYSICAL;

            $klarnaItem = new Item(
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
                new Item(
                    'Discount',
                    Item::ITEM_TYPE_DISCOUNT,
                    1,
                    -$discount
                )
            );
        }

        $tax = (float) $cartSummary['total_tax'];
        if ($tax) {
            $items->addItem(
                new Item(
                    'Tax',
                    Item::ITEM_TYPE_SURCHARGE,
                    1,
                    $tax
                )
            );
        }

        $shippingCost = (float) $cartSummary['total_shipping'];
        if ($shippingCost) {
            $items->addItem(
                new Item(
                    'Shipping Cost',
                    Item::ITEM_TYPE_SHIPPING_FEE,
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
     * Get the Module Settings HTML code
     *
     * @return string HTML code
     */
    public function getContent()
    {
        $checkoutSettings = $this->getCheckoutSettings();

        return $checkoutSettings->getContent();
    }

    /**
     * Log an exception in PHP's error log
     *
     * @param Exception $exception
     */
    private function logError($exception)
    {
        error_log($exception->getMessage());

        if (class_exists('PrestaShopLogger')) {
            Logger::addLog(
                $exception->getMessage(),
                4,
                $exception->getCode(),
                $this->name,
                $this->id,
                true
            );
        }
    }

    /**
     * Initialize the module and check for compatibility
     *
     * @return void
     *
     * @throws InvalidArgument
     */
    private function init()
    {
        /* Check if Genesis Library is initialized */
        if (!class_exists('\Genesis\Genesis')) {
            $this->warning = 'Sorry, there was a problem initializing Genesis client, please verify your installation!';
        }

        /* Catch Block added -> Prestashop 1.6.0 calls Model Constructor even when the Module is not yet installed */
        try {
            /* Check and update database if necessary */
            EmerchantpayInstall::doProcessSchemaUpdate();
        } catch (Exception $e) {
            // just ignore and log exception - Init Method is called on Upload Module
            // (it should be called after Module is installed)
            $this->logError($e);
        }

        /* Verify system requirements */
        try {
            Requirements::verify();
        } catch (Exception $e) {
            $this->warning = $this->l('Your server does not meet the minimum system requirements! Contact your hosting provider for assistance!'); // phpcs:ignore Generic.Files.LineLength.TooLong
        }

        /* Check if the module is configured */
        if (Configuration::get('EMERCHANTPAY_USERNAME') && Configuration::get('EMERCHANTPAY_PASSWORD')) {
            $this->applyGenesisConfig();
        } else {
            $this->warning = $this->l('You need to set your credentials (username, password), in order to use Genesis Payment Gateway!'); // phpcs:ignore Generic.Files.LineLength.TooLong
        }

        // Load Available WPF Languages
        $this->languages = i18n::getAll();
    }

    /**
     * Set Genesis Configuration based on the module settings
     *
     * @return void
     *
     * @throws InvalidArgument
     */
    public function applyGenesisConfig()
    {
        Config::setEndpoint(
            Endpoints::EMERCHANTPAY
        );

        Config::setUsername(
            Configuration::get('EMERCHANTPAY_USERNAME')
        );
        Config::setPassword(
            Configuration::get('EMERCHANTPAY_PASSWORD')
        );

        Config::setEnvironment(
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
            ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT,
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
    public function isPrestaVersion17()
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
            case Types::PAY_PAL:
                $result = 'payment_type';
                break;
            case Types::GOOGLE_PAY:
            case Types::APPLE_PAY:
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
     * Order transaction types with Card Transaction types in front
     *
     * @param array $selectedTypes Selected transaction types
     *
     * @return array
     */
    protected function orderCardTransactionTypes($selectedTypes)
    {
        $creditCardTypes = Types::getCardTransactionTypes();

        asort($selectedTypes);

        $sortedArray = array_intersect($creditCardTypes, $selectedTypes);

        return array_merge(
            $sortedArray,
            array_diff($selectedTypes, $sortedArray)
        );
    }

    /**
     * @return CheckoutSettings
     */
    private function getCheckoutSettings()
    {
        return new CheckoutSettings($this, $this->identifier, $this->context);
    }
}
