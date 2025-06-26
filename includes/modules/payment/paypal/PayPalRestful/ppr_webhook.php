<?php
/**
 * Controller for incoming subscribed PayPal Webhook notifications
 *
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte June 2025 $
 *
 * Last updated: v1.2.0
 *
 * @var $currencies currencies
 * @var $db queryFactoryResult|queryFactory
 * @var $messageStack messageStack
 * @var $zco_notifier Zencart\Traits\NotifierManager
 */

/**
 * STEPS:
 * 1. Confirm that this is a PayPal webhook
 * 2. Log the incoming call
 * 3. Do CRC check to ensure trustworthiness
 * 4. Acknowledge the incoming call with appropriate HTTP response
 * 5. Take action according to the nature of the webhook payload
 */

/**
 * set supporting application_top parameters
 */

use PayPalRestful\Common\Logger;
use PayPalRestful\Webhooks\WebhookObject;
use PayPalRestful\Webhooks\WebhookResponder;

// Boot application
$loaderPrefix = 'webhook';
require 'includes/application_top.php';
$current_page_base = 'ppr_webhook';
require DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

function strToStudly($value): string
{
    $words = explode(' ', str_replace(['.', '-'], ' ', strtolower($value)));
    $studlyWords = array_map(static fn ($word) => mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($word, 1, null, 'UTF-8'), $words);
    return implode($studlyWords);
}

/**
 * Inspect webhook
 */
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
$ppr_logger->write("ppr_webhook ($event, $user_agent, $request_method) starts.\n" . Logger::logJSON($json_body), true, 'before');

// set object
$webhook = new WebhookObject($request_method, $request_headers, $request_body, $user_agent);

// prepare for verification
$verifier = new WebhookResponder($webhook);

// Ensure that the incoming request contains headers etc relevant to PayPal
if (!$verifier->shouldRespond()) {
    $ppr_logger->write('ppr_webhook IGNORED DUE TO HEADERS MISMATCH' . print_r($request_headers, true), false, 'before');
    require 'includes/application_bottom.php';
    exit();
}

// Verify that the webhook's signature is valid, to avoid spoofing and fraud, and wasted processing cycles
$status = $verifier->verify();

if ($status === null) {
    // For future dev: null means this webhook handler should be ignored, and go to next one
    // Probably this logic would be in a loop of classes being iterated, and would respond null to loop to the next one.
    // And then this exit() call would need removed.
    require 'includes/application_bottom.php';
    exit();
}

if ($status === false) {
    $ppr_logger->write('ppr_webhook FAILED VERIFICATION', false, 'before');
    // The verifier already sent an HTTP response, so we just exit here.
    require 'includes/application_bottom.php';
    exit();
}

// Now that verification has passed, dispatch the webhook according to the declared event_type

$ppr_logger->write("\n\n" . 'webhook verification passed', false, 'before');

$objectName = 'PayPalRestful\Webhooks\Events\\' . strToStudly($event);
if (class_exists($objectName)) {
//    $ppr_logger->write('class found: ' . $objectName, false, 'before');
    $call = new $objectName($webhook);
    if ($call->eventTypeIsSupported()) {
        $ppr_logger->write("\n\n" . 'webhook event supported by ' . $objectName, false, 'before');
        $call->action();
        require 'includes/application_bottom.php';
        exit();
    }
} else {
    $ppr_logger->write('class NOT found: ' . $objectName, false, 'before');
}

require 'includes/application_bottom.php';
