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

class CheckoutOrderApproved extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'CHECKOUT.ORDER.APPROVED',
    ];

    public function action(): void
    {
        // TODO: Implement action() method.
        // A buyer approved a checkout order
        // https://developer.paypal.com/docs/api/orders/v2/

        $this->log->write('CHECKOUT.ORDER.APPROVED - action() triggered');

    }
}
