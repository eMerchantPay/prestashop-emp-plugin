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

use Genesis\Exceptions\InvalidArgument;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayValidationModuleFrontController
 *
 * Validation Front-End Controller
 */
class EmerchantpayValidationModuleFrontController extends ModuleFrontController
{
    /** @var emerchantpay */
    public $module;

    /**
     * @see FrontController::initContent()
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
        }
    }

    /**
     * Validate checkout process
     *
     * @return void
     *
     * @throws InvalidArgument
     */
    public function validateCheckout()
    {
        // Is Checkout allowed?
        if (!$this->module->isCheckoutPaymentMethodAvailable()) {
            $this->module->redirectToPage('order.php', ['step' => 3]);
        }

        // Send transaction
        $url = $this->module->doCheckout();

        if (!isset($url)) {
            $url = $this->module->getPageLink(
                'order.php',
                [
                    'step' => 3,
                    'select_payment_option' => Tools::getValue('select_payment_option'),
                ]
            );
            $url = $this->module->isIframeEnabled() ? $this->module->getIframeControllerUrl($url) : $url;
        }

        if ($this->module->isIframeEnabled()) {
            $this->ajaxRenderWrapper(json_encode(['redirect' => $url]));
        }

        Tools::redirect($url);
    }

    /**
     * Check if ajaxRender method exists
     *
     * @param string $json
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function ajaxRenderWrapper($json)
    {
        if (!method_exists(get_parent_class($this), 'ajaxRender')) {
            $this->ajaxDie($json);
        }

        $this->ajaxRender($json);

        exit;
    }
}
