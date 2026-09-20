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
 * Display the assessment form for a Quest answer.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$aid = required_param('aid', PARAM_INT); // Answer ID..
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_LOCALURL);
require_sesskey();
global $DB, $OUTPUT, $PAGE;

$answer = $DB->get_record('quest_answers', ['id' => $aid], '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', ['id' => $answer->submissionid], '*', MUST_EXIST);
$quest = $DB->get_record("quest", ["id" => $submission->questid], '*', MUST_EXIST);
list($course, $cm) = quest_get_course_and_cm_from_quest($quest);
require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$cangrade = has_capability('mod/quest:grade', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("assess", "quest");

$strsubmission = "<a href=\"challenges.php?id=$cm->id&amp;action=showchallenge&amp;cid=$submission->id\">$submission->title</a>";

$url = new moodle_url('/mod/quest/assess.php',
        ['aid' => $aid, 'sid' => $submission->id, 'allowcomments' => $allowcomments, 'redirect' => $redirect,
                        'sesskey' => sesskey()]);
$PAGE->set_url($url);

$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('challenge', 'quest') . ': ' . $submission->title,
        new moodle_url('challenges.php', ['id' => $cm->id, 'cid' => $submission->id, 'action' => 'showchallenge']));
$PAGE->navbar->add(get_string('answername', 'quest', $answer));
echo $OUTPUT->header();

// ...there can be an assessment record , if there isn't...
if (!$assessment = $DB->get_record("quest_assessments", ["answerid" => $answer->id, "questid" => $quest->id])) {

    $now = time();
    // ...create one and set timecreated way in the future, this is reset when record is updated.
    $assessment = new stdClass();
    $assessment->questid = $quest->id;
    if ($cangrade) {
        $assessment->teacherid = $USER->id;
    } else if (($submission->userid == $USER->id) && (!$cangrade)) {
        $assessment->userid = $USER->id;
    } else {
        throw new \moodle_exception('assess_forbidden', 'quest');
    }

    $assessment->answerid = $answer->id;
    $assessment->dateassessment = $now;
    $assessment->commentsforteacher = '';
    $assessment->commentsteacher = '';

    if (!$assessment->id = $DB->insert_record("quest_assessments", $assessment)) {
        throw new \moodle_exception('inserterror', 'quest', '', "quest_assessments");
    }
    // ...if it's the teacher and the quest is error banded set all the elements to Yes.
    if ($cangrade && ($quest->gradingstrategy == 2)) {
        if ($DB->get_field("quest_submissions", "numelements", ["id" => $submission->id]) == 0) {
            $num = $DB->get_field("quest", "nelements", ["id" => $quest->id]);
        } else {
            $num = $DB->get_field("quest_submissions", "numelements", ["id" => $submission->id]);
        }
        for ($i = 0; $i < $num; $i++) {
            $element = new stdClass();
            $element->questid = $quest->id;
            $element->assessmentid = $assessment->id;
            $element->elementno = $i;
            $element->userid = $USER->id;
            $element->calification = 1;
            if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                throw new \moodle_exception('inserterror', 'quest', '', "quest_elements_assessments");
            }
        }
        // ...now set the adjustment.
        $element = new stdClass();
        $i = $num;
        $element->questid = $quest->id;
        $element->assessmentid = $assessment->id;
        $element->elementno = $i;
        $element->userid = $USER->id;
        $element->calification = 0;
        if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
            throw new \moodle_exception('inserterror', 'quest', '', "quest_elements_assessments");
        }
    }
}

echo $OUTPUT->heading_with_help(get_string("assessthisanswer", "quest"), "grading", "quest");

// ...show assessment and allow changes.
// ...print bottom frame with the submission.

$title = get_string('answername', 'quest', $answer);

if (has_capability('mod/quest:preview', $context)) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($answer->userid, $course->id);
}

$title .= " " . get_string('tothechallenge', 'quest') .
         "<a name=\"cid_$submission->id\" href=\"challenges.php?" .
        "id=$cm->id&amp;action=showchallenge&amp;cid=$submission->id\">$submission->title</a>";

echo $OUTPUT->heading($title);

quest_print_answer_info($quest, $answer);
// Link to assessment elements preview.
echo '<div class="text-center my-3">';
echo '<a class="btn btn-outline-secondary btn-sm" href="assessments.php?id=' . $cm->id . '&amp;action=displaygradingform">';
echo '<i class="fa fa-external-link me-1" aria-hidden="true"></i>' . get_string("specimenassessmentform", "quest");
echo '</a> ';
echo $OUTPUT->help_icon('specimenanswer', 'quest');
echo '</div>';

echo '<div class="quest-assessment-container my-4">';
echo '<div class="card shadow-sm border-0">';
echo '<div class="card-header bg-light fw-bold py-2 px-3 text-dark">';
echo '<i class="fa fa-file-text-o text-primary me-2" aria-hidden="true"></i>' . get_string('answercontent', 'quest');
echo '</div>';
echo '<div class="card-body p-4">';
quest_print_answer($quest, $answer);
echo '</div></div></div>';
// If user has general assess privileges get next answer to evaluate.
if ($cangrade) {
    $nextanswer = quest_next_unassesed_answer($answer);
} else {
    // ... else redirect to answers list.
    $nextanswer = null;
}
if ($nextanswer !== null ) {
    $returnto = new moodle_url('assess.php', [
        'id' => $cm->id,
        'sid' => $submission->id,
        'aid' => $nextanswer->id,
        'sesskey' => sesskey(),
    ]);
} else {
    $returnto = new moodle_url('challenges.php', ['id' => $cm->id, 'cid' => $submission->id, 'action' => 'showchallenge' ]);
}
quest_print_assessment($quest, $submission->id, $assessment, true, $allowcomments, $returnto);

echo $OUTPUT->footer();
