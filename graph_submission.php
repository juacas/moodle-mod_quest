<?php
// This file is part of Questournament activity for Moodle http://moodle.org/
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


/** Questournament activity for Moodle: Draw the scoring graph.
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */
require_once('../../config.php');
global $CFG;
require_once('locallib.php');
require_once('classes/quest_graphlib.php');
/**
 * Calculate scoring.
 * @param unknown $timenow
 * @param unknown $datestart
 * @param unknown $dateend
 * @param unknown $tinitial
 * @param unknown $dateanswercorrect
 * @param unknown $initialpoints
 * @param unknown $pointsmax
 * @param unknown $type
 * @return number
 */
function quest_calculate_pointsc($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax, $type) {
    if (($dateend - $datestart - $tinitial) == 0) {
        $incline = 0;
    } else {
        if ($type == 0) {
            $incline = ($pointsmax - $initialpoints) / ($dateend - $datestart - $tinitial);
        } else {
            if ($initialpoints == 0) {
                $initialpoints = 0.0001;
            }
            $incline = (1 / ($dateend - $datestart - $tinitial)) * log($pointsmax / $initialpoints);
        }
    }

    if ($datestart > $timenow) {
        $grade = $initialpoints;
    } else if ($dateend < $timenow) {
        $grade = 0;
    } else if ($timenow < $datestart + $tinitial && ($timenow <= $dateanswercorrect || $dateanswercorrect == 0)) {
        $grade = $initialpoints;
    } else if ($dateanswercorrect == 0 || $timenow <= $dateanswercorrect) {
        // There is no inflexion point...
        $t = $timenow - $datestart;
        if ($type == 0) {
            $grade = ($t - $tinitial) * $incline + $initialpoints;
        } else {
            $grade = $initialpoints * exp($incline * ($t - $tinitial));
        }
    } else { // It is in decreasing zone...
        $t = $timenow - $dateanswercorrect;
        if ($dateanswercorrect < $datestart + $tinitial) {
            if ($type == 0) {
                $pointscorrect = $initialpoints;
                $incline2 = $pointscorrect / ($dateend - $dateanswercorrect);
                $grade = $pointscorrect - $incline2 * $t;

            } else {
                // TODO Check this. WARNING THIS MAY NOT WORK PROPERTLY.
                $grade = $initialpoints * exp($incline * ($dateanswercorrect - $datestart - $tinitial));
                $incline2 = (1 / ($dateend - $dateanswercorrect)) * log(0.0001 / $pointscorrect);
                $grade = $pointsanswercorrect * exp($incline2 * $t);
            }
        } else { // After tinitial...
            if ($type == 0) {
                $pointscorrect = $incline * ($dateanswercorrect - $datestart - $tinitial) + $initialpoints;
                $incline2 = $pointscorrect / ($dateend - $dateanswercorrect);
                $grade = $pointscorrect - $incline2 * $t;
            } else {
                $grade = $initialpoints * exp($incline * ($dateanswercorrect - $datestart - $tinitial));
                $incline2 = (1 / ($dateend - $dateanswercorrect)) * log(0.0001 / $pointscorrect);
                $grade = $pointsanswercorrect * exp($incline2 * $t);
            }
        }
    }
    if ($grade < 0) {
        $grade = 0;
    }
    return $grade;
}

/**
 * Sustituida por la version reducida anterior (no probada con type!=0.
 * @param unknown $timenow
 * @param unknown $datestart
 * @param unknown $dateend
 * @param unknown $tinitial
 * @param unknown $dateanswercorrect
 * @param unknown $initialpoints
 * @param unknown $pointsmax
 * @param unknown $type
 * @return number
 */
function quest_calculate_pointsb($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax, $type) {
    if (!$type) {
        $type = 0;
    }
    if ($dateanswercorrect != 0) {
        $nanswerscorrect = 1;
    }
    if (($dateend - $datestart - $tinitial) == 0) {
        $incline = 0;
    } else {
        if ($type == 0) {
            $incline = ($pointsmax - $initialpoints) / ($dateend - $datestart - $tinitial);
        } else {
            if ($initialpoints == 0) {
                $initialpoints = 0.0001;
            }
            $incline = (1 / ($dateend - $datestart - $tinitial)) * log($pointsmax / $initialpoints);
        }
    }

    if ($datestart > $timenow) {
        $grade = $initialpoints;
    } else {

        if ($dateend < $timenow) {
            if ($nanswerscorrect == 0) {
                $t = $dateend - $datestart;
                if ($t <= $tinitial) {
                    $grade = $initialpoints;
                } else {
                    $grade = $pointsmax;
                }
            } else {
                $grade = 0;
            }
        } else {
            if ($nanswerscorrect == 0) {
                $t = $timenow - $datestart;
                if ($t < $tinitial) {
                    $grade = $initialpoints;
                } else {
                    if ($t >= ($dateend - $datestart)) {
                        $grade = $pointsmax;
                    } else {
                        if ($type == 0) {
                            $grade = ($t - $tinitial) * $incline + $initialpoints;
                        } else {
                            $grade = $initialpoints * exp($incline * ($t - $tinitial));
                        }
                    }
                }
            } else {
                if ($timenow < $dateanswercorrect) {

                    $t = $timenow - $datestart;
                    if ($t < $tinitial) {
                        $grade = $initialpoints;
                    } else {
                        if ($t >= ($dateend - $datestart)) {
                            $grade = $pointsmax;
                        } else {
                            if ($type == 0) {
                                $grade = ($t - $tinitial) * $incline + $initialpoints;
                            } else {
                                $grade = $initialpoints * exp($incline * ($t - $tinitial));
                            }
                        }
                    }
                } else {
                    $t = $timenow - $dateanswercorrect;
                    if ($type == 0) {
                        $pointsanswercorrect = ($dateanswercorrect - $datestart - $tinitial) * $incline + $initialpoints;
                    } else {
                        $pointsanswercorrect = $initialpoints * exp($incline * ($dateanswercorrect - $datestart - $tinitial));
                    }

                    if (($dateend - $dateanswercorrect) == 0) {
                        $incline = 0;
                    } else {
                        if ($type == 0) {
                            $incline = (-$pointsanswercorrect) / ($dateend - $dateanswercorrect);
                        } else {
                            $incline = (1 / ($dateend - $dateanswercorrect)) * log(0.0001 / $pointsanswercorrect);
                        }
                    }
                    if ($type == 0) {
                        $grade = $pointsanswercorrect + $incline * $t;
                    } else {

                        $grade = $pointsanswercorrect * exp($incline * $t);
                    }
                }
            }
        }
    }

    if ($grade < 0) {
        $grade = 0;
    }
    return $grade;
}
/**
 *
 */
function graph_submissions() {

    $datestart = required_param('dst', PARAM_INT);
    $dateend = required_param('dend', PARAM_INT);
    $tinit = required_param('tinit', PARAM_INT);
    $initialpoints = required_param('ipoints', PARAM_INT);
    $dateanswercorrect = required_param('daswcorr', PARAM_INT);
    $datefirstanswer = required_param('dfirstansw', PARAM_INT);
    $pointsmax = required_param('pointsmax', PARAM_INT);
    $pointsmin = optional_param('pointsmin', 0, PARAM_INT);
    $width = optional_param('width', 300, PARAM_INT);
    $height = optional_param('height', 200, PARAM_INT);

    $mygraph = new quest_graph($width, $height);
    $mygraph->parameter['title'] = get_string("questgraphtitle", 'quest');
    $mygraph->parameter['output_format'] = 'PNG';
    $mygraph->parameter['x_label'] = userdate($datestart, '%x') .
            '  <---->  ' . userdate($dateend, '%x');
    $mygraph->parameter['y_label_left'] = get_string("questgraphYlegend", 'quest');
    $mygraph->parameter['x_label_angle'] = 0;
    $mygraph->parameter['inner_border_type'] = 'axis';
    $mygraph->parameter['outer_background'] = 'ltltorange';
    $mygraph->parameter['x_axis_gridlines'] = 6;
    $mygraph->parameter['x_min'] = $datestart;
    $mygraph->parameter['x_max'] = $dateend;
    $mygraph->parameter['y_max_left'] = $pointsmax != $pointsmin ? $pointsmax : $pointsmax * 1.5;
    $mygraph->parameter['y_min_left'] = 0;
    $mygraph->parameter['x_label_date'] = 1;
    $mygraph->parameter['outer_background'] = "none";

    $mygraph->y_tick_labels = null;

    // Singular points.
    $timenow = time();
    $dates = [$datestart, $datestart + $tinit,  $dateend];
    $line1 = [];
    $line2 = [];
    $datesinflexion = [];

    if ($datefirstanswer == 0) {
        $datefirstanswer = PHP_INT_MAX;
    }
    if ($dateanswercorrect == 0) {
        $dateanswercorrect = PHP_INT_MAX;
    }
    if ($datefirstanswer > $datestart && $datefirstanswer < $dateend) {
        $dates[] = $datefirstanswer;
        $datesinflexion[] = $datefirstanswer;
    }
    if ($dateanswercorrect < $dateend && $dateanswercorrect > $datestart) {
        $dates[] = $dateanswercorrect;
        $datesinflexion[] = $dateanswercorrect;
    }

    $drawworstcase = count($datesinflexion) > 0;
    $dateinflexion = $drawworstcase ? min($datesinflexion) : 0;
    sort($dates);
    foreach ($dates as $date) {
        $line2[] = quest_calculate_points($date, $datestart, $dateend, $tinit, $dateanswercorrect,
                                        $initialpoints, $pointsmax, $pointsmin);
        if ($drawworstcase) {
            $line1[] = quest_calculate_points($date, $datestart, $dateend, $tinit, $dateinflexion,
                                        $initialpoints, $pointsmax, $pointsmin);
        }
    }
    if ($drawworstcase) {
        $mygraph->y_data['line1'] = $line1;
        $mygraph->y_format['line1'] = array('colour' => 'red', 'line' => 'line', 'legend' => '');
        $mygraph->y_order[] = 'line1';
    }

    $mygraph->x_data = $dates;
    $mygraph->y_data['line2'] = $line2;
    $mygraph->y_format['line2'] = array('colour' => 'blue', 'line' => 'line', 'legend' => '');
    $mygraph->y_order[] = 'line2';

    $mygraph->draw_stack();
    $xtoday = $mygraph->get_x_point($timenow);
    /*
     * Decorate graph adding references.
     */
    $label2 = $mygraph->calculated['x_label'];

    $mygraph->line($xtoday, 0, $xtoday,
            $mygraph->calculated['boundary_box']['bottom'] - $mygraph->calculated['boundary_box']['top'],
            'dash', 'circle', 1, 'black', 0);

    $todaymaxpoints = quest_calculate_points($timenow, $datestart, $dateend, $tinit, $dateanswercorrect,
                                            $initialpoints, $pointsmax, $pointsmin);

    $ymaxpointstoday = $mygraph->get_y_point($todaymaxpoints);
    $coords = array('x' => $xtoday - 16, 'y' => 24, 'reference' => 'top-left');
    $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
    $label2['text'] = get_string("today", 'quest');
    $mygraph->print_TTF($label2);

    $coords = array('x' => $xtoday + 2, 'y' => $ymaxpointstoday - 14, 'reference' => 'top-left');
    $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
    $label2['text'] = "Max:" . number_format($todaymaxpoints, 2);
    $mygraph->print_TTF($label2);

    if ($drawworstcase) {
        $todayminpoints = quest_calculate_points($timenow, $datestart, $dateend, $tinit, $dateinflexion,
                                                $initialpoints, $pointsmax, $pointsmin);
        $yminpointstoday = $mygraph->get_y_point($todayminpoints);
        $coords = array('x' => $xtoday + 2, 'y' => $yminpointstoday, 'reference' => 'top-left');
        $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
        $label2['text'] = "Min:" . number_format($todayminpoints, 2);
        $mygraph->print_TTF($label2);

        if (count($datesinflexion) > 1) {
            $inflexionpoints = quest_calculate_points($dateinflexion, $datestart, $dateend, $tinit, $dateinflexion,
                                                $initialpoints, $pointsmax, $pointsmin);
            $yinflexionpoint = $mygraph->get_y_point($inflexionpoints);
            $xinflexionpoint = $mygraph->get_x_point($dateinflexion);
            $coords = array('x' => $xinflexionpoint + 2, 'y' => $yinflexionpoint, 'reference' => 'top-left');
            $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
            $label2['text'] = get_string('answers', 'quest');
            $mygraph->print_TTF($label2);
        }
    }

    $mygraph->output();
} // ...end graph_submissions.
require_login($COURSE);
graph_submissions();