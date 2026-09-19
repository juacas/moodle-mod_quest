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
 * Inline challenge date editor for the Quest detail table.
 *
 * @module     mod_quest/challenge_dates
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification'], function(Notification) {
    'use strict';

    /**
     * Convert a Unix timestamp to an input value.
     *
     * @param {number} timestamp Unix timestamp in seconds.
     * @return {string} Datetime-local input value.
     */
    function timestampToInput(timestamp) {
        var date = new Date(Number(timestamp) * 1000);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0') + 'T' + String(date.getHours()).padStart(2, '0') + ':' +
            String(date.getMinutes()).padStart(2, '0');
    }

    /**
     * Convert a local input value to a Unix timestamp.
     *
     * @param {string} value Datetime-local input value.
     * @return {number} Unix timestamp in seconds.
     */
    function inputToTimestamp(value) {
        if (!value) {
            return 0;
        }
        var parts = value.split('T');
        if (parts.length !== 2) {
            return 0;
        }
        var dateparts = parts[0].split('-');
        var timeparts = parts[1].split(':');
        var date = new Date(
            parseInt(dateparts[0], 10),
            parseInt(dateparts[1], 10) - 1,
            parseInt(dateparts[2], 10),
            parseInt(timeparts[0], 10),
            parseInt(timeparts[1], 10),
            0
        );
        return Math.floor(date.getTime() / 1000);
    }

    /**
     * Format a Unix timestamp for the current locale.
     *
     * @param {number} timestamp Unix timestamp in seconds.
     * @param {string} locale Locale identifier.
     * @return {string} Localized date and time.
     */
    function formatDate(timestamp, locale) {
        var date = new Date(Number(timestamp) * 1000);
        try {
            return new Intl.DateTimeFormat(locale || document.documentElement.lang || undefined, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(date);
        } catch (e) {
            return date.toLocaleString();
        }
    }

    /**
     * Find one of the editable date fields.
     *
     * @param {HTMLElement} table Challenge table.
     * @param {number|string} cid Challenge identifier.
     * @param {string} field Date field name.
     * @return {HTMLElement|null} Matching field or null.
     */
    function findField(table, cid, field) {
        var fields = table.querySelectorAll('.quest-view-date-field');
        for (var i = 0; i < fields.length; i++) {
            if (String(fields[i].dataset.cid) === String(cid) && fields[i].dataset.field === field) {
                return fields[i];
            }
        }
        return null;
    }

    /**
     * Resolve a click target to the editable date field.
     *
     * @param {Element} target Event target.
     * @return {HTMLElement|null} Matching date field or null.
     */
    function getDateField(target) {
        var field = target.closest('.quest-view-date-field, .quest-view-date-cell');
        if (field && field.classList.contains('quest-view-date-cell')) {
            field = field.querySelector('.quest-view-date-field');
        }
        return field;
    }

    /**
     * Return the Bootstrap modal instance when available.
     *
     * @param {HTMLElement} modal Modal element.
     * @return {Object|null} Modal instance or null.
     */
    function getModalInstance(modal) {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (window.bootstrap.Modal.getOrCreateInstance) {
                return window.bootstrap.Modal.getOrCreateInstance(modal);
            }
            return window.bootstrap.Modal.getInstance(modal) || new window.bootstrap.Modal(modal);
        }
        return null;
    }

    return {
        init: function(config) {
            var table = document.getElementById(config.tableId);
            var modal = document.getElementById(config.modalId);
            var saveButton = document.getElementById('quest-view-date-save-btn');
            var dirtyBadge = document.getElementById('quest-view-date-dirty-badge');
            var statusMessage = document.getElementById('quest-view-date-status-msg');
            var cidInput = document.getElementById('quest-view-date-modal-cid');
            var startInput = document.getElementById('quest-view-date-modal-start');
            var endInput = document.getElementById('quest-view-date-modal-end');
            var errorMessage = document.getElementById('quest-view-date-modal-error');
            var applyButton = document.getElementById('quest-view-date-modal-apply');
            var changes = {};
            var dirty = false;
            var modalInstance = modal ? getModalInstance(modal) : null;

            if (!table || !modal || !saveButton || !startInput || !endInput || !applyButton) {
                return;
            }

            /**
             * Update the dirty state and save controls.
             *
             * @param {boolean} value Whether changes are pending.
             */
            function setDirty(value) {
                dirty = value;
                saveButton.disabled = !value;
                if (dirtyBadge) {
                    dirtyBadge.classList.toggle('d-none', !value);
                }
            }

            /**
             * Display a status message below the save controls.
             *
             * @param {string} message Status text.
             */
            function showStatus(message) {
                if (statusMessage) {
                    statusMessage.textContent = message || '';
                }
            }

            /**
             * Open the date editor for one challenge.
             *
             * @param {HTMLElement} field Clicked date field.
             */
            function showModal(field) {
                var cid = field.dataset.cid;
                var startField = findField(table, cid, 'start');
                var endField = findField(table, cid, 'end');
                if (!startField || !endField) {
                    return;
                }
                cidInput.value = cid;
                startInput.value = timestampToInput(startField.dataset.date);
                endInput.value = timestampToInput(endField.dataset.date);
                errorMessage.classList.add('d-none');
                if (modalInstance) {
                    modalInstance.show();
                } else if (window.jQuery && window.jQuery.fn.modal) {
                    window.jQuery(modal).modal('show');
                }
            }

            /**
             * Update a displayed date field.
             *
             * @param {HTMLElement} field Date field element.
             * @param {number} timestamp New Unix timestamp.
             */
            function updateField(field, timestamp) {
                field.dataset.date = String(timestamp);
                field.textContent = formatDate(timestamp, config.locale);
            }

            /** Validate and stage the modal values. */
            function applyDates() {
                var cid = cidInput.value;
                var datestart = inputToTimestamp(startInput.value);
                var dateend = inputToTimestamp(endInput.value);
                if (!datestart || !dateend || dateend <= datestart) {
                    errorMessage.classList.remove('d-none');
                    return;
                }
                var startField = findField(table, cid, 'start');
                var endField = findField(table, cid, 'end');
                if (!startField || !endField) {
                    return;
                }
                changes[cid] = {id: Number(cid), datestart: datestart, dateend: dateend};
                updateField(startField, datestart);
                updateField(endField, dateend);
                errorMessage.classList.add('d-none');
                setDirty(true);
                showStatus('');
                if (modalInstance) {
                    modalInstance.hide();
                } else if (window.jQuery && window.jQuery.fn.modal) {
                    window.jQuery(modal).modal('hide');
                }
            }

            /** Persist all staged date changes. */
            function saveChanges() {
                var items = Object.keys(changes).map(function(cid) {
                    return changes[cid];
                });
                if (!items.length) {
                    setDirty(false);
                    return;
                }
                var originalText = saveButton.innerHTML;
                saveButton.disabled = true;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" ' +
                    'aria-hidden="true"></span> Saving...';
                fetch(config.saveUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({sesskey: config.sesskey, schedules: items})
                }).then(function(response) {
                    return response.text().then(function(text) {
                        var data = null;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            // Keep the server response useful when it is not JSON.
                        }
                        if (!data) {
                            throw new Error('Server returned HTTP ' + response.status);
                        }
                        return data;
                    });
                }).then(function(data) {
                    saveButton.innerHTML = originalText;
                    if (!data.success) {
                        saveButton.disabled = false;
                        throw new Error(data.message || 'Failed to save schedule.');
                    }
                    (data.schedules || []).forEach(function(saved) {
                        var startField = findField(table, saved.id, 'start');
                        var endField = findField(table, saved.id, 'end');
                        if (startField && endField) {
                            updateField(startField, Number(saved.datestart));
                            updateField(endField, Number(saved.dateend));
                        }
                    });
                    changes = {};
                    setDirty(false);
                    showStatus(data.message || 'Schedule saved successfully.');
                    Notification.addNotification({
                        message: data.message || 'Schedule saved successfully.',
                        type: 'success'
                    });
                }).catch(function(error) {
                    saveButton.innerHTML = originalText;
                    saveButton.disabled = false;
                    Notification.exception(error);
                });
            }

            table.addEventListener('click', function(event) {
                var field = getDateField(event.target);
                if (field) {
                    event.preventDefault();
                    showModal(field);
                }
            });
            table.addEventListener('keydown', function(event) {
                var field = getDateField(event.target);
                if (field && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                    showModal(field);
                }
            });
            applyButton.addEventListener('click', applyDates);
            saveButton.addEventListener('click', saveChanges);
            window.addEventListener('beforeunload', function(event) {
                if (dirty) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }
    };
});
