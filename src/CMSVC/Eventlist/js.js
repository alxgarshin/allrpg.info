/** Календарь событий проектов */

if (withDocumentEvents) {
    /** Фильтр */
    _(document).on('change', 'select[name="eventlist_filter_obj"]', function () {
        const self = _(this).find('option:selected');

        _('div.eventlist_block').hide();

        if (self.attr('obj_type') != 'all' && self.attr('obj_type') != 'personal' && self.attr('obj_type') != 'global') {
            _(`div.eventlist_block[obj_type=${self.attr('obj_type`)}][obj_id=${self.attr(`obj_id')}]`).show();
        } else if (self.attr('obj_type') == 'all') {
            _('div.eventlist_block').show();
        } else if (self.attr('obj_type') == 'personal') {
            _('div.eventlist_block[obj_type=""][obj_id=""]').show();
        } else if (self.attr('obj_type') == 'global') {
            _('div.eventlist_block[obj_type=global]').show();
        }
    });
}