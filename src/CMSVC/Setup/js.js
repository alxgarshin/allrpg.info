/** Форма заявки */

/** Поле "Значения" */
if (el('form#form_setup')) {
    if (_('textarea[name^="field_values"]').val() == '' && _('select[name^="field_type"]').val() != 'select' && _('select[name^="field_type"]').val() != 'multiselect') {
        _('textarea[name^="field_values"]').disable();
    }

    _('select[name^="field_type"]').on('change', function () {
        if (_(this).val() == 'select' || _(this).val() == 'multiselect') {
            _('textarea[name^="field_values"]').enable();
        } else {
            _('textarea[name^="field_values"]').val('').disable();
        }
    });

    _('textarea[name^="field_values"]').on('focus', function () {
        if (_(this).val() == '' && (_('select[name^="field_type"]').val() == 'select' || _('select[name^="field_type"]').val() == 'multiselect')) {
            _(this).val('[1][]\r\n[2][]\r\n[3][]');
        }
    });

    _('textarea[name^="field_default"]').on('focus', function () {
        const self = _(this);

        if (self.val() == '') {
            if (_('select[name^="field_type"]').val() == 'checkbox' || _('select[name^="field_type"]').val() == 'select') {
                self.val('1');
            } else if (_('select[name^="field_type"]').val() == 'multiselect') {
                self.val('-1-');
            }
        }
    });
}

/** Cмена мест полей заявки перетаскиванием мышкой */
if (el('table.setup')) {
    fraymDragDropApply('table.setup tbody tr', {
        sortable: true,
        dragEnd: function () {
            const self = _(this);
            const link = self.find('td').first().html();
            const linkRegexp = /\/(\d+)\/act=edit/g;
            const values = linkRegexp.exec(link);
            const code = self.index() + 1;
            let trClassNumber = 1;

            _('table.setup tbody tr').each(function () {
                _(this).removeClass('string1').removeClass('string2').addClass(`string${trClassNumber}`);
                trClassNumber = 3 - trClassNumber;
            });

            actionRequest({
                action: 'setup/change_project_field_code',
                obj_id: values[1],
                code: code
            });
        }
    })
}

if (withDocumentEvents) {
    /** Добавление новой строки в поле "Значения" */
    _(document).on('keydown', 'textarea[name^="field_values"]', function (e) {
        if (e.keyCode == 13 && (_('select[name^="field_type"]').val() == 'select' || _('select[name^="field_type"]').val() == 'multiselect')) {
            const self = _(this);
            const val = self.val();

            if (val.length == self.prop("selectionStart")) {
                e.preventDefault();

                const values = val.match(/\[\d+]/g);
                const lastone = 0;

                if (values) {
                    _each(values, function (value) {
                        const curval = parseInt(value.replace("\[", "").replace("\]", ""));

                        if (curval > lastone) {
                            lastone = curval;
                        }
                    });
                }

                lastone++;

                self.val(`${self.val()}${(values ? '\r\n' : '')}[${lastone}][]`);
                self.scrollTop(self[0].scrollHeight - self.height());
            }
        }
    });

    _arSuccess('change_project_field_code', function () { })
}