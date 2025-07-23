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

class PaymentCaptureRefunded extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'PAYMENT.CAPTURE.REFUNDED',
    ];

    public function action(): void
    {
        // A merchant refunds a payment capture.
        // https://developer.paypal.com/docs/api/payments/v2/#authorizations_capture - Show details for authorized payment with response `status` of `refunded`.

        $this->log->write('PAYMENT.CAPTURE.REFUNDED - action() triggered');

        // Loading this to load all language file dependencies.
        require DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment ('paypalr');

        // Lookup order ID and update the order-status record to note that the order was refunded.

        $summary = $this->data['summary'];
        $txnID = $this->data['resource']['id'];

        $oID = $txnID;// @TODO lookup via $txnID

        $amount = $this->data['resource']['amount']['value'];
        $comments =
            "Notice: REFUNDED/REVERSED. Trans ID: $txnID \n" .
            "Amount: $amount\n$summary\n";
        $capture_admin_message = sprintf(MODULE_PAYMENT_PAYPALR_REFUND_COMPLETE, $amount);
        $refund_status = (int)MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID;
        $refund_status = ($refund_status > 0) ? $refund_status : 1;

        zen_update_orders_history($oID, $comments, null, $refund_status, 1);
        zen_update_orders_history($oID, $capture_admin_message);

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
  "id": "WH-1GE84257G0350133W-6RW800890C634293G",
  "create_time": "2018-08-15T19:14:04.543Z",
  "resource_type": "refund",
  "event_type": "PAYMENT.CAPTURE.REFUNDED",
  "summary": "A $ 0.99 USD capture payment was refunded",
  "resource": {
    "seller_payable_breakdown": {
      "gross_amount": {
        "currency_code": "USD",
        "value": "0.99"
      },
      "paypal_fee": {
        "currency_code": "USD",
        "value": "0.02"
      },
      "net_amount": {
        "currency_code": "USD",
        "value": "0.97"
      },
      "total_refunded_amount": {
        "currency_code": "USD",
        "value": "1.98"
      }
    },
    "amount": {
      "currency_code": "USD",
      "value": "0.99"
    },
    "update_time": "2018-08-15T12:13:29-07:00",
    "create_time": "2018-08-15T12:13:29-07:00",
    "links": [
      {
        "href": "https://api.paypal.com/v2/payments/refunds/1Y107995YT783435V",
        "rel": "self",
        "method": "GET"
      },
      {
        "href": "https://api.paypal.com/v2/payments/captures/0JF852973C016714D",
        "rel": "up",
        "method": "GET"
      }
    ],
    "id": "1Y107995YT783435V",
    "status": "COMPLETED"
  },
  "links": [
    {
      "href": "https://api.paypal.com/v1/notifications/webhooks-events/WH-1GE84257G0350133W-6RW800890C634293G",
      "rel": "self",
      "method": "GET",
      "encType": "application/json"
    },
    {
      "href": "https://api.paypal.com/v1/notifications/webhooks-events/WH-1GE84257G0350133W-6RW800890C634293G/resend",
      "rel": "resend",
      "method": "POST",
      "encType": "application/json"
    }
  ],
  "event_version": "1.0",
  "resource_version": "2.0"
}
 */
