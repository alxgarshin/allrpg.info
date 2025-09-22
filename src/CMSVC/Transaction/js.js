/** История взносов */

if (el('div.kind_transaction')) {
    _('a#verify_transaction').on('click', function () {
        const self = _(this);
        const obj_id = self.closest('.tr').attr('obj_id');

        actionRequest({
            action: 'transaction/verify_transaction',
            obj_id: obj_id
        }, self.closest('.td'));
    });

    _('#form_transaction_add [name^="amount"], #form_transaction_add [name^="comission_percent"], #form_transaction_add [name^="comission_value"]').on('change keyup', function (e) {
        const lineId = _(e.target).closest('.tr').attr('id');
        const amountField = `.tr#${lineId} [name^="amount"]`;
        const comissionPercentField = `.tr#${lineId} [name^="comission_percent"]`;
        const comissionValueField = `.tr#${lineId} [name^="comission_value"]`;

        let amountFieldVal = parseInt(_(amountField).val());
        let comissionPercentFieldVal = parseFloat(_(comissionPercentField).val());
        let comissionValueFieldVal = parseFloat(_(comissionValueField).val());

        if (isNaN(amountFieldVal)) {
            _(amountField).val('0');
            amountFieldVal = 0;
        }
        if (isNaN(comissionPercentFieldVal)) {
            _(comissionPercentField).val('0');
            comissionPercentFieldVal = 0;
        }
        if (isNaN(comissionValueFieldVal)) {
            _(comissionValueField).val('0');
            comissionValueFieldVal = 0;
        }

        if (_(e.target).is(amountField)) {
            if (comissionPercentFieldVal > 0) {
                _(comissionValueField).val(amountFieldVal / 100 * comissionPercentFieldVal);
            } else if (comissionValueFieldVal > 0) {
                _(comissionPercentField).val(comissionValueFieldVal / (amountFieldVal / 100));
            }
        } else if (_(e.target).is(comissionPercentField)) {
            if (amountFieldVal > 0) {
                _(comissionValueField).val(amountFieldVal / 100 * comissionPercentFieldVal);
            } else if (comissionValueFieldVal > 0) {
                _(amountField).val(Math.round(comissionValueFieldVal / comissionPercentFieldVal * 100));
            }
        } else if (_(e.target).is(comissionValueField)) {
            if (amountFieldVal > 0) {
                _(comissionPercentField).val(comissionValueFieldVal / (amountFieldVal / 100));
            } else if (comissionPercentFieldVal > 0) {
                _(amountField).val(Math.round(comissionValueFieldVal / comissionPercentFieldVal * 100));
            }
        }
    });

    _('#form_transaction_add .without_payment_datetime .tr:not([id="line0"]):not(.menu) .td:nth-of-type(11), #form_transaction_add .without_payment_datetime .tr:not([id="line0"]):not(.menu) .td:nth-of-type(12), #form_transaction_add .with_payment_datetime .tr:not([id="line0"]):not(.menu) .td:nth-of-type(12), #form_transaction_add .with_payment_datetime .tr:not([id="line0"]):not(.menu) .td:nth-of-type(13)').on('click', function () {
        const self = _(this);
        const withPaymentDatetime = self.closest('.with_payment_datetime');
        const tr = self.closest('.tr');
        const type = (self.is('.td:nth-of-type(13)') && withPaymentDatetime) || (self.is('.td:nth-of-type(12)') && !withPaymentDatetime) ? 'comission_value' : 'comission_percent';
        const amountField = tr.find('.td:nth-of-type(5)');
        const comissionPercentField = tr.find(`.td:nth-of-type(${withPaymentDatetime ? 12 : 11})`);
        const comissionValueField = tr.find(`.td:nth-of-type(${withPaymentDatetime ? 13 : 12})`);

        createPseudoPrompt(
            `<div><input name="change_comission" id="change_comission" value="${self.text()}" autocomplete="off"></div>`,
            LOCALE.transaction.newComissionHeader,
            [
                {
                    text: LOCALE.approveCapitalized,
                    class: 'main',
                    click: function () {
                        const self = getNotyDialogDOM();
                        let newValue = parseFloat(self.find('#change_comission').val());
                        if (isNaN(newValue)) {
                            newValue = 0;
                        }

                        if (type === 'comission_percent') {
                            comissionPercentField.text(newValue);
                            comissionValueField.text(parseInt(amountField.text()) / 100 * newValue);
                        } else {
                            comissionValueField.text(newValue);
                            comissionPercentField.text(newValue / (parseInt(amountField.text()) / 100));
                        }

                        actionRequest({
                            action: 'transaction/change_comission',
                            obj_id: tr.attr('obj_id'),
                            obj_type: type,
                            value: newValue
                        });

                        notyDialog?.close();
                    }
                }
            ]);
    });
}

if (withDocumentEvents) {
    getHelperData('form#form_transaction_add .sbi-info', 'show_user_info_from_rolelist');

    _arSuccess('verify_transaction', function (jsonData, params, target) {
        target.html(jsonData['response_data']);
    })

    _arSuccess('change_comission', function () { })

    _arSuccess('show_user_info_from_rolelist', function (jsonData, params, target) {
        getHelpersSuccess(jsonData, params, target);
    })
}