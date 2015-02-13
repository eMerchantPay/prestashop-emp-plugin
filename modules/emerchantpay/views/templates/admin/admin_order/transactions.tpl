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
{if $smarty.const._PS_VERSION_ > 1.5}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-heading">
                    <img src="{$base_url}modules/{$module_name}/logo.png" alt="" style="width:16px;" />
                    <span>{l s='eMerchantPay Transactions' mod='emerchantpay'}</span>
                </div>
                <div class="panel-collapse collapse in">
                    {if $warning}
                        <div class="alert alert-warning alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$warning|escape:html:'UTF-8'}
                        </div>
                    {/if}
                    {if $payment_error}
                        <div class="alert alert-danger alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$payment_error|escape:html:'UTF-8'}
                        </div>
                    {/if}
                    {if $transactions}
                    <table class="table table-hover tree">
                        <thead>
                            <tr>
                                <th>{l s="Id"       mod="emerchantpay"}</th>
                                <th>{l s="Type"     mod="emerchantpay"}</th>
                                <th>{l s="Date"     mod="emerchantpay"}</th>
                                <th>{l s="Amount"   mod="emerchantpay"}</th>
                                <th>{l s="Status"   mod="emerchantpay"}</th>
                                <th>{l s="Message"  mod="emerchantpay"}</th>
                                <th>{l s="Capture"  mod="emerchantpay"}</th>
                                <th>{l s="Refund"   mod="emerchantpay"}</th>
                                <th>{l s="Cancel"   mod="emerchantpay"}</th>
                            </tr>
                        </thead>
                        <tbody>
                        {foreach from=$transactions item=transaction}
                            <tr class="treegrid-{$transaction['id_unique']} {if $transaction['id_parent']}treegrid-parent-{$transaction['id_parent']}{/if}">
                                <td class="text-left">
                                    {$transaction['id_unique']}
                                </td>
                                <td class="text-left">
                                    {$transaction['type']}
                                </td>
                                <td class="text-left">
                                    {$transaction['date_add']}
                                </td>
                                <td class="text-left">
                                    {$transaction['amount']}
                                </td>
                                <td class="text-left">
                                    {$transaction['status']}
                                </td>
                                <td class="text-left">
                                    {$transaction['message']}
                                </td>
                                <td class="text-center">
                                    {if $transaction['can_capture']}
                                    <div class="transaction-action-button">
                                        <a class="btn btn-transaction btn-success button-capture button" role="button" data-type="capture" data-id-unique="{$transaction['id_unique']}">
                                            <i class="icon-check icon-large"></i>
                                        </a>
                                    </div>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    {if $transaction['can_refund']}
                                    <div class="transaction-action-button">
                                        <a class="btn btn-transaction btn-warning button-refund button" role="button" data-type="refund" data-id-unique="{$transaction['id_unique']}">
                                            <i class="icon-reply icon-large"></i>
                                        </a>
                                    </div>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    {if $transaction['can_void']}
                                    <div class="transaction-action-button">
                                        <a class="btn btn-transaction btn-danger button-void button" role="button" data-type="void" data-id-unique="{$transaction['id_unique']}">
                                            <i class="icon-remove icon-large"></i>
                                        </a>
                                    </div>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                    {/if}
                    <div class="disclaimer" style="text-align:right;margin-top:16px;">
                        {l s="Note: For more complex workflows/functionallity, please visit our Merchant Portal!" mod="emerchantpay"}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="{$module_name}-modal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">
                        <i class="icon-times"></i>
                    </button>
                    <img src="{$base_url}modules/{$module_name}/logo.png" style="width:16px;" />
                    <h3 class="{$module_name}-modal-title" style="margin:0;display:inline-block;"></h3>
                </div>
                <div class="modal-body">
                    <form id="{$module_name}-modal-form" class="form modal-form" action="" method="post">
                        <input type="hidden" name="{$module_name}_transaction_id" value="" />
                        <input type="hidden" name="{$module_name}_transaction_type" value="" />

                        <div class="form-group amount-input">
                            <label for="comment">Amount:</label>
                            <div class="input-group">
                                <div class="input-group-addon">{{$order_currency}}</div>
                                <input type="text" class="form-control" name="{$module_name}_transaction_amount" placeholder="{l s="Amount..." mod="emerchantpay"}" value="{{$order_amount}}" />
                            </div>
                        </div>

                        <div class="form-group usage-input">
                            <label for="comment">{l s='Message (optional):' mod='emerchantpay'}</label>
                            <textarea class="form-control" rows="3" name="{$module_name}_transaction_usage"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <span class="form-loading hidden">
                        <i class="icon-spinner icon-spin icon-large"></i>
                    </span>
                    <span class="form-buttons">
                        <button class="btn btn-default" data-dismiss="modal" aria-hidden="true">{l s="Cancel" mod="emerchantpay"}</button>
                        <button class="btn btn-submit btn-primary btn-capture" value="partial">{l s="Submit" mod="emerchantpay"}</button>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(function() {
            $('.tree').treegrid();
            $('.btn-transaction').click(function() {
                transactionModal($(this).attr('data-type'), $(this).attr('data-id-unique'));
            });
        });

        function transactionModal(type, id_unique) {
            modalObj = $('#{$module_name}-modal');

            switch(type) {
                case 'capture':
                    modalObj.find('h3.{$module_name}-modal-title').text('{l s="Capture transaction" mod="emerchantpay"}');
                    modalObj.find('input[name="{$module_name}_transaction_type"]').attr('value', 'capture');
                    modalObj.find('.amount-input').show();
                    break;
                case 'refund':
                    modalObj.find('h3.{$module_name}-modal-title').text('{l s="Refund transaction" mod="emerchantpay"}');
                    modalObj.find('input[name="{$module_name}_transaction_type"]').attr('value', 'refund');
                    modalObj.find('.amount-input').show();
                    break;
                case 'void':
                    modalObj.find('h3.{$module_name}-modal-title').text('{l s="Cancel transaction" mod="emerchantpay"}');
                    modalObj.find('input[name="{$module_name}_transaction_type"]').attr('value', 'void');
                    modalObj.find('.amount-input').hide();
                    break;
                default:
                    return;
            }

            modalObj.find('input[name="{$module_name}_transaction_id"]').attr('value', id_unique);

            modalObj.modal('show');

            $('.btn-submit').click(function() {
                $('#{$module_name}-modal-form').submit();
            });
        }
    </script>
{else}
    <script type="text/javascript">
        $(document).ready(function() {
            $('.tree').treegrid();
            $('.btn-transaction').click(function () {
                transactionBar($(this).attr('data-type'), $(this).attr('data-id-unique'), $(this).attr('data-amount'));
                $('html, body').animate({
                    scrollTop: $("#{$module_name}_action_bar").offset().top - 180
                }, 2000);
            });
        });

        function transactionBar(type, id_unique, amount) {
            modalObj = $('#{$module_name}_action_bar');

            if (modalObj.is(':visible')) {
                modalObj.delay(420).fadeOut(420);
            }

            switch(type) {
                case 'capture':
                    modalObj.find('#{$module_name}_transaction_type').attr('value', 'capture');
                    modalObj.find('#{$module_name}_transaction_amount').val(amount).show();
                    break;
                case 'refund':
                    modalObj.find('#{$module_name}_transaction_type').attr('value', 'refund');
                    modalObj.find('#{$module_name}_transaction_amount').val(amount).show();
                    break;
                case 'void':
                    modalObj.find('#{$module_name}_transaction_type').attr('value', 'void');
                    modalObj.find('#{$module_name}_transaction_amount').hide();
                    break;
                default:
                    return;
            }

            modalObj.find('#{$module_name}_transaction_id').attr('value', id_unique);

            modalObj.delay(420).fadeIn(420);
        }
    </script>
    <br/>
    <fieldset {if isset($ps_version) && ($ps_version < '1.5')}style="width: 400px"{/if}>
        <legend><img src="{$base_url}modules/{$module_name}/logo.png" style="width:16px" alt="" />{l s='eMerchantPay Transactions' mod='emerchantpay'}</legend>
        {if $warning}
            <div class="warn">{$warning|escape:html:'UTF-8'}</div>
        {else}
            {if $payment_error}
                <div class="error">{$payment_error}</div>
            {/if}
            <p><b>{l s='Information:' mod='emerchantpay'}</b> {l s="For more complex workflows/functionallity, please visit our Merchant Portal!" mod="emerchantpay"}</p>
            <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
                <table class="table tree" width="100%" cellspacing="0" cellpadding="0" id="shipping_table">
                    <colgroup>
                        <col width="18%"/>
                        <col width="5%"/>
                        <col width="5%"/>
                        <col width="5%"/>
                        <col width="5%"/>
                        <col width="5%"/>
                        <col width="2%"/>
                        <col width="2%"/>
                        <col width="2%"/>
                    </colgroup>
                    <thead>
                    <tr>
                        <th>{l s="Id"       mod="emerchantpay"}</th>
                        <th>{l s="Type"     mod="emerchantpay"}</th>
                        <th>{l s="Date"     mod="emerchantpay"}</th>
                        <th>{l s="Amount"   mod="emerchantpay"}</th>
                        <th>{l s="Status"   mod="emerchantpay"}</th>
                        <th>{l s="Message"  mod="emerchantpay"}</th>
                        <!--
                        <th>{l s="Capture"  mod="emerchantpay"}</th>
                        <th>{l s="Refund"   mod="emerchantpay"}</th>
                        <th>{l s="Cancel"   mod="emerchantpay"}</th>
                        -->
                        <th colspan="3" style="text-align: center;">{l s="Action" mod="emerchantpay"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$transactions item=transaction}
                        <tr class="treegrid-{$transaction['id_unique']} {if $transaction['id_parent']}treegrid-parent-{$transaction['id_parent']}{/if}">
                            <td class="text-left">
                                {$transaction['id_unique']}
                            </td>
                            <td class="text-left">
                                {$transaction['type']}
                            </td>
                            <td class="text-left">
                                {$transaction['date_add']}
                            </td>
                            <td class="text-left">
                                {$transaction['amount']}
                            </td>
                            <td class="text-left">
                                {$transaction['status']}
                            </td>
                            <td class="text-left">
                                {$transaction['message']}
                            </td>
                            <td class="text-center">
                                {if $transaction['can_capture']}
                                    <div class="transaction-action-button">
                                        <a class="btn btn-transaction btn-success button-capture button" role="button" data-type="capture" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['amount']}" >
                                            <i class="fa fa-check fa-large"></i>
                                        </a>
                                    </div>
                                {/if}
                            </td>
                            <td class="text-center">
                                {if $transaction['can_refund']}
                                    <div class="transaction-action-button">
                                        <a class="btn btn-transaction btn-warning button-refund button" role="button" data-type="refund" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['amount']}">
                                            <i class="fa fa-reply fa-large"></i>
                                        </a>
                                    </div>
                                {/if}
                            </td>
                            <td class="text-center">
                                {if $transaction['can_void']}
                                    <div class="transaction-action-button">
                                        <a class="btn btn-transaction btn-danger button-void button" role="button" data-type="void" data-id-unique="{$transaction['id_unique']}">
                                            <i class="fa fa-remove fa-large"></i>
                                        </a>
                                    </div>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                    <tr id="{$module_name}_action_bar" class="current-edit" style="display:none;">
                        <td colspan="1">
                            <label for="{$module_name}_transaction_amount" style="width:auto;">{l s="Amount:" mod="emerchantpay"}</label>
                            <input type="text" id="{$module_name}_transaction_amount" name="{$module_name}_transaction_amount" placeholder="{l s="Amount..." mod="emerchantpay"}" value="{{$order_amount}}" />
                        </td>
                        <td colspan="5">
                            <label for="{$module_name}_transaction_usage" style="width:auto;">{l s="Usage:" mod="emerchantpay"}</label>
                            <input type="text" id="{$module_name}_transaction_usage" name="{$module_name}_transaction_usage" placeholder="{l s="Usage..." mod="emerchantpay"}" />
                        </td>
                        <td colspan="3" style="text-align:center;">
                            <input type="hidden" id="{$module_name}_transaction_id" name="{$module_name}_transaction_id" value="" />
                            <input type="hidden" id="{$module_name}_transaction_type" name="{$module_name}_transaction_type" value="" />
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-arrow-right fa-large"></i>
                                {l s="Submit" mod="emerchantpay"}
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </form>
        {/if}
    </fieldset>
{/if}