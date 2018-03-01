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

$aid = required_param('aid', PARAM_INT); // Assessment ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_URL);
$sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

global $DB, $PAGE, $OUTPUT;

$assessment = $DB->get_record("quest_assessments_autors", array("id" => $aid), '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', array('id' => $assessment->submissionid), '*', MUST_EXIST);
$quest = $DB->get_record("quest", array("id" => $submission->questid), '*', MUST_EXIST);
list($course, $cm) = quest_get_course_and_cm_from_quest($quest);

require_login($course->id, false, $cm);

$url = new moodle_url('/mod/quest/viewassessmentautor.php',
        array('aid' => $aid, 'allowcomments' => $allowcomments, 'redirect' => $redirect, 'dir' => $dir, 'sort' => $sort));
$PAGE->set_url($url);
$PAGE->navbar->add(get_string('submission', 'quest') . ':' . $submission->title,
        new moodle_url('submissions.php', array('id' => $cm->id, 'sid' => $submission->id, 'action' => 'showsubmission')));
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("viewassessmentautor", "quest");
$newcalification = optional_param('newcalification', null, PARAM_FLOAT);
if (isset($newcalification)) {

    if (($ismanager) && ($assessment->state != 0)) {

        if ($calificationuser = $DB->get_record("quest_calification_users", "userid", $submission->userid, "questid", $quest->id)) {
            $calificationuser->points -= $assessment->points;
            $calificationuser->pointssubmission -= $assessment->points;
            $calificationuser->points += $newcalification;
            $calificationuser->pointssubmission += $newcalification;
            $DB->set_field("quest_calification_users", "points", $calificationuser->points, array("id" => $calificationuser->id));
            $DB->set_field("quest_calification_users", "pointssubmission", $calificationuser->pointssubmission,
                    array("id" => $calificationuser->id));

            if ($quest->allowteams) {
                if ($calificationteam = $DB->get_record("quest_calification_teams",
                        array("teamid" => $calificationuser->teamid, "questid" => $quest->id))) {
                    $calificationteam->points -= $assessment->points;
                    $calificationteam->pointssubmission -= $assessment->points;
                    $calificationteam->points += $newcalification;
                    $calificationteam->pointssubmission += $newcalification;
                    $DB->set_field("quest_calification_teams", "points", $calificationteam->points,
                            array("id" => $calificationteam->id));
                    $DB->set_field("quest_calification_teams", "pointssubmission", $calificationteam->pointssubmission,
                            array("id" => $calificationteam->id));
                }
            }
        }
        $assessment->points = $newcalification;
        $DB->set_field("quest_assessments_autors", "points", $assessment->points, array("id" => $assessment->id));
        $DB->set_field("quest_assessments_autors", "dateassessment", time(), array("id" => $assessment->id));
    }
}

// Show assessment but don't allow changes.
quest_print_assessment_autor($quest, $assessment, false, $allowcomments);
$submission = $DB->get_record("quest_submissions", array("id" => $submission->id));
$title = '"' . $submission->title . '" ';
if (($ismanager || ($submission->userid == $USER->id))) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
}

echo $OUTPUT->heading($title);

quest_print_submission_info($quest, $submission);

echo ("<center><b><a href=\"assessments.php?id=$cm->id&amp;action=displaygradingform\">" .
         get_string("specimenassessmentform", "quest") . "</a></b></center>");

$OUTPUT->heading(get_string('description', 'quest'));
quest_print_submission($quest, $submission);

$timenow = time();
if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
    ($submission->nanswerscorrect < $quest->nmaxanswers)) {
    $submission->phase = SUBMISSION_PHASE_ACTIVE;
}
echo "<br><br>";
echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER'] . '#sid=' . $submission->id);
echo $OUTPUT->footer();
