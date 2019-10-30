<?php
namespace Kodbruket\VsfKco\Model\Klarna\DataTransform\Request;

use Magento\Directory\Model\Region;
use Magento\Framework\DataObject;


/**
 * Class Address
 * @package Kodbruket\VsfKco\Model\Klarna\DataTransform\Request
 */
class Address
{
    /**
     * @var Region
     */
    private $region;

    /**
     * Address constructor.
     * @param Region $region
     */
    public function __construct(
        Region $region
    ) {
        $this->region = $region;
    }

    /**
     * @param DataObject $klarnaAddressDto
     * @return array
     */
    public function prepareMagentoAddress(DataObject $klarnaAddressDto)
    {
        $country = strtoupper($klarnaAddressDto->getCountry());
        $address1 = $klarnaAddressDto->hasStreetName()
            ? $klarnaAddressDto->getStreetName() . ' ' . $klarnaAddressDto->getStreetNumber()
            : $klarnaAddressDto->getStreetAddress();
        if ($klarnaAddressDto->hasHouseExtension()) {
            $address1 .= ' ' . $klarnaAddressDto->getHouseExtension();
        }
        $streetData = [
            $address1,
            $klarnaAddressDto->getData('street_address2')
        ];
        $streetData = array_filter($streetData);
        $companyName = $klarnaAddressDto->getOrganizationName()
            ? $klarnaAddressDto->getOrganizationName()
            : $klarnaAddressDto->getCareOf();

        $data = [
            'lastname'      => $klarnaAddressDto->getFamilyName(),
            'firstname'     => $klarnaAddressDto->getGivenName(),
            'email'         => $klarnaAddressDto->getEmail(),
            'company'       => $companyName,
            'prefix'        => $klarnaAddressDto->getTitle(),
            'street'        => implode(',', $streetData),
            'postcode'      => $klarnaAddressDto->getPostalCode(),
            'city'          => $klarnaAddressDto->getCity(),
            'telephone'     => $klarnaAddressDto->getPhone(),
            'country_id'    => $country,
            'same_as_other' => $klarnaAddressDto->getSameAsOther() ? 1 : 0
        ];

        $region = $this->region->loadByName($klarnaAddressDto->getRegion(), $country);

        if (empty($region->getId())) {
            $region = $this->region->loadByCode($klarnaAddressDto->getRegion(), $country);
        }

        if ($region) {
            $data['region_id'] = $region->getId();
            $data['region'] = $region->getName();
        }

        if ($klarnaAddressDto->hasCustomerDob()) {
            $data['dob'] = $klarnaAddressDto->getCustomerDob();
        }

        if ($klarnaAddressDto->hasCustomerGender()) {
            $data['gender'] = $klarnaAddressDto->getCustomerGender();
        }

        return $data;
    }
}
