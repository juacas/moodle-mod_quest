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
    public static function update_challenge_schedule(
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

        // =========================================================================
        // Phase 1: Atomic Pre-validation & Dependency Safeguards.
        // =========================================================================
        foreach ($schedules as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $datestart = isset($item['datestart']) ? (int)$item['datestart'] : 0;
            $dateend = isset($item['dateend']) ? (int)$item['dateend'] : 0;

            if ($id <= 0) {
                $errors[] = get_string('scheduleerrorinvaliditem', 'quest');
                continue;
            }

            $submission = $DB->get_record('quest_submissions', ['id' => $id, 'questid' => $quest->id]);
            if (!$submission) {
                $errors[] = get_string('scheduleerrornotfound', 'quest', $id);
                continue;
            }

            $chtitle = format_string($submission->title);

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
            if (!empty($submission->dateanswercorrect) && (int)$submission->dateanswercorrect > 0) {
                $inflection = (int)$submission->dateanswercorrect;
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
                'submission' => $submission,
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

        // =========================================================================
        // Phase 2: Tournament Boundary Expansion / Synchronization.
        // =========================================================================
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

        // =========================================================================
        // Phase 3: Delegated Transaction & Synchronized Event Updates.
        // =========================================================================
        $updated = 0;
        $transaction = $DB->start_delegated_transaction();

        try {
            $eventuser = (!empty($USER) && !empty($USER->id)) ? $USER : get_admin();

            foreach ($validateditems as $item) {
                /** @var stdClass $sub */
                $sub = $item['submission'];
                $sub->datestart = $item['datestart'];
                $sub->dateend = $item['dateend'];

                $DB->update_record('quest_submissions', $sub);

                // Update challenge calendar events in {event} (openchallenge and closechallenge).
                if (function_exists('quest_update_challenge_calendar')) {
                    quest_update_challenge_calendar($cm, $quest, $sub);
                }

                // Trigger challenge updated event.
                if (class_exists('\mod_quest\event\challenge_updated') && $eventuser) {
                    \mod_quest\event\challenge_updated::create_from_parts($eventuser, $sub, $cm)->trigger();
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
            'quest_updated' => $questneedsupdate,
            'quest_datestart' => $newqueststart,
            'quest_dateend' => $newquestend,
            'errors' => [],
        ];
    }
}

