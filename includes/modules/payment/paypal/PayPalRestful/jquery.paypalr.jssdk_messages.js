// PayPal PayLater messaging
let payLaterStyles = {"layout":"text","logo":{"type":"inline","position":"top"},"text":{"align":"center"}};
if (!paypalMessagesPageType.length) {
    paypalMessagesPageType = "other";
}
jQuery(document).ready(function () {
    // Wait for the JS SDK to load
    jQuery("#PayPalJSSDK").on("load", function () {

        // Possible placements for PayLater messaging;
        //  container is what we search for,
        //  price is where the price is found,
        //  outputElement is what element for PayPal SDK to add pricing display into
        //  styleAlign can be left, center, right
        
        let $messagableObjects = [
            {
                container: "#productsPriceBottom-card",
                price: ".productBasePrice",
                outputElement: ".productPriceBottomPrice",
                styleAlign: ""
            },
            {
                container: ".add-to-cart-Y",
                price: ".productBasePrice",
                outputElement: "#productPrices",
                styleAlign: ""
            },
            {
                container: ".pl-dp",
                price: ".productBasePrice",
                outputElement: ".pl-dp",
                styleAlign: ""
            },
            {
                container: ".list-price",
                price: ".productBasePrice",
                outputElement: ".list-price",
                styleAlign: ""
            },
            {
                container: "#shoppingCartDefault",
                price: "#cart-total",
                outputElement: "#paypal-message-container",
                styleAlign: "right"
            },
            {
                container: "#shoppingCartDefault-cartTableDisplay",
                price: "#cartTotal",
                outputElement: "#cartTotal",
                styleAlign: "right"
            },
            {
                container: "#shoppingCartDefault",
                price: "#cartSubTotal",
                outputElement: "#cartSubTotal",
                styleAlign: "right"
            },
            {
                container: "#checkout_payment",
                price: "#ottotal > .ot-text",
                outputElement: "#paypal-message-container",
                styleAlign: "right"
            },
            {
                container: "#checkoutOrderTotals",
                price: "#ottotal > .totalBox",
                outputElement: "#checkoutOrderTotals",
                styleAlign: ""
            },
        ];

        let $paypalMessagesOutputContainer = ""; // empty placeholder
        let $paypalHasMessageObjects = false;
        let shouldBreak = false;
        jQuery.each($messagableObjects, function(index, current) {
            if (shouldBreak) return false; // break outer loop

            let $output = jQuery(current.outputElement);

            if (!$output.length) {
                console.info("Loop " + index + ": " + current.outputElement + ' not found, continuing');
                // not found; iterate to next group
                return true;
            }
            let $findInContainer = jQuery(current.container);
            if (!$findInContainer.length) {
                console.info("Loop " + index + ": " + current.container + ' not found, continuing');
                // not found; iterate to next group
                return true;
            }

            // each container is either a product, or a cart/checkout div that contains another element containing a price
            jQuery.each($findInContainer, function (i, element) {
                console.info("Loop " + index + ": " + current.outputElement + " found on page, and " + current.container + " element found. Extracting price from " + current.price);
                // Extract the price of the product by grabbing the text content of the
                // element that contains the price. Use .slice(1) to remove the leading
                // currency symbol, and replace() any commas (thousands-separators).
                const price = Number(
                    element.querySelector(current.price).textContent.slice(1).replace(/,/, '')
                );
                console.info("Loop " + index + ": " + 'Price ' + price + "; will try to set in " + current.outputElement)

                // Add/set the data-pp-amount attribute on this element.
                // The PayPal SDK monitors message elements for changes to its attributes,
                // so the message is updated automatically to reflect this amount in whatever messaging PayPal displays.
                $output.attr('data-pp-amount', price.toString());

                $paypalHasMessageObjects = true;
                $paypalMessagesOutputContainer = current.outputElement;

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
