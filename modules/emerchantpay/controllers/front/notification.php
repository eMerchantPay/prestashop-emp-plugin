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

class eMerchantPayNotificationModuleFrontController extends ModuleFrontController
{
	/** @var eMerchantPay */
	public $module;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		if (Tools::getIsset('signature')) {
			if (Tools::getIsset('wpf_unique_id')) {
				$this->processCheckoutIPN();
			}
			else {
				$this->processStandardIPN();
			}
		}

		exit(0);
	}

	/**
	 * Process Notification for the Standard (Payment) API
	 */
	private function processStandardIPN()
	{
		try {
			/** @var \Genesis\API\Notification $notification */
			$notification = new \Genesis\API\Notification();

			$notification->parseNotification( $_POST );

			if ( $notification->isAuthentic() ) {
				$genesis = new \Genesis\Genesis( 'Reconcile\Transaction' );

				$genesis->request()
				        ->setUniqueId( $notification->getParsedNotification()->unique_id );

				$genesis->execute();

				if (null !== $genesis->response()) {

					$reconcile = $genesis->response()->getResponseObject();

					if ( isset( $reconcile->transaction_id ) ) {
						$transaction = eMerchantPayTransaction::getByUniqueId( $reconcile->unique_id );

						if ( isset( $transaction->id_unique ) && $transaction->id_unique == $reconcile->unique_id ) {

							if ($reconcile->status == 'approved') {
								$status = _PS_OS_PAYMENT_;
							}
							else {
								$status = _PS_OS_ERROR_;
							}

							$transaction->updateOrderHistory( $status, true );

							$transaction->importResponse( $reconcile );

							$transaction->save();

							header( 'Content-type: application/xml' );
							echo $notification->getEchoResponse();
						}
					}
				}
			}
		}
		catch (Exception $exception) {
			if (class_exists('Logger')) {
				Logger::addLog( $exception->getMessage(), 4, $exception->getCode(), $this->module->displayName, $this->module->id, true );
			}
		}
	}

	/**
	 * Process Notifications for the Checkout (WPF) API
	 */
	private function processCheckoutIPN()
	{
		try {
			/** @var \Genesis\API\Notification $notification */
			$notification = new \Genesis\API\Notification();

			$notification->parseNotification( $_POST );

			if ( $notification->isAuthentic() ) {
				$genesis = new \Genesis\Genesis( 'WPF\Reconcile' );

				$genesis->request()
				        ->setUniqueId( $notification->getParsedNotification()->wpf_unique_id );

				$genesis->execute();

				if (null !== $genesis->response()) {

					$checkout_reconcile = $genesis->response()->getResponseObject();

					$checkout_transaction = eMerchantPayTransaction::getByUniqueId($checkout_reconcile->unique_id);

					if (isset($checkout_transaction)) {

						$payment_reconcile = $checkout_reconcile->payment_transaction;

						if ( isset( $payment_reconcile->unique_id ) ) {
							/** @var eMerchantPayTransaction $transaction */
							$payment_transaction = eMerchantPayTransaction::getByUniqueId( $payment_reconcile->unique_id );

							if ( $payment_transaction ) {
								$payment_transaction->importResponse( $payment_reconcile );
								$payment_transaction->save();
							} else {
								$payment_transaction = new eMerchantPayTransaction();

								$payment_transaction->id_parent = $checkout_transaction->id_unique;
								$payment_transaction->ref_order = $checkout_transaction->ref_order;
								$payment_transaction->importResponse( $payment_reconcile );
								$payment_transaction->add();
							}
						}

						if ($payment_reconcile->status == 'approved') {
							$status = _PS_OS_PAYMENT_;
						}
						else {
							$status = _PS_OS_ERROR_;
						}

						$checkout_transaction->type = 'checkout';
						$checkout_transaction->importResponse($checkout_reconcile);
						$checkout_transaction->updateOrderHistory( $status, true );
						$checkout_transaction->save();

						header( 'Content-type: application/xml' );
						echo $notification->getEchoResponse();
					}
				}
			}
		}
		catch (Exception $exception) {
			if (class_exists('Logger')) {
				Logger::addLog( $exception->getMessage(), 4, $exception->getCode(), $this->module->displayName, $this->module->id, true );
			}
		}
	}
}