<?php

class eMerchantPayValidationModuleFrontController extends ModuleFrontControllerCore
{
	/** @var eMerchantPay */
	public $module;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;

		if ('POST' != $_SERVER['REQUEST_METHOD']) {
			$this->module->redirectToPage('order.php');
		}

		if (!$this->module->checkCurrency($cart)) {
			$this->module->redirectToPage('order.php');
		}

		if (!$this->checkPostFields()) {

			$this->module->setSessionVariable(
				$this->module->l('Please fill all of the required fields!')
			);

			$this->module->redirectToPage('order.php', array('step' => '3'));
		}

		// Prepare all the required data
		$this->module->populateTransactionData();

		// Send payment
		$this->module->doPayment();
	}

	/**
	 * Check if all required fields are submitted
	 *
	 * @return bool
	 */
	public function checkPostFields()
	{
		return Tools::getIsset('emerchantpay-number') &&
		       Tools::getIsset('emerchantpay-name') &&
	           Tools::getIsset('emerchantpay-cvc') &&
	           Tools::getIsset('emerchantpay-expiry');
	}
}