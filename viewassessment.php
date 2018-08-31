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
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

global $DB, $OUTPUT, $PAGE;

$asid = required_param('asid', PARAM_INT); // Assessment ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_LOCALURL);

$assessment = $DB->get_record("quest_assessments", array("id" => $asid), '*', MUST_EXIST);
$answer = $DB->get_record('quest_answers', array('id' => $assessment->answerid), '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', array('id' => $answer->submissionid), '*', MUST_EXIST);
$quest = $DB->get_record("quest", array("id" => $submission->questid), '*', MUST_EXIST);
$course = get_course($quest->course);
$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
$sid = $submission->id;
require_login($course->id, false, $cm);

quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$url = new moodle_url('/mod/quest/viewassessment.php',
        array('asid' => $asid, 'sid' => $sid, 'allowcomments' => $allowcomments, 'redirect' => $redirect));
$PAGE->set_url($url);
$PAGE->navbar->add(get_string('submission', 'quest') . ':' . $submission->title,
        new moodle_url('submissions.php', array('id' => $cm->id, 'sid' => $sid, 'action' => 'showsubmission')));
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!$ismanager && $answer->userid != $USER->id && $assessment->userid != $USER->id) {
    print_error('nopermissions', 'error', null, "Unauthorized access!");
}
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("viewassessment", "quest");

if (!$redirect) {
    $redirect = "submissions.php?id=$cm->id&sid=$sid&action=showsubmission#sid=$sid";
}

echo $OUTPUT->heading_with_help(get_string('seeassessment', 'quest'), "seeassessment", "quest");

if (($ismanager) || ($answer->userid == $USER->id) || ($assessment->userid == $USER->id)) {
    // Show assessment but don't allow changes.
    quest_print_assessment($quest, $sid, $assessment, false, $allowcomments);
}

echo "<br>";
if ($answer->userid == $USER->id) {

    if (!isset($answer->commentforteacher)) {
        $answer->commentforteacher = '';
    }

    echo "<form name=\"gradingform\" action=\"answer.php\" method=\"post\">";
    echo "<a name=\"Claims\"><input type=\"hidden\" name=\"action\" value=\"updatecomment\" /></a>";
    echo "<input type=\"hidden\" name=\"redirect\" value=\"$redirect\"/>";
    echo "<input type=\"hidden\" name=\"sid\" value=\"$sid\" /> ";
    echo "<input type=\"hidden\" name=\"aid\" value=\"$answer->id\" /> ";
    echo "<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\" /> ";
    echo "<center>";
    echo "<table cellpadding=\"5\" border=\"1\">";
    echo "<tr valign=\"top\">";
    echo "<td align=\"right\"><b>" . get_string("commentsforteacher", "quest") . "</b></td>";
    echo "<td>";
    echo "<textarea name=\"teachercomment\" rows=\"5\" cols=\"75\">$answer->commentforteacher</textarea>";
    echo " </td>";
    echo "</tr>";

    echo "</table>";
    echo "<input type=\"submit\" value=\"" . get_string("save", "quest") . "\" />";
    echo "</center>";
    echo "</form>";
}
if ($ismanager) {
    if (!empty($answer->commentforteacher)) {
        echo "<a name=\"Claims\"></a>";
        echo "<b>" . get_string("commentsforteacher", "quest") . "</b><br>";
        echo $OUTPUT->box(format_text($answer->commentforteacher), 'center');
    }
}

$answer = $DB->get_record('quest_answers', array('id' => $assessment->answerid), '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', array('id' => $answer->submissionid), '*', MUST_EXIST);
$quest = $DB->get_record("quest", array("id" => $submission->questid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
$title = get_string('answername', 'quest', $answer);

if (($ismanager || ($answer->userid == $USER->id))) {
    $title .= ' ' . get_string('by', 'quest') . ' ' . quest_fullname($answer->userid, $course->id);
}

$title .= " " . get_string('tothechallenge', 'quest') .
         "<a name=\"sid_$sid\" href=\"submissions.php?" .
        "id=$cm->id&amp;action=showsubmission&amp;sid=$sid\">$submission->title</a>";

echo $OUTPUT->heading($title);

quest_print_answer_info($quest, $answer);
echo $OUTPUT->box_start();
echo $OUTPUT->heading(get_string('answercontent', 'quest'));
quest_print_answer($quest, $answer);
echo $OUTPUT->box_end();
if (!empty($redirect)) {
    echo $OUTPUT->continue_button($redirect);
}

echo $OUTPUT->footer();