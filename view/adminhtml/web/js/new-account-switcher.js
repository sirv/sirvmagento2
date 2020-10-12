/**
 * New account switcher widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('sirv.newAccountSwitcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            if (this.element.attr('checked') == 'checked') {
                this._updatePlaceholders(this.element.attr('value') == 'no');
            }
            this.element.on('change', $.proxy(this._eventHandler, this));
        },

        /**
         * Update placeholders
         * @param {Boolean} isNewAccount - is new account
         */
        _updatePlaceholders: function (isNewAccount) {
            if (isNewAccount) {
                $('[name=magictoolbox\\[email\\]]').attr('placeholder', 'Your email address');
                $('[name=magictoolbox\\[password\\]]').attr('placeholder', 'Choose a password');
            } else {
                $('[name=magictoolbox\\[email\\]]').attr('placeholder', 'Sirv account email');
                $('[name=magictoolbox\\[password\\]]').attr('placeholder', 'Sirv account password');
            }
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            var fields = {
                    'first_and_last_name': 'yes',
                    'alias': 'yes',
                    'connect': 'no',
                    'register': 'yes'
                },
                value = this.element.attr('value'),
                disabled = (value == 'yes'),
                display;

            this._updatePlaceholders(value == 'no');

            for (name in fields) {
                display = fields[name] == value ? 'none' : 'block';
                $('.field-mt-' + name).css('display', display);
            }

            $('[name=magictoolbox\\[first_and_last_name\\]]').prop('disabled', disabled);
            $('[name=magictoolbox\\[alias\\]]').prop('disabled', disabled);
        }
    });

    return $.sirv.newAccountSwitcher;
});
