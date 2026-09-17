<?php
// This file is part of Questournament activity for Moodle - http://moodle.org/
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
 * Score recalculation helpers and backward compatibility wrappers.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_quest\service\tournament_manager;
use mod_quest\service\leaderboard_service;

/**
 * Recalculate scores for all teams in a quest instance.
 *
 * @param int $questid
 */
function updateallteams($questid) {
    global $DB;
    $teams = $DB->get_records('quest_teams', ['questid' => $questid]);
    $idteams = [];
    foreach ($teams as $team) {
        $idteams[] = $team->id;
        leaderboard_service::update_team_scores($questid, $team->id);
    }
    // Clean orphan records.
    if (!empty($idteams)) {
        [$insql, $inparams] = $DB->get_in_or_equal($idteams, SQL_PARAMS_NAMED, 'param', false);
        $DB->delete_records_select('quest_calification_teams', "questid = :qid AND teamid $insql", array_merge(['qid' => $questid], $inparams));
    } else {
        $DB->delete_records('quest_calification_teams', ['questid' => $questid]);
    }
}

/**
 * Recalculate scores for all users in a quest instance.
 *
 * @param int $questid
 */
function updateallusers($questid) {
    global $DB;
    $quest = $DB->get_record('quest', ['id' => $questid], '*', MUST_EXIST);
    $usercals = $DB->get_records('quest_calification_users', ['questid' => $questid]);
    foreach ($usercals as $usercal) {
        leaderboard_service::update_user_scores($quest, (int)$usercal->userid);
    }
}

/**
 * Update and calculate inflection date and points for first correct answer.
 *
 * @param stdClass $submission
 * @return stdClass Updated submission
 */
function quest_calculate_pointsanswercorrect_and_date($submission) {
    global $DB;
    $query = $DB->get_records_select('quest_answers', 'submissionid = ? AND grade >= 50', [$submission->id], 'date ASC', 'id, date, pointsmax', 0, 1);
    if (!empty($query)) {
        $first = reset($query);
        $submission->dateanswercorrect = $first->date;
        $submission->pointsanswercorrect = $first->pointsmax;
    } else {
        $submission->dateanswercorrect = 0;
        $submission->pointsanswercorrect = 0;
    }
    return $submission;
}

/**
 * Counts and updates aggregated counts for a challenge.
 *
 * @param int $cid Challenge ID
 * @return stdClass
 */
function quest_update_challenge_counts($cid) {
    return tournament_manager::update_submission_counts($cid);
}

/**
 * Counts and updates aggregated counts for a submission (legacy wrapper).
 *
 * @param int $sid Submission ID
 * @return stdClass
 */
function quest_update_submission_counts($sid) {
    return quest_update_challenge_counts($sid);
}

/**
 * Count total answers for a challenge.
 *
 * @param int $cid
 * @return int
 */
function quest_count_challenge_answers($cid) {
    global $DB;
    return (int)$DB->count_records('quest_answers', ['submissionid' => $cid]);
}

/**
 * Count total answers for a submission (legacy wrapper).
 *
 * @param int $sid
 * @return int
 */
function quest_count_submission_answers($sid) {
    return quest_count_challenge_answers($sid);
}

/**
 * Count assessed answers for a challenge.
 *
 * @param int $cid
 * @return int
 */
function quest_count_challenge_answers_assesed($cid) {
    global $DB;
    return (int)$DB->count_records_select('quest_answers', 'submissionid = ? AND phase > 0', [$cid]);
}

/**
 * Count assessed answers for a submission (legacy wrapper).
 *
 * @param int $sid
 * @return int
 */
function quest_count_submission_answers_assesed($sid) {
    return quest_count_challenge_answers_assesed($sid);
}

/**
 * Count correct answers for a challenge.
 *
 * @param int $cid
 * @return int
 */
function quest_count_challenge_answers_correct($cid) {
    global $DB;
    return (int)$DB->count_records_select('quest_answers', 'submissionid = ? AND grade >= 50', [$cid]);
}

/**
 * Count correct answers for a submission (legacy wrapper).
 *
 * @param int $sid
 * @return int
 */
function quest_count_submission_answers_correct($sid) {
    return quest_count_challenge_answers_correct($sid);
}

