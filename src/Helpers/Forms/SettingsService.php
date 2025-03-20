<?php
/**
 * Copyright (C) 2015-2025 emerchantpay Ltd.
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
 * @copyright   2015-2025 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Emerchantpay\Genesis\Helpers\Forms;

use PrestaShopLogger as Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SettingsService
{
    /**
     * @var \PaymentModule
     */
    private $module;

    /**
     * @param \PaymentModule $module
     */
    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Check and save module settings to DB
     *
     * @param array $configKeys All config keys
     * @param array $arrayConfigKeys Config keys used to have multiple values
     * @param array $optionalSettings
     *
     * @return mixed
     */
    public function handleSubmit($configKeys, $arrayConfigKeys, $optionalSettings = null)
    {
        $output = '';

        foreach ($configKeys as $key) {
            $value = $this->getConfigValue($key, $arrayConfigKeys);

            switch (true) {
                case !\Validate::isConfigName($key):
                    $message = $this->module->l('Invalid config name: %s');
                    $output = $this->module->displayError(sprintf($message, $key));
                    break;

                case is_string($value) && strlen($value) === 0 && $optionalSettings
                && !in_array($key, $optionalSettings):
                    $message = $this->module->l('Invalid content for: %s');
                    $output = $this->module->displayError(sprintf($message, $key));
                    break;

                default:
                    \Configuration::updateValue($key, $value);
                    break;
            }
        }

        return $output;
    }

    /**
     * Save settings to the DB
     * Use json_encode if the value is an array to store as string
     *
     * @param array $configItems
     *
     * @return bool
     */
    public function saveSettings($configItems)
    {
        try {
            foreach ($configItems as $key => $value) {
                $value = is_array($value) ? json_encode($value) : $value;
                \Configuration::updateValue($key, $value);
            }

            return true;
        } catch (\Exception $e) {
            if (class_exists('PrestaShopLogger')) {
                Logger::addLog($e->getMessage(), 4);
            }

            return false;
        }
    }

    /**
     * Get config value and encode to json if the setting has multiple values
     * /is array/
     *
     * @param string $key
     * @param array $arrayConfigKeys
     *
     * @return false|string
     */
    private static function getConfigValue($key, $arrayConfigKeys)
    {
        $value = \Tools::getValue($key);

        if ($arrayConfigKeys && in_array($key, $arrayConfigKeys)) {
            $value = json_encode($value);
        }

        return $value;
    }
}
