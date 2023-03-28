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


namespace Genesis\API\Request\Financial\Preauthorization;

use Genesis\API\Constants\Transaction\Types;
use Genesis\API\Request\Base\Financial;

/**
 * Class IncrementalAuthorize
 * @package Genesis\API\Request\Financial\Preauthorization
 */
class IncrementalAuthorize extends Financial\Reference
{
    /**
     * Returns the Request transaction type
     * @return string
     */
    protected function getTransactionType()
    {
        return Types::INCREMENTAL_AUTHORIZE;
    }
}
