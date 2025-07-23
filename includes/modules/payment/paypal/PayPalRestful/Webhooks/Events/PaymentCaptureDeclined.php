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

class PaymentCaptureDeclined extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'PAYMENT.CAPTURE.DECLINED',
    ];

    public function action(): void
    {
        // A payment capture is declined.
        // https://developer.paypal.com/docs/api/payments/v2/#authorizations_capture - with response `status` of `declined`

        $this->log->write('PAYMENT.CAPTURE.DECLINED - action() triggered');

        // Loading this to load all language file dependencies.
        require DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment ('paypalr');

        $summary = $this->data['summary'];
        $txnID = $this->data['resource']['id'];

        $oID = $this->data['resource']['supplementary_data']['related_ids']['order_id'] ?? null;

        // @TODO - verify $oID is found in db, and if not, then lookup via $this->data['resource']['custom_id'] or ['invoice_id']

        $amount = $this->data['resource']['amount']['value'];
        $comments =
            "Notice: CAPTURE DECLINED. Trans ID: $txnID \n" .
            "Amount: $amount\n$summary\n";

        $capture_admin_message = MODULE_PAYMENT_PAYPALR_CAPTURE_ERROR;
        $capture_status = 1;
        zen_update_orders_history($oID, $comments, null, $capture_status, 0);
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
    "id": "WH-6HE329230C693231F-5WV60586YA659351G",
    "event_version": "1.0",
    "create_time": "2022-12-13T19:13:07.251Z",
    "resource_type": "capture",
    "resource_version": "2.0",
    "event_type": "PAYMENT.CAPTURE.DECLINED",
    "summary": "A payment capture for $ 185.1 USD was declined.",
    "resource": {
        "id": "7U133281TB3277326",
        "amount": {
            "currency_code": "USD",
            "value": "185.10"
        },
        "final_capture": false,
        "seller_protection": {
            "status": "ELIGIBLE",
            "dispute_categories": [
                "ITEM_NOT_RECEIVED",
                "UNAUTHORIZED_TRANSACTION"
            ]
        },
        "disbursement_mode": "INSTANT",
        "seller_receivable_breakdown": {
            "gross_amount": {
                "currency_code": "USD",
                "value": "185.10"
            },
            "platform_fees": [
                {
                    "amount": {
                        "currency_code": "USD",
                        "value": "0.50"
                    },
                    "payee": {
                        "merchant_id": "QG3ECYYLJ2A48"
                    }
                }
            ],
            "net_amount": {
                "currency_code": "USD",
                "value": "184.60"
            },
            "receivable_amount": {
                "currency_code": "EUR",
                "value": "115.98"
            },
            "exchange_rate": {
                "source_currency": "USD",
                "target_currency": "EUR",
                "value": "0.628281035098039"
            }
        },
        "invoice_id": "ARG0-2022-12-08T21:00:21.564Z-435",
        "custom_id": "CUSTOMID-1001",
        "status": "DECLINED",
        "supplementary_data": {
            "related_ids": {
                "order_id": "48R416400V564864N",
                "authorization_id": "24B76447NN600461P"
            }
        },
        "create_time": "2022-12-13T19:13:00Z",
        "update_time": "2022-12-13T19:13:00Z",
        "links": [
            {
                "href": "https:\/\/api.paypal.com\/v2\/payments\/captures\/7U133281TB3277326",
                "rel": "self",
                "method": "GET"
            },
            {
                "href": "https:\/\/api.paypal.com\/v2\/payments\/captures\/7U133281TB3277326\/refund",
                "rel": "refund",
                "method": "POST"
            },
            {
                "href": "https:\/\/api.paypal.com\/v2\/payments\/authorizations\/24B76447NN600461P",
                "rel": "up",
                "method": "GET"
            }
        ]
    }
}
 */
