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
use context_module;

/**
 * Service managing tournament lifecycle, challenges, answers and submissions.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tournament_manager {

    /** Challenge phases / states. */
    public const STATE_PENDING_APPROVAL = 0;
    public const STATE_ACTIVE = 1;
    public const STATE_CLOSED = 2;

    /**
     * Get active challenges for a quest tournament.
     *
     * @param int $questid
     * @param int|null $currentuserid
     * @return array List of challenge objects.
     */
    public static function get_challenges(int $questid, ?int $currentuserid = null): array {
        global $DB;

        $params = ['questid' => $questid];
        $sql = "SELECT s.*, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                  FROM {quest_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.questid = :questid
              ORDER BY s.datestart DESC, s.id DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Count submission answers and update aggregated counts.
     *
     * @param int $submissionid
     * @return stdClass Updated submission object.
     */
    public static function update_submission_counts(int $submissionid): stdClass {
        global $DB;

        $submission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);

        $na = $DB->count_records('quest_answers', ['submissionid' => $submissionid]);
        $naa = $DB->count_records_select('quest_answers', 'submissionid = :sid AND phase > 0', ['sid' => $submissionid]);
        $nac = $DB->count_records_select('quest_answers', 'submissionid = :sid AND grade >= 50', ['sid' => $submissionid]);

        $submission->nanswers = $na;
        $submission->nanswerscorrect = $nac;

        // Check first correct answer date and points.
        $correctanswers = $DB->get_records_select(
            'quest_answers',
            'submissionid = :sid AND grade >= 50',
            ['sid' => $submissionid],
            'date ASC',
            'id, date, pointsmax',
            0,
            1
        );

        if (!empty($correctanswers)) {
            $firstcorrect = reset($correctanswers);
            $submission->dateanswercorrect = (int)$firstcorrect->date;
            $submission->pointsanswercorrect = (float)$firstcorrect->pointsmax;
        } else {
            $submission->dateanswercorrect = 0;
            $submission->pointsanswercorrect = 0.0;
        }

        $DB->update_record('quest_submissions', $submission);
        return $submission;
    }

    /**
     * Validate whether a user can submit an answer to a challenge.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param int $userid
     * @return array [bool $allowed, string $reason]
     */
    public static function can_submit_answer(stdClass $quest, stdClass $submission, int $userid): array {
        global $DB;

        $timenow = time();

        // Author cannot answer own challenge.
        if ($submission->userid == $userid) {
            return [false, get_string('cannotanswerownchallenge', 'quest')];
        }

        // Must be active.
        if ($timenow < $submission->datestart) {
            return [false, get_string('challengenotstarted', 'quest')];
        }

        if ($timenow > $submission->dateend) {
            return [false, get_string('challengeclosed', 'quest')];
        }

        // Check user answer count.
        if ($quest->nmaxanswers > 0) {
            $useranswercount = $DB->count_records('quest_answers', [
                'submissionid' => $submission->id,
                'userid' => $userid,
            ]);
            if ($useranswercount >= $quest->nmaxanswers) {
                return [false, get_string('maxanswersreached', 'quest')];
            }
        }

        return [true, ''];
    }

    /**
     * Record a new answer and update scoring.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param int $userid
     * @param string $title
     * @param string $description
     * @param int $descriptionformat
     * @param float $grade
     * @param int $phase
     * @param int $questionusageid Question Engine usage id, if applicable.
     * @return stdClass Newly created answer record.
     */
    public static function record_answer(
        stdClass $quest,
        stdClass $submission,
        int $userid,
        string $title,
        string $description,
        int $descriptionformat = FORMAT_HTML,
        float $grade = 0.0,
        int $phase = 0,
        int $questionusageid = 0
    ): stdClass {
        global $DB;

        $timenow = time();
        $tinitial = (int)($quest->tinitial * 86400);

        // Calculate maximum points available at this moment.
        $pointsmax = scoring_calculator::calculate_points(
            $timenow,
            (int)$submission->datestart,
            (int)$submission->dateend,
            $tinitial,
            !empty($submission->dateanswercorrect) ? (int)$submission->dateanswercorrect : null,
            (float)$submission->initialpoints,
            (float)$submission->pointsmax,
            (float)$submission->pointsmin
        );

        $answer = new stdClass();
        $answer->questid = (int)$quest->id;
        $answer->submissionid = (int)$submission->id;
        $answer->userid = $userid;
        $answer->title = $title;
        $answer->description = $description;
        $answer->descriptionformat = $descriptionformat;
        $answer->descriptiontrust = 0;
        $answer->attachment = '';
        $answer->date = $timenow;
        $answer->pointsmax = $pointsmax;
        $answer->grade = $grade;
        $answer->commentforteacher = '';
        $answer->phase = $phase;
        $answer->state = 0;
        $answer->permitsubmit = 0;
        $answer->perceiveddifficulty = -1;
        $answer->questionusageid = $questionusageid;

        $answer->id = $DB->insert_record('quest_answers', $answer);

        // Update submission aggregations and inflection points.
        self::update_submission_counts($submission->id);

        // Update user achievement points.
        leaderboard_service::update_user_scores($quest, $userid);
        if ($submission->userid != $userid) {
            leaderboard_service::update_user_scores($quest, $submission->userid);
        }

        return $answer;
    }
}
