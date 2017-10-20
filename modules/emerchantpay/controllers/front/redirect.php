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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class eMerchantPayRedirectModuleFrontController
 *
 * Redirection Front-End Controller
 */
class eMerchantPayRedirectModuleFrontController extends ModuleFrontController
{
	/** @var  eMerchantPay */
	public $module;
	/** @var  ContextCore  */
	protected $context;
    /** @var array  */
    protected $statuses = array('success', 'failure', 'cancel');
    /** @var array  */
    protected $actionsToRestoreCart = array('failure', 'cancel');

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

        if ($this->shouldRestoreCustomerCart()) {
            $this->restoreCustomerCart();
        }

        if (!in_array(Tools::getValue('action'), $this->statuses)) {
            $this->module->redirectToPage('history.php');
        }

		$this->context->smarty->append(
            'emerchantpay',
			array(
                'redirect'  => array(
                    'status' => Tools::getValue('action'),
                    'url'    => array(
                        'order'     => $this->getOrderUrl(),
                        'history'   => $this->context->link->getPageLink('history.php'),
                        'restore'   => $this->context->link->getModuleLink(
                            $this->module->name, 'redirect', array('restore' => 'cart')
                        ),
                        'support'   => $this->context->link->getPageLink('contact.php'),
                    )
                )
			),
            true
		);

		if (version_compare(_PS_VERSION_, '1.7', '<')) {
			$this->setTemplate('redirect.tpl');
		} else {
			$this->setTemplate('module:emerchantpay/views/templates/front/redirectpage.tpl');
		}
	}

    /**
     * Checks if cart should be restored.
     *
     * @return bool
     */
    protected function shouldRestoreCustomerCart()
    {
        return Tools::getValue('restore') === 'cart' ||
               in_array(
                   Tools::getValue( 'action' ),
                   $this->actionsToRestoreCart
               );
    }

    /**
     * @return string
     */
    protected function getOrderUrl()
    {
        return Configuration::get('PS_ORDER_PROCESS_TYPE') == PS_ORDER_PROCESS_OPC ?
            $this->context->link->getPageLink('order-opc.php', array('step' => '3')) :
            $this->context->link->getPageLink('order.php', array('step' => '3'));
    }

    /**
     * Restore customer's cart
     *
     * @return void
     */
    protected function restoreCustomerCart()
    {
        $order = Order::getCustomerOrders($this->context->customer->id, false, $this->context);
        $order = reset($order);

        $duplication = $this->getCart(
            $order['id_order']
        )->duplicate();

        if ($duplication && Validate::isLoadedObject($duplication['cart']))
        {
            $this->context->cart = $duplication['cart'];
            $this->context->cookie->id_cart = $duplication['cart']->id;
            $this->context->cookie->write();
        }
    }

    /**
     * @param int $orderId
     *
     * @return Cart
     */
    protected function getCart($orderId)
    {
        return new Cart(
            (int) Order::getCartIdStatic(
                $orderId,
                $this->context->customer->id
            )
        );
    }
}