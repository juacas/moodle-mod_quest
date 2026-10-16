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
     * Return the grade recorded by the question engine for an automatically graded answer.
     *
     * @param stdClass $answer Quest answer.
     * @return float|null Grade percentage, or null when automatic grading is unavailable.
     */
    public static function get_automatic_grade(stdClass $answer): ?float {
        global $CFG;

        if (empty($answer->questionusageid)) {
            return null;
        }
        require_once($CFG->libdir . '/questionlib.php');
        $quba = question_engine::load_questions_usage_by_activity((int)$answer->questionusageid);
        $weighted = 0.0;
        $totalmark = 0.0;
        foreach ($quba->get_slots() as $slot) {
            $question = $quba->get_question($slot);
            if ($question->qtype->is_manual_graded() || $quba->get_question_state($slot) == \question_state::$needsgrading) {
                return null;
            }
            $fraction = $quba->get_question_fraction($slot);
            if ($fraction === null) {
                return null;
            }
            $maxmark = $quba->get_question_max_mark($slot);
            $weighted += $fraction * $maxmark;
            $totalmark += $maxmark;
        }
        return $totalmark > 0 ? round(($weighted / $totalmark) * 100, 2) : null;
    }

    /**
     * Whether every question in a challenge can be graded by the question engine.
     *
     * @param int $challengeid Challenge ID.
     * @return bool
     */
    public static function is_fully_automatic_challenge(int $challengeid): bool {
        global $CFG;

        $questions = question_reference_service::get_challenge_questions($challengeid);
        if (!$questions) {
            return false;
        }
        require_once($CFG->libdir . '/questionlib.php');
        foreach ($questions as $item) {
            $qtype = \question_bank::get_qtype($item->question->qtype, false);
            if (!$qtype || $qtype->is_manual_graded()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Combine the question engine marks with the challenge rubric grade.
     *
     * The rubric scores the combined weight of manually graded questions. An
     * assessment of an entirely automatic attempt remains a manual override.
     *
     * @param stdClass $answer Answer with a saved question attempt, if applicable.
     * @param float $rubricfraction Rubric grade between zero and one.
     * @return float Combined grade as a fraction.
     */
    public static function combine_with_rubric_grade(stdClass $answer, float $rubricfraction): float {
        global $CFG;

        if (empty($answer->questionusageid)) {
            return $rubricfraction;
        }
        require_once($CFG->libdir . '/questionlib.php');
        $quba = question_engine::load_questions_usage_by_activity((int)$answer->questionusageid);
        $automaticpoints = 0.0;
        $manualmax = 0.0;
        $totalmax = 0.0;
        foreach ($quba->get_slots() as $slot) {
            $maxmark = (float)$quba->get_question_max_mark($slot);
            if ($maxmark <= 0) {
                continue;
            }
            $totalmax += $maxmark;
            $question = $quba->get_question($slot);
            if ($question->qtype->is_manual_graded() || $quba->get_question_state($slot) == \question_state::$needsgrading) {
                $manualmax += $maxmark;
            } else {
                $automaticpoints += (float)($quba->get_question_fraction($slot) ?? 0) * $maxmark;
            }
        }

        if ($manualmax <= 0 || $totalmax <= 0) {
            return $rubricfraction;
        }
        return ($automaticpoints + $manualmax * $rubricfraction) / $totalmax;
    }

    /**
     * Start or load an existing question attempt for a user on a challenge.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param int $userid
     * @param context $context
     * @return array [question_usage_by_activity $quba, int[] $slots]
     */
    public static function get_or_create_attempt(
        stdClass $quest,
        stdClass $submission,
        int $userid,
        context $context
    ): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $questions = question_reference_service::get_challenge_questions((int)$submission->id);
        if (!$questions) {
            throw new \moodle_exception('errornotquestionbankchallenge', 'quest');
        }

        // Check if there is an existing unfinished attempt or past answer.
        $lastanswer = $DB->get_record_sql("
            SELECT * FROM {quest_answers}
            WHERE submissionid = ? AND userid = ?
            ORDER BY id DESC
        ", [$submission->id, $userid], IGNORE_MULTIPLE);

        if ($lastanswer && !empty($lastanswer->questionusageid)) {
            // A permitted resubmission starts a new attempt. The previous
            // usage is deliberately kept so it remains part of the history.
            if ($lastanswer->phase == 0 && $lastanswer->permitsubmit != ANSWER_PERMITSUBMIT_EDITABLE) {
                $quba = question_engine::load_questions_usage_by_activity($lastanswer->questionusageid);
                $unfinished = false;
                foreach ($quba->get_slots() as $slot) {
                    if (!$quba->get_question_state($slot)->is_finished()) {
                        $unfinished = true;
                        break;
                    }
                }
                if (!$unfinished) {
                    throw new \moodle_exception('answerexisty', 'quest');
                }
                return [$quba, $quba->get_slots()];
            }
        }

        // Create new attempt usage.
        $quba = question_engine::make_questions_usage_by_activity('mod_quest', $context);
        $quba->set_preferred_behaviour('deferredfeedback');

        $slots = [];
        foreach ($questions as $questiondata) {
            $loadedquestion = \question_bank::load_question((int)$questiondata->question->id);
            $slots[] = $quba->add_question($loadedquestion, (float)$questiondata->maxmark);
        }
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
        $answer->phase = ANSWER_PHASE_UNGRADED; // Phase 0 means ungraded.
        $answer->state = 0;
        $answer->permitsubmit = 0;
        $answer->perceiveddifficulty = -1;
        $answer->questionusageid = $quba->get_id();

        $answer->id = $DB->insert_record('quest_answers', $answer);

        return [$quba, $slots];
    }

    /**
     * Get or create a persistent question usage for previewing a challenge.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param stdClass|array $question Question record or ordered slot collection.
     * @param \context $context
     * @return array [\question_usage_by_activity $quba, int[] $slots]
     */
    public static function get_or_create_challenge_preview_usage(
        stdClass $quest,
        stdClass $submission,
        stdClass|array $question,
        \context $context
    ): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');

        if (!empty($submission->questionusageid)) {
            try {
                $quba = \question_engine::load_questions_usage_by_activity((int)$submission->questionusageid);
                $questionlist = is_array($question) ? $question :
                    [(object)['question' => $question, 'maxmark' => $submission->pointsmax]];
                $usedslots = $quba->get_slots();
                $matches = count($usedslots) === count($questionlist);
                foreach (array_values($questionlist) as $index => $questiondata) {
                    $expected = $questiondata->question ?? $questiondata;
                    $used = $matches ? $quba->get_question($usedslots[$index], false) : false;
                    if (!$used || (int)$used->id !== (int)$expected->id) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return [$quba, $usedslots];
                }
            } catch (\Exception $e) {
                // Usage missing or question changed; recreate below.
                unset($e);
            }
        }

        $quba = \question_engine::make_questions_usage_by_activity('mod_quest', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $questionlist = is_array($question) ? $question :
            [(object)['question' => $question, 'maxmark' => $submission->pointsmax]];
        $slots = [];
        foreach ($questionlist as $questiondata) {
            $questionrecord = $questiondata->question ?? $questiondata;
            $mark = isset($questiondata->maxmark) ? (float)$questiondata->maxmark : (float)$submission->pointsmax;
            $loadedquestion = \question_bank::load_question((int)$questionrecord->id);
            $slots[] = $quba->add_question($loadedquestion, $mark);
        }
        $quba->start_all_questions();

        \question_engine::save_questions_usage_by_activity($quba);

        $submission->questionusageid = (int)$quba->get_id();
        try {
            $DB->set_field('quest_submissions', 'questionusageid', $submission->questionusageid, ['id' => $submission->id]);
        } catch (\Exception $e) {
            // In case DB upgrade hasn't run yet.
            unset($e);
        }

        return [$quba, $slots];
    }

    /**
     * Render the question engine question to HTML for display in answer form.
     *
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param bool $readonly Whether question inputs should be disabled (e.g. after finish).
     * @param bool $showcorrectanswer Whether the challenge has ended and review feedback may be shown.
     * @param bool $showresults Whether marks and response feedback should be shown before the challenge ends.
     * @return string HTML output
     */
    public static function render_question(
        \question_usage_by_activity $quba,
        int $slot = 1,
        bool $readonly = false,
        bool $showcorrectanswer = false,
        bool $showresults = false
    ): string {
        // Guarantee that the usage is saved so question text URLs have valid numeric usage IDs.
        if (!is_numeric($quba->get_id())) {
            \question_engine::save_questions_usage_by_activity($quba);
        }

        $options = new question_display_options();
        $options->readonly = $readonly;
        $options->flags = question_display_options::HIDDEN;
        $options->marks = $readonly ? question_display_options::HIDDEN : question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::HIDDEN;
        $options->numpartscorrect = question_display_options::HIDDEN;
        $options->generalfeedback = question_display_options::HIDDEN;
        $options->rightanswer = question_display_options::HIDDEN;
        $options->manualcomment = question_display_options::HIDDEN;
        $options->correctness = question_display_options::HIDDEN;
        if ($readonly && ($showcorrectanswer || $showresults)) {
            $options->marks = question_display_options::MARK_AND_MAX;
            $options->feedback = question_display_options::VISIBLE;
            $options->numpartscorrect = question_display_options::VISIBLE;
            $options->correctness = question_display_options::VISIBLE;
        }
        if ($readonly && $showcorrectanswer) {
            $options->generalfeedback = question_display_options::VISIBLE;
            $options->rightanswer = question_display_options::VISIBLE;
        }

        return $quba->render_question($slot, $options, (string) $slot);
    }

    /**
     * Render every question in a usage, preserving the question-bank slot order.
     *
     * @param \question_usage_by_activity $quba
     * @param bool $readonly
     * @param bool $showcorrectanswer
     * @param bool $showresults
     * @return string
     */
    public static function render_questions(
        \question_usage_by_activity $quba,
        bool $readonly = false,
        bool $showcorrectanswer = false,
        bool $showresults = false
    ): string {
        $html = '';
        foreach ($quba->get_slots() as $index => $slot) {
            $html .= \html_writer::div(
                self::render_question($quba, $slot, $readonly, $showcorrectanswer, $showresults),
                'quest-question-slot mb-4',
                ['data-slot' => $index + 1]
            );
        }
        return $html;
    }

    /**
     * Render a question preview with its configured response controls disabled.
     *
     * The core question renderer remains the source of truth for the question
     * definition. This mode is intended for challenge previews, where the
     * controls must be visible but must not create a response attempt.
     *
     * @param \question_usage_by_activity $quba Question usage.
     * @param int $slot Question slot.
     * @return string HTML output.
     */
    public static function render_question_preview(
        \question_usage_by_activity $quba,
        int $slot = 1
    ): string {
        $questionhtml = self::render_question($quba, $slot, false);
        $question = $quba->get_question($slot);
        $notice = '';

        if (property_exists($question, 'attachments') && (int)$question->attachments !== 0) {
            $allowed = (int)$question->attachments < 0
                ? get_string('unlimited', 'moodle')
                : (string)(int)$question->attachments;
            if (!empty($question->attachmentsrequired)) {
                $a = (object)[
                    'required' => (string)(int)$question->attachmentsrequired,
                    'allowed' => $allowed,
                ];
                $stringid = 'questionpreviewattachmentsrequired';
            } else {
                $a = (object)['count' => $allowed];
                $stringid = 'questionpreviewattachments';
            }
            $notice = \html_writer::div(
                get_string($stringid, 'quest', $a),
                'alert alert-info quest-question-preview-attachments',
                ['role' => 'note']
            );
        }

        return \html_writer::div(
            $notice . self::disable_preview_controls($questionhtml),
            'quest-question-preview',
            ['role' => 'group']
        );
    }

    /**
     * Render all questions in a challenge preview with disabled controls.
     *
     * @param \question_usage_by_activity $quba
     * @param \context|null $context
     * @param int $cmid
     * @param bool $showactions Whether question preview actions should be rendered.
     * @param bool $showeditquestion Whether to include the question-bank edit action.
     * @return string
     */
    public static function render_questions_preview(
        \question_usage_by_activity $quba,
        ?\context $context = null,
        int $cmid = 0,
        bool $showactions = false,
        bool $showeditquestion = true
    ): string {
        global $OUTPUT, $USER;

        $html = '';
        foreach ($quba->get_slots() as $index => $slot) {
            $question = $quba->get_question($slot);
            $actions = '';
            if ($showactions && $context && $cmid) {
                $previewurl = \qbank_previewquestion\helper::question_preview_url(
                    (int)$question->id, null, null, null, null, $context, $cmid
                );
                $actions .= \html_writer::link(
                    $previewurl,
                    $OUTPUT->pix_icon('i/preview', get_string('preview')) . ' ' . get_string('preview'),
                    ['class' => 'btn btn-sm btn-outline-primary', 'target' => '_blank', 'title' => get_string('preview')]
                );
                $metadata = \mod_quest\question\bank_provider::get_question((int)$question->id);
                $questioncontext = \context::instance_by_id($metadata->contextid);
                $canedit = has_capability('moodle/question:editall', $questioncontext) ||
                    ((int)$metadata->createdby === (int)$USER->id &&
                        has_capability('moodle/question:editmine', $questioncontext));
                if ($showeditquestion && $canedit) {
                    $editurl = new \moodle_url('/question/bank/editquestion/question.php', [
                        'id' => $question->id,
                        'cmid' => $cmid,
                    ]);
                    $actions .= \html_writer::link(
                        $editurl,
                        $OUTPUT->pix_icon('t/edit', get_string('edit')) . ' ' . get_string('editquestion', 'quest'),
                        ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank', 'title' => get_string('editquestion', 'quest')]
                    );
                }
            }
            $qtypename = $question->qtype->local_name();
            $title = \html_writer::div(
                \html_writer::tag('strong', format_string($question->name)) .
                \html_writer::tag('span', $qtypename, ['class' => 'badge bg-light text-dark ms-2']),
                'quest-question-preview-title d-flex align-items-center flex-wrap gap-2'
            );
            $header = \html_writer::div(
                $title . \html_writer::div($actions, 'quest-question-preview-actions d-flex gap-2'),
                'd-flex align-items-center gap-3 mb-2'
            );
            $html .= \html_writer::div(
                $header . self::render_question_preview($quba, $slot),
                'quest-question-preview-item mb-4',
                ['data-slot' => $index + 1]
            );
        }
        return $html;
    }

    /**
     * Render the same question panel wherever a challenge is displayed.
     *
     * @param stdClass $quest Quest activity.
     * @param stdClass $submission Challenge record.
     * @param \context $context Quest module context.
     * @param int $cmid Course module ID.
     * @param bool $showactions Whether question preview links are visible.
     * @return string Question panel, or an empty string for a plain challenge.
     */
    public static function render_challenge_questions_preview(
        stdClass $quest,
        stdClass $submission,
        \context $context,
        int $cmid,
        bool $showactions = false
    ): string {
        $questions = question_reference_service::get_challenge_questions((int)$submission->id);
        if (!$questions) {
            return '';
        }

        [$quba] = self::get_or_create_challenge_preview_usage($quest, $submission, $questions, $context);
        $preview = self::render_questions_preview($quba, $context, $cmid, $showactions, false);
        return \html_writer::div(
            \html_writer::div($preview, 'card-body p-4 bg-white rounded shadow-sm'),
            'card border-0 mb-4',
            ['id' => 'quest-qpreview-panel']
        );
    }

    /**
     * Disable interactive controls in a question preview without changing its HTML.
     *
     * @param string $html Rendered question HTML.
     * @return string HTML with controls disabled.
     */
    private static function disable_preview_controls(string $html): string {
        $html = preg_replace_callback(
            '/<(input|textarea|select|button)\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[2];
                if (preg_match('/\btype\s*=\s*["\']hidden["\']/i', $attributes)) {
                    return $matches[0];
                }
                if (!preg_match('/\bdisabled\s*=/i', $attributes)) {
                    $attributes .= ' disabled="disabled"';
                }
                if (!preg_match('/\baria-disabled\s*=/i', $attributes)) {
                    $attributes .= ' aria-disabled="true"';
                }
                return '<' . $matches[1] . $attributes . '>';
            },
            $html
        );

        $html = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[1];
                if (!preg_match('/\brole\s*=\s*["\']button["\']/i', $attributes)) {
                    return $matches[0];
                }
                if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attributes)) {
                    $attributes = preg_replace_callback(
                        '/\bclass\s*=\s*(["\'])(.*?)\1/i',
                        static function (array $classmatches): string {
                            return 'class=' . $classmatches[1] . trim($classmatches[2] . ' disabled') .
                                $classmatches[1];
                        },
                        $attributes,
                        1
                    );
                } else {
                    $attributes .= ' class="disabled"';
                }
                if (!preg_match('/\baria-disabled\s*=/i', $attributes)) {
                    $attributes .= ' aria-disabled="true"';
                }
                if (!preg_match('/\btabindex\s*=/i', $attributes)) {
                    $attributes .= ' tabindex="-1"';
                }
                return '<a' . $attributes . '>';
            },
            $html
        );

        return preg_replace_callback(
            '/\scontenteditable\s*=\s*(["\'])true\1/i',
            static function (array $matches): string {
                return ' contenteditable="false" aria-readonly="true"';
            },
            $html
        );
    }

    /**
     * Process student answer submission, grade automatically, and award points.
     *
     * @param stdClass $quest
     * @param stdClass $submission
     * @param int $userid
     * @param \question_usage_by_activity $quba
     * @param int|array|null $slot Legacy slot argument; all slots are evaluated.
     * @return array [bool $passed, float $points, float $fraction, string $message]
     */
    public static function process_submission(
        stdClass $quest,
        stdClass $submission,
        int $userid,
        \question_usage_by_activity $quba,
        $slot = null
    ): array {
        global $DB;

        $timenow = time();
        $quba->process_all_actions($timenow);
        $quba->finish_all_questions($timenow);
        question_engine::save_questions_usage_by_activity($quba);

        $weightedfraction = 0.0;
        $totalmark = 0.0;
        $ismanual = false;
        foreach ($quba->get_slots() as $questionslot) {
            $question = $quba->get_question($questionslot);
            $state = $quba->get_question_state($questionslot);
            if ($question->qtype->is_manual_graded() || ($state == \question_state::$needsgrading)) {
                $ismanual = true;
                continue;
            }
            $fraction = $quba->get_question_fraction($questionslot);
            $maxmark = (float)$quba->get_question_max_mark($questionslot);
            $weightedfraction += (float)($fraction ?? 0) * $maxmark;
            $totalmark += $maxmark;
        }

        // Update the existing answer record.
        $answer = $DB->get_record('quest_answers', [
            'questionusageid' => $quba->get_id(),
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
            $answer->phase = ANSWER_PHASE_UNGRADED;
            $answer->grade = 0.0;
            $answer->state = ANSWER_STATE_EDITTED;
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

        $fraction = $totalmark > 0 ? $weightedfraction / $totalmark : 0.0;

        $grade = round($fraction * 100, 2);
        $passed = ($grade >= 50.0);

        $answer->grade = $grade;
        $answer->phase = $passed ? ANSWER_PHASE_PASSED : ANSWER_PHASE_GRADED;
        $answer->state = ANSWER_STATE_EDITTED;
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
