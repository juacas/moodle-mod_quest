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
require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
$fullname = "restore test course";
$shortname = "restoretest";
$categoryid = 3;
$folder = 'd5441abf95d594bbfff05dd83c7f6b74';
$userid = 2;
$courseid = 16; // reuse a course
              // Create new course
if (!isset($courseid)) {
    $courseid = restore_dbops::create_new_course($fullname, $shortname, $categoryid);
} else {
    restore_dbops::delete_course_content($courseid);
}

// Restore backup into course
$controller = new restore_controller($folder, $courseid, backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $userid,
        backup::TARGET_NEW_COURSE);
$controller->execute_precheck();
$controller->execute_plan();