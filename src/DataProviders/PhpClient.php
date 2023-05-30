<?php

namespace Sauladam\ShipmentTracker\DataProviders;

class PhpClient implements DataProviderInterface
{
    /**
     * Request the given url.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url): string
    {
        return file_get_contents($url);
    }

    /**
     * Post the given data to the given url.
     *
     * @param $url
     * @param $data
     *
     * @return string
     */
    public function post($url, $data): string
    {
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
            ],
        ];

        return file_get_contents($url, false, stream_context_create($options));
    }
}
