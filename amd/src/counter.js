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
	var decimals = parseFloat(decimales);
	decimals = (!decimals ? 2 : decimals);
	var valor = Math.round(value * Math.pow(10, decimals)) / Math.pow(10, decimals);
	return valor.toFixed(4);
}
/**
 * 
 * @param timenow
 * @param datestart
 * @param dateend
 * @param tinitial
 * @param dateanswercorrect
 * @param initialpoints
 * @param pointsmax
 * @param pointsmin
 * @param type
 * @returns
 */
function quest_calculate_points(timenow, datestart, dateend, tinitial, dateanswercorrect, initialpoints,
								pointsmax, pointsmin, type) {
    if (dateanswercorrect == 0) {
        dateanswercorrect = Number.MAX_SAFE_INTEGER; // This regularize comparisons.
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
        case 'inflaction': // Inflactionary zone.
            var dt = timenow - (datestart + tinitial);
            points = dt * (pointsmax - initialpoints) / (dateend - datestart - tinitial) + initialpoints;
            break;
        case 'deflaction': // Deflactionary score.
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
 * 
 * @param $ jquery dep
 * @param indice number of counters
 * @param incline 
 * @param pointsmax
 * @param pointsmin
 * @param initialpoints
 * @param tinitial stationary time (seconds)
 * @param datestart start time (seconds)
 * @param state
 * @param nanswerscorrect
 * @param dateanswercorrect
 * @param pointsanswercorrect
 * @param dateend end time (seconds)
 * @param formularios
 * @param type
 * @param nmaxanswers
 * @param pointsnmaxanswers
 * @param servertime  seconds
 * @param correccion seconds
 * @returns
 */
function puntuacionarray($, indice, pointsmax, pointsmin, initialpoints, tinitial,
		datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend,
		formularios, type, nmaxanswers, pointsnmaxanswers, servertime, correccion) {
	var browserdate = new Date();
    var browsertime = browserdate.getTime()/1000; // ...in seconds.
    if (correccion === null) {
		correccion = servertime - browsertime;
    }
    for (var i = 0; i < indice; i++) {

        var tiempo = parseInt((browsertime + correccion));
        var form = $(formularios[i]);
        var grade = quest_calculate_points(tiempo, datestart[i], dateend[i], tinitial[i], dateanswercorrect[i],
        									initialpoints[i], pointsmax[i], pointsmin[i]);
        grade = redondear(grade, 4);
        form.val(grade);
    }

    setTimeout(function (){
    	puntuacionarray($, indice, pointsmax, pointsmin, initialpoints, tinitial,
    					datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect,
    					dateend, formularios, type, nmaxanswers, pointsnmaxanswers, null, correccion);
    	}, 1000);
}

define(['jquery'], function($) {

	var init = {
		puntuacionarray:  function(indice, pointsmax, pointsmin, initialpoints, tinitial, datestart,
									state, nanswerscorrect, dateanswercorrect, pointsanswercorrect,
									dateend, formularios, type, nmaxanswers, pointsnmaxanswers, servertime, correccion) {
			puntuacionarray($, indice, pointsmax, pointsmin, initialpoints, tinitial, datestart, state,
							nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios,
							type, nmaxanswers, pointsnmaxanswers, servertime, correccion);
		},
	};
	return init;
});
