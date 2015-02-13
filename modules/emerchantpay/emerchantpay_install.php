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

class eMerchantPayInstall
{
	private $status = true;

	private $hooks = array(
		'header',
		'payment',
		'paymentTop',
		//'orderConfirmation',
		'adminOrder',
		'cancelProduct',
		'BackOfficeHeader',
		'displayMobileHeader'
	);

	/**
	 * Create the table/tables required by the module
	 */
	public function createSchema()
	{
		$schema = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'emerchantpay_transactions` (
						`id_entry` INT NOT NULL AUTO_INCREMENT,
						`id_unique` varchar(255) NOT NULL,
						`id_parent` varchar(255) NOT NULL,
						`ref_order` varchar(9) NOT NULL,
						`type` varchar(255) NOT NULL,
						`status` varchar(255) NOT NULL,
						`message` varchar(255) NULL,
						`currency` varchar(3) NULL,
						`amount` DECIMAL(10,2) NULL,
						`date_add` DATETIME DEFAULT CURRENT_TIMESTAMP,
						`date_upd` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (`id_entry`)
					) ENGINE=`' . _MYSQL_ENGINE_ . '` DEFAULT CHARSET=utf8;';

		if (!Db::getInstance()->execute($schema)) {
			$this->status = false;
		}
	}

	/**
	 * Register all Hooks required by the module
	 *
	 * @param $instance eMerchantPay
	 *
	 * @throws PrestaShopException
	 */
	public function registerHooks($instance)
	{
		foreach ($this->hooks as $hook) {
			if (!$instance->registerHook($hook)) {
				$this->status = false;
				throw new PrestaShopException('Module Hook (' . $hook . ') can\'t be registered!');
			}
		}
	}

	/**
	 * Delete the table/tables required by the module
	 */
	public function dropSchema()
	{
		$schema = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'emerchantpay_transactions`';

		if (!Db::getInstance()->execute($schema)) {
			$this->status = false;;
		}
	}

	/**
	 * Delete registered hooks
	 *
	 * @param $instance eMerchantPay
	 *
	 * @throws PrestaShopException
	 */
	public function dropHooks($instance)
	{
		foreach ($this->hooks as $hook) {
			if (!$instance->unregisterHook($hook)) {
				$this->status = false;
				throw new PrestaShopException('Module Hook (' . $hook . ') can\'t be unregistered!');
			}
		}
	}

	/**
	 * Delete module configuration
	 */
	public function dropKeys($instance)
	{
		foreach ($instance->getConfigKeys() as $key) {
			if (!Configuration::deleteByName($key)) {
				$this->status = false;
			}
		}
	}

	/**
	 * Return the status of all processed methods
	 * @return bool
	 */
	public function isSuccessful() {
		return $this->status;
	}
}