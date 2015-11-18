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
// along with Questournament for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Questournament activity for Moodle
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

  Show the page that allow to do the assess of a submission

 * **************************************************** */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$sid = required_param('sid', PARAM_INT);   // Submission ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_ALPHA);
global $DB;

if (!$submission = $DB->get_record('quest_submissions', array('id' => $sid))) {
    error("Incorrect submission id");
}
if (!$quest = $DB->get_record("quest", array("id" => $submission->questid))) {
    print_error("incorrectQuest",'quest');;
}
if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
    print_error("course_misconfigured",'quest');
}
if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
    error("No coursemodule found");
}

if (!$redirect) {
    $redirect = urlencode($_SERVER["HTTP_REFERER"] . '#sid=' . $submission->id);
}

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("assess", "quest");

$url = new moodle_url('/mod/quest/asses_autors.php',
        array('sid' => $sid, 'allowcomments' => $allowcomments, 'redirect' => $redirect));
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (empty($_GET['sid'])) {
    error("Show submission: submission id missing");
}

$submission = $DB->get_record("quest_submissions", array("id" => $_GET['sid']));
$title = '"' . $submission->title . '" ';
if ($ismanager) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
}
echo $OUTPUT->heading($title);
quest_print_submission_info($quest, $submission);

echo("<center><b><a href=\"assessments.php?cmid=$cm->id&amp;action=displaygradingform\">" .
 get_string("specimenassessmentform", "quest") . "</a></b></center>");

echo $OUTPUT->heading(get_string('description', 'quest'));
quest_print_submission($quest, $submission);

if (!$assessment = $DB->get_record("quest_assessments_autors", array("submissionid" => $submission->id))) {

    $now = time();
    // ...create one and set timecreated way in the future, this is reset when record is updated.
    $assessment = new stdclass();
    $assessment->questid = $quest->id;

    if ($ismanager) {
        $assessment->userid = $USER->id;
    }

    $assessment->submissionid = $submission->id;
    $assessment->dateassessment = $now;
    $assessment->state = 0;
    $assessment->commentsforteacher = '';
    $assessment->commentsteacher = '';

    if (!$assessment->id = $DB->insert_record("quest_assessments_autors", $assessment)) {
        error("Could not insert quest assessment autor!");
    }
    // ...if it's the teacher and the quest is error banded set all the elements to Yes.
    if ($ismanager and ( $quest->gradingstrategy == 2)) {
        for ($i = 0; $i < $quest->nelements; $i++) {
            unset($element);
            $element->questid = $quest->id;
            $element->assessmentautorid = $assessment->id;
            $element->elementno = $i;
            $element->userid = $USER->id;
            $element->calification = 1;
            if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                error("Could not insert quest grade!");
            }
        }
        // ...now set the adjustment.
        unset($element);
        $i = $quest->nelements;
        $element->questid = $quest->id;
        $element->assessmentautorid = $assessment->id;
        $element->elementno = $i;
        $element->userid = $USER->id;
        $element->calification = 0;
        if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
            error("Could not insert quest grade!");
        }
    }
}

echo $OUTPUT->heading_with_help(get_string("assessthissubmission", "quest"), "assessthissubmission", "quest");

// ...show assessment autor and allow changes.
quest_print_assessment_autor($quest, $assessment, true, $allowcomments,
        "submissions.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=showsubmission");


echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER'] . '#sid=' . $submission->id);

echo $OUTPUT->footer();