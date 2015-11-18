<?php
// This file is part of INTUITEL http://www.intuitel.eu as an adaptor for Moodle http://moodle.org/
//
// INTUITEL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// INTUITEL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with INTUITEL for Moodle Adaptor.  If not, see <http://www.gnu.org/licenses/>.

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
 *
 * Debug functions
 */
require_once("../../config.php");
require_once("locallib.php");

//TODO use $quest record

function updateallteams($questid) {
    global $DB;
    $query = $DB->get_records_select("quest_teams", "questid=?", array($questid));
    $quest = $DB->get_record('quest', array('id' => $questid));
    $idteams = array();
    foreach ($query as $team) {
        $idteams[] = $team->id;
        print("<p>Updating team $team->id on quest $questid: </p>  ");
        quest_update_team_scores($quest->id, $team->id);
//test_update_team($questid, $team->id,false);
    }
// Clean orphan records
    if (!empty($idteams)) {
        $select = 'teamid not in (' . join(',', $idteams) . ') and questid=' . $questid;
    } else {
        $select = 'questid=' . $questid;
    }
    print("<p>Cleaning orphan calification_teams records.");
    $DB->delete_records_select('quest_calification_teams', $select);
}

//TODO use $quest record
function updateallusers($questid) {
    global $DB;
    $query = $DB->get_records_select("quest_calification_users", "questid=?", array($questid));
    $quest = $DB->get_record('quest', array('id' => $questid));

    foreach ($query as $usercal) {
        print("<p>Updating user $usercal->userid on quest $questid: </p>  ");
        quest_update_user_scores($quest, $usercal->userid);
//test_update_team($questid, $team->id,false);
    }
}

function test_update_team($questid, $teamid, $update = true) {
    global $DB;
    $query = $DB->get_records_select("quest_calification_users", "teamid=?", array($teamid));
    foreach ($query as $q)
        $members[] = $q->userid;
    $member_list = implode(',', $members);


    $pointsanswers = quest_calculate_user_score($questid, $members);

    $nanswers = quest_count_user_answers($questid, $members);
    $nanswersassessed = quest_count_user_answers_assesed($questid, $members);
    $submissionpoints = quest_calculate_user_submissions_score($questid, $members);
    $nsubmissions = quest_count_user_submissions($questid, $members);
    $nsubmissionassessed = quest_count_user_submissions_assesed($questid, $members);
    $points = $submissionpoints + $pointsanswers;
    if ($update) {
        print("...actualizando... equipo $teamid en quest $questid");
        $calification_teams = $DB->get_record('quest_calification_teams', array("questid" => $questid, "teamid" => $teamid));
        $calification_teams->nanswers = $nanswers;
        $calification_teams->nanswerassessment = $nanswersassessed;
        $calification_teams->points = "$points";
        $calification_teams->pointsanswers = "$pointsanswers";
        $calification_teams->pointssubmission = "$submissionpoints";
        $calification_teams->nsubmissionsassessment = $nsubmissionassessed;
        $calification_teams->nsubmissions = $nsubmissions;
        $DB->update_record('quest_calification_teams', $calification_teams);
    }
}

/**
 *
 * @param stdClass $submission
 * @return stdClass Submission updated
 */
function quest_calculate_pointsanswercorrect_and_date($submission) {
    global $DB;

    $query = $DB->get_records_select("quest_answers", "submissionid=? and grade>=50", array($submission->id), "date, pointsmax");

    if (count($query) > 0) {
        $query = reset($query); // get first record
        $submission->dateanswercorrect = $query->date;
        $submission->pointsanswercorrect = $query->pointsmax;
    } else {
        $submission->dateanswercorrect = 0;
        $submission->pointsanswercorrect = 0;
    }
    return $submission;
}

/**
 * Counts and update record for quest_submissions
 * @param unknown $sid
 * @return unknown
 */
function quest_update_submission_counts($sid) {
    global $DB, $message;
    $submission = $DB->get_record("quest_submissions", array('id' => $sid), '*', MUST_EXIST);
    $na = quest_count_submission_answers($sid);
    $naa = quest_count_submission_answers_assesed($sid);
    $nac = quest_count_submission_answers_correct($sid);

    $message .= "<p>Submission $sid has $na answers ($naa assessed) ($nac correct)</p>";

    $submission->nanswers = $na;
    $submission->nanswersassessed = $naa;
    $submission->nanswerscorrect = $nac;
    $submission = quest_calculate_pointsanswercorrect_and_date($submission);
    $DB->update_record('quest_submissions', $submission);
    return $submission;
}

/**
 * @param $sid submission id
 */
function quest_count_submission_answers($sid) {
    global $DB;
    if ($query = $DB->get_record_select("quest_answers", "submissionid=?", array($sid), "count(*) as num")) {
        return $query->num;
    } else {
        return 0;
    }
}

function quest_count_submission_answers_assesed($sid) {
    global $DB;
    if ($query = $DB->get_record_select("quest_answers", "submissionid=? and phase>0", array($sid), "count(*) as num")) {
        return $query->num;
    } else {
        return 0;
    }
}

function quest_count_submission_answers_correct($sid) {
    global $DB;
    if ($query = $DB->get_record_select("quest_answers", "submissionid=? and grade>=50", array($sid), "count(grade) as num")) {
        return $query->num;
    } else {
        return 0;
    }
}

function quest_recalculate_all_submissions_stats() {
    global $CFG;
    $sql = "update mdl_quest_submissions set nanswers=" .
            "(select count(*) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id )";
    $sql2 = "update mdl_quest_submissions set nanswerscorrect=" .
            "(select count(*) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id and ans.grade>50 )";
    $sql3 = "update mdl_quest_submissions set nanswersassessed=" .
            "(select count(*) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id and ans.phase>0 )";
    $sql4 = "update mdl_quest_submissions set dateanswercorrect=(select min(date) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id and ans.grade>=50)";

    echo "Recalculating nanwers, nanswerscorrect, dateanswercorrect";

    execute_sql(str_replace("mdl_", $CFG->prefix, $sql));
    execute_sql(str_replace("mdl_", $CFG->prefix, $sql2));
    execute_sql(str_replace("mdl_", $CFG->prefix, $sql3));
    execute_sql(str_replace("mdl_", $CFG->prefix, $sql4));
}