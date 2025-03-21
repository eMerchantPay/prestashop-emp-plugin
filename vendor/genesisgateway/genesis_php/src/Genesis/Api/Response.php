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

namespace Genesis\Api;

use DateTime;
use Genesis\Exceptions\InvalidMethod;
use Genesis\Exceptions\InvalidResponse;
use Genesis\Network;
use Genesis\Parser;

/**
 * Response - process/format an incoming Genesis response
 *
 * @package    Genesis
 * @subpackage Api
 *
 * @method bool isApproved()
 * @method bool isDeclined()
 * @method bool isPending()
 * @method bool isPendingAsync()
 * @method bool isError()
 * @method bool isRefunded()
 * @method bool isVoided()
 * @method bool isEnabled()
 * @method bool isDisabled()
 * @method bool isSuccess()
 * @method bool isSubmitted()
 * @method bool isPendingHold()
 * @method bool isSecondChargebacked()
 * @method bool isRepresented()
 * @method bool isInProgress()
 * @method bool isUnsuccessful()
 * @method bool isNew()
 * @method bool isUser()
 * @method bool isTimeout()
 * @method bool isChargebacked()
 * @method bool isChargebackReversed()
 * @method bool isRepresentmentReversed()
 * @method bool isPreArbitrated()
 * @method bool isActive()
 * @method bool isInvalidated()
 * @method bool isChargebackReversal()
 * @method bool isPendingReview()
 * @method bool isCancelled()
 * @method bool isAccepted()
 * @method bool isChanged()
 * @method bool isDeleted()
 * @method bool isReceived()
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Response
{
    const HEADER_CONTENT_TYPE_JSON = 'Content-Type: application/json';

    /**
     * Store parsed, response object
     *
     * @var \stdClass
     */
    public $responseObj;

    /**
     * Store the response raw data
     *
     * @var String
     */
    public $responseRaw;

    /**
     * Store the HTTP response status
     *
     * @var int
     */
    public $responseCode;

    /**
     * Genesis Request Context
     *
     * @var \Genesis\Api\Request
     */
    protected $requestCtx;

    /**
     * Initialize with NetworkContext (if available)
     *
     * @param \Genesis\Network|null $networkContext
     *
     * @throws \Genesis\Exceptions\InvalidArgument
     */
    public function __construct($networkContext = null)
    {
        if (!is_null($networkContext) && is_a($networkContext, '\Genesis\Network')) {
            $this->parseResponse($networkContext);
        }
    }

    /**
     * Parse Genesis response to stdClass and
     * apply transformation to known fields
     *
     * @param Network $network
     *
     * @throws \Genesis\Exceptions\InvalidArgument
     * @throws \Genesis\Exceptions\InvalidResponse
     */
    public function parseResponse(Network $network)
    {
        $this->responseRaw  = $network->getResponseBody();
        $this->responseCode = $network->getStatus();

        try {
            $parser = $this->getParser($network);

            $this->responseObj = $parser->getObject();
        } catch (\Exception $e) {
            throw new InvalidResponse(
                $e->getMessage(),
                $e->getCode()
            );
        }

        // Apply per-field transformations
        $this->transform([$this->responseObj]);
    }

    protected function getParser(Network $network)
    {
        if ($this->isResponseTypeJson($network->getResponseHeaders())) {
            $parser = new Parser(Parser::JSON_INTERFACE);
            $parser->parseDocument($network->getResponseBody());

            return $parser;
        }

        $parser = new Parser(Parser::XML_INTERFACE);
        $parser->skipRootNode();
        $parser->parseDocument($network->getResponseBody());

        return $parser;
    }

    /**
     * @param string $headers
     *
     * @return bool
     */
    protected function isResponseTypeJson($headers)
    {
        return stripos($headers, self::HEADER_CONTENT_TYPE_JSON) !== false;
    }

    /**
     * Check whether the request was successful
     *
     * Note: You should consult with the documentation
     * which transaction responses have status available.
     *
     * @return bool | null (on missing status)
     */
    public function isSuccessful()
    {
        $status = new Constants\Transaction\States(
            isset($this->responseObj->status) ? $this->responseObj->status : ''
        );

        if ($status->isValid()) {
            return !$status->isError();
        }

        return null;
    }

    /**
     * Check whether the transaction was partially approved
     *
     * @see Genesis_API_Documentation for more information
     *
     * @return bool | null (if inapplicable)
     */
    public function isPartiallyApproved()
    {
        if (isset($this->responseObj->partial_approval)) {
            return \Genesis\Utils\Common::filterBoolean($this->responseObj->partial_approval);
        }

        return null;
    }

    /**
     * Try to fetch a description of the received Error Code
     *
     * @return string | null (if inapplicable)
     */
    public function getErrorDescription()
    {
        if (isset($this->responseObj->code) && !empty($this->responseObj->code)) {
            return Constants\Errors::getErrorDescription($this->responseObj->code);
        }

        if (isset($this->responseObj->response_code) && !empty($this->responseObj->response_code)) {
            return Constants\Errors::getIssuerResponseCode($this->responseObj->response_code);
        }

        return null;
    }

    /**
     * Get the raw Genesis output
     *
     * @return String
     */
    public function getResponseRaw()
    {
        return $this->responseRaw;
    }

    /**
     * Get the HTTP response status
     *
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Get the parsed response
     *
     * @return \stdClass
     */
    public function getResponseObject()
    {
        return $this->responseObj;
    }

    /**
     * Set Genesis Request context
     *
     * @param $requestCtx
     */
    public function setRequestCtx($requestCtx)
    {
        $this->requestCtx = $requestCtx;
    }

    /**
     * Iterate and transform object
     *
     * @param mixed $obj
     */
    public static function transform($obj)
    {
        if (is_array($obj) || is_object($obj)) {
            foreach ($obj as &$object) {
                if (isset($object->status)) {
                    self::transformObject($object);
                }

                self::transform($object);
            }
        }
    }

    /**
     * Apply filters to an entry object
     *
     * @param \stdClass|\ArrayObject $entry
     *
     * @return mixed
     */
    public static function transformObject(&$entry)
    {
        $filters = [
            'transformFilterAmounts',
            'transformFilterTimestamp'
        ];

        foreach ($filters as $filter) {
            if (method_exists(__CLASS__, $filter)) {
                $result = call_user_func([__CLASS__, $filter], $entry);

                if ($result) {
                    $entry = $result;
                }
            }
        }
    }

    /**
     * Get formatted response amounts (instead of ISO4217, return in float)
     *
     * @param \stdClass|\ArrayObject $transaction
     *
     * @return \stdClass|\ArrayObject $transaction
     */
    public static function transformFilterAmounts($transaction)
    {
        $properties = [
            'amount',
            'leftover_amount'
        ];

        foreach ($properties as $property) {
            if (isset($transaction->{$property}) && isset($transaction->currency)) {
                $transaction->{$property} = \Genesis\Utils\Currency::exponentToAmount(
                    $transaction->{$property},
                    $transaction->currency
                );
            }
        }
        return $transaction;
    }

    /**
     * Get formatted amount (instead of ISO4217, return in float)
     *
     * @param \stdClass|\ArrayObject $transaction
     *
     * @return String | null (if amount/currency are unavailable)
     */
    public static function transformFilterAmount($transaction)
    {
        // Process a single transaction
        if (isset($transaction->currency) && isset($transaction->amount)) {
            $transaction->amount = \Genesis\Utils\Currency::exponentToAmount(
                $transaction->amount,
                $transaction->currency
            );
        }
        return $transaction;
    }

    /**
     * Get DateTime object from the timestamp inside the response
     *
     * @param \stdClass|\ArrayObject $transaction
     *
     * @return \DateTime|null (if invalid timestamp)
     */
    public static function transformFilterTimestamp($transaction)
    {
        if (isset($transaction->timestamp)) {
            try {
                $transaction->timestamp = new DateTime($transaction->timestamp);
            } catch (\Exception $e) {
                // Just log the attempt
                error_log($e->getMessage());
            }
        }

        return $transaction;
    }

    /**
     * Handle "magic" calls for status check
     *
     * @param $method
     * @param $args
     *
     * @throws InvalidMethod
     * @return bool
     */
    public function __call($method, $args)
    {
        $status = new Constants\Transaction\States(
            isset($this->responseObj->status) ? $this->responseObj->status : ''
        );

        if ($status->isValid()) {
            return $status->$method();
        }

        return false;
    }
}
