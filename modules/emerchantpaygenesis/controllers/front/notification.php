<?php

ini_set('display_errors', 1);

class eMerchantPayGenesisNotificationModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		if (Tools::getValue('unique_id') && Tools::getValue('signature')) {
			$this->bootstrapGenesis();
			$this->processNotification();
		}

		switch (Tools::getValue('action')) {
			case 'success':
				$this->handleSuccessfulRedirect();
				break;
			case 'failure':
				$this->handleFailureRedirect();
				break;
		}

		exit(0);
	}

	private function processNotification()
	{
		/** @var \Genesis\API\Notification $notification */
		$notification = new \Genesis\API\Notification();

		$notification->parseNotification($_POST);

		if ($notification->isAuthentic()) {
			$genesis = new \Genesis\Genesis('Reconcile\Transaction');

			$genesis
				->request()
					->setUniqueId($notification->getParsedNotification()->unique_id);

			$genesis->execute();

			$reconcile = $genesis->response()->getResponseObject();

			if (isset($reconcile->transaction_id)) {

				$transaction_id = explode('-', $reconcile->transaction_id);

				$cart_id = (intval($transaction_id[0]) > 0) ? intval($transaction_id[0]) : null;

				$context = Context::getContext();

				$context->cart = new Cart((int)$cart_id);

				if (Validate::isLoadedObject($context->cart)) {
					if ($context->cart->OrderExists()) {
						$order = new Order((int)Order::getOrderByCartId($context->cart->id));

						$new_history = new OrderHistory();
						$new_history->id_order = (int)$order->id;
						$new_history->changeIdOrderState((int)_PS_OS_PAYMENT_, $order, true);
						$new_history->addWithemail(true);
					}
					else {
						$context->customer = new Customer((int)$context->cart->id_customer);

						$this->module->ValidateOrder(
							$context->cart->id,
							_PS_OS_PAYMENT_,
							\Genesis\Utils\Currency::exponentToReal($reconcile->amount, $reconcile->currency),
							$this->module->displayName,
							null,
							array(
								'transaction_id'        => $reconcile->unique_id,
								'transaction_timestamp' => $reconcile->timestamp
							),
							null,
							false,
							$this->context->customer->secure_key
						);
					}

					header( 'Content-type: application/xml' );
					echo $notification->getEchoResponse();
				}
			}
		}
	}

	private function bootstrapGenesis()
	{
		include _PS_MODULE_DIR_ . $this->module->name. '/lib/genesis_php/vendor/autoload.php';

		\Genesis\GenesisConfig::setUsername(
			Configuration::get('EMERCHANTPAY_GENESIS_USERNAME')
		);
		\Genesis\GenesisConfig::setPassword(
			Configuration::get('EMERCHANTPAY_GENESIS_PASSWORD')
		);
		\Genesis\GenesisConfig::setToken(
			Configuration::get('EMERCHANTPAY_GENESIS_TOKEN')
		);

		switch (Configuration::get('EMERCHANTPAY_GENESIS_ENVIRONMENT')) {
			default:
			case 'sandbox':
				\Genesis\GenesisConfig::setEnvironment('sandbox');
				break;
			case 'production':
				\Genesis\GenesisConfig::setEnvironment('production');
				break;
		}
	}

	private function handleSuccessfulRedirect()
	{
		$controller = $this->context->link->getPageLink(
			Configuration::get( 'PS_ORDER_PROCESS_TYPE' ) ? 'order-confirmation.php' : 'order.php'
		);

		$separator  = ( strpos( $controller, '?' ) !== false ? '&' : '?' );

		$secure_key = $this->context->customer->secure_key;
		$id_cart    = $this->context->cart->id;

		$id_module  = $this->module->id;
		$id_order   = $this->module->currentOrder;

		$location = sprintf(
			'%s%skey=%s&id_cart=%s&id_module=%s&id_order=%s',
			$controller,
			$separator,
			$secure_key,
			$id_cart,
			$id_module,
			$id_order
		);

		Tools::redirect($location);
	}

	private function handleFailureRedirect()
	{
		$controller = $this->context->link->getPageLink(
			Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php'
		);

		$separator  = (strpos($controller, '?') !== false ? '&' : '?');

		$message = $this->module->l("We were unable to process your transaction, please try again!");

		$location = sprintf(
			'%s%sstep=3&genesis_error=%s',
			$controller,
			$separator,
			urlencode($message)
		);

		Tools::redirect($location);
	}
}