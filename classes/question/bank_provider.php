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

// NOTE: core_question\local\bank\question_bank_helper was introduced in Moodle 5.0.
// On Moodle 4.x the equivalent class is core_question\local\bank\helper (different API).
// All references to the 5.0+ class are guarded by self::has_bank_helper() at runtime.
// question_version_status exists in both 4.x and 5.x, so it is safe to use directly.
use core_question\local\bank\question_version_status;
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
     * Return authorized question banks available to the user in a course.
     *
     * On Moodle 4.x this always returns an empty array since the shared-bank
     * discovery API (question_bank_helper) does not exist yet.
     * Use {@see self::get_course_questions()} for the Moodle 4.x equivalent.
     *
     * @param int $courseid Current course ID.
     * @return array List of available question bank instances (Moodle 5.0+ only).
     */
    public static function get_available_banks(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        if (!self::has_bank_helper()) {
            // Moodle 4.x: no shared question bank discovery API.
            return [];
        }

        // Moodle 5.0+: use question_bank_helper via dynamic class reference
        // to avoid a fatal error on class-load on 4.x.
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
     * Return ready questions from the course question bank categories.
     *
     * This is the Moodle 4.x equivalent of the shared-bank picker. It queries
     * all question_categories belonging to the course context (and optionally
     * the module context) and returns latest-version 'ready' questions that
     * the current user has permission to use.
     *
     * @param int $courseid  Course ID.
     * @param int $excludecategoryid  Optional activity-local category to exclude
     *                                (questions already scoped to this quest instance).
     * @return stdClass[]  Rows with id, name, qtype, createdby, version, status,
     *                     questionbankentryid, categoryid, categoryname.
     */
    public static function get_course_questions(int $courseid, int $excludecategoryid = 0): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        // Collect all relevant context IDs for this course (course + any module contexts).
        $coursecontext = \context_course::instance($courseid);
        $contextids = [$coursecontext->id];

        // Also include all module contexts in the course so questions stored per-activity
        // (e.g. from a quiz) are reachable, subject to capability checks below.
        $modcontexts = $DB->get_fieldset_sql(
            "SELECT c.id FROM {context} c
              JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :ctxlevel
             WHERE cm.course = :course AND cm.deletioninprogress = 0",
            ['ctxlevel' => CONTEXT_MODULE, 'course' => $courseid]
        );
        $contextids = array_merge($contextids, $modcontexts);

        if (empty($contextids)) {
            return [];
        }

        // Use two separate get_in_or_equal() calls with distinct prefixes so that
        // each occurrence of the context list in the SQL gets its own unique named
        // parameters. Reusing the same named params in two places of one query
        // causes a Moodle "invalidqueryparam" error.
        [$ctxsql1, $ctxparams1] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx1');
        [$ctxsql2, $ctxparams2] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx2');

        // Optionally exclude the activity-local category (questions already in this Quest).
        $excludesql = '';
        $excludeparams = [];
        if ($excludecategoryid > 0) {
            $excludesql = ' AND qbe.questioncategoryid <> :excludecat';
            $excludeparams['excludecat'] = $excludecategoryid;
        }

        // Fetch latest-version questions with status 'ready'.
        $sql = "SELECT q.id, q.name, q.qtype, q.createdby,
                       qv.version, qv.status, qv.questionbankentryid,
                       qc.id AS categoryid, qc.name AS categoryname, qc.contextid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN (
                      SELECT qv2.questionbankentryid, MAX(qv2.version) AS maxver
                        FROM {question_versions} qv2
                        JOIN {question_bank_entries} qbe2 ON qbe2.id = qv2.questionbankentryid
                        JOIN {question_categories} qc2 ON qc2.id = qbe2.questioncategoryid
                       WHERE qc2.contextid $ctxsql1
                         AND qv2.status = 'ready'
                       GROUP BY qv2.questionbankentryid
                  ) latest ON latest.questionbankentryid = qv.questionbankentryid
                           AND qv.version = latest.maxver
                 WHERE qc.contextid $ctxsql2
                   AND qv.status = 'ready'
                   AND q.parent = 0
                   $excludesql
                 ORDER BY qc.name ASC, q.name ASC";

        $params = array_merge($ctxparams1, $ctxparams2, $excludeparams);
        $rows = $DB->get_records_sql($sql, $params);

        // Filter by capability: keep only questions the current user can 'use'.
        $filtered = [];
        foreach ($rows as $row) {
            $ctx = \context::instance_by_id($row->contextid, IGNORE_MISSING);
            if (!$ctx) {
                continue;
            }
            if (!has_any_capability(self::CAPS, $ctx)) {
                continue;
            }
            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * Validate an explicitly chosen bank course module.
     *
     * On Moodle 4.x, only 'qbank' modules are accepted (the only module that
     * acted as a standalone question bank in that version). On Moodle 5.0+,
     * any module declaring FEATURE_PUBLISHES_QUESTIONS is accepted.
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
            $helper = 'core_question\\local\\bank\\question_bank_helper';
            if (!in_array($cm->modname, $helper::get_activity_types_with_shareable_questions(), true)) {
                throw new moodle_exception('banknotavailable', 'quest');
            }
            if ($cm->modname === 'qbank' &&
                    $DB->get_field('qbank', 'type', ['id' => $cm->instance]) === $helper::TYPE_PREVIEW) {
                throw new moodle_exception('banknotavailable', 'quest');
            }
        } else {
            // Moodle 4.x: no standalone bank module exists. The module context is the activity itself (quest) or qbank if present.
            if ($cm->modname !== 'quest' && $cm->modname !== 'qbank') {
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

        // question_version_status exists in both Moodle 4.x and 5.x.
        if ($question->parent || $question->status !== question_version_status::QUESTION_STATUS_READY) {
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
