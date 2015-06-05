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
 * Class eMerchantPayCheckoutModuleFrontController
 *
 * Checkout Front-End Controller
 */
class eMerchantPayCheckoutModuleFrontController extends ModuleFrontController
{
	/** @var eMerchantPay */
	public $module;

	// Hide the left column
	public $display_column_left = false;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		$this->page_name = $this->module->l('eMerchantPay Checkout');

		if ($this->context->customer->isLogged()) {
			$this->initCheckout();
		}
		else {
			$this->module->redirectToPage('my-account.php');
		}
	}

	/**
	 * Show confirmation page to the customer
	 * and inform them, that they are going
	 * to be redirected to our payment gateway.
	 */
	public function initCheckout()
	{
		if ($this->module->isAvailable()) {
			$cart = $this->context->cart;

			if (!$this->module->checkCurrency($cart)) {
				$this->module->redirectToPage('order.php');
			}

			if(!$cart->getOrderTotal(true, $cart::BOTH)) {
				$this->module->redirectToPage('order.php');
			}

			if (version_compare(_PS_VERSION_, '1.6', '<')) {
				$this->context->controller->addCSS(
					$this->module->getPathUri() . 'assets/css/bootstrap-custom.min.css', 'all'
				);
				$this->context->controller->addJS(
					$this->module->getPathUri() . 'assets/js/bootstrap/bootstrap.min.js'
				);
			}

			$this->context->controller->addCSS(
				$this->module->getPathUri() . 'assets/css/card.min.css', 'all'
			);
			$this->context->controller->addJS(
				$this->module->getPathUri() . 'assets/js/card/card.min.js'
			);

			$this->context->smarty->assign(array(
				'product_count' => $cart->nbProducts(),
				'currency'      => $cart->id_currency,
				'total'         => $cart->getOrderTotal(true, $cart::BOTH),
				'isoCode'       => $this->context->language->iso_code,
				'error_checkout'=> $this->module->getSessVar('error_checkout'),
				'link_back'     => $this->context->link->getPageLink('order', true, NULL, "step=3"),
				'link_confirm'  => $this->context->link->getModuleLink($this->module->name, 'validation'),
			));

			$this->setTemplate('checkout_confirmation.tpl');
		}
	}
}