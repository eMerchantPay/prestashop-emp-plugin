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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class eMerchantPayTransaction
 *
 * eMerchantPay Transaction Model
 */
class eMerchantPayTransaction extends ObjectModel
{
	public $id_unique;
	public $id_parent;
	public $ref_order;
	public $type;
	public $status;
	public $message;
	public $currency;
	public $amount;
    public $terminal;
	public $date_add;
	public $date_upd;

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table'  => 'emerchantpay_transactions',
		'primary'=> 'id_entry',
		'fields' => array(
			'id_unique' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 254),
			'id_parent' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 254),
			'ref_order' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 9),
			'type'      => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 254),
			'status'    => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 254),
			'message'   => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 254),
			'currency'  => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 3),
			'amount'    => array('type' => self::TYPE_FLOAT,  'validate' => 'isPrice'),
            'terminal'  => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
			'date_add'  => array('type' => self::TYPE_DATE,   'validate' => 'isDate'),
			'date_upd'  => array('type' => self::TYPE_DATE,   'validate' => 'isDate'),
		),
	);

	/**
	 * Add transaction
	 *
	 * @param bool $autodate    set autodate without explicit declaration?
	 * @param bool $nullValues  accept nulls?
	 *
	 * @return bool
	 */
	public function add($autodate = true, $nullValues = false)
	{
		if (parent::add($autodate, $nullValues))
		{
			Hook::exec('actionEmerchantPayAddTransaction', array('emerchantpayAddTransaction' => $this));
			return true;
		}

		return false;
	}

	/**
	 * Get the order associated with the current transaction
	 *
	 * @return OrderCore
	 */
	public function getOrder()
	{
		/** @var PrestaShopCollectionCore $orders */
		$orders = new PrestaShopCollection('Order');
		$orders->where('reference', '=', $this->ref_order);
		return $orders->getFirst();
	}

    /**
     * Return transaction object based on its id_unique
     *
     * @param $id_unique
     *
     * @return eMerchantPayTransaction
     *
     * @throws PrestaShopException
     */
	public static function getByUniqueId($id_unique)
	{
		/** @var PrestaShopCollectionCore $result */
		$result = new PrestaShopCollection('eMerchantPayTransaction');
		$result->where('id_unique', '=', $id_unique);
		return $result->getFirst();
	}

	/**
	 * Get the detailed payment of an order
	 *
	 * @param int $id_order
	 *
	 * @return array
	 */
	public static function getByOrderId($id_order)
	{
		$order = new Order((int)$id_order);

		/** @var PrestaShopCollectionCore $transactions */
		$transactions = new PrestaShopCollection('eMerchantPayTransaction');
		$transactions->where('ref_order', '=', $order->reference);
		return $transactions;
	}

	/**
	 * Get the order associated with the transaction
	 * specified by id_transaction
	 *
	 * @param $id_transaction
	 *
	 * @return OrderCore
	 */
	public static function getOrderByTransactionId($id_transaction)
	{
		$transaction = eMerchantPayTransaction::getByUniqueId($id_transaction);

		/** @var PrestaShopCollectionCore $orders */
		$orders = new PrestaShopCollection('Order');
		$orders->where('reference', '=', $transaction->ref_order);
		return $orders->getFirst();
	}

	/**
	 * Get the detailed payment of an order
	 * @param int $order_reference
	 * @return array
	 * @since 1.5.0.13
	 */
	public static function getByOrderReference($order_reference)
	{
		return ObjectModel::hydrateCollection('eMerchantPayTransaction',
			Db::getInstance()->executeS("
				SELECT *
				FROM `" . _DB_PREFIX_ . "emerchantpay_transactions`
				WHERE `ref_order` = '" . pSQL($order_reference) . "'
			")
		);
	}

	/**
	 * Returns an array with tree-structure where
	 * every branch is a transaction related to
	 * the order
	 *
	 * @param $id_order int OrderId
	 *
	 * @return array
	 */
	public static function getTransactionTree($id_order)
	{
		/** @var OrderCore $order */
		$order = new Order((int)$id_order);

		$result = self::getByOrderReference($order->reference);

		$transactions = array();

        /** @var eMerchantPayTransaction $transaction */
        foreach ($result as $transaction) {
			$transactions[] = $transaction->getFields();
		}

		// Sort the transactions list in the following order:
		//
		// 1. Sort by timestamp (date), i.e. most-recent transactions on top
		// 2. Sort by relations, i.e. every parent has the child nodes immediately after

		// Ascending Date/Timestamp sorting
		uasort($transactions, function($a, $b) {
			// sort by timestamp (date) first
			if (@$a["date_add"] == @$b["date_add"]){
				return 0;
			}
			return (@$a["date_add"] > @$b["date_add"]) ? 1 : -1;
		});

		// Process individual fields
		foreach ($transactions as &$transaction) {
			$transaction['amount'] = number_format($transaction['amount'], 2);

			$transaction['date_add'] = date("H:i:s \n m/d/Y", strtotime($transaction['date_add']));

			if (in_array( $transaction['type'], array( 'authorize', 'authorize3d')) && $transaction['status'] == 'approved') {
				$transaction['can_capture'] = true;
			}
			else {
				$transaction['can_capture'] = false;
			}

			if (in_array( $transaction['type'], array( 'authorize', 'authorize3d', 'capture', 'sale', 'sale3d', 'init_recurring_sale', 'recurring_sale' )) && $transaction['status'] == 'approved') {
				$transaction['can_refund'] = true;
			} else {
				$transaction['can_refund'] = false;
			}

			if (in_array( $transaction['type'], array( 'authorize', 'authorize3d', 'capture', 'sale', 'sale3d', 'init_recurring_sale', 'recurring_sale', 'refund' )) && $transaction ) {
				$transaction['can_void'] = true;
			} else {
				$transaction['can_void'] = false;
			}
		}

		// Create the parent/child relations from a flat array
		$array_asc = array();

		foreach($transactions as $key => $val){
			// create an array with ids as keys and children
			// with the assumption that parents are created earlier.
			// store the original key
			$array_asc[$val['id_unique']] = array_merge($val, array('org_key' => $key));

			if (isset($val['id_parent']) && (bool)$val['id_parent']) {
				$array_asc[$val['id_parent']]['children'][] = $val['id_unique'];
			}
		}

		// Order the parent/child entries
		$transactions = array();

		foreach($array_asc as $val) {

			/*
			if (isset($val['id_parent']) && $val['id_parent']){
				continue;
			}
			*/

			self::treeTransactionSort($transactions, $val, $array_asc);
		}

		return $transactions;
	}

	/**
	 * Recursive function used in the process of sorting
	 * the Transactions list
	 *
	 * @param $array_out array
	 * @param $val array
	 * @param $array_asc array
	 */
	public static function treeTransactionSort(&$array_out, $val, $array_asc)
    {
		if (isset($val['org_key'])) {
			$array_out[ $val['org_key'] ] = $val;

			if ( isset( $val['children'] ) && sizeof( $val['children'] ) ) {
				foreach ( $val['children'] as $id ) {
					self::treeTransactionSort( $array_out, $array_asc[ $id ], $array_asc );
				}
			}
			unset( $array_out[ $val['org_key'] ]['children'], $array_out[ $val['org_key'] ]['org_key'] );
		}
	}

	/**
	 * Import a Genesis Response Object
	 *
	 * @param stdClass $response
	 */
	public function importResponse($response)
    {
		include_once dirname(__FILE__) . '/../lib/genesis/vendor/autoload.php';

		if (isset($response->unique_id)) {
			$this->id_unique = $response->unique_id;
		}
		if (isset($response->transaction_type)) {
			$this->type = $response->transaction_type;
		}
		if (isset($response->status)) {
			$this->status = $response->status;
		}
		if (isset($response->message)) {
			$this->message = $response->message;
		}
		if (isset($response->currency)) {
			$this->currency = $response->currency;
		}
		if (isset($response->amount)) {
			$this->amount = $response->amount;
		}
        if (isset($response->terminal_token)) {
            $this->terminal = $response->terminal_token;
        }
        if (isset($response->payment_transaction->terminal_token)) {
            $this->terminal = $response->payment_transaction->terminal_token;
        }
	}

	/**
	 * Update the order history of the order related to the transaction
	 *
	 * @param int   $status             Order Status Id
	 * @param bool  $notify_customer    Should we notify the customer?
	 */
	public function updateOrderHistory($status, $notify_customer = null)
    {
		$order = $this->getOrder();

		/** @var OrderHistoryCore $new_history */
		$new_history = new OrderHistory();
		$new_history->id_order = (int)$order->id;
		$new_history->changeIdOrderState((int)$status, $order, true);

		if ($notify_customer) {
			$new_history->addWithemail(true);
		}
	}

}