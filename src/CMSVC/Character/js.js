/** Персонажи */

/** Сокрытие / показ полей */
if (el('form#form_character')) {
    window.ancval = false;

    _('input[name="applications_needed_count[0]"]').on('change keyup', function () {
        const self = _(this);

        if (self.val() > 1) {
            _('div[id="field_auto_new_character_creation[0]"]').show();

            if (window.ancval) {
                _('input[name="auto_new_character_creation[0]"]').checked(true).trigger('refresh');
            }
        } else {
            _('div[id="field_auto_new_character_creation[0]"]').hide();

            if (window.ancval) {
                _('input[name="auto_new_character_creation[0]"]').checked(false).trigger('refresh');
            }
        }

        window.ancval = true;
    });
    _('input[name="applications_needed_count[0]"]').change();

    _('select[name="team_character[0]"]').on('change', function () {
        const self = _(this);

        if (self.val() == 1) {
            _('div[id="field_team_applications_needed_count[0]"]').show();
        } else {
            _('div[id="field_team_applications_needed_count[0]"]').hide();
            _('input[name="team_applications_needed_count[0]"]').val('0');
        }
    });
    _('select[name="team_character[0]"]').change();
}

if (withDocumentEvents) {

}