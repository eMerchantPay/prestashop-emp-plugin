<?php

include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

if (!defined('_PS_VERSION_'))
	exit;

$module_name = basename(__DIR__);

var_dump($_POST);

if (!Tools::getIsset('submit' . $module_name))
	exit;

/** @var eMerchantPay $eMerchantPay */
$eMerchantPay = Module::getInstanceByName($module_name);
$eMerchantPay->doPayment();