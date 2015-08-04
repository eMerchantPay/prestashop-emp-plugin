<?php
/*
 * Copyright (C) 2015 eMerchantPay Ltd.
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
 * @copyright   2015 eMerchantPay Ltd.
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
    public function __construct()
    {
        /* Initial Module Setup */
        $this->name         = 'emerchantpay';
        $this->tab          = 'payments_gateways';
        $this->displayName  = 'eMerchantPay Payment Gateway';
        $this->controllers  = array('checkout', 'notification', 'redirect', 'validation');
        $this->version      = '1.2.3';
        $this->author       = 'eMerchantPay Ltd.';

        /* The parent construct is required for translations */
        $this->page         = basename(__FILE__, '.php');
        $this->description  = $this->l('Accept payments through eMerchantPay\'s Payment Gateway - Genesis');

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

        return $pre_install && $install->isSuccessful();
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
        return (Configuration::get('EMERCHANTPAY_DIRECT') == 'true' ? true : false);
    }

    /**
     * Is Checkout payment method available?
     *
     * @return bool
     */
    public function isCheckoutPaymentMethodAvailable()
    {
        return (Configuration::get('EMERCHANTPAY_CHECKOUT') == 'true' ? true : false);
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
            return (stripos(Configuration::get('EMERCHANTPAY_DIRECT_TRX_TYPE'), '3d') !== false) ? true : false;
        }

        return false;
    }

    /**
     * Hook AdminOrder to display the saved transactions,
     * related to the order
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
        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/treegrid/cookie.min.js'
        );
        $this->context->controller->addJS(
            $this->getPathUri() . 'assets/js/treegrid/treegrid.min.js'
        );

        $currency = new Currency((int)$order->id_currency);

        $this->context->smarty->append(
            'emerchantpay',
            array(
                'transactions'  => array(
                    'order'             => array(
                        'id'          => $order->id,
                        'amount'      => $order->getTotalPaid(),
                        'currency'    => $currency->iso_code,
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
                )
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
            case Configuration::get('PS_OS_PAYMENT'):
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
        $data->id               = md5(mt_rand() . microtime(true) . uniqid());
        $data->transaction_type = Configuration::get('EMERCHANTPAY_DIRECT_TRX_TYPE');
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

            list($month, $year) = explode(' / ', $data->expiration);

            $data->expiration_month = $month;
            $data->expiration_year  = substr(date('Y'), 0, 2) . substr($year, -2);
        }

        // Billing
        if ($invoice) {
            $data->billing            = new stdClass();
            $data->billing->firstname = $invoice->firstname;
            $data->billing->lastname  = $invoice->lastname;
            $data->billing->address1  = $invoice->address1;
            $data->billing->address2  = $invoice->address2;
            $data->billing->postcode  = $invoice->postcode;
            $data->billing->city      = $invoice->city;
            $data->billing->state     = State::getNameById($invoice->id_state);
            $data->billing->country   = \Genesis\Utils\Country::getCountryISO($invoice->country);
        }

        // Shipping
        if ($shipping) {
            $data->shipping            = new stdClass();
            $data->shipping->firstname = $shipping->firstname;
            $data->shipping->lastname  = $shipping->lastname;
            $data->shipping->address1  = $shipping->address1;
            $data->shipping->address2  = $shipping->address2;
            $data->shipping->postcode  = $shipping->postcode;
            $data->shipping->city      = $shipping->city;
            $data->shipping->state     = State::getNameById($shipping->id_state);
            $data->shipping->country   = \Genesis\Utils\Country::getCountryISO($shipping->country);
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

            $message = 'Unique Id: ' . $response->unique_id . PHP_EOL;

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
            $transaction->id_parent = 0;
            $transaction->ref_order = $new_order->reference;
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
                $this->l("Please, make sure you've entered all of the required data correctly, e.g. Email, Phone, Billing/Shipping Address.")
            );
        }

        Tools::redirect(
            $this->context->link->getModuleLink($this->name, 'checkout')
        );

        return false;
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
        } catch (\Genesis\Exceptions\ErrorAPI $api) {
            $this->logError($api);

            $this->setSessVar('error_direct', $api->getMessage());

            $this->redirectToPage('order.php', array('step' => '3'));
        } catch (\Exception $e) {
            $this->logError($e);

            $this->setSessVar('error_direct',
                $this->l('There was a problem processing your transaction, please try again!')
            );

            $this->redirectToPage('order.php', array('step' => '3'));
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
            $transaction_response->updateOrderHistory(
                Configuration::get('PS_OS_PAYMENT'), true
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
                return Configuration::get('PS_OS_PAYMENT');
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
                return Configuration::get('PS_OS_PAYMENT');
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
            Configuration::get('EMERCHANTPAY_CHECKOUT_TRX_TYPES')
        );

        $alias_map = array(
            \Genesis\API\Constants\Payment\Methods::ELV         =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
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
        );

        foreach ($selected_types as $selected_type) {
            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $processed_list[$transaction_type]['parameters'][] = array(
                    'payment_method' => $selected_type
                );
            } else {
                $processed_list[] = $selected_type;
            }
        }

        return $processed_list;
    }

    /**
     * Get Module's configuration fields
     *
     * @return array field keys
     */
    public function getConfigKeys()
    {
        return array(
            'EMERCHANTPAY_USERNAME',
            'EMERCHANTPAY_PASSWORD',
            'EMERCHANTPAY_TOKEN',
            'EMERCHANTPAY_ENVIRONMENT',
            'EMERCHANTPAY_DIRECT',
            'EMERCHANTPAY_DIRECT_TRX_TYPE',
            'EMERCHANTPAY_CHECKOUT',
            'EMERCHANTPAY_CHECKOUT_TRX_TYPES'
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
            if (in_array($config_key, array('EMERCHANTPAY_CHECKOUT_TRX_TYPES'))) {
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

                if (in_array($key, array('EMERCHANTPAY_CHECKOUT_TRX_TYPES'))) {
                    $value = json_encode($value);
                }

                if (!Validate::isConfigName($key)) {
                    $output = $this->displayError($this->l('Invalid config name: ' . $key));
                } elseif (empty($value)) {
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
                        'type' => 'text',
                        'label' => $this->l('Username'),
                        'desc' => $this->l(
                            'Enter your Username, required for accessing the Genesis Gateway'
                        ),
                        'name' => 'EMERCHANTPAY_USERNAME',
                        'size' => 20,
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Password'),
                        'desc' => $this->l(
                            'Enter your Password, required for accessing the Genesis Gateway'
                        ),
                        'name' => 'EMERCHANTPAY_PASSWORD',
                        'size' => 20,
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Token'),
                        'desc' => $this->l(
                            'Enter your Token, required for accessing the Genesis Gateway'
                        ),
                        'name' => 'EMERCHANTPAY_TOKEN',
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
                        'name' => 'EMERCHANTPAY_ENVIRONMENT',
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
                    array(
                        'type' => 'radio',
                        'label' => 'Direct (Hosted) Payment Method',
                        'desc' => $this->l(
                            'Enable/Disable the Direct API - allow customers to enter their CreditCard information on your website.' . PHP_EOL .
                            'Note: You need PCI-DSS certificate in order to enable this feature.'
                        ),
                        'name' => 'EMERCHANTPAY_DIRECT',
                        'values' => array(
                            array(
                                'id' => 'on',
                                'value' => 'true',
                                'label' => $this->l('Enable'),
                            ),
                            array(
                                'id' => 'off',
                                'value' => 'false',
                                'label' => $this->l('Disable'),
                            )
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Direct Transaction Type'),
                        'desc' => $this->l(
                            'Select the transaction type you want to use for Direct processing.'
                        ),
                        'name' => 'EMERCHANTPAY_DIRECT_TRX_TYPE',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::AUTHORIZE,
                                    'name'  => $this->l('Authorize')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D,
                                    'name'  => $this->l('Authorize 3D')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::SALE,
                                    'name'  => $this->l('Sale')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::SALE_3D,
                                    'name'  => $this->l('Sale 3D')
                                )
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'label' => 'Checkout (Remote) Payment Method',
                        'desc' => $this->l(
                            'Enable/Disable the Checkout payment method - receive credit-card payments, without the need of PCI-DSS certificate or HTTPS.' . PHP_EOL .
                            'Note: Upon checkout, the customer will be redirected to a secure payment form, located on our servers and we will notify you, once the payment reached a final status'
                        ),
                        'name' => 'EMERCHANTPAY_CHECKOUT',
                        'values' => array(
                            array(
                                'id' => 'on',
                                'value' => 'true',
                                'label' => $this->l('Enable'),
                            ),
                            array(
                                'id' => 'off',
                                'value' => 'false',
                                'label' => $this->l('Disable'),
                            )
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Checkout Transaction Types'),
                        'desc' => $this->l(
                            'Select the transaction types you want to use during Checkout session.'
                        ),
                        'id' => 'EMERCHANTPAY_CHECKOUT_TRX_TYPES',
                        'name' => 'EMERCHANTPAY_CHECKOUT_TRX_TYPES[]',
                        'multiple' => true,
                        'options' => array(
                            'query' => array(
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::ABNIDEAL,
                                    'name'  => $this->l('ABN iDEAL')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::AUTHORIZE,
                                    'name'  => $this->l('Authorize')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D,
                                    'name'  => $this->l('Authorize 3D')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::CASHU,
                                    'name'  => $this->l('CashU')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::ELV,
                                    'name'  => $this->l('ELV')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::EPS,
                                    'name'  => $this->l('eps')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::GIRO_PAY,
                                    'name'  => $this->l('GiroPay')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::NETELLER,
                                    'name'  => $this->l('Neteller')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::QIWI,
                                    'name'  => $this->l('Qiwi')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::PAYSAFECARD,
                                    'name'  => $this->l('PaySafeCard')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::PRZELEWY24,
                                    'name'  => $this->l('Przelewy24')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::SAFETY_PAY,
                                    'name'  => $this->l('SafetyPay')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::SALE,
                                    'name'  => $this->l('Sale')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::SALE_3D,
                                    'name'  => $this->l('Sale 3D')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Transaction\Types::SOFORT,
                                    'name'  => $this->l('SOFORT')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::TELEINGRESO,
                                    'name'  => $this->l('teleingreso')
                                ),
                                array(
                                    'id'    => \Genesis\API\Constants\Payment\Methods::TRUST_PAY,
                                    'name'  => $this->l('TrustPay')
                                ),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

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

        /** Check if SSL is enabled */
        if (!Configuration::get('PS_SSL_ENABLED') && $this->isDirectPaymentMethodAvailable()) {
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
}