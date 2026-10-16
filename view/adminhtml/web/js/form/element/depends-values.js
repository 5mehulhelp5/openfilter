/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 *
 * Filter form > Where it shows: the values of the filter this one waits for (1.2). The list follows the "Show only
 * after a pick in" select without saving first; values of another filter are dropped. Hidden when the filter waits for
 * nothing or for a filter without values to choose (any pick then shows it).
 */
define(['Magento_Ui/js/form/element/ui-select'], function (UiSelect) {
    'use strict';

    return UiSelect.extend({
        defaults: {
            optionsByParent: {},
            parent: '',
            imports: {
                parent: '${ $.parentName }.depends_on:value'
            },
            listens: {
                parent: 'parentChanged'
            }
        },

        /**
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.parentChanged(this.parent);

            return this;
        },

        /**
         * @param {String} parent
         */
        parentChanged: function (parent) {
            var options = this.optionsByParent[parent] || [],
                allowed = options.map(function (option) {
                    return String(option.value);
                }),
                value = this.value();

            // ui-select filters and labels from its cache, so the cache follows the new list too.
            this.cacheOptions.plain = options;
            this.cacheOptions.tree = options;
            this.options(options);
            this.setCaption();
            if (Array.isArray(value)) {
                this.value(value.filter(function (item) {
                    return allowed.indexOf(String(item)) !== -1;
                }));
            }
            this.visible(options.length > 0);
        }
    });
});
