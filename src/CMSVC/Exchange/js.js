/** Склад */

if (el('div.kind_exchange')) {
    _('div.kind_exchange select#filter_region').on('change', function () {
        const region = _(this).val();

        updateState(`/exchange/region=${region}`);
    });
}

if (withDocumentEvents) {

}