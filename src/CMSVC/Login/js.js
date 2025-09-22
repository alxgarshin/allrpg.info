/** Логин */

if (el('div.mainpage_login')) {
    _('#btn_make_remind').disable();
    _('#btn_login').disable();

    _('#btn_remind').on('click', function (e) {
        e.stopPropagation();

        _('#login_choices').addClass('fadeToTransparent');

        delay(200).then(() => {
            _('#login_choices').hide();
            _('#login_remind').show().addClass('fadeFromTransparent');
        })
    });

    _('#login_global, #pass_global').on('change keyup', function () {
        if (_('#login_global').val() != '') {
            _('#btn_login').enable();
        } else {
            _('#btn_login').disable();
        }
    });

    _(document).on('change keyup', 'input#login_global', function () {
        const regex = /^([а-яёa-zA-Z0-9_.\-+])+@(([а-яёa-zA-Z0-9\-])+\.)+([а-яёa-zA-Z0-9]{2,4})+$/;

        simpleValidate(_(this), regex.test(_(this).val()), LOCALE.wrongEmailFormat);
    });

    _('#em_global').on('change keyup', function () {
        if (_(this).val() != '') {
            _('#btn_make_remind').enable();
        } else {
            _('#btn_make_remind').disable();
        }
    });

    _('#btn_make_remind').on('click', function () {
        const regex = /^([а-яёa-zA-Z0-9_.\-+])+@(([а-яёa-zA-Z0-9\-])+\.)+([а-яёa-zA-Z0-9]{2,4})+$/;

        if (regex.test(_('#em_global').val())) {
            _(this).closest('form').submit();
        } else {
            showMessage({
                text: LOCALE.wrongEmailFormat,
                type: 'error',
                timeout: 5000
            });
        }

        return false;
    });
}

if (withDocumentEvents) {

}