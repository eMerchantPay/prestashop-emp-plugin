<?php
/**
 * Copyright (C) 2015-2024 emerchantpay Ltd.
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
 * @copyright   2015-2024 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayFrameModuleFrontController
 *
 * Break frames jail Controller
 */
class EmerchantpayFrameModuleFrontController extends ModuleFrontController
{
    /**
     * Used to break iframe and redirect to the url
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $url = rawurldecode(Tools::getValue('url'));

        $this->context->smarty->assign('url', $this->sanitizeRedirectUrl($url), true);
        $this->setTemplate('module:emerchantpay/views/templates/front/redirect-helper.tpl');
    }

    /**
     * Check for malicious redirects
     *
     * @return string
     */
    private function sanitizeRedirectUrl($url)
    {
        $shopUrl = Tools::getShopDomainSsl(true);
        $parsedShopUrl = parse_url($shopUrl);
        $parsedUrl = parse_url($url);

        return ($parsedUrl && isset($parsedUrl['host']) && $parsedUrl['host'] === $parsedShopUrl['host']) ?
            $url :
            $shopUrl;
    }
}
