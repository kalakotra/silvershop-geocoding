<?php

declare(strict_types=1);

namespace SilverShop\Geocoding\Extensions;

use BetterBrief\GoogleMapField;
use Geocoder\Geocoder;
use Psr\Log\LoggerInterface;
use SilverShop\Model\Address;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use Throwable;

class AddressGeocoding extends Extension
{
    private static $db = [
        'Latitude' => 'Decimal(10,8)', // -90 to 90 degrees
        'Longitude' => 'Decimal(11,8)', // -180 to 180 degrees
    ];

    private static ?Geocoder $inst = null;

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldToTab(
            'Root.Main',
            GoogleMapField::create($this->owner, 'Location', [
                'fieldNames' => [
                    'lat' => 'Latitude',
                    'lng' => 'Longitude',
                ],
                'showSearchBox' => false,
            ])
        );

        $fields->removeByName('Latitude');
        $fields->removeByName('Longitude');
    }

    /**
     * Get the configured geocoder instance.
     */
    public static function get_geocoder(): ?Geocoder
    {
        return self::$inst;
    }

    public static function set_geocoder(Geocoder $geocoder): void
    {
        self::$inst = $geocoder;
    }

    public function onBeforeWrite(): void
    {
        if (!$this->owner->Latitude && !$this->owner->Longitude && Address::config()->get('enable_geocoding')) {
            $this->geocodeAddress();
        }
    }

    public function geocodeAddress(): void
    {
        $geocoder = self::get_geocoder();
        if (!$geocoder) {
            return;
        }

        try {
            $results = $geocoder->geocode($this->owner->toString());
            if (!$results->isEmpty()) {
                $coordinates = $results->first()->getCoordinates();
                if ($coordinates) {
                    $this->owner->Latitude = $coordinates->getLatitude();
                    $this->owner->Longitude = $coordinates->getLongitude();
                }
            }
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Provide distance (in km) to given address.
     * Returns null if inadequate info is present.
     */
    public function distanceTo(Address $address): ?float
    {
        if (
            !$this->owner->Latitude ||
            !$this->owner->Longitude ||
            !$address->Latitude ||
            !$address->Longitude
        ) {
            return null;
        }

        return self::haversine_distance(
            (float) $this->owner->Latitude,
            (float) $this->owner->Longitude,
            (float) $address->Latitude,
            (float) $address->Longitude
        ) / 1000; // convert meters to km
    }

    /**
     * Calculates the great-circle distance between two points using the Haversine formula.
     */
    public static function haversine_distance(
        float $latitudeFrom,
        float $longitudeFrom,
        float $latitudeTo,
        float $longitudeTo,
        float $earthRadius = 6371000
    ): float {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}
