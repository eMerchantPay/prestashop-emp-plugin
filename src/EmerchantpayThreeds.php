<?php
/**
 * Copyright (C) 2022 emerchantpay Ltd.
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
 * @copyright   2022 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Emerchantpay\Genesis;

use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\PasswordChangeIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\RegistrationIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\ShippingAddressUsageIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\UpdateIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\Control\ChallengeIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\ReorderItemIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\ShippingIndicators;
use Genesis\Utils\Common as CommonUtils;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Threeds helper class
 *
 * @suppressWarnings(PHPMD.LongVariable)
 */
class EmerchantpayThreeds
{
    /**
     * PrestaShop date format
     */
    public const PRESTASHOP_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Indicator value constants
     */
    public const CURRENT_TRANSACTION_INDICATOR = 'current_transaction';
    public const LESS_THAN_30_DAYS_INDICATOR = 'less_than_30_days';
    public const MORE_THAN_30_LESS_THAN_60_INDICATOR = 'more_30_less_60_days';
    public const MORE_THAN_60_DAYS_INDICATOR = 'more_than_60_days';

    /**
     * Activity periods
     */
    public const ACTIVITY_24_HOURS = 'PT24H';
    public const ACTIVITY_6_MONTHS = 'P6M';
    public const ACTIVITY_1_YEAR = 'P1Y';

    /**
     * @var array Order statuses where the order is already paid
     */
    private static $paidStatuses = [2, 4, 5, 9, 11];

    /**
     * List of available challenge options
     *
     * @return array
     */
    public static function getChallengeIndicators()
    {
        return [
            ChallengeIndicators::NO_PREFERENCE => 'No preference',
            ChallengeIndicators::NO_CHALLENGE_REQUESTED => 'No challenge requested',
            ChallengeIndicators::PREFERENCE => 'Preference',
            ChallengeIndicators::MANDATE => 'Mandate',
        ];
    }

    /**
     * Get shipping indicator
     *
     * @param \Cart $cart
     * @param \Address $invoice
     * @param \Address $shipping
     * @param bool $isGuest
     *
     * @return string
     */
    public static function getShippingIndicator($cart, $invoice, $shipping, $isGuest)
    {
        $indicator = ShippingIndicators::OTHER;

        if ($cart->isVirtualCart()) {
            return ShippingIndicators::DIGITAL_GOODS;
        }

        if (!$isGuest) {
            $indicator = ShippingIndicators::STORED_ADDRESS;

            if ($cart->id_address_invoice === $cart->id_address_delivery
                && self::areAddressesSame($invoice, $shipping)) {
                $indicator = ShippingIndicators::SAME_AS_BILLING;
            }
        }

        return $indicator;
    }

    /**
     * Get Reorder items indicator, according if this is first order or item is reordered
     *
     * @param \Cart $cart
     * @param \Customer $customer
     * @param bool $isGuest
     *
     * @return string
     */
    public static function getReorderItemsIndicator($cart, $customer, $isGuest)
    {
        if ($isGuest) {
            return ReorderItemIndicators::FIRST_TIME;
        }

        $products = $cart->getProducts();
        $boughtProducts = $customer->getBoughtProducts();

        foreach ($products as $product) {
            if (in_array($product['id_product'], array_column($boughtProducts, 'product_id'))) {
                return ReorderItemIndicators::REORDERED;
            }
        }

        return ReorderItemIndicators::FIRST_TIME;
    }

    /**
     * Get customer latest update indicator
     *
     * @param \Customer $customer
     * @param int $idLang
     *
     * @return string
     */
    public static function getUpdateIndicator($customer, $idLang)
    {
        $indicatorClass = UpdateIndicators::class;
        $dateToCheck = self::findLastChangeDate($customer, $idLang);

        return self::getIndicatorValue($dateToCheck, $indicatorClass);
    }

    /**
     * Get last password change indicator
     *
     * @param \Customer $customer
     *
     * @return string
     */
    public static function getPasswordIndicator($customer)
    {
        $indicatorClass = PasswordChangeIndicators::class;
        $dateToCheck = $customer->last_passwd_gen;

        return self::getIndicatorValue($dateToCheck, $indicatorClass);
    }

    /**
     * Find last changes date - address or customer account
     *
     * @param \Customer $customer
     * @param int $idLang
     *
     * @return string
     */
    public static function findLastChangeDate($customer, $idLang)
    {
        $customerUpdated = $customer->date_upd;
        $customerAddresses = self::getSortedCustomerData($customer->getAddresses($idLang));

        $lastChangeDate = $customerUpdated;

        if (CommonUtils::isValidArray($customerAddresses)) {
            $customerLastUpdatedAddress = $customerAddresses[0];

            $customerLastUpdatedAddressDate = \DateTime::createFromFormat(
                self::PRESTASHOP_DATETIME_FORMAT,
                $customerLastUpdatedAddress['date_upd']
            );
            $customerUpdatedDate = \DateTime::createFromFormat(
                self::PRESTASHOP_DATETIME_FORMAT,
                $customerUpdated
            );

            if ($customerLastUpdatedAddressDate > $customerUpdatedDate) {
                $lastChangeDate = $customerLastUpdatedAddress['date_upd'];
            }
        }

        return $lastChangeDate;
    }

    /**
     * Get Payment account firs time usage indicator
     *
     * @param \Customer $customer
     *
     * @return string
     */
    public static function getRegistrationIndicator($customer)
    {
        $indicatorClass = RegistrationIndicators::class;
        $customerFirstOrderDate = self::findFirstCustomerOrderDate($customer);

        return self::getIndicatorValue($customerFirstOrderDate, $indicatorClass);
    }

    /**
     * Find date when the customer made first order
     *
     * @param \Customer $customer
     *
     * @return string
     */
    public static function findFirstCustomerOrderDate($customer)
    {
        $orders = self::getSortedCustomerData(\Order::getCustomerOrders($customer->id), SORT_ASC);
        $orderDate = (new \DateTime())->format(self::PRESTASHOP_DATETIME_FORMAT);

        if (CommonUtils::isValidArray($orders)) {
            $orderDate = $orders[0]['date_add'];
        }

        return $orderDate;
    }

    /**
     * Iterate orders to find the first date when Shipping address is used
     *
     * @param \Customer $customer
     * @param \Cart $cart
     *
     * @return string
     */
    public static function findShippingAddressDateFirstUsed($customer, $cart)
    {
        $orders = self::getSortedCustomerData(\Order::getCustomerOrders($customer->id), SORT_ASC);

        foreach ($orders as $order) {
            if ($order['id_address_delivery'] === $cart->id_address_delivery) {
                return $order['date_add'];
            }
        }

        return (new \DateTime())->format(self::PRESTASHOP_DATETIME_FORMAT);
    }

    /**
     * Get Shipping address usage indicator according the given period
     *
     * @param string $date
     *
     * @return string
     */
    public static function getShippingAddressUsageIndicator($date)
    {
        $indicatorClass = ShippingAddressUsageIndicators::class;

        return self::getIndicatorValue($date, $indicatorClass);
    }

    /**
     * Find number of customer's orders for a given period
     *
     * @param int $customerId
     * @param string $period
     *
     * @return int
     *
     * @throws \Exception
     */
    public static function findNumberOfOrdersForAPeriod($customerId, $period)
    {
        $endDate = new \DateTime();
        $startDate = (new \DateTime())->sub(new \DateInterval($period));

        return count(
            \Order::getOrdersIdByDate(
                $startDate->format(self::PRESTASHOP_DATETIME_FORMAT),
                $endDate->format(self::PRESTASHOP_DATETIME_FORMAT),
                $customerId
            )
        );
    }

    /**
     * Iterate over customer's orders for last six months
     *
     * @param int $customerId
     *
     * @return int
     */
    public static function findNumberOfOrdersForLastSixMonths($customerId)
    {
        $allOrders = \Order::getCustomerOrders($customerId);
        $startDate = (new \DateTime())->sub(new \DateInterval(self::ACTIVITY_6_MONTHS));

        $numberOfOrders = 0;

        foreach ($allOrders as $order) {
            $orderDate = \DateTime::createFromFormat(
                self::PRESTASHOP_DATETIME_FORMAT,
                $order['date_upd']
            );

            if ($orderDate < $startDate) {
                break;
            }

            if (in_array($order['current_state'], self::$paidStatuses)) {
                ++$numberOfOrders;
            }
        }

        return $numberOfOrders;
    }

    /**
     * Compare billing and shipping addresses
     *
     * @param \Address $invoiceAddress
     * @param \Address $shippingAddress
     *
     * @return bool
     */
    private static function areAddressesSame($invoiceAddress, $shippingAddress)
    {
        $invoice = [
            $invoiceAddress->firstname,
            $invoiceAddress->lastname,
            $invoiceAddress->address1,
            $invoiceAddress->address2,
            $invoiceAddress->postcode,
            $invoiceAddress->city,
            $invoiceAddress->country,
        ];

        $shipping = [
            $shippingAddress->firstname,
            $shippingAddress->lastname,
            $shippingAddress->address1,
            $shippingAddress->address2,
            $shippingAddress->postcode,
            $shippingAddress->city,
            $shippingAddress->country,
        ];

        return count(array_diff($invoice, $shipping)) === 0;
    }

    /**
     * Get indicator value according the given period of time
     *
     * @param string $date
     * @param string $indicatorClass
     *
     * @return string
     */
    private static function getIndicatorValue($date, $indicatorClass)
    {
        switch (self::getDateIndicator($date)) {
            case static::LESS_THAN_30_DAYS_INDICATOR:
                return $indicatorClass::LESS_THAN_30DAYS;
            case static::MORE_THAN_30_LESS_THAN_60_INDICATOR:
                return $indicatorClass::FROM_30_TO_60_DAYS;
            case static::MORE_THAN_60_DAYS_INDICATOR:
                return $indicatorClass::MORE_THAN_60DAYS;
            default:
                if ($indicatorClass === PasswordChangeIndicators::class) {
                    return $indicatorClass::DURING_TRANSACTION;
                }

                return $indicatorClass::CURRENT_TRANSACTION;
        }
    }

    /**
     * Check if date is less than 30, between 30 and 60 or more than 60 days
     *
     * @param string $date
     *
     * @return string
     */
    private static function getDateIndicator($date)
    {
        $now = new \DateTime();
        $checkDate = \DateTime::createFromFormat(self::PRESTASHOP_DATETIME_FORMAT, $date);
        $days = $checkDate->diff($now)->days;

        if ($days < 1) {
            return self::CURRENT_TRANSACTION_INDICATOR;
        }
        if ($days < 30) {
            return self::LESS_THAN_30_DAYS_INDICATOR;
        }
        if ($days < 60) {
            return self::MORE_THAN_30_LESS_THAN_60_INDICATOR;
        }

        return self::MORE_THAN_60_DAYS_INDICATOR;
    }

    /**
     * Sort customer data multidimensional array by date updated
     *
     * @param array $customerData
     * @param int $sort
     *
     * @return array
     */
    private static function getSortedCustomerData($customerData, $sort = SORT_DESC)
    {
        array_multisort(
            array_map('strtotime', array_column($customerData, 'date_upd')),
            $sort,
            $customerData
        );

        return $customerData;
    }
}
