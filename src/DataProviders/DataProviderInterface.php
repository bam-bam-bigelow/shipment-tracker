<?php

namespace Sauladam\ShipmentTracker\DataProviders;

interface DataProviderInterface
{
    /**
     * Get the contents for the given URL.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url): string;

    /**
     * Post the given data to the given url.
     *
     * @param $url
     * @param $data
     *
     * @return string
     */
    public function post($url, $data): string;
}
