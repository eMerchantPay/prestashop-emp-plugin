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

	public function initContent()
	{
		parent::initContent();

		if (Tools::getValue('unique_id') && Tools::getValue('signature')) {
			$this->processNotification();
		}

		exit(0);
	}

	private function processNotification()
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

							switch ( $reconcile->status ) {
								case 'approved':
									$status = _PS_OS_PAYMENT_;
									break;
								default:
									$status = _PS_OS_ERROR_;
									break;
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
}