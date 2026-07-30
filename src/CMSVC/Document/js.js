/** Геннератор документов */

const documentRawSourceApply = (rawSource, attempt) => {
    const id = rawSource.getAttribute('data-raw-for');
    const quill = wysiwygObjs.get(id);

    if (!quill) {
        if (attempt < 100) {
            delay(50).then(() => documentRawSourceApply(rawSource, attempt + 1));
        }

        return;
    }

    if (quill.rawSourceApplied) {
        return;
    }

    const codeEditor = el(`textarea[data-wysiwyg-id="${id}"]`);

    if (!codeEditor) {
        return;
    }

    let raw = rawSource.value;

    _(codeEditor).val(raw).hide();
    _(quill.container).show();

    quill.on('text-change', (delta, oldDelta, source) => {
        if (source === 'user') {
            raw = quill.root.innerHTML;
        }
    });

    quill.getModule('toolbar').addHandler('viewCode', () => {
        if (_(codeEditor).hasClass('hidden')) {
            _(codeEditor).val(raw).show();
            _(quill.container).hide();
            codeEditor.focus();
        } else {
            raw = codeEditor.value;
            /** Визуальному редактору отдаём почищенную парсером версию: она нужна только для показа, сохраняется raw */
            quill.clipboard.dangerouslyPasteHTML(raw);
            _(codeEditor).hide();
            _(quill.container).show();
            quill.focus();
        }
    });

    wysiwygObjs.set(id, {
        rawSourceApplied: true,
        root: {
            get innerHTML() {
                return _(codeEditor).hasClass('hidden') ? raw : codeEditor.value;
            },
        },
    });
};

_('textarea.wysiwyg_raw_source').each(function () {
    documentRawSourceApply(this, 0);
});

if (withDocumentEvents) {

}
