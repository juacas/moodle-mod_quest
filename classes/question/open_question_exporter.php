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
use context_user;
use question_bank;
use core_question\local\bank\question_version_status;

/**
 * Service to export open Quest challenges into standard question bank questions.
 *
 * Saves challenges into the activity's question bank category so they can be
 * shared, reused in quizzes, and preserved across course iterations.
 * Compatible with Moodle 4.5 and 5.x Question Bank architectures.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class open_question_exporter {

    /**
     * Get or create the default question category for the quest activity instance.
     *
     * Fully compatible with Moodle 4.5 and 5.x without invoking deprecated functions
     * or calling reset() on objects.
     *
     * @param context_module $context
     * @return stdClass
     */
    public static function get_or_create_activity_category(context_module $context): stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        // Moodle 4.0+ supports $createifnotexists = true in question_get_default_category.
        $category = question_get_default_category($context->id, true);
        if ($category instanceof stdClass) {
            return $category;
        }

        // Direct fallback: ensure top category exists and get or create the default category.
        $topcategory = question_get_top_category($context->id, true);
        $existing = $DB->get_record_select(
            'question_categories',
            'contextid = ? AND parent <> 0',
            [$context->id],
            '*',
            IGNORE_MULTIPLE
        );
        if ($existing) {
            return $existing;
        }

        $defaultcat = new stdClass();
        $contextname = $context->get_context_name(false, true);
        $defaultcat->name = shorten_text(get_string('defaultfor', 'question', $contextname), 255);
        $defaultcat->info = get_string('defaultinfofor', 'question', $contextname);
        $defaultcat->contextid = $context->id;
        $defaultcat->parent = $topcategory ? (int)$topcategory->id : 0;
        $defaultcat->sortorder = 999;
        $defaultcat->stamp = make_unique_id_code();
        $defaultcat->id = $DB->insert_record('question_categories', $defaultcat);

        return $defaultcat;
    }

    /**
     * Export an open Quest challenge into the activity's question bank as an essay question.
     *
     * Preserves embedded images, copies attachments, and formats options according
     * to Moodle 4.5 / 5.x Question Bank requirements.
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
        require_once($CFG->libdir . '/filelib.php');

        // Check if already linked to a question bank question.
        $existing = question_reference_service::get_question_for_challenge((int)$submission->id);
        if ($existing) {
            return $existing;
        }

        // When executed from cron, CLI, or task, ensure a valid user context exists for draft areas.
        $resetuser = false;
        if (empty($USER->id)) {
            $admin = get_admin();
            if ($admin) {
                \core\cron::setup_user($admin);
                $resetuser = true;
            }
        }

        try {
            $category = self::get_or_create_activity_category($context);

            $qtype = question_bank::get_qtype('essay');

        // Prepare draft area so embedded files and images in challenge description are preserved.
        $draftid = 0;
        $descriptiontext = file_prepare_draft_area(
            $draftid,
            $context->id,
            'mod_quest',
            'submission',
            (int)$submission->id,
            ['subdirs' => true],
            $submission->description
        );

        // Also copy any challenge attachment files into draft area and link them if present.
        $fs = get_file_storage();
        $attachments = $fs->get_area_files(
            $context->id,
            'mod_quest',
            'attachment',
            (int)$submission->id,
            'filename',
            false
        );
        if (!empty($attachments)) {
            $usercontext = context_user::instance($USER->id);
            $attachmenthtml = '<div class="quest_challenge_attachments mt-2"><p><strong>' .
                get_string('attachment', 'quest') . ':</strong></p><ul>';
            foreach ($attachments as $attfile) {
                $filerecord = [
                    'contextid' => $usercontext->id,
                    'component' => 'user',
                    'filearea' => 'draft',
                    'itemid' => $draftid,
                    'filepath' => '/',
                    'filename' => $attfile->get_filename(),
                ];
                if (!$fs->file_exists(
                    $filerecord['contextid'],
                    $filerecord['component'],
                    $filerecord['filearea'],
                    $filerecord['itemid'],
                    $filerecord['filepath'],
                    $filerecord['filename']
                )) {
                    $fs->create_file_from_storedfile($filerecord, $attfile);
                }
                $attachmenthtml .= '<li><a href="@@PLUGINFILE@@/' . rawurlencode($attfile->get_filename()) . '">' .
                    s($attfile->get_filename()) . '</a></li>';
            }
            $attachmenthtml .= '</ul></div>';
            $descriptiontext .= $attachmenthtml;
        }

        $name = clean_param($submission->title, PARAM_TEXT);
        if (trim($name) === '') {
            $name = shorten_text(strip_tags($submission->description), 50);
            if (trim($name) === '') {
                $name = get_string('submission', 'quest') . ' ' . $submission->id;
            }
        }

        $formdata = new stdClass();
        $formdata->category = "{$category->id},{$context->id}";
        $formdata->contextid = $context->id;
        $formdata->qtype = 'essay';
        $formdata->name = $name;
        $formdata->questiontext = [
            'text' => $descriptiontext,
            'format' => $submission->descriptionformat ?: FORMAT_HTML,
            'itemid' => $draftid,
        ];
        $formdata->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $formdata->defaultmark = (float)$submission->pointsmax;
        $formdata->penalty = 0.0;
        $formdata->idnumber = 'quest_' . $submission->id;
        $formdata->status = question_version_status::QUESTION_STATUS_READY;

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

        $graderinfo = '';
        if (!empty($submission->commentteacherpupil)) {
            $graderinfo = $submission->commentteacherpupil;
        } else if (!empty($submission->commentteacherauthor)) {
            $graderinfo = $submission->commentteacherauthor;
        }

        $formdata->graderinfo = [
            'text' => $graderinfo,
            'format' => FORMAT_HTML,
        ];
        $formdata->responsetemplate = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];

        // Explicitly set qtype on the question base object to avoid PHP 8.2+ dynamic property notices in save_question.
        $question = new stdClass();
        $question->qtype = 'essay';

        $savedquestion = $qtype->save_question($question, $formdata);

        // Fetch question_bank_entry ID for question reference.
        $entryid = $DB->get_field_sql(
            "SELECT qv.questionbankentryid
               FROM {question_versions} qv
              WHERE qv.questionid = :qid",
            ['qid' => $savedquestion->id]
        );

        if ($entryid) {
            question_reference_service::set_challenge_question(
                $context->id,
                (int)$submission->id,
                (int)$entryid,
                null // Follow the latest version after future edits.
            );
        }

        if (isset($submission->state) && (int)$submission->state === SUBMISSION_STATE_APPROVAL_PENDING) {
            question_reference_service::tag_approval_pending((int)$savedquestion->id, $context);
        }

        return $savedquestion;
        } finally {
            if ($resetuser) {
                \core\cron::setup_user();
            }
        }
    }
}
