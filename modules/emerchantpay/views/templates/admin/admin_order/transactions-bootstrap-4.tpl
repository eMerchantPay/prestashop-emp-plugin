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

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <img src="{$emerchantpay['presta']['url']}modules/{$emerchantpay['name']['module']}/logo.png" alt="" style="width:16px;" />
                    <span>{l s='emerchantpay Transactions' mod='emerchantpay'}</span>
                </div>
                <div class="card-body">

                    {* System errors, impacting the module functionallity *}
                    {if $emerchantpay['warning']}
                        <div class="alert alert-warning alert-dismissable error-wrapper mx-auto">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {$emerchantpay['warning']|escape:html:'UTF-8'}
                        </div>
                    {/if}

                    {* Transaction errors *}
                    {if $emerchantpay['transactions']['error']}
                        <div class="alert alert-danger alert-dismissable error-wrapper mx-auto">
                            {$emerchantpay['transactions']['error']|escape:html:'UTF-8'}
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    {/if}

                    <div class="row">
                        <div class="col-sm-3"></div>
                        <div class="col-sm-6">
                            <div class="alert alert-info">
                                {l s="You must process full/partial refunds only through this panel!" mod="emerchantpay"}
                                <br/>
                                {l s="Full/Partial refunds through Prestashop's UI are local and WILL NOT REFUND the original transaction." mod="emerchantpay"}
                            </div>
                        </div>
                        <div class="col-sm-3"></div>
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
                                                <a class="btn btn-transaction btn-success button-capture button" role="button" data-type="capture" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['available_amount']}"
                                                    {if !$emerchantpay['transactions']['options']['allow_partial_capture']}
                                                        data-toggle="tooltip" data-placement="bottom"
                                                        title="{$emerchantpay['transactions']['text']['denied_partial_capture']|escape:html:'UTF-8'}"
                                                    {/if}
                                                    >
                                                    <i class="fa fa-check fa-large"></i>
                                                </a>
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $transaction['can_refund']}
                                            <div class="transaction-action-button">
                                                <a class="btn btn-transaction btn-warning button-refund button" role="button" data-type="refund" data-id-unique="{$transaction['id_unique']}" data-amount="{$transaction['available_amount']}"
                                                    {if !$emerchantpay['transactions']['options']['allow_partial_refund']}
                                                        data-toggle="tooltip" data-placement="bottom"
                                                        title="{$emerchantpay['transactions']['text']['denied_partial_refund']|escape:html:'UTF-8'}"
                                                    {/if}
                                                    >
                                                    <i class="fa fa-reply fa-large"></i>
                                                </a>
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $transaction['can_void']}
                                            <div class="transaction-action-button">
                                                <a class="btn btn-transaction btn-danger button-void button" role="button" data-type="void" data-id-unique="{$transaction['id_unique']}" data-amount="0"
                                                    {if !$emerchantpay['transactions']['options']['allow_void']}
                                                        data-disabled="disabled" style="cursor: default" data-toggle="tooltip" data-placement="bottom"
                                                        title="{$emerchantpay['transactions']['text']['denied_void']|escape:html:'UTF-8'}"
                                                    {/if}
                                                    >
                                                    <i class="fa fa-remove fa-large"></i>
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
                    <img src="{$emerchantpay['presta']['url']}modules/{$emerchantpay['name']['module']}/logo.png" style="width:16px;" />
                    <h3 class="{$emerchantpay['name']['module']}-modal-title" style="margin:0;display:inline-block;"></h3>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="{$emerchantpay['name']['module']}-modal-form" class="form modal-form" action="" method="post">
                        <input type="hidden" name="{$emerchantpay['name']['module']}_transaction_id" value="" />
                        <input type="hidden" name="{$emerchantpay['name']['module']}_transaction_type" value="" />

                        <div id="{$emerchantpay['name']['module']}_capture_trans_info" class="row" style="display: none;">
                            <div class="col-sm-12">
                                <div class="alert alert-warning mx-auto">
                                    {l s="You are allowed to process only full capture through this panel!" mod="emerchantpay"}
                                    <br/>
                                    {l s="For further Information please contact your Account Manager." mod="emerchantpay"}
                                </div>
                            </div>
                        </div>

                        <div id="{$emerchantpay['name']['module']}_refund_trans_info" class="row" style="display: none;">
                            <div class="col-sm-12">
                                <div class="alert alert-warning mx-auto">
                                    You are allowed to process only full refund through this panel!
                                    <br/>
                                    This option can be enabled in the <strong>Module Settings</strong>, but it depends on the <strong>acquirer</strong>
                                    For further Information please contact your <strong>Account Manager</strong>
                                </div>
                            </div>
                        </div>

                        <div id="{$emerchantpay['name']['module']}_cancel_trans_warning" class="row" style="display: none;">
                            <div class="col-sm-12">
                                <div class="alert alert-warning mx-auto">
                                    {l s="This service is only available for particular acquirers!" mod="emerchantpay"}
                                    <br/>
                                    {l s="For further Information please contact your Account Manager." mod="emerchantpay"}
                                </div>
                            </div>
                        </div>

                        <div class="form-group amount-input">
                            <label for="{$emerchantpay['name']['module']}_transaction_amount">{l s="Amount:" mod="emerchantpay"}</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" data-toggle="{$emerchantpay['name']['module']}-tooltip" data-placement="top" title="{{$emerchantpay['transactions']['order']['currency']['iso_code']}}">{{$emerchantpay['transactions']['order']['currency']['sign']}}</span>
                                </div>
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

            $('[data-toggle="tooltip"]').tooltip();

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
                expanderExpandedClass:  'fa fa-chevron-circle-down',
                expanderCollapsedClass: 'fa fa-chevron-circle-right'
            });

            $('.btn-transaction').click(function() {
                if ($(this).is("[data-disabled]")) {
                    return;
                }

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
                refundTransactionInfoHolder = $('#{$emerchantpay['name']['module']}_refund_trans_info', modalObj),
                cancelTransactionWarningHolder = $('#{$emerchantpay['name']['module']}_cancel_trans_warning', modalObj),
                transactionAmountInput = $('#{$emerchantpay['name']['module']}_transaction_amount', modalObj);

            switch(type) {
                case 'capture':
                      modalTitle.text('{l s="Capture transaction" mod="emerchantpay"}');
                      {if !$emerchantpay['transactions']['options']['allow_partial_capture']}
                          updateTransModalControlState([captureTransactionInfoHolder], true);
                      {else}
                          updateTransModalControlState([captureTransactionInfoHolder], false);
                      {/if}
                      updateTransModalControlState([modalAmountInputContainer], true);
                      updateTransModalControlState([refundTransactionInfoHolder, cancelTransactionWarningHolder], false);

                      {if !$emerchantpay['transactions']['options']['allow_partial_capture']}
                          transactionAmountInput.attr('readonly', 'readonly');
                      {else}
                          transactionAmountInput.removeAttr('readonly');
                      {/if}
                      break;

                case 'refund':
                      modalTitle.text('{l s="Refund transaction" mod="emerchantpay"}');
                      updateTransModalControlState([captureTransactionInfoHolder, cancelTransactionWarningHolder], false);
                      {if !$emerchantpay['transactions']['options']['allow_partial_refund']}
                          updateTransModalControlState([refundTransactionInfoHolder], true);
                      {else}
                          updateTransModalControlState([refundTransactionInfoHolder], false);
                      {/if}
                      updateTransModalControlState([modalAmountInputContainer], true);
                      {if !$emerchantpay['transactions']['options']['allow_partial_refund']}
                          transactionAmountInput.attr('readonly', 'readonly');
                      {else}
                          transactionAmountInput.removeAttr('readonly');
                      {/if}
                      break;

                case 'void':
                      modalTitle.text('{l s="Cancel transaction" mod="emerchantpay"}');
                      updateTransModalControlState([captureTransactionInfoHolder, refundTransactionInfoHolder, modalAmountInputContainer], false);
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

    <style>
        .bootstrap .tooltip-inner {
            padding: 5px 20px;
        }
        #emerchantpay-modal .modal-header img {
            margin-right: 10px;
        }
    </style>
