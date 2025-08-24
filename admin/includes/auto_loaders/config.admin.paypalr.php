<?php

/**
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id:  $
 *
 * Part of Paypal RESTful module
 *
 * Last updated: v1.2.0
 *
 */
// Loading after point 40 to ensure that init_general_funcs has already loaded core functions, since we have some fallbacks in case they're not defined.
$autoLoadConfig[45][] = [
    'autoType' => 'class',
    'loadFile' => 'auto.paypalrestful.php',
    'classPath' => DIR_FS_CATALOG . DIR_WS_CLASSES . 'observers/',
];
$autoLoadConfig[175][] = [
    'autoType' => 'classInstantiate',
    'className' => 'zcObserverPaypalrestful',
    'objectName' => 'zcObserverPaypalrestful',
];
