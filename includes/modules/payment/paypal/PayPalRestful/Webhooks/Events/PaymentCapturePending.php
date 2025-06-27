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

class PaymentCapturePending extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'PAYMENT.CAPTURE.PENDING',
    ];

    public function action(): void
    {
        // TODO: Implement action() method.

        // The state of a payment capture changes to pending
        // https://developer.paypal.com/docs/api/payments/v2/#authorizations_get - Show details for authorized payment with response `status` of `pending`.

        $this->log->write('PAYMENT.CAPTURE.PENDING - action() triggered');

    }
}
