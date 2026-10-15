/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 *
 * "Why is this filter not showing?": runs the checks again for the picked store view and category.
 */
define(['jquery'], function ($) {
    'use strict';

    function item(check, options) {
        var li = document.createElement('li'),
            state = document.createElement('span'),
            title = document.createElement('strong'),
            link;

        li.className = 'of-why-check ' + (check.ok ? '_ok' : '_fail');
        li.setAttribute('data-of-check', check.code);
        state.className = 'of-why-state';
        state.textContent = check.ok ? options.okLabel : options.failLabel;
        title.textContent = check.title;
        li.appendChild(state);
        li.appendChild(title);
        [['detail', 'of-why-detail'], ['fix', 'of-why-fix']].forEach(function (part) {
            var span;

            if (check[part[0]]) {
                span = document.createElement('span');
                span.className = part[1];
                span.textContent = check[part[0]];
                li.appendChild(span);
            }
        });
        if (check.url) {
            link = document.createElement('a');
            link.className = 'of-why-link';
            link.href = check.url;
            link.textContent = options.linkLabel;
            li.appendChild(link);
        }

        return li;
    }

    /**
     * The panel is inserted by the form's Knockout template after page load, so the handler listens on the
     * document and reads its options from the panel.
     */
    return function () {
        var tries = 0,
            timer;

        // Opened from the grid's "Why is this filter not showing?" link: the panel is last in the form, bring it
        // into view once Knockout has inserted it.
        if (/\/why\/1(\/|$)/.test(window.location.pathname)) {
            timer = setInterval(function () {
                var panel = document.querySelector('.of-why');

                if (panel || ++tries > 50) {
                    clearInterval(timer);
                    if (panel) {
                        panel.scrollIntoView({block: 'start'});
                    }
                }
            }, 100);
        }
        $(document).on('click', '.of-why [data-of-why-run]', function () {
            var root = $(this).closest('.of-why'),
                list = root.find('[data-of-why-checks]')[0],
                options = root.data('ofWhy');

            $.ajax({
                url: options.url,
                type: 'GET',
                dataType: 'json',
                showLoader: true,
                data: {
                    store: root.find('[data-of-why-store]').val(),
                    category: root.find('[data-of-why-category]').val()
                }
            }).done(function (response) {
                var preview = root.find('[data-of-why-preview]')[0],
                    link;

                list.replaceChildren.apply(list, (response.checks || []).map(function (check) {
                    return item(check, options);
                }));
                preview.replaceChildren();
                if (response.preview) {
                    link = document.createElement('a');
                    link.href = response.preview;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = options.previewLabel;
                    preview.appendChild(link);
                }
            }).fail(function () {
                var li = document.createElement('li');

                li.className = 'of-why-check _fail';
                li.textContent = options.errorText;
                list.replaceChildren(li);
            });
        });
    };
});
