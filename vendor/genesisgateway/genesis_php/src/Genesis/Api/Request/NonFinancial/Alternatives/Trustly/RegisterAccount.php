<?php

/**
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author      emerchantpay
 * @copyright   Copyright (C) 2015-2025 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/MIT The MIT License
 */

namespace Genesis\Api\Request\NonFinancial\Alternatives\Trustly;

use Genesis\Api\Constants\NonFinancial\Alternatives\Trustly\ClearingHouses;
use Genesis\Api\Request\Base\NonFinancial\Alternatives\Trustly\BaseRequest;
use Genesis\Utils\Common as CommonUtils;

/**
 * Class RegisterAccount
 * @package Genesis\Api\Request\NonFinancial\Alternatives\Trustly
 *
 * @method string getAccountNumber()
 * @method string getBankNumber()
 * @method string getClearingHouse()
 * @method $this  setAccountNumber($value)
 * @method $this  setBankNumber($value)
 */
class RegisterAccount extends BaseRequest
{
    /**
     * The clearing house of the customer's bank account
     *
     * @var string
     */
    protected $clearing_house;

    /**
     * The account number of the customer's bank account
     *
     * @var string
     */
    protected $account_number;

    /**
     * The bank number of the customer's account in the given clearing house
     *
     * @var string
     */
    protected $bank_number;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('register_account');
    }

    /**
     * @return string[]
     */
    protected function allowedEmptyNotNullFields()
    {
        return [
            'bank_number' => 'bank_number'
        ];
    }

    /**
     * Set the required fields
     *
     * @return void
     */
    protected function setRequiredFields()
    {
        $requiredFields = [
            'first_name',
            'last_name',
            'birth_date',
            'user_id',
            'clearing_house',
            'account_number',
            'bank_number'
        ];

        $this->requiredFields = CommonUtils::createArrayObject($requiredFields);

        $requiredFieldValues = [
            'clearing_house' => ClearingHouses::getAll()
        ];

        $this->requiredFieldValues = CommonUtils::createArrayObject($requiredFieldValues);
    }

    /**
     * @return array
     */
    protected function getRequestStructure()
    {
        return [
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'email'          => $this->email,
            'mobile_phone'   => $this->mobile_phone,
            'national_id'    => $this->national_id,
            'birth_date'     => $this->getBirthDate(),
            'user_id'        => $this->user_id,
            'account_number' => $this->account_number,
            'clearing_house' => $this->clearing_house,
            'bank_number'    => $this->bank_number
        ];
    }
}
