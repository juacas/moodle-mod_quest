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

use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_version_status;
use stdClass;
use cm_info;
use moodle_exception;

/**
 * Question bank discovery and permission validator for Quest.
 *
 * Compatible with Moodle 5.0+ shared question banks (mod_qbank).
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bank_provider {

    public const CAPS = ['moodle/question:useall', 'moodle/question:usemine'];

    /**
     * Return authorized question banks available to the user in a course.
     *
     * @param int $courseid Current course ID.
     * @return array List of available question bank instances.
     */
    public static function get_available_banks(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        if (!class_exists('core_question\local\bank\question_bank_helper')) {
            return [];
        }

        $localbanks = question_bank_helper::get_activity_instances_with_shareable_questions(
            incourseids: [$courseid],
            havingcap: self::CAPS
        );

        $otherbanks = question_bank_helper::get_activity_instances_with_shareable_questions(
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
        if (!in_array($cm->modname, question_bank_helper::get_activity_types_with_shareable_questions(), true)) {
            throw new moodle_exception('banknotavailable', 'quest');
        }
        if ($cm->modname === 'qbank' && $DB->get_field('qbank', 'type', ['id' => $cm->instance]) === question_bank_helper::TYPE_PREVIEW) {
            throw new moodle_exception('banknotavailable', 'quest');
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
            self::require_bank($context->instanceid);
        }

        question_require_capability_on($question, 'use');

        if ($question->parent || $question->status !== question_version_status::QUESTION_STATUS_READY) {
            throw new moodle_exception('questionnotusable', 'quest');
        }

        return $question;
    }
}
