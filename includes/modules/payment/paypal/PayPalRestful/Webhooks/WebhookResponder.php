<?php
/**
 * PayPal REST API Webhook Responder
 * This class handles verifying the validity of an incoming webhook
 * to ensure that it is legitimate. It checks POST headers for relevance
 * and checks that the payload's CRC check passes validity with PayPal
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte June 2025 $
 *
 * Last updated: v1.2.0
 */
namespace PayPalRestful\Webhooks;

class WebhookResponder
{
    protected bool $shouldRespond = false;

    protected string|null $webhook_listener_subscribe_id = null;

    public function __construct(protected WebhookObject $webhook) {
        $this->setWebhookSubscribeId();
    }

    /**
     * Check that headers match what PayPal Webhooks will contain,
     * and check that a few usual body content properties are present
     */
    public function shouldRespond(): bool
    {
        $headers = $this->webhook->getHeaders();
        $data = $this->webhook->getJsonBody();
        if (array_key_exists('Paypal-Auth-Version', $headers)
            && array_key_exists('Paypal-Auth-Algo', $headers)
            && isset($data['event_type'])
            && \str_contains($this->webhook->getUserAgent(), 'PayPal/')
        ) {
            $this->shouldRespond = true;
        }

        return $this->shouldRespond;
    }

    public function verify(): bool|null
    {
        if ($this->shouldRespond !== true) {
            return null;
        }

        $valid = $this->doCrcCheck();

        // In case we couldn't complete a CRC check (ie: internal issue, not "failed validation),
        // this falls through to trying a postback instead
        if ($valid === null) {
            $valid = $this->verifyByPostback();
        }

        // null means "we" couldn't complete a verification attempt (and we *will* want PayPal to see it as failed-to-complete, so they keep re-sending)
        // false means "failed validation"
        // true means "passed validation"
        if ($valid !== null) {
            // send a 200 response to acknowledge that we received the webhook
            http_response_code(200);
        }

        return $valid;
    }

    /**
     * @return bool|null  returns null if we cannot do CRC check, so fails over to PostBack approach
     */
    protected function doCrcCheck(): bool|null
    {
        $headers = $this->webhook->getHeaders();

        $transmissionId = $headers['Paypal-Transmission-Id'];
        $timestamp = $headers['Paypal-Transmission-Time'];
        $crc = \hexdec(\hash('crc32b', $this->webhook->getRawBody()));
        $calculatedSignature = "$transmissionId|$timestamp|$this->webhook_listener_subscribe_id|$crc";
        $transmissionSignature = $headers['Paypal-Transmission-Sig'];
        $decodedSignature = base64_decode($transmissionSignature);

        $publicKeyUrl = $headers['Paypal-Cert-Url'];

        // @TODO - download and cache the public key, from the URL, instead of retrieving fresh in real time
        $pem_cert = \file_get_contents($publicKeyUrl); // @TODO add curl fallback option in case server blocks this way of reading

        $publicKey = openssl_get_publickey($pem_cert);

        $result = openssl_verify($calculatedSignature, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * @return bool|null  returns null if unable to use CURL
     */
    protected function verifyByPostback(): bool|null
    {
        $headers = $this->webhook->getHeaders();

// @TODO rewrite as curl() call, and set ACCESS-TOKEN

//  curl -X POST https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature \
//  -H "Content-Type: application/json" \
//  -H "Authorization: Bearer ACCESS-TOKEN" \
//  -d '{
//  "transmission_id": "$headers['Paypal-Transmission-Id']",
//  "transmission_time": "$headers['Paypal-Transmission-Time']",
//  "cert_url": "$headers['Paypal-Cert-Url']",
//  "auth_algo": "SHA256withRSA",
//  "transmission_sig": "$headers['Paypal-Transmission-Sig']",
//  "webhook_id": "$this->webhook_listener_subscribe_id",
//  "webhook_event": "$this->webhook->getRawBody()"
//}'
//
    }

    /**
     * This method is only used by the Postback verification method
     */
    protected function setWebhookSubscribeId()
    {
        // @TODO - need to create this Config constant, or store in db some other way, when we register it upon installation of the paypalr module.
        if (defined('PAYPALR_LISTENER_SUBSCRIBE_ID')) {
            $this->webhook_listener_subscribe_id = PAYPALR_LISTENER_SUBSCRIBE_ID;
        }
    }
}
