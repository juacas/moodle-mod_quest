// This file is part of Questournament activity for Moodle - http://moodle.org/
//
// Questournament for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Questournament for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

/**
 * Progressive datetime-local enhancement for the challenge editor.
 *
 * The Moodle date_time_selector remains in the form and is the submitted source
 * of truth. The modern input only mirrors its calendar components, avoiding any
 * browser-to-Moodle timezone conversion.
 *
 * @module     mod_quest/challenge_datetime
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    var dateparts = ['year', 'month', 'day', 'hour', 'minute'];

    /**
     * Pad a date component to two digits.
     *
     * @param {string|number} value Component value.
     * @return {string} Padded value.
     */
    function pad(value) {
        return String(value).padStart(2, '0');
    }

    /**
     * Find the Moodle selects belonging to a date_time_selector.
     *
     * @param {HTMLElement} fieldset Date fieldset.
     * @param {string} name Form field name.
     * @return {Object|null} Selects indexed by date component, or null.
     */
    function getControls(fieldset, name) {
        var controls = {};
        for (var i = 0; i < dateparts.length; i++) {
            var part = dateparts[i];
            controls[part] = fieldset.querySelector('[name="' + name + '[' + part + ']"]');
            if (!controls[part]) {
                return null;
            }
        }
        return controls;
    }

    /**
     * Build a datetime-local value from Moodle selector components.
     *
     * @param {Object} controls Moodle date selector controls.
     * @return {string} Datetime-local value.
     */
    function readControls(controls) {
        return controls.year.value + '-' + pad(controls.month.value) + '-' + pad(controls.day.value) +
            'T' + pad(controls.hour.value) + ':' + pad(controls.minute.value);
    }

    /**
     * Copy a datetime-local value into Moodle selector components.
     *
     * @param {Object} controls Moodle date selector controls.
     * @param {string} value Datetime-local value.
     */
    function writeControls(controls, value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value);
        if (!match) {
            return;
        }
        controls.year.value = String(parseInt(match[1], 10));
        controls.month.value = String(parseInt(match[2], 10));
        controls.day.value = String(parseInt(match[3], 10));
        controls.hour.value = String(parseInt(match[4], 10));
        controls.minute.value = String(parseInt(match[5], 10));
        controls.minute.dispatchEvent(new Event('change', {bubbles: true}));
    }

    /**
     * Replace one visible selector with a synchronized modern input.
     *
     * @param {Object} field Field definition.
     */
    function enhanceField(field) {
        var year = document.querySelector('[name="' + field.name + '[year]"]');
        if (!year) {
            return;
        }
        var fieldset = year.closest('fieldset[data-fieldtype="date_time"]');
        var selector = fieldset ? fieldset.querySelector('.fdate_time_selector') : null;
        if (!fieldset || !selector || fieldset.querySelector('.quest-modern-datetime-input')) {
            return;
        }

        var controls = getControls(fieldset, field.name);
        if (!controls) {
            return;
        }
        var input = document.createElement('input');
        input.type = 'datetime-local';
        if (input.type !== 'datetime-local') {
            return;
        }
        input.className = 'form-control quest-modern-datetime-input';
        input.id = fieldset.id + '-modern';
        input.step = '60';
        input.value = readControls(controls);
        input.min = field.min || '';
        input.max = field.max || '';

        var legend = fieldset.querySelector('legend');
        if (legend) {
            input.setAttribute('aria-label', legend.textContent.trim());
        }
        input.addEventListener('change', function() {
            writeControls(controls, input.value);
        });
        fieldset.addEventListener('change', function(event) {
            if (event.target !== input) {
                input.value = readControls(controls);
            }
        });

        selector.before(input);
        selector.classList.add('d-none');
        selector.hidden = true;
        selector.setAttribute('aria-hidden', 'true');
    }

    return {
        /**
         * Enhance the configured challenge date fields.
         *
         * @param {Object} config Module configuration.
         */
        init: function(config) {
            if (!config || !Array.isArray(config.fields)) {
                return;
            }
            config.fields.forEach(enhanceField);
        }
    };
});
