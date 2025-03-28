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

namespace Genesis\Api\Request\NonFinancial\Sca;

use Genesis\Api\Constants\Transaction\Parameters\Recurring\Types;
use Genesis\Api\Constants\Transaction\Parameters\ScaExemptions;
use Genesis\Api\Request\Base\BaseVersionedRequest;
use Genesis\Api\Traits\Request\Financial\Cards\Recurring\RecurringTypeAttributes;
use Genesis\Api\Validators\Request\RegexValidator;
use Genesis\Builder;
use Genesis\Config;
use Genesis\Utils\Common;
use Genesis\Utils\Currency;

/**
 * Class Checker
 *
 * This call is used to check if SCA is required
 *
 * @package Genesis\Api\Request\NonFinancial\Sca
 *
 * @method setTransactionCurrency($value) Transaction currency
 */
class Checker extends BaseVersionedRequest
{
    use RecurringTypeAttributes;

    const CARD_NUMBER_MIN_LENGTH = 6;
    const CARD_NUMBER_MAX_LENGTH = 16;

    /**
     * Full card number or first 6 digits
     *
     * @var $card_number
     */
    protected $card_number;

    /**
     * Amount of transaction in minor currency unit
     *
     * @var $transaction_amount
     */
    protected $transaction_amount;

    /**
     * Transaction currency
     *
     * @var $transaction_currency
     */
    protected $transaction_currency;

    /**
     * Signifies whether a MOTO (mail order telephone order) transaction is performed
     *
     * @var $moto ;
     */
    protected $moto;

    /**
     * Signifies whether a MIT (merchant initiated transaction) is performed
     *
     * @var $mit
     */
    protected $mit;

    public function setCardNumber($value)
    {
        $this->card_number = !is_null($value) ? (string) $value : null;

        return $this;
    }

    /**
     * Exemption
     *
     * @var $transaction_exemption
     */
    protected $transaction_exemption;

    /**
     * Checker constructor
     */
    public function __construct()
    {
        parent::__construct('sca/checker/' . Config::getToken(), Builder::JSON, ['v1']);
    }

    /**
     * Signifies whether a MOTO (mail order telephone order) transaction is performed
     *
     * @param $value
     * @return Checker
     */
    public function setMoto($value)
    {
        $this->moto = Common::toBoolean($value);

        return $this;
    }

    /**
     * Signifies whether a MIT (merchant initiated transaction) is performed
     *
     * @param $value
     * @return Checker
     */
    public function setMit($value)
    {
        $this->mit = Common::toBoolean($value);

        return $this;
    }

    /**
     * Exemption
     *
     * @param $value
     * @return Checker
     * @throws \Genesis\Exceptions\InvalidArgument
     */
    public function setTransactionExemption($value)
    {
        if ($value === null) {
            $this->transaction_exemption = null;

            return $this;
        }

        $this->allowedOptionsSetter(
            'transaction_exemption',
            ScaExemptions::getAll(),
            $value,
            'Invalid data for Transaction Exemption.'
        );

        return $this;
    }

    /**
     * @param $value
     * @return $this
     * @throws \Genesis\Exceptions\InvalidArgument
     */
    public function setTransactionAmount($value)
    {
        return $this->parseAmount('transaction_amount', $value);
    }

    /**
     * Set the required fields
     *
     * @return void
     */
    protected function setRequiredFields()
    {
        $requiredFields = [
            'card_number',
            'transaction_amount',
            'transaction_currency'
        ];

        $requiredFieldsValues = [
            'transaction_currency' => Currency::getList(),
            'card_number'          => $this->getCardNumberValidator()
        ];

        $this->requiredFieldValues = Common::createArrayObject($requiredFieldsValues);

        $this->requiredFields = Common::createArrayObject($requiredFields);
    }

    protected function getRequestStructure()
    {
        return [
            'card_number'           => $this->card_number,
            'transaction_amount'    => $this->amountToExponent($this->transaction_amount, $this->transaction_currency),
            'transaction_currency'  => $this->transaction_currency,
            'moto'                  => $this->moto,
            'mit'                   => $this->mit,
            'transaction_exemption' => $this->transaction_exemption,
            'recurring_type'        => $this->getRecurringType()
        ];
    }

    protected function initConfiguration()
    {
        parent::initConfiguration();

        $this->initApiGatewayConfiguration("{$this->getVersion()}/sca/checker", true);
    }

    protected function checkRequirements()
    {
        $this->requiredFieldValuesConditional = Common::createArrayObject([
            'recurring_type' => [
                $this->recurring_type => [
                    ['recurring_type' => [Types::INITIAL, Types::SUBSEQUENT]]
                ]
            ]
        ]);

        parent::checkRequirements();
    }

    /**
     * Transform Amount to Exponent
     *
     * @param $amount
     * @param $currency
     * @return float
     */
    private function amountToExponent($amount, $currency)
    {
        return (float) Currency::amountToExponent($amount, $currency);
    }

    private function getCardNumberValidator()
    {
        return new RegexValidator(
            '/^\d{' . self::CARD_NUMBER_MIN_LENGTH . ',' . self::CARD_NUMBER_MAX_LENGTH . '}$/',
            sprintf(
                'Invalid value for card_number. Allowed value - only digits with min length %s and max length %s',
                self::CARD_NUMBER_MIN_LENGTH,
                self::CARD_NUMBER_MAX_LENGTH
            )
        );
    }
}
