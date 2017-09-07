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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Questournament for Moodle. If not, see <http://www.gnu.org/licenses/>.

define('CLI_SCRIPT', 1);
require (dirname(__FILE__) . '/../../../../config.php');
require_once ($CFG->dirroot . '/backup/util/helper/convert_helper.class.php');
$testcoursebackup = 'quest_answers_source';
convert_helper::to_moodle2_format($testcoursebackup, 'moodle1');