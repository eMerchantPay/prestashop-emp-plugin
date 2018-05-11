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

{if version_compare($emerchantpay['presta']['version'], '1.5', '>=') && version_compare($emerchantpay['presta']['version'], '1.6', '<') }

    {capture name=path}
        <a href="{$emerchantpay['checkout']['links']['back']|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='emerchantpay'}">
            {l s='Checkout' mod='emerchantpay'}
        </a>
        <span class="navigation-pipe">
        {$navigationPipe}
    </span>
        {l s='emerchantpay Secure Checkout' mod='emerchantpay'}
    {/capture}

    {include file="$tpl_dir./breadcrumb.tpl"}

    <h2>{l s='Order summary' mod='emerchantpay'}</h2>

    {assign var='current_step' value='payment'}
    {include file="$tpl_dir./order-steps.tpl"}

    {if $emerchantpay['checkout']['product_count'] <= 0}
        <p class="warning">{l s='Your shopping cart is empty.' mod='emerchantpay'}</p>
    {else}
        <style type="text/css">
            #module-emerchantpay-checkout #center-column {
                width: 737px;
            }
        </style>

        {if $emerchantpay['checkout']['error']}
            <div class="row row-spacer">
                <div class="alert alert-warning alert-dismissable error-wrapper" style="width:75%;margin:15px auto;">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {$emerchantpay['checkout']['error']|escape:html:'UTF-8'}
                </div>
            </div>
        {/if}

        <h3>{l s='emerchantpay Secure Checkout' mod='emerchantpay'}</h3>
        <form action="{$emerchantpay['checkout']['links']['confirm']|escape:'html'}" method="POST" name="">
            <input type="hidden" name="checkout" value="confirmed" />
            <input type="hidden" name="submit{$emerchantpay['name']['module']}Checkout" value="true" />

            <p>
                <img src="{$emerchantpay['path']}/assets/img/logos/emerchantpay.png" alt="{l s='emerchantpay Checkout' mod='emerchantpay'}" width="128" style="float:left;margin: 0 10px 5px 0;" />
                {l s='You have chosen to pay via emerchantpay Secure Checkout' mod='emerchantpay'}
            </p>
            <p style="margin-top:20px;">
                {l s='Here is a short summary of your order:' mod='emerchantpay'}
                <br/><br/>
                - {l s='The total amount of your order is' mod='emerchantpay'}
                <span id="amount" class="price">{displayPrice price=$emerchantpay['checkout']['total']}</span>
                {if $use_taxes == 1}
                    {l s='(tax incl.)' mod='emerchantpay'}
                {/if}
            </p>
            <p>
                {l s="You will be redirected to our emerchantpay's website, where you can safely enter your payment details and complete this order." mod='emerchantpay'}
                <br /><br />
                <b>{l s='Please confirm your order by clicking "I confirm my order".' mod='emerchantpay'}</b>
            </p>
            <p class="cart_navigation" id="cart_navigation">
                <input type="submit" value="{l s='I confirm my order' mod='emerchantpay'}" class="exclusive_large" />
                <a href="{$emerchantpay['checkout']['links']['back']|escape:'html':'UTF-8'}" class="button_large">{l s='Other payment methods' mod='emerchantpay'}</a>
            </p>
        </form>
    {/if}

{/if}

{if version_compare($emerchantpay['presta']['version'], '1.6', '>=') && version_compare($emerchantpay['presta']['version'], '1.7', '<') }

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
                {$emerchantpay['checkout']['error']|escape:html:'UTF-8'}
            </div>
        </div>
    {/if}

        <form action="{$emerchantpay['checkout']['links']['confirm']|escape:'html'}" method="post">
            <input type="hidden" name="checkout" value="confirmed" />
            <input type="hidden" name="submit{$emerchantpay['name']['module']}Checkout" value="true" />

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
                    - {l s="You will be redirected to our emerchantpay's website, where you can safely enter your payment details and complete this order." mod='emerchantpay'}
                    <br />
                    - <b>{l s='Please confirm your order by clicking "I confirm my order".' mod='emerchantpay'}</b>
                </p>
            </div>

            <p class="cart_navigation clearfix" id="cart_navigation">
                <a href="{$emerchantpay['checkout']['links']['back']|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
                    <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='emerchantpay'}
                </a>
                <button type="submit" class="button btn btn-default button-medium">
                    <span>{l s='I confirm my order' mod='emerchantpay'}<i class="icon-chevron-right right"></i></span>
                </button>
            </p>
        </form>
    {/if}

{/if}