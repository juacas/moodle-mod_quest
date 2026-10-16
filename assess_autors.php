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
 * Display the assessment form for a Quest submission.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$sid = required_param('sid', PARAM_INT); // Submission ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_ALPHA);
global $DB, $USER;

$submission = $DB->get_record('quest_submissions', ['id' => $sid], '*', MUST_EXIST);
$quest = $DB->get_record("quest", ["id" => $submission->questid], '*', MUST_EXIST);
list($course, $cm) = quest_get_course_and_cm_from_quest($quest);

if (!$redirect) {
    $redirect = urlencode($_SERVER["HTTP_REFERER"] . '#sid=' . $submission->id);
}

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
if (!quest_user_passed_password($quest, $context)) {
    quest_require_password($quest, $course, optional_param('userpassword', '', PARAM_RAW_TRIMMED));
}
$ismanager = has_capability('mod/quest:manage', $context);
$cangrade = has_capability('mod/quest:grade', $context);
if (!$ismanager && !$cangrade && (int)$submission->userid !== (int)$USER->id) {
    throw new \moodle_exception('nopermissions', 'error', '', 'assess this challenge');
}

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("assess", "quest");

$url = new moodle_url('/mod/quest/assess_autors.php',
                ['sid' => $sid, 'allowcomments' => $allowcomments, 'redirect' => $redirect]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_activity_record($quest);
$PAGE->activityheader->set_attrs([
    'description' => quest_get_activity_header_description($quest, $cm, $context),
]);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
quest_add_breadcrumbs($cm, $submission, null, get_string('evaluate', 'quest'));

echo $OUTPUT->header();

$title = '"' . $submission->title . '" ';
if (has_capability('mod/quest:preview', $context)) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
}
echo $OUTPUT->heading($title);
quest_print_submission_info($quest, $submission);

echo '<div class="text-center my-3">';
echo '<a class="btn btn-outline-secondary btn-sm" href="assessments_autors.php?id=' . $cm->id . '&amp;action=displaygradingform">';
echo '<i class="fa fa-external-link me-1" aria-hidden="true"></i>' . get_string("specimenassessmentform", "quest");
echo '</a>';
echo '</div>';

$anylinkedq = \mod_quest\question\question_reference_service::get_question_for_challenge((int)$submission->id);

if (!$anylinkedq) {
    echo '<div class="quest-assessment-container my-4">';
    echo '<div class="card shadow-sm border-0">';
    echo '<div class="card-header bg-light fw-bold py-2 px-3 text-dark">';
    echo '<i class="fa fa-file-text-o text-primary me-2" aria-hidden="true"></i>' . get_string('description', 'quest');
    echo '</div>';
    echo '<div class="card-body p-4">';
    quest_print_submission($quest, $submission);
    echo '</div></div></div>';
}

if ($anylinkedq) {
    $isownpending = (int)$submission->userid === (int)$USER->id &&
        (int)$submission->state === SUBMISSION_STATE_APPROVAL_PENDING;
    echo $OUTPUT->heading(get_string('description', 'quest'));
    echo \mod_quest\service\autograde_service::render_challenge_questions_preview(
        $quest, $submission, $context, (int)$cm->id, $ismanager || $isownpending
    );
}

$assessment = $DB->get_record("quest_assessments_autors", ["submissionid" => $submission->id]);
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
        throw new \moodle_exception('inserterror', 'quest', '', "quest_assessments_autors");
    }
}
$assessment->dateassessment = $now;

// ...if it's the teacher and the quest is error banded set all the elements to Yes.
if ($cangrade && ($quest->gradingstrategy == 2)) {
    for ($i = 0; $i < $quest->nelements; $i++) {
        $element = new stdClass();
        $element->questid = $quest->id;
        $element->assessmentautorid = $assessment->id;
        $element->elementno = $i;
        $element->userid = $USER->id;
        $element->calification = 1;
        if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
            throw new \moodle_exception('inserterror', 'quest', '', "quest_items_assesments_autor");
        }
    }
    // ...now set the adjustment.
    $element = new stdClass();
    $i = $quest->nelements;
    $element->questid = $quest->id;
    $element->assessmentautorid = $assessment->id;
    $element->elementno = $i;
    $element->userid = $USER->id;
    $element->calification = 0;
    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
        throw new \moodle_exception('inserterror', 'quest', '', "quest_items_assesments_autor");
    }
}

echo $OUTPUT->heading_with_help(get_string("assessthissubmission", "quest"), "assessthissubmission", "quest");
// ...show assessment autor and allow changes.
// If user has general assess privileges get next answer to evaluate.

$returnto = new moodle_url('/mod/quest/view.php', ['id' => $cm->id]);
quest_print_assessment_autor($quest, $assessment, true, $allowcomments, $returnto);
$continueto = new moodle_url('view.php', ['id' => $cm->id ]);
echo $OUTPUT->single_button($continueto, get_string('cancel'));
echo $OUTPUT->footer();
