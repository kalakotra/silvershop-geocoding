<?php

declare(strict_types=1);

namespace SilverShop\Geocoding\Extensions;

use Psr\Log\LoggerInterface;
use SilverShop\Geocoding\Extensions\AddressGeocoding;
use SilverShop\Model\Address;
use SilverShop\ShopUserInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use Throwable;

class GeocodedUserInfo extends Extension
{
    public function contentcontrollerInit(): void
    {
        $location = ShopUserInfo::singleton()->getAddress();

        if (!$location || Controller::curr()?->getRequest()?->getVar('relocateuser')) {
            $address = Address::create();
            $address->update($this->findLocation());
            ShopUserInfo::singleton()->setAddress($address);
        }
    }

    protected function findLocation(): array
    {
        $request = Controller::curr()?->getRequest();
        if (!$request) {
            return [];
        }

        $ip = $request->getIP();
        // Rewrite localhost to test IP when available.
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            $ip = (string) Address::config()->get('test_ip');
        }

        return $this->addressFromIP($ip);
    }

    protected function addressFromIP(?string $ip): array
    {
        $geocoder = AddressGeocoding::get_geocoder();
        if (!$geocoder || !$ip) {
            return [];
        }

        $geodata = [];
        try {
            $collection = $geocoder->geocode($ip);
            if (!$collection->isEmpty()) {
                $geodata = $collection->first()->toArray();
            }
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage(), ['exception' => $e]);
        }

        $geodata = array_filter($geodata);
        $datamap = [
            'Country' => 'countryCode',
            'County' => 'county',
            'State' => 'region',
            'PostalCode' => 'zipcode',
            'Latitude' => 'latitude',
            'Longitude' => 'longitude',
        ];

        $mappeddata = [];
        foreach ($datamap as $addressfield => $geofield) {
            if (is_array($geofield)) {
                $intersection = array_intersect_key($geodata, array_fill_keys($geofield, true));
                if ($intersection) {
                    $mappeddata[$addressfield] = implode(' ', $intersection);
                }
            } elseif (isset($geodata[$geofield])) {
                $mappeddata[$addressfield] = $geodata[$geofield];
            }
        }

        return $mappeddata;
    }
}
