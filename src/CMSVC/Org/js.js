/** Мастера / организаторы */

if (el('form#form_org_add')) {
    _('input[type="radio"][name^="type"]').on('change', function () {
        const self = _(this);
        const target = self.closest('.tr')?.find('[id^="comment"]:not([id$="[help_1]"]):not([id$="[help_2]"])');

        if (self.val() == '{admin}' || self.val() == '{gamemaster}') {
            target.enable();
        } else {
            doDropfieldRefresh = false;
            target.checked(false).disable();
            doDropfieldRefresh = true;
            self.closest('.tr')?.find('[id^="selected_comment"]')?.trigger('refresh');
        }
    });

    doDropfieldRefresh = false;
    _('input[type="radio"][name^="type"]:checked').change();
    doDropfieldRefresh = true;
}

if (withDocumentEvents) {

}