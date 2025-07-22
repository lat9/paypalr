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

class PaymentCaptureReversed extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'PAYMENT.CAPTURE.REVERSED',
    ];

    public function action(): void
    {
        // PayPal reverses a payment capture (not the merchant)
        // https://developer.paypal.com/docs/api/payments/v2/#captures_refund

        $this->log->write('PAYMENT.CAPTURE.REVERSED - action() triggered');

        // Add an order-status record indicating that PayPal reversed the payment capture (refunded the payment), unbeknownst to the merchant

        $summary = $this->data['summary'];
        $txnID = $this->data['resource']['id'];

        $oID = FOO; // @TODO - lookup via $txnID

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
    }
}

/*
{
  "id": "WH-6F207351SC284371F-0KX52201050121307",
  "create_time": "2018-08-15T21:30:35.780Z",
  "resource_type": "refund",
  "event_type": "PAYMENT.CAPTURE.REVERSED",
  "summary": "A $ 2.51 USD capture payment was reversed",
  "resource": {
    "seller_payable_breakdown": {
      "gross_amount": {
        "currency_code": "USD",
        "value": "2.51"
      },
      "paypal_fee": {
        "currency_code": "USD",
        "value": "0.00"
      },
      "net_amount": {
        "currency_code": "USD",
        "value": "2.51"
      },
      "total_refunded_amount": {
        "currency_code": "USD",
        "value": "2.51"
      }
    },
    "amount": {
      "currency_code": "USD",
      "value": "2.51"
    },
    "update_time": "2018-08-15T14:30:10-07:00",
    "create_time": "2018-08-15T14:30:10-07:00",
    "links": [
      {
        "href": "https://api.paypal.com/v2/payments/refunds/09E71677NS257044M",
        "rel": "self",
        "method": "GET"
      },
      {
        "href": "https://api.paypal.com/v2/payments/captures/4L335234718889942",
        "rel": "up",
        "method": "GET"
      }
    ],
    "id": "09E71677NS257044M",
    "note_to_payer": "Payment reversed",
    "status": "COMPLETED"
  },
  "links": [
    {
      "href": "https://api.paypal.com/v1/notifications/webhooks-events/WH-6F207351SC284371F-0KX52201050121307",
      "rel": "self",
      "method": "GET",
      "encType": "application/json"
    },
    {
      "href": "https://api.paypal.com/v1/notifications/webhooks-events/WH-6F207351SC284371F-0KX52201050121307/resend",
      "rel": "resend",
      "method": "POST",
      "encType": "application/json"
    }
  ],
  "event_version": "1.0",
  "resource_version": "2.0"
}
 */
