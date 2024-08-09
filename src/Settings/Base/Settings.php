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

namespace Emerchantpay\Genesis\Settings\Base;

use Emerchantpay\Genesis\Helpers\Constants\ConfigurationKeys;
use Emerchantpay\Genesis\Helpers\Forms\FormGenerator;
use Emerchantpay\Genesis\Helpers\Forms\SettingsService;
use Genesis\Api\Constants\Banks;
use Genesis\Api\Constants\Transaction\Parameters\ScaExemptions;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Manages settings and forms
 */
abstract class Settings
{
    /**
     * @var \Emerchantpay
     */
    protected $module;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var \Context
     */
    protected $context;

    /**
     * @var FormGenerator
     */
    protected $formFieldsBase;

    /**
     * @var SettingsService
     */
    protected $settingsService;

    /**
     * @param \Emerchantpay $module
     * @param string $identifier
     * @param \Context $context
     */
    public function __construct($module, $identifier, $context)
    {
        $this->module = $module;
        $this->identifier = $identifier;
        $this->context = $context;

        $this->formFieldsBase = new FormGenerator($this->module);
        $this->settingsService = new SettingsService($this->module);
    }

    /**
     * Get the Module Settings HTML code
     *
     * @return string HTML code
     */
    abstract public function getContent();

    /**
     * List of available Bank codes for Online banking
     *
     * @return array
     */
    protected static function getAvailableBankCodes()
    {
        return [
            Banks::CPI => 'Interac Combined Pay-in',
            Banks::BCT => 'Bancontact',
            Banks::BLK => 'BLIK',
            Banks::SE => 'SPEI',
            Banks::PID => 'LatiPay',
        ];
    }

    /**
     * Get form credentials fields (User, Pass, Token ...)
     *
     * @return array
     */
    protected function getFormCredentialsFields()
    {
        return [
            $this->formFieldsBase->inputField(
                'Username',
                'Enter your Username, required for accessing the Genesis Gateway',
                ConfigurationKeys::SETTING_EMERCHANTPAY_USERNAME
            ),
            $this->formFieldsBase->inputField(
                'Password',
                'Enter your Password, required for accessing the Genesis Gateway',
                ConfigurationKeys::SETTING_EMERCHANTPAY_PASSWORD
            ),
            $this->formFieldsBase->selectField(
                'Environment',
                'Select the environment you wish to use for processing your transactions.' . PHP_EOL .
                'Note: Its recommended to use the Sandbox environment every-time you alter ' .
                'your settings, in order to ensure everything works as intended.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_ENVIRONMENT,
                ConfigurationKeys::SETTING_EMERCHANTPAY_ENVIRONMENT,
                $this->formFieldsBase->generateOptionsFromArray([
                    'sandbox' => 'Sandbox',
                    'production' => 'Production',
                ])
            ),
        ];
    }

    /**
     * Return default data
     *
     * @return array[]
     */
    protected function getFormDefaultStructure()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('emerchantpay Configuration'),
                    'icon' => 'icon-cog',
                ],
                'input' => [
                    $this->formFieldsBase->switchField(
                        'Partial Capture',
                        'Use this option to allow / deny Partial Capture Transactions',
                        ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE
                    ),
                    $this->formFieldsBase->switchField(
                        'Partial Refund',
                        'Use this option to allow / deny Partial Refund Transactions',
                        ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND
                    ),
                    $this->formFieldsBase->switchField(
                        'Cancel Transaction',
                        'Use this option to allow / deny Cancel Transactions',
                        ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_VOID
                    ),
                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Generate the Module Settings HTML via HelperForm()
     *
     * @return mixed HTML Content
     */
    protected function generateForm($formStructure, $arrayConfigKeys = null)
    {
        $helper = new \HelperForm();
        // Title and toolbar
        $helper->title = $this->module->displayName;
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = false;
        // Module, token and currentIndex
        $helper->id = (int) \Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->module->name . '&tab_module=' .
            $this->module->tab . '&module_name=' . $this->module->name;

        // Language
        $helper->default_form_language = (int) \Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) \Configuration::get('PS_LANG_DEFAULT');

        $helper->submit_action = 'submit' . $this->module->name;

        $helper->tpl_vars = [
            'id_language' => $this->context->language->id,
            'languages' => $this->context->controller->getLanguages(),
        ];
        if ($arrayConfigKeys) {
            $helper->tpl_vars += [
                'fields_value' => $this->getConfigValues($arrayConfigKeys),
            ];
        }

        return $helper->generateForm(
            [$formStructure]
        );
    }

    /**
     * Returns SCA Exemption options
     *
     * @return array
     */
    protected function getScaExemptionOptions()
    {
        return [
            ScaExemptions::EXEMPTION_LOW_RISK => 'Low risk',
            ScaExemptions::EXEMPTION_LOW_VALUE => 'Low value',
        ];
    }

    /**
     * Get the configuration keys and their respective values
     *
     * @return array Key => Value array
     */
    protected function getConfigValues($arrayConfigKeys = null)
    {
        $config_key_value = [];

        foreach ($this->getConfigKeys() as $config_key) {
            if ($arrayConfigKeys && in_array($config_key, $arrayConfigKeys)) {
                $config_key_value[$config_key . '[]'] = json_decode(\Configuration::get($config_key));
            } else {
                $config_key_value[$config_key] = \Configuration::get($config_key);
            }
        }

        return $config_key_value;
    }

    /**
     * Get Module's configuration fields
     *
     * @return array field keys
     */
    public function getConfigKeys()
    {
        return [
            ConfigurationKeys::SETTING_EMERCHANTPAY_USERNAME,
            ConfigurationKeys::SETTING_EMERCHANTPAY_PASSWORD,
            ConfigurationKeys::SETTING_EMERCHANTPAY_ENVIRONMENT,
            ConfigurationKeys::SETTING_EMERCHANTPAY_IFRAME_ALLOWED,
            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_CAPTURE,
            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_PARTIAL_REFUND,
            ConfigurationKeys::SETTING_EMERCHANTPAY_ALLOW_VOID,
            ConfigurationKeys::SETTING_EMERCHANTPAY_ADD_JQUERY_CHECKOUT,
            ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_ALLOWED,
            ConfigurationKeys::SETTING_EMERCHANTPAY_THREEDS_CHALLENGE_INDICATOR,
            ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION,
            ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT,
        ];
    }

    /**
     * Get the Module Settings HTML code
     *
     * @param array $configKeys
     * @param array $arrayConfigKeys
     * @param array $optionalSettings
     *
     * @return string HTML code
     */
    protected function saveSettings($configKeys, $arrayConfigKeys = null, $optionalSettings = null)
    {
        $output = $this->settingsService->handleSubmit(
            $configKeys,
            $arrayConfigKeys,
            $optionalSettings
        );

        // If $output is empty - everything went fine
        if (empty($output)) {
            $output = $this->module->displayConfirmation($this->module->l('Settings updated'));
        }

        return $output;
    }

    /**
     * Get SCA options form fields
     *
     * @return array
     */
    protected function getFormScaFields()
    {
        return [
            $this->formFieldsBase->selectField(
                'SCA Exemption',
                'Exemption for the Strong Customer Authentication.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION,
                ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION,
                $this->formFieldsBase->generateOptionsFromArray($this->getScaExemptionOptions())
            ),
            $this->formFieldsBase->inputField(
                'Exemption Amount',
                'Exemption Amount determinate if the SCA Exemption should be' .
                ' included in the request to the Gateway.',
                ConfigurationKeys::SETTING_EMERCHANTPAY_SCA_EXEMPTION_AMOUNT
            ),
        ];
    }
}
