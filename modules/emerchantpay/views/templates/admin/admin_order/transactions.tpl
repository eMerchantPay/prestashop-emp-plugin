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

{if version_compare($emerchantpay['presta']['version'], '1.5', '>=') && version_compare($emerchantpay['presta']['version'], '1.6', '<') }

    <style type="text/css">
        .text-left { text-align:left; }
        .text-center { text-align:center; }
        .text-right { text-align:right; }
    </style>

    <br/>

    <fieldset {if isset($emerchantpay['presta']['version']) && ($emerchantpay['presta']['version'] < '1.5')}style="width: 400px"{/if}>
        <legend><img src="{$emerchantpay['presta']['url']}/modules/{$emerchantpay['name']['module']}/logo.png" style="width:16px" alt="" />{l s='eMerchantPay Transactions' mod='emerchantpay'}</legend>
        {* System errors, impacting the module functionallity *}
        {if $emerchantpay['warning']}
            <div class="warn">{$emerchantpay['warning']|escape:html:'UTF-8'}</div>
        {else}
            {* Transaction errors *}
            {if $emerchantpay['transactions']['error']}
                <div class="error">{$emerchantpay['transactions']['error']}</div>
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
                    {foreach from=$emerchantpay['transactions']['tree'] item=transaction}
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
                    <tr id="{$emerchantpay['name']['module']}_action_bar" class="current-edit" style="display:none;">
                        <td colspan="1" id="{$emerchantpay['name']['module']}_transaction_amount_placeholder" style="vertical-align:middle">
                            <label for="{$emerchantpay['name']['module']}_transaction_amount" style="width:20%;">{l s="Amount:" mod="emerchantpay"}</label>
                            <input type="text" id="{$emerchantpay['name']['module']}_transaction_amount" name="{$emerchantpay['name']['module']}_transaction_amount" placeholder="{l s="Amount..." mod="emerchantpay"}" value="{{$emerchantpay['transactions']['order']['amount']}}" style="width:70%;" />
                        </td>
                        <td colspan="5" id="{$emerchantpay['name']['module']}_transaction_usage_placeholder" style="vertical-align:middle">
                            <label for="{$emerchantpay['name']['module']}_transaction_usage" style="width:20%;">{l s="Usage:" mod="emerchantpay"}</label>
                            <input type="text" id="{$emerchantpay['name']['module']}_transaction_usage" name="{$emerchantpay['name']['module']}_transaction_usage" placeholder="{l s="Usage..." mod="emerchantpay"}" style="width:70%;" />
                        </td>
                        <td colspan="3" style="text-align:center;vertical-align:middle">
                            <input type="hidden" id="{$emerchantpay['name']['module']}_transaction_id" name="{$emerchantpay['name']['module']}_transaction_id" value="" />
                            <input type="hidden" id="{$emerchantpay['name']['module']}_transaction_type" name="{$emerchantpay['name']['module']}_transaction_type" value="" />
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
            modalObj = $('#{$emerchantpay['name']['module']}_action_bar');

            modalObj.fadeOut(300, function() {
                switch(type) {
                    case 'capture':
                        modalObj.find('#{$emerchantpay['name']['module']}_transaction_amount_placeholder').css('visibility', 'visible');
                        break;
                    case 'refund':
                        modalObj.find('#{$emerchantpay['name']['module']}_transaction_amount_placeholder').css('visibility', 'visible');
                        break;
                    case 'void':
                        modalObj.find('#{$emerchantpay['name']['module']}_transaction_amount_placeholder').css('visibility', 'hidden');
                        break;
                    default:
                        return;
                }

                modalObj.find('#{$emerchantpay['name']['module']}_transaction_type').attr('value', type);

                modalObj.find('#{$emerchantpay['name']['module']}_transaction_id').attr('value', id_unique);

                modalObj.find('#{$emerchantpay['name']['module']}_transaction_amount').attr('value', amount);
            });

            modalObj.fadeIn(300, function() {

            });
        }
    </script>
{/if}

{if version_compare($emerchantpay['presta']['version'], '1.6', '>=')}

    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-heading">
                    <img src="{$emerchantpay['presta']['url']}modules/{$emerchantpay['name']['module']}/logo.png" alt="" style="width:16px;" />
                    <span>{l s='eMerchantPay Transactions' mod='emerchantpay'}</span>
                </div>
                <div class="panel-collapse collapse in">

                    {* System errors, impacting the module functionallity *}
                    {if $emerchantpay['warning']}
                        <div class="alert alert-warning alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$emerchantpay['warning']|escape:html:'UTF-8'}
                        </div>
                    {/if}

                    {* Transaction errors *}
                    {if $emerchantpay['transactions']['error']}
                        <div class="alert alert-danger alert-dismissable error-wrapper">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$emerchantpay['transactions']['error']|escape:html:'UTF-8'}
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
                    
                    {if $emerchantpay['transactions']['tree']}
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
                            {foreach from=$emerchantpay['transactions']['tree'] item=transaction}
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
                                    <td class="text-right">
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
                                                <a class="btn btn-transaction btn-success button-capture button" role="button" data-type="capture" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['available_amount']}">
                                                    <i class="icon-check icon-large"></i>
                                                </a>
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $transaction['can_refund']}
                                            <div class="transaction-action-button">
                                                <a class="btn btn-transaction btn-warning button-refund button" role="button" data-type="refund" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['available_amount']}">
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
    <div id="{$emerchantpay['name']['module']}-modal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">
                        <i class="icon-times"></i>
                    </button>
                    <img src="{$emerchantpay['presta']['url']}modules/{$emerchantpay['name']['module']}/logo.png" style="width:16px;" />
                    <h3 class="{$emerchantpay['name']['module']}-modal-title" style="margin:0;display:inline-block;"></h3>
                </div>
                <div class="modal-body">
                    <form id="{$emerchantpay['name']['module']}-modal-form" class="form modal-form" action="" method="post">
                        <input type="hidden" name="{$emerchantpay['name']['module']}_transaction_id" value="" />
                        <input type="hidden" name="{$emerchantpay['name']['module']}_transaction_type" value="" />

                        <div id="{$emerchantpay['name']['module']}_capture_trans_info" class="row" style="display: none;">
                            <div class="col-xs-12">
                                <div class="alert alert-info">
                                    {l s="You are allowed to process only full capture through this panel!" mod="emerchantpay"}
                                    <br/>
                                    {l s="For further Information please contact your Account Manager." mod="emerchantpay"}
                                </div>
                            </div>
                        </div>

                        <div id="{$emerchantpay['name']['module']}_cancel_trans_warning" class="row" style="display: none;">
                            <div class="col-xs-12">
                                <div class="alert alert-warning">
                                    {l s="This service is only available for particular acquirers!" mod="emerchantpay"}
                                    <br/>
                                    {l s="For further Information please contact your Account Manager." mod="emerchantpay"}
                                </div>
                            </div>
                        </div>

                        <div class="form-group amount-input">
                            <label for="{$emerchantpay['name']['module']}_transaction_amount">{l s="Amount:" mod="emerchantpay"}</label>
                            <div class="input-group">
                                <span class="input-group-addon" data-toggle="{$emerchantpay['name']['module']}-tooltip" data-placement="top" title="{{$emerchantpay['transactions']['order']['currency']['iso_code']}}">{{$emerchantpay['transactions']['order']['currency']['sign']}}</span>
                                <input type="text" class="form-control" id="{$emerchantpay['name']['module']}_transaction_amount" name="{$emerchantpay['name']['module']}_transaction_amount" placeholder="{l s="Amount..." mod="emerchantpay"}" value="{{$emerchantpay['transactions']['order']['amount']}}" />
                            </div>
                            <span class="help-block" id="{$emerchantpay['name']['module']}-amount-error-container"></span>                           
                        </div>

                        <div class="form-group usage-input">
                            <label for="{$emerchantpay['name']['module']}_transaction_usage">{l s='Message (optional):' mod='emerchantpay'}</label>
                            <textarea class="form-control form-message" rows="3" id="{$emerchantpay['name']['module']}_transaction_usage" name="{$emerchantpay['name']['module']}_transaction_usage" placeholder="{l s='Message' mod='emerchantpay'}"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">                    	
                    <span class="form-loading hidden">
                        <i class="icon-spinner icon-spin icon-large"></i>
                    </span>
                    <span class="form-buttons">
                        <button class="btn btn-default" data-dismiss="modal" aria-hidden="true">{l s="Cancel" mod="emerchantpay"}</button>
                        <button id="{$emerchantpay['name']['module']}-modal-submit" class="btn btn-submit btn-primary btn-capture" value="partial">{l s="Submit" mod="emerchantpay"}</button>
                    </span>
                </div>
            </div>
        </div>
    </div>
		
    <script type="text/javascript">
        var modalPopupDecimalValueFormatConsts = {
            decimalPlaces       : {$emerchantpay['transactions']['order']['currency']['decimalPlaces']},
            decimalSeparator    : "{$emerchantpay['transactions']['order']['currency']['decimalSeparator']}",
            thousandSeparator   : "{$emerchantpay['transactions']['order']['currency']['thousandSeparator']}"
        };
        
        (function($) {
            jQuery.exists = function(selector) {
                    return ($(selector).length > 0);
            }

            $.fn.bootstrapValidator.i18n.transactionAmount = $.extend($.fn.bootstrapValidator.i18n.transactionAmount || {}, {
                'default': 'Please enter a valid transaction amount. (Ex. %s)'
            });

            $.fn.bootstrapValidator.validators.transactionAmount = {
                html5Attributes: {
                    message: 'message',
                    exampleValue: 'exampleValue'
                },

                validate: function(validator, $field, options) {
                    var fieldValue 	    = $field.val(),
                        regexp          = /^(([0-9]*)|(([0-9]*)\.([0-9]*)))$/i,
                        isValid    	    = true,
                        errorMessage    = options.message || $.fn.bootstrapValidator.i18n.transactionAmount['default'],
                        exampleValue    = options.exampleValue || "123.45";

                    errorMessage = $.fn.bootstrapValidator.helpers.format(errorMessage, [exampleValue]);

                    return {
                            valid: regexp.test(fieldValue),
                            message: errorMessage
                    };
                }
            }
        }(window.jQuery));

        function formatTransactionAmount(amount) {
            if ((typeof amount == 'undefined') || (amount == null))
                    amount = 0;

            return $.number(amount, modalPopupDecimalValueFormatConsts.decimalPlaces,
                                                            modalPopupDecimalValueFormatConsts.decimalSeparator,
                                                            modalPopupDecimalValueFormatConsts.thousandSeparator);
        }

        function destroyBootstrapValidator(submitFormId) {
            $(submitFormId).bootstrapValidator('destroy');
        }

        function createBootstrapValidator(submitFormId) {
        var submitForm = $(submitFormId),
            transactionAmount = formatTransactionAmount($('#{$emerchantpay['name']['module']}_transaction_amount').val());

            destroyBootstrapValidator(submitFormId);
						
            var transactionAmountControlSelector = '#{$emerchantpay['name']['module']}_transaction_amount';
            
            var shouldCreateValidator = $.exists(transactionAmountControlSelector);
            
            /* it is not needed to create attach the bootstapValidator, when the field to validate is not visible (Void Transaction) */
            if (!shouldCreateValidator) 
                return false;
								
            submitForm.bootstrapValidator({
            fields: {
                fieldAmount: {
                        selector: transactionAmountControlSelector,
                        container: '#{$emerchantpay['name']['module']}-amount-error-container',
                        trigger: 'keyup',
                        validators: {
                            notEmpty: {
                                message: 'The transaction amount is a required field!'
                            },
                            stringLength: {
                                    max: 10
                            },
                            greaterThan: {
                                    value: 0,
                                    inclusive: false
                            },
                            lessThan: {
                                    value: transactionAmount,
                                    inclusive: true
                            },
                            transactionAmount: {
                                    exampleValue: transactionAmount,
                            }
                        }
                    }
                }
            })
            .on('error.field.bv', function(e, data) {
                    $('#{$emerchantpay['name']['module']}-modal-submit').attr('disabled', 'disabled');
            })
            .on('success.field.bv', function(e) {
                    $('#{$emerchantpay['name']['module']}-modal-submit').removeAttr('disabled');
            })
            .on('success.form.bv', function(e) {
                    e.preventDefault(); // Prevent the form from submitting

                /* submits the transaction form (No validators have failed) */
                submitForm.bootstrapValidator('defaultSubmit');
            });
            
            return true;
        }

        function executeBootstrapFieldValidator(formId, validatorFieldName) {
            var submitForm = $(formId);

            submitForm.bootstrapValidator('validateField', validatorFieldName);
            submitForm.bootstrapValidator('updateStatus', validatorFieldName, 'NOT_VALIDATED');
        }

        $(document).ready(function() {

            $('[data-toggle="{$emerchantpay['name']['module']}-tooltip"]').tooltip();

            $('.tree').treegrid({
                expanderExpandedClass:  'icon icon-chevron-sign-down',
                expanderCollapsedClass: 'icon icon-chevron-sign-right'
            });

            $('.btn-transaction').click(function() {
                transactionModal($(this).attr('data-type'), $(this).attr('data-id-unique'), $(this).attr('data-amount'));
            });

            var modalObj = $('#{$emerchantpay['name']['module']}-modal'),
                transactionAmountInput = $('#{$emerchantpay['name']['module']}_transaction_amount', modalObj);

                $('.btn-submit').click(function() {
                $('#{$emerchantpay['name']['module']}-modal-form').submit();
            });

            modalObj.on('hide.bs.modal', function() {
                destroyBootstrapValidator('#{$emerchantpay['name']['module']}-modal-form');
            });

            modalObj.on('shown.bs.modal', function() {
                /* enable the submit button just in case (if the bootstrapValidator is enabled it will disable the button if necessary */
                $('#{$emerchantpay['name']['module']}-modal-submit').removeAttr('disabled');
                
                if (createBootstrapValidator('#{$emerchantpay['name']['module']}-modal-form')) {
                    executeBootstrapFieldValidator('#{$emerchantpay['name']['module']}-modal-form', 'fieldAmount');
                }
            });

            transactionAmountInput.number(true, modalPopupDecimalValueFormatConsts.decimalPlaces,
                                                modalPopupDecimalValueFormatConsts.decimalSeparator,
                                                modalPopupDecimalValueFormatConsts.thousandSeparator);
        });

        function transactionModal(type, id_unique, amount) {
            if ((typeof amount == 'undefined') || (amount == null))
                amount = 0;

            modalObj = $('#{$emerchantpay['name']['module']}-modal');

                        var modalTitle = modalObj.find('h3.{$emerchantpay['name']['module']}-modal-title'),
                            modalAmountInputContainer = modalObj.find('div.amount-input'),
                            captureTransactionInfoHolder = $('#{$emerchantpay['name']['module']}_capture_trans_info', modalObj),
                            cancelTransactionWarningHolder = $('#{$emerchantpay['name']['module']}_cancel_trans_warning', modalObj),
                            transactionAmountInput = $('#{$emerchantpay['name']['module']}_transaction_amount', modalObj);

            switch(type) {
                case 'capture':
                      modalTitle.text('{l s="Capture transaction" mod="emerchantpay"}');
                      updateTransModalControlState([captureTransactionInfoHolder, modalAmountInputContainer], true);
                      updateTransModalControlState([cancelTransactionWarningHolder], false);
                      transactionAmountInput.attr('readonly', 'readonly');
                      break;

                case 'refund':
                      modalTitle.text('{l s="Refund transaction" mod="emerchantpay"}');
                      updateTransModalControlState([captureTransactionInfoHolder, cancelTransactionWarningHolder], false);
                      updateTransModalControlState([modalAmountInputContainer], true);
                      transactionAmountInput.removeAttr('readonly');
                      break;

                case 'void':
                      modalTitle.text('{l s="Cancel transaction" mod="emerchantpay"}');
                      updateTransModalControlState([captureTransactionInfoHolder, modalAmountInputContainer], false);
                      updateTransModalControlState([cancelTransactionWarningHolder], true);
                      break;
                default:
                    return;
            }

            modalObj.find('input[name="{$emerchantpay['name']['module']}_transaction_type"]').val(type);

            modalObj.find('input[name="{$emerchantpay['name']['module']}_transaction_id"]').val(id_unique);

            transactionAmountInput.val(amount);

            modalObj.modal('show');

        }

        function updateTransModalControlState(controls, visibilityStatus) {
            $.each(controls, function(index, control){
                if (!$.exists(control))
                        return; /* continue to the next item */

                if (visibilityStatus)
                    control.fadeIn('fast');
                else
                    control.fadeOut('fast');
            });
        }

    </script>

    <style type="text/css">
        .bootstrap .tooltip-inner {
            padding: 5px 20px;
        }
    </style>

{/if}