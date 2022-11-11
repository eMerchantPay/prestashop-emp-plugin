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

<div class="payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}">
    {if $emerchantpay['payment']['methods']['checkout']}
        {if $emerchantpay['payment']['errors']['checkout']}
            <div class="row row-spacer" style="margin-bottom: 10pt;">
                <div class="alert alert-warning alert-dismissable error-wrapper">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {$emerchantpay['payment']['errors']['checkout']|escape:'htmlall':'UTF-8'}
                </div>
            </div>
        {/if}

        <div id="payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout">
            <div class="payment-method-container">
                <div class="row">
                    <img src="{$emerchantpay['path']|escape:'htmlall':'UTF-8'}/views/img/logos/emerchantpay_checkout.png"
                         alt="{l s='emerchantpay Logo' mod='emerchantpay'}"/>
                    <span>{l s='Pay safely with emerchantpay' mod='emerchantpay'}</span>
                </div>
                <div class="row">
                    <p>
                        {l s='You will be redirected to emerchantpay, where you can safely enter your payment details and complete this order.' mod='emerchantpay'}
                    </p>
                </div>
            </div>
        </div>
    {/if}
</div>
{include file='module:emerchantpay/views/templates/hook/payment/footer.tpl'}

<style>
    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout {
        position: relative;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout.payment_module {
        padding-bottom: 20px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout .center-wrapper {
        position: relative;
        display: block;
        top: 50%;
        margin-top: -1000px;
        height: 2000px;
        text-align: center;
        line-height: 2000px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout .center-wrapper .wrap {
        line-height: 0;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout .center-wrapper .wrap img {
        width: 85%;
        max-width: 350px;
        vertical-align: middle;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout a.payment_module_link {
        line-height: 50px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout a.payment_module_link:after {
        display: block;
        content: "\f054";
        position: absolute;
        right: 15px;
        top: 25%;
        margin-top: 0;
        font-family: 'FontAwesome', sans-serif;
        font-size: 25px;
        height: 22px;
        width: 14px;
        color: #777777;
    }
</style>
