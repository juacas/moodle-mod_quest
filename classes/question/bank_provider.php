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

// NOTE: core_question\local\bank\question_bank_helper and question_version_status
// were introduced in Moodle 5.0. Conditional use aliases are not possible in PHP,
// so all references to these classes are guarded by self::has_bank_helper() at runtime.
use stdClass;
use cm_info;
use context_module;
use moodle_exception;

/**
 * Question bank discovery and permission validator for Quest.
 *
 * Compatible with Moodle 4.5 (legacy question categories) and
 * Moodle 5.0+ shared question banks (mod_qbank).
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bank_provider {

    public const CAPS = ['moodle/question:useall', 'moodle/question:usemine'];

    /**
     * Check if the Moodle 5.0+ question_bank_helper class is available.
     *
     * @return bool True on Moodle 5.0+, false on 4.x.
     */
    public static function has_bank_helper(): bool {
        return class_exists('core_question\\local\\bank\\question_bank_helper');
    }

    /**
     * Check if a question version status constant is available (Moodle 5.0+).
     *
     * @param string $status
     * @return string The status string value.
     */
    private static function get_ready_status(): string {
        if (class_exists('core_question\\local\\bank\\question_version_status')) {
            return \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        }
        // Moodle 4.x uses the plain string value.
        return 'ready';
    }

    /**
     * Return authorized question banks available to the user in a course.
     *
     * @param int $courseid Current course ID.
     * @return array List of available question bank instances.
     */
    public static function get_available_banks(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        if (!self::has_bank_helper()) {
            // Moodle 4.x: no shared question bank support via question_bank_helper.
            return [];
        }

        /** @var \core_question\local\bank\question_bank_helper $helper */
        $helper = 'core_question\\local\\bank\\question_bank_helper';

        $localbanks = $helper::get_activity_instances_with_shareable_questions(
            incourseids: [$courseid],
            havingcap: self::CAPS
        );

        $otherbanks = $helper::get_activity_instances_with_shareable_questions(
            notincourseids: [$courseid],
            havingcap: self::CAPS
        );

        $allbanks = array_merge($localbanks, $otherbanks);
        return array_map(static function ($bank) {
            return method_exists($bank, 'get_formatted') ? $bank->get_formatted() : $bank;
        }, $allbanks);
    }

    /**
     * Validate an explicitly chosen bank course module.
     *
     * @param int $cmid
     * @return cm_info
     * @throws moodle_exception
     */
    public static function require_bank(int $cmid): cm_info {
        global $DB;
        $cm = cm_info::create(get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST));

        if (self::has_bank_helper()) {
            // Moodle 5.0+: validate via question_bank_helper.
            /** @var \core_question\local\bank\question_bank_helper $helper */
            $helper = 'core_question\\local\\bank\\question_bank_helper';
            if (!in_array($cm->modname, $helper::get_activity_types_with_shareable_questions(), true)) {
                throw new moodle_exception('banknotavailable', 'quest');
            }
            if ($cm->modname === 'qbank' &&
                    $DB->get_field('qbank', 'type', ['id' => $cm->instance]) === $helper::TYPE_PREVIEW) {
                throw new moodle_exception('banknotavailable', 'quest');
            }
        } else {
            // Moodle 4.x fallback: only allow qbank instances.
            if ($cm->modname !== 'qbank') {
                throw new moodle_exception('banknotavailable', 'quest');
            }
        }

        if ($cm->deletioninprogress || !has_any_capability(self::CAPS, $cm->context)) {
            throw new moodle_exception('nopermissions', 'error');
        }
        return $cm;
    }

    /**
     * Retrieve question metadata with version and category details.
     *
     * @param int $questionid
     * @return stdClass
     */
    public static function get_question(int $questionid): stdClass {
        global $DB;
        $sql = "SELECT q.*, qv.version, qv.status, qv.id AS versionid,
                       qv.questionbankentryid, qc.id AS categoryid, qc.contextid, qbe.idnumber
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.id = :id";
        return $DB->get_record_sql($sql, ['id' => $questionid], MUST_EXIST);
    }

    /**
     * Validate and ensure a question can be used in Quest.
     *
     * @param int $questionid
     * @return stdClass
     * @throws moodle_exception
     */
    public static function require_question(int $questionid): stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $question = self::get_question($questionid);
        $context = \context::instance_by_id($question->contextid);
        if ($context->contextlevel === CONTEXT_MODULE) {
            // On Moodle 4.x, require_bank() uses a safe fallback (qbank-only check).
            self::require_bank($context->instanceid);
        }

        question_require_capability_on($question, 'use');

        // Check that the question is in a usable state.
        // On Moodle 5.0+ compare against the enum; on 4.x use the plain 'ready' string.
        $readystatus = self::get_ready_status();
        if ($question->parent || $question->status !== $readystatus) {
            throw new moodle_exception('questionnotusable', 'quest');
        }

        return $question;
    }

    /**
     * Get or create the activity question category in context_module.
     *
     * @param context_module $context
     * @return stdClass
     */
    public static function get_or_create_activity_category(context_module $context): stdClass {
        return open_question_exporter::get_or_create_activity_category($context);
    }

    /**
     * Ensure student role has local capabilities to add/edit/view their own questions in this activity context.
     *
     * @param context_module $context
     * @return void
     */
    public static function ensure_student_question_capabilities(context_module $context): void {
        global $DB;
        $caps = [
            'mod/quest:addchallenge',
            'moodle/question:add',
            'moodle/question:editmine',
            'moodle/question:viewmine',
            'moodle/question:usemine',
        ];

        // Ensure student archetype roles have local permission in this context_module.
        $studentroles = get_archetype_roles('student');
        foreach ($studentroles as $role) {
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $role->id, $context->id, true);
            }
        }

        // Also ensure any roles held by the current user in course/module context have these capabilities.
        $userroles = get_user_roles($context, 0, false);
        if (empty($userroles)) {
            $coursecontext = $context->get_course_context();
            $userroles = get_user_roles($coursecontext, 0, false);
        }
        foreach ($userroles as $ra) {
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $ra->roleid, $context->id, true);
            }
        }

        $context->mark_dirty();
    }
}
