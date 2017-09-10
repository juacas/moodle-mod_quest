<?php
// This file is part of INTUITEL http://www.intuitel.eu as an adaptor for Moodle http://moodle.org/
//
// INTUITEL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// INTUITEL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with INTUITEL for Moodle Adaptor. If not, see <http://www.gnu.org/licenses/>.

/** Questournament activity for Moodle
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
 *          Draw the scoring graph */
require_once('../../config.php');
global $CFG;
require_once('lib.php');
require_once('vendor/graphlib.php');

function quest_calculate_points($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax, $type) {
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

// Sustituida por la version reducida anterior (no probada con type!=0.
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

function graph_submissions() {
    global $DB;
    $sid = required_param('sid', PARAM_INT);
    $datestart = required_param('dst', PARAM_INT);
    $dateend = required_param('dend', PARAM_INT);
    $tinit = required_param('tinit', PARAM_INT);
    $initialpoints = required_param('ipoints', PARAM_INT);
    $dateanswercorrect = required_param('daswcorr', PARAM_INT);
    $pointsmax = required_param('pointsmax', PARAM_INT);
    $timenow = time();
    $width = optional_param('width', 300, PARAM_INT);
    $height = optional_param('height', 200, PARAM_INT);

    $datefirstanswer = $DB->get_field("quest_answers", "min(date)", array("submissionid" => $sid));
    $mygraph = new graph($width, $height);
    $mygraph->parameter['title'] = get_string("questgraphtitle", 'quest');
    $mygraph->parameter['output_format'] = 'PNG';
    $mygraph->parameter['x_label'] = date('F', $datestart) . '-' . date('o', $datestart) .
            '  <---->  ' . date('F', $dateend) . '-' . date('o', $dateend);
    $mygraph->parameter['y_label_left'] = get_string("questgraphYlegend", 'quest');
    $mygraph->parameter['x_label_angle'] = 0;
    $mygraph->parameter['inner_border_type'] = 'axis';
    $mygraph->parameter['outer_background'] = 'ltltorange';
    $mygraph->parameter['x_axis_gridlines'] = 6;
    $mygraph->parameter['x_min'] = $datestart;
    $mygraph->parameter['x_max'] = $dateend;
    $mygraph->parameter['x_label_date'] = 1;
    $mygraph->parameter['outer_background'] = "none";
    $mygraph->y_tick_labels = null;

    // Adjust $tinit to avoid overflow challenge duration...

    if ($tinit + $datestart > $dateend) {
        $tinit = $dateend - $datestart;
    }

    if ($dateanswercorrect == 0) {
        if (empty($datefirstanswer) || $datefirstanswer == 0) {
            $points = quest_calculate_points($datestart, $datestart, $dateend, $tinit, $dateanswercorrect, $initialpoints,
                    $pointsmax, 0);

            $mygraph->x_data = array($datestart, $datestart + $tinit, $dateend);
            $mygraph->y_data['line1'] = array($initialpoints, $initialpoints, $pointsmax);
            $mygraph->y_data['line2'] = array($initialpoints, $initialpoints, $pointsmax);
        } else {
            if ($datefirstanswer < ($datestart + $tinit)) {
                $mygraph->x_data = array($datestart, $datefirstanswer, $datestart + $tinit, $dateend);
                $points = (($dateend * $initialpoints) - (($datestart + $tinit) * $initialpoints)) / ($dateend - $datefirstanswer);
                $mygraph->y_data['line1'] = array($initialpoints, $initialpoints, $points, 0);
                $mygraph->y_data['line2'] = array($initialpoints, $initialpoints, $initialpoints, $pointsmax);
            } else {
                $incline = ($pointsmax - $initialpoints) / ($dateend - ($datestart + $tinit));
                $points = $initialpoints + $incline * ($datefirstanswer - ($datestart + $tinit));
                $mygraph->x_data = array($datestart, $datestart + $tinit, $datefirstanswer, $dateend);
                $mygraph->y_data['line1'] = array($initialpoints, $initialpoints, $points, 0);
                $mygraph->y_data['line2'] = array($initialpoints, $initialpoints, $points, $pointsmax);
            }
        }
    } else { // End dateanswercorrect==0 ...
             // Then dateanswercorrect!=0 ...

        if (empty($datefirstanswer) || $datefirstanswer == 0 || $datefirstanswer > $dateanswercorrect) {
            $datefirstanswer = $dateanswercorrect; // ...should not be necessary.
        }

        if ($dateanswercorrect >= $datefirstanswer) {
            if ($dateanswercorrect <= ($datestart + $tinit) && $datefirstanswer <= ($datestart + $tinit)) {

                $mygraph->x_data = array($datestart, $datefirstanswer, $dateanswercorrect, $datestart + $tinit, $dateend);
                $mygraph->y_data['line1'] = array($initialpoints, $initialpoints,
                                quest_calculate_points($dateanswercorrect, $datestart, $dateend, $tinit, $dateanswercorrect,
                                        $initialpoints, $pointsmax, 0),
                                quest_calculate_points($datestart + $tinit, $datestart, $dateend, $tinit, $dateanswercorrect,
                                        $initialpoints, $pointsmax, 0), 0);
                $mygraph->y_data['line2'] = array($initialpoints, $initialpoints, $initialpoints,
                                quest_calculate_points($datestart + $tinit, $datestart, $dateend, $tinit, $datefirstanswer,
                                        $initialpoints, $pointsmax, 0), 0);
            } else if ($dateanswercorrect > ($datestart + $tinit) && $datefirstanswer < ($datestart + $tinit)) {
                $incline = ($pointsmax - $initialpoints) / ($dateend - ($datestart + $tinit));
                $points = $initialpoints + $incline * ($datefirstanswer - ($datestart + $tinit));
                $points = quest_calculate_points($dateanswercorrect, $datestart, $dateend, $tinit, $dateanswercorrect,
                        $initialpoints, $pointsmax, 0);
                $points3 = quest_calculate_points($datefirstanswer, $datestart, $dateend, $tinit, $datefirstanswer,
                        $initialpoints, $pointsmax, 0);
                $points4 = quest_calculate_points($datestart + $tinit + 1, $datestart, $dateend, $tinit, $datefirstanswer,
                        $initialpoints, $pointsmax, 0);
                $points5 = quest_calculate_points($dateanswercorrect, $datestart, $dateend, $tinit, $datefirstanswer,
                        $initialpoints, $pointsmax, 0);
                $mygraph->x_data = array($datestart, $datefirstanswer, $datestart + $tinit, $dateanswercorrect, $dateend);
                $mygraph->y_data['line1'] = array($initialpoints, $points3, $points4, $points5, 0);
                $mygraph->y_data['line2'] = array($initialpoints, $initialpoints, $initialpoints, $points, 0);
            } else if ($dateanswercorrect > ($datestart + $tinit) && $datefirstanswer > ($datestart + $tinit)) {
                $mygraph->x_data = array($datestart, $datestart + $tinit, $datefirstanswer, $dateanswercorrect, $dateend);
                $mygraph->y_data['line1'] = array($initialpoints, $initialpoints,
                                quest_calculate_points($datefirstanswer, $datestart, $dateend, $tinit, $dateanswercorrect,
                                        $initialpoints, $pointsmax, 0),
                                quest_calculate_points($dateanswercorrect, $datestart, $dateend, $tinit, $dateanswercorrect,
                                        $initialpoints, $pointsmax, 0), 0);
                $mygraph->y_data['line2'] = array($initialpoints, $initialpoints,
                                quest_calculate_points($datefirstanswer, $datestart, $dateend, $tinit, $datefirstanswer,
                                        $initialpoints, $pointsmax, 0),
                                quest_calculate_points($dateanswercorrect, $datestart, $dateend, $tinit, $datefirstanswer,
                                        $initialpoints, $pointsmax, 0), 0);
            }
        } else { // End dateanswercorrect > date_first_answer...
            // dateanswercorrect <= date_first_answer...
            if ($dateanswercorrect < ($datestart + $tinit)) {
                $mygraph->x_data = array($datestart, $dateanswercorrect, $datestart + $tinit, $dateend);
                $points = (($dateend * $initialpoints) - (($datestart + $tinit) * $initialpoints)) / ($dateend - $dateanswercorrect);
                $mygraph->y_data['line1'] = array($initialpoints, $initialpoints, $points, 0);
            } else {
                $mygraph->x_data = array($datestart, $datestart + $tinit, $dateanswercorrect, $dateend);
                $incline = ($pointsmax - $initialpoints) / ($dateend - ($datestart + $tinit));
                $points2 = $initialpoints + $incline * ($dateanswercorrect - ($datestart + $tinit));
                $mygraph->y_data['line1'] = array($initialpoints, $initialpoints, $points2, 0);
            }
        }

    }

    $mygraph->y_format['line2'] = array('colour' => 'blue', 'line' => 'line', 'legend' => '');
    $mygraph->y_format['line1'] = array('colour' => 'red', 'line' => 'line', 'legend' => '');

    $mygraph->y_order = array('line1', 'line2');
    $mygraph->y_max_left = $pointsmax;
    $mygraph->y_min_left = 0;

    $mygraph->draw_stack();
    $xtoday = $mygraph->get_x_point($timenow);
    /*
     * Decorate graph adding references.
     */
    $label2 = $mygraph->calculated['x_label'];

    $mygraph->line($xtoday, 0, $xtoday,
            $mygraph->calculated['boundary_box']['bottom'] - $mygraph->calculated['boundary_box']['top'],
            'dash', 'circle', 1, 'black', 0);

    $todaymaxpoints = quest_calculate_points($timenow, $datestart, $dateend, $tinit, $dateanswercorrect, $initialpoints, $pointsmax,
            0);

    $ymaxpointstoday = $mygraph->get_y_point($todaymaxpoints);
    $coords = array('x' => $xtoday - 16, 'y' => 24, 'reference' => 'top-left');
    $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
    $label2['text'] = get_string("today", 'quest');
    ;
    $mygraph->print_TTF($label2);

    $coords = array('x' => $xtoday + 2, 'y' => $ymaxpointstoday - 14, 'reference' => 'top-left');
    $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
    $label2['text'] = "Max:" . number_format($todaymaxpoints, 2);
    $mygraph->print_TTF($label2);

    $todayminpoints = quest_calculate_points($timenow, $datestart, $dateend, $tinit, $datefirstanswer, $initialpoints, $pointsmax,
            0);
    $yminpointstoday = $mygraph->get_y_point($todayminpoints);
    $coords = array('x' => $xtoday + 2, 'y' => $yminpointstoday, 'reference' => 'top-left');
    $mygraph->update_boundaryBox($label2['boundary_box'], $coords);
    $label2['text'] = "Min:" . number_format($todayminpoints, 2);
    $mygraph->print_TTF($label2);

    $mygraph->output();
} // ...end graph_submissions.
require_login($COURSE);
graph_submissions();