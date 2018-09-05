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

/** This page lists all the instances of QUEST in a particular course
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
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
$id = required_param('id', PARAM_INT); // Course...
global $DB, $PAGE, $OUTPUT;
$course = get_course($id);
require_login($course);
$params = [];
$params['id'] = optional_param('id', null, PARAM_INT);
$thispageurl = new moodle_url('/mod/quest/index.php', $params);
$PAGE->set_url($thispageurl);

$context = context_course::instance($course->id);
// Get all required strings.
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strinfo = get_string("phase", "quest");
$strdeadline = get_string("deadline", "quest");
// Print the header.
$url = new moodle_url('/mod/quest/index.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->navbar->add($strquests, "index.php?id=$course->id");
$PAGE->set_title($strquests);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
// Get all the appropriate data.
if (!$quests = get_all_instances_in_course("quest", $course)) {
    notice("There are no quests", "../../course/view.php?id=$course->id");
    die();
}

// Print the list of instances.

$timenow = time();
$strname = get_string("name");
$strweek = get_string("week");
$strtopic = get_string("topic");

$table = new html_table();
if ($course->format == "weeks") {
    $table->head = array($strweek, $strname, $strinfo, $strdeadline);
    $table->align = array("CENTER", "LEFT", "LEFT", "LEFT");
} else if ($course->format == "topics") {
    $table->head = array($strtopic, $strname, $strinfo, $strdeadline);
    $table->align = array("CENTER", "LEFT", "LEFT", "LEFT");
} else {
    $table->head = array($strname, $strinfo, $strdeadline);
    $table->align = array("LEFT", "LEFT", "LEFT");
}

foreach ($quests as $quest) {

    $info = quest_phase($quest);
    $due = userdate($quest->dateend);

    if (!$quest->visible && has_capability('moodle/course:viewhiddenactivities', $context)) {
        // Show dimmed if the mod is hidden.
        $link = "<a class=\"dimmed\" href=\"view.php?id=$quest->coursemodule\">" . format_string($quest->name, true) . "</a>";
    } else {
        // Show normal if the mod is visible.
        $link = "<a href=\"view.php?id=$quest->coursemodule\">" . format_string($quest->name, true) . "</a>";
    }

    if ($course->format == "weeks" or $course->format == "topics") {
        $table->data[] = array($quest->section, $link, $info, $due);
    } else {
        $table->data[] = array($link, $info, $due);
    }
}

echo "<BR>";

echo html_writer::table($table);

// Finish the page.

echo $OUTPUT->footer();
