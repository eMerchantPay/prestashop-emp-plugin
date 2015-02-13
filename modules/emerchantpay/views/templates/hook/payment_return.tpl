{*
* Copyright (C) 2015 eMerchantPay Ltd.
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
* @author      eMerchantPay
* @copyright   2015 eMerchantPay Ltd.
* @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
*}

{if $status == 'ok'}
    <p>{l s='Your order is complete.' mod='emerchantpay'}
        <br /><br />{l s='You can find more information about the order, ' mod='emerchantpay'} <a href="{$base_dir|escape:'htmlall':'UTF-8'}history.php">{l s='in your order history' mod='emerchantpay'}</a>.
        <br /><br />{l s='For any questions or for further information, please contact our' mod='emerchantpay'} <a href="{$base_dir|escape:'htmlall':'UTF-8'}contact-form.php">{l s='customer support' mod='emerchantpay'}</a>.
    </p>
{else}
    <p class="warning">
        {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='emerchantpay'}
        <a href="{$base_dir}contact-form.php">{l s='customer support' mod='emerchantpay'}</a>.
    </p>
{/if}
