// PayPal PayLater messaging
// Last updated: v1.3.0
if (!paypalMessagesPageType.length) {
    paypalMessagesPageType = "None";
}
let payLaterStyles = {"layout":"text","logo":{"type":"inline","position":"top"},"text":{"align":"center"}, ...paypalMessageableStyles};

// Wait until the page has loaded
jQuery(function() {
    // If PayPal's JSSDK hasn't loaded, nothing further to be done.
    //
    if (!window.PayPalSDK) {
        console.warn('PayPal SDK not loaded, no Pay Later messaging available.');
        return;
    }

    let $paypalMessagesOutputContainer = ""; // empty placeholder
    let $paypalHasMessageObjects = false;
    let shouldBreak = false;
    $messagableObjects.unshift(paypalMessageableOverride);
    jQuery.each($messagableObjects, function(index, current) {
        if (shouldBreak) {
            return false; // break outer loop
        }

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
            // Use .replace to remove the leading currency symbol and any commas (thousands-separators).
            const price = Number(priceElement.textContent.replace(/[^\d.]/g, ''));
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
// End PayPal PayLater Messaging
