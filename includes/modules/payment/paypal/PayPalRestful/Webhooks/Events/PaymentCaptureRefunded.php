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
        // TODO: Implement action() method.

        // A merchant refunds a payment capture.
        // https://developer.paypal.com/docs/api/payments/v2/#authorizations_capture - Show details for authorized payment with response `status` of `refunded`.

        $this->log->write('PAYMENT.CAPTURE.REFUNDED - action() triggered');

    }
}
