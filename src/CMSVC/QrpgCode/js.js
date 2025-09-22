/** QRpg: коды */

if (el('form#form_qrpg_code_add')) {
    _(document).on('focusin', 'form#form_qrpg_code_add textarea[name^="description"]', function () {
        _(this).addClass('expanded');
        _(this).parent().addClass('expanded');
    });

    _(document).on('focusout', 'form#form_qrpg_code_add textarea[name^="description"]', function () {
        _(this).removeClass('expanded');
        _(this).parent().removeClass('expanded');
    });

    _('form#form_qrpg_code_add input[name^="sid"]').on('keydown', function () {
        _(this).closest('tr')?.find('a.qrpg_code_generate_link')?.hide();
    });

    _('form#form_qrpg_code_add').on('submit', function () {
        _(this).find('tr')?.each(function () {
            _(this).find('a.qrpg_code_generate_link').show();
        });
    });
}

if (withDocumentEvents) {

}