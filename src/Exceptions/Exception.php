<?php
/**
 * Copyright (C) 2015-2025 emerchantpay Ltd.
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
 * @copyright   2015-2025 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Emerchantpay\Genesis\Exceptions;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Base Exception class
 */
class Exception extends \Exception
{
    /**
     * Default constructor
     *
     * @param $message
     * @param $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->message = $this->getCustomMessage();
    }

    /**
     * Custom message
     *
     * @return string
     */
    protected function getCustomMessage()
    {
        return $this->getMessage();
    }
}
