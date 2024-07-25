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

namespace Emerchantpay\Genesis\Settings\Checkout;

use Emerchantpay\Genesis\EmerchantpayThreeds;
use Emerchantpay\Genesis\Helpers\Constants\ConfigurationKeys;
use Emerchantpay\Genesis\Settings\Base\Settings;
use Genesis\Api\Constants\Payment\Methods;
use Genesis\Api\Constants\Transaction\Names;
use Genesis\Api\Constants\Transaction\Types;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Manages settings and forms
 */
class CheckoutSettings extends Settings
{
    /**
     * Transaction Type specifics
     */
    const GOOGLE_PAY_TRANSACTION_PREFIX = 'google_pay_';
    const GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE = 'authorize';
    const GOOGLE_PAY_PAYMENT_TYPE_SALE = 'sale';
    const PAYPAL_TRANSACTION_PREFIX = 'pay_pal_';
    const PAYPAL_PAYMENT_TYPE_AUTHORIZE = 'authorize';
    const PAYPAL_PAYMENT_TYPE_SALE = 'sale';
    const PAYPAL_PAYMENT_TYPE_EXPRESS = 'express';
    const APPLE_PAY_TRANSACTION_PREFIX = 'apple_pay_';
    const APPLE_PAY_PAYMENT_TYPE_AUTHORIZE = 'authorize';
    const APPLE_PAY_PAYMENT_TYPE_SALE = 'sale';

    /**
     * @param \Emerchantpay $module
     * @param string $identifier
     * @param \Context $context
     */
    public function __construct($module, $identifier, $context)
    {
        parent::__construct($module, $identifier, $context);
    }

    /**
     * Get the Module Settings HTML code
     *
     * @return string HTML code
     */
    public function getContent()
    {
        $optionalSettings = [ConfigurationKeys::SETTING_EMERCHANTPAY_WEB_PAYMENT_FORM_ID];
        $output = '';

        if (\Tools::isSubmit('submit' . $this->module->name)) {
            $output = $this->saveSettings(
                $this->getConfigKeys(),
                $this->getArrayConfigKeys(),
                $optionalSettings
            );
        }

        return $output . $this->displayForm();
    }

    /**
     * Prepares default values for some configuration keys
     *
     * @return bool
     */
    public function setDefaultSettingsToDB()
    {
        $defaultConfigItems = [
            ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT => '0',
            ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES => [
                Types::AUTHORIZE,
                Types::SALE,
            ],
            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE => '1',
            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND => '1',
            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_VOID => '1',
            ConfigurationKeys::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT => '1',
            ConfigurationKeys::SETTING_EMERCHANTPAY_WPF_TOKENIZATION => '0',
            ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES => [],
            ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_ALLOWED => '1',
            ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION => 'low_risk',
            ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT => '100',
            ConfigurationKeys::SETTING_EMERCHANTPAY_IFRAME_ALLOWED => '0',
        ];

        return $this->settingsService->saveSettings($defaultConfigItems);
    }

    /**
     * Returns list of the WPF supported transaction types
     *
     * @return array
     */
    protected function getSupportedWpfTransactionTypes()
    {
        $data = [];

        $transactionTypes = Types::getWPFTransactionTypes();
        $excludedTypes = [
            Types::INIT_RECURRING_SALE,
            Types::INIT_RECURRING_SALE_3D,
            Types::SDD_INIT_RECURRING_SALE,
            Types::PPRO,
            Types::GOOGLE_PAY,
            Types::PAY_PAL,
            Types::APPLE_PAY,
        ];

        $transactionTypes = array_diff($transactionTypes, $excludedTypes);

        // Add Google Pay Transaction Methods
        $googlePayMethods = array_map(
            function ($type) {
                return self::GOOGLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                self::GOOGLE_PAY_PAYMENT_TYPE_SALE,
            ]
        );

        // Add PayPal Transaction Methods
        $payPalMethods = array_map(
            function ($type) {
                return self::PAYPAL_TRANSACTION_PREFIX . $type;
            },
            [
                self::PAYPAL_PAYMENT_TYPE_AUTHORIZE,
                self::PAYPAL_PAYMENT_TYPE_SALE,
                self::PAYPAL_PAYMENT_TYPE_EXPRESS,
            ]
        );

        // Add Apple Pay Transaction Methods
        $applePayMethods = array_map(
            function ($type) {
                return self::APPLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                self::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                self::APPLE_PAY_PAYMENT_TYPE_SALE,
            ]
        );

        $transactionTypes = array_merge(
            $transactionTypes,
            $googlePayMethods,
            $payPalMethods,
            $applePayMethods
        );
        asort($transactionTypes);

        foreach ($transactionTypes as $type) {
            $name = Names::getName($type);
            if (!Types::isValidTransactionType($type)) {
                $name = \Tools::strtoupper($type);
            }

            $data[$type] = $this->module->l($name);
        }

        return $data;
    }

    /**
     * Generate the Module Settings HTML via HelperForm()
     *
     * @return mixed HTML Content
     */
    protected function displayForm()
    {
        $formStructure = $this->getFormDefaultStructure();

        /* Add form fields */
        $formStructure['form']['input'] = array_merge(
            $this->getFormEnablePaymentFields(),
            $this->getFormCredentialsFields(),
            $this->getFormIframeOptionFields(),
            $this->getFormTransactionTypesFields(),
            $this->getFormBankCodesFields(),
            $this->getFormWpfTokenizationFields(),
            $this->getFormThreedsFields(),
            $this->getFormScaFields(),
            $formStructure['form']['input']
        );

        /*
         * Option for registering jQuery to Checkout Page
         *
         * Note: 1.7.x does not register jQuery on the Checkout Page, so we are adding this option
         * in order to be disabled if jQuery has been added from other module
         */
        if ($this->module->isPrestaVersion17()) {
            $formStructure['form']['input'][] = $this->formFieldsBase->switchField(
                'Include jQuery Plugin to Checkout Page',
                'Use this option to allow / deny jQuery Plugin Registration. This option should be enabled' .
                    ' unless jQuery has already been registered.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT
            );
        }

        return $this->generateForm($formStructure, $this->getArrayConfigKeys());
    }

    /**
     * Get Module's configuration fields
     *
     * @return array field keys
     */
    public function getConfigKeys()
    {
        $configKeys = parent::getConfigKeys();

        return array_merge(
            $configKeys,
            [
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT,
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
                ConfigurationKeys::SETTING_EMERCHANTPAY_WPF_TOKENIZATION,
                ConfigurationKeys::SETTING_EMERCHANTPAY_WEB_PAYMENT_FORM_ID,
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
            ]
        );
    }

    /**
     * Get list of the array config keys
     *
     * @return array
     */
    private function getArrayConfigKeys()
    {
        return [
            ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
            ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
        ];
    }

    /**
     * Get enable checkout transaction form fields
     *
     * @return array
     */
    private function getFormEnablePaymentFields()
    {
        return [
            $this->formFieldsBase->switchField(
                'Checkout (Remote) Payment Method',
                'Enable/Disable the Checkout payment method - receive credit-card payments, without' .
                'the need of PCI-DSS certificate or HTTPS.' . PHP_EOL . 'Note: Upon checkout, the customer will be' .
                ' redirected to a secure payment form, located on our servers and we will notify you,' .
                ' once the payment reached a final status',
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT
            ),
        ];
    }

    /**
     * Get form option for iframe processing
     *
     * @return array[]
     */
    private function getFormIframeOptionFields()
    {
        return [
            $this->formFieldsBase->switchField(
                'Enable payment processing into an iframe',
                'Enable payment processing into an iframe by removing the redirects to the' .
                ' Gateway Web Payment Form Page. The iFrame processing requires a specific' .
                'setting inside Merchant Console. For more info ask:  tech-support@emerchantpay.com',
                ConfigurationKeys::SETTING_EMERCHANTPAY_IFRAME_ALLOWED
            ),
        ];
    }

    /**
     * Get form with transaction types fields
     *
     * @return array
     */
    private function getFormTransactionTypesFields()
    {
        return [
            $this->formFieldsBase->selectField(
                'Checkout Transaction Types',
                'Select the transaction types you want to use during Checkout session.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES,
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_TRX_TYPES . '[]',
                $this->formFieldsBase->generateOptionsFromArray(self::getSupportedWpfTransactionTypes()),
                true
            ),
        ];
    }

    /**
     * Get form with bank codes fields
     *
     * @return array
     */
    private function getFormBankCodesFields()
    {
        return [
            $this->formFieldsBase->selectField(
                'Checkout Bank codes for Online banking',
                'Select Bank code(s) to use with Online banking transaction type.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES,
                ConfigurationKeys::SETTING_EMERCHANTPAY_CHECKOUT_BANK_CODES . '[]',
                $this->formFieldsBase->generateOptionsFromArray(self::getAvailableBankCodes()),
                true
            ),
        ];
    }

    /**
     * Get form with WPF Tokenization fields
     *
     * @return array
     */
    private function getFormWpfTokenizationFields()
    {
        return [
            $this->formFieldsBase->switchField(
                'WPF Tokenization',
                'Enable/Disable tokenization for Web Payment Form. Guest checkout has to be ' .
                'disabled when tokenization is enabled',
                ConfigurationKeys::SETTING_EMERCHANTPAY_WPF_TOKENIZATION
            ),
            $this->formFieldsBase->inputField(
                'Web payment form unique ID',
                'The unique ID of the the web payment form configuration to be displayed for the current payment.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_WEB_PAYMENT_FORM_ID
            ),
        ];
    }

    /**
     * Get form 3DS fields
     *
     * @return array
     */
    private function getFormThreedsFields()
    {
        return [
            $this->formFieldsBase->switchField(
                'Enable 3DSv2',
                'Enable 3DSv2 optional parameters',
                ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_ALLOWED
            ),
            $this->formFieldsBase->selectField(
                '3DSv2 Challenge',
                'The value has weight and might impact the decision whether a' .
                ' challenge will be required for the transaction or not.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR,
                ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR,
                $this->formFieldsBase->generateOptionsFromArray(EmerchantpayThreeds::getChallengeIndicators())
            ),
        ];
    }
}
