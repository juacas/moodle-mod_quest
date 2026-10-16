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

namespace mod_quest\question;

use stdClass;
use context;
use core_question\local\bank\question_version_status;

/**
 * Service managing question_references for Quest challenges.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_reference_service {

    /** Component used by Moodle's question reference API. */
    public const COMPONENT = 'mod_quest';
    /** Question reference area used for challenge links. */
    public const QUESTIONAREA = 'challenge_question';
    /** Question reference area used by ordered challenge question slots. */
    public const SLOTQUESTIONAREA = 'challenge_question_slot';

    /**
     * Link a question bank question to a quest challenge.
     *
     * @param int $contextid Context ID of the quest activity module.
     * @param int $challengeid Challenge (submission) ID.
     * @param int $questionbankentryid Question bank entry ID.
     * @param int|null $version Specific version number or null for latest ready version.
     * @return stdClass The question_reference record.
     */
    public static function set_challenge_question(
        int $contextid,
        int $challengeid,
        int $questionbankentryid,
        ?int $version = null
    ): stdClass {
        global $DB;

        $existing = $DB->get_record('question_references', [
            'usingcontextid' => $contextid,
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $challengeid,
        ]);

        if ($existing) {
            $existing->questionbankentryid = $questionbankentryid;
            $existing->version = $version;
            $DB->update_record('question_references', $existing);
            return $existing;
        }

        $ref = new stdClass();
        $ref->usingcontextid = $contextid;
        $ref->component = self::COMPONENT;
        $ref->questionarea = self::QUESTIONAREA;
        $ref->itemid = $challengeid;
        $ref->questionbankentryid = $questionbankentryid;
        $ref->version = $version;

        $ref->id = $DB->insert_record('question_references', $ref);
        return $ref;
    }

    /**
     * Make a challenge follow the latest ready version of its question bank entry.
     *
     * Older Quest references may have been created with a fixed version. Clearing
     * that value preserves the question bank entry while allowing Moodle to
     * resolve the newest version after a question is edited.
     *
     * @param int $challengeid
     */
    public static function use_latest_version_for_challenge(int $challengeid): void {
        global $DB;

        $ref = self::get_challenge_question_reference($challengeid);
        if (!$ref || $ref->version === null) {
            return;
        }

        $ref->version = null;
        $DB->update_record('question_references', $ref);
    }

    /**
     * Get the linked question reference for a challenge, if any.
     *
     * @param int $challengeid
     * @return stdClass|null
     */
    public static function get_challenge_question_reference(int $challengeid): ?stdClass {
        global $DB;
        $questions = self::get_challenge_questions($challengeid);
        if ($questions) {
            return $questions[0]->reference;
        }
        $ref = $DB->get_record('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $challengeid,
        ]);
        return $ref ?: null;
    }

    /**
     * Get the ordered question collection attached to a challenge.
     *
     * @param int $challengeid Challenge ID.
     * @return array Ordered slot records with question and reference properties.
     */
    public static function get_challenge_questions(int $challengeid): array {
        global $DB;

        $slots = $DB->get_records('quest_challenge_questions', ['submissionid' => $challengeid], 'slotnumber ASC, id ASC');
        $questions = [];
        foreach ($slots as $slot) {
            $slot->reference = $DB->get_record('question_references', [
                'component' => self::COMPONENT,
                'questionarea' => self::SLOTQUESTIONAREA,
                'itemid' => $slot->id,
            ]);
            if ($slot->reference) {
                $slot->question = self::resolve_reference($slot->reference);
                if ($slot->question) {
                    $questions[] = $slot;
                }
            }
        }

        // Read old data during upgrades or when restoring a legacy backup.
        if (!$questions) {
            $reference = $DB->get_record('question_references', [
                'component' => self::COMPONENT,
                'questionarea' => self::QUESTIONAREA,
                'itemid' => $challengeid,
            ]);
            if ($reference && ($question = self::resolve_reference($reference))) {
                $questions[] = (object)[
                    'id' => 0,
                    'submissionid' => $challengeid,
                    'slotnumber' => 1,
                    'maxmark' => 1.0,
                    'reference' => $reference,
                    'question' => $question,
                ];
            }
        }
        return $questions;
    }

    /**
     * Replace a challenge's ordered question collection.
     *
     * Each item needs questionbankentryid and maxmark; version defaults to the latest ready one.
     *
     * @param int $contextid Activity context ID.
     * @param int $questid Quest ID.
     * @param int $challengeid Challenge ID.
     * @param array $items Ordered slot data.
     * @return void
     */
    public static function replace_challenge_questions(int $contextid, int $questid, int $challengeid, array $items): void {
        global $DB;

        $slotids = $DB->get_fieldset_select(
            'quest_challenge_questions', 'id', 'submissionid = :sid', ['sid' => $challengeid]
        );
        if ($slotids) {
            [$insql, $params] = $DB->get_in_or_equal($slotids, SQL_PARAMS_NAMED);
            $params['component'] = self::COMPONENT;
            $params['questionarea'] = self::SLOTQUESTIONAREA;
            $DB->delete_records_select(
                'question_references',
                "component = :component AND questionarea = :questionarea AND itemid $insql",
                $params
            );
        }
        $DB->delete_records('quest_challenge_questions', ['submissionid' => $challengeid]);
        $DB->delete_records('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $challengeid,
        ]);

        foreach (array_values($items) as $index => $item) {
            $slot = (object)[
                'questid' => $questid,
                'submissionid' => $challengeid,
                'slotnumber' => $index + 1,
                'maxmark' => (float)$item['maxmark'],
            ];
            $slot->id = $DB->insert_record('quest_challenge_questions', $slot);
            self::set_slot_question($contextid, (int)$slot->id, (int)$item['questionbankentryid'], $item['version'] ?? null);
        }

        // The challenge preview is a cache. It will be rebuilt for the new collection.
        $DB->set_field('quest_submissions', 'questionusageid', 0, ['id' => $challengeid]);
    }

    /**
     * Set the question-bank reference owned by a slot.
     *
     * @param int $contextid Activity context ID.
     * @param int $slotid Slot row ID.
     * @param int $questionbankentryid Question bank entry ID.
     * @param int|null $version Specific version, or latest ready when null.
     * @return stdClass Reference record.
     */
    public static function set_slot_question(
        int $contextid,
        int $slotid,
        int $questionbankentryid,
        ?int $version = null
    ): stdClass {
        global $DB;

        $reference = (object)[
            'usingcontextid' => $contextid,
            'component' => self::COMPONENT,
            'questionarea' => self::SLOTQUESTIONAREA,
            'itemid' => $slotid,
            'questionbankentryid' => $questionbankentryid,
            'version' => $version,
        ];
        $existing = $DB->get_record('question_references', [
            'usingcontextid' => $contextid,
            'component' => self::COMPONENT,
            'questionarea' => self::SLOTQUESTIONAREA,
            'itemid' => $slotid,
        ]);
        if ($existing) {
            $reference->id = $existing->id;
            $DB->update_record('question_references', $reference);
        } else {
            $reference->id = $DB->insert_record('question_references', $reference);
        }
        return $reference;
    }

    /**
     * Resolve the concrete question ID for a challenge reference.
     *
     * @param int $challengeid
     * @return stdClass|null Question record or null if not linked.
     */
    public static function get_question_for_challenge(int $challengeid): ?stdClass {
        $questions = self::get_challenge_questions($challengeid);
        return $questions ? $questions[0]->question : null;
    }

    /** Resolve a Moodle question reference to its current question record. */
    private static function resolve_reference(stdClass $ref): ?stdClass {
        global $DB;

        if ($ref->version !== null) {
            $sql = "SELECT q.*
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                     WHERE qv.questionbankentryid = :entryid AND qv.version = :version";
            $question = $DB->get_record_sql($sql, [
                'entryid' => $ref->questionbankentryid,
                'version' => $ref->version,
            ]);
        } else {
            // Get highest ready version.
            $sql = "SELECT q.*
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                     WHERE qv.questionbankentryid = :entryid
                       AND qv.status = :status
                  ORDER BY qv.version DESC";
            $question = $DB->get_record_sql($sql, [
                'entryid' => $ref->questionbankentryid,
                'status' => question_version_status::QUESTION_STATUS_READY,
            ], IGNORE_MULTIPLE);

            if (!$question) {
                // Fallback to highest version regardless of status (e.g. pending approval).
                $sql = "SELECT q.*
                          FROM {question} q
                          JOIN {question_versions} qv ON qv.questionid = q.id
                         WHERE qv.questionbankentryid = :entryid
                      ORDER BY qv.version DESC";
                $question = $DB->get_record_sql($sql, ['entryid' => $ref->questionbankentryid], IGNORE_MULTIPLE);
            }
        }

        return $question ?: null;
    }

    /**
     * Delete question reference for a challenge.
     *
     * @param int $challengeid
     */
    public static function delete_challenge_reference(int $challengeid): void {
        global $DB;
        $slotids = $DB->get_fieldset_select(
            'quest_challenge_questions', 'id', 'submissionid = :sid', ['sid' => $challengeid]
        );
        if ($slotids) {
            [$insql, $params] = $DB->get_in_or_equal($slotids, SQL_PARAMS_NAMED, 'slot');
            $DB->delete_records_select('question_references',
                'component = :component AND questionarea = :area AND itemid ' . $insql,
                array_merge(['component' => self::COMPONENT, 'area' => self::SLOTQUESTIONAREA], $params));
            $DB->delete_records('quest_challenge_questions', ['submissionid' => $challengeid]);
        }
        $DB->delete_records('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $challengeid,
        ]);
    }

    /** Tag used to mark a question awaiting approval. */
    public const TAG_APPROVAL_PENDING = 'approval_pending';

    /**
     * Add 'approval_pending' tag to a question.
     *
     * @param int $questionid
     * @param context $context
     */
    public static function tag_approval_pending(int $questionid, context $context): void {
        global $CFG;
        require_once($CFG->dirroot . '/tag/classes/tag.php');

        $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $questionid);
        if (!in_array(self::TAG_APPROVAL_PENDING, $tags, true)) {
            $tags[] = self::TAG_APPROVAL_PENDING;
            \core_tag_tag::set_item_tags('core_question', 'question', $questionid, $context, $tags);
        }
    }

    /**
     * Mark a question as approved: remove 'approval_pending' tag and set status to READY.
     *
     * @param int $questionid
     * @param context $context
     */
    public static function mark_as_approved(int $questionid, context $context): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/tag/classes/tag.php');

        $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $questionid);
        if (in_array(self::TAG_APPROVAL_PENDING, $tags, true)) {
            $tags = array_values(array_filter($tags, static fn($t) => $t !== self::TAG_APPROVAL_PENDING));
            \core_tag_tag::set_item_tags('core_question', 'question', $questionid, $context, $tags);
        }

        // Ensure question version status is READY.
        $DB->execute(
            "UPDATE {question_versions}
                SET status = :status
              WHERE questionid = :qid",
            [
                'status' => question_version_status::QUESTION_STATUS_READY,
                'qid' => $questionid,
            ]
        );
    }

    /**
     * Check if a question has the 'approval_pending' tag.
     *
     * @param int $questionid
     * @return bool
     */
    public static function is_approval_pending(int $questionid): bool {
        global $CFG;
        require_once($CFG->dirroot . '/tag/classes/tag.php');

        $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $questionid);
        return in_array(self::TAG_APPROVAL_PENDING, $tags, true);
    }
}
