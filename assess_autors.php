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
 * @package mod_quest
 *
 *          Show the page that allow to do the assess of a submission
 *
 *          **************************************************** */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$sid = required_param('sid', PARAM_INT); // Submission ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_ALPHA);
global $DB;

$submission = $DB->get_record('quest_submissions', array('id' => $sid), '*', MUST_EXIST);
$quest = $DB->get_record("quest", array("id" => $submission->questid), '*', MUST_EXIST);
list($course, $cm) = quest_get_course_and_cm_from_quest($quest);

if (!$redirect) {
    $redirect = urlencode($_SERVER["HTTP_REFERER"] . '#sid=' . $submission->id);
}

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$cangrade = has_capability('mod/quest:grade', $context);
$canapprove = has_capability('mod/quest:approvechallenge', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("assess", "quest");

$url = new moodle_url('/mod/quest/assess_autors.php',
                array('sid' => $sid, 'allowcomments' => $allowcomments, 'redirect' => $redirect));
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('submission', 'quest') . ': ' . $submission->title,
        new moodle_url('submissions.php', array('id' => $cm->id, 'sid' => $submission->id, 'action' => 'showsubmission')));

echo $OUTPUT->header();

$title = '"' . $submission->title . '" ';
if (has_capability('mod/quest:preview', $context)) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
}
echo $OUTPUT->heading($title);
quest_print_submission_info($quest, $submission);

echo ("<center><b><a href=\"assessments.php?id=$cm->id&amp;action=displaygradingform\">" .
         get_string("specimenassessmentform", "quest") . "</a></b></center>");

echo $OUTPUT->heading(get_string('description', 'quest'));
quest_print_submission($quest, $submission);

$assessment = $DB->get_record("quest_assessments_autors", array("submissionid" => $submission->id));
$now = time();
if (!$assessment) {
    // ...create one and set timecreated way in the future, this is reset when record is updated.
    $assessment = new stdclass();
    $assessment->questid = $quest->id;
    if ($cangrade) {
        $assessment->userid = $USER->id;
    }
    $assessment->submissionid = $submission->id;
    $assessment->state = 0;
    $assessment->commentsforteacher = '';
    $assessment->commentsteacher = '';
    if (!$assessment->id = $DB->insert_record("quest_assessments_autors", $assessment)) {
        print_error('inserterror', 'quest', null, "quest_assessments_autors");
    }
}
$assessment->dateassessment = $now;

// ...if it's the teacher and the quest is error banded set all the elements to Yes.
if ($cangrade and ($quest->gradingstrategy == 2)) {
    for ($i = 0; $i < $quest->nelements; $i++) {
        unset($element);
        $element->questid = $quest->id;
        $element->assessmentautorid = $assessment->id;
        $element->elementno = $i;
        $element->userid = $USER->id;
        $element->calification = 1;
        if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
            print_error('inserterror', 'quest', null, "quest_items_assesments_autor");
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
        print_error('inserterror', 'quest', null, "quest_items_assesments_autor");
    }
}

echo $OUTPUT->heading_with_help(get_string("assessthissubmission", "quest"), "assessthissubmission", "quest");
// ...show assessment autor and allow changes.
// If user has general assess privileges get next answer to evaluate.

$returnto = quest_next_submission_url($submission, $cm);
quest_print_assessment_autor($quest, $assessment, true, $allowcomments, $returnto);
$continueto = new moodle_url('view.php', ['id' => $cm->id ]);
echo $OUTPUT->single_button($continueto, get_string('cancel'));
echo $OUTPUT->footer();