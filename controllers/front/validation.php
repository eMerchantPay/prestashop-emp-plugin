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
 * Class EmerchantpayValidationModuleFrontController
 *
 * Validation Front-End Controller
 */
class EmerchantpayValidationModuleFrontController extends ModuleFrontControllerCore
{
    /** @var emerchantpay */
    public $module;

    /**
     * @see FrontControlwler::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        if ('POST' != $_SERVER['REQUEST_METHOD']) {
            $this->module->redirectToPage('order.php', ['step' => 3]);
        }

        if (!$this->module->checkCurrency($this->context->cart)) {
            $this->module->redirectToPage('order.php', ['step' => 3]);
        }

        if (!$this->module->isAvailable()) {
            $this->module->redirectToPage('order.php', ['step' => 3]);
        }

        if (Tools::getIsset('submit' . $this->module->name . 'Checkout')) {
            $this->validateCheckout();
        } elseif (Tools::getIsset('submit' . $this->module->name . 'Direct')) {
            $this->validateDirect();
        }
    }

    public function validateCheckout()
    {
        // Is Checkout allowed?
        if (!$this->module->isCheckoutPaymentMethodAvailable()) {
            $this->module->redirectToPage('order.php', ['step' => 3]);
        }

        // Send transaction
        $url = $this->module->doCheckout();

        if (isset($url)) {
            Tools::redirect($url);
        } else {
            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                Tools::redirect(
                    $this->context->link->getModuleLink($this->module->name, 'checkout')
                );
            } else {
                $this->module->redirectToPage(
                    'order.php',
                    [
                        'step' => 3,
                        'select_payment_option' => Tools::getValue('select_payment_option'),
                    ]
                );
            }
        }
    }

    public function validateDirect()
    {
        // Is standard method allowed?
        if (!$this->module->isDirectPaymentMethodAvailable()) {
            $this->module->redirectToPage('order.php', ['step' => 3]);
        }

        // Is everything required filled in?
        if (!$this->isRequiredFilled()) {
            $this->module->setSessVar(
                'error_direct',
                $this->module->l('Please fill all of the required fields!')
            );

            $this->module->redirectToPage(
                'order.php',
                [
                    'step' => '3',
                    'select_payment_option' => Tools::getValue('select_payment_option'),
                ]
            );
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
        return Tools::getIsset($this->module->name . '-cvc') &&
               !empty(Tools::getValue($this->module->name . '-cvc')) &&
               Tools::getIsset($this->module->name . '-name') &&
               !empty(Tools::getValue($this->module->name . '-name')) &&
               Tools::getIsset($this->module->name . '-number') &&
               !empty(Tools::getValue($this->module->name . '-number')) &&
               Tools::getIsset($this->module->name . '-expiry') &&
               !empty(Tools::getValue($this->module->name . '-expiry'));
    }
}
