{*
 * Copyright (C) 2018 emerchantpay Ltd.
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
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 *}

<style type="text/css">
    #center_column a {
        text-decoration:underline;
    }
</style>

{if $emerchantpay['confirmation']['status'] == 'success'}
    <p>
        {l s='Your payment on' mod='emerchantpay'} <span class="bold">{$emerchantpay['name']['store']|escape:'htmlall':'UTF-8'}</span> {l s='is complete.' mod='emerchantpay'}
        <br /><br /><span class="bold">{l s='Your order will be sent very soon.' mod='emerchantpay'}</span>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='emerchantpay'} <a href="{$link->getPageLink('contact', true)}" target="_blank">{l s='customer support' mod='emerchantpay'}</a>.
    </p>
{elseif $emerchantpay['confirmation']['status'] == 'pending'}
    <p>
        {l s='Your payment on' mod='emerchantpay'} <span class="bold">{$emerchantpay['name']['store']|escape:'htmlall':'UTF-8'}</span> {l s='is pending processing.' mod='emerchantpay'}
        <br /><br /><span class="bold">{l s='Your order will be sent as soon as we cler your payment.' mod='emerchantpay'}</span>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='emerchantpay'} <a href="{$link->getPageLink('contact', true)}" target="_blank">{l s='customer support' mod='emerchantpay'}</a>.
    </p>
{else}
    <p>
        {l s='Your payment on' mod='emerchantpay'} <span class="bold">{$emerchantpay['name']['store']|escape:'htmlall':'UTF-8'}</span> {l s='was unsuccessful.' mod='emerchantpay'}
        <br /><br /><span class="bold">{l s='Please check your input and try again or contact us, if the problem persists.' mod='emerchantpay'}</span>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='emerchantpay'} <a href="{$link->getPageLink('contact', true)}" target="_blank">{l s='customer support' mod='emerchantpay'}</a>.
    </p>
{/if}