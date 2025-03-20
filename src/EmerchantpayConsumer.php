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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayConsumer
 */
final class EmerchantpayConsumer extends \ObjectModel
{
    public $merchant_username;
    public $customer_email;
    public $consumer_id;
    public $date_add;
    public $date_upd;

    /**
     * @see \ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'emerchantpay_consumers',
        'primary' => 'id',
        'fields' => [
            'merchant_username' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 40,
            ],
            'customer_email' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isEmail',
                'required' => true,
            ],
            'consumer_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Add consumer
     *
     * @param bool $autodate set autodate without explicit declaration?
     * @param bool $nullValues accept nulls?
     *
     * @return bool
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function add($autodate = true, $nullValues = false)
    {
        if (parent::add($autodate, $nullValues)) {
            \Hook::exec('actionEmerchantPayAddConsumer', ['emerchantpayAddConsumer' => $this]);

            return true;
        }

        return false;
    }

    /**
     * @param $merchantUsername
     * @param $customerEmail
     *
     * @return int|null
     *
     * @throws \PrestaShopException
     */
    public static function getConsumerId($merchantUsername, $customerEmail)
    {
        /** @var \PrestaShopCollectionCore $result */
        $result = new \PrestaShopCollection(self::class);
        $result->where('merchant_username', '=', $merchantUsername);
        $result->where('customer_email', '=', $customerEmail);

        $consumer = $result->getFirst();

        return !empty($consumer->consumer_id) ? $consumer->consumer_id : null;
    }

    /**
     * @param $merchantUsername
     * @param $customerEmail
     * @param $consumerId
     *
     * @return bool
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function createConsumer($merchantUsername, $customerEmail, $consumerId)
    {
        if (self::getConsumerId($merchantUsername, $customerEmail)) {
            return true;
        }

        $consumer = new static();

        $consumer->merchant_username = $merchantUsername;
        $consumer->customer_email = $customerEmail;
        $consumer->consumer_id = $consumerId;

        return $consumer->add();
    }
}
