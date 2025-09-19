// PayPal PayLater messaging
// Last updated: v1.3.0
if (!paypalMessagesPageType.length) {
    paypalMessagesPageType = "None";
}
let payLaterStyles = {"layout":"text","logo":{"type":"inline","position":"top"},"text":{"align":"center"}, ...paypalMessageableStyles};

jQuery(document).ready(function () {
    // Wait for the JS SDK to load
    jQuery("#PayPalJSSDK").on("load", function () {

        // Possible placements for PayLater messaging;
        //  pageType is the page to which the rest of the details apply in this object; relates to paypalr config switch MODULE_PAYMENT_PAYPALR_PAYLATER_MESSAGING
        //  container is what containing element we will search in,
        //  price is the element inside container where the price is found,
        //  outputElement is what element we want the PayPal SDK to add pricing display into
        //    NOTE: where a duplicate occurs with #paypal-message-container first, that's to allow overriding by adding said element to the template where desired
        //  styleAlign can be left, center, right. Controls placement in outputElement.

        let $messagableObjects = [
            {
                pageType: "product-details",
                container: "#productsPriceBottom-card",
                price: ".productBasePrice",
                outputElement: "#paypal-message-container",
                styleAlign: ""
            },
            {
                pageType: "product-details",
                container: "#productsPriceBottom-card",
                price: ".productBasePrice",
                outputElement: ".productPriceBottomPrice",
                styleAlign: ""
            },
            {
                pageType: "product-details",
                container: ".add-to-cart-Y",
                price: ".productBasePrice",
                outputElement: "#productPrices",
                styleAlign: ""
            },
            {
                pageType: "product-details",
                container: ".add-to-cart-Y",
                price: ".productBasePrice",
                outputElement: "#paypal-message-container",
                styleAlign: ""
            },
            {
                pageType: "product-listing",
                container: ".pl-dp",
                price: ".productBasePrice",
                outputElement: ".pl-dp",
                styleAlign: ""
            },
            {
                pageType: "product-listing",
                container: ".list-price",
                price: ".productBasePrice",
                outputElement: ".list-price",
                styleAlign: ""
            },
            {
                pageType: "search-results",
                container: ".pl-dp",
                price: ".productBasePrice",
                outputElement: ".pl-dp",
                styleAlign: ""
            },
            {
                pageType: "search-results",
                container: ".list-price",
                price: ".productBasePrice",
                outputElement: ".list-price",
                styleAlign: ""
            },
            {
                pageType: "cart",
                container: "#shoppingCartDefault",
                price: "#cart-total",
                outputElement: "#paypal-message-container",
                styleAlign: "right"
            },
            {
                pageType: "cart",
                container: "#shoppingCartDefault-cartTableDisplay",
                price: "#cartTotal",
                outputElement: "#cartTotal",
                styleAlign: "right"
            },
            {
                pageType: "cart",
                container: "#shoppingCartDefault",
                price: "#cartSubTotal",
                outputElement: "#cartSubTotal",
                styleAlign: "right"
            },
            {
                pageType: "checkout",
                container: "#checkout_payment",
                price: "#ottotal > .ot-text",
                outputElement: "#paypal-message-container",
                styleAlign: "right"
            },
            {
                pageType: "checkout",
                container: "#checkoutPayment",
                price: "#ottotal > .ot-text",
                outputElement: "#paypal-message-container",
                styleAlign: "left"
            },
            {
                pageType: "checkout",
                container: "#checkoutPayment",
                price: "#ottotal > .ot-text",
                outputElement: "#yourTotal-card",
                styleAlign: "right"
            },
            {
                pageType: "checkout",
                container: "#checkoutOrderTotals",
                price: "#ottotal > .totalBox",
                outputElement: "#paypal-message-container",
                styleAlign: ""
            },
            {
                pageType: "checkout",
                container: "#checkoutOrderTotals",
                price: "#ottotal > .totalBox",
                outputElement: "#checkoutOrderTotals",
                styleAlign: ""
            },
        ];

        let $paypalMessagesOutputContainer = ""; // empty placeholder
        let $paypalHasMessageObjects = false;
        let shouldBreak = false;
        $messagableObjects.unshift(paypalMessageableOverride);
        jQuery.each($messagableObjects, function(index, current) {
            if (shouldBreak) return false; // break outer loop

            if (paypalMessagesPageType !== current.pageType) {
                // not for this page, so skip
                return true;
            }

            let $output = jQuery(current.outputElement);

            if (!$output.length) {
                console.info("Msgs Loop " + index + ": " + current.outputElement + ' not found, continuing');
                // outputElement not found on this page; try to find in next group
                return true;
            }
            let $findInContainer = jQuery(current.container);
            if (!$findInContainer.length) {
                console.info("Msgs Loop " + index + ": " + current.container + ' not found, continuing');
                // Container in which to search for price was not found; try next group
                return true;
            }

            // each container is either a product, or a cart/checkout div that contains another element containing a price
            jQuery.each($findInContainer, function (i, element) {
                console.info("Msgs Loop " + index + ": " + current.outputElement + " found on page, and " + current.container + " element found. Extracting price from " + current.price);

                // Extract the price of the product by grabbing the text content of the element that contains the price.
                // @TODO: could try to parse for numeric data, or split "words" out of it in case the price is prefixed with text
                let priceElement = element.querySelector(current.price);
                if (!priceElement) {
                    console.info("Msgs Loop " + index + ": priceElement is empty. Skipping.");
                    return true;
                }
                // Use .slice(1) to remove the leading currency symbol, and replace() any commas (thousands-separators).
                const price = Number(
                    priceElement.textContent.slice(1).replace(/,/, '')
                );
                console.info("Msgs Loop " + index + ": " + 'Price ' + price + "; will try to set in " + current.outputElement)

                // Add/set the data-pp-amount attribute on this element.
                // The PayPal SDK monitors message elements for changes to its attributes,
                // so the message is updated automatically to reflect this amount in whatever messaging PayPal displays.
                $output.attr('data-pp-amount', price.toString());

                $paypalMessagesOutputContainer = current.outputElement;
                $paypalHasMessageObjects = true;

                if (current.styleAlign.length) {
                    payLaterStyles.text.align = current.styleAlign;
                }

                // finished with the loop
                shouldBreak = true; // flag to break outer loop too
                return false;
            });
        });

        // Render any PayPal PayLater messages if an appropriate container exists.
        if ($paypalHasMessageObjects && $paypalMessagesOutputContainer.length) {
            PayPalSDK.Messages({
                style: payLaterStyles,
                pageType: paypalMessagesPageType,
            }).render($paypalMessagesOutputContainer);
        }
    });
});
// End PayPal PayLater Messaging
