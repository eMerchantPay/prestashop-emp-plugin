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
{if !$warning}
<div class="emerchantpay-container">
    <div class="row row-spacer">
        <div class="emerchantpay-logo col-xs-12">
            <img src="{$logo_url}" alt="{l s="eMerchantPay Logo" mod="emerchantpay"}" />
        </div>
    </div>

    {if $payment_error}
    <div class="row row-spacer">
        <div class="alert alert-warning alert-dismissable error-wrapper">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {$payment_error|escape:html:'UTF-8'}
        </div>
    </div>
    {/if}

    <div class="row no-gutter">
        <div class="col-xs-6 no-gutter">
            <div class="payment-standard">
                <div class="card-wrapper"></div>

                <div class="form-wrapper">
                    <div class="form-group active">
                        <form action="{$form_url}" method="POST">
                            <input placeholder="{l s="Card Number"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-number">
                            <input placeholder="{l s="Card Holder"  mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-name">
                            <input placeholder="{l s="Month / Year" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-expiry">
                            <input placeholder="{l s="CVV/CVV2/CSC" mod="emerchantpay"}" class="form-control" type="text" name="{$module_name}-cvc">
                            <input class="form-control submit" type="submit" name="submit{$module_name}" value="{l s="Confirm Order" mod="emerchantpay"}"/>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-6 no-gutter">
            <div class="payment-checkout">
                <span class="center-wrapper">
                    <span class="wrap">
                        <a href="{$checkout_url}">
                            <img src="{$path}/assets/img/checkout.png" alt="{l s="eMerchantPay Logo" mod="emerchantpay"}" />
                        </a>
                    </span>
                </span>
            </div>
        </div>
    </div>

</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('.active form').card({
            container:      $('.card-wrapper'),
            nameInput:      'input[name="{$module_name}-name"]',
            cvcInput:       'input[name="{$module_name}-cvc"]',
            numberInput:    'input[name="{$module_name}-number"]',
            expiryInput:    'input[name="{$module_name}-expiry"]'
        });
        $('.payment-standard').hover(
            function() {
                $('.payment-checkout').fadeTo('slow', '0.5');
            },
            function() {
                $('.payment-checkout').fadeTo('slow', '1');
            }
        );
        $('.payment-checkout').hover(
                function() {
                    $('.payment-standard').fadeTo('slow', '0.5');
                },
                function() {
                    $('.payment-standard').fadeTo('slow', '1');
                }
        );
    });
</script>
{/if}