<?php
/**
 * PayPal REST API Webhook Controller
 * This controller parses the incoming webhook and brokers the
 * necessary steps for validation and dispatching based on the
 * nature of the webhook content.
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte June 2025 $
 *
 * Last updated: v1.2.0
 */

namespace PayPalRestful\Webhooks;

use PayPalRestful\Common\Logger;

class WebhookController
{
    public function __invoke(): bool|null
    {
        // Inspect webhook
        $request_method = $_SERVER['REQUEST_METHOD'];
        $request_headers = getallheaders();
        $request_body = file_get_contents('php://input');
        $json_body = json_decode($request_body, true);
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $event = $json_body['event_type'] ?? '(event not determined)';

        $summary = $json_body['summary'] ?? '(summary not determined)';

        $logIdentifier = $json_body['id'] ?? $json_body['event_type'] ?? '';

        // create logger
        $ppr_logger = new Logger($logIdentifier);
        $ppr_logger->enableDebug(); // @TODO remove?

        // log that we got an incoming webhook, and its details
        $ppr_logger->write("ppr_webhook ($event, $user_agent, $request_method) starts.\n" . Logger::logJSON($json_body), true);

        // set object
        $webhook = new WebhookObject($request_method, $request_headers, $request_body, $user_agent);

        // prepare for verification
        $verifier = new WebhookResponder($webhook);

        // Ensure that the incoming request contains headers etc relevant to PayPal
        if (!$verifier->shouldRespond()) {
            $ppr_logger->write('ppr_webhook IGNORED DUE TO HEADERS MISMATCH' . print_r($request_headers, true), false, 'before');
            return false;
        }

        // Verify that the webhook's signature is valid, to avoid spoofing and fraud, and wasted processing cycles
        $status = $verifier->verify();

        if ($status === null) {
            // For future dev: null means this webhook handler should be ignored, and go to next one
            // Probably this logic would be in a loop of classes being iterated, and would respond null to loop to the next one.
            return null;
        }

        if ($status === false) {
            $ppr_logger->write('ppr_webhook FAILED VERIFICATION', false, 'before');
            // The verifier already sent an HTTP response, so we just exit here.
            return false;
        }

        // Now that verification has passed, dispatch the webhook according to the declared event_type

        $ppr_logger->write("\n\n" . 'webhook verification passed', false, 'before');


        $objectName = 'PayPalRestful\Webhooks\Events\\' . $this->strToStudly($event);

        if (class_exists($objectName)) {
//debug:    $ppr_logger->write('class found: ' . $objectName, false, 'before');

            $call = new $objectName($webhook);
            if ($call->eventTypeIsSupported()) {
                $ppr_logger->write("\n\n" . 'webhook event supported by ' . $objectName, false, 'before');

                // dispatch to take the necessary action for the webhook
                $call->action();

                return true;
            }
        }
        $ppr_logger->write('class NOT found: ' . $objectName, false, 'before');
        return false;
    }

    /**
     * Convert string to Studly/CamelCase, using space, dot, hyphen, underscore as word break indicators
     */
    protected function strToStudly(string $value, array $dividers = ['.', '-', '_']): string
    {
        $words = explode(' ', str_replace($dividers, ' ', strtolower($value)));
        $studlyWords = array_map(static fn($word) => mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($word, 1, null, 'UTF-8'), $words);
        return implode($studlyWords);
    }

}
