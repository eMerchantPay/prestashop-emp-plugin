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

namespace Emerchantpay\Genesis\Helpers\Forms;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Form fields helper base class
 */
class FormGenerator
{
    /**
     * @var \PaymentModule
     */
    private $module;

    /**
     * @param \PaymentModule $module
     */
    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Generate form select options from array.
     * Array key will be used as id attribute, value as name
     *
     * @param array $options
     * @param string $id
     * @param string $name
     *
     * @return mixed
     */
    public function generateOptionsFromArray($options, $id = 'id', $name = 'name')
    {
        foreach ($options as $key => &$value) {
            $value = [
                $id => $key,
                $name => $this->module->l($value),
            ];
        }

        return $options;
    }

    /**
     * Base / common settings field method
     *
     * @param string $type
     * @param string $label
     * @param string $description
     * @param string $name
     * @param mixed $extra
     *
     * @return array
     */
    protected function baseField($type, $label, $description, $name, $extra = [])
    {
        $field = [
            'type' => $type,
            'label' => $this->module->l($label),
            'desc' => $this->module->l($description),
            'name' => $name,
        ];

        return array_merge($field, $extra);
    }

    /**
     * Base input field method
     *
     * @param string $label
     * @param string $description
     * @param string $name
     * @param string $type
     * @param int $size
     * @param bool $required
     *
     * @return array
     */
    public function inputField($label, $description, $name, $type = 'text', $size = 20, $required = true)
    {
        $extra = [
            'size' => $size,
            'required' => $required,
        ];

        return $this->baseField($type, $label, $description, $name, $extra);
    }

    /**
     * Base select field method
     *
     * @param string $label
     * @param string $description
     * @param string $id
     * @param string $name
     * @param array $options
     * @param bool $multiple
     *
     * @return array
     */
    public function selectField($label, $description, $id, $name, $options, $multiple = false)
    {
        $extra = [
            'id' => $id,
            'multiple' => $multiple,
            'options' => [
                'query' => $options,
                'id' => 'id',
                'name' => 'name',
            ],
        ];

        return $this->baseField('select', $label, $description, $name, $extra);
    }

    /**
     * Base switch field method
     *
     * @param string $label
     * @param string $description
     * @param string $name
     * @param array $values
     *
     * @return array
     */
    public function switchField($label, $description, $name)
    {
        $extra = [
            'values' => [
                [
                    'id' => 'active_on',
                    'value' => '1',
                ],
                [
                    'id' => 'active_off',
                    'value' => '0',
                ],
            ],
        ];

        return $this->baseField('switch', $label, $description, $name, $extra);
    }
}
