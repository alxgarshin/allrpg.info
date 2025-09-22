/** Бюджет */

if (el('form#form_budget_add')) {
    _('a#nullify_fees').on('click', function () {
        actionRequest({
            action: 'transaction/nullify_fees'
        });
    });

    fraymDragDropApply('form#form_budget_add div.multi_objects_table div.tr[class*="string"]', {
        sortable: true,
        handler: '.td.multi_objects_num',
        dragEnd: function () {
            const self = _(this);

            const objId = self.find('[name^="id"]').val();
            const afterObjId = self.prev('.tr')?.find('[name^="id"]')?.val() || 0;

            let trClassNumber = 1;
            let i = 1;
            _('form#form_budget_add div.multi_objects_table div.tr[class*="string"]').each(function () {
                const self = _(this);

                self.removeClass('string1').removeClass('string2').addClass(`string${trClassNumber}`);
                trClassNumber = 3 - trClassNumber;

                self.find('div.td.multi_objects_num').text(i);
                i++;
            });

            actionRequest({
                action: 'budget/change_budget_code',
                obj_id: objId,
                after_obj_id: afterObjId
            });

            _('input[name="price[0]"]').trigger('change');
        }
    })

    _('input[name^="quantity_needed"], input[name^="quantity"]').on('change keyup', function () {
        const self = _(this);
        const id = self.attr('name').match(/\d+/);
        const quantityNeeded = parseInt(_(`input[name="quantity_needed[${id}]"]`).val() || 0);
        const quantity = parseInt(_(`input[name="quantity[${id}]"]`).val()) || 0;
        const price = parseInt(_(`input[name="price[${id}]"]`).val()) || 0;

        if (quantityNeeded > 0 || quantity > 0) {
            const condition = quantityNeeded * price <= quantity;

            self.closest('div.tr').toggleClass('bought', condition).toggleClass('not_bought', !condition);
        } else {
            self.closest('div.tr').removeClass('bought').removeClass('not_bought');
        }
    });

    _('input[name^="quantity_needed"]').trigger('change');

    _('input[name^="quantity_needed"], input[name^="quantity"], input[name^="price"], select[name^="bought_by"]').on('change keyup', function () {
        let spent = 0;
        let total = 0;
        const boughtByGamemaster = [];

        _('input[name^="price"]').each(function () {
            const self = _(this);
            const id = self.attr('name').match(/\d+/);
            const price = _(`input[name="price[${id}]"]`).val();
            const gamemasterId = _(`select[name="bought_by[${id}]"]`).val();
            const quantity = parseInt(_(`input[name="quantity[${id}]"]`).val()) || 0;
            const quantityNeeded = parseInt(_(`input[name="quantity_needed[${id}]"]`).val()) || 0;

            if (gamemasterId == '') {
                spent += quantity;
            } else {
                const boughtByGamemasterSpent = quantity;

                if (boughtByGamemaster[gamemasterId] === undefined) {
                    boughtByGamemaster[gamemasterId] = 0;
                }

                boughtByGamemaster[gamemasterId] += boughtByGamemasterSpent;
            }

            total += quantityNeeded * price;
        });

        const total10 = Math.round(total / 100 * 110);
        const needed = total - (parseInt(_('div#budget_paid span.budget_field_data').text()) - parseFloat(_('div#budget_comission span.budget_field_data').text()));
        const remaining = parseInt(_('div#budget_paid span.budget_field_data').text()) - parseFloat(_('div#budget_comission span.budget_field_data').text()) - spent;
        const recommended = Math.round(total / parseInt(_('div#budget_player_count span.budget_field_data').text()) / 100 * 120);
        const overdraft = parseInt(_('div#budget_player_count span.budget_field_data').text()) * parseInt(_('div#budget_set span.budget_field_data').text()) - total;

        _('div#budget_total10 span.budget_field_data').text(total10);
        _('div#budget_needed span.budget_field_data').text(needed);
        _('div#budget_spent span.budget_field_data').text(spent);
        _('div#budget_remaining span.budget_field_data').text(remaining);
        _('div#budget_total span.budget_field_data').text(total);
        _('div#budget_recommended span.budget_field_data').text(recommended);
        _('div#budget_overdraft span.budget_field_data').text(overdraft);
        _('div.bought_by_gamemaster span.bought_by_gamemaster_amount').text('');

        _each(boughtByGamemaster, function (item, key) {
            _(`div.bought_by_gamemaster[obj_id=${key}] span.bought_by_gamemaster_amount`).text(item);
        });

        //расчет суммы по категории (если есть)
        _('div.tr.readonly').each(function () {
            const categoryTr = _(this);
            const categoryTd = categoryTr.find('div.td:nth-of-type(4)');
            const categoryTd2 = categoryTr.find('div.td:nth-of-type(7)');
            let totalPrice = 0;

            categoryTr.next('div.tr', 'div.tr.readonly, div.tr.menu')?.each(function () {
                const self = _(this);

                totalPrice += self.find('input[name^="quantity"]').val() * self.find('input[name^="price"]').val();
            });

            categoryTd.html(totalPrice);
            categoryTd2.html('+10% = ' + (totalPrice / 100 * 110));
        });
    });

    _('input[name="price[0]"]').change();

    _('form#form_budget_add div.multi_objects_table div.tbody div.tr').on('remove', function () {
        _('input[name="price[0]"]').change();
    });

    _(document).on('focusin', 'form#form_budget_add textarea[name^="description"]', function () {
        _(this).addClass('expanded');
        _(this).parent().addClass('expanded');
    });

    _(document).on('focusout', 'form#form_budget_add textarea[name^="description"]', function () {
        _(this).removeClass('expanded');
        _(this).parent().removeClass('expanded');
    });
}

if (withDocumentEvents) {
    _arSuccess('change_budget_code', function () { })

    _arSuccess('nullify_fees', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        updateState(currentHref);
    })
}