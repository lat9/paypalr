<?php
/**
 * Part of the paypalr (PayPal Restful Api) payment module.
 * It also watches for notifications from the 'order_total' class,
 * introduced in this (https://github.com/zencart/zencart/pull/6090) Zen Cart PR,
 * to determine an order's overall value and what amounts each order-total
 * module has added/subtracted to the order's overall value.
 *
 * Last updated: v1.2.0
 */
 
use PayPalRestful\Api\PayPalRestfulApi;
use Zencart\Traits\ObserverManager;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

class zcObserverPaypalrestful
{
    use ObserverManager;

    protected array $lastOrderValues = [];
    protected array $orderTotalChanges = [];
    protected bool $freeShippingCoupon = false;
    protected bool $adminBeforeInsertDone = false;

    public function __construct()
    {
        // -----
        // If the paypalr payment-module isn't installed or isn't configured to be
        // enabled, nothing further to do here.
        //
        if (!defined('MODULE_PAYMENT_PAYPALR_STATUS') || MODULE_PAYMENT_PAYPALR_STATUS !== 'True') {
            return;
        }

        if (IS_ADMIN_FLAG) {
            $this->attach($this, ['ZEN_UPDATE_ORDERS_HISTORY_AFTER_INSERT']);
            if (zen_get_zcversion() < 2.2) {
                $this->attach($this, ['ZEN_UPDATE_ORDERS_HISTORY_BEFORE_INSERT']);
            }
            return;
        }

        // -----
        // If currently on either the 3-page or OPC checkout-confirmation pages, need to monitor
        // calls to the order-totals' pre_confirmation_check method. That method is run on that
        // page prior to paypalr's pre_confirmation_check method.
        //
        // NOTE: The page that's set during the AJAX checkout-payment class is 'index'!
        //
        global $current_page_base;
        $pages_to_watch = [
            FILENAME_CHECKOUT_CONFIRMATION,
            FILENAME_DEFAULT,
        ];
        if (defined('FILENAME_CHECKOUT_ONE_CONFIRMATION')) {
            $pages_to_watch[] = FILENAME_CHECKOUT_ONE_CONFIRMATION;
        }
        if (in_array($current_page_base, $pages_to_watch)) {
            $this->attach($this, [
                'NOTIFY_ORDER_TOTAL_PRE_CONFIRMATION_CHECK_STARTS',
                'NOTIFY_ORDER_TOTAL_PRE_CONFIRMATION_CHECK_NEXT',
                'NOTIFY_OT_COUPON_CALCS_FINISHED',
            ]);
        // -----
        // If currently on the checkout_process page, need to monitor calls to the
        // order-totals' process method.  That method's run on that page prior to
        // paypalr's before_process method.
        //
        } elseif ($current_page_base === FILENAME_CHECKOUT_PROCESS) {
            $this->attach($this, [
                'NOTIFY_ORDER_TOTAL_PROCESS_STARTS',
                'NOTIFY_ORDER_TOTAL_PROCESS_NEXT',
                'NOTIFY_OT_COUPON_CALCS_FINISHED',
            ]);
        }
    }

    // -----
    // Notification 'update' handlers for the notifications from order-totals' pre_confirmation_check method.
    //
    public function updateNotifyOrderTotalPreConfirmationCheckStarts(&$class, $eventID, array $starting_order_info)
    {
        $this->setLastOrderValues($starting_order_info['order_info']);
    }
    public function updateNotifyOrderTotalPreConfirmationCheckNext(&$class, $eventID, array $ot_updates)
    {
        $this->setOrderTotalUpdate($ot_updates);
    }

    // -----
    // Notification 'update' handlers for the notifications from order-totals' process method.
    //
    public function updateNotifyOrderTotalProcessStarts(&$class, $eventID, array $starting_order_info)
    {
        $this->setLastOrderValues($starting_order_info['order_info']);
    }
    public function updateNotifyOrderTotalProcessNext(&$class, $eventID, array $ot_updates)
    {
        $this->setOrderTotalUpdate($ot_updates);
    }

    // -----
    // Notification 'update' handler for ot_coupon, letting us know if the associated
    // coupon provides free shipping.
    //
    public function updateNotifyOtCouponCalcsFinished(&$class, $eventID, array $parameters)
    {
        $coupon_type = $parameters['coupon']['coupon_type'];
        $this->freeShippingCoupon = in_array($coupon_type, ['S', 'E', 'O']);
    }

    /**
     * @param array $data [int orders_id, int orders_status_id, date_added, int customer_notified, comments, updated_by]
     */
    public function updateZenUpdateOrdersHistoryBeforeInsert(&$class, $eventID, $null, array $data): void
    {
        $this->updateZenUpdateOrdersHistoryAfterInsert($class, $eventID, 0, $data);
        $this->detach($this, ['ZEN_UPDATE_ORDERS_HISTORY_BEFORE_INSERT']);
        $this->adminBeforeInsertDone = true;
    }

    /**
     * @param array $data [int orders_id, int orders_status_id, date_added, int customer_notified, comments, updated_by]
     */
    public function updateZenUpdateOrdersHistoryAfterInsert(&$class, $eventID, int $osh_id, array $data): void
    {
        if ($this->adminBeforeInsertDone) {
            // avoid double-processing when attached to an older version's ZEN_UPDATE_ORDERS_HISTORY_BEFORE_INSERT
            return;
        }
        // Parse POST for tracking IDs.
        $track_ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $track_id_var = "track_id$i";
            if (empty($_POST[$track_id_var])) {
                continue;
            }
            $track_ids[$i] = str_replace(' ', '', zen_db_prepare_input($_POST[$track_id_var]));
        }
        // Abort if no tracking IDs found.
        if (count($track_ids) === 0) {
            return;
        }
        $order_id = (int)$data['orders_id'];
        // Lookup the initial PayPal transaction record related to this order.
        $paypalLookup = $GLOBALS['db']->Execute(
            "SELECT txn_id, txn_type
                 FROM " . TABLE_PAYPAL . "
                 WHERE order_id = $order_id
                 ORDER BY date_added, parent_txn_id, paypal_ipn_id", 1
        );
        $paypal = $paypalLookup->EOF ? [] : $paypalLookup->fields;
        if (empty($paypal)) {
            return;
        }

        require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';
        [$client_id, $secret] = \paypalr::getEnvironmentInfo();
        $ppr = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER, $client_id, $secret);

        foreach ($track_ids as $i => $tracking_number) {
            if (empty($tracking_number)) {
                continue;
            }
            // Add the tracking number to the PayPal transaction.
            $carrier_name = defined("CARRIER_NAME_$i") && !empty("CARRIER_NAME_$i") ? constant("CARRIER_NAME_$i") : 'OTHER';
            $result = $ppr->updatePackageTracking($paypal['txn_id'], $tracking_number, $carrier_name, 'ADD');
        }

        // De-register, to prevent multiple insertions in this cycle.
        $this->detach($this, ['ZEN_UPDATE_ORDERS_HISTORY_AFTER_INSERT']);
    }

    // -----
    // Set the last order-values seen, based on the associated 'start' notification.
    //
    protected function setLastOrderValues(array $order_info)
    {
        $this->lastOrderValues = [
            'total' => $order_info['total'],
            'tax' => $order_info['tax'],
            'subtotal' => $order_info['subtotal'],
            'shipping_cost' => $order_info['shipping_cost'],
            'shipping_tax' => $order_info['shipping_tax'],
            'tax_groups' => $order_info['tax_groups'],
        ];
    }

    // -----
    // Determine the difference to the current order's values for the current
    // order-total module.
    //
    // The $ot_updates is an associative array containing these keys:
    //
    // - class ........ The name of the order-total module currently being processed.
    // - order_info ... Contains the $order->info array *after* the order-total has been processed.
    // - output ....... The 'output' provided by the order-total currently being processed.
    //
    // Note: Fuzzy comparisons are used on values throughout this method, since we're dealing
    // with floating-point values.
    //
    protected function setOrderTotalUpdate(array $ot_updates)
    {
        $updated_order = $ot_updates['order_info'];

        // -----
        // Loop through each of the 'pertinent' elements of the $order->info array, to
        // see what (if any) changes have been provided by the current order-total module.
        //
        $diff = [];
        foreach ($this->lastOrderValues as $key => $value) {
            // -----
            // All elements _other than_ the tax_groups are scalar values, just
            // check if the current order-total has made changes to the value.
            //
            if ($key !== 'tax_groups') {
                $value_difference = $updated_order[$key] - $value;
                if ($value_difference != 0) {
                    $diff[$key] = $value_difference;
                }
                continue;
            }

            // -----
            // Loop through each of the tax-groups *last seen* in the order, determining
            // whether the current order-total has make changes.
            //
            // Once processed, remove the tax-group from the updates so that any
            // *additions* can be handled.
            //
            foreach ($this->lastOrderValues['tax_groups'] as $tax_group_name => $tax_value) {
                $value_difference = $updated_order['tax_groups'][$tax_group_name] - $tax_value;
                if ($value_difference != 0) {
                    $diff['tax_groups'][$tax_group_name] = $value_difference;
                }
                unset($updated_order['tax_groups'][$tax_group_name]);
            }

            // -----
            // If any tax-groups remain in the updated order-info, then the current
            // order-total has *added* that tax-group element to the order.
            //
            foreach ($updated_order['tax_groups'] as $tax_group_name => $tax_value) {
                if ($tax_value != 0) {
                    $diff['tax_groups'][$tax_group_name] = $tax_value;
                }
            }
        }

        // -----
        // If the current order-total has made changes to the order-info, record
        // that information for use by the paypalr payment-module's processing.
        //
        if (count($diff) !== 0) {
            $this->orderTotalChanges[$ot_updates['class']] = [
                'diff' => $diff,
                'output' => $ot_updates['ot_output'],
            ];
        }

        // -----
        // Register the order-info after the current order-total has run.  These
        // values are used when checking the next order-total's changes; the
        // final result seen will be the order-info that's associated with
        // the order itself.
        //
        $this->setLastOrderValues($ot_updates['order_info']);
    }

    // -----
    // Public methods (used by the paypalr payment-module) to retrieve the results
    // of the notifications' processing.
    //
    // Note: If getLastOrderValues returns an empty array, the implication is that
    // the required notifications have not been added to the order_total.php class.
    //
    public function getLastOrderValues(): array
    {
        return $this->lastOrderValues;
    }
    public function getOrderTotalChanges(): array
    {
        return $this->orderTotalChanges;
    }
    public function orderHasFreeShippingCoupon(): bool
    {
        return $this->freeShippingCoupon;
    }
}
