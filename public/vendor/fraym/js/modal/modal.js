/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class FraymModal {
    constructor(element, options) {
        if (!element) return false;

        const self = _(element);

        if (self.hasClass('fraymmodal-window')) {
            const overlayId = defaultFor(options.overlayId, 'fraymmodal-overlay');
            const containerId = defaultFor(options.containerId, 'fraymmodal-container');
            const closeButtonId = defaultFor(options.closeButtonId, 'fraymmodal-close');
            const modalTitleClass = defaultFor(options.modalTitleClass, 'fraymmodal-title');
            const modalContentClass = defaultFor(options.modalContentClass, 'fraymmodal-content');

            const modalWindow = function () {
                const external = self.hasClass('modal-external') ? '' : '&modal=true';

                fetchData(self.attr('href') + external, { method: 'GET', json: false }).then(result => {
                    _('.selected').removeClass('selected');

                    let curTabIndex = 0;
                    if (el('[tabIndex]')) {
                        curTabIndex = +_('[tabIndex]').last().attr('tabIndex');
                    }

                    _('body').addClass('noscroll');

                    if (el(`#${overlayId}`)) {
                        _(`.${modalTitleClass}`).addClass('fadeToTransparent');
                        _(`.${modalContentClass}`).addClass('fadeToTransparent');

                        delay()
                            .then(() => {
                                el(`.${modalTitleClass}`).remove();
                                el(`.${modalContentClass}`).remove();

                                el(`#${containerId}`).insertAdjacentHTML('beforeend', result);

                                _(`.${modalTitleClass}`).addClass('shown').addClass('fadeFromTransparent');
                                _(`.${modalContentClass}`).addClass('shown').addClass('fadeFromTransparent');

                                return delay();
                            })
                            .then(() => {
                                options.onOpen(self);

                                fixTabIndex(curTabIndex);
                            });
                    } else {
                        const overlay = elNew('div', { id: overlayId });
                        const container = elNew('div', { id: containerId });

                        document.body.append(overlay);
                        document.body.append(container);

                        const closeButton = elNew('div', { id: closeButtonId });

                        container.append(closeButton);
                        container.insertAdjacentHTML('beforeend', result);

                        options.onOpen(self);

                        _(closeButton).on('click', () => closeModal());
                        _(overlay).on('click', () => closeModal());
                        _(document).on('keydown', (event) => closeModalOnEscapeKey(event));

                        _(overlay).addClass('shown').addClass('fadeFromTransparent');

                        delay()
                            .then(() => {
                                _(container).addClass('shown').addClass('fadeFromTransparent');

                                return delay();
                            })
                            .then(() => {
                                _(`#${closeButtonId}`).addClass('shown').addClass('fadeFromTransparent');
                                _(`.${modalTitleClass}`).addClass('shown').addClass('fadeFromTransparent');
                                _(`.${modalContentClass}`).addClass('shown').addClass('fadeFromTransparent');

                                return delay();
                            }).then(() => {
                                options.onOpen(self);

                                fixTabIndex(curTabIndex);
                            });
                    }
                });
            };

            const closeModal = function () {
                const container = _(`#${containerId}`);

                if (container.hasClass('fraymmodal-closing')) {
                    return;
                } else {
                    container.addClass('fraymmodal-closing');
                }

                _('body').removeClass('noscroll');

                _(`#${closeButtonId}`).addClass('fadeToTransparent');
                _(`.${modalTitleClass}`).addClass('fadeToTransparent');
                _(`.${modalContentClass}`).addClass('fadeToTransparent');

                delay()
                    .then(() => {
                        _(`#${overlayId}`).addClass('fadeToTransparent');

                        return delay();
                    })
                    .then(() => {
                        _(`#${containerId}`).addClass('fadeToTransparent');

                        return delay();
                    })
                    .then(() => {
                        el(`#${overlayId}`).remove();
                        el(`#${containerId}`).remove();

                        options.onClose();
                    });
            };

            const closeModalOnEscapeKey = function (event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            };

            const fixTabIndex = function (curTabIndex) {
                _(`#${containerId} .${modalContentClass} [tabIndex]`).each(function () {
                    const self = _(this);
                    self.attr('tabIndex', +self.attr('tabIndex') + curTabIndex);
                });
            };

            modalWindow();
        }
    }
}