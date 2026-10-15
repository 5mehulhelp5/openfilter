/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 *
 * "Sort filters": reorders the list by drag and drop or by the Move up / Move down buttons (keyboard), keeps focus
 * on the moved button and announces the new place.
 */
define([], function () {
    'use strict';

    return function (options, form) {
        var list = form.querySelector('.of-sort-list'),
            status = form.querySelector('.of-sort-status'),
            dragged = null;

        /**
         * The first row cannot move up, the last one not down: aria-disabled keeps the button focusable, so focus
         * stays on it when a filter reaches the top or the bottom.
         */
        function refresh() {
            var items = list.children;

            Array.prototype.forEach.call(items, function (item, index) {
                item.querySelector('[data-of-move="up"]').setAttribute('aria-disabled', String(index === 0));
                item.querySelector('[data-of-move="down"]').setAttribute('aria-disabled', String(index === items.length - 1));
            });
        }

        function announce(item) {
            var items = Array.prototype.slice.call(list.children),
                label = item.querySelector('.of-sort-label').textContent;

            status.textContent = options.movedLabel
                .replace('%1', label)
                .replace('%2', String(items.indexOf(item) + 1))
                .replace('%3', String(items.length));
        }

        refresh();

        // "Find a filter": shows the filters whose name contains the text; moving works within the whole list.
        form.querySelector('[data-of-sort-search]').addEventListener('input', function (event) {
            var text = event.target.value.trim().toLowerCase();

            Array.prototype.forEach.call(list.children, function (item) {
                item.hidden = text !== '' &&
                    item.querySelector('.of-sort-label').textContent.toLowerCase().indexOf(text) === -1;
            });
        });

        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-of-move]'),
                item,
                sibling;

            if (!button || button.getAttribute('aria-disabled') === 'true') {
                return;
            }
            item = button.closest('.of-sort-item');
            if (button.getAttribute('data-of-move') === 'up') {
                sibling = item.previousElementSibling;
                if (sibling) {
                    list.insertBefore(item, sibling);
                }
            } else {
                sibling = item.nextElementSibling;
                if (sibling) {
                    list.insertBefore(sibling, item);
                }
            }
            refresh();
            button.focus();
            announce(item);
        });

        list.addEventListener('dragstart', function (event) {
            dragged = event.target.closest('.of-sort-item');
            if (dragged) {
                dragged.classList.add('_dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', dragged.getAttribute('data-of-key'));
            }
        });

        list.addEventListener('dragover', function (event) {
            var target = event.target.closest('.of-sort-item'),
                box;

            if (!dragged || !target || target === dragged) {
                return;
            }
            event.preventDefault();
            box = target.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? target : target.nextElementSibling);
        });

        list.addEventListener('dragend', function () {
            if (dragged) {
                dragged.classList.remove('_dragging');
                refresh();
                announce(dragged);
                dragged = null;
            }
        });
    };
});
