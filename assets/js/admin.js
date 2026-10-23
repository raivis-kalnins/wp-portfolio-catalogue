/* global WPPCAdmin, wp */
(function () {
	'use strict';

	var strings = window.WPPCAdmin || {};

	function say(element, message) {
		if (element) {
			element.textContent = message || '';
		}
	}

	function control(action, label, icon) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'button wppc-admin-icon';
		button.dataset.wppcAction = action;
		button.setAttribute('aria-label', label);
		button.title = label;
		var symbol = document.createElement('span');
		symbol.className = 'dashicons dashicons-' + icon;
		symbol.setAttribute('aria-hidden', 'true');
		button.appendChild(symbol);
		return button;
	}

	function initGallery(gallery) {
		var list = gallery.querySelector('[data-wppc-gallery-list]');
		var input = gallery.querySelector('[data-wppc-gallery-ids]');
		var changed = gallery.querySelector('[data-wppc-gallery-changed]');
		var status = gallery.querySelector('[data-wppc-gallery-status]');
		var dragging = null;

		function items() {
			return Array.from(list.children);
		}

		function sync(markChanged) {
			var images = items();
			input.value = images.map(function (item) { return item.dataset.imageId; }).join(',');
			images.forEach(function (item, index) {
				item.querySelector('[data-wppc-action="up"]').disabled = index === 0;
				item.querySelector('[data-wppc-action="down"]').disabled = index === images.length - 1;
			});
			if (markChanged) {
				changed.value = '1';
				var legacy = gallery.querySelector('[data-wppc-legacy-gallery]');
				if (legacy) {
					legacy.hidden = true;
				}
				say(status, strings.galleryUpdated);
			}
		}

		function appendImage(attachment) {
			var item = document.createElement('li');
			item.className = 'wppc-admin-gallery-item';
			item.dataset.imageId = String(attachment.id);
			item.draggable = true;
			var image = document.createElement('img');
			var size = attachment.sizes && (attachment.sizes.thumbnail || attachment.sizes.medium);
			image.src = size ? size.url : attachment.url;
			image.alt = attachment.alt || '';
			image.draggable = false;
			var buttons = document.createElement('div');
			buttons.className = 'wppc-admin-gallery-tools';
			buttons.appendChild(control('up', strings.moveImageUp, 'arrow-up-alt2'));
			buttons.appendChild(control('down', strings.moveImageDown, 'arrow-down-alt2'));
			buttons.appendChild(control('remove', strings.removeImage, 'no-alt'));
			item.appendChild(image);
			item.appendChild(buttons);
			list.appendChild(item);
		}

		gallery.querySelector('[data-wppc-add-images]').addEventListener('click', function () {
			if (!window.wp || !wp.media) {
				return;
			}
			var frame = wp.media({
				title: strings.mediaTitle,
				button: { text: strings.mediaButton },
				library: { type: 'image' },
				multiple: true
			});
			frame.on('select', function () {
				var existing = new Set(items().map(function (item) { return Number(item.dataset.imageId); }));
				var limitReached = false;
				var added = false;
				frame.state().get('selection').toJSON().forEach(function (attachment) {
					if (!attachment.id || attachment.type !== 'image' || existing.has(Number(attachment.id))) {
						return;
					}
					if (existing.size >= 100) {
						limitReached = true;
						return;
					}
					appendImage(attachment);
					existing.add(Number(attachment.id));
					added = true;
				});
				if (added) {
					sync(true);
				}
				if (limitReached) {
					say(status, strings.galleryLimit);
				}
			});
			frame.open();
		});

		gallery.querySelector('[data-wppc-clear-gallery]').addEventListener('click', function () {
			list.replaceChildren();
			sync(true);
		});

		list.addEventListener('click', function (event) {
			var button = event.target.closest('[data-wppc-action]');
			if (!button) {
				return;
			}
			var item = button.closest('[data-image-id]');
			var action = button.dataset.wppcAction;
			var focus = button;
			if (action === 'remove') {
				var sibling = item.nextElementSibling || item.previousElementSibling;
				focus = sibling ? sibling.querySelector('[data-wppc-action="remove"]') : gallery.querySelector('[data-wppc-add-images]');
				item.remove();
			} else if (action === 'up' && item.previousElementSibling) {
				list.insertBefore(item, item.previousElementSibling);
			} else if (action === 'down' && item.nextElementSibling) {
				list.insertBefore(item.nextElementSibling, item);
			}
			sync(true);
			if (focus.disabled) {
				focus = item.querySelector('[data-wppc-action="remove"]');
			}
			focus.focus();
		});

		list.addEventListener('dragstart', function (event) {
			var item = event.target.closest('[data-image-id]');
			if (!item) {
				return;
			}
			dragging = item;
			item.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', item.dataset.imageId);
		});
		list.addEventListener('dragover', function (event) {
			var target = event.target.closest('[data-image-id]');
			if (dragging && target && dragging !== target) {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';
				items().forEach(function (item) { item.classList.toggle('is-drop-target', item === target); });
			}
		});
		list.addEventListener('drop', function (event) {
			var target = event.target.closest('[data-image-id]');
			if (!dragging || !target || dragging === target) {
				return;
			}
			event.preventDefault();
			var images = items();
			var movingForward = images.indexOf(dragging) < images.indexOf(target);
			list.insertBefore(dragging, movingForward ? target.nextSibling : target);
			sync(true);
		});
		list.addEventListener('dragend', function () {
			items().forEach(function (item) { item.classList.remove('is-dragging', 'is-drop-target'); });
			dragging = null;
		});
		sync(false);
	}

	function initRepeater(section) {
		var list = section.querySelector('[data-wppc-rows]');
		var template = section.querySelector('[data-wppc-row-template]');
		var status = section.querySelector('[data-wppc-row-status]');
		var add = section.querySelector('[data-wppc-add-row]');

		function sync() {
			var rows = Array.from(list.children);
			rows.forEach(function (row, index) {
				row.querySelector('[data-wppc-row-number]').textContent = strings.item + ' ' + (index + 1);
				row.querySelector('[data-wppc-action="up"]').disabled = index === 0;
				row.querySelector('[data-wppc-action="down"]').disabled = index === rows.length - 1;
				row.querySelectorAll('input, textarea, select, label').forEach(function (field) {
					['name', 'id', 'for'].forEach(function (attribute) {
						var value = field.getAttribute(attribute);
						if (!value) {
							return;
						}
						if (attribute === 'name') {
							value = value.replace(/\[(?:\d+|__INDEX__)\]/, '[' + index + ']');
						} else {
							value = value.replace(/_(?:\d+|__INDEX__)_/, '_' + index + '_');
						}
						field.setAttribute(attribute, value);
					});
				});
			});
			section.querySelector('[data-wppc-empty]').hidden = rows.length > 0;
			add.disabled = rows.length >= 50;
		}

		add.addEventListener('click', function () {
			if (list.children.length >= 50) {
				say(status, strings.rowLimit);
				return;
			}
			var fragment = template.content.cloneNode(true);
			var row = fragment.querySelector('[data-wppc-row]');
			list.appendChild(fragment);
			sync();
			row.querySelector('input, textarea, select').focus();
			say(status, strings.itemAdded);
		});

		list.addEventListener('click', function (event) {
			var button = event.target.closest('[data-wppc-action]');
			if (!button) {
				return;
			}
			var row = button.closest('[data-wppc-row]');
			var action = button.dataset.wppcAction;
			var focus = button;
			if (action === 'remove') {
				var sibling = row.nextElementSibling || row.previousElementSibling;
				focus = sibling ? sibling.querySelector('input, textarea, select') : add;
				row.remove();
				say(status, strings.itemRemoved);
			} else if (action === 'up' && row.previousElementSibling) {
				list.insertBefore(row, row.previousElementSibling);
				say(status, strings.itemMoved);
			} else if (action === 'down' && row.nextElementSibling) {
				list.insertBefore(row.nextElementSibling, row);
				say(status, strings.itemMoved);
			}
			sync();
			if (focus.disabled) {
				focus = row.querySelector('input, textarea, select');
			}
			focus.focus();
		});
		sync();
	}

	function initDates() {
		var start = document.getElementById('wppc_date_start');
		var end = document.getElementById('wppc_date_end');
		if (!start || !end) {
			return;
		}
		function validate() {
			end.min = start.value || '';
			end.setCustomValidity(start.value && end.value && end.value < start.value ? strings.endDateInvalid : '');
		}
		start.addEventListener('change', validate);
		end.addEventListener('change', validate);
		validate();
	}

	function init() {
		document.querySelectorAll('[data-wppc-admin-gallery]').forEach(initGallery);
		document.querySelectorAll('[data-wppc-repeater]').forEach(initRepeater);
		document.querySelectorAll('[data-wppc-copy]').forEach(function (button) {
			button.addEventListener('click', function () {
				var status = document.querySelector('[data-wppc-copy-status]');
				if (!navigator.clipboard || !window.isSecureContext) {
					say(status, strings.copyManually);
					var code = button.parentElement.querySelector('code');
					if (code && window.getSelection) {
						var selection = window.getSelection();
						var range = document.createRange();
						range.selectNodeContents(code);
						selection.removeAllRanges();
						selection.addRange(range);
					}
					return;
				}
				navigator.clipboard.writeText(button.dataset.wppcCopy).then(function () {
					say(status, strings.copied);
				}).catch(function () {
					say(status, strings.copyManually);
				});
			});
		});
		initDates();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
