<?php
/**
 * PayPal REST API Webhooks
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte June 2025 $
 *
 * Last updated: v1.2.0
 */

namespace PayPalRestful\Webhooks\Events;

use PayPalRestful\Webhooks\WebhookHandlerContract;

class CheckoutPaymentApprovalReversed extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
    ];

    public function action(): void
    {
        // A problem occurred after the buyer approved the order but before you captured the payment.
        // https://developer.paypal.com/docs/api/orders/v2/

        $this->log->write('CHECKOUT.PAYMENT-APPROVAL.REVERSED - action() triggered');

        // Loading this to load all language file dependencies.
        require DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment ('paypalr');


        // Refer to Handle uncaptured payments for what to do when this event occurs
        // https://developer.paypal.com/docs/checkout/apm/reference/handle-uncaptured-payments/

        // When a transaction is not captured within a specified amount of time
        // after the buyer approves it through the payment method, PayPal sends
        // CHECKOUT.PAYMENT-APPROVAL.REVERSED webhook event, initiates a cancellation of the order,
        // and refunds the buyer's account. The time window for capturing the payment is
        // controlled by the merchant, but the default is 3 hours.
        // Send a notification to your buyer that provides them with possible next steps, like contacting customer support. And cancel/downgrade the order to unpaid status.


        // @TODO - lookup order ID and add an order-status record noting what's happened ...
        // @TODO - AND NOTIFY THE CUSTOMER
        // @TODO - also downgrade the order-status to payment-pending
        // @TODO - also would be good to notify the merchant by email.

        $summary = $this->data['summary'];

        $oID = $this->data['resource']['order_id'] ?? null;
        // @TODO - verify $oID is found in db, and if not, then lookup via $this->data['resource']['custom_id'] or ['invoice_id']

        $comments =
            "Notice: PAYMENT REVERSAL. Order ID: $oID \n" .
            "$summary\n";

        $admin_message = MODULE_PAYMENT_PAYPALR_CAPTURE_ERROR;
        $status = (int)MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID;
        $status = ($status > 0) ? $status : 1;

        zen_update_orders_history($oID, $comments, null, $status, 1);
        zen_update_orders_history($oID, $admin_message);

        // @TODO - NOTIFY MERCHANT VIA EMAIL
        // @todo could use this logic from paypalr.php module:
//        $GLOBALS['paypalr']->sendAlertEmail(
//            MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN,
//            sprintf(MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATION, $this->orderInfo['orders_id'], $this->orderInfo['paypal_payment_status'])
//        );
//     or   $GLOBALS['paypalr']->sendAlertEmail(MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN, sprintf(MODULE_PAYMENT_PAYPALR_ALERT_EXTERNAL_TXNS, $zf_order_id));

    }
}

/*
{
  "id": "WH-COC11055RA711503B-4YM959094A144403T",
  "create_time": "2020-01-25T21:21:49.000Z",
  "event_type": "CHECKOUT.PAYMENT-APPROVAL.REVERSED",
  "summary": "A payment has been reversed after approval.",
  "resource": {
    "order_id": "5O190127TN364715T",
    "purchase_units": [
      {
        "reference_id": "d9f83340-38f0-11e8-b467-0ed5f89f718b",
        "custom_id": "MERCHANT_CUSTOM_ID",
        "invoice_id": "MERCHANT_INVOICE_ID"
      }
    ],
    "payment_source": {
      "ideal": {
        "name": "John Doe",
        "country_code": "NL"
      }
    }
  }
  "event_version": "1.0"
}
 */
