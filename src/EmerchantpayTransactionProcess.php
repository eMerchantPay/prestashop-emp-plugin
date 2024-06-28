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

namespace Emerchantpay\Genesis;

use Emerchantpay\Genesis\Exceptions\ErrorState;
use Genesis\Api\Constants\Transaction\States;
use Genesis\Api\Constants\Transaction\Types;
use Genesis\Api\Request\Financial\Alternatives\Klarna\Items;
use Genesis\Api\Request\Wpf\Create;
use Genesis\Api\Response;
use Genesis\Config;
use Genesis\Genesis;

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
    /**
     * Create a Web-Payment Form instance.
     *
     * @param $data
     *
     * @return Response
     *
     * @throws \Exception
     */
    public static function checkout($data)
    {
        $genesis = new Genesis('Wpf\Create');

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
                    ->setReturnPendingUrl($data->url->return_success)
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
                Config::getUsername(),
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

        if ($data->is_threeds_allowed) {
            /** @var Create $request */
            $request = $genesis->request();
            $request
                ->setThreedsV2ControlChallengeIndicator($data->threeds_challenge_indicator)
                ->setThreedsV2PurchaseCategory($data->threeds_purchase_category)
                ->setThreedsV2MerchantRiskDeliveryTimeframe($data->threeds_delivery_timeframe)
                ->setThreedsV2MerchantRiskShippingIndicator($data->threeds_shipping_indicator)
                ->setThreedsV2MerchantRiskReorderItemsIndicator($data->threeds_reorder_items_indicator)
                ->setThreedsV2CardHolderAccountRegistrationIndicator($data->threeds_registration_indicator)
            ;
            if (!$data->is_guest) {
                $request
                    ->setThreedsV2CardHolderAccountCreationDate($data->threeds_creation_date)
                    ->setThreedsV2CardHolderAccountLastChangeDate($data->threeds_last_change_date)
                    ->setThreedsV2CardHolderAccountUpdateIndicator($data->threeds_update_indicator)
                    ->setThreedsV2CardHolderAccountPasswordChangeDate($data->threeds_password_change_date)
                    ->setThreedsV2CardHolderAccountPasswordChangeIndicator($data->threeds_password_change_indicator)
                    ->setThreedsV2CardHolderAccountRegistrationDate($data->threeds_registration_date)
                    ->setThreedsV2CardHolderAccountShippingAddressDateFirstUsed(
                        $data->threeds_shipping_address_date_first_used
                    )
                    ->setThreedsV2CardHolderAccountShippingAddressUsageIndicator(
                        $data->threeds_shipping_address_usage_indicator
                    )
                    ->setThreedsV2CardHolderAccountTransactionsActivityLast24Hours(
                        $data->transactions_activity_last_24_hours
                    )
                    ->setThreedsV2CardHolderAccountTransactionsActivityPreviousYear(
                        $data->transactions_activity_previous_year
                    )
                    ->setThreedsV2CardHolderAccountPurchasesCountLast6Months(
                        $data->purchases_count_last_6_months
                    )
                ;
            }
        }

        $wpfAmount = (float) $genesis->request()->getAmount();
        if ($wpfAmount <= $data->sca_exemption_amount) {
            $genesis->request()->setScaExemption($data->sca_exemption_value);
        }

        if (isset($data->web_payment_form_id)) {
            $genesis->request()->setWebPaymentFormId($data->web_payment_form_id);
        }

        $genesis->execute();

        if (!$genesis->response()->isSuccessful()) {
            throw new ErrorState($genesis->response()->getErrorDescription());
        }

        return $genesis->response();
    }

    /**
     * @param string $email
     *
     * @return int|null
     */
    protected static function retrieveConsumerIdFromEmail($email)
    {
        try {
            $genesis = new Genesis('NonFinancial\Consumers\Retrieve');
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
        $state = new States($response->status);

        return $state->isEnabled();
    }

    /**
     * Perform a standard (and 3DSecure) payment transaction.
     *
     * Note: the transaction type depends on the Admin Panel selection
     *
     * @param $data \stdClass Parameters for the transaction
     *
     * @return Response
     *
     * @throws \Exception
     */
    public static function pay($data)
    {
        $genesis = new Genesis(
            Types::getFinancialRequestClassForTrxType($data->transaction_type)
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

        if (isset($data->url) && Types::is3D($data->transaction_type)) {
            $genesis
                ->request()
                    ->setNotificationUrl($data->url->notification)
                    ->setReturnSuccessUrl($data->url->return_success)
                    ->setReturnFailureUrl($data->url->return_failure);
        }

        $genesis->execute();

        if (!$genesis->response()->isSuccessful()) {
            throw new ErrorState($genesis->response()->getErrorDescription());
        }

        return $genesis->response();
    }

    /**
     * Execute a Capture transaction
     *
     * @param $data array Parameters for the transaction
     *
     * @return Response|null
     *
     * @throws \Exception
     */
    public static function capture($data)
    {
        $genesis = new Genesis(
            Types::getCaptureTransactionClass($data['transaction_type'])
        );

        $genesis
            ->request()
                ->setTransactionId($data['transaction_id'])
                ->setUsage($data['usage'])
                ->setRemoteIp($data['remote_ip'])
                ->setReferenceId($data['reference_id'])
                ->setAmount($data['amount'])
                ->setCurrency($data['currency']);

        if ($data['transaction_type'] === Types::KLARNA_AUTHORIZE && $data['items'] instanceof Items) {
            $genesis
                ->request()
                ->setItems($data['items']);
        }

        $genesis->execute();

        if (!$genesis->response()->isSuccessful()) {
            throw new ErrorState($genesis->response()->getErrorDescription());
        }

        return $genesis->response();
    }

    /**
     * Execute a Refund transaction
     *
     * @param $data array Parameters for the transaction
     *
     * @return Response
     *
     * @throws \Exception
     */
    public static function refund($data)
    {
        $genesis = new Genesis(
            Types::getRefundTransactionClass($data['transaction_type'])
        );

        $genesis
            ->request()
                ->setTransactionId($data['transaction_id'])
                ->setUsage($data['usage'])
                ->setRemoteIp($data['remote_ip'])
                ->setReferenceId($data['reference_id'])
                ->setAmount($data['amount'])
                ->setCurrency($data['currency']);

        if ($data['transaction_type'] === Types::KLARNA_CAPTURE && $data['items'] instanceof Items) {
            $genesis
                ->request()
                ->setItems($data['items']);
        }

        $genesis->execute();

        if (!$genesis->response()->isSuccessful()) {
            throw new ErrorState($genesis->response()->getErrorDescription());
        }

        return $genesis->response();
    }

    /**
     * Execute Void transaction
     *
     * @param $data array Parameters for the transaction
     *
     * @return Response
     *
     * @throws \Exception
     */
    public static function void($data)
    {
        $genesis = new Genesis('Financial\Cancel');

        $genesis
            ->request()
                ->setTransactionId($data['transaction_id'])
                ->setUsage($data['usage'])
                ->setRemoteIp($data['remote_ip'])
                ->setReferenceId($data['reference_id']);

        $genesis->execute();

        if (!$genesis->response()->isSuccessful()) {
            throw new ErrorState($genesis->response()->getErrorDescription());
        }

        return $genesis->response();
    }
}
