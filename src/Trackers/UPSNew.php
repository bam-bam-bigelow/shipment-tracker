<?php

namespace Sauladam\ShipmentTracker\Trackers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class UPSNew extends AbstractTracker
{
    protected $serviceEndpoint = 'https://www.bing.com/packagetrackingv2?packNum={trackNumber}&carrier=ups&cc=US';

    public function trackingUrl($trackingNumber, $language = null, $params = [])
    {
        return str_replace('{trackNumber}', $trackingNumber, $this->serviceEndpoint);
    }

    protected function buildResponse($response)
    {
        $track = new Track();
        $dom = new DOMDocument();
        @$dom->loadHTML($response);
        $dom->preserveWhiteSpace = false;
        $domXPath = new DOMXPath($dom);

        // get table with class "rpt_se" and go over all rows
        // creating new Event objects and add them to the track
        $rows = $domXPath->query('//*[@class="rpt_se"]/tr');
        foreach ($rows as $row) {
            /** @var DOMElement $row */
            // skip first row
            if (($row->firstChild->tagName ?? '') === 'th') {
                continue;
            }
            $statusDescription = $domXPath->query('td[4]', $row)->item(0)->nodeValue ?? '';
            $fullDate = $domXPath->query('td[1]', $row)->item(0)->nodeValue
                . ' ' . date('Y') . ' '
                . $domXPath->query('td[2]', $row)->item(0)->nodeValue;
            $location = $domXPath->query('td[3]', $row)->item(0)->nodeValue;

            $event = new Event();
            $event->setDate($fullDate);
            $event->setDescription($statusDescription);
            $event->setLocation($location);
            $status = $this->resolveState($statusDescription);
            $event->setStatus($status);
            $track->addEvent($event);
        }
        return $track->sortEvents();
    }

    protected function resolveState($status)
    {
        // replace double spaces with single space
        $status = preg_replace('/\s+/', ' ', $status);
        // remove leading and trailing spaces
        $status = trim($status);
        $status = strtolower($status);

        switch (true) {
            case strpos($status, 'transit') !== false:
            case strpos($status, 'vehicle') !== false:
            case strpos($status, 'arriving') !== false:
            case strpos($status, 'arrived') !== false:
            case strpos($status, 'facility') !== false:
            case strpos($status, 'departed') !== false:
                return Track::STATUS_IN_TRANSIT;
            case strpos($status, 'picked') !== false:
                return Track::STATUS_PICKUP;
            case strpos($status, 'exception') !== false:
                return Track::STATUS_EXCEPTION;
            case $status === 'delivered':
                return Track::STATUS_DELIVERED;
            default:
                return Track::STATUS_UNKNOWN;
        }
    }
}