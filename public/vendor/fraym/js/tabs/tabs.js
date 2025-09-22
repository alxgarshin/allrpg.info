/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class FraymTabs {
    constructor(element) {
        if (!element) return false;

        const self = _(element);

        if (!self.hasClass('fraymTabsApplied')) {
            const tabs = function () {
                self.addClass('fraymTabsApplied');

                const navPanel = self.find(':scope > ul');
                const controls = navPanel.find('li');
                const panels = self.find(':scope > div[id]');

                panels.addClass('fraymtabs-panel');

                panels.each(function () {
                    if (this.innerHTML.trim() === '') {
                        _(el(`a#${this.id.replace('fraymtabs-', '')}`, navPanel.asDomElement())).closest('li').attr('disabled', 'true');
                    }
                });

                _(document).on('activate', '.fraymtabs>ul>li', function () {
                    const self = _(this);

                    if (!self.hasClass('fraymtabs-active') && !self.is('[disabled]')) {
                        const tabs = self.closest('.fraymtabs').asDomElement();
                        const hash = self.find('a').attr('id');

                        _(elAll(':scope > ul li', tabs)).removeClass('fraymtabs-active');
                        _(elAll(':scope > .fraymtabs-panel', tabs)).hide();

                        self.addClass('fraymtabs-active');
                        _(el(`div#fraymtabs-${hash}`, tabs)).show().addClass('fadeFromTransparentSlow');
                    }
                });

                _(document).on('activateWithParents', '.fraymtabs>ul>li', function () {
                    const self = _(this);

                    self.trigger('activate');

                    const parentFraymTabsPanel = self.closest('.fraymtabs').parent().closest('.fraymtabs-panel');

                    if (parentFraymTabsPanel && parentFraymTabsPanel.asDomElement()) {
                        _(`a#${parentFraymTabsPanel.attr('id').replace('fraymtabs-', '')}`).closest('li').trigger('activateWithParents');
                    }
                });

                _(document).on('click', '.fraymtabs>ul>li', function (e) {
                    e.preventDefault();

                    const self = _(this);
                    const parentFraymTabsPanel = self.closest('.fraymtabs').parent().closest('.fraymtabs-panel');
                    const hash = self.find('a').attr('id');

                    self.trigger('activateWithParents');

                    if (window.location.hash !== `#${hash}`) {
                        componentsUpdateState((self.index() !== 0 || (parentFraymTabsPanel && parentFraymTabsPanel.asDomElement())) ? hash : '');
                    }
                });

                const hash = window.location.hash.substring(1);

                if (hash !== '' && el(`.fraymtabs li>a#${hash}`, navPanel.asDomElement())) {
                    if (el(`.fraymtabs div#fraymtabs-${hash}`).checkVisibility()) {
                    } else {
                        _(`.fraymtabs li>a#${hash}`).click();
                    }
                } else {
                    controls.first().trigger('activate');
                }
            };

            tabs();
        }
    }
}