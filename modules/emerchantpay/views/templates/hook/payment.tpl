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
        <a href="{$checkout_url}">
            <img src="{$module_path}/assets/img/checkout.png" alt="{l s="eMerchantPay Logo" mod="emerchantpay"}" style="width:224px;" />
            {l s="Pay safely with eMerchantPay" mod="emerchantpay"}
        </a>
    </p>
    {/if}

    {if $payment_methods['standard']}
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

            {if $error_standard}
                <div class="row row-spacer">
                    <div class="alert alert-warning alert-dismissable error-wrapper">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        {$error_standard|escape:html:'UTF-8'}
                    </div>
                </div>
            {/if}

            <div class="row no-gutter">
                <div class="col-sm-3 no-gutter"></div>
                <div class="col-xs-12 col-sm-6 no-gutter">
                    <div class="payment-standard">
                        <div class="card-wrapper"></div>

                        <div class="form-wrapper">
                            <div class="form-group active">
                                <form action="{$standard_url}" method="POST">
                                    <input placeholder="{l s="Card Number"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-number">
                                    <input placeholder="{l s="Card Holder"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-name">
                                    <input placeholder="{l s="Month / Year" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-expiry">
                                    <input placeholder="{l s="CVV/CVV2/CSC" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-cvc">
                                    <input class="form-control submit" type="submit" name="submit{$module_name}Standard" value="{l s="Pay" mod="emerchantpay"}"/>
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
          font-family: "FontAwesome";
          font-size: 25px;
          height: 22px;
          width: 14px;
          color: #777777; }
    </style>
    {if $payment_methods['checkout']}
    <div class="row">
        <div class="col-xs-12 col-md-6">
             <p class="payment_module">
                <a class="emerchantpay" href="{$checkout_url}">
                    <img src="{$module_path}/assets/img/checkout.png" alt="{l s="eMerchantPay Logo" mod="emerchantpay"}" style="width:224px;" />
                    <span>{l s="Pay safely with eMerchantPay" mod="emerchantpay"}</span>
                </a>
            </p>
        </div>
    </div>
    {/if}
    {if $payment_methods['standard']}
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

                    {if $error_standard}
                    <div class="row row-spacer">
                        <div class="alert alert-warning alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$error_standard|escape:html:'UTF-8'}
                        </div>
                    </div>
                    {/if}

                    <div class="row payment-standard no-gutter">
                        <div class="col-md-1 col-lg-2"></div>
                        <div class="col-xs-12 col-sm-6 col-md-5 col-lg-4 no-gutter">
                            <div class="">
                                <div class="card-wrapper"></div>
                            </div>
                        </div>

                        <div class="col-xs-12 col-sm-6 col-md-5 col-lg-4 no-gutter">
                            <div class="form-wrapper">
                                <div class="form-group active">
                                    <form action="{$standard_url}" method="POST" style="padding-top:6px;">
                                        <input placeholder="{l s="Card Number"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-number">
                                        <input placeholder="{l s="Card Holder"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-name">
                                        <input placeholder="{l s="Month / Year" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-expiry">
                                        <input placeholder="{l s="CVV/CVV2/CSC" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-cvc">
                                        <input class="form-control submit" type="submit" name="submit{$module_name}Standard" value="{l s="Pay" mod="emerchantpay"}"/>
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

<script type="text/javascript">
    $(document).ready(function() {
        $('.active form').card({
            container:      $('.card-wrapper'),
            nameInput:      'input[name="{$module_name}-name"]',
            cvcInput:       'input[name="{$module_name}-cvc"]',
            numberInput:    'input[name="{$module_name}-number"]',
            expiryInput:    'input[name="{$module_name}-expiry"]'
        });
    });
</script>