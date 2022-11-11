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
    {if ($emerchantpay['payment']['methods']['direct'] && $emerchantpay['ssl']['enabled'])}
        <div id="payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct">
            <div class="row">
                <div class="col-xs-12">
                    <div class="payment-method-container">
                        <div class="payment-method-header">
                            <div class="row">
                                <div class="col-xs-12">
                                    <img src="{$emerchantpay['path']|escape:'htmlall':'UTF-8'}/views/img/logos/emerchantpay_direct.png"
                                         alt="{l s='emerchantpay Logo' mod='emerchantpay'}" style="width:224px;"/>
                                    <span>&nbsp;{l s='Pay with Credit / Debit Card' mod='emerchantpay'}</span>
                                </div>
                            </div>
                        </div>

                        {if $emerchantpay['payment']['errors']['direct']}
                            <div class="payment-method-status">
                                <div class="row row-spacer">
                                    <div class="alert alert-warning alert-dismissable error-wrapper">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        {$emerchantpay['payment']['errors']['direct']|escape:'htmlall':'UTF-8'}
                                    </div>
                                </div>
                            </div>
                        {/if}

                        <div class="payment-method-content">
                            <div class="row no-gutter">
                                <div class="col-md-1 col-lg-2"></div>
                                <div class="row">
                                    <div class="card-wrapper"></div>
                                </div>

                                <div class="row">
                                    <div class="form-group active" style="margin: auto; width: 350px;">
                                        <form autocomplete="off" class="payment-form" method="post">
                                            <input autocomplete="off"
                                                   placeholder="{l s='Card number'  mod='emerchantpay'}"
                                                   class="form-control" type="text" required
                                                   name="{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-number">
                                            <input autocomplete="off"
                                                   placeholder="{l s='Card holder'  mod='emerchantpay'}"
                                                   class="form-control" type="text" required
                                                   name="{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-name">
                                            <input autocomplete="off"
                                                   placeholder="{l s='Expiration date (month / year)' mod='emerchantpay'}"
                                                   class="form-control" type="text" required
                                                   name="{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-expiry">
                                            <input autocomplete="off"
                                                   placeholder="{l s='CVV / CVV2 / CSC' mod='emerchantpay'}"
                                                   class="form-control" type="text" required
                                                   name="{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-cvc">
                                        </form>
                                    </div>
                                </div>

                                <div class="col-md-1 col-lg-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>

{if !$emerchantpay['payment']['methods']['checkout']}
    {include file='module:emerchantpay/views/templates/hook/payment/footer.tpl'}
{/if}

<style>
    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container input {
        margin: 5pt 0;
    }
</style>

<style>
    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct {
        padding-top: 16px !important;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container {
        /*width:100%;*/
        background: #FFF;
        padding: 22px 12px;
        overflow: hidden;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .no-gutter {
        margin: 0;
        padding: 0;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .payment-method-status .row-spacer {
        margin-bottom: 16px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container input {
        border: 1px solid #ccc;
        max-width: 350px !important;
        background-color: #fff;
        color: #000;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        box-sizing: border-box;
        -webkit-border-radius: 5pt;
        -moz-border-radius: 5pt;
        border-radius: 5pt;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .payment-method-content .card-wrapper {
        display: block;
        padding-top: 16px;
        margin-bottom: 16px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .payment-method-content .form-wrapper .form-group {
        margin: 16px auto 0 auto;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .payment-method-content .form-wrapper .form-group input {
        width: 98%;
        margin: 0 auto 16px auto;
        height: 36px;
        padding: 0 8px;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .payment-method-content .form-wrapper .form-group input.submit {
        box-shadow: none;
        border-radius: 6px !important;
        background: #5F604B;
        color: #fff;
        height: 40px;
        line-height: 8px;
        margin: 0 auto;
    }

    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .jp-card-container,
    #payment-method-{$emerchantpay['name']['module']|escape:'htmlall':'UTF-8'}-direct .payment-method-container .payment-method-content .form-group {
        margin: 0 0 0 15px !important;
    }
</style>

{* Disable Card init if there is no Direct method available *}
{if ($emerchantpay['payment']['methods']['direct'] && $emerchantpay['ssl']['enabled'])}
    <script type="text/javascript">
        new Card({
            form: '#payment-method-{$emerchantpay['name']['module']|escape:'javascript':'UTF-8'}-direct .payment-form',
            container: '#payment-method-{$emerchantpay['name']['module']|escape:'javascript':'UTF-8'}-direct .card-wrapper',
            formSelectors: {
                nameInput: 'input[name="{$emerchantpay['name']['module']|escape:'javascript':'UTF-8'}-name"]',
                numberInput: 'input[name="{$emerchantpay['name']['module']|escape:'javascript':'UTF-8'}-number"]',
                cvcInput: 'input[name="{$emerchantpay['name']['module']|escape:'javascript':'UTF-8'}-cvc"]',
                expiryInput: 'input[name="{$emerchantpay['name']['module']|escape:'javascript':'UTF-8'}-expiry"]'
            },
            messages: {
                legalText: '&copy;{$emerchantpay['legal']['year']|escape:'javascript':'UTF-8'} {$emerchantpay['name']['display']|escape:'javascript':'UTF-8'}<br/><br/>{$emerchantpay['name']['store']|escape:'javascript':'UTF-8'}'
            }
        });
    </script>
{/if}
