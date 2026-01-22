/** Регистрация на месте */

if (el('input#registration_search')) {
    _('input#registration_search').on('keyup', function () {
        //ждем немного дальнейшего ввода
        const self = _(this);

        debounce('registrationSearch', function () {
            if (self.val() != '') {
                actionRequest({
                    action: 'registration/get_registration_player',
                    obj_name: self.val()
                });
            }
        }, 500);
    });
}

if (withDocumentEvents) {
    _arSuccess('get_registration_player', function (jsonData, params, target) {
        _('div#registration_result').html(jsonData['response_data']);

        _('a#set_registration_player').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'registration/set_registration_player',
                obj_id: self.attr('obj_id')
            }, self);
        });

        _('a#set_registration_player_money').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'registration/set_registration_player_money',
                obj_id: self.attr('obj_id')
            }, self);
        });

        _('a#set_registration_eco_money').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'registration/set_registration_eco_money',
                obj_id: self.attr('obj_id')
            }, self);
        });

        _('textarea#player_registration_comments').on('keyup', function () {
            const self = _(this);

            actionRequest({
                action: 'registration/set_registration_comments',
                value: self.val(),
                obj_id: self.attr('obj_id')
            }, self);
        });
    })

    _arSuccess('set_registration_player', function (jsonData, params, target) {
        target.parent().html('<span class="sbi sbi-check"></span>');
    })

    _arSuccess('set_registration_player_money', function (jsonData, params, target) {
        target.parent().html('<span class="sbi sbi-check"></span>');
    })

    _arSuccess('set_registration_eco_money', function (jsonData, params, target) {
        target.parent().html('<span class="sbi sbi-check"></span>');
    })

    _arSuccess('set_registration_comments', function () { })
}