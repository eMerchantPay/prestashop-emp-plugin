<?php
/**
 * Copyright (C) 2018-2023 emerchantpay Ltd.
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
 * @copyright   2018-2023 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Emerchantpay\Genesis\Helpers\Constants;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationKeys
{
    /**
     * Configurable module settings
     */
    const SETTING_EMERCHANTPAY_USERNAME = 'EMERCHANTPAY_USERNAME';
    const SETTING_EMERCHANTPAY_PASSWORD = 'EMERCHANTPAY_PASSWORD';
    const SETTING_EMERCHANTPAY_ENVIRONMENT = 'EMERCHANTPAY_ENVIRONMENT';
    const SETTING_EMERCHANTPAY_CHECKOUT = 'EMERCHANTPAY_CHECKOUT';
    const SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES = 'EMERCHANTPAY_CHECKOUT_TRX_TYPES';
    const SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE = 'EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE';
    const SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND = 'EMERCHANTPAY_ALLOW_PARTIAL_REFUND';
    const SETTING_EMERCHANTPAY_ALLOW_VOID = 'EMERCHANTPAY_ALLOW_VOID';
    const SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT = 'EMERCHANTPAY_ADD_JQUERY_CHECKOUT';
    const SETTING_EMERCHANTPAY_WPF_TOKENIZATION = 'EMERCHANTPAY_WPF_TOKENIZATION';
    const SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES = 'EMERCHANTPAY_CHECKOUT_BANK_CODES';
    const SETTING_EMERCHANTPAY_THREEDS_ALLOWED = 'EMERCHANTPAY_THREEDS_ALLOWED';
    const SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR = 'EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR';
    const SETTING_EMERCHANTPAY_SCA_EXEMPTION = 'EMERCHANTPAY_SCA_EXEMPTION';
    const SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT = 'EMERCHANTPAY_SCA_EXEMPTION_AMOUNT';
    const SETTING_EMERCHANTPAY_IFRAME_ALLOWED = 'SETTING_EMERCHANTPAY_IFRAME_ALLOWED';
    const SETTING_EMERCHANTPAY_WEB_PAYMENT_FORM_ID = 'EMERCHANTPAY_WEB_PAYMENT_FORM_ID';
}
