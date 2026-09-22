<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Test data generator for the Quest activity.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quest_generator extends testing_module_generator {

    /**
     * Create a Quest instance with safe defaults for the legacy schema.
     *
     * @param array|stdClass|null $record Activity values.
     * @param array|null $options Course-module values.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $now = time();

        $defaults = [
            'introformat' => FORMAT_HTML,
            'nattachments' => 0,
            'validateassessment' => 0,
            'usepassword' => 0,
            'password' => '',
            'maxbytes' => 10485760,
            'datestart' => $now - 3600,
            'dateend' => $now + (7 * DAYSECS),
            'gradingstrategy' => 1,
            'nelements' => 1,
            'timemaxquestion' => 7,
            'nmaxanswers' => 25,
            'maxcalification' => 100,
            'mincalification' => 0,
            'typecalification' => 0,
            'allowteams' => 0,
            'ncomponents' => 2,
            'phase' => 0,
            'format' => FORMAT_HTML,
            'visible' => 1,
            'tinitial' => 0,
            'gradingstrategyautor' => 1,
            'nelementsautor' => 1,
            'initialpoints' => 10,
            'teamporcent' => 25,
            'showclasifindividual' => 1,
            'showauthoringdetails' => 1,
            'typegrade' => 0,
            'permitviewautors' => 1,
            'completionpass' => 0,
            'allowqbankquestions' => 1,
            'autoexportqbank' => 0,
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->{$field})) {
                $record->{$field} = $value;
            }
        }

        return parent::create_instance($record, $options);
    }
}
