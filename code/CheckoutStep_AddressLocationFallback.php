<?php

declare(strict_types=1);

namespace SilverShop\Geocoding\Checkout\Step;

use BetterBrief\GoogleMapField;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Checkout\Step\CheckoutStep;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;

/**
 * Fallback step used if geocoding fails to locate address.
 */
class AddressLocationFallback extends CheckoutStep
{
    private static $allowed_actions = [
        'addresslocation',
        'AddressLocationForm',
    ];

    public function addresslocation(): array|HTTPResponse
    {
        $shippingaddress = $this->getShippingAddress();

        if ($shippingaddress && (float) $shippingaddress->Latitude && (float) $shippingaddress->Longitude) {
            return $this->owner->redirect($this->NextStepLink());
        }

        return [
            'OrderForm' => $this->AddressLocationForm(),
        ];
    }

    public function AddressLocationForm(): Form
    {
        $shippingaddress = $this->getShippingAddress();

        $config = [
            'fieldNames' => [
                'lat' => 'Latitude',
                'lng' => 'Longitude',
            ],
            'coords' => [
                '-27.7949688',
                '136.4324989',
            ],
            'map' => [
                'zoom' => 4,
            ],
            'showSearchBox' => false,
        ];

        $fields = FieldList::create(
            LiteralField::create(
                'locationneededmessage',
                '<p class="message warning">We could not automatically determine your shipping location. Please find and click the exact location on the map:</p>'
            ),
            GoogleMapField::create($shippingaddress, 'Location', $config)
                ->setDescription('Please click the exact location of your address')
        );

        $actions = FieldList::create(
            FormAction::create('setAddressLocation', 'Continue')
        );

        return Form::create($this->owner, 'AddressLocationForm', $fields, $actions);
    }

    public function setAddressLocation(array $data, Form $form): HTTPResponse
    {
        $shippingaddress = $this->getShippingAddress();
        if ($shippingaddress) {
            $form->saveInto($shippingaddress);
            $shippingaddress->write();
        }

        return $this->owner->redirect($this->NextStepLink());
    }

    protected function getShippingAddress()
    {
        return ShoppingCart::singleton()->current()?->getShippingAddress();
    }
}
