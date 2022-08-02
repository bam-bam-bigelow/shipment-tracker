<?php

use Sauladam\ShipmentTracker\ShipmentTracker;

class FedexTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\Fedex
     */
    protected $tracker;

    public function setUp(): void
    {
        parent::setUp();

//        ShipmentTracker::set(FedexMock::class, 'fedex');
        ShipmentTracker::set('fedex', \Sauladam\ShipmentTracker\Trackers\Fedex::class);
        $this->tracker = ShipmentTracker::get('fedex');
    }

    public function test_it_resolves_a_delivered_shipment()
    {

        $track = $this->tracker->track(601912732440);

        $this->assertTrue($track->delivered());
        $this->assertCount(5, $track->events());
    }
}
