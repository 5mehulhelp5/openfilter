/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 *
 * Stores > Configuration > OpenFilter > SEO: a live character count under the title and description templates
 * (search results show about 60 and 160 characters). Placeholders count with their own length, so the number is a
 * guide, not a limit.
 */
define([], function () {
    'use strict';

    return function (options) {
        Object.keys(options.fields).forEach(function (id) {
            var input = document.getElementById(id),
                note,
                update;

            if (!input) {
                return;
            }
            note = document.createElement('p');
            note.className = 'note of-template-length';
            note.setAttribute('aria-live', 'polite');
            update = function () {
                var length = input.value.length,
                    limit = options.fields[id];

                note.textContent = options.label.replace('%1', String(length)).replace('%2', String(limit));
                note.style.color = length > limit ? '#e22626' : '';
            };
            input.insertAdjacentElement('afterend', note);
            input.addEventListener('input', update);
            update();
        });
    };
});
