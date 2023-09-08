<?php

namespace Sauladam\ShipmentTracker\DataProviders;

use GuzzleHttp\Client;
use Sauladam\ShipmentTracker\Exceptions\RequestException;
use Throwable;

class GuzzleClient implements DataProviderInterface
{
    /**
     * @var Client
     */
    public $client;


    /**
     * GuzzleClient constructor.
     */
    public function __construct()
    {
        $config = [];
        $config['headers'] = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/117.0',
            'TE' => 'trailers',
            'Sec-Fetch-Dest' => 'empty',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.8',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
            'Accept' => 'application/json'
        ];
        $this->client = new Client($config);
    }


    /**
     * Request the given url.
     *
     * @param $url
     *
     * @return string
     * @throws RequestException
     */
    public function get($url): string
    {
        try {
            return $this->client->get($url, [
                'timeout' => 10,
                'connect_timeout' => 10
            ])->getBody()->getContents();
        } catch (Throwable $exception) {
            throw new RequestException('Request failed: ' . $exception->getMessage());
        }
    }

    /**
     * Post the given data to the given url.
     *
     * @param $url
     * @param $data
     *
     * @return string
     * @throws RequestException
     */
    public function post($url, $data): string
    {
        try {
            return $this->client->post($url, [
                'connection_timeout' => 10,
                'timeout' => 10,
                'form_params' => $data
            ])->getBody()->getContents();
        } catch (Throwable $exception) {
            throw new RequestException('Request failed: ' . $exception->getMessage());
        }
    }
}
