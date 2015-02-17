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

		if ('POST' != $_SERVER['REQUEST_METHOD']) {
			$this->module->redirectToPage('order.php', array('step' => 3));
		}

		if (!$this->module->checkCurrency($this->context->cart)) {
			$this->module->redirectToPage('order.php', array('step' => 3));
		}

		if (!$this->module->isAvailable()) {
			$this->module->redirectToPage('order.php', array('step' => 3));
		}

		if (Tools::getIsset('submit' . $this->module->name . 'Checkout')) {
			$this->validateCheckout();
		}
		elseif (Tools::getIsset('submit' . $this->module->name . 'Standard')) {
			$this->validateStandard();
		}

		exit(0);
	}

	public function validateCheckout()
	{
		// Is Checkout allowed?
		if (!$this->module->isCheckoutMethodAvailable()) {
			$this->module->redirectToPage('order.php', array('step' => 3));
		}

		// Send transaction
		$url = $this->module->doCheckout();

		if (isset($url)) {
			Tools::redirect($url);
		}
		else {
			Tools::redirect(
				$this->context->link->getModuleLink($this->module->name, 'checkout')
			);
		}

	}

	public function validateStandard()
	{
		// Is standard method allowed?
		if (!$this->module->isStandardMethodAvailable()) {
			$this->module->redirectToPage('order.php', array('step' => 3));
		}

		// Is everything required filled in?
		if (!$this->isRequiredFilled()) {
			$this->module->setSessionVariable( 'error_standard', $this->module->l('Please fill all of the required fields!') );

			$this->module->redirectToPage('order.php', array('step' => '3'));
		}

		// Send transaction
		$this->module->doPayment();
	}

	/**
	 * Check if all required fields are submitted
	 *
	 * @return bool
	 */
	public function isRequiredFilled()
	{
		return Tools::getIsset('emerchantpay-number') &&
		       Tools::getIsset('emerchantpay-name') &&
	           Tools::getIsset('emerchantpay-cvc') &&
	           Tools::getIsset('emerchantpay-expiry');
	}
}