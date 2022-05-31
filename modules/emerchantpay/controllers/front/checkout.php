<?php
/**
 * Copyright (C) 2018 emerchantpay Ltd.
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
 * @author      emerchantpay
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayCheckoutModuleFrontController
 *
 * Checkout Front-End Controller
 */
class EmerchantpayCheckoutModuleFrontController extends ModuleFrontController
{
    /** @var emerchantpay */
    public $module;

    // Hide the left column
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->page_name = $this->module->l('emerchantpay Checkout');

        $this->initCheckout();
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

            if (!$cart->getOrderTotal(true, $cart::BOTH)) {
                $this->module->redirectToPage('order.php');
            }

            if (version_compare(_PS_VERSION_, '1.6', '<')) {
                $this->context->controller->addCSS(
                    $this->module->getPathUri() . 'views/css/bootstrap-custom.min.css', 'all'
                );
                $this->context->controller->addJS(
                    $this->module->getPathUri() . 'views/js/bootstrap/bootstrap.min.js'
                );
            }

            $this->context->controller->addCSS(
                $this->module->getPathUri() . 'views/css/card.min.css', 'all'
            );
            $this->context->controller->addJS(
                $this->module->getPathUri() . 'views/js/card/card.min.js'
            );

            $this->context->smarty->append(
                'emerchantpay',
                [
                    'checkout' => [
                        'product_count' => $cart->nbProducts(),
                        'currency'      => $cart->id_currency,
                        'total'         => $cart->getOrderTotal(true, $cart::BOTH),
                        'isoCode'       => $this->context->language->iso_code,
                        'error'         => $this->module->getSessVar('error_checkout'),
                        'links'         => [
                            'back'    => $this->context->link->getPageLink('order', true, null, "step=3"),
                            'confirm' => $this->context->link->getModuleLink($this->module->name, 'validation'),
                        ]
                    ]
                ],
                true
            );

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->setTemplate('checkout.tpl');
            } else {
                $this->setTemplate('module:emerchantpay/views/templates/front/checkoutpage.tpl');
            }
        }
    }
}
