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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Questournament for Moodle. If not, see <http://www.gnu.org/licenses/>.

/** Debugging script
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
require_once ("../../config.php");
require_once ("../scores_lib.php");
exit();
$id = 403;
if ($id) {
    $cm = $DB->get_record("course_modules", "id", $id, '*', MUST_EXIST);
    $quest = $DB->get_record("quest", "id", $cm->instance, '*', MUST_EXIST);
}
$questid = $quest->id;
$userid = "1294";
// $query = $DB->get_record_select("quest_assessments_autors","questid=$questid and userid in
// ($userid)","sum(points) as points");
// print_object($query);
// test_update_team(56,539,true);

// updateallteams($questid);

// quest_update_team_scores($questid,630);
// test_team_scores($questid,630);
// $score=calculate_quest_score_for_answer(3191,$quest);
// print ("<p>la puntuacion max: ".$score."</p>");

// evp revisar que funciona bien, esta sentencia $sql no se usa en ning�n lado es probable que se
// olvidasen de borrarla
$sql = "select sum(ans.grade*ans.pointsmax/100) as points from {quest_answers} as ans, {quest_assessments} as assess WHERE " .
         "ans.questid=$questid AND " . "ans.userid in ($userid)AND " . "ans.id=assess.answerid AND " . "assess.phase=1";
// print_object($CFG);
$grade = quest_calculate_user_score($questid, $userid);
print_object($grade);

function calculate_quest_score_for_answer($assesmentid, $quest) {
    if (!$assessment = $DB->get_record("quest_assessments", "id", $assesmentid)) {
        error("quest assessment is misconfigured");
    }
    if (!$answer = $DB->get_record("quest_answers", "id", $assessment->answerid)) {
        error("quest answer is misconfigured");
    }
    if (!$submission = $DB->get_record("quest_submissions", "id", $answer->submissionid)) {
        error("quest submission is misconfigured");
    }
    return quest_get_points_deb($submission, $quest, $answer);
}

function quest_get_points_deb($submission, $quest, $answer = ' ') {
    if (empty($answer)) {
        $timenow = time();
    } else {
        $timenow = $answer->date;
    }
    $grade = 0;

    $initialpoints = $submission->initialpoints;
    $nanswerscorrect = $submission->nanswerscorrect;
    $datestart = $submission->datestart;
    $dateend = $submission->dateend;
    $dateanswercorrect = $submission->dateanswercorrect;
    $pointsmax = $submission->pointsmax;

    $tinitial = $quest->tinitial * 86400;
    $type = $quest->typecalification;
    $nmaxanswers = $quest->nmaxanswers;
    $pointsnmaxanswers = $submission->points;
    $state = $submission->state;

    if ($state < 2) {
        $grade = $initialpoints;
    } else if ($nanswerscorrect >= $nmaxanswers) {
        $grade = 0;
    } else {
        $grade = quest_calculate_points($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax,
                $type);
    }

    return $grade;
}

function test_team_scores($questid, $teamid) {
    $query = $DB->get_records_select("quest_calification_users", "teamid=?", array($teamid));
    foreach ($query as $q) {
        $members[] = $q->userid;
    }
    $memberlist = implode(',', $members);

    $pointsanswers = quest_calculate_user_score($questid, $members);

    $nanswers = quest_count_user_answers($questid, $members);
    print("nanswers:" . $nanswers);
    $nanswersassessed = quest_count_user_answers_assesed($questid, $members);

    $submissionpoints = quest_calculate_user_submissions_score($questid, $members);
    $nsubmissions = quest_count_user_submissions($questid, $members);
    // print("Team $teamid formed by $member_list has $nsubmissions submissions");
    $nsubmissionassessed = quest_count_user_submissions_assesed($questid, $members);

    $points = $submissionpoints + $pointsanswers;
}