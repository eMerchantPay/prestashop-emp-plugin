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

{if version_compare($ps_version, '1.5', '>=') && version_compare($ps_version, '1.6', '<') }
    {if $payment_methods['checkout']}
    <p class="payment_module">
        <a href="{$url_checkout}">
            <img src="{$module_path}/assets/img/checkout.png" alt="{l s="eMerchantPay Logo" mod="emerchantpay"}" style="width:224px;" />
            {l s="Pay safely with eMerchantPay" mod="emerchantpay"}
        </a>
    </p>
    {/if}

    {if $payment_methods['direct']}
    <p class="payment_module">
        <div class="emerchantpay-container" style="margin-top:-15px;">

            <div class="row no-gutter">
                <div class="col-xs-12">
                    <h3 style="margin-bottom:19px;color:#aaa;">
                        <i class="fa fa-credit-card"></i>
                        {l s="Pay with Credit / Debit Card" mod="emerchantpay"}
                    </h3>
                </div>
            </div>

            {if $errors['direct']}
                <div class="row row-spacer">
                    <div class="alert alert-warning alert-dismissable error-wrapper">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        {$errors['direct']|escape:html:'UTF-8'}
                    </div>
                </div>
            {/if}

            <div class="row no-gutter">
                <div class="col-sm-3 no-gutter"></div>
                <div class="col-xs-12 col-sm-6 no-gutter">
                    <div class="payment-direct">
                        <div class="card-wrapper"></div>

                        <div class="form-wrapper">
                            <div class="form-group active">
                                <form action="{$urls['direct']}" autocomplete="off" id="{$module_name}-form" method="post">
                                    <input autocomplete="off" placeholder="{l s="Card Number"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-number">
                                    <input autocomplete="off" placeholder="{l s="Card Holder"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-name">
                                    <input autocomplete="off" placeholder="{l s="Month / Year" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-expiry">
                                    <input autocomplete="off" placeholder="{l s="CVV/CVV2/CSC" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-cvc">
                                    <input class="form-control submit" type="submit" name="submit{$module_name}Direct" value="{l s="Pay" mod="emerchantpay"}"/>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3 no-gutter"></div>
            </div>
        </div>
    </p>
    {/if}
{/if}

{if version_compare($ps_version, '1.6', '>=') && version_compare($ps_version, '1.7', '<') }
    <style>
    p.payment_module a.emerchantpay {
        padding-left:16px;
    }
    p.payment_module a.emerchantpay:after {
          display: block;
          content: "\f054";
          position: absolute;
          right: 15px;
          margin-top: -11px;
          top: 50%;
          font-family: 'FontAwesome', sans-serif;
          font-size: 25px;
          height: 22px;
          width: 14px;
          color: #777777;
    }
    </style>
    {if $payment_methods['checkout']}
    <div class="row">
        <div class="col-xs-12 col-md-6">
             <p class="payment_module">
                <a class="emerchantpay" href="{$urls['checkout']}">
                    <img src="{$module_path}/assets/img/checkout.png" alt="{l s="eMerchantPay Logo" mod="emerchantpay"}" style="width:224px;" />
                    <span>{l s="Pay safely with eMerchantPay" mod="emerchantpay"}</span>
                </a>
            </p>
        </div>
    </div>
    {/if}
    {if $payment_methods['direct']}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <div class="emerchantpay-container">
                    <div class="row no-gutter">
                        <div class="col-xs-12">
                            <h3 style="margin-bottom:19px;">
                                <i class="icon icon-credit-card"></i>
                                {l s="Pay with Credit / Debit Card" mod="emerchantpay"}
                            </h3>
                        </div>
                    </div>

                    {if $errors['direct']}
                    <div class="row row-spacer">
                        <div class="alert alert-warning alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$errors['direct']|escape:html:'UTF-8'}
                        </div>
                    </div>
                    {/if}

                    <div class="row payment-direct no-gutter">
                        <div class="col-md-1 col-lg-2"></div>
                        <div class="col-xs-12 col-sm-6 col-md-5 col-lg-4 no-gutter">
                            <div class="">
                                <div class="card-wrapper"></div>
                            </div>
                        </div>

                        <div class="col-xs-12 col-sm-6 col-md-5 col-lg-4 no-gutter">
                            <div class="form-wrapper">
                                <div class="form-group active">
                                    <form action="{$urls['direct']}" autocomplete="off" id="{$module_name}-form" method="post" enctype="multipart/form-data">
                                        <input autocomplete="off" placeholder="{l s="Card number"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-number">
                                        <input autocomplete="off" placeholder="{l s="Card holder"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-name">
                                        <input autocomplete="off" placeholder="{l s="Expiration date (month / year)" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-expiry">
                                        <input autocomplete="off" placeholder="{l s="CVV / CVV2 / CSC" mod="emerchantpay"}" class="form-control" name="{$module_name}-cvc">
                                        <input class="form-control submit" type="submit" name="submit{$module_name}Direct" value="{l s="Pay" mod="emerchantpay"}"/>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-1 col-lg-2"></div>
                    </div>
                </div>
            </p>
        </div>
    </div>
    {/if}
{/if}

<style>
    .emerchantpay-container {
        /*width:100%;*/
        background: #FFF;
        border:1px solid #ddd;
        border-radius:16px;
        padding:22px 12px;
        overflow:hidden;
    }

    .emerchantpay-container .no-gutter {
        margin:0;
        padding:0;
    }

    .emerchantpay-container .row-spacer {
        margin-bottom:16px;
    }

    .emerchantpay-container input {
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        box-sizing: border-box;
        max-width: 350px !important;
    }

    .emerchantpay-logo {
        border:none;
        padding:0;
        margin:0;
    }
    .emerchantpay-logo img {
        width:226px;
        outline:none;
    }

    .payment-checkout {
        position:relative;
    }
    .payment-checkout .center-wrapper {
        position: relative;
        display: block;
        top: 50%;
        margin-top: -1000px;
        height: 2000px;
        text-align: center;
        line-height: 2000px;
    }
    .payment-checkout .center-wrapper .wrap {
        line-height: 0;
    }
    .payment-checkout .center-wrapper .wrap img {
        width: 85%;
        max-width: 350px;
        vertical-align: middle;
    }

    .payment-direct .card-wrapper {
        display:block;
        margin-bottom:16px;
    }

    .payment-direct .form-wrapper .form-group {
        margin: 0 auto;
    }
    .payment-direct .form-wrapper .form-group input {
        width:98%;
        margin:0 auto 16px auto;
        height: 36px;
        padding: 0 8px;
    }

    .payment-direct .form-wrapper .form-group input.submit {
        box-shadow: none;
        border-radius: 6px !important;
        background: #5F604B;
        color: #fff;
        height:40px;
        line-height: 8px;
        cursor: pointer;
        cursor: hand;
        margin: 0 auto;
    }
    .payment-direct .form-wrapper .form-group input.submit:hover {
        text-decoration: underline;
    }
</style>

<script type="text/javascript">
    new Card({
        form:       '#{$module_name}-form',
        container:  '.card-wrapper',
        formSelectors: {
            nameInput:  'input[name="{$module_name}-name"]',
            numberInput:'input[name="{$module_name}-number"]',
            cvcInput:   'input[name="{$module_name}-cvc"]',
            expiryInput:'input[name="{$module_name}-expiry"]'
        },
        messages: {
            legalText: '&copy;{$smarty.now|date_format: '%Y'} {$shop_name}<br/><br/>Powered by {$display_name}'
        }
    });
</script>