// This file is part of Questournament activity for Moodle - http://moodle.org/
//
// Questournament for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Questournament for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Interactive timeline calendar for scheduling and reordering Quest challenges.
 *
 * @module     mod_quest/schedule_calendar
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification'], function(Notification) {
    'use strict';

    /**
     * Format a unix timestamp (seconds) into a readable localized date and time string.
     *
     * @param {number} sec Unix timestamp in seconds.
     * @param {string} [locale] Language/locale code.
     * @return {string}
     */
    function formatDateTime(sec, locale) {
        var d = new Date(sec * 1000);
        var loc = locale || (typeof M !== 'undefined' && M.cfg && M.cfg.lang) || document.documentElement.lang || undefined;
        try {
            return new Intl.DateTimeFormat(loc, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(d);
        } catch (e) {
            return d.toLocaleString(loc);
        }
    }

    /**
     * Format a duration in seconds into a friendly string.
     *
     * @param {number} sec Duration in seconds.
     * @return {string}
     */
    function formatDuration(sec) {
        var days = Math.floor(sec / 86400);
        var hours = Math.round((sec % 86400) / 3600);
        if (days > 0 && hours > 0) {
            return days + 'd ' + hours + 'h';
        } else if (days > 0) {
            return days + 'd';
        }
        return Math.max(1, hours) + 'h';
    }

    /**
     * Convert a Unix timestamp to the value expected by a datetime-local input.
     *
     * @param {number} sec Unix timestamp in seconds.
     * @return {string} Local datetime value.
     */
    function timestampToDateTimeLocal(sec) {
        var d = new Date(sec * 1000);
        var year = d.getFullYear();
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        var hour = String(d.getHours()).padStart(2, '0');
        var minute = String(d.getMinutes()).padStart(2, '0');
        return year + '-' + month + '-' + day + 'T' + hour + ':' + minute;
    }

    /**
     * Convert a datetime-local value to a Unix timestamp.
     *
     * @param {string} value Local datetime value.
     * @return {number} Unix timestamp in seconds.
     */
    function dateTimeLocalToTimestamp(value) {
        if (!value) {
            return 0;
        }
        var parts = value.split('T');
        if (parts.length !== 2) {
            return 0;
        }
        var dateParts = parts[0].split('-');
        var timeParts = parts[1].split(':');
        var date = new Date(
            parseInt(dateParts[0], 10),
            parseInt(dateParts[1], 10) - 1,
            parseInt(dateParts[2], 10),
            parseInt(timeParts[0], 10),
            parseInt(timeParts[1], 10),
            0
        );
        return Math.floor(date.getTime() / 1000);
    }

    /**
     * Get weekend days for the given locale.
     * Returns an array of ISO day numbers: 1 (Monday) to 7 (Sunday).
     *
     * @param {string} [locale] BCP47 locale or Moodle language code.
     * @return {Array<number>}
     */
    function getLocaleWeekendDays(locale) {
        if (typeof Intl !== 'undefined' && Intl.Locale) {
            try {
                var tag = String(locale || 'default').replace(/_/g, '-');
                var loc = new Intl.Locale(tag);
                var info = loc.weekInfo || (loc.getWeekInfo ? loc.getWeekInfo() : null);
                if (info && Array.isArray(info.weekend) && info.weekend.length > 0) {
                    return info.weekend;
                }
            } catch (e) {
                // Fallback to default weekend.
            }
        }
        return [6, 7];
    }

    /**
     * Check whether a given Date is on a weekend according to weekend days.
     *
     * @param {Date} date Date instance.
     * @param {Array<number>} weekendDays Array of ISO day numbers (1 = Mon ... 7 = Sun).
     * @return {boolean}
     */
    function isDateWeekend(date, weekendDays) {
        var day = date.getDay();
        var isoDay = (day === 0) ? 7 : day;
        return weekendDays.indexOf(isoDay) !== -1;
    }

    /**
     * ScheduleCalendar manager object.
     */
    var ScheduleCalendar = {
        config: null,
        challenges: [],
        initialChallenges: [],
        isDirty: false,
        activeDrag: null,
        trackWidthPx: 1200,
        locale: undefined,

        /**
         * Initialize calendar timeline.
         *
         * @param {Object} cfg Configuration object from PHP.
         */
        init: function(cfg) {
            this.config = cfg;
            this.locale = cfg.lang || (typeof M !== 'undefined' && M.cfg && M.cfg.lang) ||
                document.documentElement.lang || undefined;
            var dataEl = document.getElementById('quest-schedule-data');
            var rawChallenges = cfg.challenges;
            if (!rawChallenges && dataEl) {
                try {
                    rawChallenges = JSON.parse(dataEl.textContent);
                } catch (e) {
                    rawChallenges = [];
                }
            }
            this.challenges = JSON.parse(JSON.stringify(rawChallenges || []));
            this.initialChallenges = JSON.parse(JSON.stringify(rawChallenges || []));

            this.board = document.getElementById('quest-timeline-board');
            this.wrapper = document.getElementById('quest-timeline-wrapper');
            this.hud = document.getElementById('quest-timeline-hud');
            this.saveBtn = document.getElementById('quest-schedule-save-btn');
            this.dirtyBadge = document.getElementById('quest-schedule-dirty-badge');
            this.statusMsg = document.getElementById('quest-schedule-status-msg');
            this.snapSelect = document.getElementById('quest-schedule-snap');
            this.autoSeqBtn = document.getElementById('quest-schedule-autosequence');
            this.resetBtn = document.getElementById('quest-schedule-reset');

            if (!this.board || !this.challenges.length) {
                return;
            }

            // Adjust default snap if period <= 7 days
            var totalSec = Math.max(3600, this.config.questEnd - this.config.questStart);
            var totalDays = totalSec / 86400;
            if (totalDays <= 7 && this.snapSelect) {
                this.snapSelect.innerHTML = '<option value="3600" selected>1 hour</option>' +
                    '<option value="14400">4 hours</option>' +
                    '<option value="43200">12 hours</option>' +
                    '<option value="86400">1 day</option>';
            }

            this.renderTimeline();
            this.bindEvents();
            this.scrollToInitialFocus();
        },

        /**
         * Generate the 3-level timeline bands.
         *
         * @param {number} qStartSec Tournament start timestamp.
         * @param {number} qEndSec Tournament end timestamp.
         * @return {Object} Bands configuration with b1, b2, b3 arrays.
         */
        calculateBands: function(qStartSec, qEndSec) {
            var self = this;
            var totalSec = Math.max(3600, qEndSec - qStartSec);
            var totalDays = totalSec / 86400;
            var isShort = totalDays <= 7;
            var weekendDays = getLocaleWeekendDays(self.locale);
            var b1 = [], b2 = [], b3 = [];

            if (!isShort) {
                // Case: Period > 7 days.
                // Level 1: AÑO (Year)
                var y0 = new Date(qStartSec * 1000).getFullYear();
                var y1 = new Date(qEndSec * 1000).getFullYear();
                for (var y = y0; y <= y1; y++) {
                    var yStart = Math.max(qStartSec, Math.floor(new Date(y, 0, 1, 0, 0, 0).getTime() / 1000));
                    var yEnd = Math.min(qEndSec, Math.floor(new Date(y + 1, 0, 1, 0, 0, 0).getTime() / 1000));
                    if (yEnd > yStart) {
                        b1.push({
                            label: String(y),
                            left: ((yStart - qStartSec) / totalSec) * 100,
                            width: ((yEnd - yStart) / totalSec) * 100
                        });
                    }
                }

                // Level 2: MES (Month)
                var dCur = new Date(qStartSec * 1000);
                var curY = dCur.getFullYear();
                var curM = dCur.getMonth();
                while (new Date(curY, curM, 1, 0, 0, 0).getTime() / 1000 < qEndSec) {
                    var mStart = Math.max(qStartSec, Math.floor(new Date(curY, curM, 1, 0, 0, 0).getTime() / 1000));
                    var mEnd = Math.min(qEndSec, Math.floor(new Date(curY, curM + 1, 1, 0, 0, 0).getTime() / 1000));
                    if (mEnd > mStart) {
                        var mDate = new Date(curY, curM, 1);
                        var mName = mDate.toLocaleDateString(self.locale, {month: 'short'});
                        b2.push({
                            label: mName,
                            left: ((mStart - qStartSec) / totalSec) * 100,
                            width: ((mEnd - mStart) / totalSec) * 100
                        });
                    }
                    curM++;
                    if (curM > 11) {
                        curM = 0;
                        curY++;
                    }
                }

                // Level 3: DÍA (Day) - grouped according to scale, no hours.
                var stepDays = 1;
                if (totalDays > 365 * 3) {
                    stepDays = 30; // ~monthly ticks for multi-year
                } else if (totalDays > 365) {
                    stepDays = 14; // fortnightly for 1-3 years
                } else if (totalDays > 90) {
                    stepDays = 7;  // weekly for 3-12 months
                } else if (totalDays > 31) {
                    stepDays = 2;  // 2 days for 1-3 months
                } else {
                    stepDays = 1;  // daily for <= 31 days
                }

                var dayPointer = new Date(qStartSec * 1000);
                dayPointer.setHours(0, 0, 0, 0);

                while (dayPointer.getTime() / 1000 < qEndSec) {
                    var nextDay = new Date(dayPointer.getTime() + stepDays * 86400 * 1000);
                    var dStart = Math.max(qStartSec, Math.floor(dayPointer.getTime() / 1000));
                    var dEnd = Math.min(qEndSec, Math.floor(nextDay.getTime() / 1000));

                    if (dEnd > dStart) {
                        var isWkDay = (stepDays === 1) ? isDateWeekend(dayPointer, weekendDays) : false;
                        var dLabel = '';
                        if (stepDays === 1) {
                            dLabel = dayPointer.getDate();
                        } else if (stepDays <= 7) {
                            dLabel = dayPointer.getDate();
                        } else {
                            dLabel = dayPointer.toLocaleDateString(self.locale, {day: 'numeric', month: 'numeric'});
                        }
                        b3.push({
                            label: String(dLabel),
                            left: ((dStart - qStartSec) / totalSec) * 100,
                            width: ((dEnd - dStart) / totalSec) * 100,
                            isWeekend: isWkDay
                        });
                    }
                    dayPointer = nextDay;
                }
            } else {
                // Case: Period <= 7 days.
                // Level 1: Año / Mes
                var dtS = new Date(qStartSec * 1000);
                var dtE = new Date(qEndSec * 1000);
                var strM1 = dtS.toLocaleDateString(self.locale, {month: 'long', year: 'numeric'});
                var strM2 = dtE.toLocaleDateString(self.locale, {month: 'long', year: 'numeric'});
                var topLabel = (strM1 === strM2) ? strM1 : (strM1 + ' - ' + strM2);
                b1.push({
                    label: topLabel,
                    left: 0,
                    width: 100
                });

                // Level 2: Día (Calendar days)
                var dPtr = new Date(qStartSec * 1000);
                dPtr.setHours(0, 0, 0, 0);
                while (dPtr.getTime() / 1000 < qEndSec) {
                    var dNextP = new Date(dPtr.getTime() + 86400 * 1000);
                    var dayS = Math.max(qStartSec, Math.floor(dPtr.getTime() / 1000));
                    var dayE = Math.min(qEndSec, Math.floor(dNextP.getTime() / 1000));
                    if (dayE > dayS) {
                        var dayName = dPtr.toLocaleDateString(self.locale, {weekday: 'short', day: 'numeric'});
                        var isWk = isDateWeekend(dPtr, weekendDays);
                        b2.push({
                            label: dayName,
                            left: ((dayS - qStartSec) / totalSec) * 100,
                            width: ((dayE - dayS) / totalSec) * 100,
                            isWeekend: isWk
                        });
                    }
                    dPtr = dNextP;
                }

                // Level 3: Hora (Hours according to scale)
                var stepHours = 1;
                if (totalDays > 3) {
                    stepHours = 6;
                } else if (totalDays > 1) {
                    stepHours = 4;
                } else {
                    stepHours = 2;
                }

                var hPtr = new Date(qStartSec * 1000);
                var remH = hPtr.getHours() % stepHours;
                hPtr.setHours(hPtr.getHours() - remH, 0, 0, 0);

                while (hPtr.getTime() / 1000 < qEndSec) {
                    var hNextP = new Date(hPtr.getTime() + stepHours * 3600 * 1000);
                    var hS = Math.max(qStartSec, Math.floor(hPtr.getTime() / 1000));
                    var hE = Math.min(qEndSec, Math.floor(hNextP.getTime() / 1000));
                    if (hE > hS) {
                        var hLabel = hPtr.toLocaleTimeString(self.locale, {hour: '2-digit', minute: '2-digit', hour12: false});
                        var isHWk = isDateWeekend(hPtr, weekendDays);
                        b3.push({
                            label: hLabel,
                            left: ((hS - qStartSec) / totalSec) * 100,
                            width: ((hE - hS) / totalSec) * 100,
                            isWeekend: isHWk
                        });
                    }
                    hPtr = hNextP;
                }
            }

            // Compute contiguous weekend blocks for track backdrop shading.
            var weekends = [];
            var curD = new Date(qStartSec * 1000);
            curD.setHours(0, 0, 0, 0);

            var inWeekend = false;
            var wBlockStart = 0;
            var wBlockEnd = 0;

            while (curD.getTime() / 1000 < qEndSec) {
                var nxtD = new Date(curD.getTime() + 86400 * 1000);
                var curDs = Math.max(qStartSec, Math.floor(curD.getTime() / 1000));
                var curDe = Math.min(qEndSec, Math.floor(nxtD.getTime() / 1000));
                var isCurW = isDateWeekend(curD, weekendDays);

                if (isCurW) {
                    if (!inWeekend) {
                        inWeekend = true;
                        wBlockStart = curDs;
                        wBlockEnd = curDe;
                    } else {
                        wBlockEnd = curDe;
                    }
                } else {
                    if (inWeekend) {
                        weekends.push({
                            left: ((wBlockStart - qStartSec) / totalSec) * 100,
                            width: ((wBlockEnd - wBlockStart) / totalSec) * 100
                        });
                        inWeekend = false;
                    }
                }
                curD = nxtD;
            }
            if (inWeekend) {
                weekends.push({
                    left: ((wBlockStart - qStartSec) / totalSec) * 100,
                    width: ((wBlockEnd - wBlockStart) / totalSec) * 100
                });
            }

            return {
                b1: b1,
                b2: b2,
                b3: b3,
                isShort: isShort,
                weekends: weekends
            };
        },

        /**
         * Render the timeline board with the 3-level bands header and challenge tracks.
         */
        renderTimeline: function() {
            var self = this;
            var qStart = self.config.questStart;
            var qEnd = self.config.questEnd;
            var totalSec = Math.max(3600, qEnd - qStart);

            var bands = self.calculateBands(qStart, qEnd);

            // Compute track pixel width to ensure base units have at least 36px
            var baseCount = Math.max(10, bands.b3.length);
            var wrapperWidth = self.wrapper ? (self.wrapper.clientWidth - 220) : 1000;
            self.trackWidthPx = Math.max(wrapperWidth, baseCount * 36);

            var board = document.createElement('div');
            board.className = 'quest-timeline-table d-flex';

            // Left column: Sticky challenge labels
            var leftCol = document.createElement('div');
            leftCol.className = 'quest-timeline-left-column flex-shrink-0';
            leftCol.style.width = '220px';

            // Left header
            var leftHeader = document.createElement('div');
            leftHeader.className =
                'quest-left-header p-2 border-bottom border-end bg-light d-flex flex-column justify-content-center';
            leftHeader.style.height = '84px';
            leftHeader.innerHTML = '<div class="fw-bold small text-dark"><i class="fa fa-flag-checkered me-1 text-primary"></i>' +
                ' Challenges (' + self.challenges.length + ')</div>' +
                '<div class="smaller text-muted">Drag bars to adjust schedule</div>';
            leftCol.appendChild(leftHeader);

            // Left rows
            self.challenges.forEach(function(c) {
                var rowLabel = document.createElement('div');
                rowLabel.className =
                    'quest-left-row p-2 border-bottom border-end bg-white d-flex flex-column justify-content-center';
                rowLabel.setAttribute('data-cid', c.id);
                var dur = formatDuration(c.dateend - c.datestart);
                rowLabel.innerHTML = '<div class="quest-left-title fw-bold small text-dark"' +
                    ' title="' + self.escapeHtml(c.title) + '">' +
                    self.escapeHtml(c.title) + '</div>' +
                    '<div class="quest-left-meta d-flex justify-content-between align-items-center smaller text-muted">' +
                    '<span class="quest-left-author text-truncate me-1">' + self.escapeHtml(c.author || '') + '</span>' +
                    '<span class="badge quest-duration-badge quest-lbl-dur">' + dur + '</span></div>';
                leftCol.appendChild(rowLabel);
            });
            board.appendChild(leftCol);

            // Right column: Horizontal scrollable track area
            var rightArea = document.createElement('div');
            rightArea.className = 'quest-timeline-right-area flex-grow-1 position-relative';
            rightArea.style.width = self.trackWidthPx + 'px';
            rightArea.style.minWidth = self.trackWidthPx + 'px';

            // 3-Level Bands Header
            var bandsHeader = document.createElement('div');
            bandsHeader.className = 'quest-bands-header border-bottom position-relative';
            bandsHeader.style.height = '84px';

            // Band 1: Level 1 (Year or Month/Year)
            var band1Row = document.createElement('div');
            band1Row.className = 'quest-band-row quest-band-l1 position-relative';
            band1Row.style.height = '28px';
            bands.b1.forEach(function(item) {
                var cell = document.createElement('div');
                cell.className = 'quest-band-cell quest-cell-l1 fw-bold text-center position-absolute border-end';
                cell.style.left = item.left + '%';
                cell.style.width = item.width + '%';
                cell.textContent = item.label;
                band1Row.appendChild(cell);
            });
            bandsHeader.appendChild(band1Row);

            // Band 2: Level 2 (Month or Day)
            var band2Row = document.createElement('div');
            band2Row.className = 'quest-band-row quest-band-l2 position-relative';
            band2Row.style.height = '28px';
            bands.b2.forEach(function(item) {
                var cell = document.createElement('div');
                cell.className = 'quest-band-cell quest-cell-l2 text-center position-absolute border-end';
                if (item.isWeekend) {
                    cell.className += ' quest-cell-weekend';
                }
                cell.style.left = item.left + '%';
                cell.style.width = item.width + '%';
                cell.textContent = item.label;
                band2Row.appendChild(cell);
            });
            bandsHeader.appendChild(band2Row);

            // Band 3: Level 3 (Day or Hour)
            var band3Row = document.createElement('div');
            band3Row.className = 'quest-band-row quest-band-l3 position-relative';
            band3Row.style.height = '28px';
            bands.b3.forEach(function(item) {
                var cell = document.createElement('div');
                cell.className = 'quest-band-cell quest-cell-l3 text-center position-absolute border-end';
                if (item.isWeekend) {
                    cell.className += ' quest-cell-weekend';
                }
                cell.style.left = item.left + '%';
                cell.style.width = item.width + '%';
                cell.textContent = item.label;
                band3Row.appendChild(cell);
            });
            bandsHeader.appendChild(band3Row);

            rightArea.appendChild(bandsHeader);

            // Track lanes for each challenge
            self.challenges.forEach(function(c) {
                var lane = document.createElement('div');
                lane.className = 'quest-timeline-lane position-relative border-bottom';
                lane.setAttribute('data-cid', c.id);

                // Shaded weekend backdrop blocks
                bands.weekends.forEach(function(wk) {
                    var wkBlock = document.createElement('div');
                    wkBlock.className = 'quest-lane-weekend position-absolute';
                    wkBlock.style.left = wk.left + '%';
                    wkBlock.style.width = wk.width + '%';
                    lane.appendChild(wkBlock);
                });

                // Vertical background grid matching base units
                bands.b3.forEach(function(tick) {
                    var gridLine = document.createElement('div');
                    gridLine.className = 'quest-lane-grid-tick position-absolute border-end';
                    if (tick.isWeekend) {
                        gridLine.className += ' quest-lane-grid-weekend';
                    }
                    gridLine.style.left = tick.left + '%';
                    gridLine.style.width = tick.width + '%';
                    lane.appendChild(gridLine);
                });

                // Challenge Draggable Bar
                var bar = self.createBarElement(c, totalSec, qStart);
                lane.appendChild(bar);

                rightArea.appendChild(lane);
            });

            board.appendChild(rightArea);

            self.board.innerHTML = '';
            self.board.appendChild(board);
            self.syncRowHeights();
        },

        /**
         * Match each timeline lane to the rendered height of its label content.
         *
         * @return {void}
         */
        syncRowHeights: function() {
            var self = this;
            var rows = self.board.querySelectorAll('.quest-left-row');

            rows.forEach(function(row) {
                var cid = row.getAttribute('data-cid');
                var lane = self.board.querySelector('.quest-timeline-lane[data-cid="' + cid + '"]');

                row.style.height = 'auto';
                if (lane) {
                    lane.style.height = 'auto';
                }

                var rowHeight = Math.ceil(row.getBoundingClientRect().height);
                var laneHeight = lane ? Math.ceil(lane.getBoundingClientRect().height) : 0;
                var height = Math.max(rowHeight, laneHeight);

                row.style.height = height + 'px';
                if (lane) {
                    lane.style.height = height + 'px';
                }
            });
        },

        /**
         * Create a draggable/resizable bar DOM element for a challenge.
         *
         * @param {Object} c Challenge data.
         * @param {number} totalSec Total tournament seconds.
         * @param {number} qStart Tournament start timestamp.
         * @return {HTMLElement}
         */
        createBarElement: function(c, totalSec, qStart) {
            // Respect actual real range of the challenge
            var leftPct = Math.max(0, Math.min(100, ((c.datestart - qStart) / totalSec) * 100));
            var rightPct = Math.max(0, Math.min(100, ((c.dateend - qStart) / totalSec) * 100));
            var widthPct = Math.max(0.4, rightPct - leftPct);

            var bar = document.createElement('div');
            bar.className = 'quest-calendar-bar shadow-sm';
            bar.setAttribute('data-cid', c.id);
            bar.style.left = leftPct + '%';
            bar.style.width = widthPct + '%';

            // Left resize handle
            var handleStart = document.createElement('div');
            handleStart.className = 'quest-bar-handle handle-start';
            handleStart.setAttribute('title', 'Drag to change start date');
            bar.appendChild(handleStart);

            // Center body
            var body = document.createElement('div');
            body.className = 'quest-bar-body d-flex align-items-center justify-content-between px-2 text-truncate';
            var durStr = formatDuration(c.dateend - c.datestart);
            body.innerHTML = '<span class="quest-bar-title fw-bold text-truncate small me-2">' +
                this.escapeHtml(c.title) + '</span>' +
                '<span class="quest-bar-badge badge quest-duration-badge smaller">' + durStr + '</span>';
            bar.appendChild(body);

            // Right resize handle
            var handleEnd = document.createElement('div');
            handleEnd.className = 'quest-bar-handle handle-end';
            handleEnd.setAttribute('title', 'Drag to change end date');
            bar.appendChild(handleEnd);

            return bar;
        },

        /**
         * Scroll horizontally on load so the first challenge is in view.
         */
        scrollToInitialFocus: function() {
            var self = this;
            if (!self.wrapper || !self.challenges.length) {
                return;
            }
            var qStart = self.config.questStart;
            var qEnd = self.config.questEnd;
            var totalSec = Math.max(3600, qEnd - qStart);

            // Find earliest challenge start
            var minStart = self.challenges.reduce(function(acc, c) {
                return Math.min(acc, c.datestart);
            }, self.challenges[0].datestart);

            var leftPct = Math.max(0, ((minStart - qStart) / totalSec));
            var targetPx = (leftPct * self.trackWidthPx) - 60;

            if (targetPx > 20) {
                setTimeout(function() {
                    self.wrapper.scrollLeft = targetPx;
                }, 100);
            }
        },

        /**
         * Bind UI and Pointer events.
         */
        bindEvents: function() {
            var self = this;

            // Pointer drag events on board (event delegation)
            self.board.addEventListener('pointerdown', function(e) {
                var handle = e.target.closest('.quest-bar-handle');
                var bar = e.target.closest('.quest-calendar-bar');
                if (!bar) {
                    return;
                }

                var cid = parseInt(bar.getAttribute('data-cid'), 10);
                var challenge = self.challenges.find(function(item) {
                    return item.id === cid;
                });
                if (!challenge) {
                    return;
                }

                var mode = 'move';
                if (handle && handle.classList.contains('handle-start')) {
                    mode = 'resize-start';
                } else if (handle && handle.classList.contains('handle-end')) {
                    mode = 'resize-end';
                }

                var lane = bar.closest('.quest-timeline-lane');
                var laneRect = lane.getBoundingClientRect();

                self.clickCandidate = {
                    cid: cid,
                    startX: e.clientX,
                    startY: e.clientY,
                    hasMoved: false,
                    startTime: Date.now()
                };

                bar.setPointerCapture(e.pointerId);
                bar.classList.add('is-dragging');

                self.activeDrag = {
                    pointerId: e.pointerId,
                    cid: cid,
                    challenge: challenge,
                    mode: mode,
                    startX: e.clientX,
                    origStart: challenge.datestart,
                    origEnd: challenge.dateend,
                    trackWidth: laneRect.width,
                    barElem: bar,
                    laneElem: lane
                };

                self.showHud(e.clientX, e.clientY, challenge.title, challenge.datestart, challenge.dateend);
                e.preventDefault();
            });

            self.board.addEventListener('pointermove', function(e) {
                if (self.clickCandidate && !self.clickCandidate.hasMoved) {
                    var moveDistance = Math.hypot(
                        e.clientX - self.clickCandidate.startX,
                        e.clientY - self.clickCandidate.startY
                    );
                    if (moveDistance > 4) {
                        self.clickCandidate.hasMoved = true;
                    }
                }

                if (!self.activeDrag) {
                    return;
                }
                var act = self.activeDrag;
                var qStart = self.config.questStart;
                var qEnd = self.config.questEnd;
                var totalSec = Math.max(3600, qEnd - qStart);

                var deltaX = e.clientX - act.startX;
                var deltaSec = (deltaX / act.trackWidth) * totalSec;

                // Snapping
                var snapVal = parseInt(self.snapSelect.value, 10) || 86400;
                deltaSec = Math.round(deltaSec / snapVal) * snapVal;

                var newStart = act.origStart;
                var newEnd = act.origEnd;
                var minDur = 3600; // minimum 1 hour

                if (act.mode === 'move') {
                    var dur = act.origEnd - act.origStart;
                    newStart = act.origStart + deltaSec;
                    newEnd = act.origEnd + deltaSec;

                    if (newStart < qStart) {
                        newStart = qStart;
                        newEnd = newStart + dur;
                    }
                    if (newEnd > qEnd) {
                        newEnd = qEnd;
                        newStart = Math.max(qStart, newEnd - dur);
                    }
                } else if (act.mode === 'resize-start') {
                    newStart = Math.min(act.origEnd - minDur, Math.max(qStart, act.origStart + deltaSec));
                    newEnd = act.origEnd;
                } else if (act.mode === 'resize-end') {
                    newStart = act.origStart;
                    newEnd = Math.max(act.origStart + minDur, Math.min(qEnd, act.origEnd + deltaSec));
                }

                // Update visual bar
                var leftPct = Math.max(0, Math.min(100, ((newStart - qStart) / totalSec) * 100));
                var rightPct = Math.max(0, Math.min(100, ((newEnd - qStart) / totalSec) * 100));
                var widthPct = Math.max(0.4, rightPct - leftPct);

                act.barElem.style.left = leftPct + '%';
                act.barElem.style.width = widthPct + '%';

                // Update challenge memory
                act.challenge.datestart = newStart;
                act.challenge.dateend = newEnd;

                // Update HUD
                self.showHud(e.clientX, e.clientY, act.challenge.title, newStart, newEnd);
            });

            var onPointerEnd = function(e) {
                if (!self.activeDrag || self.activeDrag.pointerId !== e.pointerId) {
                    return;
                }
                var act = self.activeDrag;
                var wasSimpleClick = self.clickCandidate &&
                    !self.clickCandidate.hasMoved &&
                    (Date.now() - self.clickCandidate.startTime < 500);
                try {
                    act.barElem.releasePointerCapture(e.pointerId);
                } catch (err) {
                    // Pointer capture may have already been released.
                }
                act.barElem.classList.remove('is-dragging');

                // Update badge inside bar and left column.
                var durStr = formatDuration(act.challenge.dateend - act.challenge.datestart);
                var badge = act.barElem.querySelector('.quest-bar-badge');
                if (badge) {
                    badge.textContent = durStr;
                }

                var leftRow = self.board.querySelector('.quest-left-row[data-cid="' + act.challenge.id + '"] .quest-lbl-dur');
                if (leftRow) {
                    leftRow.textContent = durStr;
                }

                // Update table row.
                self.updateTableRow(act.challenge);

                self.hideHud();
                self.activeDrag = null;
                self.clickCandidate = null;

                if (wasSimpleClick) {
                    self.scrollToTableRow(act.cid);
                }

                if (act.challenge.datestart !== act.origStart || act.challenge.dateend !== act.origEnd) {
                    self.setDirty(true);
                }
            };

            self.board.addEventListener('pointerup', onPointerEnd);
            self.board.addEventListener('pointercancel', function() {
                self.clickCandidate = null;
                if (!self.activeDrag) {
                    return;
                }
                self.activeDrag.barElem.classList.remove('is-dragging');
                self.activeDrag = null;
                self.hideHud();
            });

            // Auto-sequence button.
            if (self.autoSeqBtn) {
                self.autoSeqBtn.addEventListener('click', function() {
                    self.autoSequence();
                });
            }

            // Strategy modal interactions.
            var strategyItems = document.querySelectorAll(
                '#quest-autosequence-strategy-list .list-group-item'
            );
            strategyItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    var strategy = item.getAttribute('data-strategy');
                    var radio = item.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                    strategyItems.forEach(function(el) {
                        el.classList.remove('active');
                    });
                    item.classList.add('active');

                    document.querySelectorAll('.quest-strategy-desc-content').forEach(function(panel) {
                        panel.classList.add('d-none');
                    });
                    var activeDesc = document.getElementById('quest-strategy-desc-' + strategy);
                    if (activeDesc) {
                        activeDesc.classList.remove('d-none');
                    }
                });
            });

            var applyAutoSequenceBtn = document.getElementById('quest-autosequence-apply');
            if (applyAutoSequenceBtn) {
                applyAutoSequenceBtn.addEventListener('click', function() {
                    var selectedRadio = document.querySelector(
                        'input[name="quest_autosequence_strategy"]:checked'
                    );
                    var strategy = selectedRadio ? selectedRadio.value : 'equal';
                    self.applyAutoSequence(strategy);
                    self.closeAutoSequenceModal();
                });
            }

            var cancelAutoSequenceBtn = document.getElementById('quest-autosequence-cancel');
            if (cancelAutoSequenceBtn) {
                cancelAutoSequenceBtn.addEventListener('click', function() {
                    self.closeAutoSequenceModal();
                });
            }

            var autoSequenceCloseBtn = document.querySelector('#quest-autosequence-modal .btn-close');
            if (autoSequenceCloseBtn) {
                autoSequenceCloseBtn.addEventListener('click', function() {
                    self.closeAutoSequenceModal();
                });
            }

            // Reset button
            if (self.resetBtn) {
                self.resetBtn.addEventListener('click', function() {
                    self.resetSchedule();
                });
            }

            // Save button
            if (self.saveBtn) {
                self.saveBtn.addEventListener('click', function() {
                    self.saveSchedule();
                });
            }

            // A simple click on a Gantt label jumps to and highlights its table row.
            self.board.addEventListener('click', function(e) {
                var leftRow = e.target.closest('.quest-left-row');
                if (leftRow) {
                    self.scrollToTableRow(leftRow.getAttribute('data-cid'));
                }
            });

            // Date cells open the same datetime-local editor used by local_reschedule.
            var scheduleTable = document.getElementById('quest-schedule-table');
            if (scheduleTable) {
                scheduleTable.addEventListener('click', function(e) {
                    var dateCell = e.target.closest('.quest-col-datestart, .quest-col-dateend');
                    if (!dateCell) {
                        return;
                    }
                    e.preventDefault();
                    var row = dateCell.closest('tr[data-cid]');
                    if (row) {
                        self.openDateModal(
                            row.getAttribute('data-cid'),
                            dateCell.classList.contains('quest-col-dateend') ? 'end' : 'start'
                        );
                    }
                });

                scheduleTable.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter' && e.key !== ' ') {
                        return;
                    }
                    var dateCell = e.target.closest('.quest-col-datestart, .quest-col-dateend');
                    if (!dateCell) {
                        return;
                    }
                    e.preventDefault();
                    var row = dateCell.closest('tr[data-cid]');
                    if (row) {
                        self.openDateModal(
                            row.getAttribute('data-cid'),
                            dateCell.classList.contains('quest-col-dateend') ? 'end' : 'start'
                        );
                    }
                });
            }

            var modal = document.getElementById('quest-date-modal');
            var modalStart = document.getElementById('quest-date-modal-start');
            var modalEnd = document.getElementById('quest-date-modal-end');
            var modalDuration = document.getElementById('quest-date-modal-duration');
            var modalError = document.getElementById('quest-date-modal-error');

            var validateModalDates = function() {
                var start = dateTimeLocalToTimestamp(modalStart && modalStart.value);
                var end = dateTimeLocalToTimestamp(modalEnd && modalEnd.value);
                var valid = start > 0 && end > start;
                if (modalDuration && valid) {
                    modalDuration.textContent = formatDuration(end - start);
                }
                if (modalError) {
                    modalError.classList.toggle('d-none', valid);
                }
                if (modalEnd) {
                    modalEnd.classList.toggle('is-invalid', !valid && end > 0);
                }
                return valid;
            };

            if (modalStart) {
                modalStart.addEventListener('input', validateModalDates);
                modalStart.addEventListener('change', validateModalDates);
            }
            if (modalEnd) {
                modalEnd.addEventListener('input', validateModalDates);
                modalEnd.addEventListener('change', validateModalDates);
            }
            var applyModalButton = document.getElementById('quest-date-modal-apply');
            if (applyModalButton) {
                applyModalButton.addEventListener('click', function() {
                    self.applyDateModalChanges();
                });
            }
            var cancelModalButton = document.getElementById('quest-date-modal-cancel');
            if (cancelModalButton) {
                cancelModalButton.addEventListener('click', function() {
                    self.closeDateModal();
                });
            }
            var closeModalButton = modal ? modal.querySelector('.btn-close') : null;
            if (closeModalButton) {
                closeModalButton.addEventListener('click', function() {
                    self.closeDateModal();
                });
            }

            window.addEventListener('beforeunload', function(e) {
                if (self.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        /**
         * Show floating HUD above current pointer position.
         *
         * @param {number} x Screen clientX.
         * @param {number} y Screen clientY.
         * @param {string} title Challenge title.
         * @param {number} startSec Start timestamp.
         * @param {number} endSec End timestamp.
         */
        showHud: function(x, y, title, startSec, endSec) {
            if (!this.hud) {
                return;
            }
            this.hud.querySelector('.quest-hud-title').textContent = title;
            this.hud.querySelector('.quest-hud-start').textContent = formatDateTime(startSec, this.locale);
            this.hud.querySelector('.quest-hud-end').textContent = formatDateTime(endSec, this.locale);
            this.hud.querySelector('.quest-hud-dur').textContent = 'Duration: ' + formatDuration(endSec - startSec);

            var wrapper = document.getElementById('quest-timeline-wrapper');
            var wrapRect = wrapper.getBoundingClientRect();
            var hudX = (x - wrapRect.left) + 15;
            var hudY = (y - wrapRect.top) - 60;

            if (hudX + 260 > wrapRect.width) {
                hudX = wrapRect.width - 270;
            }
            if (hudY < 10) {
                hudY = 10;
            }

            this.hud.style.left = hudX + 'px';
            this.hud.style.top = hudY + 'px';
            this.hud.classList.remove('d-none');
        },

        /**
         * Hide floating HUD.
         */
        hideHud: function() {
            if (this.hud) {
                this.hud.classList.add('d-none');
            }
        },

        /**
         * Scroll to and highlight the corresponding challenge row.
         *
         * @param {number|string} cid Challenge ID.
         */
        scrollToTableRow: function(cid) {
            var row = document.querySelector('#quest-schedule-table tr[data-cid="' + cid + '"]');
            if (!row) {
                return;
            }
            row.scrollIntoView({behavior: 'smooth', block: 'center'});
            row.classList.remove('quest-row-highlight');
            void row.offsetWidth;
            row.classList.add('quest-row-highlight');
            setTimeout(function() {
                row.classList.remove('quest-row-highlight');
            }, 2000);
        },

        /**
         * Open the challenge date editor.
         *
         * @param {number|string} cid Challenge ID.
         * @param {string} fieldToFocus Date field to focus.
         */
        openDateModal: function(cid, fieldToFocus) {
            var self = this;
            var challenge = self.challenges.find(function(item) {
                return String(item.id) === String(cid);
            });
            var modal = document.getElementById('quest-date-modal');
            if (!challenge || !modal) {
                return;
            }

            var cidInput = document.getElementById('quest-date-modal-cid');
            var title = document.getElementById('quest-date-modal-title');
            var author = document.getElementById('quest-date-modal-author');
            var duration = document.getElementById('quest-date-modal-duration');
            var error = document.getElementById('quest-date-modal-error');
            var start = document.getElementById('quest-date-modal-start');
            var end = document.getElementById('quest-date-modal-end');

            if (cidInput) {
                cidInput.value = challenge.id;
            }
            if (title) {
                title.textContent = challenge.title;
            }
            if (author) {
                author.textContent = challenge.author || '';
            }
            if (duration) {
                duration.textContent = formatDuration(challenge.dateend - challenge.datestart);
            }
            if (start) {
                start.value = timestampToDateTimeLocal(challenge.datestart);
                start.classList.remove('is-invalid');
            }
            if (end) {
                end.value = timestampToDateTimeLocal(challenge.dateend);
                end.classList.remove('is-invalid');
            }
            if (error) {
                error.classList.add('d-none');
            }

            if (window.bootstrap && window.bootstrap.Modal) {
                var instance = typeof window.bootstrap.Modal.getOrCreateInstance === 'function' ?
                    window.bootstrap.Modal.getOrCreateInstance(modal) :
                    (window.bootstrap.Modal.getInstance(modal) || new window.bootstrap.Modal(modal));
                instance.show();
            } else if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
                window.jQuery(modal).modal('show');
            } else {
                modal.classList.add('show');
                modal.style.display = 'block';
                modal.removeAttribute('aria-hidden');
                modal.setAttribute('aria-modal', 'true');
                if (!document.getElementById('quest-date-modal-backdrop')) {
                    var backdrop = document.createElement('div');
                    backdrop.id = 'quest-date-modal-backdrop';
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }

            setTimeout(function() {
                var target = fieldToFocus === 'end' ? end : start;
                if (target) {
                    target.focus();
                }
            }, 250);
        },

        /**
         * Close the challenge date editor.
         */
        closeDateModal: function() {
            var modal = document.getElementById('quest-date-modal');
            if (!modal) {
                return;
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                var instance = window.bootstrap.Modal.getInstance(modal);
                if (instance) {
                    instance.hide();
                }
            } else if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
                window.jQuery(modal).modal('hide');
            } else {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                var backdrop = document.getElementById('quest-date-modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        },

        /**
         * Apply changes made in the challenge date editor.
         */
        applyDateModalChanges: function() {
            var self = this;
            var cidInput = document.getElementById('quest-date-modal-cid');
            var startInput = document.getElementById('quest-date-modal-start');
            var endInput = document.getElementById('quest-date-modal-end');
            var error = document.getElementById('quest-date-modal-error');
            if (!cidInput || !startInput || !endInput) {
                return;
            }

            var challenge = self.challenges.find(function(item) {
                return String(item.id) === String(cidInput.value);
            });
            if (!challenge) {
                return;
            }

            var newStart = dateTimeLocalToTimestamp(startInput.value);
            var newEnd = dateTimeLocalToTimestamp(endInput.value);
            if (!newStart || !newEnd || newEnd <= newStart) {
                if (error) {
                    error.classList.remove('d-none');
                }
                endInput.classList.add('is-invalid');
                return;
            }

            challenge.datestart = newStart;
            challenge.dateend = newEnd;

            var totalSec = Math.max(3600, self.config.questEnd - self.config.questStart);
            var startPct = Math.max(0, Math.min(100,
                ((newStart - self.config.questStart) / totalSec) * 100));
            var endPct = Math.max(0, Math.min(100,
                ((newEnd - self.config.questStart) / totalSec) * 100));
            var bar = self.board.querySelector('.quest-calendar-bar[data-cid="' + challenge.id + '"]');
            if (bar) {
                bar.style.left = startPct + '%';
                bar.style.width = Math.max(0.4, endPct - startPct) + '%';
                var badge = bar.querySelector('.quest-bar-badge');
                if (badge) {
                    badge.textContent = formatDuration(newEnd - newStart);
                }
            }

            self.updateTableRow(challenge);
            self.setDirty(true);
            self.closeDateModal();
            self.scrollToTableRow(challenge.id);
        },

        /**
         * Update corresponding row in the detail table.
         *
         * @param {Object} c Challenge data.
         */
        updateTableRow: function(c) {
            var self = this;
            var row = document.getElementById('quest-row-' + c.id);
            if (!row) {
                return;
            }
            var startEl = row.querySelector('.quest-tbl-start');
            var endEl = row.querySelector('.quest-tbl-end');
            var durEl = row.querySelector('.quest-tbl-dur');

            if (startEl) {
                startEl.textContent = formatDateTime(c.datestart, self.locale);
            }
            if (endEl) {
                endEl.textContent = formatDateTime(c.dateend, self.locale);
            }
            if (durEl) {
                durEl.textContent = formatDuration(c.dateend - c.datestart);
            }
        },

        /**
         * Open the strategy chooser before auto-sequencing.
         */
        autoSequence: function() {
            this.openAutoSequenceModal();
        },

        /**
         * Open the auto-sequence strategy modal.
         */
        openAutoSequenceModal: function() {
            var modalEl = document.getElementById('quest-autosequence-modal');
            if (!modalEl) {
                return;
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = typeof window.bootstrap.Modal.getOrCreateInstance === 'function' ?
                    window.bootstrap.Modal.getOrCreateInstance(modalEl) :
                    (window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl));
                modal.show();
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('show');
            } else {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                if (!document.getElementById('quest-autosequence-modal-backdrop')) {
                    var backdrop = document.createElement('div');
                    backdrop.id = 'quest-autosequence-modal-backdrop';
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }
        },

        /**
         * Close the auto-sequence strategy modal.
         */
        closeAutoSequenceModal: function() {
            var modalEl = document.getElementById('quest-autosequence-modal');
            if (!modalEl) {
                return;
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = window.bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('hide');
            } else {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                var backdrop = document.getElementById('quest-autosequence-modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        },

        /**
         * Auto-sequence challenges according to the selected strategy.
         *
         * @param {string} strategy 'equal', 'sequential', or 'proportional'
         */
        applyAutoSequence: function(strategy) {
            var self = this;
            var qStart = self.config.questStart;
            var qEnd = self.config.questEnd;
            var totalSec = Math.max(3600, qEnd - qStart);
            var count = self.challenges.length;

            if (count === 0) {
                return;
            }

            // Keep chronological ordering for all three strategies.
            self.challenges.sort(function(a, b) {
                return a.datestart - b.datestart;
            });

            if (strategy === 'equal') {
                var equalDuration = Math.max(3600, Math.floor(totalSec / count));
                var equalTime = qStart;
                self.challenges.forEach(function(challenge, index) {
                    challenge.datestart = equalTime;
                    challenge.dateend = (index === count - 1) ?
                        qEnd : Math.min(qEnd, equalTime + equalDuration);
                    equalTime = challenge.dateend;
                });
            } else if (strategy === 'sequential') {
                var sequentialTime = qStart;
                self.challenges.forEach(function(challenge) {
                    var duration = Math.max(3600, challenge.dateend - challenge.datestart);
                    challenge.datestart = sequentialTime;
                    challenge.dateend = sequentialTime + duration;
                    sequentialTime = challenge.dateend;
                });
            } else {
                var rawDurations = self.challenges.map(function(challenge) {
                    return Math.max(3600, challenge.dateend - challenge.datestart);
                });
                var averageDuration = rawDurations.reduce(function(total, duration) {
                    return total + duration;
                }, 0) / rawDurations.length;
                var maxAllowedDuration = Math.max(
                    3600 * 24,
                    Math.min(averageDuration * 3, totalSec * 0.4)
                );
                var cappedDurations = rawDurations.map(function(duration) {
                    return Math.min(duration, maxAllowedDuration);
                });
                var totalCappedDuration = cappedDurations.reduce(function(total, duration) {
                    return total + duration;
                }, 0);
                var proportionalTime = qStart;

                self.challenges.forEach(function(challenge, index) {
                    var weight = totalCappedDuration > 0 ?
                        cappedDurations[index] / totalCappedDuration : 1 / count;
                    var duration = Math.max(3600, Math.round(weight * totalSec));
                    challenge.datestart = proportionalTime;
                    challenge.dateend = (index === count - 1) ?
                        qEnd : Math.min(qEnd, proportionalTime + duration);
                    proportionalTime = challenge.dateend;
                });
            }

            self.renderTimeline();
            self.challenges.forEach(function(challenge) {
                self.updateTableRow(challenge);
            });
            self.setDirty(true);
            self.showStatus('Challenges auto-sequenced successfully.');
        },

        /**
         * Reset schedule back to initial values.
         */
        resetSchedule: function() {
            var self = this;
            self.challenges = JSON.parse(JSON.stringify(self.initialChallenges));
            self.challenges.forEach(function(c) {
                self.updateTableRow(c);
            });
            self.renderTimeline();
            self.setDirty(false);
            self.showStatus('Schedule reset to initial values.');
        },

        /**
         * Set dirty state and update action buttons.
         *
         * @param {boolean} dirty
         */
        setDirty: function(dirty) {
            this.isDirty = dirty;
            if (this.saveBtn) {
                this.saveBtn.disabled = !dirty;
            }
            if (this.dirtyBadge) {
                if (dirty) {
                    this.dirtyBadge.classList.remove('d-none');
                } else {
                    this.dirtyBadge.classList.add('d-none');
                }
            }
        },

        /**
         * Display status message banner.
         *
         * @param {string} msg
         */
        showStatus: function(msg) {
            if (this.statusMsg) {
                this.statusMsg.textContent = msg;
            }
        },

        /**
         * Save schedule via AJAX.
         */
        saveSchedule: function() {
            var self = this;
            if (!self.isDirty) {
                return;
            }

            self.saveBtn.disabled = true;
            var originalText = self.saveBtn.innerHTML;
            self.saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"' +
                ' role="status" aria-hidden="true"></span> Saving...';

            var payload = self.challenges.map(function(c) {
                return {
                    id: c.id,
                    datestart: c.datestart,
                    dateend: c.dateend
                };
            });

            var formData = new URLSearchParams();
            formData.append('sesskey', self.config.sesskey);
            formData.append('schedules', JSON.stringify(payload));

            fetch(self.config.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                self.saveBtn.innerHTML = originalText;
                if (data.success) {
                    self.initialChallenges = JSON.parse(JSON.stringify(self.challenges));
                    self.setDirty(false);
                    if (data.quest_updated) {
                        self.config.questStart = data.quest_datestart;
                        self.config.questEnd = data.quest_dateend;
                        self.renderTimeline();
                    }
                    self.showStatus(data.message || ('Schedule saved successfully (' + data.updated + ' challenges updated).'));
                } else {
                    self.saveBtn.disabled = false;
                    var errMsg = data.message || 'Failed to save schedule.';
                    if (data.errors && data.errors.length > 1) {
                        errMsg = data.errors.join('\n');
                    }
                    Notification.addNotification({
                        message: errMsg,
                        type: 'error'
                    });
                }
            })
            .catch(function(err) {
                self.saveBtn.innerHTML = originalText;
                self.saveBtn.disabled = false;
                Notification.exception(err);
            });
        },

        /**
         * Escape HTML string helper.
         *
         * @param {string} str
         * @return {string}
         */
        escapeHtml: function(str) {
            if (!str) {
                return '';
            }
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    return {
        init: function(config) {
            ScheduleCalendar.init(config);
        }
    };
});
