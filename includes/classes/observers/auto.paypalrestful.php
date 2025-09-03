<?php
/**
 * Part of the paypalr (PayPal Restful Api) payment module.
 * This observer-class handles the JS SDK integration logic.
 * It also watches for notifications from the 'order_total' class,
 * introduced in this (https://github.com/zencart/zencart/pull/6090) Zen Cart PR,
 * to determine an order's overall value and what amounts each order-total
 * module has added/subtracted to the order's overall value.
 *
 * Last updated: v1.2.0
 */

use PayPalRestful\Api\Data\CountryCodes;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Zc2Pp\Amount;
use Zencart\Traits\ObserverManager;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

class zcObserverPaypalrestful
{
    use ObserverManager;

    protected array $lastOrderValues = [];
    protected array $orderTotalChanges = [];
    protected bool $freeShippingCoupon = false;
    protected bool $headerAssetsSent = false;
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

        // -----
        // Attach to header to render JS SDK assets.
        $this->attach($this, ['NOTIFY_HTML_HEAD_JS_BEGIN', 'NOTIFY_HTML_HEAD_END']);
        // Attach to footer to instantiate the JS.
        $this->attach($this, ['NOTIFY_FOOTER_END']);
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

    public function updateNotifyHtmlHeadEnd(&$class, $eventID, $current_page_base): void
    {
        // This is a fallback for older versions, to ensure we only output the header JS once.
        if ($this->headerAssetsSent) {
            return;
        }
        $this->outputJsSdkHeaderAssets($current_page_base);
    }
    public function updateNotifyHtmlHeadJsBegin(&$class, $eventID, $current_page_base): void
    {
        $this->outputJsSdkHeaderAssets($current_page_base);
        $this->headerAssetsSent = true;
    }
    public function updateNotifyFooterEnd(&$class, $eventID, $current_page_base): void
    {
        $this->outputJsFooter($current_page_base);
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


    /** Internal methods **/

    protected function outputJsSdkHeaderAssets($current_page): void
    {
        global $current_page_base, $order, $tpl_page_body, $paypalSandboxBuyerCountryCodeOverride, $paypalSandboxLocaleOverride;
        if (empty($current_page)) {
            $current_page = $current_page_base;
        }

        $js_url = 'https://www.paypal.com/sdk/js';
        $js_fields = [];
        $js_scriptparams = [];

        $js_fields['client-id'] = MODULE_PAYMENT_PAYPALR_SERVER === 'live' ? MODULE_PAYMENT_PAYPALR_CLIENTID_L : MODULE_PAYMENT_PAYPALR_CLIENTID_S;

        if (MODULE_PAYMENT_PAYPALR_SERVER === 'sandbox') {
            $js_fields['client-id'] = 'sb'; // 'sb' for sandbox
            $js_fields['debug'] = 'true'; // sandbox only, un-minifies the JS
            $buyerCountry = CountryCodes::ConvertCountryCode($order->delivery['country']['iso_code_2'] ?? 'US');
            $js_fields['buyer-country'] = $paypalSandboxBuyerCountryCodeOverride ?? $buyerCountry; // sandbox only
            $js_fields['locale'] = $paypalSandboxLocaleOverride ?? 'en_US'; // only passing this in sandbox to allow override testing; otherwise just letting it default to customer's browser
        }

        if (!empty($order->info['currency'])) {
            $amount = new Amount($order->info['currency']);
            $js_fields['currency'] = $amount->getDefaultCurrencyCode();
        }

        // possible components: buttons,marks,messages,funding-eligibility,hosted-fields,card-fields,applepay
        $js_fields['components'] = 'buttons,marks,messages,card-fields,funding-eligibility';

        // commit value is a boolean string; 'true' = pay-now, 'false'=continue to final confirmation
        $confirmation_pages = [FILENAME_CHECKOUT_CONFIRMATION];
        if (defined('FILENAME_CHECKOUT_ONE_CONFIRMATION')) {
            $confirmation_pages[] = FILENAME_CHECKOUT_ONE_CONFIRMATION;
        }
        if (in_array($current_page, $confirmation_pages, true)) {
            $js_fields['commit'] = 'true'; // pay-now
        } else {
            $js_fields['commit'] = 'false'; // will confirm on subsequent page
        }

        if (str_starts_with(MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE, 'Auth')) {
//            $js_fields['intent'] = 'AUTHORIZE'; // default is 'CAPTURE', so we only set intent in cases where we want to override that
        }

        // filter MODULE_PAYMENT_PAYPALR_ALLOWED_METHODS to specify which ones the merchant has opted to disable
        $potentialMethods = ['card', 'credit', 'paylater', 'venmo', 'bancontact', 'blik', 'eps', 'ideal', 'mercadopago', 'mybank', 'p24', 'sepa'];
        $enabledMethods = array_map(static fn($value) => strstr($value, '=', true) ?: $value, explode(', ', MODULE_PAYMENT_PAYPALR_ALLOWED_METHODS));
        $disabledMethods = [];
        foreach ($potentialMethods as $value) {
            if (!in_array($value, $enabledMethods, true)) {
                $disabledMethods[] = $value;
            }
        }
        $js_fields['disable-funding'] = implode(',', $disabledMethods);

        // ---
        $js_page_type = match (true) {
            str_starts_with($current_page, "checkout") => 'checkout',
            str_contains(MODULE_PAYMENT_PAYPALR_BUTTON_PLACEMENT, 'Cart') && $current_page === 'shopping_cart' => 'cart',
            str_contains(MODULE_PAYMENT_PAYPALR_BUTTON_PLACEMENT, 'Cart') && $current_page === 'mini-cart' => 'mini-cart',
            str_contains(MODULE_PAYMENT_PAYPALR_BUTTON_PLACEMENT, 'Product') && in_array($current_page, zen_get_buyable_product_type_handlers(), true) => 'product-details',
            str_contains(MODULE_PAYMENT_PAYPALR_BUTTON_PLACEMENT, 'Listing') && ($tpl_page_body ?? null) === 'tpl_index_product_list.php' => 'product-listing',
            str_contains(MODULE_PAYMENT_PAYPALR_BUTTON_PLACEMENT, 'Search') && $current_page === 'advanced_search_result' => 'search-results',
            default => null,
        };
        if ($js_page_type) {
            $js_scriptparams[] = 'data-page-type="' . $js_page_type . '"';
        }

        $js_fields['integration-date'] = '2025-08-01';
        $js_scriptparams[] = 'data-partner-attribution-id="ZenCart_SP_PPCP"';
        $js_scriptparams[] = 'data-namespace="PayPalSDK"';

        ?>
<link title="PayPal Cardfields CSS" href="https://www.paypalobjects.com/webstatic/en_US/developer/docs/css/cardfields.css" rel="stylesheet"/>
<script title="PayPalSDK" src="<?= $js_url . '?'. str_replace('%2C', ',', http_build_query($js_fields)) ?>" <?= implode(' ', $js_scriptparams) ?> async></script>
<?php
    }

    protected function outputJsFooter($current_page): void
    {
        $buttonProps = $this->getButtonProps();
        $messageProps = $this->getMessageProps();

        $message_selector = match($current_page) {
            'product_info' => '.productPriceBottomPrice',
            FILENAME_SHOPPING_CART => '#paypal-message-container',
            default => '#paypal-message-container',
        };
?>
    <script title="PayPal Functions">
        $(document).ready(function () {
            let ppButtons = PayPalSDK.Buttons(<?= json_encode($buttonProps) ?>);
            // If using App Switch, the buyer may end up in a new tab depending on the browser
            // To trigger flow completion, we call resume():
            if (ppButtons.hasReturned()) {
                ppButtons.resume();
            } else {
                ppButtons.render("#paypal-buttons-container");
            }

            PayPalSDK.Messages(<?= json_encode($messageProps) ?>).render('<?= $message_selector ?>');
        });

        function createOrder() {
            // This function is called when the customer clicks the PayPal button.
            // It should return a promise that resolves to the order ID.
            let data = $('form[name="cart_quantity"]').serializeArray();
            let coupon = $('#disc-ot_coupon');
            if (coupon) {
                data.push({name: 'disc-ot_coupon', value: coupon});
            }

            return zcJS.ajax({
                url: "ajax.php?act=ajaxPaypalRest&method=getOrder",
                data: data
            }).done(function (response) {
                // The order ID is used to create the PayPal transaction.
                return response.orderID;
            });
        }

        function onApprove(data, actions) {
            // This function is called when the customer approves the payment,
            // and initiates a call to capture the payment.
            // Here we capture the order on the backend.
            return zcJS.ajax({
                url: "ajax.php?act=ajaxPaypalRest&method=captureOrder",
                data: data
            }).done(function (response) {
                if (response.order.status === 'COMPLETED') {
                    window.location.href = "<?= zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL') ?>";
                } else {
                    // @TODO - handle INSTRUMENT_DECLINED responses by re-displaying PayPal button which will re-initiate the call but WITH the same previous orderID, so PayPal uses a Retry flow excluding the failed funding source.
                    throw new Error('Payment not completed');
                }
            });
        }

        function onError(err) {
            // This function is called when there is an error during the payment process.
            // We have many options for handling an error. This is just something overly basic.
            console.error('PayPal Error:', err);
            alert('An error occurred while processing your payment. Please try again.');
        }

        // onInit is called when the button first renders
        function onInit(data, actions) {
            // Disable the buttons
            actions.disable();

            // Listen for changes to the checkbox
            document.querySelector("#checkbox").addEventListener("change", function (event) {
                // Enable or disable the button when it is checked or unchecked
                if (event.target.checked) {
                    actions.enable();
                } else {
                    actions.disable();
                }
            });
        }

        // onClick is called when the button is selected
        function onClick() {
            // Show a validation error if the checkbox isn't checked
            if (!document.querySelector("#checkbox").checked) {
                document.querySelector("#error").classList.remove("hidden");
            }
        }

        function onCancel(data) {
            // Show a cancel page, or return to cart
            window.location.assign("/index.php?main_page=shopping_cart");
        }

        const cardFields = PayPalSDK.CardFields({
            createOrder,
            onApprove,
            onError
        });

        function onShippingAddressChange(data, actions) {
            return zcJS.ajax({
                url: "ajax.php?act=ajaxPaypalRest&method=shippingAddressChange",
                data: data
            }).done(function (response) {
                if (response.error === false) {
                    return actions.resolve();
                }
                if (response.error === 'method_unavailable') {
                    return actions.reject(data.errors.METHOD_UNAVAILABLE);
                }
                if (response.error === 'country') {
                    return actions.reject(data.errors.COUNTRY_ERROR);
                }
                if (response.error === 'state') {
                    return actions.reject(data.errors.STATE_ERROR);
                }
                if (response.error === 'zip') {
                    return actions.reject(data.errors.ZIP_ERROR);
                }
                if (response.error === 'address') {
                    return actions.reject(data.errors.ADDRESS_ERROR);
                }
            });
        }

        function onShippingOptionsChange(data, actions) {
            return zcJS.ajax({
                url: "ajax.php?act=ajaxPaypalRest&method=shippingOptionsChange",
                data: data
            }).done(function (response) {
                if (response.error === 'method_unavailable') {
                    return actions.reject(data.errors.METHOD_UNAVAILABLE);
                }
                if (response.error === 'unavailable_at_this_store') {
                    return actions.reject(data.errors.STORE_UNAVAILABLE);
                }
            });
        }
    </script>
<?php
        return;
    }

    /**
     * Button object for setting up the transaction when a payment button is selected
     * @reference https://developer.paypal.com/sdk/js/reference
     */
    protected function getButtonProps(): array
    {
        return [
            // https://developer.paypal.com/sdk/js/reference/#label
            'style' => [
                // 'layout' => 'horizontal', // horizontal or vertical
                // 'color' => 'gold', // gold, blue, silver, white, black
                // 'shape' => 'rect', // rect, pill, sharp
                // 'label' => 'paypal',
                // 'borderRadius' => 0, // 0-50
                // 'size' = '',
                // 'height' => '55', // 25-55
            ],

            // https://developer.paypal.com/docs/checkout/standard/customize/app-switch/
            'appSwitchWhenAvailable' =>  true, // indicator to trigger App Switch on eligible devices

//            'createOrder' => 'createOrder()',
//            'onApprove' => 'onApprove()',
//            'onCancel' => 'onCancel()',
//            'onError' => 'onError()',
//            'onShippingAddressChange' => 'onShippingAddressChange()',
//            'onShippingOptionsChange' => 'onShippingOptionsChange()',
        ];
    }

    protected function getMessageProps(): array
    {
        global $current_page_base, $tpl_page_body;
        return [
            'style' => [
                'layout' => 'text',
                'logo' => [
                    'type' => 'inline',
                    'position' => 'top',
                ],
                'text' => [
                    'align' => 'right',
                ],
            ],
            'pageType' => match(true) {
                str_starts_with($current_page_base, "checkout") => 'checkout',
                $current_page_base === 'shopping_cart' => 'cart',
                $current_page_base === 'mini-cart' => 'mini-cart',
                in_array($current_page_base, zen_get_buyable_product_type_handlers(), true) => 'product-details',
                ($tpl_page_body ?? null) === 'tpl_index_product_list.php' => 'product-listing',
                $current_page_base === 'advanced_search_result' => 'search-results',
                default => 'home',
            },
        ];
    }

}





/*****************************/
// Backward Compatibility for prior to ZC v2.2.0
if (!function_exists('zen_get_buyable_product_type_handlers')) {
    /**
     * Get a list of product page names that identify buyable products.
     * This allows us to mark a page as containing a product which can
     * be allowed to add-to-cart or buy-now with various modules.
     */
    function zen_get_buyable_product_type_handlers(): array
    {
        global $db;
        $sql = "SELECT type_handler from " . TABLE_PRODUCT_TYPES . " WHERE allow_add_to_cart = 'Y'";
        $results = $db->Execute($sql);
        $retVal = [];
        foreach ($results as $result) {
            $retVal[] = $result['type_handler'] . '_info';
        }
        return $retVal;
    }
}
/*****************************/
