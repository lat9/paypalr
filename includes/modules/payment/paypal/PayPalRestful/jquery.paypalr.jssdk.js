jQuery(document).ready(function () {
    // Wait for the JS SDK to load
    jQuery("#PayPalJSSDK").on("load", function () {

        let ppButtons = PayPalSDK.Buttons({
            style: {
                // layout: 'horizontal', // horizontal or vertical
                // color: 'gold', // gold, blue, silver, white, black
                // shape: 'rect', // rect, pill, sharp
                // label: 'paypal',
                // borderRadius: 0, // 0-50
                // height: 55, // 25-55
            },
            // https://developer.paypal.com/docs/checkout/standard/customize/app-switch/
            appSwitchWhenAvailable: true, // indicator to trigger App Switch on eligible devices


            createOrder() {
                // This function is called when the customer clicks the PayPal button.
                // It triggers creation of an Order request for the current state of purchase activity
                // and expects to receive back the order ID, which the SDK will then use to suggest funding selections.
                return zcJS.ajax({
                    url: "ajax.php?act=ajaxPaypalRest&method=getOrder",
                    data: {
                        page_url: window.location.href,
                        formdata: jQuery('form[name="cart_quantity"]').serializeArray(),
                        coupon: jQuery('#disc-ot_coupon').val(),
                        ppr_type: 'paypal',
                    }
                }).done(function (response) {
                    return response.order.id;
                }).always(function (response) {
                    console.error(response);
                });
            },

            onApprove(data, actions) {
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
            },


            onError(err) {
                // This function is called when there is an error during the payment process.
                // We have many options for handling an error. This is just something overly basic.
                console.error('PayPal Error:', err);
                alert('An error occurred while processing your payment. Please try again.');
            },

            // // onInit is called when the button first renders
            // onInit(data, actions) {
            //     // Disable the buttons
            //     actions.disable();
            //
            //     // Listen for changes to the checkbox
            //     document.querySelector("#checkbox").addEventListener("change", function (event) {
            //         // Enable or disable the button when it is checked or unchecked
            //         if (event.target.checked) {
            //             actions.enable();
            //         } else {
            //             actions.disable();
            //         }
            //     });
            // },
            //
            // // onClick is called when the button is selected
            // onClick() {
            //     // Show a validation error if the checkbox isn't checked
            //     if (!document.querySelector("#checkbox").checked) {
            //         document.querySelector("#error").classList.remove("hidden");
            //     }
            // },

            onCancel(data) {
                // Show a cancel page, or return to cart
                window.location.assign("/index.php?main_page=shopping_cart");
            },


            onShippingAddressChange(data, actions) {
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
            },

            onShippingOptionsChange(data, actions) {
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

        });

        // If using App Switch, the buyer may end up in a new tab depending on the browser
        // To trigger flow completion, we call resume():
        if (ppButtons.hasReturned()) {
            ppButtons.resume();
        } else {
            ppButtons.render("#paypal-buttons-container");
        }

        PayPalSDK.Messages(messageProps).render(messageSelector);

        const cardFields = PayPalSDK.CardFields({
            createOrder() {
                // This function is called when the customer clicks the PayPal button.
                // It triggers creation of an Order request for the current state of purchase activity
                // and expects to receive back the order ID, which the SDK will then use to suggest funding selections.
                return zcJS.ajax({
                    url: "ajax.php?act=ajaxPaypalRest&method=getCardFieldsOrder",
                    data: {
                        page_url: window.location.href,
                        formdata: jQuery('form[name="cart_quantity"]').serializeArray(),
                        coupon: jQuery('#disc-ot_coupon').val(),
                        ppr_type: 'card',
                    }
                }).done(function (response) {
                    return response.order.id;
                }).always(function (response) {
                    console.log(response);
                });
            },

            onApprove(data, actions) {
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
            },


            onError(err) {
                // This function is called when there is an error during the payment process.
                // We have many options for handling an error. This is just something overly basic.
                console.error('PayPal Error:', err);
                alert('An error occurred while processing your payment. Please try again.');
            },
        });

    });
});
