<?php
/**
 * Copyright (C) 2015-2024 emerchantpay Ltd.
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
 * @copyright   2015-2024 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Emerchantpay\Genesis\Helpers;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Database helper methods
 */
class DbHelper
{
    /**
     * Check if DB table exists
     *
     * @param $table
     * @return bool
     */
    public static function isTableExists($table)
    {
        $result = \Db::getInstance()->executeS('SHOW TABLES LIKE \'' . $table . '\'');

        return $result && count($result) > 0;
    }

    /**
     * Check if column exists in a table
     *
     * @param $table
     * @param $field
     * @return bool
     */
    public static function isColumnExists($table, $field)
    {
        $result = \Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE \'' . $field . '\'');

        return $result && count($result) > 0;
    }
}
