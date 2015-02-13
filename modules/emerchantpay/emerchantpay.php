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

include __DIR__ . '/emerchantpay_install.php';
include __DIR__ . '/emerchantpay_transaction.php';
include __DIR__ . '/emerchantpay_transaction_process.php';

class eMerchantPay extends PaymentModule
{
	public function __construct()
	{
		/* Initial Module Setup */
		$this->name         = 'emerchantpay';
		$this->tab          = 'payments_gateways';
		$this->displayName  = 'eMerchantPay Payment Gateway';
		$this->controllers  = array('notification', 'payment');
		$this->version      = 1.0;

		/* The parent construct is required for translations */
		$this->page         = basename(__FILE__, '.php');
		$this->description  = $this->l('Accept payments through eMerchantPay\'s Payment Gateway - Genesis via Standard API');

		/* Use Bootstrap */
		$this->bootstrap = true;

		/* Get all configuration keys */
		$this->config = Configuration::getMultiple( $this->getConfigKeys() );

		/* Flag, whether or not 3DSecure transaction type is selected */
		$this->is3DSecure = (stripos($this->config['EMERCHANTPAY_TRANSACTION_TYPE'], '3d') !== false) ? true : false;

		/* Storage for transaction data to avoid init/call every-time */
		$this->transaction_data = new stdClass();

		/* Store warnings during init */
		$this->warning = '';

		/* Initialize Genesis Client */
		$this->init();

		/* Error if conditions are not met */
		$this->isAvailable();

		/* Run all parent constructors */
		parent::__construct();

		/* Set Smarty BaseDir */
		$this->context->smarty->assign('base_dir', __PS_BASE_URI__);
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
		if (isset($this->warning)) {
			return false;
		}

		return true;
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
				$this->_path . 'assets/css/font-awesome.min.css', 'all'
			);
		}

		$this->context->controller->addCSS(
			$this->_path . 'assets/css/treegrid.min.css', 'all'
		);
		$this->context->controller->addJS(
			$this->_path . 'assets/js/treegrid/cookie.min.js'
		);
		$this->context->controller->addJS(
			$this->_path . 'assets/js/treegrid/treegrid.min.js'
		);

		$currency = new Currency((int)$order->id_currency);

		$this->context->smarty->assign(
			array(
				'warning'           => $this->warning,
				'ps_version'        => _PS_VERSION_,
				'base_url'          => _PS_BASE_URL_ . __PS_BASE_URI__,
				'module_name'       => $this->name,
				'order_id'          => $order->id,
				'order_amount'      => $order->getTotalPaid(),
				'order_currency'    => $currency->iso_code,
				'payment_error'     => $this->getSessionVariable('admin_transaction_error'),
				'transactions'      => eMerchantPayTransaction::getTransactionTree((int)$params['id_order']),
			)
		);

		return $this->fetchTemplate('/views/templates/admin/admin_order/transactions.tpl');
	}

	public function hookProductCancel($params)
	{
		if (Tools::isSubmit('generateDiscount')) {
			return false;
		}
		elseif ($params['order']->module != $this->name || !($order = $params['order']) || !Validate::isLoadedObject($order)) {
			return false;
		}
		elseif (!$order->hasBeenPaid()) {
			return false;
		}

		$order_detail = new OrderDetail((int)$params['id_order_detail']);
		if (!$order_detail || !Validate::isLoadedObject($order_detail))
			return false;

		$transactions = eMerchantPayOrder::getTransactionByOrderId((int)$order->id);

		/** @var eMerchantPayTransaction $transaction */
		$transaction = null;

		foreach ($transactions as $trx) {
			if (in_array(array('capture', 'sale'), $trx['type'])) {
				if ($trx['status'] == 'approved') {
					$transaction = $trx;
				}
			}
		}

		if (!$transaction) {
			return false;
		}

		$products   = $order->getProducts();
		$quantity   = Tools::getValue('cancelQuantity');

		$amount = (float)($products[(int)$order_detail->id]['product_price_wt'] * (int)$quantity[(int)$order_detail->id]);

		$this->doRefund(
			array(
				'id_unique' => $transaction->id_unique,
				'amount'    => $amount,
				'usage'     => (Tools::getIsset('discount_name') ? Tools::getValue('discount_name') : 'Prestashop Refund'),
			)
		);

		return true;
	}

	/**
	 * Display our payment page
	 *
	 * @param $params
	 *
	 * @return mixed
	 */
	public function hookPayment($params)
	{
		global $smarty;

		if (!isset($_SESSION)) {
			session_start();
		}

		$smarty->assign(
			array(
				'path'          => $this->_path,
				'module_name'   => $this->name,
				'warning'       => $this->warning,
				'logo_url'      => Tools::getHttpHost(true) . '/modules/' . $this->name . '/assets/img/logo_500px.png',
				'form_url'      => Tools::getHttpHost(true) . '/modules/' . $this->name . '/validation.php',
				'payment_error' => $this->getSessionVariable('payment_error'),
			)
		);

		return $this->fetchTemplate('payment.tpl');
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
		if (version_compare(_PS_VERSION_, '1.6', '<')) {
			$this->context->controller->addCSS(
				$this->_path . 'assets/css/bootstrap-custom.min.css', 'all'
			);
			$this->context->controller->addJS(
				$this->_path  . 'assets/js/bootstrap/bootstrap.min.js'
			);
		}

		$this->context->controller->addCSS(
			$this->_path . 'assets/css/card.min.css', 'all'
		);
		$this->context->controller->addJS(
			$this->_path  . 'assets/js/card/card.min.js'
		);
	}

	/**
	 * Show the customer a message regarding his order status
	 *
	 * @param $params
	 *
	 * @return mixed
	 */
	public function hookPaymentReturn($params)
	{
		global $smarty;

		$state = $params['objOrder']->getCurrentState();

		if ($state == _PS_OS_OUTOFSTOCK_ or $state == _PS_OS_PAYMENT_) {
			$smarty->assign( array(
				'status'       => 'ok',
				'id_order'     => $params['objOrder']->id,
				'total_to_pay' => Tools::displayPrice( $params['total_to_pay'], $params['currencyObj'], false, false )
			) );
		}
		else {
			$smarty->assign( 'status', 'failed' );
		}

		return $this->fetchTemplate('payment_return.tpl');
	}

	/*
	public function hookOrderConfirmation($params)
	{
		if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name))
			return false;

		if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid))

			$this->smarty->assign('order', array('reference' => isset($params['objOrder']->reference) ? $params['objOrder']->reference : '#'.sprintf('%06d', $params['objOrder']->id), 'valid' => $params['objOrder']->valid));

		$pendingOrderStatus = (int)Configuration::get('TWOCHECKOUT_PENDING_ORDER_STATUS');
		$currentOrderStatus = (int)$params['objOrder']->getCurrentState();

		if ($pendingOrderStatus==$currentOrderStatus) {
			$this->smarty->assign('order_pending', true);
		} else {
			$this->smarty->assign('order_pending', false);
		}

		return $this->fetchTemplate('confirmation.tpl');
	}
	*/

	/**
	 * Collect and process the data required for the initial payment.
	 *
	 * @return stdClass Processed data
	 */
	public function populateTransactionData()
	{
		/** @var CartCore $cart */
		$cart = new Cart((int)$this->context->cart->id);

		/** @var AddressCore $shipping */
		$shipping   = new Address(intval($cart->id_address_delivery));
		/** @var AddressCore $invoice */
		$invoice    = new Address(intval($cart->id_address_invoice));
		/** @var CustomerCore $customer */
		$customer   = new Customer(intval($cart->id_customer));
		/** @var CurrencyCore $currency */
		$currency   = new Currency(intval($cart->id_currency));

		// Parameters
		$this->transaction_data->id                = sprintf('%s-%s', $cart->id, md5(microtime(true)));
		$this->transaction_data->transaction_type  = $this->config['EMERCHANTPAY_TRANSACTION_TYPE'];

		$this->transaction_data->remote_ip         = $_SERVER['REMOTE_ADDR'];
		$this->transaction_data->currency          = $currency->iso_code;
		$this->transaction_data->amount            = $cart->getOrderTotal();

		$this->transaction_data->customer_phone    = $invoice->phone;
		$this->transaction_data->customer_email    = $customer->email;

		$this->transaction_data->card_number       = str_replace(' ', '', Tools::getValue('emerchantpay-number'));
		$this->transaction_data->card_holder       = Tools::getValue('emerchantpay-name');
		$this->transaction_data->cvv               = Tools::getValue('emerchantpay-cvc');
		$this->transaction_data->expiration        = Tools::getValue('emerchantpay-expiry');

		$this->transaction_data->card_type         = $this->getCardTypeByNumber($this->transaction_data->card_number);
		$this->transaction_data->card_last4        = substr($this->transaction_data->card_number, -4);

		list($month, $year) = explode(' / ', $this->transaction_data->expiration);

		$this->transaction_data->expiration_month  = $month;
		$this->transaction_data->expiration_year   = substr(date('Y'), 0, 2) . substr($year, -2);

		// Billing
		$this->transaction_data->billing = new stdClass();
		$this->transaction_data->billing->firstname         = $invoice->firstname;
		$this->transaction_data->billing->lastname          = $invoice->lastname;
		$this->transaction_data->billing->address1          = $invoice->address1;
		$this->transaction_data->billing->address2          = $invoice->address2;
		$this->transaction_data->billing->postcode          = $invoice->postcode;
		$this->transaction_data->billing->city              = $invoice->city;
		$this->transaction_data->billing->state             = State::getNameById($invoice->id_state);
		$this->transaction_data->billing->country           = \Genesis\Utils\Country::getCountryISO($invoice->country);

		// Shipping
		if ($shipping) {
			$this->transaction_data->shipping = new stdClass();
			$this->transaction_data->shipping->firstname    = $shipping->firstname;
			$this->transaction_data->shipping->lastname     = $shipping->lastname;
			$this->transaction_data->shipping->address1     = $shipping->address1;
			$this->transaction_data->shipping->address2     = $shipping->address2;
			$this->transaction_data->shipping->postcode     = $shipping->postcode;
			$this->transaction_data->shipping->city         = $shipping->city;
			$this->transaction_data->shipping->state        = State::getNameById($shipping->id_state);
			$this->transaction_data->shipping->country      = \Genesis\Utils\Country::getCountryISO($shipping->country);
		}

		// URL (Async transactions)
		if ($this->is3DSecure) {
			$this->transaction_data->url = new stdClass();
			$this->transaction_data->url->notification      = $this->getNotificationURL();
			$this->transaction_data->url->return_success    = $this->getAsyncSuccessURL();
			$this->transaction_data->url->return_failure    = $this->getAsyncFailureURL();
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
			//$this->populateTransactionData();

			$response = eMerchantPayTransactionProcess::pay($this->transaction_data);

			// Successful
			if (isset($response)) {
				if ( $response->isSuccessful() ) {

					// 3DSecure
					if ( $this->is3DSecure ) {
						// Validate/Insert Order
						$message = 'UniqueId: ' . $response->getResponseObject()->unique_id . PHP_EOL;

						/*
							$this->l('Genesis Transaction Details:'). PHP_EOL . PHP_EOL .
							$this->l('Unique ID:').' '.$responseData->unique_id . PHP_EOL .
							$this->l('Status:').' '.$responseData->status . PHP_EOL .
							$this->l('Amount:').' '. $this->getResponseAmount($responseData) . PHP_EOL .
							$this->l('Currency:').' '.$responseData->currency . PHP_EOL .
							$this->l('Message:').' '.$responseData->message . PHP_EOL;
						*/

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

						/*
							 $this->l('Genesis Transaction Details:'). PHP_EOL . PHP_EOL .
							 $this->l('Unique ID:').' '.$responseData->unique_id . PHP_EOL .
							 $this->l('Status:').' '.$responseData->status . PHP_EOL .
							 $this->l('Amount:').' '. $this->getResponseAmount($responseData) . PHP_EOL .
							 $this->l('Currency:').' '.$responseData->currency . PHP_EOL .
							 $this->l('Message:').' '.$responseData->message . PHP_EOL;
						*/

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

			if (isset($response) && is_object($response)) {
				$message = strval($response->getResponseObject()->message);
			}
			else {
				$message = $this->l('There was a problem processing your transaction, please try again!');
			}

			$this->setSessionVariable('payment_error', $message);

			$this->redirectToPage('order.php', array('step' => '3'));
		}
		catch (Exception $e) {
			error_log($e->getMessage());

			if (class_exists('Logger')) {
				Logger::addLog( $e->getMessage(), 4, $e->getCode(), $this->displayName, $this->id, true );
			}

			$this->setSessionVariable('payment_error', $this->l('We\'re experiencing technical difficulties, please try again or contact us to resolve this issues.'));

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

			$this->setSessionVariable('admin_transaction_error', $message);
		}
	}

	/**
	 * Perform a Refund on a Genesis Transaction
	 *
	 * @return bool
	 */
	function doRefund($args = array())
	{
		$default = array(
			'id_unique' => Tools::getValue($this->name . '_transaction_id'),
			'amount'    => Tools::getValue($this->name . '_transaction_amount'),
			'usage'     => Tools::getValue($this->name . '_transaction_usage'),
			'ip_addr'   => Tools::getRemoteAddr()
		);

		/*
		$id_unique  = Tools::getValue($this->name . '_transaction_id');
		$amount     = Tools::getValue($this->name . '_transaction_amount');
		$usage      = Tools::getValue($this->name . '_transaction_usage');
		$ip_addr    = Tools::getRemoteAddr();
		*/

		$params = array_merge($default, $args);

		$transaction = eMerchantPayTransaction::getByUniqueId($params['id_unique']);

		$data = array(
			'transaction_id'    => md5($transaction->date_upd . mt_rand() . microtime(true)),
			'usage'             => $params['usage'],
			'remote_ip'         => $params['ip_addr'],
			'reference_id'      => $transaction->id_unique,
			'currency'          => $transaction->currency,
			'amount'            => $params['amount'],
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

			$this->setSessionVariable('admin_transaction_error', $message);
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

			$this->setSessionVariable('admin_transaction_error', $message);
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

				if (empty($value) || !Validate::isGenericName($value)) {
					$output = $this->displayError($this->l('Invalid value for key: ' . $key));
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
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('eMerchantPay Configuration'),
					'icon' => 'icon-cog'
				),
				'input' => array(
					array(
						'type'      => 'text',
						'label'     => $this->l('Username'),
						'name'      => 'EMERCHANTPAY_USERNAME',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'text',
						'label'     => $this->l('Password'),
						'name'      => 'EMERCHANTPAY_PASSWORD',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'text',
						'label'     => $this->l('Token'),
						'name'      => 'EMERCHANTPAY_TOKEN',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'select',
						'label'     => $this->l('Environment'),
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
						'type'      => 'select',
						'label'     => $this->l('Transaction type'),
						'name'      => 'EMERCHANTPAY_TRANSACTION_TYPE',
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
			'languages'     => $this->context->controller->getLanguages(),
			'id_language'   => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	/*
	public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}
	*/

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
		if (version_compare(_PS_VERSION_, '1.4', '<')) {
			$this->context->smarty->currentTemplate = $name;
		}
		elseif (version_compare(_PS_VERSION_, '1.5', '<')) {

			$locations = array(
				'/'. $name,
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
	 * Get Module's configuration fields
	 *
	 * @return array field keys
	 */
	private function getConfigKeys()
	{
		return array(
			'EMERCHANTPAY_USERNAME',
			'EMERCHANTPAY_PASSWORD',
			'EMERCHANTPAY_TOKEN',
			'EMERCHANTPAY_ENVIRONMENT',
			'EMERCHANTPAY_TRANSACTION_TYPE',
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
			$config_key_value[$config_key] = Configuration::get($config_key);
		}

		return $config_key_value;
	}

	/**
	 * Initialize the module:
	 * - Include the backward compatibility
	 * - Include and populate gateway settings
	 *
	 * @return void
	 */
	private function init()
	{
		/* Backward compatibility */
		if (version_compare(_PS_VERSION_, '1.6', '<')) {
			include_once  __DIR__ . '/backward_compatibility/backward.php';
		}

		/** Check if SSL is enabled */
		if (!Configuration::get('PS_SSL_ENABLED')) {
			//$this->warning = $this->l( 'This plugin requires SSL enabled and PCI-DSS compliant server!' );
		}

		/* Check if cURL is available */
		if (!function_exists('curl_version')) {
			$this->warning = $this->l( 'Sorry, this module requires the cURL PHP Extension (http://www.php.net/curl), which is not available on your server.' . PHP_EOL . 'Please ask your hosting provider for assistance.' );
		}

		/* Bootstrap Genesis */
		include_once __DIR__ . '/lib/genesis_php/vendor/autoload.php';

		/* Check if Genesis Library is initialized */
		if (!class_exists('\Genesis\Genesis')) {
			$this->warning = 'Sorry, there was a problem initializing Genesis client, please check your installation!';
		}

		/* Check if the module is configured */
		if (!isset($this->config['EMERCHANTPAY_USERNAME']) ||
		    !isset($this->config['EMERCHANTPAY_PASSWORD']) ||
		    !isset($this->config['EMERCHANTPAY_TOKEN']))
		{
			$this->warning = $this->l('You need to set your credentials (username, password, token), in order to access our service!');
		}

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