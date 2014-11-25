<?php

if (!defined('_PS_VERSION_'))
	exit;

class eMerchantPayGenesis extends PaymentModule
{
	public function __construct()
	{
		$this->name         = 'emerchantpaygenesis';
		$this->displayName  = 'eMerchantPay Genesis';
		$this->tab          = 'payments_gateways';
		$this->version      = 1.0;

		parent::__construct();

		/* The parent construct is required for translations */
		$this->page         = basename(__FILE__, '.php');
		$this->description  = $this->l('Accept payments through eMerchantPay\'s Payment Gateway - Genesis');

		/* Use Bootstrap */
		$this->bootstrap = true;

		/* Backward compatibility */
		if (version_compare(_PS_VERSION_, '1.5', '<'))
			require(_PS_MODULE_DIR_.$this->name.'/backward_compatibility/backward.php');

		/* Set Smarty BaseDir */
		$this->context->smarty->assign('base_dir', __PS_BASE_URI__);

		/* Warn if curl is not available */
		if (!function_exists('curl_version'))
			$this->warning = $this->l('Sorry, this module requires the cURL PHP Extension (http://www.php.net/curl), which is not enabled on your server. Please ask your hosting provider for assistance.');

		/* Get all configuration keys */
		$this->config = Configuration::getMultiple( $this->getConfigKeys() );

		if (!isset($this->config['EMERCHANTPAY_GENESIS_USERNAME']) ||
		    !isset($this->config['EMERCHANTPAY_GENESIS_PASSWORD']) ||
		    !isset($this->config['EMERCHANTPAY_GENESIS_TOKEN'])) {
			$this->warning = $this->l('You need to set your credentials (username, password, token), in order to access our service!');
		}

		$this->use3D = ($this->config['EMERCHANTPAY_GENESIS_USE3D'] == 'true') ? true : false;

		/* Initialize Genesis Client */
		$this->initGenesis();
	}

	/**
	 * Add our CSS/JS to the Checkout Step
	 *
	 * @return void
	 */
	public function hookHeader()
	{
		if (Tools::getValue('controller') != 'order-opc'
		    && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order.php'
		          || $_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order-opc.php'
		          || Tools::getValue('controller') == 'order'
		          || Tools::getValue('controller') == 'orderopc'
		          || Tools::getValue('step') == 3)
			   )
			)
			return;

		$this->context->controller->addJS($this->_path.'assets/js/card.js');
		$this->context->controller->addCSS($this->_path.'assets/css/style.css', 'all');
	}

	public function hookDisplayMobileHeader()
	{
		return $this->hookHeader();
	}

	public function hookBackOfficeHeader()
	{
		/* Continue only if we are on the order's details page (Back-office) */
		if (!isset($_GET['vieworder']) || !isset($_GET['id_order']))
			return;

		/* If the "Refund" button has been clicked, check if we can perform a partial or full refund on this order */
		if (Tools::isSubmit('process_refund') && isset($_POST['refund_amount']) && isset($_POST['id_transaction'])) {

			try {
				$order_id = Tools::getValue('order_id');

				$order_payment = OrderPayment::getByOrderId($order_id);

				$unique_id = sprintf('%sR-%s', $order_id, md5(microtime(true)));

				$usage = sprintf('Order #%d %s', $order_id, $this->l('Refund'));

				$transaction_id = $order_payment->transaction_id;

				$refund_amount = Tools::getValue('refund_amount');

				$refund_currency = Tools::getValue('refund_currency');

				$genesis = new \Genesis\Genesis('Financial\Refund');

				$genesis
					->request()
						->setUniqueId($unique_id)
						->setUsage($usage)
						->setRemoteIp($_SERVER['REMOTE_ADDR'])
						->setReferenceId($transaction_id)
						->setAmount($refund_amount)
						->setCurrency($refund_currency);
			}
			catch (Exception $e) {
				error_log($e->getMessage());

				if (class_exists('Logger')) {
					$message = $this->l( 'eMerchantPay Genesis - An error has occurred, while trying to issue a refund!' );

					Logger::addLog( $message, 1, null, 'AdminOrder', $this->context->cart->id, true );
				}
			}
		}
	}

	public function hookPayment($params)
	{
		global $smarty;

		$smarty->assign(
			array(
				'this_path' 	=> $this->_path,
				'this_path_ssl' =>
					Configuration::get('PS_FO_PROTOCOL') .
					$_SERVER['HTTP_HOST'] .
					__PS_BASE_URI__ .
					"modules/{$this->name}/"
			)
		);

		return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
	}

	public function hookPaymentReturn($params)
	{
		global $smarty;

		$state = $params['objOrder']->getCurrentState();

		if ($state == _PS_OS_OUTOFSTOCK_ or $state == _PS_OS_PAYMENT_)
			$smarty->assign(array(
				'total_to_pay' 	=> Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false, false),
				'status' 		=> 'ok',
				'id_order' 		=> $params['objOrder']->id
			));
		else
			$smarty->assign('status', 'failed');

		return $this->display(__FILE__, 'payment_return.tpl');
	}

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

		return $this->display(__FILE__, 'order-confirmation.tpl');

	}

	/**
	 * Collect and set parameters for the transaction
	 *
	 * @return stdClass Parsed properties
	 */
	private function prepareData()
	{
		$transactionData = new stdClass();

		$cart = $this->context->cart;

		/** @var AddressCore $shipping */
		$shipping   = new Address(intval($cart->id_address_delivery));
		/** @var AddressCore $invoice */
		$invoice    = new Address(intval($cart->id_address_invoice));
		/** @var CustomerCore $customer */
		$customer   = new Customer(intval($cart->id_customer));

		$currency = new Currency((int)$this->context->cart->id_currency);

		$expiration = array_combine(  array( 'month', 'year' ),  explode( '/', Tools::getValue('emerchantpaygenesis-expiry') ) );

		// hackish y-Y parsing
		if (strlen($expiration['year']) < 4) {
			$expiration['year'] = intval('20' . trim($expiration['year']));
		}

		$transactionData->id                = sprintf('%s-%s', $cart->id, md5(microtime(true)));

		$transactionData->remote_ip         = $_SERVER['REMOTE_ADDR'];
		$transactionData->currency          = $currency->iso_code;
		$transactionData->amount            = $cart->getOrderTotal();

		$transactionData->customer_phone    = $invoice->phone;
		$transactionData->customer_email    = $customer->email;

		$transactionData->card_number       = str_replace(' ', '', Tools::getValue('emerchantpaygenesis-number'));
		$transactionData->card_holder       = Tools::getValue('emerchantpaygenesis-name');
		$transactionData->cvv               = Tools::getValue('emerchantpaygenesis-cvc');

		$transactionData->expiration_month  = $expiration['month'];
		$transactionData->expiration_year   = $expiration['year'];

		// Billing
		$transactionData->billing                   = new stdClass();
		$transactionData->billing->firstname        = $invoice->firstname;
		$transactionData->billing->lastname         = $invoice->lastname;
		$transactionData->billing->address1         = $invoice->address1;
		$transactionData->billing->address2         = $invoice->address2;
		$transactionData->billing->postcode         = $invoice->postcode;
		$transactionData->billing->city             = $invoice->city;
		$transactionData->billing->state            = State::getNameById($invoice->id_state);
		$transactionData->billing->country          = \Genesis\Utils\Country::getCountryISO($invoice->country);

		// Shipping
		if ($shipping) {
			$transactionData->shipping              = new stdClass();
			$transactionData->shipping->firstname   = $shipping->firstname;
			$transactionData->shipping->lastname    = $shipping->lastname;
			$transactionData->shipping->address1    = $shipping->address1;
			$transactionData->shipping->address2    = $shipping->address2;
			$transactionData->shipping->postcode    = $shipping->postcode;
			$transactionData->shipping->city        = $shipping->city;
			$transactionData->shipping->state       = State::getNameById($shipping->id_state);
			$transactionData->shipping->country     = \Genesis\Utils\Country::getCountryISO($shipping->country);
		}

		return $transactionData;
	}

	/**
	 * Process the Payment
	 *
	 * This method will collect all the information it requires
	 * for a transaction and it will try execute it.
	 *
	 * @return void
	 */
	function processPayment()
	{
		try {
			if ($this->use3D) {
				$genesis = new \Genesis\Genesis('Financial\Sale3D');
			}
			else {
				$genesis = new \Genesis\Genesis('Financial\Sale');
			}

			$trxData = $this->prepareData();

			$genesis
				->request()
					->setTransactionId($trxData->id)
					->setRemoteIp($trxData->remote_ip)
					->setCurrency($trxData->currency)
					->setAmount($trxData->amount)
					->setCardHolder($trxData->card_holder)
					->setCardNumber($trxData->card_number)
					->setExpirationMonth($trxData->expiration_month)
					->setExpirationYear($trxData->expiration_year)
					->setCvv($trxData->cvv)
					->setCustomerEmail($trxData->customer_email)
					->setCustomerPhone($trxData->customer_phone)
					->setBillingFirstName($trxData->billing->firstname)
					->setBillingLastName($trxData->billing->lastname)
					->setBillingAddress1($trxData->billing->address1)
					->setBillingAddress2($trxData->billing->address2)
					->setBillingZipCode($trxData->billing->postcode)
					->setBillingCity($trxData->billing->city)
					->setBillingState($trxData->billing->state)
					->setBillingCountry($trxData->billing->country);

			if ($this->use3D) {
				$genesis
					->request()
						->setNotificationUrl(str_replace("&", "&amp;", $this->getNotificationURL()))
						->setReturnSuccessUrl(str_replace("&", "&amp;", $this->getAsyncSuccessURL()))
						->setReturnFailureUrl(str_replace("&", "&amp;", $this->getAsyncFailureURL('')));
			}

			if (isset($trxData->shipping)) {
				$genesis
					->request()
						->setShippingFirstName($trxData->shipping->firstname)
						->setShippingLastName($trxData->shipping->lastname)
						->setShippingAddress1($trxData->shipping->address1)
						->setShippingAddress2($trxData->shipping->address2)
						->setShippingZipCode($trxData->shipping->postcode)
						->setShippingCity($trxData->shipping->city)
						->setShippingState($trxData->shipping->state)
						->setShippingCountry($trxData->shipping->country);
			}

			$genesis->execute();

			$response = $genesis->response()->getResponseObject();

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($response->technical_message);
			}

			if ($this->use3D && strval($response->status) == 'pending_async') {
				Tools::redirect($response->redirect_url);
			}
			else {
				$order_total = \Genesis\Utils\Currency::exponentToReal($response->amount, $response->currency);

				$this->ValidateOrder(
					$this->context->cart->id,
					_PS_OS_PAYMENT_,
					$order_total,
					$this->displayName,
					null,
					array(
						'transaction_id'        => $response->unique_id,
						'transaction_timestamp' => $response->timestamp
					),
					null,
					false,
					$this->context->customer->secure_key
				);

				Tools::redirect(
					'index.php?controller=order-confirmation' .
					'?key=' .       $this->context->customer->secure_key .
					'&id_cart=' .   intval($this->context->cart->id) .
					'&id_module=' . intval($this->module->id) .
					'&id_order=' .  intval($this->module->currentOrder)
				);
			}
		} catch (Exception $e) {
			error_log($e->getMessage());

			if (class_exists('Logger')) {
				$message = $this->l( 'eMerchantPay Genesis - Error processing transaction!' );

				Logger::addLog( $message, 1, null, 'Cart', $this->context->cart->id, true );
			}

			// Redirect the user
			$controller = $this->context->link->getPageLink(
				Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php'
			);

			$separator  = (strpos($controller, '?') !== false ? '&' : '?');

			Tools::redirect(
				sprintf(
					'%s%sstep=3&genesis_error=%s',
					$controller,
					$separator,
					$this->l('We were unable to process your transaction, please try again!')
				)
			);
		}
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
		return $this->context->link->getModuleLink($this->name, 'notification', array('action' => 'success'));
	}

	/**
	 * Get Failure URL for Async Transactions
	 *
	 * @return mixed Http URL
	 */
	private function getAsyncFailureURL()
	{
		return $this->context->link->getModuleLink($this->name, 'notification', array('action' => 'failure'));
	}

	/**
	 * Set the Genesis credentials from the DB settings
	 *
	 * @return void
	 */
	private function initGenesis()
	{
		include dirname(__FILE__) . '/lib/genesis_php/vendor/autoload.php';

		if (!class_exists('\Genesis\Genesis')) {
			$this->warning = 'Sorry, there was a problem initializing Genesis client, please check your installation.';
		}

		\Genesis\GenesisConfig::setUsername( $this->config['EMERCHANTPAY_GENESIS_USERNAME'] );
		\Genesis\GenesisConfig::setPassword( $this->config['EMERCHANTPAY_GENESIS_PASSWORD'] );
		\Genesis\GenesisConfig::setToken( $this->config['EMERCHANTPAY_GENESIS_TOKEN'] );

		switch ($this->config['EMERCHANTPAY_GENESIS_ENVIRONMENT']) {
			default:
			case 'sandbox':
				\Genesis\GenesisConfig::setEnvironment('sandbox');
				break;
			case 'production':
				\Genesis\GenesisConfig::setEnvironment('production');
				break;
		}
	}

	/**
	 * Get the Module Settings HTML form
	 *
	 * @return string HTML code
	 */
	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit'.$this->name)) {

			$config = $this->getConfigKeys();

			$settings = array();

			foreach ($config as $config_key) {
				$settings[$config_key] = Tools::getValue($config_key);
			}

			foreach ($settings as $key => $value) {
				$output .= $this->_verifyFormData($key, $value);
			}
		}

		return $output.$this->_displayForm();
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
					'title' => $this->l('Genesis Configuration'),
					'icon' => 'icon-cog'
				),
				'input' => array(
					array(
						'type'      => 'text',
						'label'     => $this->l('Username'),
						'name'      => 'EMERCHANTPAY_GENESIS_USERNAME',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'text',
						'label'     => $this->l('Password'),
						'name'      => 'EMERCHANTPAY_GENESIS_PASSWORD',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'text',
						'label'     => $this->l('Token'),
						'name'      => 'EMERCHANTPAY_GENESIS_TOKEN',
						'size'      => 20,
						'required'  => true
					),
					array(
						'type'      => 'select',
						'label'     => $this->l('Environment'),
						'name'      => 'EMERCHANTPAY_GENESIS_ENVIRONMENT',
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
						'label'     => $this->l('3D Transactions'),
						'name'      => 'EMERCHANTPAY_GENESIS_USE3D',
						'options'   => array(
							'query' => array(
								array(
									'id'    => 'false',
									'name'  => $this->l('Disabled')
								),
								array(
									'id'    => 'true',
									'name'  => $this->l('Enabled')
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

		/*
		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Genesis Payment Gateway Configuration'),
				'image' => '../img/admin/edit.gif'
			),
			'input' => array(
				array(
					'type'      => 'text',
					'label'     => $this->l('Username'),
					'name'      => 'EMERCHANTPAY_GENESIS_USERNAME',
					'size'      => 20,
					'required'  => true
				),
				array(
					'type'      => 'text',
					'label'     => $this->l('Password'),
					'name'      => 'EMERCHANTPAY_GENESIS_PASSWORD',
					'size'      => 20,
					'required'  => true
				),
				array(
					'type'      => 'text',
					'label'     => $this->l('Token'),
					'name'      => 'EMERCHANTPAY_GENESIS_TOKEN',
					'size'      => 20,
					'required'  => true
				),
				array(
					'type'      => 'select',
					'label'     => $this->l('Environment'),
					'name'      => 'EMERCHANTPAY_GENESIS_ENVIRONMENT',
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
					'label'     => $this->l('3D Transactions'),
					'name'      => 'EMERCHANTPAY_GENESIS_USE3D',
					'options'   => array(
						'query' => array(
							array(
								'id'    => 'false',
								'name'  => $this->l('Disabled')
							),
							array(
								'id'    => 'true',
								'name'  => $this->l('Enabled')
							)
						),
						'id'    => 'id',
						'name'  => 'name',
					)
				),
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		*/

		$helper = new HelperForm();
		// Title and toolbar
		$helper->title          = $this->displayName;
		$helper->show_toolbar   = false;
		$helper->toolbar_scroll = false;
		// Module, token and currentIndex
		$helper->id             = (int)Tools::getValue('id_carrier');
		$helper->identifier     = $this->identifier;
		$helper->token          = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

		// Language
		$helper->default_form_language      = intval(Configuration::get('PS_LANG_DEFAULT'));
		$helper->allow_employee_form_lang   = intval(Configuration::get('PS_LANG_DEFAULT'));

		$helper->submit_action  = 'submit'.$this->name;

		$helper->tpl_vars = array(
			'fields_value'  => $this->getConfigValues(),
			'languages'     => $this->context->controller->getLanguages(),
			'id_language'   => $this->context->language->id
		);

		/*
		$helper->toolbar_btn = array(
			'save' =>
				array(
					'desc' => $this->l('Save'),
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
					          '&token='.Tools::getAdminTokenLite('AdminModules'),
				),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);
		*/

		return $helper->generateForm(array($fields_form));
	}

	/**
	 * Validate module settings
	 *
	 * @param $key   Setting Key
	 * @param $value Setting Value
	 *
	 * @return mixed
	 */
	private function _verifyFormData($key, $value)
	{
		if (empty($value) || !Validate::isGenericName($value))
			return $this->displayError($this->l('Invalid Configuration value'));
		else
		{
			Configuration::updateValue($key, $value);
			return $this->displayConfirmation($this->l('Settings updated'));
		}
	}

	/**
	 * Get Module's Configuration fields
	 *
	 * @return array field keys
	 */
	private function getConfigKeys()
	{
		return array(
			'EMERCHANTPAY_GENESIS_USERNAME',
			'EMERCHANTPAY_GENESIS_PASSWORD',
			'EMERCHANTPAY_GENESIS_TOKEN',
			'EMERCHANTPAY_GENESIS_ENVIRONMENT',
			'EMERCHANTPAY_GENESIS_USE3D',
		);
	}

	/**
	 * Get the configuration keys and their respective values
	 *
	 * @return array Key/Value array
	 */
	private function getConfigValues()
	{
		$config = $this->getConfigKeys();

		$config_key_value = array();

		foreach ($config as $config_key) {
			$config_key_value[$config_key] = Configuration::get($config_key);
		}

		return $config_key_value;
	}

	/**
	 * Install logic
	 *
	 * Install and create/register the require hooks
	 *
	 * @return bool
	 */
	function install()
	{
		/* The cURL PHP extension must be enabled to use this module */
		if (!function_exists('curl_version'))
		{
			$this->_errors[] = $this->l('Sorry, this module requires the cURL PHP Extension (http://www.php.net/curl), which is not enabled on your server. Please ask your hosting provider for assistance.');
			return false;
		}

		//Call PaymentModule default install function
		$install = parent::install() && $this->registerHook('payment') && $this->registerHook('header') && $this->registerHook('orderConfirmation') && $this->registerHook('BackOfficeHeader');
		$this->registerHook('displayMobileHeader');
		return $install;
	}

	/**
	 * Uninstall logic
	 *
	 * Remove all the set Configuration keys and unregister all hooks
	 *
	 * @return bool
	 */
	function uninstall()
	{
		$config = $this->getConfigKeys();

		foreach ($config as $config_key) {
			Configuration::deleteByName($config_key);
		}

		return $this->unregisterHook('payment') && $this->unregisterHook('paymentReturn') && parent::uninstall();
	}
}