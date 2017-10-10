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

<style type="text/css">
    #center_column a {
        text-decoration:underline;
    }
</style>

{if $emerchantpay['redirect']['status'] == 'success'}

    {capture name=path}{l s='Payment Status'}{/capture}

    <div id="center_column" class="center_column">
        <h1 class="page-heading bottom-indent">
            {l s='Your payment is complete' mod='emerchantpay'}
        </h1>

        <div class="row">
            <div class="col-xs-12">
                <p>
                    {l s='You can find more information about the order, ' mod='emerchantpay'}
                    <a href="{$emerchantpay['redirect']['url']['history']}">{l s='in your order history' mod='emerchantpay'}</a>.

                    <br /><br />{l s='For any questions or for further information, please contact our' mod='emerchantpay'}
                    <a href="{$emerchantpay['redirect']['url']['support']}">{l s='customer support' mod='emerchantpay'}</a>.
                </p>
            </div>
        </div>
    </div>

{/if}

{if $emerchantpay['redirect']['status'] == 'failure'}

    {capture name=path}{l s='Payment Status'}{/capture}

    <div id="center_column" class="center_column">
        <h1 class="page-heading bottom-indent">
            {l s='Your payment was unsuccessful' mod='emerchantpay'}
        </h1>

        <div class="row">
            <div class="col-xs-12">
                <p>
                    {l s='Please check your input and try again!' mod='emerchantpay'}

                    <br /><br />{l s='If the problem persists, you can contact our' mod='emerchantpay'}
                    <a href="{$emerchantpay['redirect']['url']['support']}">{l s='customer support' mod='emerchantpay'}</a>.
                </p>
            </div>
        </div>
    </div>

{/if}

{if $emerchantpay['redirect']['status'] == 'cancel'}

    {capture name=path}{l s='Payment Status'}{/capture}

    <div id="center_column" class="center_column">
        <h1 class="page-heading bottom-indent">
            {l s='Your payment was successfully cancelled' mod='emerchantpay'}
        </h1>

        <div class="row">
            <div class="col-xs-12">
                <p>
                    {l s='You have successfully cancelled your order.' mod='emerchantpay'}

                    <br/><br/>{l s='You can redo your ' mod='emerchantpay'}
                    <a href="{$emerchantpay['redirect']['url']['order']}">{l s='order' mod='emerchantpay'}</a>
                    {l s=' with different details or review your order' mod='emerchantpay'}
                    <a href="{$emerchantpay['redirect']['url']['history']}">{l s='history' mod='emerchantpay'}</a>.
                </p>
            </div>
        </div>
    </div>

{/if}