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

namespace mod_quest\service;

use stdClass;

/**
 * Service managing individual and team leaderboard scores, ranks and achievements.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class leaderboard_service {

    /**
     * Calculate user points earned from assessed answers.
     *
     * @param int $questid
     * @param int|array $userid Single user ID or array of user IDs.
     * @return float Total points earned.
     */
    public static function calculate_user_answer_score(int $questid, int|array $userid): float {
        global $DB;

        if (empty($userid)) {
            return 0.0;
        }

        $userids = is_array($userid) ? $userid : [$userid];
        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $params = array_merge([$questid], $inparams);

        // Answers with approved assessments (phase 2) OR auto-graded answers (phase 3 / autograded).
        $sql = "SELECT SUM(ans.grade * ans.pointsmax / 100) AS points
                  FROM {quest_answers} ans
                  LEFT JOIN {quest_assessments} assess ON ans.id = assess.answerid
                 WHERE ans.questid = ?
                   AND ans.userid $insql
                   AND (assess.phase = 2 OR ans.phase >= 2)";

        $record = $DB->get_record_sql($sql, $params);
        return $record && isset($record->points) ? (float)$record->points : 0.0;
    }

    /**
     * Calculate user points earned as author of challenges.
     *
     * @param int $questid
     * @param int|array $userid
     * @return float Total author points earned.
     */
    public static function calculate_user_author_score(int $questid, int|array $userid): float {
        global $DB;

        if (empty($userid)) {
            return 0.0;
        }

        $userids = is_array($userid) ? $userid : [$userid];
        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $params = array_merge([$questid], $inparams);

        $sql = "SELECT s.id
                  FROM {quest_submissions} s
                 WHERE s.questid = ? AND s.userid $insql";

        $submissions = $DB->get_fieldset_sql($sql, $params);
        if (empty($submissions)) {
            return 0.0;
        }

        [$sinsql, $sinparams] = $DB->get_in_or_equal($submissions);
        $records = $DB->get_record_sql(
            "SELECT SUM(points) AS points FROM {quest_assessments_autors} WHERE submissionid $sinsql",
            $sinparams
        );

        return $records && isset($records->points) ? (float)$records->points : 0.0;
    }

    /**
     * Update user qualification record with answer, author and total points.
     *
     * @param stdClass $quest
     * @param int $userid
     * @return stdClass Updated quest_calification_users record.
     */
    public static function update_user_scores(stdClass $quest, int $userid): stdClass {
        global $DB;

        $cal = $DB->get_record('quest_calification_users', ['questid' => $quest->id, 'userid' => $userid]);
        if (!$cal) {
            $cal = new stdClass();
            $cal->questid = (int)$quest->id;
            $cal->userid = $userid;
            $cal->teamid = 0;
            $cal->points = 0.0;
            $cal->nanswers = 0;
            $cal->nanswersassessment = 0;
            $cal->nsubmissions = 0;
            $cal->nsubmissionsassessment = 0;
            $cal->pointssubmission = 0.0;
            $cal->pointsanswers = 0.0;
            $cal->id = $DB->insert_record('quest_calification_users', $cal);
        }

        $cal->pointsanswers = self::calculate_user_answer_score($quest->id, $userid);
        $cal->pointssubmission = self::calculate_user_author_score($quest->id, $userid);
        $cal->nanswers = (int)$DB->count_records('quest_answers', ['questid' => $quest->id, 'userid' => $userid]);
        $cal->nanswersassessment = (int)$DB->count_records_select(
            'quest_answers',
            'questid = :qid AND userid = :uid AND phase > 0',
            ['qid' => $quest->id, 'uid' => $userid]
        );
        $cal->nsubmissions = (int)$DB->count_records('quest_submissions', ['questid' => $quest->id, 'userid' => $userid]);
        $cal->nsubmissionsassessment = (int)$DB->count_records_select(
            'quest_submissions',
            'questid = :qid AND userid = :uid AND evaluated = 1',
            ['qid' => $quest->id, 'uid' => $userid]
        );
        $cal->points = $cal->pointssubmission + $cal->pointsanswers;

        $DB->update_record('quest_calification_users', $cal);

        // Update team score if user belongs to a team.
        if (!empty($quest->allowteams) && !empty($cal->teamid)) {
            self::update_team_scores($quest->id, (int)$cal->teamid);
        }

        return $cal;
    }

    /**
     * Update team scores based on members' individual points.
     *
     * @param int $questid
     * @param int $teamid
     * @return stdClass|null Updated team qualification record, or null if no members.
     */
    public static function update_team_scores(int $questid, int $teamid): ?stdClass {
        global $DB;

        $members = $DB->get_fieldset_select(
            'quest_calification_users',
            'userid',
            'questid = :qid AND teamid = :tid',
            ['qid' => $questid, 'tid' => $teamid]
        );

        if (empty($members)) {
            return null;
        }

        $pointsanswers = self::calculate_user_answer_score($questid, $members);
        $pointssubmission = self::calculate_user_author_score($questid, $members);

        [$minsql, $minparams] = $DB->get_in_or_equal($members);
        $params = array_merge([$questid], $minparams);

        $nanswers = (int)$DB->count_records_select('quest_answers', "questid = ? AND userid $minsql", $params);
        $nanswerassessment = (int)$DB->count_records_select(
            'quest_answers',
            "questid = ? AND userid $minsql AND phase > 0",
            $params
        );
        $nsubmissions = (int)$DB->count_records_select('quest_submissions', "questid = ? AND userid $minsql", $params);
        $nsubmissionsassessment = (int)$DB->count_records_select(
            'quest_submissions',
            "questid = ? AND userid $minsql AND evaluated = 1",
            $params
        );

        $calteam = $DB->get_record('quest_calification_teams', ['questid' => $questid, 'teamid' => $teamid]);
        if (!$calteam) {
            $calteam = new stdClass();
            $calteam->questid = $questid;
            $calteam->teamid = $teamid;
            $calteam->id = $DB->insert_record('quest_calification_teams', $calteam);
        }

        $calteam->points = $pointssubmission + $pointsanswers;
        $calteam->pointsanswers = $pointsanswers;
        $calteam->pointssubmission = $pointssubmission;
        $calteam->nanswers = $nanswers;
        $calteam->nanswerassessment = $nanswerassessment;
        $calteam->nsubmissions = $nsubmissions;
        $calteam->nsubmissionsassessment = $nsubmissionsassessment;

        $DB->update_record('quest_calification_teams', $calteam);
        return $calteam;
    }

    /**
     * Fetch individual leaderboard standings.
     *
     * @param int $questid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_individual_standings(int $questid, int $limit = 50, int $offset = 0): array {
        global $DB;

        $userfields = \core_user\fields::for_userpic()->with_name()->including('email');
        $userselects = $userfields->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT qcu.*, {$userselects}, t.name AS teamname
                  FROM {quest_calification_users} qcu
                  JOIN {user} u ON u.id = qcu.userid
             LEFT JOIN {quest_teams} t ON t.id = qcu.teamid
                 WHERE qcu.questid = :questid
              ORDER BY qcu.points DESC, qcu.nanswers DESC";

        $records = $DB->get_records_sql($sql, ['questid' => $questid], $offset, $limit);

        $rank = $offset + 1;
        $result = [];
        foreach ($records as $r) {
            $r->rank = $rank++;
            $r->points = round((float)$r->points, 2);
            $result[] = $r;
        }

        return $result;
    }

    /**
     * Fetch team leaderboard standings.
     *
     * @param int $questid
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_team_standings(int $questid, int $limit = 50, int $offset = 0): array {
        global $DB;

        $sql = "SELECT qct.*, t.name, t.ncomponents
                  FROM {quest_calification_teams} qct
                  JOIN {quest_teams} t ON t.id = qct.teamid
                 WHERE qct.questid = :questid
              ORDER BY qct.points DESC, qct.nanswers DESC";

        $records = $DB->get_records_sql($sql, ['questid' => $questid], $offset, $limit);

        $rank = $offset + 1;
        $result = [];
        foreach ($records as $r) {
            $r->rank = $rank++;
            $r->points = round((float)$r->points, 2);
            $result[] = $r;
        }

        return $result;
    }
}
