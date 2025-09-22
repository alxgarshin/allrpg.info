/** Календарь */

let reldates = [];

if (el('div.calendar')) {
    _('table.calendar_table td[rel-date]').on('click', function () {
        const reldate = _(this).attr('rel-date');

        scrollWindow(0);

        delay(200)
            .then(() => {
                _(`div.calendar div.calendar_event_card[dates~="${reldate}"]`).removeClass('hidden');

                let trClassNumber = 2;

                _('div.calendar div.calendar_event_card:not(.hidden)').each(function () {
                    _(this).removeClass('string1').removeClass('string2').addClass(`string${trClassNumber}`);
                    trClassNumber = 3 - trClassNumber;
                });
            });
    });

    _('select#filter_year').on('change', function () {
        const year = _(this).val();

        updateState(`/calendar/${year}/`);
    });

    _('div.filter_year div.fixed_select').on('click', function () {
        const year = _(this).attr('value');

        _('select#filter_year').val(year).change();
    });

    _('div.filter_gametype2 div.fixed_select').on('click', function () {
        const gametype2 = _(this).attr('value');

        _('select#filter_gametype2').val(gametype2).change();
    });

    _('input#filter_cancelled_moved, select#filter_region, select#filter_setting, select#filter_gametype2, select#filter_month').on('change', function () {
        const filters = calendarFilterString();

        if (filters.length > 0) {
            _('div.calendar div.calendar_event_card').hide();
            _('div.calendar div.calendar_event_month').hide();

            for (const key in filters) {
                _(filters[key]).show();
            }
        } else {
            _('div.calendar div.calendar_event_card').show();
            _('div.calendar div.calendar_event_month').show();
        }

        if (!el('input#filter_cancelled_moved:checked')) {
            _('div.calendar_event_card.cancelled_moved').hide();
        }

        let trClassNumber = 2;

        _('div.calendar_event_card:not(.hidden)').each(function () {
            _(this).removeClass('string1').removeClass('string2').addClass(`string${trClassNumber}`);
            trClassNumber = 3 - trClassNumber;
        });
    });

    _('div.filter_region div.fixed_select').on('click', function () {
        const region = _(this).attr('value');

        _('select#filter_region').val(region).change();
    });

    _('div.filter_month div.fixed_select').on('click', function () {
        const self = _(this);

        if (self.hasClass('filterMonthApplied')) {
            self.removeClass('filterMonthApplied');
            self.text(LOCALE.filter_month_fixed_select.closest);
            reldates = [];
        } else {
            self.addClass('filterMonthApplied');
            self.text(LOCALE.filter_month_fixed_select.all);
        }

        _('select#filter_month').val('all').change();
    });
}

if (withDocumentEvents) {
    _arSuccess('change_calendarstyle', function (jsonData, params, target) {
        if (target.text() == LOCALE.calendarstyle_0) {
            target.text(LOCALE.calendarstyle_1);
        } else {
            target.text(LOCALE.calendarstyle_0);
        }

        if (_('div.calendar_tables_container').hasClass('shown')) {
            _('div.calendar_tables_container').hide();
            _('div.calendar div.calendar_event_card.string1').removeClass('string2');
            _('div.filter_month, div.filter_region, div.filter_setting, div.filter_gametype2, div.filter_cancelled_moved, div.calendar_event_month, div.calendar div.calendar_event_card.hidden').show();
            _('input#filter_cancelled_moved').change();
        } else {
            _('div.calendar_tables_container').addClass('shown');
            _('div.calendar div.calendar_event_card.string1').addClass('string2');
            _('div.filter_month, div.filter_region, div.filter_setting, div.filter_gametype2, div.filter_cancelled_moved, div.calendar div.calendar_event_month, div.calendar div.calendar_event_card').hide();
        }
    })
}

/** Функция сбора всех фильтров в одну выборку */
function calendarFilterString() {
    const result = [];
    const setting = _('select#filter_setting').val();
    const settingFilter = setting != 'all' ? `[setting="${setting}"]` : ``;

    const gametype2 = _('select#filter_gametype2').val();
    const gametype2Filter = gametype2 != 'all' ? `[gametype2="${gametype2}"]` : ``;

    const month = _('select#filter_month').val();
    const monthFilter = month != 'all' ? `[month="${month}"]` : ``;

    let region = _('select#filter_region').val();
    let regionFilter = '';

    if (region != 'all') {
        region = eval(region);
        region.forEach(function (element) {
            regionFilter = `[region="${element}"]`;
            result.push(`div.calendar div.calendar_event_card${regionFilter}${settingFilter}${gametype2Filter}${monthFilter}`);
        });
    } else {
        if (setting != "all" || month != "all" || gametype2 != "all") {
            result.push(`div.calendar div.calendar_event_card${settingFilter}${gametype2Filter}${monthFilter}`);
        }
    }

    if (_('div.filter_month div.fixed_select').hasClass('filterMonthApplied')) {
        const d = new Date();
        d.setHours(0, 0, 0, 0);

        const lowestDate = d.valueOf() / 1000 | 0;
        const highestDate = lowestDate + 3600 * 24 * 30;

        const reldate = lowestDate;
        while (reldate < highestDate) {
            reldates.push(reldate);
            reldate = reldate + 3600 * 24;
        }

        const dateFilter = '';

        if (result.length > 0) {
            const result2 = [];

            for (const key in result) {
                const copy_filter = result[key];

                for (const key2 in reldates) {
                    dateFilter = `[dates~="${reldates[key2]}"]`;
                    result2.push(copy_filter + dateFilter);
                }
            }

            result = result2;
        } else {
            for (const key2 in reldates) {
                dateFilter = `[dates~="${reldates[key2]}"]`;
                result.push(`div.calendar div.calendar_event_card${dateFilter}`);
            }
        }
    }

    return result;
}