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
 * @package mod_quest */
namespace mod_quest\event;

/** The mod_quest abstract base event class.
 *
 * Most mod_quest events can extend this class.
 *
 * @package mod_quest
 * @since Moodle 2.7
 * @copyright 2015 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */
abstract class base extends \core\event\base {
    /** Returns relevant URL.
     *
     * @return \moodle_url */
    public function get_url() {
        if (isset($this->data['other']['url'])) {
            return new \moodle_url($this->data['other']['url']);
        } else {
            return null;
        }
    }
    /**
     * URL of the event.
     * @param mixed $url
     * @return void
     */
    public function set_url($url) {
        $this->url = $url;
    }
}