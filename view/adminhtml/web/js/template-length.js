/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 *
 * Stores > Configuration > OpenFilter > SEO: under the title, description and H1 templates, a live preview with a
 * real category, two option labels and the store name (1.2), and how many of its characters search results show
 * (about 60 and 160). The preview follows Model\Seo\SeoTemplates::render.
 */
define([], function () {
    'use strict';

    function render(template, sample) {
        var text;

        if (template.trim() === '') {
            return '';
        }
        text = template.split('{category}').join(sample.category)
            .split('{filters}').join(sample.filters.join(', '))
            .split('{store}').join(sample.store)
            .replace(/\s+/g, ' ')
            .replace(/\s+([,:.])/g, '$1');

        return text.replace(/^[\s|,\-:]+|[\s|,\-:]+$/g, '');
    }

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
                var preview = render(input.value, options.sample),
                    limit = options.fields[id],
                    parts = [];

                if (preview !== '') {
                    parts.push(options.preview.replace('%1', preview));
                }
                if (limit > 0) {
                    parts.push(options.label.replace('%1', String(preview.length)).replace('%2', String(limit)));
                }
                note.textContent = parts.join(' · ');
                note.style.color = limit > 0 && preview.length > limit ? '#e22626' : '';
            };
            input.insertAdjacentElement('afterend', note);
            input.addEventListener('input', update);
            update();
        });
    };
});
