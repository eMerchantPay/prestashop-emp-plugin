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
	/** @var  eMerchantPay */
	public $module;
	/** @var  ContextCore  */
	protected $context;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		if (version_compare(_PS_VERSION_, '1.6', '<')) {
			$this->display_column_left  = true;
			$this->display_column_right = true;
		}

		parent::initContent();

		if (Tools::getIsset('restore')) {
			if (Tools::getValue('restore') == 'cart') {
				$this->restoreCustomerCart();
			}
		}

		$this->context->smarty->assign(
			array(
				'status'        => Tools::getValue('action'),
				'url_history'   => $this->context->link->getPageLink('history.php'),
				'url_restore'   => $this->context->link->getModuleLink(
					$this->module->name, 'redirect', array('restore' => 'cart')
				),
				'url_support'   => $this->context->link->getPageLink('contact.php'),
			)
		);

		$this->setTemplate('async_return.tpl');
	}

	/**
	 * Restore customer's cart
	 *
	 * @return void
	 */
	private function restoreCustomerCart()
	{
		$order = Order::getCustomerOrders($this->context->customer->id, false, $this->context);

		$order = reset($order);

		$oldCart = new Cart((int)Order::getCartIdStatic($order['id_order'], $this->context->customer->id));

		$duplication = $oldCart->duplicate();

		if ($duplication && Validate::isLoadedObject($duplication['cart']))
		{
			$this->context->cookie->id_cart = $duplication['cart']->id;
			$this->context->cookie->write();

			if (Configuration::get('PS_ORDER_PROCESS_TYPE') == PS_ORDER_PROCESS_OPC) {
				$this->module->redirectToPage('order-opc.php', array('step' => '3'));
			}
			else {
				$this->module->redirectToPage('order.php', array('step' => '3'));
			}
		}

		// If all else fails, redirect the customer to their OrderHistory
		$this->module->redirectToPage('history.php');
	}
}