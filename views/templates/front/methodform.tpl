{**
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
 *}

<form class="payment-option-form-{$method_name|escape:'htmlall':'UTF-8'}"
      method="post"
      action="{$submit_form_action|escape:'htmlall':'UTF-8'}"
      onsubmit="{$on_submit_callback|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="{$method_input_name|escape:'htmlall':'UTF-8'}" value="1" />
</form>
