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
 * Display an author assessment.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$aid = required_param('aid', PARAM_INT); // Assessment ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_URL);
$sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

global $DB, $PAGE, $OUTPUT;

$assessment = $DB->get_record("quest_assessments_autors", ["id" => $aid], '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', ['id' => $assessment->submissionid], '*', MUST_EXIST);
$quest = $DB->get_record("quest", ["id" => $submission->questid], '*', MUST_EXIST);
list($course, $cm) = quest_get_course_and_cm_from_quest($quest);

require_login($course->id, false, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/quest/viewassessmentautor.php',
        ['aid' => $aid, 'allowcomments' => $allowcomments, 'redirect' => $redirect, 'dir' => $dir, 'sort' => $sort]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_activity_record($quest);
$PAGE->activityheader->set_attrs([
    'description' => quest_get_activity_header_description($quest, $cm, $context),
]);
$PAGE->navbar->add(get_string('challenge', 'quest') . ': ' . $submission->title,
        new moodle_url('challenges.php', ['id' => $cm->id, 'cid' => $submission->id, 'action' => 'showchallenge']));
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

quest_check_visibility($course, $cm);

$ismanager = has_capability('mod/quest:manage', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("viewassessmentautor", "quest");
$newcalification = optional_param('newcalification', null, PARAM_FLOAT);
if (isset($newcalification)) {

    if (($ismanager) && ($assessment->state != 0)) {

        if ($calificationuser = $DB->get_record("quest_calification_users", [
            "userid" => $submission->userid,
            "questid" => $quest->id,
        ])) {
            $calificationuser->points -= $assessment->points;
            $calificationuser->pointssubmission -= $assessment->points;
            $calificationuser->points += $newcalification;
            $calificationuser->pointssubmission += $newcalification;
            $DB->set_field("quest_calification_users", "points", $calificationuser->points, ["id" => $calificationuser->id]);
            $DB->set_field("quest_calification_users", "pointssubmission", $calificationuser->pointssubmission,
                    ["id" => $calificationuser->id]);

            if ($quest->allowteams) {
                if ($calificationteam = $DB->get_record("quest_calification_teams",
                        ["teamid" => $calificationuser->teamid, "questid" => $quest->id])) {
                    $calificationteam->points -= $assessment->points;
                    $calificationteam->pointssubmission -= $assessment->points;
                    $calificationteam->points += $newcalification;
                    $calificationteam->pointssubmission += $newcalification;
                    $DB->set_field("quest_calification_teams", "points", $calificationteam->points,
                            ["id" => $calificationteam->id]);
                    $DB->set_field("quest_calification_teams", "pointssubmission", $calificationteam->pointssubmission,
                            ["id" => $calificationteam->id]);
                }
            }
        }
        $assessment->points = $newcalification;
        $DB->set_field("quest_assessments_autors", "points", $assessment->points, ["id" => $assessment->id]);
        $DB->set_field("quest_assessments_autors", "dateassessment", time(), ["id" => $assessment->id]);
    }
}

// Show assessment but don't allow changes.
quest_print_assessment_autor($quest, $assessment, false, $allowcomments);
$submission = $DB->get_record("quest_submissions", ["id" => $submission->id]);
$title = '"' . $submission->title . '" ';
if (($ismanager || ($submission->userid == $USER->id))) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
}

echo $OUTPUT->heading($title);

quest_print_submission_info($quest, $submission);

echo '<div class="text-center my-3">';
echo '<a class="btn btn-outline-secondary btn-sm" href="assessments_autors.php?id=' . $cm->id . '&amp;action=displaygradingform">';
echo '<i class="fa fa-external-link me-1" aria-hidden="true"></i>' . get_string("specimenassessmentform", "quest");
echo '</a>';
echo '</div>';

echo '<div class="quest-assessment-container my-4">';
echo '<div class="card shadow-sm border-0">';
echo '<div class="card-header bg-light fw-bold py-2 px-3 text-dark">';
echo '<i class="fa fa-file-text-o text-primary me-2" aria-hidden="true"></i>' . get_string('description', 'quest');
echo '</div>';
echo '<div class="card-body p-4">';
quest_print_submission($quest, $submission);
echo '</div></div></div>';

$timenow = time();
if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
    ($submission->nanswerscorrect < $quest->nmaxanswers)) {
    $submission->phase = SUBMISSION_PHASE_ACTIVE;
}
echo '<div class="text-center my-4">';
$returl = !empty($_SERVER['HTTP_REFERER'])
    ? $_SERVER['HTTP_REFERER']
    : (new moodle_url('/mod/quest/challenges.php', [
        'id' => $cm->id,
        'cid' => $submission->id,
        'action' => 'showchallenge',
    ]))->out();
echo $OUTPUT->continue_button($returl);
echo '</div>';
echo $OUTPUT->footer();
