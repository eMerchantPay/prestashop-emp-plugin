<?php
/*
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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayTransactionProcess
 *
 * Build and execute emerchantpay transactions
 */
class EmerchantpayTransactionProcess
{
    const displayName = 'emerchantpay Transactions';

    /**
     * Create a Web-Payment Form instance.
     *
     * @param $data
     *
     * @throws Exception
     * @return \Genesis\API\Response
     */
    public static function checkout($data)
    {
        $genesis = new \Genesis\Genesis('WPF\Create');

        $genesis
            ->request()
                ->setTransactionId($data->id)
                ->setCurrency($data->currency)
                ->setAmount($data->amount)
                ->setCustomerEmail($data->customer_email)
                ->setCustomerPhone($data->customer_phone);

        if (isset($data->usage)) {
            $genesis
                ->request()
                    ->setUsage($data->usage);
        }

        if (isset($data->description)) {
            $genesis
                ->request()
                    ->setDescription($data->description);
        }

        if (isset($data->billing)) {
            $genesis
                ->request()
                    ->setBillingFirstName($data->billing->firstname)
                    ->setBillingLastName($data->billing->lastname)
                    ->setBillingAddress1($data->billing->address1)
                    ->setBillingAddress2($data->billing->address2)
                    ->setBillingZipCode($data->billing->postcode)
                    ->setBillingCity($data->billing->city)
                    ->setBillingState($data->billing->state)
                    ->setBillingCountry($data->billing->country);
        }

        if (isset($data->shipping)) {
            $genesis
                ->request()
                    ->setShippingFirstName($data->shipping->firstname)
                    ->setShippingLastName($data->shipping->lastname)
                    ->setShippingAddress1($data->shipping->address1)
                    ->setShippingAddress2($data->shipping->address2)
                    ->setShippingZipCode($data->shipping->postcode)
                    ->setShippingCity($data->shipping->city)
                    ->setShippingState($data->shipping->state)
                    ->setShippingCountry($data->shipping->country);
        }

        if (isset($data->url)) {
            $genesis
                ->request()
                    ->setNotificationUrl($data->url->notification)
                    ->setReturnSuccessUrl($data->url->return_success)
                    ->setReturnFailureUrl($data->url->return_failure)
                    ->setReturnCancelUrl($data->url->return_cancel);
        }

        if (isset($data->transaction_types)) {
            foreach ($data->transaction_types as $type) {
                if (is_array($type)) {
                    $genesis
                        ->request()
                            ->addTransactionType($type['name'], $type['parameters']);
                } else {
                    $genesis
                        ->request()
                            ->addTransactionType($type);
                }
            }
        }

        if (isset($data->language)) {
            $genesis
                ->request()
                    ->setLanguage($data->language);
        }

        if ($data->is_wpf_tokenization_enabled) {
            $consumerId = EmerchantpayConsumer::getConsumerId(
                \Genesis\Config::getUsername(),
                $data->customer_email
            );

            if (empty($consumerId)) {
                $consumerId = static::retrieveConsumerIdFromEmail($data->customer_email);
            }

            if (!empty($consumerId)) {
                $genesis->request()->setConsumerId($consumerId);
            }

            $genesis->request()->setRememberCard(true);
        }

        $genesis->execute();

        return $genesis->response();
    }

    /**
     * @param string $email
     *
     * @return null|int
     */
    protected static function retrieveConsumerIdFromEmail($email)
    {
        try {
            $genesis = new \Genesis\Genesis('NonFinancial\Consumers\Retrieve');
            $genesis->request()->setEmail($email);

            $genesis->execute();

            $response = $genesis->response()->getResponseObject();

            if (!self::isConsumerEnabled($response)) {
                return null;
            }

            return $response->consumer_id;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @param $response
     *
     * @return bool
     */
    private static function isConsumerEnabled($response)
    {
        $state = new \Genesis\API\Constants\Transaction\States($response->status);

        return $state->isEnabled();
    }

    /**
     * Perform a standard (and 3DSecure) payment transaction.
     *
     * Note: the transaction type depends on the Admin Panel selection
     *
     * @param $data stdClass Parameters for the transaction
     *
     * @throws Exception
     * @return \Genesis\API\Response
     */
    public static function pay($data)
    {
        $genesis = new \Genesis\Genesis(
            \Genesis\API\Constants\Transaction\Types::getFinancialRequestClassForTrxType($data->transaction_type)
        );

        $genesis
            ->request()
                ->setTransactionId($data->id)
                ->setRemoteIp($data->remote_ip)
                ->setCurrency($data->currency)
                ->setAmount($data->amount)
                ->setCardHolder($data->card_holder)
                ->setCardNumber($data->card_number)
                ->setExpirationMonth($data->expiration_month)
                ->setExpirationYear($data->expiration_year)
                ->setCvv($data->cvv)
                ->setCustomerEmail($data->customer_email)
                ->setCustomerPhone($data->customer_phone);

        if (isset($data->billing)) {
            $genesis
                ->request()
                    ->setBillingFirstName($data->billing->firstname)
                    ->setBillingLastName($data->billing->lastname)
                    ->setBillingAddress1($data->billing->address1)
                    ->setBillingAddress2($data->billing->address2)
                    ->setBillingZipCode($data->billing->postcode)
                    ->setBillingCity($data->billing->city)
                    ->setBillingState($data->billing->state)
                    ->setBillingCountry($data->billing->country);
        }

        if (isset($data->shipping)) {
            $genesis
                ->request()
                    ->setShippingFirstName($data->shipping->firstname)
                    ->setShippingLastName($data->shipping->lastname)
                    ->setShippingAddress1($data->shipping->address1)
                    ->setShippingAddress2($data->shipping->address2)
                    ->setShippingZipCode($data->shipping->postcode)
                    ->setShippingCity($data->shipping->city)
                    ->setShippingState($data->shipping->state)
                    ->setShippingCountry($data->shipping->country);
        }

        if (isset($data->url) &&
            \Genesis\API\Constants\Transaction\Types::is3D($data->transaction_type)) {
            $genesis
                ->request()
                    ->setNotificationUrl($data->url->notification)
                    ->setReturnSuccessUrl($data->url->return_success)
                    ->setReturnFailureUrl($data->url->return_failure);
        }

        $genesis->execute();

        return $genesis->response();
    }

    /**
     * Execute a Capture transaction
     *
     * @param $data array Parameters for the transaction
     *
     * @throws Exception
     *
     * @return \Genesis\API\Response|null
     */
    public static function capture($data)
    {
        $genesis = new \Genesis\Genesis(
            \Genesis\API\Constants\Transaction\Types::getCaptureTransactionClass($data['transaction_type'])
        );

        $genesis
            ->request()
                ->setTransactionId($data['transaction_id'])
                ->setUsage($data['usage'])
                ->setRemoteIp($data['remote_ip'])
                ->setReferenceId($data['reference_id'])
                ->setAmount($data['amount'])
                ->setCurrency($data['currency']);

        if ($data['transaction_type'] === \Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE &&
            $data['items'] instanceof \Genesis\API\Request\Financial\Alternatives\Klarna\Items
        ) {
            $genesis
                ->request()
                ->setItems($data['items']);
        }

        $genesis->execute();

        return $genesis->response();
    }

    /**
     * Execute a Refund transaction
     *
     * @param $data array Parameters for the transaction
     *
     * @throws Exception
     *
     * @return \Genesis\API\Response
     */
    public static function refund($data)
    {
        $genesis = new \Genesis\Genesis(
            \Genesis\API\Constants\Transaction\Types::getRefundTransactionClass($data['transaction_type'])
        );

        $genesis
            ->request()
                ->setTransactionId($data['transaction_id'])
                ->setUsage($data['usage'])
                ->setRemoteIp($data['remote_ip'])
                ->setReferenceId($data['reference_id'])
                ->setAmount($data['amount'])
                ->setCurrency($data['currency']);

        if ($data['transaction_type'] === \Genesis\API\Constants\Transaction\Types::KLARNA_CAPTURE &&
            $data['items'] instanceof \Genesis\API\Request\Financial\Alternatives\Klarna\Items
        ) {
            $genesis
                ->request()
                ->setItems($data['items']);
        }

        $genesis->execute();

        return $genesis->response();
    }

    /**
     * Execute Void transaction
     *
     * @param $data array Parameters for the transaction
     *
     * @throws Exception
     *
     * @return \Genesis\API\Response
     */
    public static function void($data)
    {
        $genesis = new \Genesis\Genesis('Financial\Cancel');

        $genesis
            ->request()
                ->setTransactionId($data['transaction_id'])
                ->setUsage($data['usage'])
                ->setRemoteIp($data['remote_ip'])
                ->setReferenceId($data['reference_id']);

        $genesis->execute();

        return $genesis->response();
    }
}
