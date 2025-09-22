/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class FraymStyler {
    constructor(element, options) {
        if (!element) return false;

        const self = _(element);

        if (element.tagName === 'INPUT' && self.attr('type') === 'file' && !self.hasClass('fraymStylerApplied') && !self.hasClass('filepond')) {
            const id = (self.attr('id') !== null && self.attr('id') !== '') ? self.attr('id') + '-styler' : null;
            const title = self.attr('title');
            const classes = self.attr('class');
            const placeholder = defaultFor(self.attr('placeholder'), options.filePlaceholder);
            const browse = defaultFor(self.attr('browse'), options.fileBrowse);

            const file = '<div>' +
                '<div class="inputfile__name">' + placeholder + '</div>' +
                '<div class="inputfile__browse">' + browse + '</div>' +
                '</div>';

            element.insertAdjacentHTML('afterend', file);

            const fileElement = self.next();

            if (id) {
                fileElement.attr('id', id);
            }

            if (title) {
                fileElement.attr('title', title);
            }

            if (classes) {
                const classesArray = classes.split(' ');

                classesArray.forEach((className) => {
                    fileElement.addClass(className);
                })
            }

            if (element.disabled) fileElement.addClass('disabled');

            fileElement.asDomElement().insertAdjacentElement('beforeend', element);

            const name = el('.inputfile__name', fileElement.asDomElement());

            self
                .on('change', function () {
                    let value = element.value;

                    if (self.attr('multiple')) {
                        value = '';
                        const files = element.files.length;

                        if (files > 0) {
                            let number = self.attr('data-number');
                            if (number === null) number = options.fileNumber;
                            number = number.replace('%s', files);
                            value = number;
                        }
                    }

                    name.innerText = value.replace(/.+[\\\/]/, '');

                    if (value === '') {
                        name.innerText = placeholder;
                        fileElement.removeClass('changed');
                    } else {
                        fileElement.addClass('changed');
                    }
                })
                .on('focus', function () {
                    fileElement.addClass('focused');
                })
                .on('blur', function () {
                    fileElement.removeClass('focused');
                })
                .on('click', function () {
                    fileElement.removeClass('focused');
                });
        }
    }
}