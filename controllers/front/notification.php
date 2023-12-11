<?php
/**
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
require_once __DIR__ . '/../../vendor/autoload.php';

use Emerchantpay\Genesis\EmerchantpayTransaction;
use Genesis\API\Constants\Transaction\Types;
use PrestaShopLogger as Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class EmerchantpayNotificationModuleFrontController
 *
 * Notifications Front-End Controller
 */
class EmerchantpayNotificationModuleFrontController extends ModuleFrontController
{
    /** @var Emerchantpay */
    public $module;

    /**
     * Supported transaction types for Order Status
     *
     * @var array
     */
    public $types = [
        Types::AUTHORIZE,
        Types::AUTHORIZE_3D,
        Types::CASHU,
        Types::FASHIONCHEQUE,
        Types::NETELLER,
        Types::PAYSAFECARD,
        Types::PPRO,
        Types::SALE,
        Types::SALE_3D,
        Types::SOFORT,
        Types::EZEEWALLET,
        Types::IDEBIT_PAYIN,
        Types::INSTA_DEBIT_PAYIN,
        Types::INTERSOLVE,
        Types::ONLINE_BANKING_PAYIN,
        Types::P24,
        Types::POLI,
        Types::SDD_SALE,
        Types::TCS,
        Types::TRUSTLY_SALE,
        Types::WEBMONEY,
        Types::WECHAT,
    ];

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->module->applyGenesisConfig();

        if (Tools::getIsset('signature')) {
            if (Tools::getIsset('wpf_unique_id')) {
                $this->processCheckoutIPN();
            } else {
                $this->processDirectIPN();
            }
        }

        exit(0);
    }

    /**
     * Process Notification for the Direct API
     */
    private function processDirectIPN()
    {
        try {
            /** @var \Genesis\API\Notification $notification */
            $notification = new Genesis\API\Notification($_POST);

            if ($notification->isAuthentic()) {
                $notification->initReconciliation();

                $reconcile = $notification->getReconciliationObject();

                if (isset($reconcile->unique_id)) {
                    $transaction = EmerchantpayTransaction::getByUniqueId($reconcile->unique_id);

                    if (isset($transaction->id_unique) && $transaction->id_unique == $reconcile->unique_id) {
                        if (in_array($reconcile->transaction_type, $this->types)) {
                            $status = $this->module->getPrestaStatus($reconcile->status);
                        } else {
                            $status = $this->module->getPrestaBackendStatus($reconcile->transaction_type);
                        }

                        $transaction->importResponse($reconcile);
                        $transaction->updateOrderHistory($status, true);
                        $transaction->save();
                    }

                    $notification->renderResponse();
                }
            }
        } catch (\Exception $exception) {
            if (class_exists('PrestaShopLogger')) {
                Logger::addLog(
                    $exception->getMessage(),
                    4,
                    $exception->getCode(),
                    $this->module->name,
                    $this->module->id,
                    true
                );
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
            $notification = new Genesis\API\Notification($_POST);

            if ($notification->isAuthentic()) {
                $notification->initReconciliation();

                $checkout_reconcile = $notification->getReconciliationObject();

                if (isset($checkout_reconcile->unique_id)) {
                    $checkout_transaction = EmerchantpayTransaction::getByUniqueId($checkout_reconcile->unique_id);

                    if (isset($checkout_transaction->id_unique)) {
                        $checkout_transaction->type = 'checkout';
                        $checkout_transaction->importResponse($checkout_reconcile);
                        $checkout_transaction->save();

                        if (isset($checkout_reconcile->payment_transaction)) {
                            $this->savePaymentTransaction(
                                $checkout_transaction,
                                $checkout_reconcile->payment_transaction
                            );
                        }

                        $checkout_transaction->updateOrderHistory(
                            $this->module->getPrestaStatus($checkout_reconcile->status),
                            true
                        );

                        $notification->renderResponse();
                    }
                }
            }
        } catch (\Exception $exception) {
            if (class_exists('PrestaShopLogger')) {
                Logger::addLog(
                    $exception->getMessage(),
                    4,
                    $exception->getCode(),
                    $this->module->name,
                    $this->module->id,
                    true
                );
            }
        }
    }

    /**
     * @param $checkout_transaction
     * @param $payment_reconcile
     *
     * @throws PrestaShopException
     */
    protected function savePaymentTransaction($checkout_transaction, $payment_reconcile)
    {
        $payment_transaction = $this->getPaymentTransaction($payment_reconcile);

        if ($payment_transaction) {
            $payment_transaction->importResponse($payment_reconcile);
            $payment_transaction->save();
        } elseif ($payment_reconcile instanceof \ArrayObject) {
            foreach ($payment_reconcile as $trx) {
                $this->addPaymentTransaction($checkout_transaction, $trx);
            }
        } else {
            $this->addPaymentTransaction($checkout_transaction, $payment_reconcile);
        }
    }

    /**
     * @param $payment_reconcile
     *
     * @return EmerchantpayTransaction
     */
    protected function getPaymentTransaction($payment_reconcile)
    {
        if ($payment_reconcile instanceof \ArrayObject) {
            return EmerchantpayTransaction::getByUniqueId($payment_reconcile[0]->unique_id);
        }

        return EmerchantpayTransaction::getByUniqueId($payment_reconcile->unique_id);
    }

    /**
     * @param $checkout_transaction
     * @param $payment_reconcile
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function addPaymentTransaction($checkout_transaction, $payment_reconcile)
    {
        $payment_transaction = new EmerchantpayTransaction();

        $payment_transaction->id_parent = $checkout_transaction->id_unique;
        $payment_transaction->ref_order = $checkout_transaction->ref_order;
        $payment_transaction->importResponse($payment_reconcile);
        $payment_transaction->add();
    }
}
