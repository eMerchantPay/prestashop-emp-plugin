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

if (!defined('_PS_VERSION_'))
	exit;

include dirname(__FILE__) . '/classes/eMerchantPayInstall.php';
include dirname(__FILE__) . '/classes/eMerchantPayTransaction.php';
include dirname(__FILE__) . '/classes/eMerchantPayTransactionProcess.php';

class eMerchantPay extends PaymentModule
{
	public function __construct()
	{
		/* Initial Module Setup */
		$this->name         = 'emerchantpay';
		$this->tab          = 'payments_gateways';
		$this->displayName  = 'eMerchantPay Payment Gateway';
		$this->controllers  = array('checkout', 'notification', 'redirect', 'validation');
		$this->version      = 1.0;

		/* The parent construct is required for translations */
		$this->page         = basename(__FILE__, '.php');
		$this->description  = $this->l('Accept payments through eMerchantPay\'s Payment Gateway - Genesis via Standard API');

		/* Use Bootstrap */
		$this->bootstrap = true;

		/* Get all configuration keys */
		$this->config = Configuration::getMultiple( $this->getConfigKeys() );

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

		/* Smarty Presta constants */
		$this->context->smarty->assign('base_dir', __PS_BASE_URI__);
		$this->context->smarty->assign('ps_version', _PS_VERSION_);

		/* Smarty Module constants */
		$this->context->smarty->assign('module_name', $this->name);
		$this->context->smarty->assign('module_path', $this->getPathUri());
		$this->context->smarty->assign('module_warn', $this->warning);
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
	 * Remove all the set Configuration keys and unregister all hooks
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
	 * Is Standard payment method enabled?
	 *
	 * @return bool
	 */
	public function isStandardMethodAvailable() {
		return ($this->config['EMERCHANTPAY_STANDARD'] == 'true' ? true : false);
	}

	/**
	 * Is Checkout payment method available?
	 *
	 * @return bool
	 */
	public function isCheckoutMethodAvailable() {
		return ($this->config['EMERCHANTPAY_CHECKOUT'] == 'true' ? true : false);
	}

	/**
	 * Is the current transaction type async?
	 *
	 * Note: This takes into account only Standard
	 * transaction methods as Checkout is inherently
	 * asynchronous
	 *
	 * @return bool
	 */
	public function isAsyncTransaction() {
		if ($this->isStandardMethodAvailable()) {
			return (stripos($this->config['EMERCHANTPAY_STANDARD_TRX_TYPE'], '3d') !== false) ? true : false;
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
			switch(Tools::getValue($this->name . '_transaction_type')) {
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

		$this->context->smarty->assign(
			array(
				'base_url'          => _PS_BASE_URL_ . __PS_BASE_URI__,
				'order_id'          => $order->id,
				'order_amount'      => $order->getTotalPaid(),
				'order_currency'    => $currency->iso_code,
				'error_transaction' => $this->getSessionVariable('error_transaction'),
				'transactions'      => eMerchantPayTransaction::getTransactionTree((int)$params['id_order']),
			)
		);

		return $this->fetchTemplate('/views/templates/admin/admin_order/transactions.tpl');
	}

	/**
	 * List our payment methods
	 *
	 * @param $params
	 *
	 * @return mixed
	 */
	public function hookPayment($params)
	{
		if (!isset($_SESSION)) {
			session_start();
		}

		if (version_compare(_PS_VERSION_, '1.6', '<')) {
			$this->context->controller->addCSS(
				$this->getPathUri() . 'assets/css/font-awesome.min.css', 'all'
			);
		}

		$this->context->smarty->assign(
			array(
				'module_name'       => $this->name,
				'warning'           => $this->warning,
				//'logo_url'          => Tools::getHttpHost(true) . '/modules/' . $this->name . '/assets/img/logo_500px.png',
				//'form_url'          => Tools::getHttpHost(true) . '/modules/' . $this->name . '/validation.php',
				'checkout_url'      => $this->context->link->getModuleLink($this->name, 'checkout'),
				'standard_url'      => $this->context->link->getModuleLink($this->name, 'validation'),
				'error_standard'    => $this->getSessionVariable('error_standard'),
				'payment_methods'   => array( 'standard' => $this->isStandardMethodAvailable(), 'checkout'  => $this->isCheckoutMethodAvailable() ),
			)
		);

		if (!$this->isAvailable()) {
			return $this->fetchTemplate('blank.tpl');
		}
		else {
			return $this->fetchTemplate('payment.tpl');
		}
	}

	/**
	 * Load the CSS/JS needed in advance to ensure that
	 * when the form is called through hookPayment we
	 * have loaded the CSS/JS.
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function hookPaymentTop($params)
	{
		if (!$this->isAvailable()) {
			return null;
		}

		if (version_compare(_PS_VERSION_, '1.6', '<')) {
			$this->context->controller->addCSS(
				$this->getPathUri() . 'assets/css/bootstrap-custom.min.css', 'all'
			);
			$this->context->controller->addJS(
				$this->getPathUri() . 'assets/js/bootstrap/bootstrap.min.js'
			);
		}

		$this->context->controller->addCSS(
			$this->getPathUri() . 'assets/css/card.min.css', 'all'
		);
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

			$this->smarty->assign( 'order',
				array(
					'reference' => isset( $params['objOrder']->reference ) ? $params['objOrder']->reference : '#' . sprintf( '%06d',
							$params['objOrder']->id ),
					'valid'     => $params['objOrder']->valid
				) );
		}

		switch ($params['objOrder']->current_state) {
			case _PS_OS_PREPARATION_:
				$status = 'pending';
				break;
			case _PS_OS_PAYMENT_:
				$status = 'success';
				break;
			default:
				$status = 'failure';
				break;
		}

		$this->context->smarty->assign(
			array(
				'status' => $status,
			)
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
		$shipping   = new Address(intval($cart->id_address_delivery));
		/** @var AddressCore $invoice */
		$invoice    = new Address(intval($cart->id_address_invoice));
		/** @var CustomerCore $customer */
		$customer   = new Customer(intval($cart->id_customer));
		/** @var CurrencyCore $currency */
		$currency   = new Currency(intval($cart->id_currency));

		$data = new stdClass();

		// Parameters
		$data->id                = md5(mt_rand() . microtime(true) . mt_rand());
		$data->transaction_type  = $this->getStandardTransactionType();

		$data->usage             = $this->l('Prestashop Transaction');

		$description = '';

		foreach ($cart->getProducts() as $product) {
			if (isset($product['name']) && isset($product['quantity'])) {
				$quantity_text = ($product['quantity']) > 1 ? $this->l('pcs') : $this->l('pc');

				$description .= $product['name'] . ' x' . $product['quantity'] . $quantity_text . PHP_EOL;
			}
		}

		$data->description       = $description;

		$data->remote_ip         = Tools::getRemoteAddr();
		$data->currency          = $currency->iso_code;
		$data->amount            = $cart->getOrderTotal();

		$data->customer_email    = $customer->email;
		$data->customer_phone    = (empty($invoice->phone) ? $invoice->phone_mobile : $invoice->phone);

		if (Tools::getIsset('emerchantpay-number')) {
			$data->card_number       = str_replace(' ', '', Tools::getValue('emerchantpay-number'));
			$data->card_type         = $this->getCardTypeByNumber($this->transaction_data->card_number);
			$data->card_last4        = substr($this->transaction_data->card_number, -4);
		}

		if (Tools::getIsset('emerchantpay-name')) {
			$data->card_holder       = Tools::getValue('emerchantpay-name');
		}

		if (Tools::getIsset('emerchantpay-cvc')) {
			$data->cvv               = Tools::getValue('emerchantpay-cvc');
		}

		if (Tools::getIsset('emerchantpay-expiry')) {
			$data->expiration        = Tools::getValue('emerchantpay-expiry');

			list($month, $year) = explode(' / ', $data->expiration);

			$data->expiration_month  = $month;
			$data->expiration_year   = substr(date('Y'), 0, 2) . substr($year, -2);
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
			$data->billing->state     = State::getNameById( $invoice->id_state );
			$data->billing->country   = \Genesis\Utils\Country::getCountryISO( $invoice->country );
		}

		// Shipping
		if ($shipping) {
			$data->shipping = new stdClass();
			$data->shipping->firstname    = $shipping->firstname;
			$data->shipping->lastname     = $shipping->lastname;
			$data->shipping->address1     = $shipping->address1;
			$data->shipping->address2     = $shipping->address2;
			$data->shipping->postcode     = $shipping->postcode;
			$data->shipping->city         = $shipping->city;
			$data->shipping->state        = State::getNameById($shipping->id_state);
			$data->shipping->country      = \Genesis\Utils\Country::getCountryISO($shipping->country);
		}

		// URL endpoints (Async transactions)
		$data->url = new stdClass();
		$data->url->notification      = $this->getNotificationURL();
		$data->url->return_success    = $this->getAsyncSuccessURL();
		$data->url->return_failure    = $this->getAsyncFailureURL();
		$data->url->return_cancel     = $this->getAsyncCancelURL();

		// Set transaction types
		$data->transaction_types = json_decode(
			$this->config['EMERCHANTPAY_CHECKOUT_TRX_TYPES']
		);

		$this->transaction_data = $data;
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
		try {
			if (is_null($this->transaction_data)) {
				$this->populateTransactionData();
			}

			$response = eMerchantPayTransactionProcess::checkout($this->transaction_data);

			if (isset($response)) {
				if ($response->isSuccessful()) {
					$message = 'Unique Id: ' . $response->getResponseObject()->unique_id . PHP_EOL;

					$this->validateOrder( (int) $this->context->cart->id, (int) _PS_OS_PREPARATION_, (float)$this->getResponseAmount( $response->getResponseObject() ), $this->displayName, $message, array(), null, false, $this->context->customer->secure_key );

					// Add Transaction Info to the original Order
					$new_order = new Order( (int)$this->currentOrder );

					// Save the transaction to Db
					$transaction = new eMerchantPayTransaction();
					$transaction->id_parent = 0;
					$transaction->ref_order = $new_order->reference;
					$transaction->type      = 'checkout';
					$transaction->importResponse( $response->getResponseObject() );
					$transaction->add();

					return strval($response->getResponseObject()->redirect_url);
				}
				else {
					if (isset($response) && isset($response->getResponseObject()->message)) {
						$message = strval($response->getResponseObject()->message);
					}
					else {
						$message = $this->l("Please, make sure you've entered all of the required data correctly, e.g. Email, Phone, Billing/Shipping Address.");
					}

					$this->setSessionVariable('error_checkout', $message);

					Tools::redirect(
						$this->context->link->getModuleLink($this->name, 'checkout')
					);
				}
			}
			else {
				$this->setSessionVariable('error_checkout', $this->l("We were unable to process your request, please try again!"));

				Tools::redirect(
					$this->context->link->getModuleLink($this->name, 'checkout')
				);
			}
		}
		catch (Exception $e) {
			error_log($e->getMessage());

			if (class_exists('Logger')) {
				Logger::addLog( $e->getMessage(), 4, $e->getCode(), $this->displayName, $this->id, true );
			}

			$this->setSessionVariable('error_checkout', $this->l('We\'re experiencing technical difficulties, please try again or contact us to resolve this issues!'));

			Tools::redirect(
				$this->context->link->getModuleLink($this->name, 'checkout')
			);
		}
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
		try {
			if (is_null($this->transaction_data)) {
				$this->populateTransactionData();
			}

			$response = eMerchantPayTransactionProcess::pay($this->transaction_data);

			// Valid
			if (isset($response)) {
				// Successful
				if ( $response->isSuccessful() ) {

					if ( isset($response->getResponseObject()->redirect_url) ) {
						// Validate/Insert Order
						$message = 'UniqueId: ' . $response->getResponseObject()->unique_id . PHP_EOL;

						$this->validateOrder( (int) $this->context->cart->id, (int) _PS_OS_PREPARATION_, (float) $this->getResponseAmount( $response->getResponseObject() ), $this->displayName, $message, array(), null, false, $this->context->customer->secure_key );

						// Add Transaction Info to the original Order
						$new_order = new Order( (int)$this->currentOrder );

						// Save the transaction to Db
						$transaction = new eMerchantPayTransaction();
						$transaction->id_parent = 0;
						$transaction->ref_order = $new_order->reference;
						$transaction->importResponse( $response->getResponseObject() );
						$transaction->add();

						Tools::redirect( $response->getResponseObject()->redirect_url );
					} // Standard
					else {
						// Validate/Insert Order
						$message = 'UniqueId: ' . $response->getResponseObject()->unique_id . PHP_EOL;

						$this->validateOrder( (int) $this->context->cart->id, (int) _PS_OS_PAYMENT_, (float) $this->getResponseAmount( $response->getResponseObject() ), $this->displayName, $message, array(), null, false, $this->context->customer->secure_key );

						// Add Transaction Info to the original Order
						$new_order = new Order( (int)$this->currentOrder );

						if ( version_compare( _PS_VERSION_, '1.5', '>=' ) ) {
							if ( Validate::isLoadedObject( $new_order ) ) {
								$payment = $new_order->getOrderPaymentCollection()->getFirst();

								if ( is_object($payment) ) {
									$payment->card_brand      = pSQL( $this->transaction_data->card_type );
									$payment->card_holder     = pSQL( $this->transaction_data->card_holder );
									$payment->card_number     = pSQL( $this->transaction_data->card_last4 );
									$payment->card_expiration = pSQL( $this->transaction_data->expiration );
									$payment->transaction_id  = pSQL( (string)$response->getResponseObject()->unique_id );
									$payment->save();
								}
							}
						}

						// Save the transaction to Db
						$transaction = new eMerchantPayTransaction();
						$transaction->id_parent = 0;
						$transaction->ref_order = $new_order->reference;
						$transaction->importResponse( $response->getResponseObject() );
						$transaction->add();

						// Redirect the customer
						$this->redirectToPage('order-confirmation.php');
					}
				}
				else {
					// Currently, there's no way to log failed transactions in Prestashop.
					// If we try to validate the order, we're actually creating it.
					// Thus if a transaction fails - you'll have to create a new order
				}
			}
			else {
				// In case something prevented the network operations (missing dependency,
				// lack of connectivity, etc.)
			}

			if (isset($response) && isset($response->getResponseObject()->message)) {
				$message = strval($response->getResponseObject()->message);
			}
			else {
				$message = $this->l('There was a problem processing your transaction, please try again!');
			}

			$this->setSessionVariable('error_standard', $message);

			$this->redirectToPage('order.php', array('step' => '3'));
		}
		catch (Exception $e) {
			error_log($e->getMessage());

			if (class_exists('Logger')) {
				Logger::addLog( $e->getMessage(), 4, $e->getCode(), $this->displayName, $this->id, true );
			}

			$this->setSessionVariable('error_standard', $this->l('We\'re experiencing technical difficulties, please try again or contact us to resolve this issues.'));

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
		$id_unique  = Tools::getValue($this->name . '_transaction_id');
		$amount     = Tools::getValue($this->name . '_transaction_amount');
		$usage      = Tools::getValue($this->name . '_transaction_usage');
		$ip_addr    = Tools::getRemoteAddr();

		$transaction = eMerchantPayTransaction::getByUniqueId($id_unique);

		$data = array(
			'transaction_id'    => md5($transaction->date_upd . mt_rand() . microtime(true)),
			'usage'             => $usage,
			'remote_ip'         => $ip_addr,
			'reference_id'      => $transaction->id_unique,
			'currency'          => $transaction->currency,
			'amount'            => $amount,
		);

		$response = eMerchantPayTransactionProcess::capture($data);

		if ($response) {
			$transaction_response = new eMerchantPayTransaction();
			$transaction_response->id_parent = $transaction->id_unique;
			$transaction_response->ref_order = $transaction->ref_order;
			$transaction_response->importResponse($response->getResponseObject());
			$transaction_response->add();
		}
		else {
			$message = $this->l('The transaction was unsuccessful, please check your Logs for more information');

			$this->setSessionVariable('error_transaction', $message);
		}
	}

	/**
	 * Perform a Refund on a Genesis Transaction
	 *
	 * @return bool
	 */
	function doRefund()
	{
		$id_unique  = Tools::getValue($this->name . '_transaction_id');
		$amount     = Tools::getValue($this->name . '_transaction_amount');
		$usage      = Tools::getValue($this->name . '_transaction_usage');
		$ip_addr    = Tools::getRemoteAddr();

		$transaction = eMerchantPayTransaction::getByUniqueId($id_unique);

		$data = array(
			'transaction_id'    => md5($transaction->date_upd . mt_rand() . microtime(true)),
			'usage'             => $usage,
			'remote_ip'         => $ip_addr,
			'reference_id'      => $transaction->id_unique,
			'currency'          => $transaction->currency,
			'amount'            => $amount,
		);

		$response = eMerchantPayTransactionProcess::refund($data);

		if ($response) {
			$transaction_response = new eMerchantPayTransaction();
			$transaction_response->id_parent = $transaction->id_unique;
			$transaction_response->ref_order = $transaction->ref_order;
			$transaction_response->importResponse($response->getResponseObject());
			$transaction_response->add();
		}
		else {
			$message = $this->l('The transaction was unsuccessful, please check your Logs for more information');

			$this->setSessionVariable('error_transaction', $message);
		}
	}

	/**
	 * Perform Void (cancellation) on a Genesis Transaction
	 *
	 * @return bool
	 */
	function doVoid()
	{
		$id_unique  = Tools::getValue($this->name . '_transaction_id');
		$usage      = Tools::getValue($this->name . '_transaction_usage');
		$ip_addr    = Tools::getRemoteAddr();

		$transaction = eMerchantPayTransaction::getByUniqueId($id_unique);

		$data = array(
			'transaction_id'    => md5($transaction->date_upd . mt_rand() . microtime(true)),
			'usage'             => $usage,
			'remote_ip'         => $ip_addr,
			'reference_id'      => $transaction->id_unique,
		);

		$response = eMerchantPayTransactionProcess::void($data);

		if ($response) {
			$transaction_response = new eMerchantPayTransaction();
			$transaction_response->id_parent = $transaction->id_unique;
			$transaction_response->ref_order = $transaction->ref_order;
			$transaction_response->importResponse($response->getResponseObject());
			$transaction_response->add();
		}
		else {
			$message = $this->l('The transaction was unsuccessful, please check your Logs for more information');

			$this->setSessionVariable('error_transaction', $message);
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
		$number = preg_replace('/[^\d]/','',$number);

		if (preg_match('/^3[47][0-9]{13}$/',$number)) {
			return 'American Express';
		}
		elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',$number)) {
			return 'Diners Club';
		}
		elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/',$number)) {
			return 'Discover';
		}
		elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/',$number)) {
			return 'JCB';
		}
		elseif (preg_match('/^5[1-5][0-9]{14}$/',$number)) {
			return 'MasterCard';
		}
		elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/',$number)) {
			return 'Visa';
		}
		else {
			return 'Unknown';
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
		$currency_order     = new Currency((int)($cart->id_currency));
		$currencies_module  = $this->getCurrency((int)$cart->id_currency);

		if (is_array($currencies_module)) {
			foreach ( $currencies_module as $currency_module ) {
				if ( $currency_order->id == $currency_module['id_currency'] ) {
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
	 * @param string $page  Prestashop Page
	 * @param array $args   Optional GET arguments
	 */
	public function redirectToPage($page, $args = array())
	{
		$default = array(
			'id_cart'   => (int) $this->context->cart->id,
			'id_module' => (int) $this->id,
			'id_order'  => (int) $this->currentOrder,
			'key'       => $this->context->customer->secure_key
		);

		$params = array_merge($default, $args);

		if ( version_compare( _PS_VERSION_, '1.5', '<' ) ) {

			$get_arguments = '';

			foreach($params as $key => $value) {
				reset($params);

				$delimiter = ($key === key($params)) ? '?' : '&';

				$get_arguments .= sprintf('%s%s=%s', $delimiter, $key, $value);
			}

			Tools::redirect(
				$this->context->link->getPageLink( $page, true, null ) . $get_arguments );
		}
		else {
			Tools::redirect(
				$this->context->link->getPageLink( $page, true, null, $params) );
		}
	}

	/**
	 * Get a session variable, unique to this module
	 *
	 * @param string $key Name of the variable
	 *
	 * @return mixed
	 */
	public function getSessionVariable($key)
	{
		if (empty($key)) {
			return null;
		}

		if (!isset($_SESSION)) {
			session_start();
		}

		if (isset($_SESSION[$this->name][$key])) {
			$content = $_SESSION[ $this->name ][ $key ];

			unset($_SESSION[$this->name][$key]);

			return $content;
		}
	}

	/**
	 * Set a session variable, unique to this module
	 *
	 * @param string $key   Name of the variable
	 * @param mixed $value  Value of the variable
	 *
	 * @return mixed
	 */
	public function setSessionVariable($key = null, $value = null)
	{
		if (empty($key) || empty($value)) {
			return null;
		}

		if (!isset($_SESSION)) {
			session_start();
		}

		$_SESSION[$this->name][$key] = $value;
	}

	/**
	 * Get Notification URL
	 *
	 * @return mixed Http URL
	 */
	private function getNotificationURL()
	{
		$url = $this->context->link->getModuleLink($this->name, 'notification', array());

		return str_replace( "&", "&amp;", $url);
	}

	/**
	 * Get Success URL for Async Transactions
	 *
	 * @return mixed Http URL
	 */
	private function getAsyncSuccessURL()
	{
		$url = $this->context->link->getModuleLink($this->name, 'redirect', array('action' => 'success'));

		return str_replace( "&", "&amp;", $url);
	}

	/**
	 * Get Failure URL for Async Transactions
	 *
	 * @return mixed Http URL
	 */
	private function getAsyncFailureURL()
	{
		$url = $this->context->link->getModuleLink($this->name, 'redirect', array('action' => 'failure'));

		return str_replace( "&", "&amp;", $url);
	}

	/**
	 * Get Cancel URL for Async Transactions
	 *
	 * @return mixed Http URL
	 */
	private function getAsyncCancelURL()
	{
		$url = $this->context->link->getModuleLink($this->name, 'redirect', array('action' => 'cancel'));

		return str_replace( "&", "&amp;", $url);
	}

	/**
	 * Get the amount in major format
	 *
	 * @param $response stdClass Genesis Response
	 *
	 * @return string
	 */
	private function getResponseAmount($response)
	{
		return \Genesis\Utils\Currency::exponentToReal($response->amount, $response->currency);
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

	/**
	 * Get selected Transaction Type
	 *
	 * @return bool|string
	 */
	public function getStandardTransactionType()
	{
		if (isset($this->config['EMERCHANTPAY_STANDARD_TRX_TYPE'])) {
			switch ( $this->config['EMERCHANTPAY_STANDARD_TRX_TYPE'] ) {
				default:
				case 'authorize':
					$type = 'authorize';
					break;
				case 'authorize3d':
					$type = 'authorize3d';
					break;
				case 'sale':
					$type = 'sale';
					break;
				case 'sale3d':
					$type = 'sale3d';
					break;
			}

			return $type;
		}

		return false;
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
			'EMERCHANTPAY_STANDARD',
			'EMERCHANTPAY_STANDARD_TRX_TYPE',
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
				$config_key_value[ $config_key . '[]' ] = json_decode( Configuration::get( $config_key ) );
			}
			else {
				$config_key_value[ $config_key ] = Configuration::get( $config_key );
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
				}
				elseif (empty($value)) {
					$output = $this->displayError($this->l('Invalid content for: ' . $key));
				}
				else {
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
						'type'      => 'text',
						'label'     => $this->l('Username'),
						'desc'      => $this->l(
							'Enter your Username, required for accessing the Genesis Gateway'
						),
						'name'      => 'EMERCHANTPAY_USERNAME',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'text',
						'label'     => $this->l('Password'),
						'desc'      => $this->l(
							'Enter your Password, required for accessing the Genesis Gateway'
						),
						'name'      => 'EMERCHANTPAY_PASSWORD',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'text',
						'label'     => $this->l('Token'),
						'desc'      => $this->l(
							'Enter your Token, required for accessing the Genesis Gateway'
						),
						'name'      => 'EMERCHANTPAY_TOKEN',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'select',
						'label'     => $this->l('Environment'),
						'desc'      => $this->l(
							'Select the environment you wish to use for processing your transactions.' . PHP_EOL .
							'Note: Its recommended to use the Sandbox environment every-time you alter your settings, in order to ensure everything works as intended.'
						),
						'name'      => 'EMERCHANTPAY_ENVIRONMENT',
						'options'   => array(
							'query' => array(
								array(
									'id'    => 'sandbox',
									'name'  => $this->l('Sandbox')
								),
								array(
									'id'    => 'production',
									'name'  => $this->l('Production')
								)
							),
							'id'    => 'id',
							'name'  => 'name',
						)
					),
					array(
						'type'      => 'radio',
						'label'     => 'Standard (Hosted) Payment Method',
						'desc'      => $this->l(
							'Enable/Disable the Standard API - allow customers to enter their CreditCard information on your website.' . PHP_EOL .
							'Note: You need PCI-DSS certificate in order to enable this feature.'
						),
						'name'      => 'EMERCHANTPAY_STANDARD',
						'values'    => array(
							array(
								'id'    => 'on',
								'value' => 'true',
								'label'  => $this->l('Enable'),
							),
							array(
								'id'    => 'off',
								'value' => 'false',
								'label' => $this->l('Disable'),
							)
						)
					),
					array(
						'type'      => 'select',
						'label'     => $this->l('Standard Transaction Type'),
						'desc'      => $this->l(
							'Select the transaction type, you want to use for Standard processing.'
						),
						'name'      => 'EMERCHANTPAY_STANDARD_TRX_TYPE',
						'options'   => array(
							'query' => array(
								array(
									'id'    => 'authorize',
									'name'  => $this->l('Authorize')
								),
								array(
									'id'    => 'authorize3d',
									'name'  => $this->l('Authorize 3D')
								),
								array(
									'id'    => 'sale',
									'name'  => $this->l('Sale')
								),
								array(
									'id'    => 'sale3d',
									'name'  => $this->l('Sale 3D')
								)
							),
							'id'    => 'id',
							'name'  => 'name',
						)
					),
					array(
						'type'      => 'radio',
						'label'     => 'Checkout (Remote) Payment Method',
						'desc'      => $this->l(
							'Enable/Disable the Checkout payment method - receive credit-card payments, without the need of PCI-DSS certificate or HTTPS.' . PHP_EOL .
							'Note: Upon checkout, the customer will be redirected to a secure payment form, located on our servers and we will notify you, once the payment reached a final status'
						),
						'name'      => 'EMERCHANTPAY_CHECKOUT',
						'values'    => array(
							array(
								'id'    => 'on',
								'value' => 'true',
								'label'  => $this->l('Enable'),
							),
							array(
								'id'    => 'off',
								'value' => 'false',
								'label' => $this->l('Disable'),
							)
						)
					),
					array(
						'type'      => 'select',
						'label'     => $this->l('Checkout Transaction Types'),
						'desc'      => $this->l(
							'Select the transaction types you want to use during Checkout session.'
						),
						'id'        => 'EMERCHANTPAY_CHECKOUT_TRX_TYPES',
						'name'      => 'EMERCHANTPAY_CHECKOUT_TRX_TYPES[]',
						'multiple'  => true,
						'options'   => array(
							'query' => array(
								array(
									'id'    => 'authorize',
									'name'  => $this->l('Authorize')
								),
								array(
									'id'    => 'authorize3d',
									'name'  => $this->l('Authorize 3D')
								),
								array(
									'id'    => 'sale',
									'name'  => $this->l('Sale')
								),
								array(
									'id'    => 'sale3d',
									'name'  => $this->l('Sale 3D')
								)
							),
							'id'    => 'id',
							'name'  => 'name',
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
		$helper->id             = (int)Tools::getValue('id_carrier');
		$helper->identifier     = $this->identifier;
		$helper->token          = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex   = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

		// Language
		$helper->default_form_language      = intval(Configuration::get('PS_LANG_DEFAULT'));
		$helper->allow_employee_form_lang   = intval(Configuration::get('PS_LANG_DEFAULT'));

		$helper->submit_action  = 'submit' . $this->name;

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
		/* Check PHP compatibility */
		if (version_compare(PHP_VERSION, '5.3.0', '<')) {
			$this->warning = $this->l( 'Sorry, this module requires PHP version 5.3 (or higher).' . PHP_EOL . 'Check back with your hosting provider for assistance!');
		}

		/* Check if cURL is available */
		if (!function_exists('curl_version')) {
			$this->warning = $this->l( 'Sorry, this module requires the cURL PHP Extension (http://www.php.net/curl), which is not available on your server.' . PHP_EOL . 'Check back with your hosting provider for assistance.' );
		}

		/* Backward compatibility */
		if (version_compare(_PS_VERSION_, '1.6', '<')) {
			include_once dirname(__FILE__) . '/backward_compatibility/backward.php';
		}

		/** Check if SSL is enabled */
		if (!Configuration::get('PS_SSL_ENABLED') && $this->isStandardMethodAvailable()) {
			$this->warning = $this->l( 'This plugin requires SSL enabled and PCI-DSS compliant server, in order to accept customer\'s credit card information directly on your website!' );
		}

		/* Bootstrap Genesis */
		include_once dirname(__FILE__) . '/lib/genesis_php/vendor/autoload.php';

		/* Check if Genesis Library is initialized */
		if (!class_exists('\Genesis\Genesis')) {
			$this->warning = 'Sorry, there was a problem initializing Genesis client, please verify your installation!';
		}

		/* Check if the module is configured */
		if (!isset($this->config['EMERCHANTPAY_USERNAME']) ||
		    !isset($this->config['EMERCHANTPAY_PASSWORD']))
		{
			$this->warning = $this->l('You need to set your credentials (username, password), in order to connect to Genesis Gateway!');
		}
		else {
			\Genesis\GenesisConfig::setUsername(
				$this->config['EMERCHANTPAY_USERNAME']
			);
			\Genesis\GenesisConfig::setPassword(
				$this->config['EMERCHANTPAY_PASSWORD']
			);
			\Genesis\GenesisConfig::setToken(
				$this->config['EMERCHANTPAY_TOKEN']
			);
			\Genesis\GenesisConfig::setEnvironment(
				$this->config['EMERCHANTPAY_ENVIRONMENT']
			);
		}
	}
}