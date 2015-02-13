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

class eMerchantPayTransactionProcess
{
	const displayName = 'eMerchantPay Transactions';

	/**
	 * Perform a customer payment transaction. The
	 * transaction type depends on the type, selected
	 * in the configuration settings
	 *
	 * @param $data stdClass Parameters for the transaction
	 *
	 * @return \Genesis\API\Response
	 */
	public static function pay($data)
	{
		try {
			switch ( $data->transaction_type ) {
				default:
				case 'authorize':
					$genesis = new \Genesis\Genesis( 'Financial\Authorize' );
					break;
				case 'authorize3d':
					$genesis = new \Genesis\Genesis( 'Financial\Authorize3D' );
					break;
				case 'sale':
					$genesis = new \Genesis\Genesis( 'Financial\Sale' );
					break;
				case 'sale3d':
					$genesis = new \Genesis\Genesis( 'Financial\Sale3D' );
					break;
			}

			$genesis->request()
					->setTransactionId( $data->id )
					->setRemoteIp( $data->remote_ip )
					->setCurrency( $data->currency )
					->setAmount( $data->amount )
					->setCardHolder( $data->card_holder )
					->setCardNumber( $data->card_number )
					->setExpirationMonth( $data->expiration_month )
					->setExpirationYear( $data->expiration_year )
					->setCvv( $data->cvv )
					->setCustomerEmail( $data->customer_email )
					->setCustomerPhone( $data->customer_phone );

			if (isset($data->billing)) {
				$genesis->request()
						->setBillingFirstName( $data->billing->firstname )
						->setBillingLastName( $data->billing->lastname )
						->setBillingAddress1( $data->billing->address1 )
						->setBillingAddress2( $data->billing->address2 )
						->setBillingZipCode( $data->billing->postcode )
						->setBillingCity( $data->billing->city )
						->setBillingState( $data->billing->state )
						->setBillingCountry( $data->billing->country );
			}

			if (isset($data->shipping)) {
				$genesis->request()
						->setShippingFirstName( $data->shipping->firstname )
						->setShippingLastName( $data->shipping->lastname )
						->setShippingAddress1( $data->shipping->address1 )
						->setShippingAddress2( $data->shipping->address2 )
						->setShippingZipCode( $data->shipping->postcode )
						->setShippingCity( $data->shipping->city )
						->setShippingState( $data->shipping->state )
						->setShippingCountry( $data->shipping->country );
			}

			if (isset($data->url)) {
				$genesis->request()
						->setNotificationUrl( $data->url->notification )
						->setReturnSuccessUrl( $data->url->return_success )
						->setReturnFailureUrl( $data->url->return_failure );
			}

			$genesis->execute();

			if ( $genesis->response() ) {
				return $genesis->response();
			}
		}
		catch (Exception $exception) {
			self::logException($exception);

			return null;
		}
	}

	/**
	 * Execute a Capture transaction
	 *
	 * @param $data array Parameters for the transaction
	 *
	 * @return \Genesis\API\Response|null
	 */
	public static function capture($data)
	{
		try {
			$genesis = new \Genesis\Genesis('Financial\Capture');

			$genesis->request()
					->setTransactionId($data['transaction_id'])
					->setUsage($data['usage'])
					->setRemoteIp($data['remote_ip'])
					->setReferenceId($data['reference_id'])
					->setAmount($data['amount'])
					->setCurrency($data['currency']);

			$genesis->execute();

			if ( $genesis->response() ) {
				if (!$genesis->response()->isSuccessful()) {
					throw new Exception('Failed Capture transaction attempt: ' .
					                    $genesis->response()->getResponseObject()->technical_message);
				}

				return $genesis->response();
			}
			else {
				throw new Exception( 'Invalid response, probably a system error or missing component!' );
			}
		}
		catch(Exception $exception) {
			self::logException($exception);

			return null;
		}
	}

	/**
	 * Execute a Refund transaction
	 *
	 * @param $data array Parameters for the transaction
	 *
	 * @return \Genesis\API\Response
	 */
	public static function refund($data)
	{
		try {
			$genesis = new \Genesis\Genesis('Financial\Refund');

			$genesis->request()
					->setTransactionId($data['transaction_id'])
					->setUsage($data['usage'])
					->setRemoteIp($data['remote_ip'])
					->setReferenceId($data['reference_id'])
					->setAmount($data['amount'])
					->setCurrency($data['currency']);

			$genesis->execute();

			if ( $genesis->response() ) {
				if (!$genesis->response()->isSuccessful()) {
					throw new Exception('Failed Refund transaction attempt: ' .
					                    $genesis->response()->getResponseObject()->technical_message);
				}

				return $genesis->response();
			}
			else {
				throw new Exception( 'Invalid response, probably a system error or missing component!' );
			}
		}
		catch(Exception $exception) {
			self::logException($exception);

			return null;
		}
	}

	/**
	 * Execute Void transaction
	 *
	 * @param $data array Parameters for the transaction
	 *
	 * @return \Genesis\API\Response
	 */
	public static function void($data)
	{
		try {
			$genesis = new \Genesis\Genesis('Financial\Void');

			$genesis->request()
					->setTransactionId($data['transaction_id'])
					->setUsage($data['usage'])
					->setRemoteIp($data['remote_ip'])
					->setReferenceId($data['reference_id']);

			$genesis->execute();

			if ( $genesis->response() ) {
				if (!$genesis->response()->isSuccessful()) {
					throw new Exception('Failed Void transaction attempt: ' .
					                    $genesis->response()->getResponseObject()->technical_message);
				}

				return $genesis->response();
			}
			else {
				throw new Exception( 'Invalid response, probably a system error or missing component!' );
			}
		}
		catch(Exception $exception) {
			self::logException($exception);

			return null;
		}
	}

	/**
	 * Log exceptions to Prestashop's Internal Logging System
	 *
	 * @param $exception Exception an Exception that was thrown
	 */
	public static function logException($exception)
	{
		if (class_exists('Logger')) {
			Logger::addLog( $exception->getMessage(), 4, $exception->getCode(), self::displayName, 0, true );
		}
	}
}