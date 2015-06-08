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

    <style>
        .text-left { text-align:left; }
        .text-center { text-align:center; }
        .text-right { text-align:right; }
    </style>

    <br/>

    <fieldset {if isset($ps_version) && ($ps_version < '1.5')}style="width: 400px"{/if}>
        <legend><img src="{$base_url}modules/{$module_name}/logo.png" style="width:16px" alt="" />{l s='eMerchantPay Transactions' mod='emerchantpay'}</legend>
        {* System errors, impacting the module functionallity *}
        {if $module_warn}
            <div class="warn">{$module_warn|escape:html:'UTF-8'}</div>
        {else}
            {* Transaction errors *}
            {if $error_transaction}
                <div class="error">{$error_transaction}</div>
            {/if}

            <div class="row">
                <div class="col-xs-3"></div>
                <div class="col-xs-6">
                    <div class="warn">
                        {l s="Full/Partial refunds through Prestashop's UI are local and WILL NOT REFUND the original transaction." mod="emerchantpay"}
                    </div>
                </div>
                <div class="col-xs-3"></div>
            </div>

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
                        <th colspan="3" style="text-align: center;">{l s="Action" mod="emerchantpay"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$transactions item=transaction}
                        <tr class="treegrid-{$transaction['id_unique']} {if $transaction['id_parent']}treegrid-parent-{$transaction['id_parent']}{/if}">
                            <td class="text-left">{$transaction['id_unique']}</td>
                            <td class="text-left">{$transaction['type']}</td>
                            <td class="text-center">{$transaction['date_add']}</td>
                            <td class="text-center">{$transaction['amount']}</td>
                            <td class="text-center">{$transaction['status']}</td>
                            <td class="text-center">{$transaction['message']}</td>
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
                        <td colspan="1" id="{$module_name}_transaction_amount_placeholder" style="vertical-align:middle">
                            <label for="{$module_name}_transaction_amount" style="width:20%;">{l s="Amount:" mod="emerchantpay"}</label>
                            <input type="text" id="{$module_name}_transaction_amount" name="{$module_name}_transaction_amount" placeholder="{l s="Amount..." mod="emerchantpay"}" value="{{$order_amount}}" style="width:70%;" />
                        </td>
                        <td colspan="5" id="{$module_name}_transaction_usage_placeholder" style="vertical-align:middle">
                            <label for="{$module_name}_transaction_usage" style="width:20%;">{l s="Usage:" mod="emerchantpay"}</label>
                            <input type="text" id="{$module_name}_transaction_usage" name="{$module_name}_transaction_usage" placeholder="{l s="Usage..." mod="emerchantpay"}" style="width:70%;" />
                        </td>
                        <td colspan="3" style="text-align:center;vertical-align:middle">
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
            <p class="text-right">
                <b>{l s='Information:' mod='emerchantpay'}</b> {l s="For more complex workflows/functionallity, please visit our Merchant Portal!" mod="emerchantpay"}
            </p>
        {/if}
    </fieldset>
    <script type="text/javascript">
        $(document).ready(function() {
            $('.tree').treegrid({
                expanderExpandedClass:  'fa fa-chevron-circle-down',
                expanderCollapsedClass: 'fa fa-chevron-circle-right'
            });
            $('.btn-transaction').click(function () {
                transactionBar($(this).attr('data-type'), $(this).attr('data-id-unique'), $(this).attr('data-amount'));
            });
        });

        function transactionBar(type, id_unique, amount) {
            modalObj = $('#{$module_name}_action_bar');

            modalObj.fadeOut(300, function() {
                switch(type) {
                    case 'capture':
                        modalObj.find('#{$module_name}_transaction_amount_placeholder').css('visibility', 'visible');
                        break;
                    case 'refund':
                        modalObj.find('#{$module_name}_transaction_amount_placeholder').css('visibility', 'visible');
                        break;
                    case 'void':
                        modalObj.find('#{$module_name}_transaction_amount_placeholder').css('visibility', 'hidden');
                        break;
                    default:
                        return;
                }

                modalObj.find('#{$module_name}_transaction_type').attr('value', type);

                modalObj.find('#{$module_name}_transaction_id').attr('value', id_unique);

                modalObj.find('#{$module_name}_transaction_amount').attr('value', amount);
            });

            modalObj.fadeIn(300, function() {

            });
        }
    </script>
{/if}

{if version_compare($ps_version, '1.6', '>=') && version_compare($ps_version, '1.7', '<') }

    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-heading">
                    <img src="{$base_url}modules/{$module_name}/logo.png" alt="" style="width:16px;" />
                    <span>{l s='eMerchantPay Transactions' mod='emerchantpay'}</span>
                </div>
                <div class="panel-collapse collapse in">

                    {* System errors, impacting the module functionallity *}
                    {if $module_warn}
                        <div class="alert alert-warning alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$module_warn|escape:html:'UTF-8'}
                        </div>
                    {/if}

                    {* Transaction errors *}
                    {if $error_transaction}
                        <div class="alert alert-danger alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$error_transaction|escape:html:'UTF-8'}
                        </div>
                    {/if}

                    <div class="row">
                        <div class="col-xs-3"></div>
                        <div class="col-xs-6">
                            <div class="alert alert-info">
                                {l s="You must process full/partial refunds only through this panel!" mod="emerchantpay"}
                                <br/>
                                {l s="Full/Partial refunds through Prestashop's UI are local and WILL NOT REFUND the original transaction." mod="emerchantpay"}
                            </div>
                        </div>
                        <div class="col-xs-3"></div>
                    </div>

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
                                                <a class="btn btn-transaction btn-success button-capture button" role="button" data-type="capture" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['amount']}">
                                                    <i class="icon-check icon-large"></i>
                                                </a>
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $transaction['can_refund']}
                                            <div class="transaction-action-button">
                                                <a class="btn btn-transaction btn-warning button-refund button" role="button" data-type="refund" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['amount']}">
                                                    <i class="icon-reply icon-large"></i>
                                                </a>
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $transaction['can_void']}
                                            <div class="transaction-action-button">
                                                <a class="btn btn-transaction btn-danger button-void button" role="button" data-type="void" data-id-unique="{$transaction['id_unique']}" data-amount="0">
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
                            <label for="comment">{l s="Amount:" mod="emerchantpay"}</label>
                            <div class="input-group">
                                <div class="input-group-addon">{{$order_currency}}</div>
                                <input type="text" class="form-control" name="{$module_name}_transaction_amount" placeholder="{l s="Amount..." mod="emerchantpay"}" value="{{$order_amount}}" />
                            </div>
                        </div>

                        <div class="form-group usage-input">
                            <label for="form-message">{l s='Message (optional):' mod='emerchantpay'}</label>
                            <textarea class="form-control form-message" rows="3" name="{$module_name}_transaction_usage"></textarea>
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
            $('.tree').treegrid({
                expanderExpandedClass:  'icon icon-chevron-sign-down',
                expanderCollapsedClass: 'icon icon-chevron-sign-right'
            });
            $('.btn-transaction').click(function() {
                transactionModal($(this).attr('data-type'), $(this).attr('data-id-unique'), $(this).attr('data-amount'));
            });
        });

        function transactionModal(type, id_unique, amount = 0) {
            modalObj = $('#{$module_name}-modal');

            switch(type) {
                case 'capture':
                    modalObj.find('h3.{$module_name}-modal-title').text('{l s="Capture transaction" mod="emerchantpay"}');
                    modalObj.find('div.amount-input').show();
                    break;
                case 'refund':
                    modalObj.find('h3.{$module_name}-modal-title').text('{l s="Refund transaction" mod="emerchantpay"}');
                    modalObj.find('div.amount-input').show();
                    break;
                case 'void':
                    modalObj.find('h3.{$module_name}-modal-title').text('{l s="Cancel transaction" mod="emerchantpay"}');
                    modalObj.find('div.amount-input').hide();
                    break;
                default:
                    return;
            }

            modalObj.find('input[name="{$module_name}_transaction_type"]').attr('value', type);

            modalObj.find('input[name="{$module_name}_transaction_id"]').attr('value', id_unique);

            modalObj.find('input[name="{$module_name}_transaction_amount"]').attr('value', amount);

            modalObj.modal('show');

            $('.btn-submit').click(function() {
                $('#{$module_name}-modal-form').submit();
            });
        }
    </script>

{/if}