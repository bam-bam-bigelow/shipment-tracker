<?php
declare(strict_types=1);

use Carbon\Carbon;
use Sauladam\ShipmentTracker\ShipmentTracker;

/**
 * FedEx - ...
 * php test-parsing.php --carrier fedex --track-number 123123123
 *
 * UPS - ...
 * php test-parsing.php --carrier ups --track-number 1Z123123123
 *
 * UPS - ...
 * php test-parsing.php --carrier ups --track-number 1z321321312
 */

// it only should be called from terminal
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// check argument "--carrier"
if (!isset($argv[1]) || $argv[1] !== '--carrier') {
    die('Please specify carrier name. Example: php test-parsing.php --carrier UPS');
}

// check argument "--track-number"
if (!isset($argv[3]) || $argv[3] !== '--track-number') {
    die('Please specify track number. Example: php test-parsing.php --carrier UPS --track-number 1Z2E3A691331822670');
}

$carrier = $argv[2];
$trackNumber = $argv[4];

require_once __DIR__ . '/vendor/autoload.php';

ShipmentTracker::setProxyUri('socks5://1:2@1.1.1.1:123');
$tracker = ShipmentTracker::get($carrier);
$track = $tracker->track($trackNumber, 'en');

printf("Carrier: %s\n", $carrier);
printf("Track number: %s\n", $trackNumber);
printf("currentStatus: %s\n", $track->currentStatus());

if ((float)$track->getAdditionalDetails('totalLbsWgt') > 0) {
    printf("weight: %s\n", $track->getAdditionalDetails('totalLbsWgt'));
}

if ((float)$track->getAdditionalDetails('weight') > 0) {
    printf("weight: %s\n", $track->getAdditionalDetails('weight'));
}

$scheduledDeliveryDate = $track->getAdditionalDetails('scheduledDeliveryDate');
if ($scheduledDeliveryDate) {
    printf("deliveryDate: %s\n", json_decode($scheduledDeliveryDate));
}
$latestEvent = $track->latestEvent();
if (\is_object($latestEvent)) {
    printf("Latest event description: %s\n", $latestEvent->getDescription());
}
