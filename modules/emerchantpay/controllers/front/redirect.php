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

class eMerchantPayRedirectModuleFrontController extends ModuleFrontController
{
	public $errors = array();
	/** @var  eMerchantPay */
	public $module;
	/** @var  ContextCore  */
	protected $context;

	public function initContent()
	{
		parent::initContent();

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

	private function handleSuccessfulRedirect()
	{
		Tools::redirect($this->context->link->getPageLink('history.php'));
	}

	private function handleFailureRedirect()
	{
		$this->module->setSessionVariable('payment_error', $this->module->l('There was a problem processing your transaction, please try again!') );

		$order = Order::getCustomerOrders($this->context->customer->id, false, $this->context);

		$order = reset($order);

		$oldCart = new Cart(Order::getCartIdStatic($order['id_order'], $this->context->customer->id));

		$duplication = $oldCart->duplicate();

		if (!$duplication || !Validate::isLoadedObject($duplication['cart'])) {
			$this->errors[] = Tools::displayError( 'Sorry. We cannot renew your order.' );
		}
		else if (!$duplication['success']) {
			$this->errors[] = Tools::displayError( 'Some items are no longer available, and we are unable to renew your order.' );
		}
		else
		{
			$this->context->cookie->id_cart = $duplication['cart']->id;
			$this->context->cookie->write();

			if (Configuration::get('PS_ORDER_PROCESS_TYPE') == 1) {
				Tools::redirect( 'index.php?controller=order-opc&step=3' );
			}
			else {
				Tools::redirect( 'index.php?controller=order&step=3' );
			}
		}

		// If all else fails, redirect the customer to their OrderHistory
		Tools::redirect($this->context->link->getPageLink('history.php'));
	}
}