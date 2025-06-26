<?php
/**
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id:  $
 *
 *  Last updated: v1.2.0
 *
 */

global $psr4Autoloader;
$psr4Autoloader->addPrefix('PayPalRestful\Admin', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Admin');
$psr4Autoloader->addPrefix('PayPalRestful\Admin\Formatters', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Admin/Formatters');
$psr4Autoloader->addPrefix('PayPalRestful\Api', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Api');
$psr4Autoloader->addPrefix('PayPalRestful\Api\Data', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Api/Data');
$psr4Autoloader->addPrefix('PayPalRestful\Common', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Common');
$psr4Autoloader->addPrefix('PayPalRestful\Token', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Token');
$psr4Autoloader->addPrefix('PayPalRestful\Webhooks', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Webhooks');
$psr4Autoloader->addPrefix('PayPalRestful\Zc2Pp', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Zc2Pp');
