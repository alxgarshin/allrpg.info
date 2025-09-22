/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class FraymAutocomplete {
	constructor(element, options) {
		if (!element) return false;
		if (_(element).hasClass('fraymAutocompleteApplied')) return false;

		this.element = this.input = _(element);

		this.element.addClass('fraymAutocompleteApplied');

		this.value = null;

		this.options = {
			classPrefix: 'autocomplete'
		};

		this.options = Object.assign(
			this.options,
			{
				source: null,
				minLength: 3,
				makeEmptySearches: false,
				conditionalSearch: false,
				contiditionalMarker: '@',
				contiditionalMarkerBreakOn: ']',
				inputClass: `${this.options.classPrefix}-input`,
				emptyMessageElement: null,
				emptyMessageClass: `${this.options.classPrefix}-empty-message`,
				resultsContainerElement: null,
				resultsContainerClass: `${this.options.classPrefix}-results-container`,
				select: function () { },
				change: function () { },
				response: function (data) {
					if (data.length === 0) {
						this.emptyMessageElement.show();
						this.resultsContainerElement.hide();
					} else {
						this.emptyMessageElement.hide();
						this.resultsContainerElement.show();
					}
				}
			},
			options
		);

		this.source = this.options.source;
		this.data = [];

		this.input.addClass(this.options.inputClass);
		this.emptyMessageElement = _(this.options.emptyMessageElement ?? elNew('div')).addClass(this.options.emptyMessageClass).hide();
		this.resultsContainerElement = _(this.options.resultsContainerElement ?? elNew('div')).addClass(this.options.resultsContainerClass).hide();

		if (!this.options.emptyMessageElement) {
			this.input.insert(this.emptyMessageElement.asDomElement(), 'after');
			this.emptyMessageElement.text(LOCALE.nothing_found);
		}

		if (!this.options.resultsContainerElement) {
			this.emptyMessageElement.insert(this.resultsContainerElement.asDomElement(), 'after');
		}

		this.input.on('show' + (!this.options.conditionalSearch ? ' focusin' : ''), () => {
			if (this.data.length === 0) {
				this.emptyMessageElement.show();
				this.resultsContainerElement.hide();
			} else {
				this.emptyMessageElement.hide();
				this.resultsContainerElement.show();
			}
		});


		this.input.on('focusout', () => {
			delay(100).then(() => {
				this.emptyMessageElement.hide();
				this.resultsContainerElement.hide();
			})
		});

		this.input.on('keyup', this.handleInput.bind(this));
		this.input.on('change', this.handleInput.bind(this));
		this.input.on('activate', this.handleInput.bind(this));
	}

	handleInput(e) {
		const input = e.target;
		const autocomplete = this;
		let value = input.value;
		let currentPosition = 0;
		let closestIndice = 0;

		if (this.options.conditionalSearch) {
			if (value.indexOf(this.options.contiditionalMarker) >= 0) {
				currentPosition = getCursorPosition(input);

				if (currentPosition <= value.indexOf(this.options.contiditionalMarker)) {
					currentPosition = value.indexOf(this.options.contiditionalMarker) + 1;
				}

				let indices = [];
				for (let pos = value.indexOf(this.options.contiditionalMarker); pos !== -1; pos = value.indexOf(this.options.contiditionalMarker, pos + 1)) {
					indices.push(pos);
				}

				for (let i = 0; i < indices.length; i++) {
					if (indices[i] >= closestIndice && indices[i] < currentPosition) {
						closestIndice = indices[i];
					}
				}

				value = value.substring(closestIndice + 1, currentPosition);

				if (!value.match(this.options.contiditionalMarkerBreakOn)) {
					this.input.trigger('show');
				} else {
					value = null;
				}
			} else {
				value = null;
			}
		}


		if ((!value && !autocomplete.options.makeEmptySearches) || autocomplete.value === value) {
			return;
		}

		autocomplete.options.change.call(autocomplete, value);

		if (value.length >= this.options.minLength || (!value && autocomplete.options.makeEmptySearches)) {
			/** Ждем немного возможного дальнейшего ввода */
			window.clearTimeout(window['autocomplete_search']);

			window['autocomplete_search'] = setTimeout(function () {
				const sourceHasQuestionMark = autocomplete.source.indexOf('?') !== -1;

				if (!value && autocomplete.options.makeEmptySearches) {
					value = 'base';
				}

				fetchData(`${autocomplete.source}${sourceHasQuestionMark ? '&' : '?'}term=${value}`, { method: 'GET', json: true }).then((data) => {
					autocomplete.value = value;

					autocomplete.data = [];
					autocomplete.resultsContainerElement.empty();

					data.forEach((item) => {
						if (autocomplete.options.conditionalSearch) {
							item.currentPosition = currentPosition;
							item.closestIndice = closestIndice;
						}

						autocomplete.data.push(item);

						const itemElement = elFromHTML(`<div class="item${item.class ? ' ' + item.class : ''}">${item.value}</div>`);

						autocomplete.resultsContainerElement.insert(itemElement, 'end');

						itemElement.addEventListener('click', function () {
							autocomplete.value = item.value;

							if (!autocomplete.options.conditionalSearch) {
								autocomplete.input.val(item.value);
							}

							autocomplete.emptyMessageElement.hide();
							autocomplete.resultsContainerElement.hide();

							autocomplete.options.select.call(item);
						});
					})

					autocomplete.options.response.call(autocomplete, autocomplete.data);
				}).catch((error) => {
					console.error(error);
				})
			}, 200);
		} else if (!autocomplete.options.conditionalSearch) {
			autocomplete.data = [];
			autocomplete.resultsContainerElement.empty();
			this.input.trigger('show');
		}
	}
}