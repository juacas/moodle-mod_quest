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
use context_module;
use question_bank;

/**
 * Service to export open Quest challenges into standard question bank questions.
 *
 * Saves challenges into the activity's question bank category so they can be
 * shared, reused in quizzes, and preserved across course iterations.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class open_question_exporter {

    /**
     * Get or create the default question category for the quest activity instance.
     *
     * @param context_module $context
     * @return stdClass
     */
    public static function get_or_create_activity_category(context_module $context): stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $category = question_get_default_category($context->id);
        if (!$category) {
            $categories = question_make_default_categories([$context]);
            $category = reset($categories);
        }
        return $category;
    }

    /**
     * Export an open Quest challenge into the activity's question bank as an essay question.
     *
     * @param stdClass $quest
     * @param stdClass $submission Challenge object
     * @param context_module $context
     * @return stdClass The created question record
     */
    public static function export_challenge(
        stdClass $quest,
        stdClass $submission,
        context_module $context
    ): stdClass {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/questionlib.php');

        // Check if already linked to a question bank question.
        $existing = question_reference_service::get_question_for_challenge((int)$submission->id);
        if ($existing) {
            return $existing;
        }

        $category = self::get_or_create_activity_category($context);

        $qtype = question_bank::get_qtype('essay');

        $formdata = new stdClass();
        $formdata->category = "{$category->id},{$context->id}";
        $formdata->contextid = $context->id;
        $formdata->qtype = 'essay';
        $formdata->name = clean_param($submission->title, PARAM_TEXT);
        $formdata->questiontext = [
            'text' => $submission->description,
            'format' => $submission->descriptionformat ?: FORMAT_HTML,
        ];
        $formdata->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $formdata->defaultmark = (float)$submission->pointsmax;
        $formdata->penalty = 0.0;
        $formdata->idnumber = 'quest_' . $submission->id;

        // Essay specific options.
        $formdata->responseformat = 'editor';
        $formdata->responserequired = 1;
        $formdata->responsefieldlines = 15;
        $formdata->minwordlimit = '';
        $formdata->maxwordlimit = '';
        $formdata->attachments = !empty($quest->nattachments) ? (int)$quest->nattachments : 0;
        $formdata->attachmentsrequired = 0;
        $formdata->maxbytes = 0;
        $formdata->filetypeslist = '';
        $formdata->graderinfo = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $formdata->responsetemplate = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];

        $question = $qtype->save_question(new stdClass(), $formdata);

        // Fetch question_bank_entry ID for question reference.
        $entryid = $DB->get_field_sql(
            "SELECT qv.questionbankentryid
               FROM {question_versions} qv
              WHERE qv.questionid = :qid",
            ['qid' => $question->id]
        );

        if ($entryid) {
            question_reference_service::set_challenge_question(
                $context->id,
                (int)$submission->id,
                (int)$entryid,
                1 // Fixed version 1 initially.
            );
        }

        return $question;
    }
}
