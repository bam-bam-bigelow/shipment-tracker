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
        $this->client = new Client();
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
