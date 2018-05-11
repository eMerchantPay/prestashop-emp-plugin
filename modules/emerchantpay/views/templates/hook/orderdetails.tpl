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
{if $emerchantpay['transactions']['tree'] && version_compare($emerchantpay['presta']['version'], '1.7.2', '>=')}

    <section class="box">
        <h3>
            <img src="{$emerchantpay['presta']['url']}modules/{$emerchantpay['name']['module']}/logo.png" alt="" style="width:16px;" />
            <span>{l s='emerchantpay Transactions' mod='emerchantpay'}</span>
        </h3>

        <table class="table table-hover tree">
            <thead class="thead-default">
            <tr>
                <th>{l s="Id"       mod="emerchantpay"}</th>
                <th>{l s="Type"     mod="emerchantpay"}</th>
                <th>{l s="Date"     mod="emerchantpay"}</th>
                <th>{l s="Amount"   mod="emerchantpay"}</th>
                <th>{l s="Status"   mod="emerchantpay"}</th>
                <th class="slim-message">{l s="Message"  mod="emerchantpay"}</th>
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
                </tr>
            {/foreach}
            </tbody>
        </table>
    </section>

    <script type="text/javascript">
        $(document).ready(function() {
            $('.tree').treegrid({
                expanderExpandedClass:  'fa fa-chevron-circle-down',
                expanderCollapsedClass: 'fa fa-chevron-circle-right'
            });
        });
    </script>

    <style type="text/css">
        .bootstrap .tooltip-inner {
            padding: 5px 20px;
        }
        .slim-message {
            width:15%;
        }
    </style>

{/if}