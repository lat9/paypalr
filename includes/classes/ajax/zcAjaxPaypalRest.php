<?php

use PayPalRestful\Common\Logger;

/**
 * zcAjaxPaypalRest
 *
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id:  $
 *
 * Last updated: v1.3.0
 */

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

class zcAjaxPaypalRest extends base
{
    protected bool $enableDebugFileLogging = true;
    protected Logger $log;

    public function __construct()
    {
        // Create logger, just for logging to /logs directory
        $this->log = new Logger('ajax');

        // Enable logging
        if ($this->enableDebugFileLogging) {
            $this->log->enableDebug();
        }
    }

    // session flags for:
    // customer record cleanup
    // address book cleanup


    /**
     *
     */
    public function getOrder()
    {
        require DIR_WS_CLASSES . 'order.php';
        require DIR_WS_CLASSES . 'shipping.php';
        require DIR_WS_CLASSES . 'payment.php';
        require DIR_WS_CLASSES . 'order_total.php';

        /**
         * $formdata is the form contents from the `cart_quantity` field on
         * either the product_info (qty, attributes) or shopping_cart page (just qty/delete fields).
         * Also contains `coupon` if on a checkout page and discount coupon code was typed in but not-yet-applied.
         */
        $formdata = $_POST['formdata'] ?? [];
        $coupon = $_POST['coupon'] ?? '';
        $page_url = $_POST['page_url'] ?? '';
        $ppr_type = $_POST['ppr_type'] ?? '';

        $this->log->write('zcAjaxPaypalRest::getOrder data: ' . print_r($formdata, true), true, 'after');

// @TODO - no customer yet on product page, for example.
        $customer = new Customer($_SESSION['customer_id']);
        if (zen_get_customer_validate_session($_SESSION['customer_id']) === false) {
            // @TODO - Create a customer here?
        }



        // if free shipping selected, but order no longer qualifies, remove free-shipping
        if (isset($_SESSION['shipping']['id'])
            && $_SESSION['shipping']['id'] === 'free_free'
            && $_SESSION['cart']->get_content_type() !== 'virtual'
            && defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING')
            && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING === 'true'
            && defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER')
            && $_SESSION['cart']->show_total() < MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) {
            unset($_SESSION['shipping']);
        }
        // if no shipping method has been selected, autodetect the cheapest available shipping method
        if (empty($_SESSION['shipping'])) {
            global $shipping_modules;
            $shipping_modules ??= new shipping();
            $_SESSION['shipping'] = $shipping_modules->cheapest();
        }
//        $shipping_modules = new shipping($_SESSION['shipping']);


// if no billing destination address was selected, use the customers own address as default
        if (empty($_SESSION['billto'])) {
            $_SESSION['billto'] = $_SESSION['customer_default_address_id'];
        } else {
            global $db;
            // verify the selected billing address
            $check_address_query = "SELECT count(*) AS total FROM " . TABLE_ADDRESS_BOOK . "
                          WHERE customers_id = :customersID
                          AND address_book_id = :addressBookID";

            $check_address_query = $db->bindVars($check_address_query, ':customersID', $_SESSION['customer_id'], 'integer');
            $check_address_query = $db->bindVars($check_address_query, ':addressBookID', $_SESSION['billto'], 'integer');
            $check_address = $db->Execute($check_address_query);

            if ($check_address->fields['total'] != '1') {
                $_SESSION['billto'] = $_SESSION['customer_default_address_id'];
                $_SESSION['payment'] = '';
            }
        }

        global $order;
        $order = new \order();
        $order_total_modules = new \order_total();
        $order_total_modules->collect_posts();
        $order_total_modules->pre_confirmation_check();

        global $payment_modules;
        $payment_modules = new \payment('paypalr');
        //$this->paypalr = $GLOBALS[$payment_modules->selected_module];
        $payment_modules->update_status();
        if (is_array($payment_modules->modules)) {
            $payment_modules->pre_confirmation_check();
        }

        /**
         * The ajax return data should resolve to a PayPal order ID which can be presented
         * to the buyer via PayPal's popup, for selection of a funding source.
         */
        return ([
            'order' => $_SESSION['PayPalRestful']['Order'],
        ]);
    }

    public function getCardFieldsOrder()
    {
        // @TODO - rework this for CardFields instead of PayPal Wallet
        return $this->getOrder();
    }

    /**
     * Given the supplied PayPal order ID, capture the order.
     * This involves storing the order and completing payment.
     * May also involve account-creation; or at least looking up the customer by email address or past PayPal history.
     */
    public function captureOrder()
    {
        $data = $_POST['data'] ?? [];
        error_log('zcAjaxPaypalRest::getOrder data: ' . print_r($data, true));
        //$paypalOrder = $_SESSION['PayPalRestful']['Order'] ?? null;


        global $order;
        global $payment_modules;
        $order = new \order();
        $order_total_modules = new \order_total();
        $payment_modules = new \payment('paypalr');

        $this->notify('NOTIFY_HEADER_START_CHECKOUT_PROCESS');

        require DIR_WS_MODULES . zen_get_module_directory('checkout_process.php');

        global $insert_id;
        // load the after_process function from the payment modules
        $payment_modules->after_process();


        $this->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET', $insert_id);
        $_SESSION['cart']->reset(true);

        // unregister session variables used during checkout
        unset($_SESSION['sendto'], $_SESSION['billto'], $_SESSION['shipping'], $_SESSION['payment'], $_SESSION['comments']);
        $order_total_modules->clear_posts();//ICW ADDED FOR CREDIT CLASS SYSTEM

        $this->notify('NOTIFY_HEADER_END_CHECKOUT_PROCESS', $insert_id);
        return ([
            'order' => null,
            'redirect_page' => zen_href_link(FILENAME_CHECKOUT_SUCCESS, (($_GET['action'] ?? '') === 'confirm' ? 'action=confirm' : 'order_id=' . $insert_id), 'SSL'),
        ]);
    }

    /**
     *
     */
    public function shippingAddressChange()
    {
        //data:
        // errors: Errors to show to the user.
        //   ADDRESS_ERROR: "Your order can't be shipped to this address."
        //   COUNTRY_ERROR: "Your order can't be shipped to this country."
        //   STATE_ERROR: "Your order can't be shipped to this state."
        //   ZIP_ERROR: "Your order can't be shipped to this zip."
        // orderID: An ID that represents an order.
        // paymentID: An ID that represents a payment.
        // paymentToken: An ID or token that represents a resource.
        // shippingAddress:
        //     city: Shipping address city.
        //     countryCode: Shipping address country.
        //     postalCode: Shipping address ZIP code or postal code.
        //     state: Shipping address state or province.

        $data = $_POST['data'] ?? [];
        $address = $data['shippingAddress'] ?? [];
        $country = $address['countryCode'] ?? '';
        $state = $address['state'] ?? '';
        $zip = $address['postalCode'] ?? '';
        $city = $address['city'] ?? '';

        // @TODO - check the address for various "invalid" scenarios, and send status

        return ([
            'error' => false, // or 'country', 'state', 'zip', 'address'
        ]);
    }

    /**
     *
     */
    public function shippingOptionsChange()
    {
        // data: An object containing the payer’s selected shipping option. Consists of the following properties:
        //
        //errors: Errors to show to the payer.
        //     METHOD_UNAVAILABLE: "The shipping method you selected is unavailable. To continue, choose another way to get your order."
        //     STORE_UNAVAILABLE: "Part of your order isn't available at this store."
        // orderID: An ID that represents an order.
        //paymentID: An ID that represents a payment.
        //paymentToken: An ID or token that represents a resource.
        //selectedShippingOption: Shipping option selected by the payer.
        //id: Custom shipping method ID.
        //label: Custom shipping method label.
        //selected: Set to true by PayPal when selected by the buyer.
        //type: Shipping method type (SHIPPING or PICKUP).
        //amount: Additional cost for this method.
        //currencyCode: ISO currency code, such as USD.
        //value: String-formatted decimal format, such as 1.00.

        $data = $_POST['data'] ?? [];

        return ([
            'response' => [],
        ]);
    }

    protected function productsInStock(array $products = []): bool
    {
        // If stock checking is disabled, or if checkout is allowed when out of stock, pass.
        if (STOCK_CHECK !== 'true' || STOCK_ALLOW_CHECKOUT === 'true') {
            return true;
        }

        if (empty($products)) {
            $products = $_SESSION['cart']->get_products();
        }
        foreach ($products as $product) {
            $qtyAvailable = zen_get_products_stock($product['id']);
            // compare against product inventory, and against mixed=YES
            if ($qtyAvailable - $product['quantity'] < 0
                || $qtyAvailable - $_SESSION['cart']->in_cart_mixed($product['id']) < 0) {
                return false;
            }
        }
        return true;
    }
}
