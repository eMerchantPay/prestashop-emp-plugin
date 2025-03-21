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

namespace Emerchantpay\Genesis;

use Emerchantpay\Genesis\Helpers\DbHelper;
use Emerchantpay\Genesis\Settings\Base\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayInstall
 *
 * Perform module installation/un-installation
 */
class EmerchantpayInstall
{
    private $status = true;

    private $hooks = [
        'displayOrderConfirmation',
        'adminOrder',
        /*
         * Hooks for 1.7.x
         */
        'displayAdminOrder',
        'displayOrderDetail',
        'paymentOptions',
        'displayBackOfficeHeader',
    ];

    /**
     * Create the tables required by the module
     */
    public function createSchema()
    {
        $this->createTransactionsSchema();
        $this->createConsumersSchema();
    }

    /**
     * Creates transactions table
     *
     * @throws \PrestaShopException
     */
    protected function createTransactionsSchema()
    {
        $schema = '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'emerchantpay_transactions`
              (
                 `id_entry`       INT NOT NULL auto_increment,
                 `id_unique`      VARCHAR(255) NOT NULL,
                 `id_parent`      VARCHAR(255) NOT NULL,
                 `ref_order`      VARCHAR(9) NOT NULL,
                 `transaction_id` VARCHAR(255) NULL,
                 `type`           VARCHAR(255) NOT NULL,
                 `status`         VARCHAR(255) NOT NULL,
                 `message`        VARCHAR(255) NULL,
                 `currency`       VARCHAR(3) NULL,
                 `amount`         DECIMAL(10, 2) NULL,
                 `terminal`       VARCHAR(255) NULL,
                 `date_add`       DATETIME DEFAULT NULL,
                 `date_upd`       TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                 PRIMARY KEY (`id_entry`)
              )
            engine=`' . _MYSQL_ENGINE_ . '`
            DEFAULT charset=utf8;';

        if (!\Db::getInstance()->execute($schema)) {
            $this->status = false;
            throw new \PrestaShopException('Module Install: Unable to create MySQL Database');
        }
    }

    /**
     * Creates consumers table
     *
     * @throws \PrestaShopException
     */
    protected function createConsumersSchema()
    {
        if (!\Db::getInstance()->execute(static::getCreateConsumersSchemaQuery())) {
            $this->status = false;
            throw new \PrestaShopException('Module Install: Unable to create MySQL Database');
        }
    }

    /**
     * @return string
     */
    protected static function getCreateConsumersSchemaQuery()
    {
        return 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'emerchantpay_consumers`
            (
              `id`                int(10) unsigned NOT NULL AUTO_INCREMENT,
              `merchant_username` varchar(40) NOT NULL,
              `customer_email`    varchar(255) NOT NULL,
              `consumer_id`       int(10) unsigned NOT NULL,
              `date_add`          DATETIME DEFAULT NULL,
              `date_upd`          TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `merchant_consumer` (`merchant_username`, `customer_email`)
            ) ENGINE=`' . _MYSQL_ENGINE_ . '`
            DEFAULT charset=utf8;';
    }

    /**
     * Updates the scheme, if a new version of the module is directly copied in the root folder
     * without uninstalling the old one and installing the new one
     */
    public static function doProcessSchemaUpdate()
    {
        static::updateTransactionsSchema();
        static::updateConsumersSchema();
    }

    /**
     * Update transactions table to add transaction_id field
     */
    protected static function updateTransactionsSchema()
    {
        $table = _DB_PREFIX_ . 'emerchantpay_transactions';

        if (!DbHelper::isTableExists($table)) {
            return;
        }

        if (!DbHelper::isColumnExists($table, 'transaction_id')) {
            \Db::getInstance()->execute(
                'ALTER TABLE `' . $table . '` ADD `transaction_id` VARCHAR(255) NOT NULL AFTER `ref_order`'
            );
        }
    }

    /**
     * Create consumers table if it doesn't exist
     */
    protected static function updateConsumersSchema()
    {
        $table = _DB_PREFIX_ . 'emerchantpay_consumers';

        if (!DbHelper::isTableExists($table)) {
            \Db::getInstance()->Execute(static::getCreateConsumersSchemaQuery());
        }
    }

    /**
     * Register all Hooks required by the module
     *
     * @param \Emerchantpay $instance
     *
     * @throws \PrestaShopException
     */
    public function registerHooks($instance)
    {
        foreach ($this->hooks as $hook) {
            if (!$instance->registerHook($hook)) {
                $this->status = false;
                throw new \PrestaShopException('Module Install: Hook (' . $hook . ') can\'t be registered!');
            }
        }
    }

    /**
     * Delete the table/tables required by the module
     */
    public function dropSchema()
    {
        $this->dropTransactionsSchema();
        $this->dropConsumersSchema();
    }

    /**
     * Drops transactions table
     *
     * @throws \PrestaShopException
     */
    protected function dropTransactionsSchema()
    {
        $schema = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'emerchantpay_transactions`';

        if (!\Db::getInstance()->execute($schema)) {
            $this->status = false;
            throw new \PrestaShopException('Module Uninstall: Unable to DROP transactions table!');
        }
    }

    /**
     * Drops transactions table
     *
     * @throws \PrestaShopException
     */
    protected function dropConsumersSchema()
    {
        $schema = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'emerchantpay_consumers`';

        if (!\Db::getInstance()->execute($schema)) {
            $this->status = false;
            throw new \PrestaShopException('Module Uninstall: Unable to DROP consumers table!');
        }
    }

    /**
     * Delete registered hooks
     *
     * @param \Emerchantpay $instance
     *
     * @throws \PrestaShopException
     */
    public function dropHooks($instance)
    {
        foreach ($this->hooks as $hook) {
            if (!$instance->unregisterHook($hook)) {
                $this->status = false;
                throw new \PrestaShopException('Module Uninstall: Hook (' . $hook . ') can\'t be unregistered!');
            }
        }
    }

    /**
     * Delete module configuration
     *
     * @param Settings $instance
     *
     * @throws \PrestaShopException
     */
    public function dropKeys($instance)
    {
        foreach ($instance->getConfigKeys() as $key) {
            if (!\Configuration::deleteByName($key)) {
                $this->status = false;
                throw new \PrestaShopException('Module Uninstall: Unable to remove configuration keys');
            }
        }
    }

    /**
     * Return the status of all processed methods
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->status;
    }
}
