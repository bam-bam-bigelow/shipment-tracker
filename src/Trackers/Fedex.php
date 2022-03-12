<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class Fedex extends AbstractTracker
{
    protected $trackingUrl = 'https://www.fedex.com/apps/fedextrack/';

    protected $serviceEndpoint = 'https://api.fedex.com/track/v2/shipments';

    /** @var array */
    protected static $cookies = [];

    /** @var string */
    protected static $accessToken;

    /**
     * Get the contents of the given url.
     *
     * @param string $url
     *
     * @return string
     * @throws \Exception
     */
    protected function fetch($url)
    {
        if (empty(static::$accessToken)) {
            $this->getAccessToken();
        }

        try {
            return $this->getDataProvider()->client->post($this->serviceEndpoint, $this->buildRequest())
                ->getBody()
                ->getContents();

        } catch (\Exception $e) {
            throw new \Exception("Could not fetch tracking data for [{$this->parcelNumber}].");
        }
    }

    /**
     * @return array
     */
    protected function buildRequest()
    {
        return [
            'headers' => [
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-site',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Content-Type' => 'application/json',
                'X-clientid' => 'WTRK',
                'X-locale' => 'en_US',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => 'https://www.fedex.com',
                'DNT' => '1',
                'TE' => 'trailers',
                'Connection' => 'keep-alive',
                'X-version' => '1.0.0',
                'Referer' => $this->trackingUrl($this->parcelNumber),
                'Cookie' => implode(';', array_map(function ($name) {
                    return $name . '=' . static::$cookies[$name];
                }, array_keys(static::$cookies))),
                'Authorization' => 'Bearer ' . self::$accessToken,
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0'
            ],
            'body' => $this->buildNewDataArray()
        ];
    }

    /**
     * @return false|string
     */
    protected function buildNewDataArray()
    {
        $array = [
            'appDeviceType' => 'WTRK',
            'appType' => 'WTRK',
            'supportCurrentLocation' => true,
            'trackingInfo' => [
                [
                    'trackNumberInfo' => [
                        'trackingCarrier' => '',
                        'trackingNumber' => $this->parcelNumber,
                        'trackingQualifier' => ''
                    ]
                ]
            ],
            'uniqueKey' => '',
        ];

        return json_encode($array);
    }


    /**
     * @return false|string
     */
    protected function buildDataArray()
    {
        $array = [
            'TrackPackagesRequest' => [
                'trackingInfoList' => [
                    [
                        'trackNumberInfo' => [
                            'trackingNumber' => $this->parcelNumber,
                        ]
                    ]
                ]
            ]
        ];

        return json_encode($array);
    }

    /**
     * Build the url to the user friendly tracking site. In most
     * cases this is also the endpoint, but sometimes the tracking
     * data must be retrieved from another endpoint.
     *
     * @param string $trackingNumber
     * @param string|null $language
     * @param array $params
     *
     * @return string
     */
    public function trackingUrl($trackingNumber, $language = null, $params = [])
    {
        return $this->trackingUrl . '?tracknumbers=' . $trackingNumber;
    }

    /**
     * Build the response array.
     *
     * @param string $response
     *
     * @return \Sauladam\ShipmentTracker\Track
     */
    protected function buildResponse($response)
    {
        $contents = json_decode($response, true)['output']['packages'][0];

        $track = new Track;

        foreach ($contents['scanEventList'] as $scanEvent) {
            $track->addEvent(Event::fromArray([
                'location' => $scanEvent['scanLocation'],
                'description' => $scanEvent['status'],
                'date' => $this->getDate($scanEvent),
                'status' => $status = $this->resolveState($scanEvent)
            ]));

            if ($status == Track::STATUS_DELIVERED && isset($contents['receivedByNm'])) {
                $track->setRecipient($contents['receivedByNm']);
            }
        }

        if (isset($contents['totalKgsWgt'])) {
            $track->addAdditionalDetails('totalKgsWgt', $contents['totalKgsWgt']);
        }

        if (isset($contents['totalLbsWgt'])) {
            $track->addAdditionalDetails('totalLbsWgt', $contents['totalLbsWgt']);
        }

        if (isset($contents['pkgLbsWgt'])) {
            $track->addAdditionalDetails('pkgLbsWgt', $contents['pkgLbsWgt']);
        }

        if (isset($contents['isDelivered'])) {
            $track->addAdditionalDetails('isDelivered', $contents['isDelivered']);
        }


        return $track->sortEvents();
    }

    /**
     * Parse the date from the given strings.
     *
     * @param array $scanEvent
     *
     * @return \Carbon\Carbon
     */
    protected function getDate($scanEvent)
    {
        return Carbon::parse(
            $this->convert("{$scanEvent['date']}T{$scanEvent['time']}{$scanEvent['gmtOffset']}")
        );
    }

    /**
     * Convert unicode characters
     *
     * @param string $string
     * @return string
     */
    protected function convert($string)
    {
        if (PHP_MAJOR_VERSION >= 7) {
            return preg_replace('/(?<=\\\u)(.{4})/', '{$1}', $string);
        } else {
            return str_replace('\\u002d', '-', $string);
        }
    }

    /**
     * Match a shipping status from the given short code.
     *
     * @param $status
     *
     * @return string
     */
    protected function resolveState($status)
    {
        switch ($status['statusCD']) {
            case 'PU':
            case 'OC':
            case 'AR':
            case 'DP':
            case 'OD':
                return Track::STATUS_IN_TRANSIT;
            case 'DL':
                return Track::STATUS_DELIVERED;
            default:
                return Track::STATUS_UNKNOWN;
        }
    }

    protected function getAccessToken()
    {
        $response = $this->getDataProvider()->client->request(
            'GET',
            'https://www.fedex.com/fedextrack/properties/WTRKProperties.json?_=006c784-1647032400'
        );
        $body = $response->getBody()->getContents();
        $arr = json_decode($body, true);
        if (empty($arr['api'])) {
            return;
        }
        $clientId = $arr['api']['client_id'] ?? '';
        $clientSecret = $arr['api']['client_secret'] ?? '';

        // get Bearer
        $response = $this->getDataProvider()->client->request(
            'POST', 'https://api.fedex.com/auth/oauth/v2/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret
                ],
                [
                    'cookies' => $jar = new CookieJar,
                ]
            ]
        );
        foreach ($jar->toArray() as $cookie) {
            static::$cookies[$cookie['Name']] = $cookie['Value'];
        }

        $body = $response->getBody()->getContents();
        $arr = json_decode($body, true);
        self::$accessToken = $arr['access_token'];
    }

}
