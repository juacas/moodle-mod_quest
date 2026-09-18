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
use context;
use question_engine;
use question_display_options;
use mod_quest\question\question_reference_service;

/**
 * Service managing Question Engine integration and automatic grading for Quest.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autograde_service {

    /**
     * Start or load an existing question attempt for a user on a challenge.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param int $userid
     * @param context $context
     * @return array [question_usage_by_activity $quba, int $slot]
     */
    public static function get_or_create_attempt(
        stdClass $quest,
        stdClass $submission,
        int $userid,
        context $context
    ): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $question = question_reference_service::get_question_for_challenge((int)$submission->id);
        if (!$question) {
            throw new \moodle_exception('errornotquestionbankchallenge', 'quest');
        }

        // Check if there is an existing unfinished attempt or past answer.
        $lastanswer = $DB->get_record_sql("
            SELECT * FROM {quest_answers}
            WHERE submissionid = ? AND userid = ?
            ORDER BY id DESC
        ", [$submission->id, $userid], IGNORE_MULTIPLE);

        if ($lastanswer && !empty($lastanswer->questionusageid)) {
            if ($lastanswer->phase == 0) { // Unfinished!
                $quba = question_engine::load_questions_usage_by_activity($lastanswer->questionusageid);
                return [$quba, 1];
            }
        }

        // Create new attempt usage.
        $quba = question_engine::make_questions_usage_by_activity('mod_quest', $context);
        $quba->set_preferred_behaviour('deferredfeedback');

        $loadedquestion = \question_bank::load_question($question->id);
        $slot = $quba->add_question($loadedquestion, $submission->pointsmax);
        $quba->start_all_questions();

        question_engine::save_questions_usage_by_activity($quba);

        // Create initial answer record to persist the usage ID.
        $answer = new stdClass();
        $answer->questid = (int)$quest->id;
        $answer->submissionid = (int)$submission->id;
        $answer->userid = $userid;
        $answer->title = get_string('answer', 'quest') . ' - In progress';
        $answer->description = get_string('questionbank', 'quest') . ' autograded attempt';
        $answer->descriptionformat = FORMAT_PLAIN;
        $answer->descriptiontrust = 0;
        $answer->attachment = '';
        $answer->date = time();
        $answer->pointsmax = $submission->pointsmax; 
        $answer->grade = 0;
        $answer->commentforteacher = '';
        $answer->phase = 0; // Phase 0 = UNGRADED
        $answer->state = 0;
        $answer->permitsubmit = 0;
        $answer->perceiveddifficulty = -1;
        $answer->questionusageid = $quba->get_id();

        $answer->id = $DB->insert_record('quest_answers', $answer);

        return [$quba, $slot];
    }

    /**
     * Render the question engine question to HTML for display in answer form.
     *
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param bool $readonly Whether question inputs should be disabled (e.g. after finish).
     * @return string HTML output
     */
    public static function render_question(\question_usage_by_activity $quba, int $slot = 1, bool $readonly = false): string {
        $options = new question_display_options();
        $options->readonly = $readonly;
        $options->flags = question_display_options::HIDDEN;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->generalfeedback = $readonly ? question_display_options::VISIBLE : question_display_options::HIDDEN;
        $options->correctness = question_display_options::VISIBLE;

        return $quba->render_question($slot, $options);
    }

    /**
     * Process student answer submission, grade automatically, and award points.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param int $userid
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @return array [bool $passed, float $points, float $fraction, string $message]
     */
    public static function process_submission(
        stdClass $quest,
        stdClass $submission,
        int $userid,
        \question_usage_by_activity $quba,
        int $slot = 1
    ): array {
        global $DB;

        $timenow = time();
        $quba->process_all_actions($timenow);
        $quba->finish_all_questions($timenow);
        question_engine::save_questions_usage_by_activity($quba);

        $question = $quba->get_question($slot);
        $state = $quba->get_question_state($slot);
        $ismanual = $question->qtype->is_manual_graded() || ($state == \question_state::$needsgrading);

        // Update the existing answer record.
        $answer = $DB->get_record('quest_answers', [
            'questionusageid' => $quba->get_id()
        ], '*', MUST_EXIST);

        $tinitial = (int)($quest->tinitial * 86400);
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

        $answer->title = get_string('answer', 'quest') . ' - ' . userdate($timenow, get_string('strftimedatetime', 'langconfig'));
        $answer->date = $timenow;
        $answer->pointsmax = $pointsmax;

        if ($ismanual) {
            $answer->phase = 0; // ANSWER_PHASE_UNGRADED = Pending evaluation
            $answer->grade = 0.0;
            $DB->update_record('quest_answers', $answer);

            tournament_manager::update_submission_counts($submission->id);

            return [
                'passed' => true,
                'grade' => 0.0,
                'points' => 0.0,
                'fraction' => 0.0,
                'message' => get_string('autograde_manual_pending', 'quest'),
                'answerid' => $answer->id,
            ];
        }

        $fraction = $quba->get_question_fraction($slot);
        if ($fraction === null) {
            $fraction = 0.0;
        }

        $grade = round($fraction * 100, 2);
        $passed = ($grade >= 50.0);

        $answer->grade = $grade;
        $answer->phase = 3; // Phase 3 = auto-evaluated / approved.
        $DB->update_record('quest_answers', $answer);

        // Update submission aggregations and inflection points.
        tournament_manager::update_submission_counts($submission->id);

        // Update user achievement points.
        leaderboard_service::update_user_scores($quest, $userid);
        if ($submission->userid != $userid) {
            leaderboard_service::update_user_scores($quest, $submission->userid);
        }

        $points = 0.0;
        if ($passed) {
            $points = (float)$answer->pointsmax;
            $message = get_string('autograde_passed', 'quest', round($points, 2));
        } else {
            $message = get_string('autograde_failed', 'quest');
        }

        return [
            'passed' => $passed,
            'grade' => $grade,
            'points' => $points,
            'fraction' => $fraction,
            'message' => $message,
            'answerid' => $answer->id,
        ];
    }
}
