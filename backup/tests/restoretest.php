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
 * Backup Questournament module
 * backup task that provides all the settings and steps to perform one complete backup of the activity.
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
define('CLI_SCRIPT', 1);
require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
$fullname = "restore test course";
$shortname = "restoretest";
$categoryid = 3;
$folder = 'd5441abf95d594bbfff05dd83c7f6b74';
$userid = 2;
$courseid = 16; // Reuse a course.
              // Create new course.
if (!isset($courseid)) {
    $courseid = restore_dbops::create_new_course($fullname, $shortname, $categoryid);
} else {
    restore_dbops::delete_course_content($courseid);
}

// Restore backup into course.
$controller = new restore_controller($folder, $courseid, backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $userid,
        backup::TARGET_NEW_COURSE);
$controller->execute_precheck();
$controller->execute_plan();