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
    /** Challenge is waiting for approval. */
    public const STATE_PENDING_APPROVAL = 0;
    /** Challenge is available for answers. */
    public const STATE_ACTIVE = 1;
    /** Challenge is no longer active. */
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
     * @param int $challengeid
     * @return stdClass Updated submission object.
     */
    public static function update_challenge_counts(int $challengeid): stdClass {
        global $DB;

        $challenge = $DB->get_record('quest_submissions', ['id' => $challengeid], '*', MUST_EXIST);

        $na = $DB->count_records('quest_answers', ['submissionid' => $challengeid]);
        $naa = $DB->count_records_select('quest_answers', 'submissionid = :sid AND phase > 0', ['sid' => $challengeid]);
        $nac = $DB->count_records_select('quest_answers', 'submissionid = :sid AND grade >= 50', ['sid' => $challengeid]);

        $challenge->nanswers = $na;
        $challenge->nanswerscorrect = $nac;

        // Check first correct answer date and points.
        $correctanswers = $DB->get_records_select(
            'quest_answers',
            'submissionid = :sid AND grade >= 50',
            ['sid' => $challengeid],
            'date ASC',
            'id, date, pointsmax',
            0,
            1
        );

        if (!empty($correctanswers)) {
            $firstcorrect = reset($correctanswers);
            $challenge->dateanswercorrect = (int)$firstcorrect->date;
            $challenge->pointsanswercorrect = (float)$firstcorrect->pointsmax;
        } else {
            $challenge->dateanswercorrect = 0;
            $challenge->pointsanswercorrect = 0.0;
        }

        $DB->update_record('quest_submissions', $challenge);
        return $challenge;
    }

    /**
     * Validate whether a user can submit an answer to a challenge.
     *
     * @param stdClass $quest
     * @param stdClass $challenge
     * @param int $userid
     * @return array [bool $allowed, string $reason]
     */
    public static function can_submit_answer(stdClass $quest, stdClass $challenge, int $userid): array {
        global $DB;

        $timenow = time();

        // Author cannot answer own challenge.
        if ($challenge->userid == $userid) {
            return [false, get_string('cannotanswerownchallenge', 'quest')];
        }

        // Must be active.
        if ($timenow < $challenge->datestart) {
            return [false, get_string('challengenotstarted', 'quest')];
        }

        if ($timenow > $challenge->dateend) {
            return [false, get_string('challengeclosed', 'quest')];
        }

        // Check user answer count.
        if ($quest->nmaxanswers > 0) {
            $useranswercount = $DB->count_records('quest_answers', [
                'submissionid' => $challenge->id,
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
     * @param stdClass $challenge
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
        stdClass $challenge,
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
            (int)$challenge->datestart,
            (int)$challenge->dateend,
            $tinitial,
            !empty($challenge->dateanswercorrect) ? (int)$challenge->dateanswercorrect : null,
            (float)$challenge->initialpoints,
            (float)$challenge->pointsmax,
            (float)$challenge->pointsmin
        );

        $answer = new stdClass();
        $answer->questid = (int)$quest->id;
        $answer->submissionid = (int)$challenge->id;
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
        self::update_challenge_counts($challenge->id);

        // Update user achievement points.
        leaderboard_service::update_user_scores($quest, $userid);
        if ($challenge->userid != $userid) {
            leaderboard_service::update_user_scores($quest, $challenge->userid);
        }

        return $answer;
    }

    /**
     * Update schedules for multiple challenges in a tournament safely with
     * calendar events and dependency updates.
     *
     * @param stdClass $quest The quest tournament record.
     * @param object $cm Course module object.
     * @param array $schedules Array of items, each having 'id', 'datestart', 'dateend'.
     * @param bool $autoexpand Whether to automatically expand tournament dates if challenges extend beyond.
     * @return array Result array with status, updated count, errors, and updated quest boundaries.
     */
    public static function update_challenge_schedules(
        stdClass $quest,
        object $cm,
        array $schedules,
        bool $autoexpand = true
    ): array {
        global $DB, $USER;
        require_once(__DIR__ . '/../../locallib.php');

        $errors = [];
        $validateditems = [];
        $queststart = (int)$quest->datestart;
        $questend = (int)$quest->dateend;
        $minchallengestart = PHP_INT_MAX;
        $maxchallengeend = 0;

        $dateformat = get_string('strftimedatetimeshort', 'langconfig');

        // Phase 1: Atomic pre-validation and dependency safeguards.
        foreach ($schedules as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $datestart = isset($item['datestart']) ? (int)$item['datestart'] : 0;
            $dateend = isset($item['dateend']) ? (int)$item['dateend'] : 0;

            if ($id <= 0) {
                $errors[] = get_string('scheduleerrorinvaliditem', 'quest');
                continue;
            }

            $challenge = $DB->get_record('quest_submissions', ['id' => $id, 'questid' => $quest->id]);
            if (!$challenge) {
                $errors[] = get_string('scheduleerrornotfound', 'quest', $id);
                continue;
            }

            // Ignore unchanged dates so no database, calendar or event update is generated.
            if ((int)$challenge->datestart === $datestart && (int)$challenge->dateend === $dateend) {
                continue;
            }

            $chtitle = format_string($challenge->title);

            // 1. Positive timestamps and order coherence check.
            if ($datestart <= 0 || $dateend <= 0 || $datestart >= $dateend) {
                $errors[] = get_string('scheduleerrordatesinvalid', 'quest', $chtitle);
                continue;
            }

            // Minimum duration safeguard (at least 60 seconds).
            if (($dateend - $datestart) < 60) {
                $errors[] = get_string('scheduleerrordatesinvalid', 'quest', $chtitle);
                continue;
            }

            // 2. Dependency check with existing student answers.
            $answerstats = $DB->get_record_sql(
                "SELECT MIN(date) AS min_date, MAX(date) AS max_date, COUNT(id) AS cnt
                   FROM {quest_answers}
                  WHERE submissionid = :sid",
                ['sid' => $id]
            );

            if ($answerstats && (int)$answerstats->cnt > 0) {
                $firstanswerdate = (int)$answerstats->min_date;
                $lastanswerdate = (int)$answerstats->max_date;

                // Challenge cannot start after answers have already been submitted.
                if ($datestart > $firstanswerdate) {
                    $errors[] = get_string('scheduleerroranswerbeforestart', 'quest', (object)[
                        'title' => $chtitle,
                        'date' => userdate($firstanswerdate, $dateformat),
                    ]);
                }

                // Challenge cannot close before answers were submitted.
                if ($dateend < $lastanswerdate) {
                    $errors[] = get_string('scheduleerroranswerafterend', 'quest', (object)[
                        'title' => $chtitle,
                        'date' => userdate($lastanswerdate, $dateformat),
                    ]);
                }
            }

            // 3. Dependency check with correct answer inflection point.
            if (!empty($challenge->dateanswercorrect) && (int)$challenge->dateanswercorrect > 0) {
                $inflection = (int)$challenge->dateanswercorrect;
                if ($datestart > $inflection || $dateend < $inflection) {
                    $errors[] = get_string('scheduleerroranswercorrect', 'quest', (object)[
                        'title' => $chtitle,
                        'date' => userdate($inflection, $dateformat),
                    ]);
                }
            }

            // Track tournament boundary limits.
            if ($datestart < $minchallengestart) {
                $minchallengestart = $datestart;
            }
            if ($dateend > $maxchallengeend) {
                $maxchallengeend = $dateend;
            }

            $validateditems[] = [
                'challenge' => $challenge,
                'datestart' => $datestart,
                'dateend' => $dateend,
            ];
        }

        // If any item failed validation, abort immediately (zero side-effects).
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => reset($errors),
                'errors' => $errors,
                'updated' => 0,
            ];
        }

        if (empty($validateditems)) {
            return [
                'success' => true,
                'message' => get_string('schedulesavedcount', 'quest', 0),
                'updated' => 0,
                'parent_dates_updated' => false,
                'quest_datestart' => $queststart,
                'quest_dateend' => $questend,
                'errors' => [],
                'schedules' => [],
            ];
        }

        // Phase 2: Tournament boundary expansion and synchronization.
        $questneedsupdate = false;
        $newqueststart = $queststart;
        $newquestend = $questend;

        if ($autoexpand && !empty($validateditems)) {
            if ($queststart > 0 && $minchallengestart < $queststart) {
                $newqueststart = $minchallengestart;
                $questneedsupdate = true;
            }
            if ($questend > 0 && $maxchallengeend > $questend) {
                $newquestend = $maxchallengeend;
                $questneedsupdate = true;
            }
        }

        // Phase 3: Delegated transaction and synchronized event updates.
        $updated = 0;
        $persisted = [];
        $transaction = $DB->start_delegated_transaction();

        try {
            $eventuser = (!empty($USER) && !empty($USER->id)) ? $USER : get_admin();

            foreach ($validateditems as $item) {
                /** @var stdClass $challenge */
                $challenge = $item['challenge'];
                $challenge->datestart = $item['datestart'];
                $challenge->dateend = $item['dateend'];

                if (!$DB->update_record('quest_submissions', $challenge)) {
                    throw new \dml_exception('dmlwriteexception');
                }
                $stored = $DB->get_record('quest_submissions', ['id' => $challenge->id], 'id, datestart, dateend', MUST_EXIST);
                if (
                    (int)$stored->datestart !== (int)$challenge->datestart ||
                        (int)$stored->dateend !== (int)$challenge->dateend
                ) {
                    throw new \dml_exception('dmlwriteexception');
                }
                $persisted[] = [
                    'id' => (int)$stored->id,
                    'datestart' => (int)$stored->datestart,
                    'dateend' => (int)$stored->dateend,
                ];

                // Update challenge calendar events in {event} (openchallenge and closechallenge).
                if (function_exists('quest_update_challenge_calendar')) {
                    quest_update_challenge_calendar($cm, $quest, $challenge);
                }

                // Trigger challenge updated event.
                if (class_exists('\mod_quest\event\challenge_updated') && $eventuser) {
                    \mod_quest\event\challenge_updated::create_from_parts($eventuser, $challenge, $cm)->trigger();
                }

                $updated++;
            }

            // Synchronize parent tournament dates and events if bounds expanded.
            if ($questneedsupdate) {
                $quest->datestart = $newqueststart;
                $quest->dateend = $newquestend;
                $DB->update_record('quest', $quest);

                if (function_exists('quest_update_quest_calendar')) {
                    quest_update_quest_calendar($quest, $cm);
                }
                if (function_exists('quest_update_grades')) {
                    quest_update_grades($quest);
                }
            }

            // Trigger core course module update & rebuild course cache.
            if (class_exists('\core\event\course_module_updated')) {
                \core\event\course_module_updated::create_from_cm($cm)->trigger();
            }
            rebuild_course_cache($cm->course, true);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()],
                'updated' => 0,
            ];
        }

        $msg = get_string('schedulesavedcount', 'quest', $updated);
        if ($questneedsupdate) {
            $headerdateformat = get_string('strftimedatetime', 'langconfig');
            $msg .= ' ' . get_string('scheduleboundaryexpanded', 'quest', (object)[
                'start' => userdate($newqueststart, $headerdateformat),
                'end' => userdate($newquestend, $headerdateformat),
            ]);
        }

        return [
            'success' => true,
            'message' => $msg,
            'updated' => $updated,
            'parent_dates_updated' => $questneedsupdate,
            'quest_datestart' => $newqueststart,
            'quest_dateend' => $newquestend,
            'errors' => [],
            'schedules' => $persisted,
        ];
    }
}
