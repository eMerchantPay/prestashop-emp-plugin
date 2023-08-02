{*
 * Copyright (C) 2018-2023 emerchantpay Ltd.
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
 * @copyright   2018-2023 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 *}

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkoutButton  = document.querySelector('#payment-confirmation button');
        const mainArea        = document.querySelector('#content');
        const paymentMethod   = 'emerchantpay_checkout';

        checkoutButton.addEventListener('click', function(event) {
            const selectedPaymentOption = document.querySelector('input[name="payment-option"]:checked');

            if (selectedPaymentOption && selectedPaymentOption.getAttribute('data-module-name') !== paymentMethod) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const mainBody   = document.querySelector('body');
            const form       = document.querySelector('.payment-option-form-{$method_name|escape:'htmlall':'UTF-8'}');
            const div        = document.createElement('div');
            const header     = document.createElement('div');
            const iframe     = document.createElement('iframe');
            div.className    = 'emp-threeds-modal';
            header.className = 'emp-threeds-iframe-header';
            iframe.className = 'emp-threeds-iframe';
            header.innerHTML = '<div class="screen-logo"><img src="{$emerchantpay['path']|escape:'htmlall':'UTF-8'}/views/img/logos/emerchantpay_logo.png" alt="Emerchantpay logo"></div>'
                + '<h3>The payment is being processed<br><span>Please, wait</span></h3>';
            div.appendChild(header);
            div.appendChild(iframe);

            document.body.appendChild(div);
            mainArea.style.opacity  = 0.6;
            mainBody.style.overflow = 'hidden';
            div.style.display       = 'block';

            doBeforeSubmitEMerchantPayCheckoutPaymentForm(form);
            const postUrl   = decodeURIComponent(form.action);
            const formData  = new FormData(form);
            const xhr       = new XMLHttpRequest();

            xhr.open('POST', postUrl, true);

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 400) {
                    try {
                        let response  = JSON.parse(xhr.responseText);
                        iframe.onload = function () { document.querySelector('.emp-threeds-iframe-header').style.display = 'none' }
                        iframe.src    = response.redirect;
                    } catch (e) {
                        console.log('Could not parse the server response');
                        parent.location.reload();
                    }
                } else {
                    console.log('Server returned an error');
                    parent.location.reload();
                }
            }

            xhr.onerror = function () {
                console.log('Connection error');
                parent.location.reload();
            }

            xhr.send(formData);
        })
    });
</script>
<style>
    iframe.emp-threeds-iframe {
        border-radius: 10px;
        box-shadow: none;
        outline: none;
        border: none;
        background-color: transparent;
        width: 100%;
        min-height: 10%;
        max-height: 90%;
        height: 90%;
        overflow: visible;
        max-width: 450px;
    }
    div.emp-threeds-modal {
        display: none;
        position: fixed;
        z-index: 99999;
        padding-top: 100px;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        text-align: center;
        background-color: rgba(0, 0, 0, 0.5);
    }
    div.emp-threeds-iframe-header {
        padding: 10px;
        font-family: 'Segoe UI', Arial, sans-serif;
        text-align: center;
        max-width: 450px;
        background-color: #fff;
        border-radius: 10px;
        color: #000000;
        margin: 0 auto;
        height: 423px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        row-gap: 40px;
    }
    .emp-threeds-iframe-header .screen-logo
    {
        width: 152px;
        height: 50px;
        margin: 5px auto;
    }
    .emp-threeds-iframe-header h3 {
        font-weight: 500;
        font-size: 40px;
        margin-bottom: initial;
    }
    .emp-threeds-iframe-header h3 span {
        display: block;
        text-align: center;
        font-weight: 400;
        font-size: 16px;
        padding: 30px 0;
    }
</style>
