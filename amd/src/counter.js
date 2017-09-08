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
 * @module graphviz_social
 * @package mod_msocial/view/graphviz
 * @copyright 2017 Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
define(['jquery'], function ($) {

	var init = {
		puntuacionarray:  function (indice, incline, pointsmax, initialpoints, tinitial, datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios, type, nmaxanswers, pointsnmaxanswers,servertime, correccion) {
			puntuacionarray(indice, incline, pointsmax, initialpoints, tinitial, datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios, type, nmaxanswers, pointsnmaxanswers,servertime, correccion);
		},
	};
	return init;
});

function puntuacionarray( indice, incline, pointsmax, initialpoints, tinitial, datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios, type, nmaxanswers, pointsnmaxanswers,servertime, correccion) {
	var browserDate = new Date();
    var browserTime = browserDate.getTime();
    if (correccion == null) {
    	correccion = servertime - browserTime;
    }
    for (i = 0; i < indice; i++) {

        var tiempoactual = new Date();
        var tiempo = parseInt((tiempoactual.getTime() + correccion));
        var form = $(formularios[i]);
        if ((dateend[i] - datestart[i] - tinitial[i]) == 0) {
            incline[i] = 0;
        } else {
            if (type == 0) {
                incline[i] = (pointsmax[i] - initialpoints[i]) / (dateend[i] - datestart[i] - tinitial[i]);
            } else {
                if (initialpoints[i] == 0) {
                    initialpoints[i] = 0.0001;
                }
                incline[i] = (1 / (dateend[i] - datestart[i] - tinitial[i])) * Math.log(pointsmax[i] / initialpoints[i]);
            }
        }

        if (state[i] < 2) {
            grade = initialpoints[i];
            form.animate({color:"#cccccc"}, 1000);
        } else {

            if (datestart[i] > tiempo) {
                grade = initialpoints[i];
                form.animate({color:"#cccccc"}, 1000);
            }  else {
                if (nanswerscorrect[i] >= nmaxanswers) {
                    grade = 0;
                    form.animate({color:"#cccccc"}, 1000);
                }  else {
                    if (dateend[i] < tiempo) {
                        if (nanswerscorrect[i] == 0) {
                            t = dateend[i] - datestart[i];
                            if (t <= tinitial[i]) {
                                grade = initialpoints[i];
                                form.animate({color:"#cccccc"}, 1000);
                            } else {
                                grade = pointsmax[i];
                                form.animate({color:"#cccccc"}, 1000);
                            }
                        }  else {
                            grade = 0;
                            form.animate({color:"#cccccc"}, 1000);
                        }
                    } else {
                        if (nanswerscorrect[i] == 0) {
                            t = tiempo - datestart[i];
                            if (t < tinitial[i]) {
                                grade = initialpoints[i];
                                form.animate({color:"#000000"}, 1000);
                            } else {
                                if (t >= (dateend[i] - datestart[i])) {
                                    grade = pointsmax[i];
                                    form.animate({color:"#000000"}, 1000);
                                } else {
                                    if (type == 0) {
                                        grade = (t - tinitial[i]) * incline[i] + initialpoints[i];
	                                    form.animate({color:"#000000"}, 1000);
                                    } else {
                                        grade = initialpoints[i] * Math.exp(incline[i] * (t - tinitial[i]));
	                                    form.animate({color:"#000000"}, 1000);
                                    }
                                }
                            }
                        } else {
                            t = tiempo - dateanswercorrect[i];
                            if ((dateend[i] - dateanswercorrect[i]) == 0) {
                                incline[i] = 0;
                            } else {
                                if (type == 0) {
                                    incline[i] = (-pointsanswercorrect[i]) / (dateend[i] - dateanswercorrect[i]);
                                } else {
                                    incline[i] = (1 / (dateend[i] - dateanswercorrect[i])) * Math.log(0.0001 / pointsanswercorrect[i]);
                                }
                            }
                            if (type == 0) {
                                grade = pointsanswercorrect[i] + incline[i] * t;
                                form.animate({color:"#000000"}, 1000);
                            } else {
                                grade = pointsanswercorrect[i] * Math.exp(incline[i] * t);
                                form.animate({color:"#000000"}, 1000);
                            }
                        }
                    }
                }
            }
        }
        if (grade < 0) {
            grade = 0;
        }
        grade = redondear(grade, 4);
        form.val(grade);
    }

    setTimeout(function (){
    	puntuacionarray(indice,incline,pointsmax,initialpoints,tinitial,datestart,state,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers,null, correccion);
    	}, 1000);
}
function redondear(cantidad, decimales) {
	var cantidad = parseFloat(cantidad);
	var decimales = parseFloat(decimales);
	decimales = (!decimales ? 2 : decimales);
	var valor = Math.round(cantidad * Math.pow(10, decimales)) / Math.pow(10, decimales);
	return valor.toFixed(4);
}