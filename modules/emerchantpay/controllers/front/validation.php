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

/**
 * Class eMerchantPayValidationModuleFrontController
 *
 * Validation Front-End Controller
 */
class eMerchantPayValidationModuleFrontController extends ModuleFrontControllerCore
{
	/** @var eMerchantPay */
	public $module;

	/**
	 * @see FrontControlwler::initContent()
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
		elseif (Tools::getIsset('submit' . $this->module->name . 'Direct')) {
			$this->validateDirect();
		}
	}

	public function validateCheckout()
	{
		// Is Checkout allowed?
		if (!$this->module->isCheckoutPaymentMethodAvailable()) {
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

	public function validateDirect()
	{
		// Is standard method allowed?
		if (!$this->module->isDirectPaymentMethodAvailable()) {
			$this->module->redirectToPage('order.php', array('step' => 3));
		}

		// Is everything required filled in?
		if (!$this->isRequiredFilled()) {
			$this->module->setSessVar( 'error_direct',
                                               $this->module->l('Please fill all of the required fields!')
            );

			$this->module->redirectToPage('order.php', array('step' => '3'));
		}

        $this->module->doPayment();
	}

	/**
	 * Check if all required fields are submitted
	 *
	 * @return bool
	 */
	public function isRequiredFilled()
	{
		return Tools::getIsset('emerchantpay-cvc') &&
		       Tools::getIsset('emerchantpay-name') &&
               Tools::getIsset('emerchantpay-number') &&
	           Tools::getIsset('emerchantpay-expiry');
	}
}