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

class PaymentAuthorizationVoided extends WebhookHandlerContract
{
    protected array $eventsHandled = [
        'PAYMENT.AUTHORIZATION.VOIDED',
    ];

    public function action(): void
    {
        // TODO: Implement action() method.

        // A payment authorization is voided either due to authorization reaching its 30 day validity period or authorization was manually voided using the Void Authorized Payment API.
        // https://developer.paypal.com/docs/api/payments/v2/#authorizations_get - Show details for authorized payment with response `status` of `voided`.

        $this->log->write('PAYMENT.AUTHORIZATION.VOIDED - action() triggered');

    }
}
