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

class PaymentCaptureCompleted extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'PAYMENT.CAPTURE.COMPLETED',
    ];

    public function action(): void
    {
        // TODO: Implement action() method.
        // A payment capture completes
        // https://developer.paypal.com/docs/api/payments/v2/#authorizations_capture - with response `status` of `completed`


        // Ensure order's status is updated to reflect that payment has been captured
        // - look up order
        // - ensure it was paid with paypal
        // - update payment status, including a note with any safe-to-share info from webhook

        $this->log->write('PAYMENT.CAPTURE.COMPLETED - action() triggered');

    }
}
