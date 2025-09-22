/** Профиль */

if (el('form#form_profile')) {
    /** Уведомления в браузере */
    if (el('input[name^="messaging_active"]')) {
        _('input[name^="messaging_active"]').checked(localStorage.getItem('webpush') === 'true');

        _('input[name^="messaging_active"]').on('change', function () {
            const self = _(this);

            if (self.is(':checked')) {
                window.subscribeFlow(true);
            } else {
                const deviceId = getOrCreateDeviceId();

                actionRequest({
                    action: 'user/webpush_unsubscribe',
                    deviceId: deviceId,
                });
            }
        });
    }
}
