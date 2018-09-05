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
/*
 * This file contains modifications to bundled class graph by Juan Pablo de Castro
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
global $CFG;
require_once($CFG->libdir . '/graphlib.php');

class quest_graph extends graph {

    /**
     * The output() call removed from this method to allow modification prior to outputting the graph.
     * Call explicitly $this->output() after this.
     * {@inheritDoc}
     * @see graph::draw_stack()
     */
    public function draw_stack() {
        $this->init();
        $this->draw_text();

        $yOrder = $this->y_order; // save y_order data.
                                  // iterate over each data set. order is very important if you want
                                  // to see data correctly. remember shadows!!
        foreach ($yOrder as $set) {
            $this->y_order = array($set);
            $this->init_data();
            $this->draw_data();
        }
        $this->y_order = $yOrder; // revert y_order data.

        // $this->output();
        // Da una oportunidad a decorar posteriormente el resultado llamar
        // a output explícitamente
    }

    public function get_x_point($xdata) {
        $xStep = $this->calculated['x_axis']['step'];
        $ticksOffset = $this->parameter['x_offset']; // ...where to start drawing ticks relative to y
                                                     // axis.
        $gridLeft = $this->calculated['boundary_box']['left'] + ($xStep * $ticksOffset); // ...grid
                                                                                            // x
                                                                                            // start
        $tickX = $gridLeft; // tick x coord
        $factor = $this->calculated['x_axis']['factor'];
        $min = $this->calculated['x_axis']['min'];
        $pointx = $gridLeft + ($xdata - $min) * $factor;
        return $pointx;
    }

    public function get_y_point($ydata) {
        if (isset($set) && isset($this->y_format[$set]['y_axis']) && $this->y_format[$set]['y_axis'] == 'right') {
            return $this->calculated['boundary_box']['bottom'] - round(
                    ($ydata - $this->calculated['y_axis_right']['min']) * $this->calculated['y_axis_right']['factor']);
        } else {
            return $this->calculated['boundary_box']['bottom'] - round(
                    ($ydata - $this->calculated['y_axis_left']['min']) * $this->calculated['y_axis_left']['factor']);
        }
    }

    function init_x_axis() {
        $this->calculated['x_axis'] = array(); // ...array to hold calculated values for x_axis.
        $this->calculated['x_axis']['boundary_box_max'] = array('height' => 0, 'width' => 0);

        $axis_font = $this->parameter['axis_font'];
        $axis_size = $this->parameter['axis_size'];
        $axis_colour = $this->parameter['axis_colour'];
        $axis_angle = $this->parameter['x_axis_angle'];

        // ...check whether to treat x axis as numeric.
        if ($this->parameter['x_axis_gridlines'] == 'auto') { // Auto means text based x_axis, not
                                                              // numeric...
            $this->calculated['x_axis']['num_ticks'] = sizeof($this->x_data);
            $data = $this->x_data;
            for ($i = 0; $i < $this->calculated['x_axis']['num_ticks']; $i++) {
                $value = array_shift($data); // Grab value from begin of array.
                $this->calculated['x_axis']['data'][$i] = $value;
                $this->calculated['x_axis']['text'][$i] = $value; // Raw data and text are both the
                                                                   // same in this case.
                $size = $this->get_boundaryBox(
                        array('points' => $axis_size, 'font' => $axis_font, 'angle' => $axis_angle, 'colour' => $axis_colour,
                                        'text' => $value));
                $this->calculated['x_axis']['boundary_box'][$i] = $size;
                if ($size['height'] > $this->calculated['x_axis']['boundary_box_max']['height'])
                    $this->calculated['x_axis']['boundary_box_max'] = $size;
            }
        } else { // X axis is numeric so find max min values...
            $this->calculated['x_axis']['num_ticks'] = $this->parameter['x_axis_gridlines'];

            $min = $this->parameter['x_min'];
            $max = $this->parameter['x_max'];
            $data = array();
            $data = $this->find_range($this->x_data, $min, $max, $this->parameter['x_resolution']);

            $this->calculated['x_axis']['min'] = $min;
            $this->calculated['x_axis']['max'] = $max;

            $step = ($max - $min) / ($this->calculated['x_axis']['num_ticks'] - 1);
            $start = $min;

            for ($i = 0; $i < $this->calculated['x_axis']['num_ticks']; $i++) { // Calculate x axis
                                                                                // text sizes.
                if ($this->parameter['x_label_date'] == 0) {
                    $value = number_format($start, $this->parameter['xDecimal'], $this->parameter['decimal_point'],
                            $this->parameter['thousand_sep']);
                } else {
                    if ($max - $min < 24 * 3600)
                        $value = date('H', $start) . ':' . date('m', $start);
                    else {
                        $value = date('j', $start) . '/' . date('M', $start);
                    }
                }
                $this->calculated['x_axis']['data'][$i] = $start;
                $this->calculated['x_axis']['text'][$i] = $value; // Text is formatted raw data.

                $size = $this->get_boundaryBox(
                        array('points' => $axis_size, 'font' => $axis_font, 'angle' => $axis_angle, 'colour' => $axis_colour,
                                        'text' => $value));
                $this->calculated['x_axis']['boundary_box'][$i] = $size;

                if ($size['height'] > $this->calculated['x_axis']['boundary_box_max']['height'])
                    $this->calculated['x_axis']['boundary_box_max'] = $size;

                $start += $step;
            }
        }
        if ($this->parameter['x_axis_text']) {
            $this->calculated['boundary_box']['bottom'] -= $this->calculated['x_axis']['boundary_box_max']['height'] +
                    $this->parameter['x_inner_padding'];
        }
    }
} // class quest_graph.
