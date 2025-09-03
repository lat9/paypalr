<?php
/**
 * zcAjaxPaypalRest
 *
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id:  $
 */


class zcAjaxPaypalRest extends base
{
    /**
     *
     */
    public function getOrder()
    {
        /**
         * $data is the form contents from the `cart_quantity` field on
         * either the product_info (qty, attributes) or shopping_cart page (just qty/delete fields).
         * Also contains `disc-ot_coupon` if on a checkout page and discount coupon was applied.
         */
        $data = $_POST['data'] ?? [];
        //$paypalOrder = $_SESSION['PayPalRestful']['Order'] ?? null;

        // It should return data that can be resolved to a PayPal order ID which
        // can be presented to the buyer via PayPal's popup, for payment funding selection/approval.
        return ([
            'order' => $paypalOrder,
        ]);
    }

    /**
     * Given the supplied PayPal order ID, capture the order.
     * This involves storing the order and completing payment.
     * May also involve account-creation; or at least looking up the customer by email address or past PayPal history.
     */
    public function captureOrder()
    {
        $data = $_POST['data'] ?? [];
        //$paypalOrder = $_SESSION['PayPalRestful']['Order'] ?? null;

        return ([
            'order' => $paypalOrder,
        ]);
    }
    /**
     *
     */
    public function shippingAddressChange()
    {
        // data:
        //     errors: Errors to show to the user.
        //         ADDRESS_ERROR: "Your order can't be shipped to this address."
        //         COUNTRY_ERROR: "Your order can't be shipped to this country."
        //         STATE_ERROR: "Your order can't be shipped to this state."
        //         ZIP_ERROR: "Your order can't be shipped to this zip."
        // orderID: An ID that represents an order.
        // paymentID: An ID that represents a payment.
        // paymentToken: An ID or token that represents a resource.
        // shippingAddress:
        //     city: Shipping address city.
        //     countryCode: Shipping address country.
        //     postalCode: Shipping address ZIP code or postal code.
        //     state: Shipping address state or province.

        $data = $_POST['data'] ?? [];

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
            'response' => []
        ]);
    }
}
