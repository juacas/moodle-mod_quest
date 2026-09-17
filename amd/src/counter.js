// This file is part of QUESTOURNAMENT activity for Moodle http://moodle.org/
//
// QUESTOURNAMENT for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// QUESTOURNAMENT for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
/**
 * @module quest_counter
 * @package mod_quest
 * @copyright 2017 Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

function redondear(cantidad, decimales) {
	var value = parseFloat(cantidad);
	var decimals = (typeof decimales !== 'undefined' && decimales !== null) ? parseInt(decimales, 10) : 4;
	var factor = Math.pow(10, decimals);
	var valor = Math.round(value * factor) / factor;
	return valor.toFixed(decimals);
}

/**
 * Calculate dynamic points for a challenge at a given timestamp.
 *
 * @param {number} timenow
 * @param {number} datestart
 * @param {number} dateend
 * @param {number} tinitial
 * @param {number} dateanswercorrect
 * @param {number} initialpoints
 * @param {number} pointsmax
 * @param {number} pointsmin
 * @param {number} type
 * @returns {number}
 */
function quest_calculate_points(timenow, datestart, dateend, tinitial, dateanswercorrect, initialpoints,
								pointsmax, pointsmin, type) {
    if (dateanswercorrect == 0) {
        dateanswercorrect = Number.MAX_SAFE_INTEGER; // This regularizes comparisons.
    }
    if (dateanswercorrect < datestart) {
        dateanswercorrect = datestart;
    }
    var zone, points;
    // Determine scoring zone.
    if (timenow >= dateend) {
        zone = 'ended';
    } else if (timenow > dateanswercorrect) {
       zone = 'deflaction';
    } else if (timenow < (datestart + tinitial)) {
        zone = 'stationary';
    } else if (timenow >= (datestart + tinitial)) {
        zone = 'inflaction';
    }
    // Only type 0 (linear) is supported.
    if (type != 0 && typeof(type) != 'undefined') {
    	return 0;
    }
    switch (zone) {
        case 'stationary': // Stationary score.
            points = initialpoints;
            break;
        case 'ended':
            if (dateanswercorrect <= dateend) {
                points = pointsmin;
            } else {
                points = pointsmax;
            }
            break;
        case 'inflaction': // Inflationary zone.
            var dt = timenow - (datestart + tinitial);
            points = dt * (pointsmax - initialpoints) / (dateend - datestart - tinitial) + initialpoints;
            break;
        case 'deflaction': // Deflationary score.
            var pointscorrect = quest_calculate_points(dateanswercorrect, datestart, dateend, tinitial, dateanswercorrect,
            		initialpoints, pointsmax, pointsmin);
            var incline2 = (pointscorrect - pointsmin) / (dateend - dateanswercorrect);
            points = pointscorrect - incline2 * (timenow - dateanswercorrect);
            break;
    }

    if (points < pointsmin) {
        points = pointsmin;
    }
    return points;
}

/**
 * Update all DOM elements with class .quest-score-counter or [data-quest-counter].
 * Reads challenge configuration from data attributes instead of large arguments arrays.
 *
 * @param {object} $ jQuery
 * @param {number|null} servertime
 * @param {number|null} correccion
 */
function updateDataCounters($, servertime, correccion) {
    var browserdate = new Date();
    var browsertime = browserdate.getTime() / 1000;
    if (correccion === null || typeof correccion === 'undefined') {
        correccion = (servertime ? parseFloat(servertime) : browsertime) - browsertime;
    }
    var tiempo = parseInt(browsertime + correccion, 10);

    $('.quest-score-counter, [data-quest-counter]').each(function() {
        var el = $(this);
        var datestart = parseFloat(el.attr('data-datestart') || el.data('datestart') || 0);
        var dateend = parseFloat(el.attr('data-dateend') || el.data('dateend') || 0);
        var tinitial = parseFloat(el.attr('data-tinitial') || el.data('tinitial') || 0);
        var dateanswercorrect = parseFloat(el.attr('data-dateanswercorrect') || el.data('dateanswercorrect') || 0);
        var initialpoints = parseFloat(el.attr('data-initialpoints') || el.data('initialpoints') || 0);
        var pointsmax = parseFloat(el.attr('data-pointsmax') || el.data('pointsmax') || 0);
        var pointsmin = parseFloat(el.attr('data-pointsmin') || el.data('pointsmin') || 0);
        var type = parseInt(el.attr('data-type') || el.data('type') || 0, 10);

        var grade = quest_calculate_points(tiempo, datestart, dateend, tinitial, dateanswercorrect,
                initialpoints, pointsmax, pointsmin, type);
        var decimals = el.attr('data-decimals');
        var dec = (typeof decimals !== 'undefined' && decimals !== '') ? parseInt(decimals, 10) : 4;
        grade = redondear(grade, dec);
        var prefix = el.attr('data-prefix') || '';
        var suffix = el.attr('data-suffix') || '';
        var fulltext = prefix + grade + suffix;
        if (el.is('input')) {
            el.val(fulltext);
        } else {
            el.text(fulltext);
        }
    });

    setTimeout(function() {
        updateDataCounters($, null, correccion);
    }, 1000);
}

/**
 * Legacy array-based counter updater (kept for backwards compatibility).
 */
function puntuacionarray($, indice, pointsmax, pointsmin, initialpoints, tinitial,
		datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend,
		formularios, type, nmaxanswers, pointsnmaxanswers, servertime, correccion) {
	var browserdate = new Date();
    var browsertime = browserdate.getTime() / 1000;
    if (correccion === null) {
		correccion = servertime - browsertime;
    }
    for (var i = 0; i < indice; i++) {
        var tiempo = parseInt(browsertime + correccion, 10);
        var form = $(formularios[i]);
        var grade = quest_calculate_points(tiempo, datestart[i], dateend[i], tinitial[i], dateanswercorrect[i],
        									initialpoints[i], pointsmax[i], pointsmin[i]);
        grade = redondear(grade, 4);
        form.val(grade);
    }

    setTimeout(function() {
    	puntuacionarray($, indice, pointsmax, pointsmin, initialpoints, tinitial,
    					datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect,
    					dateend, formularios, type, nmaxanswers, pointsnmaxanswers, null, correccion);
    }, 1000);
}

define(['jquery'], function($) {
    var initialized = false;

	return {
        /**
         * Initialize live counters using data-* attributes on DOM elements.
         *
         * @param {number} [servertime] Current server unix timestamp
         */
        init: function(servertime) {
            if (initialized) {
                return;
            }
            initialized = true;
            updateDataCounters($, servertime, null);
        },

        /**
         * Legacy entry point.
         */
		puntuacionarray: function(indice, pointsmax, pointsmin, initialpoints, tinitial, datestart,
									state, nanswerscorrect, dateanswercorrect, pointsanswercorrect,
									dateend, formularios, type, nmaxanswers, pointsnmaxanswers, servertime, correccion) {
			puntuacionarray($, indice, pointsmax, pointsmin, initialpoints, tinitial, datestart, state,
							nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios,
							type, nmaxanswers, pointsnmaxanswers, servertime, correccion);
		}
	};
});
