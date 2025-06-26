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
        // TODO: Implement action() method.

        // A problem occurred after the buyer approved the order but before you captured the payment. Refer to Handle uncaptured payments for what to do when this event occurs
        // https://developer.paypal.com/docs/checkout/apm/reference/handle-uncaptured-payments/
        // https://developer.paypal.com/docs/api/orders/v2/


        $this->log->write('CHECKOUT.PAYMENT-APPROVAL.REVERSED - action() triggered');
    }
}
