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

/** Questournament activity for Moodle: The mod_quest challenge modification event.
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest*/

namespace mod_quest\event;

defined('MOODLE_INTERNAL') || die();
require_once('base.php');


/** The user modifies the Quest caracteristics
 *
 * @property-read array $other {
 *                Extra information about event.
 *
 *                - string info
 *                - string cmid
 *                }
 *
 * @package mod_quest
 * @since Moodle 2.7
 * @copyright 2015 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */
class answer_updated extends base {
    /**
     * Populate event from arguments.
     * @param \stdClass $challenge
     * @param \stdClass $answer
     * @param \cm_info $cm
     * @return \mod_quest\event\answer_updated
     */
    public static function create_from_parts(\stdClass $challenge, \stdClass $answer, \cm_info $cm) {
        $url = "/mod/quest/answer.php?sid=$challenge->id&amp;aid=$answer->id&amp;action=showanswer";
        $data = ['relateduserid' => $answer->userid, 'context' => \context_module::instance($cm->id),
                        'userid' => $answer->userid, 'courseid' => $cm->course,
                        'other' => ['info' => $answer->title, 'cmid' => $cm->id, 'activityid' => $challenge->questid,
                                        'url' => $url]];
        /** @var answer_updated $event */
        $event = self::create($data);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['level'] = $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /** Returns localised general event name.
     *
     * @return string */
    public static function get_name() {
        return "New Answer created.";
    }

    /** Returns description of what happened.
     *
     * @return string */
    public function get_description() {
        return "The user with id '$this->userid' created a new Answer in the Quest activity '" .
        $this->data['other']['activityid'] .
                 "in the course '$this->courseid'. " . $this->data['other']['info'];
    }

    /** Custom validation.
     *
     * @throws \coding_exception */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['info'])) {
            throw new \coding_exception('The \'info\' value must be set in other.');
        }
    }
}
