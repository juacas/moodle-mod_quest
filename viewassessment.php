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

/**
 * Display a Quest assessment.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

global $DB, $OUTPUT, $PAGE;

$asid = required_param('asid', PARAM_INT); // Assessment ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_LOCALURL);

$assessment = $DB->get_record("quest_assessments", ["id" => $asid], '*', MUST_EXIST);
$answer = $DB->get_record('quest_answers', ['id' => $assessment->answerid], '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', ['id' => $answer->submissionid], '*', MUST_EXIST);
$quest = $DB->get_record("quest", ["id" => $submission->questid], '*', MUST_EXIST);
$course = get_course($quest->course);
$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
$sid = $submission->id;
require_login($course->id, false, $cm);

quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$url = new moodle_url('/mod/quest/viewassessment.php',
        ['asid' => $asid, 'sid' => $sid, 'allowcomments' => $allowcomments, 'redirect' => $redirect]);
$PAGE->set_url($url);
$PAGE->navbar->add(get_string('challenge', 'quest') . ': ' . $submission->title,
        new moodle_url('challenges.php', ['id' => $cm->id, 'cid' => $sid, 'action' => 'showchallenge']));
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!$ismanager && $answer->userid != $USER->id && $assessment->userid != $USER->id) {
    throw new \moodle_exception('nopermissions', 'error', '', "Unauthorized access!");
}
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("viewassessment", "quest");

if (!$redirect) {
    $redirect = "challenges.php?id=$cm->id&cid=$sid&action=showchallenge#cid=$sid";
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

    echo '<div class="quest-assessment-container my-4">';
    echo '<div class="card shadow-sm border-0">';
    echo '<div class="card-header bg-light fw-bold py-2 px-3 text-dark">';
    echo '<i class="fa fa-commenting-o text-primary me-2" aria-hidden="true"></i>' . get_string("commentsforteacher", "quest");
    echo '</div>';
    echo '<div class="card-body p-3">';
    echo '<form name="gradingform" action="answer.php" method="post">';
    echo '<a name="Claims"><input type="hidden" name="action" value="updatecomment" /></a>';
    echo '<input type="hidden" name="redirect" value="' . s($redirect) . '"/>';
    echo '<input type="hidden" name="sid" value="' . $sid . '" />';
    echo '<input type="hidden" name="aid" value="' . $answer->id . '" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '<div class="mb-3">';
    quest_print_editor("teachercomment", "id_teachercomment", $answer->commentforteacher, $context, 5);
    echo '</div>';
    echo '<div class="text-end">';
    echo '<button type="submit" class="btn btn-primary">'
            . '<i class="fa fa-paper-plane me-1" aria-hidden="true"></i>'
            . get_string("save", "quest") . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div></div></div>';
}
if ($ismanager) {
    if (!empty($answer->commentforteacher)) {
        echo '<div class="quest-assessment-container my-4">';
        echo '<div class="card shadow-sm border-0">';
        echo '<div class="card-header bg-light fw-bold py-2 px-3 text-dark">';
        echo '<a name="Claims"></a>';
        echo '<i class="fa fa-commenting-o text-info me-2" aria-hidden="true"></i>' . get_string("commentsforteacher", "quest");
        echo '</div>';
        echo '<div class="card-body p-3 quest-review-feedback-box">';
        echo format_text($answer->commentforteacher);
        echo '</div></div></div>';
    }
}

$answer = $DB->get_record('quest_answers', ['id' => $assessment->answerid], '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', ['id' => $answer->submissionid], '*', MUST_EXIST);
$quest = $DB->get_record("quest", ["id" => $submission->questid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
$title = get_string('answername', 'quest', $answer);

if (($ismanager || ($answer->userid == $USER->id))) {
    $title .= ' ' . get_string('by', 'quest') . ' ' . quest_fullname($answer->userid, $course->id);
}

$title .= " " . get_string('tothechallenge', 'quest') .
         "<a name=\"cid_$sid\" href=\"challenges.php?" .
        "id=$cm->id&amp;action=showchallenge&amp;cid=$sid\">$submission->title</a>";

echo $OUTPUT->heading($title);

quest_print_answer_info($quest, $answer);
echo '<div class="quest-assessment-container my-4">';
echo '<div class="card shadow-sm border-0">';
echo '<div class="card-header bg-light fw-bold py-2 px-3 text-dark">';
echo '<i class="fa fa-file-text-o text-primary me-2" aria-hidden="true"></i>' . get_string('answercontent', 'quest');
echo '</div>';
echo '<div class="card-body p-4">';
quest_print_answer($quest, $answer);
echo '</div></div></div>';
if (!empty($redirect)) {
    echo $OUTPUT->continue_button($redirect);
}

echo $OUTPUT->footer();
