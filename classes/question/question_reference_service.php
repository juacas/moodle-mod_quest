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

    public const COMPONENT = 'mod_quest';
    public const QUESTIONAREA = 'challenge_question';

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
     * Get the linked question reference for a challenge, if any.
     *
     * @param int $challengeid
     * @return stdClass|null
     */
    public static function get_challenge_question_reference(int $challengeid): ?stdClass {
        global $DB;
        $ref = $DB->get_record('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $challengeid,
        ]);
        return $ref ?: null;
    }

    /**
     * Resolve the concrete question ID for a challenge reference.
     *
     * @param int $challengeid
     * @return stdClass|null Question record or null if not linked.
     */
    public static function get_question_for_challenge(int $challengeid): ?stdClass {
        global $DB;

        $ref = self::get_challenge_question_reference($challengeid);
        if (!$ref) {
            return null;
        }

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
        $DB->delete_records('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $challengeid,
        ]);
    }

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
