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
require_once __DIR__ . '/../../vendor/autoload.php';

use Emerchantpay\Genesis\EmerchantpayTransaction;
use Genesis\Api\Notification;
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

    /** @var Notification */
    protected $notification;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        try {
            parent::initContent();

            $this->module->applyGenesisConfig();

            $this->notification = new Notification($_POST);

            if ($this->notification->isAPINotification()) {
                // Configure Gateway library
                Genesis\Config::setToken(filter_input(INPUT_POST, 'terminal_token', FILTER_SANITIZE_STRING));

                $this->processDirectIPN();
            }

            if ($this->notification->isWPFNotification()) {
                $this->processCheckoutIPN();
            }

            // Provide response to the Gateway
            $this->notification->renderResponse();
        } catch (Exception $exception) {
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

        exit(0);
    }

    /**
     * Process Notification for the Direct API
     *
     * @return void
     *
     * @throws Exception
     */
    private function processDirectIPN()
    {
        if (!$this->notification->isAuthentic()) {
            throw new Exception($this->module->l('Notification can not be handled due no Authenticity.'));
        }

        $this->notification->initReconciliation();

        $reconcile = $this->notification->getReconciliationObject();

        // Update the Payment without updating the Order due the Direct Payment is not available anymore
        $this->saveDirectPaymentTransaction($reconcile);
    }

    /**
     * Process Notifications for the Checkout (WPF) API
     *
     * @return void
     *
     * @throws Exception
     */
    private function processCheckoutIPN()
    {
        if (!$this->notification->isAuthentic()) {
            throw new Exception($this->module->l('Notification can not be handled due no Authenticity.'));
        }

        $this->notification->initReconciliation();

        $checkoutReconcile = $this->notification->getReconciliationObject();
        $checkoutTransaction = $this->getPaymentTransaction($checkoutReconcile);

        if (!isset($checkoutTransaction->id_unique)) {
            throw new Exception($this->module->l('Initial Payment not found!'));
        }

        // Update initial payment - WPF creation record
        $this->updatePaymentTransaction($checkoutTransaction, $checkoutReconcile, 'checkout');

        // Process WPF Reconcile payment_transaction
        $this->saveCheckoutPaymentTransaction($checkoutTransaction, $checkoutReconcile);

        // Update PrestaShop Order
        $orderStatus = $this->module->getPrestaStatus($checkoutReconcile->status);
        $checkoutTransaction->updateOrderHistory($orderStatus, true);
    }

    /**
     * @param EmerchantpayTransaction $initialPayment
     * @param stdClass $gatewayReconciliation
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function saveCheckoutPaymentTransaction($initialPayment, $gatewayReconciliation)
    {
        if (!isset($gatewayReconciliation->payment_transaction)) {
            return;
        }

        $paymentTransaction = $gatewayReconciliation->payment_transaction;

        if ($paymentTransaction instanceof ArrayObject) {
            $this->processMultiplePaymentEvents($initialPayment, $paymentTransaction);
        }

        if ($paymentTransaction instanceof stdClass) {
            $this->processSingleEvent($initialPayment, $paymentTransaction);
        }
    }

    /**
     * @param EmerchantpayTransaction $initialPayment
     * @param stdClass $gatewayReconciliation
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function saveDirectPaymentTransaction($reconcile)
    {
        $paymentModel = $this->getPaymentTransaction($reconcile);

        if (isset($paymentModel->id_unique)) {
            $this->updatePaymentTransaction($paymentModel, $reconcile);

            return;
        }

        if (!isset($reconcile->reference_transaction_unique_id)) {
            return;
        }

        // Get the parent transaction
        $parentPaymentModel = EmerchantpayTransaction::getByUniqueId($reconcile->reference_transaction_unique_id);

        if ($parentPaymentModel) {
            $this->addPaymentTransaction($parentPaymentModel, $reconcile);
        }
    }

    /**
     * @param EmerchantpayTransaction $initialPayment
     * @param ArrayObject $gatewayPayments
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function processMultiplePaymentEvents($initialPayment, $gatewayPayments)
    {
        foreach ($gatewayPayments as $payment) {
            $this->processSingleEvent($initialPayment, $payment);
        }
    }

    /**
     * @param EmerchantpayTransaction $initialPayment
     * @param stdClass $gatewayPayment
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function processSingleEvent($initialPayment, $gatewayPayment)
    {
        $paymentModel = $this->getPaymentTransaction($gatewayPayment);

        if (isset($paymentModel->id_unique)) {
            $this->updatePaymentTransaction($paymentModel, $gatewayPayment);

            return;
        }

        if (isset($gatewayPayment->unique_id)) {
            $this->addPaymentTransaction($initialPayment, $gatewayPayment);
        }
    }

    /**
     * Retrieve the Emerchantpay Payment row from the DB by Gateway Unique ID
     *
     * @param stdClass $paymentResponse
     *
     * @return false|EmerchantpayTransaction
     */
    private function getPaymentTransaction($paymentResponse)
    {
        if (!isset($paymentResponse->unique_id)) {
            return false;
        }

        return EmerchantpayTransaction::getByUniqueId($paymentResponse->unique_id);
    }

    /**
     * @param EmerchantpayTransaction $paymentModel
     * @param stdClass $response
     *
     * @return void
     */
    private function updatePaymentTransaction($paymentModel, $response, $type = '')
    {
        if (!empty($type)) {
            $paymentModel->type = $type;
        }

        $paymentModel->importResponse($response);

        $paymentModel->save();
    }

    /**
     * @param $checkout_transaction
     * @param $payment_reconcile
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function addPaymentTransaction($checkout_transaction, $payment_reconcile)
    {
        $payment_transaction = new EmerchantpayTransaction();

        $payment_transaction->id_parent = $checkout_transaction->id_unique;
        $payment_transaction->ref_order = $checkout_transaction->ref_order;
        $payment_transaction->importResponse($payment_reconcile);
        $payment_transaction->add();
    }
}
