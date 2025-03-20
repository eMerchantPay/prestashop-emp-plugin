{**
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
 *}

    {capture name=path}{l s='emerchantpay Checkout' mod='emerchantpay'}{/capture}

    <h1 class="page-heading">{l s='Order summary' mod='emerchantpay'}</h1>

    {assign var='current_step' value='payment'}
    {include file="$tpl_dir./order-steps.tpl"}

    {if isset($nbProducts) && $nbProducts <= 0}
        <p class="alert alert-warning">{l s='Your shopping cart is empty.' mod='emerchantpay'}</p>
    {else}

    {if $emerchantpay['checkout']['error']}
        <div class="row row-spacer" style="margin:0;">
            <div class="alert alert-warning alert-dismissable error-wrapper">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {$emerchantpay['checkout']['error']|escape:'htmlall':'UTF-8'}
            </div>
        </div>
    {/if}

        <form action="{$emerchantpay['checkout']['links']['confirm']|escape:'htmlall':'UTF-8'}" method="post">
            <input type="hidden" name="checkout" value="confirmed" />
            <input type="hidden" name="submit{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}Checkout" value="true" />

            <div class="box cheque-box">
                <h3 class="page-subheading">{l s='emerchantpay Checkout' mod='emerchantpay'}</h3>
                <p>
                    <strong class="dark">
                        {l s='You have chosen to pay via emerchantpay\'s Secure Checkout.' mod='emerchantpay'}
                        <br/>
                        {l s='Here is a short summary of your order:' mod='emerchantpay'}
                    </strong>
                </p>
                <p style="margin-top:20px;">
                    - {l s='The total amount of your order comes to:' mod='emerchantpay'}
                    <span id="amount" class="price">{displayPrice price=$emerchantpay['checkout']['total']}</span>
                    {if $use_taxes == 1}
                        {l s='(tax incl.)' mod='emerchantpay'}
                    {/if}
                </p>
                <p>
                    - {l s='You will be redirected to our emerchantpay\'s website, where you can safely enter your payment details and complete this order.' mod='emerchantpay'}
                    <br />
                    - <b>{l s='Please confirm your order by clicking "I confirm my order".' mod='emerchantpay'}</b>
                </p>
            </div>

            <p class="cart_navigation clearfix" id="cart_navigation">
                <a href="{$emerchantpay['checkout']['links']['back']|escape:'htmlall':'UTF-8'}" class="button-exclusive btn btn-default">
                    <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='emerchantpay'}
                </a>
                <button type="submit" class="button btn btn-default button-medium">
                    <span>{l s='I confirm my order' mod='emerchantpay'}<i class="icon-chevron-right right"></i></span>
                </button>
            </p>
        </form>
    {/if}
