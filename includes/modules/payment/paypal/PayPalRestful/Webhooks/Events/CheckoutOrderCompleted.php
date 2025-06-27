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

class CheckoutOrderCompleted extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'CHECKOUT.ORDER.COMPLETED',
    ];

    public function action(): void
    {
        // TODO: Implement action() method.

        // A checkout order is processed. Note: For use by marketplaces and platforms only.
        // https://developer.paypal.com/docs/api/orders/v2/


        $this->log->write('CHECKOUT.ORDER.COMPLETED - action() triggered');
    }
}
