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
        <div id="payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout">
            <div class="row">
                <div class="col-xs-12 col-md-6">
                    <p class="payment_module">
                        <a class="payment_module_link" href="{$emerchantpay['payment']['urls']['checkout']|escape:'htmlall':'UTF-8'}">
                            <img src="{$emerchantpay['path']|escape:'htmlall':'UTF-8'}/views/img/logos/emerchantpay_checkout.png"
                                 alt="{l s='emerchantpay Logo' mod='emerchantpay'}"/>
                            <span>{l s='Pay safely with emerchantpay' mod='emerchantpay'}</span>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    {/if}
</div>

<style>
    .payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'} {
        margin-bottom: 16px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout a.payment_module_link {
        padding-left: 33px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout a.payment_module_link span {
        padding-left: 8px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-checkout a.payment_module_link::after {
        line-height: 0;
        top: 50% !important;
    }
</style>

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
