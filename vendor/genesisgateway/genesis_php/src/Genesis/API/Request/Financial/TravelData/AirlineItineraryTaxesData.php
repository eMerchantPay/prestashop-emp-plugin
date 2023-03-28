<?php
/**
 * Copyright (C) 2015-2022 emerchantpay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      emerchantpay
 * @copyright   2015-2023 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */


namespace Genesis\API\Request\Financial\TravelData;

use Genesis\Exceptions\InvalidArgument;
use Genesis\API\Request\Financial\TravelData\Base\AidAttributes;

class AirlineItineraryTaxesData extends AidAttributes
{
    /**
     * @var int Fee amount of travel.
     */
    protected $feeAmount;

    /**
     * @var string Fee type
     */
    protected $feeType;

    public function __construct($feeAmount = null, $feeType = null)
    {
        $this->setFeeAmount($feeAmount);
        $this->setFeeType($feeType);
    }

    /**
     * @param $value
     *
     * @return AirlineItineraryTaxesData
     * @throws InvalidArgument
     */
    public function setFeeAmount($value)
    {
        if ($value === null) {
            $this->feeAmount = null;

            return $this;
        }
         $this->feeAmount = (int)$value;
    }

    /**
     * @param $value
     *
     * @return AirlineItineraryLegData
     * @throws InvalidArgument
     */
    public function setFeeType($value)
    {
        if ($value === null) {
            $this->feeType = null;

            return $this;
        }

        return $this->setLimitedString('feeType', $value, 1, 8);
    }

    public function getStructureName()
    {
        return 'taxes';
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'fee_amount'         => $this->feeAmount,
            'fee_type'           => $this->feeType
        ];
    }
}
