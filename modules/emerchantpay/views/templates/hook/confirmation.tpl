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
<p>
    {l s='Your order on' mod='emerchantpay'} <span class="bold">{$shop_name|escape:'htmlall':'UTF-8'}</span> {l s='is complete.' mod='emerchantpay'}
    <br /><br /><span class="bold">{l s='Your order will be sent very soon.' mod='emerchantpay'}</span>
    <br /><br />{l s='For any questions or for further information, please contact our' mod='emerchantpay'} <a href="{$link->getPageLink('contact', true)}" target="_blank">{l s='customer support' mod='emerchantpay'}</a>.
</p>
