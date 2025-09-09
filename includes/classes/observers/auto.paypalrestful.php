<?php
/**
 * Part of the paypalr (PayPal Restful Api) payment module.
 * This observer-class handles the JS SDK integration logic.
 * It also watches for notifications from the 'order_total' class,
 * introduced in this (https://github.com/zencart/zencart/pull/6090) Zen Cart PR,
 * to determine an order's overall value and what amounts each order-total
 * module has added/subtracted to the order's overall value.
 *
 * Last updated: v1.3.0
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

    public function __construct()
    {
        // -----
        // If the paypalr payment-module isn't installed or isn't configured to be
        // enabled, nothing further to do here.
        //
        if (!defined('MODULE_PAYMENT_PAYPALR_STATUS') || MODULE_PAYMENT_PAYPALR_STATUS !== 'True') {
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
<script title="PayPalSDK" id="PayPalJSSDK" src="<?= $js_url . '?'. str_replace('%2C', ',', http_build_query($js_fields)) ?>" <?= implode(' ', $js_scriptparams) ?> async></script>
<script title="PayPal page type" id="PayPalPageType">
    window.paypalPageType = '<?= $js_page_type ?? 'other' ?>';
</script>
<?php
    }

    protected function outputJsFooter($current_page): void
    {
?>
<script title="PayPal Functions">
<?= file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.jssdk.js'); ?>
</script>
<?php
        [$listings_selector, $message_container, $price_selector, $style, $pageType] = $this->getMessageProps();
?>
<script title="PayPal Messages">
    jQuery(document).ready(function () {
        // Wait for the JS SDK to load
        jQuery("#PayPalJSSDK").on("load", function () {
            // Init paypal pay-later/etc message display

            let msgsContainer = "<?= $message_container ?>";
            let msgsStyle = '<?= json_encode($style) ?>';
            let msgsPageType = "<?= $pageType ?>";

            // Grab all product listings on the page
            const productListings = Array.from(document.querySelectorAll('<?= $listings_selector ?>'));

            // Loop through each product listing
            productListings.forEach((listing) => {
                // Extract the price of the product by grabbing the text content of the
                // element that contains the price. Use .slice(1) to remove the leading
                // currency symbol.
                const price = Number(
                    listing.querySelector('<?= $price_selector ?>').textContent.slice(1).replace(/,/, '')
                );

                // Grab child element of this .listing element that has the
                // pp-message classname
                const messageElement = listing.querySelector('<?= $message_container ?>');

                // Set data-pp-amount on this element.
                // The PayPal SDK monitors message elements for changes to its attributes,
                // so the message is updated automatically to reflect this amount.
                messageElement.setAttribute('data-pp-amount', price);
            });

            // Render any PayPal PayLater messages if an appropriate container exists.
            if (document.getElementById(msgsContainer)) {
                PayPalSDK.Messages({
                    style: msgsStyle,
                    pageType: msgsPageType,
                }).render(msgsContainer);
            }
        });
    });

</script>
        <?php
        return;
    }

    protected function getMessageProps(): array
    {
        global $current_page_base, $tpl_page_body;

        switch ($current_page_base) {
            case 'product_info':
                $listings_selector = '#productsPriceBottom-card';
                $message_container = '.productPriceBottomPrice';
                $price_selector = '.productBasePrice';
                $style = [
                    'layout' => 'text',
                    'logo' => [
                        'type' => 'inline',
                        'position' => 'top',
                    ],
                ];
                break;
            case FILENAME_SHOPPING_CART:
                $listings_selector = '#shoppingCartDefault';
                $message_container = '#paypal-message-container';
                $price_selector = '#cart-total';
                $style = [
                    'layout' => 'text',
                    'logo' => [
                        'type' => 'inline',
                        'position' => 'top',
                    ],
                    'text' => [
                        'align' => 'right',
                    ],
                ];
                break;
            default:
                $listings_selector = '#checkout_payment';
                $message_container = '#paypal-message-container';
                $price_selector = '#ottotal > .ot-text';
                $style = [
                    'layout' => 'text',
                    'logo' => [
                        'type' => 'inline',
                        'position' => 'top',
                    ],
                    'text' => [
                        'align' => 'right',
                    ],
                ];
                break;
        }

        $pageType = match(true) {
            str_starts_with($current_page_base, "checkout") => 'checkout',
            $current_page_base === 'shopping_cart' => 'cart',
            $current_page_base === 'mini-cart' => 'mini-cart',
            in_array($current_page_base, zen_get_buyable_product_type_handlers(), true) => 'product-details',
            ($tpl_page_body ?? null) === 'tpl_index_product_list.php' => 'product-listing',
            $current_page_base === 'advanced_search_result' => 'search-results',
            default => 'home',
        };

        return [$listings_selector, $message_container, $price_selector, $style, $pageType];
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
