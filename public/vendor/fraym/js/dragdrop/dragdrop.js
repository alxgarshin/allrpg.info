/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class FraymDragDrop {
	constructor(element, options) {
		if (!element) return false;
		if (_(element).hasClass('fraymDragDropApplied')) return false;

		const dragDrop = this;

		this.element = _(element);

		this.element.addClass('fraymDragDropApplied');

		this.options = Object.assign(
			{
				handler: null,
				revert: false,
				sortable: false,
				dragStart: function () { },
				dragEnd: function () { },
				dropTargets: [
					{
						elementSelector: null,
						onDrop: function () { }
					}
				]
			},
			options
		);

		this.element.attr('draggable', true);

		if (this.options.handler !== null) {
			this.element.each(function () {
				const elementChildren = elAll(':scope > *', this);
				const computedStyle = window.getComputedStyle(this);

				elementChildren.forEach(child => {
					if (typeof dragDrop.options.handler === 'string' ? !child.matches(dragDrop.options.handler) : child !== dragDrop.options.handler) {
						_(child)
							.attr('draggable', true)
							.on('dragstart', dragDrop.catchAndPreventDragStartEventGettingToParent);
					}
				});

				const handlerElement = typeof dragDrop.options.handler === 'string' ? _(el(dragDrop.options.handler, this)) : _(dragDrop.options.handler);

				handlerElement?.css('cursor', 'move');

				if (computedStyle.display === 'contents') {
					handlerElement?.attr('draggable', true);
				}
			})
		}

		this.element.each(function () {
			_(this).find('a')?.attr('draggable', false);
		});

		this.element.on('dragstart', function (e) {
			e.stopImmediatePropagation();

			const oldX = e.clientX;
			const oldY = e.clientY;
			const self = _(this);

			self
				.addClass('dragged')
				.attr('dragLeft', oldX - e.target.offsetLeft)
				.attr('dragTop', oldY - e.target.offsetTop);

			dragDrop.options.dragStart.call(this, e);
		});

		this.element.on('dragend', function (e) {
			e.stopImmediatePropagation();

			const self = _(this);

			if (!dragDrop.options.revert && !dragDrop.options.sortable) {
				const newX = e.clientX;
				const newY = e.clientY;

				self
					.css('left', `${newX - self.attr('dragLeft')}px`)
					.css('top', `${newY - self.attr('dragTop')}px`);
			}

			self
				.removeClass('dragged')
				.attr('dragLeft', null)
				.attr('dragTop', null);

			dragDrop.options.dragEnd.call(this, e);
		});

		if (this.options.sortable) {
			this.element.on('dragover', function (e) {
				e.preventDefault();
				e.stopImmediatePropagation();

				const draggedOverElement = this;
				const draggingElement = el('.dragged');

				if (draggedOverElement !== draggingElement && draggedOverElement.parentElement === draggingElement.parentElement) {
					dragDrop.element.each(function () {
						if (this.contains(draggingElement)) {
							this.parentElement.insertBefore(draggingElement, draggedOverElement);
							return;
						}
					})
				}
			});
		} else {
			this.options.dropTargets.forEach(dropTarget => {
				if (dropTarget.elementSelector) {
					_(dropTarget.elementSelector).each(function () {
						_(this)
							.attr('droppable', true)
							.on('dragover', function (e) {
								e.preventDefault();
								e.stopImmediatePropagation();

								e.dataTransfer.dropEffect = 'move';
							})
							.on('drop', function (e) {
								e.stopImmediatePropagation();

								dropTarget.onDrop.call(this, element);
							});
					})
				}
			});
		}
	}

	catchAndPreventDragStartEventGettingToParent(e) {
		e.preventDefault();
		e.stopImmediatePropagation();
	}
}