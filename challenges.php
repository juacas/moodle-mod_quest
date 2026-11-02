<?php
// This file is part of Questournament activity for Moodle http://moodle.org/
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

/**
 * Manage Quest challenges and their submissions.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("scores_lib.php");
// Normalize query parameters if & was HTML entity-escaped as &amp; in the URL.
foreach ($_GET as $key => $val) {
    if (str_starts_with($key, 'amp;')) {
        $cleankey = substr($key, 4);
        if (!isset($_GET[$cleankey])) {
            $_GET[$cleankey] = $val;
        }
    }
}
$id = required_param('id', PARAM_INT); // Quest coursemoduleID.
global $DB, $OUTPUT, $PAGE, $sort, $dir;
$timenow = time();
[$course, $cm] = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", ["id" => $cm->instance], '*', MUST_EXIST);
require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);
$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$canpreview = has_capability('mod/quest:preview', $context);
$caneditchallenges = has_capability('mod/quest:editchallengeall', $context);
$canapprove = has_capability('mod/quest:approvechallenge', $context);
$action = optional_param('action', '', PARAM_ALPHA);
if (empty($action) || $action === 'listallsubmissions' || $action === 'listallchallenges') {
    $ampaction = optional_param('amp;action', '', PARAM_ALPHA);
    if (!empty($ampaction)) {
        $action = $ampaction;
    } else {
        $action = 'listallchallenges';
    }
}
// Normalize action aliases.
if ($action === 'showchallenge') {
    $action = 'showsubmission';
} else if ($action === 'showchallengesuser') {
    $action = 'showsubmissionsuser';
} else if ($action === 'showchallengesteam') {
    $action = 'showsubmissionsteam';
} else if ($action === 'listallchallenges') {
    $action = 'listallsubmissions';
}
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
// Accept cid (challenge id) as well as sid (legacy challenge id).
$sid = optional_param('cid', null, PARAM_INT);
if ($sid === null) {
    $sid = optional_param('sid', null, PARAM_INT);
}
if ($sid === null) {
    $sid = optional_param('amp;cid', null, PARAM_INT);
}
if ($sid === null) {
    $sid = optional_param('amp;sid', null, PARAM_INT);
}
// Rendering a challenge must be read-only. Question-reference normalization is
// performed by the explicit editing/approval workflows, not by a GET request.
$sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$url = new moodle_url(
    '/mod/quest/challenges.php',
    ['id' => $id, 'sid' => $sid, 'action' => $action, 'sort' => $sort, 'dir' => $dir]
);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_activity_record($quest);
$PAGE->activityheader->set_attrs([
    'description' => quest_get_activity_header_description($quest, $cm, $context),
]);
if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, optional_param('userpassword', '', PARAM_RAW_TRIMMED));
}
if (empty($action) || $action === 'listallsubmissions') {
    redirect(new moodle_url('/mod/quest/view.php', ['id' => $cm->id]));
}
// Confirm delete.
if ($action == 'confirmdelete') {
    // The sid value was read at the top, accepting both sid and cid parameters.
    if ($sid === null) {
        throw new \moodle_exception('missingparam', '', '', 'sid');
    }
    $challenge = $DB->get_record("quest_submissions", ["id" => $sid, "questid" => $quest->id], '*', MUST_EXIST);
    if (!quest_user_can_delete_challenge($quest, $challenge, $context)) {
        throw new \moodle_exception('notauthorizedtodeletesubmission', 'quest');
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    quest_add_breadcrumbs($cm, $challenge, null, get_string('delete'));
    echo $OUTPUT->header();
    echo "<br>";
    echo $OUTPUT->confirm(
        get_string("confirmdeletionofthisitem", "quest", $challenge->title),
        "challenges.php?action=delete&amp;id=$cm->id&amp;sid=$sid",
        "view.php?id=$cm->id#sid=$sid"
    );
} else if ($action == 'delete') {
    // The sid value was read at the top, accepting both sid and cid parameters.
    if ($sid === null) {
        throw new \moodle_exception('missingparam', '', '', 'sid');
    }
    require_sesskey();
    $challenge = $DB->get_record("quest_submissions", ["id" => $sid, "questid" => $quest->id], '*', MUST_EXIST);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    quest_add_breadcrumbs($cm, $challenge, null, get_string('delete'));
    echo $OUTPUT->header();
    // ...check if the user has enough capability to delete the submission and only up to the
    // deadline.
    if (!quest_user_can_delete_challenge($quest, $challenge, $context)) {
        throw new \moodle_exception("notauthorizedtodeletesubmission", 'quest');
    }
    if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", [$quest->id, $challenge->id])) {
        foreach ($answers as $answer) {
            // ...first get any assessments...
            if ($assessments = quest_get_assessments($answer, 'ALL')) {
                foreach ($assessments as $assessment) {
                    // ...and all the associated records...
                    $DB->delete_records(
                        "quest_elements_assessments",
                        ["assessmentid" => $assessment->id, "questid" => $quest->id]
                    );
                    echo ".";
                }
                // ...now delete the assessments...
                $DB->delete_records("quest_assessments", ["answerid" => $answer->id, "questid" => $quest->id]);
            }
            if (!empty($answer->questionusageid)) {
                require_once($CFG->libdir . '/questionlib.php');
                question_engine::delete_questions_usage_by_activity((int)$answer->questionusageid);
            }
            $DB->delete_records("quest_answers", ["id" => $answer->id]);
            // ...now get rid of all answer files.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_quest', 'answer', $answer->id);
            $fs->delete_area_files($context->id, 'mod_quest', 'answer_attachment', $answer->id);
        }
    }
    if (
        $assessmentautor = $DB->get_record(
            "quest_assessments_autors",
            ["submissionid" => $challenge->id, "questid" => $quest->id]
        )
    ) {
        $DB->delete_records(
            "quest_items_assesments_autor",
            ["assessmentautorid" => $assessmentautor->id, "questid" => $quest->id]
        );
        $DB->delete_records("quest_assessments_autors", ["id" => $assessmentautor->id]);
    }
    // Recalculate points and report to gradebook...
    quest_grade_updated($quest, $challenge->userid);
    $DB->delete_records_select(
        'event',
        'modulename = ? AND instance = ? and ' . $DB->sql_compare_text('description') . ' = ?',
        ['modulename' => 'quest', 'instance' => $quest->id, 'description' => $challenge->description]
    );
    if (!empty($challenge->questionusageid)) {
        require_once($CFG->libdir . '/questionlib.php');
        question_engine::delete_questions_usage_by_activity((int)$challenge->questionusageid);
    }
    \mod_quest\question\question_reference_service::delete_challenge_reference((int)$challenge->id);
    // ...and the submission record...
    $DB->delete_records("quest_submissions", ["id" => $challenge->id]);
    // ...and finally the submitted files
    // now get rid of all files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_quest', 'submission', $challenge->id);
    $fs->delete_area_files($context->id, 'mod_quest', 'attachment', $challenge->id);
    // Log the action.
    \mod_quest\event\challenge_deleted::create_from_parts($USER, $challenge, $cm)->trigger();
    echo "<center>" . get_string("deletechallenge", "quest") . "</center>";
    echo $OUTPUT->continue_button("view.php?id=$cm->id");
} else if ($action === 'addqchallenge') {
    $canaddchallenge = has_capability('mod/quest:addchallenge', $context);
    if (!$canaddchallenge) {
        throw new \moodle_exception('nocapabilityaddchallenge', 'quest');
    }
    $canaddquestionbank = \mod_quest\challenge_type_policy::allows_question_bank($quest, $ismanager);
    if (!$canaddquestionbank) {
        throw new \moodle_exception('questionbankdisabled', 'quest');
    }
    \mod_quest\question\bank_provider::ensure_student_question_capabilities($context);
    $category = \mod_quest\question\bank_provider::get_or_create_activity_category($context);
    $questionids = optional_param_array('questionids', [], PARAM_INT);
    $lastchanged = optional_param('lastchanged', 0, PARAM_INT);
    if ($lastchanged > 0) {
        $questionids[] = $lastchanged;
    }
    $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
    $returnurl = quest_question_composer_url($cm->id, 'addqchallenge', $questionids, 0, true);
    // Prepare the question-bank and create-question actions for the composer toolbar.
    $questionbankbutton = '';
    if ($canaddquestionbank && \mod_quest\question\bank_provider::has_bank_helper()) {
        $availablebanks = \mod_quest\question\bank_provider::get_available_banks($course->id);
        if (!empty($availablebanks)) {
            $defaultbank = reset($availablebanks);
            $questionbankbutton = html_writer::tag("button", get_string('addfromquestionbank', 'quest'), [
                "type" => "button",
                "class" => "btn btn-outline-primary",
                "data-action" => "questionbank",
                "data-header" => get_string("addfromquestionbank", "quest"),
            ]);
            $PAGE->requires->js_call_amd(
                "mod_quest/modal_quest_question_bank",
                "init",
                [$context->id, (int)$defaultbank->modid, $cm->id, $course->id]
            );
        }
    } else if ($canaddquestionbank) {
        $questionbankbutton = html_writer::tag("button", get_string('addfromquestionbank', 'quest'), [
            "type" => "button",
            "class" => "btn btn-outline-primary",
            "data-action" => "questionbank",
            "data-header" => get_string("addfromquestionbank", "quest"),
        ]);
        $PAGE->requires->js_call_amd(
            "mod_quest/modal_quest_question_bank_45",
            "init",
            [$context->id, (int)$cm->id, (int)$course->id]
        );
    }
    require_once($CFG->dirroot . '/question/editlib.php');
    require_once($CFG->dirroot . '/question/bank/editquestion/classes/editquestion_helper.php');
    $canadd = has_capability('moodle/question:add', $context) || has_capability('mod/quest:addchallenge', $context);
    $qtypewidget = $OUTPUT->render(new \qbank_editquestion\output\add_new_question(
        (int)$category->id,
        ['cmid' => $cm->id, 'returnurl' => $returnurl->out_as_local_url(false)],
        $canadd
    ));

    $PAGE->set_title(get_string('composequestionchallenge', 'quest'));
    $PAGE->set_heading($course->fullname);
    quest_add_breadcrumbs($cm, null, null, get_string('composequestionchallenge', 'quest'));
    echo $OUTPUT->header();
    echo html_writer::div(
        $questionbankbutton . $qtypewidget,
        'quest-composer-toolbar d-flex flex-wrap align-items-center gap-2 mb-3'
    );
    if ($questionids) {
        require_once($CFG->libdir . '/questionlib.php');
        $selectedquestions = [];
        foreach ($questionids as $questionid) {
            // Editing a question returns its new ID alongside the old ID. Both
            // versions represent one bank entry and must occupy only one slot.
            $metadata = \mod_quest\question\bank_provider::get_question($questionid);
            $selectedquestions[(int)$metadata->questionbankentryid] = $questionid;
        }
        $selectedquestions = array_map(
            static fn($questionid) => \mod_quest\question\bank_provider::require_question($questionid, $context),
            array_values($selectedquestions)
        );
        $descriptionoptions = [
            'trusttext' => true,
            'subdirs' => false,
            'maxfiles' => -1,
            'maxbytes' => $course->maxbytes,
            'context' => $context,
        ];
        $attachmentoptions = [
            'subdirs' => false,
            'maxfiles' => $quest->nattachments,
            'maxbytes' => $quest->maxbytes,
            'context' => $context,
        ];
        $newchallenge = (object)[
            'id' => null,
            'title' => shorten_text($selectedquestions[0]->name, 100),
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
        ];
        $newchallenge = file_prepare_standard_editor(
            $newchallenge,
            'description',
            $descriptionoptions,
            $context,
            'mod_quest',
            'submission',
            null
        );
        $newchallenge = file_prepare_standard_filemanager(
            $newchallenge,
            'attachment',
            $attachmentoptions,
            $context,
            'mod_quest',
            'attachment',
            null
        );
        $composerform = new \mod_quest\form\challenge_form(null, [
            'challenge' => $newchallenge,
            'quest' => $quest,
            'cm' => $cm,
            'definitionoptions' => $descriptionoptions,
            'attachmentoptions' => $attachmentoptions,
            'action' => 'submitchallenge',
            'composerquestions' => $selectedquestions,
        ], 'post', '', ['class' => 'quest-question-composer']);
        $PAGE->requires->js_call_amd('mod_quest/question_composer', 'init');
        $composerform->display();
    } else {
        echo $OUTPUT->heading(get_string('composequestionchallenge', 'quest'), 3);
        echo html_writer::div(get_string('questioncomposerinstructions', 'quest'), 'alert alert-info');
    }
    echo $OUTPUT->footer();
    exit();
} else if ($action === 'processexistingqchallenge') {
    // Create a challenge linked to an already-existing question bank question.
    require_sesskey();
    require_capability('mod/quest:addchallenge', $context);
    if (!\mod_quest\challenge_type_policy::allows_question_bank($quest, $ismanager)) {
        throw new \moodle_exception('questionbankdisabled', 'quest');
    }
    $questionid = required_param('questionid', PARAM_INT);
    require_once($CFG->libdir . '/questionlib.php');
    $question = \mod_quest\question\bank_provider::require_question($questionid, $context);
    $entryid = (int)$question->questionbankentryid;
    // Get the highest version question record for this entry.
    $latestq = $DB->get_record_sql(
        "SELECT q.*, qv.version, qv.status
           FROM {question} q
           JOIN {question_versions} qv ON qv.questionid = q.id
          WHERE qv.questionbankentryid = :eid AND qv.status = 'ready'
          ORDER BY qv.version DESC",
        ['eid' => $entryid],
        IGNORE_MULTIPLE
    );
    if (!$latestq) {
        throw new \moodle_exception('questiondoesnotexist', 'question');
    }
    $newchallenge = new stdClass();
    $newchallenge->questid      = $quest->id;
    $newchallenge->userid       = $USER->id;
    $newchallenge->title        = shorten_text($latestq->name, 100);
    $newchallenge->description  = $latestq->questiontext;
    $newchallenge->descriptionformat = !empty($latestq->questiontextformat) ? $latestq->questiontextformat : FORMAT_HTML;
    $newchallenge->descriptiontrust  = 0;
    $newchallenge->timecreated  = time();
    $timenow = time();
    $newchallenge->datestart    = max($timenow, (int)$quest->datestart);
    $challengeend                = $newchallenge->datestart + (int)$quest->timemaxquestion * 24 * 3600;
    $newchallenge->dateend      = min($challengeend, (int)$quest->dateend);
    $newchallenge->pointsmax    = $quest->maxcalification;
    $newchallenge->pointsmin    = $quest->mincalification;
    $newchallenge->initialpoints      = $quest->initialpoints;
    $newchallenge->attachment         = 0;
    $newchallenge->perceiveddifficulty = -1;
    $newchallenge->nanswers     = 0;
    $newchallenge->nanswerscorrect = 0;
    // Teachers are approved automatically.
    $newchallenge->state        = SUBMISSION_STATE_APROVED;
    $newchallenge->id = $DB->insert_record('quest_submissions', $newchallenge);
    // Link using null version = always resolve to latest ready version.
    \mod_quest\question\question_reference_service::set_challenge_question(
        $context->id,
        (int)$newchallenge->id,
        (int)$entryid,
        // Always resolve to the latest version.
        null
    );
    quest_update_challenge_calendar($cm, $quest, $newchallenge);
    quest_grade_updated($quest, $USER->id);
    redirect(
        new moodle_url('/mod/quest/challenges.php', ['id' => $cm->id, 'cid' => $newchallenge->id, 'action' => 'showchallenge']),
        get_string('challengeadded', 'quest'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action === 'processnewqchallenge') {
    require_sesskey();
    $canaddchallenge = has_capability('mod/quest:addchallenge', $context);
    if (!$canaddchallenge) {
        throw new \moodle_exception('nocapabilityaddchallenge', 'quest');
    }
    if (!\mod_quest\challenge_type_policy::allows_question_bank($quest, $ismanager)) {
        throw new \moodle_exception('questionbankdisabled', 'quest');
    }
    $lastchanged = optional_param('lastchanged', 0, PARAM_INT);
    if ($lastchanged <= 0) {
        redirect(new moodle_url('/mod/quest/challenges.php', ['id' => $cm->id]));
    }
    $question = \mod_quest\question\bank_provider::require_question($lastchanged, $context);
    $newchallenge = new stdClass();
    $newchallenge->questid = $quest->id;
    $newchallenge->userid = $USER->id;
    $newchallenge->title = shorten_text($question->name, 100);
    $newchallenge->description = $question->questiontext;
    $newchallenge->descriptionformat = !empty($question->questiontextformat) ? $question->questiontextformat : FORMAT_HTML;
    $newchallenge->descriptiontrust = 0;
    $newchallenge->timecreated = time();
    $timenow = time();
    $newchallenge->datestart = max($timenow, (int)$quest->datestart);
    $challengeend = $newchallenge->datestart + (int)$quest->timemaxquestion * 24 * 3600;
    $newchallenge->dateend = min($challengeend, (int)$quest->dateend);
    $newchallenge->pointsmax = $quest->maxcalification;
    $newchallenge->pointsmin = $quest->mincalification;
    $newchallenge->initialpoints = $quest->initialpoints;
    $newchallenge->attachment = 0;
    $newchallenge->perceiveddifficulty = -1;
    $newchallenge->nanswers = 0;
    $newchallenge->nanswerscorrect = 0;
    $canapprove = has_capability('mod/quest:approvechallenge', $context);
    if ($canapprove) {
        $newchallenge->state = SUBMISSION_STATE_APROVED;
    } else {
        $newchallenge->state = SUBMISSION_STATE_APPROVAL_PENDING;
    }
    $newchallenge->id = $DB->insert_record('quest_submissions', $newchallenge);
    // Link question in question_references.
    $entryid = $DB->get_field_sql(
        "SELECT qv.questionbankentryid
           FROM {question_versions} qv
          WHERE qv.questionid = :qid",
        ['qid' => $question->id]
    );
    if ($entryid) {
        \mod_quest\question\question_reference_service::set_challenge_question(
            $context->id,
            (int)$newchallenge->id,
            (int)$entryid,
            null
        );
    }
    // If state is approval pending, add approval_pending tag.
    if ((int)$newchallenge->state === SUBMISSION_STATE_APPROVAL_PENDING) {
        \mod_quest\question\question_reference_service::tag_approval_pending((int)$question->id, $context);
    }
    quest_update_challenge_calendar($cm, $quest, $newchallenge);
    quest_grade_updated($quest, $USER->id);
    $msg = get_string('challengeadded', 'quest');
    if ((int)$newchallenge->state === SUBMISSION_STATE_APPROVAL_PENDING) {
        $msg .= ' (' . get_string('phase1submission', 'quest') . ')';
    }
    redirect(
        new moodle_url('/mod/quest/challenges.php', ['id' => $cm->id, 'cid' => $newchallenge->id, 'action' => 'showchallenge']),
        $msg,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action == 'submitchallenge') {
    // Check if the user has enough capability to add the submission.
    $canaddchallenge = has_capability('mod/quest:addchallenge', $context);
    if (!$canaddchallenge) {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo "<center>" . get_string('nocapabilityaddchallenge', 'quest') . "</center>";
        echo $OUTPUT->continue_button("view.php?id=$cm->id");
        echo $OUTPUT->footer();
        exit();
    }
    $hascomposition = !empty(quest_get_submitted_question_order());
    if (!$hascomposition && optional_param('composer_mode', 0, PARAM_BOOL)) {
        throw new \moodle_exception('noquestionsselected', 'quest');
    }
    if ($hascomposition) {
        if (!\mod_quest\challenge_type_policy::allows_question_bank($quest, $ismanager)) {
            throw new \moodle_exception('questionbankdisabled', 'quest');
        }
    } else if (!\mod_quest\challenge_type_policy::allows_simple($quest, $ismanager)) {
        throw new \moodle_exception('simplechallengedisabled', 'quest');
    }
    $newchallenge = new stdClass();
    $newchallenge->id = null;
    $descriptionoptions = ['trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes,
                    'context' => $context];
    $attachmentoptions = ['subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes,
                    'context' => $context];
    $newchallenge = file_prepare_standard_editor(
        $newchallenge,
        'description',
        $descriptionoptions,
        $context,
        'mod_quest',
        'submission',
        $newchallenge->id
    );
    $newchallenge = file_prepare_standard_filemanager(
        $newchallenge,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_quest',
        'attachment',
        $newchallenge->id
    );
    $mform = new \mod_quest\form\challenge_form(
        null,
        ['challenge' => $newchallenge, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions,
        'attachmentoptions' => $attachmentoptions,
        'action' => $action]
    );
    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($newchallenge = $mform->get_data()) {
        $authorid = $USER->id;
        $questionitems = $hascomposition
            ? quest_get_submitted_question_composition([], null, null, $context)
            : [];
        quest_upload_challenge(
            $quest,
            $newchallenge,
            $canaddchallenge,
            $cm,
            $descriptionoptions,
            $attachmentoptions,
            $context,
            $action,
            $authorid,
            false,
            $questionitems
        );
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(
            get_string("submitchallengeassignment", "quest") . ":",
            "submitchallengeassignment",
            "quest"
        );
        $mform->display();
    }
} else if ($action == 'modif') {
    // The sid value was read at the top, accepting both sid and cid parameters.
    if ($sid === null) {
        throw new \moodle_exception('missingparam', '', '', 'sid');
    }
    $challenge = $DB->get_record("quest_submissions", ["id" => $sid, "questid" => $quest->id], '*', MUST_EXIST);
    $titlechallenge = $challenge->title;
    quest_add_breadcrumbs($cm, $challenge, null, get_string('adjustchallenge', 'quest'));
    if (($challenge->userid != $USER->id) && (!$caneditchallenges)) {
        throw new \moodle_exception('nopermissions', 'error', '', "Edit submission: Only teachers and autors can look this page");
    }
    // If it's the author editing their own challenge, ensure they have the necessary
    // question bank capabilities (editmine, viewmine) so the Edit link in the
    // question bank notification works correctly.
    if ($challenge->userid == $USER->id && !$caneditchallenges) {
        \mod_quest\question\bank_provider::ensure_student_question_capabilities($context);
    }
    $descriptionoptions = ['trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes,
                    'context' => $context];
    $attachmentoptions = ['subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes];
    $linkedquestion = \mod_quest\question\question_reference_service::get_question_for_challenge((int)$challenge->id);
    $composerquestions = [];
    $existingquestions = [];
    if ($linkedquestion) {
        $existingquestions = \mod_quest\question\question_reference_service::get_challenge_questions((int)$challenge->id);
        $questionsbyentry = [];
        foreach ($existingquestions as $slot) {
            $entryid = (int)$slot->reference->questionbankentryid;
            $questionsbyentry[$entryid] = (object)[
                'question' => $slot->question,
                'maxmark' => (float)$slot->maxmark,
                'fixedversion' => $slot->reference->version !== null,
            ];
        }
        $requestedquestionids = optional_param_array('questionids', [], PARAM_INT);
        $lastchanged = optional_param('lastchanged', 0, PARAM_INT);
        if ($lastchanged > 0) {
            $requestedquestionids[] = $lastchanged;
        }
        if ($requestedquestionids) {
            require_capability('mod/quest:addchallenge', $context);
            if (!\mod_quest\challenge_type_policy::allows_question_bank($quest, $ismanager)) {
                throw new \moodle_exception('questionbankdisabled', 'quest');
            }
        }
        require_once($CFG->libdir . '/questionlib.php');
        $requestedbyentry = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $requestedquestionids)))) as $questionid) {
            $metadata = \mod_quest\question\bank_provider::get_question($questionid);
            $entryid = (int)$metadata->questionbankentryid;
            if (!empty($questionsbyentry[$entryid]->fixedversion)) {
                // A pinned slot keeps its version until the user changes the selector.
                continue;
            }
            $requestedbyentry[$entryid] = $questionid;
        }
        foreach ($requestedbyentry as $entryid => $questionid) {
            $question = \mod_quest\question\bank_provider::require_question($questionid, $context);
            $questionsbyentry[$entryid] = (object)[
                'question' => $question,
                'maxmark' => $questionsbyentry[$entryid]->maxmark ?? 1.0,
            ];
        }
        foreach ($questionsbyentry as $item) {
            $item->question->maxmark = $item->maxmark;
            $composerquestions[] = $item->question;
        }
    }
    if (!$linkedquestion) {
        $challenge = file_prepare_standard_editor(
            $challenge,
            'description',
            $descriptionoptions,
            $context,
            'mod_quest',
            'submission',
            $challenge->id
        );
    }
    $challenge = file_prepare_standard_filemanager(
        $challenge,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_quest',
        'attachment',
        $challenge->id
    );
    $draftitemid = file_get_submitted_draft_itemid('introattachments');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_quest', 'attachment', 0, ['subdirs' => 0]);
    $challenge->attachment = $draftitemid;
    $mform = new \mod_quest\form\challenge_form(
        null,
        ['challenge' => $challenge, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions,
                            'attachmentoptions' => $attachmentoptions, 'action' => $action,
                            'linkedquestion' => $linkedquestion, 'composerquestions' => $composerquestions],
        'post',
        '',
        ['class' => $composerquestions ? 'quest-question-composer' : '']
    );
    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($modifchallenge = $mform->get_data()) {
        $authorid = $challenge->userid;
        $questionitems = $linkedquestion
            ? quest_get_submitted_question_composition($existingquestions, null, null, $context)
            : [];
        quest_upload_challenge(
            $quest,
            $modifchallenge,
            $caneditchallenges,
            $cm,
            $descriptionoptions,
            $attachmentoptions,
            $context,
            $action,
            $authorid,
            (bool)$linkedquestion,
            $questionitems
        );
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string("modifsubmission", "quest", $titlechallenge), "modifsubmission", "quest");
        // Question bank notification in the modification view.
        // Show a banner if this challenge is already linked to a question bank question.
        // Visible to: teachers (editchallengeall) and the author while in approval_pending state.
        if ($linkedquestion && $composerquestions) {
            $canaddqbankquestions = (has_capability('mod/quest:addchallenge', $context) || $ismanager) &&
                \mod_quest\challenge_type_policy::allows_question_bank($quest, $ismanager);
            if ($canaddqbankquestions) {
                $questionids = array_map(static fn($question) => (int)$question->id, $composerquestions);
                $questionbankbutton = html_writer::tag('button', get_string('addfromquestionbank', 'quest'), [
                    'type' => 'button',
                    'class' => 'btn btn-outline-primary',
                    'data-action' => 'questionbank',
                    'data-header' => get_string('addfromquestionbank', 'quest'),
                    'data-return-action' => 'modif',
                    'data-challengeid' => (int)$challenge->id,
                    'data-questionids' => implode(',', $questionids),
                ]);
                if (\mod_quest\question\bank_provider::has_bank_helper()) {
                    $banks = \mod_quest\question\bank_provider::get_available_banks($course->id);
                    if ($banks) {
                        $bank = reset($banks);
                        $PAGE->requires->js_call_amd(
                            'mod_quest/modal_quest_question_bank',
                            'init',
                            [$context->id, (int)$bank->modid, $cm->id, $course->id]
                        );
                    }
                } else {
                    $PAGE->requires->js_call_amd(
                        'mod_quest/modal_quest_question_bank_45',
                        'init',
                        [$context->id, (int)$cm->id, (int)$course->id]
                    );
                }
                $returnurl = quest_question_composer_url($cm->id, 'modif', $questionids, $challenge->id);
                $category = \mod_quest\question\bank_provider::get_or_create_activity_category($context);
                require_once($CFG->dirroot . '/question/editlib.php');
                require_once($CFG->dirroot . '/question/bank/editquestion/classes/editquestion_helper.php');
                $cancreatequestion = has_capability('moodle/question:add', $context) ||
                    has_capability('mod/quest:addchallenge', $context);
                $createquestion = $OUTPUT->render(new \qbank_editquestion\output\add_new_question(
                    (int)$category->id,
                    [
                        'cmid' => $cm->id,
                        'returnurl' => $returnurl->out_as_local_url(false),
                    ],
                    $cancreatequestion
                ));
                echo html_writer::div(
                    $questionbankbutton . $createquestion,
                    'quest-composer-toolbar d-flex flex-wrap align-items-center gap-2 mb-3'
                );
            }
            $PAGE->requires->js_call_amd('mod_quest/question_composer', 'init');
        }
        $mform->display();
    }
} else if ($action === 'exporttoqbank') {
    require_sesskey();
    require_capability('mod/quest:manage', $context);
    $challenge = $DB->get_record("quest_submissions", ['id' => $sid, 'questid' => $quest->id], '*', MUST_EXIST);
    $question = \mod_quest\question\open_question_exporter::export_challenge($quest, $challenge, $context);
    $catparam = !empty($question->category) ? "{$question->category},{$context->id}" : '';
    $qbankurl = new moodle_url('/question/edit.php', array_filter(['cmid' => $cm->id, 'cat' => $catparam]));
    $qbanklink = \html_writer::link(
        $qbankurl,
        get_string('viewinquestionbank', 'quest'),
        ['class' => 'alert-link font-weight-bold ms-1 text-decoration-underline']
    );
    $message = get_string('exportedtoquestionbank', 'quest') . ' [' . $qbanklink . ']';
    redirect(
        new moodle_url('/mod/quest/challenges.php', ['id' => $cm->id, 'sid' => $sid, 'action' => 'showsubmission']),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action == 'showsubmission') {
    // The sid value was read at the top, accepting both sid and cid parameters.
    if ($sid === null) {
        throw new \moodle_exception('missingparam', '', '', 'sid');
    }
    $challenge = $DB->get_record("quest_submissions", ["id" => $sid, "questid" => $quest->id], '*', MUST_EXIST);
    if ((!($canpreview)) && ($challenge->userid != $USER->id && ($challenge->datestart > time() || $challenge->state == 1))) {
        throw new \moodle_exception('notpermissionsubmission', 'quest');
    }
    if (
        ($challenge->datestart < time()) && ($challenge->dateend > time()) &&
            ($challenge->nanswerscorrect < $quest->nmaxanswers)
    ) {
        $challenge->phase = SUBMISSION_PHASE_ACTIVE; // ...active.
    } else {
        $challenge->phase = SUBMISSION_PHASE_CLOSED; // ...closed.
    }
    if (
        ($quest->permitviewautors == 1) && ($challenge->phase == SUBMISSION_PHASE_CLOSED) &&
             ($challenge->state == SUBMISSION_STATE_APROVED) && ($challenge->datestart < time()) ||
             has_capability('mod/quest:viewotherattemptsowners', $context)
    ) {
        $permitviewautors = 1;
    } else {
        $permitviewautors = 0;
    }
    // Helper: author of a challenge pending approval.
    // Authors of their own pending-approval challenge get edit-level access to
    // modify the challenge and view/edit the linked question bank question.
    $isownpending = ($challenge->userid == $USER->id)
        && ($challenge->state == SUBMISSION_STATE_APPROVAL_PENDING);
    $title = '"' . $challenge->title . '"';
    if (($canpreview) || ($challenge->userid == $USER->id) || ($permitviewautors == 1)) {
        $title .= ' ' . get_string('by', 'quest') . ' ' . quest_fullname($challenge->userid, $course->id);
    }
    $PAGE->set_title(format_string($quest->name . ' ' . $challenge->title));
    $PAGE->set_heading($course->fullname);
    quest_add_breadcrumbs($cm, $challenge);
    echo $OUTPUT->header();
    // Flag to force a recalculation of team statistics and scores.
    $debugrecalculate = optional_param('recalculate', 'no', PARAM_ALPHA);
    $recalculatelink = '';
    if ($debugrecalculate === 'yes') {
        require_capability('mod/quest:manage', $context);
        require_sesskey();
        require_once("scores_lib.php");
        print("<p>Fixing submission stats...</p>");
        $challenge = quest_update_challenge_counts($challenge->id);
    }
    // Action bar.
    // Build the action bar: modify, answer, assessment, reassessment, recalculate, and export.
    $actionbarbtns = '';
    $answerbutton = '';
    // Modify button: teachers always; authors while their challenge is pending approval.
    if (has_capability('mod/quest:editchallengeall', $context) || $isownpending) {
        $modifurl = new moodle_url(
            '/mod/quest/challenges.php',
            ['action' => 'modif', 'id' => $cm->id, 'sid' => $challenge->id]
        );
        $actionbarbtns .= '<a href="' . $modifurl->out() . '" class="btn btn-sm btn-outline-secondary">' .
            '<i class="fa fa-pencil me-1" aria-hidden="true"></i>' . get_string('adjustchallenge', 'quest') . '</a> ';
    }
    // Approve button.
    if (
        has_capability('mod/quest:approvechallenge', $context) &&
            $challenge->state == SUBMISSION_STATE_APPROVAL_PENDING
    ) {
        $approveurl = new moodle_url(
            '/mod/quest/challenges.php',
            ['id' => $cm->id, 'cid' => $challenge->id, 'action' => 'approve']
        );
        $actionbarbtns .= '<a href="' . $approveurl->out() . '" class="btn btn-sm btn-success">' .
            '<i class="fa fa-check me-1" aria-hidden="true"></i>' . get_string('approve', 'quest') . '</a> ';
    }
    // Answer button (if user can answer and is not the author).
    $cananswer = has_capability('mod/quest:attempt', $context);
    if ($ismanager || ($cananswer && $challenge->userid != $USER->id)) {
        $answered = false;
        if ($answers = quest_get_user_answers($challenge, $USER)) {
            foreach ($answers as $answer) {
                if ($answer->submissionid == $challenge->id) {
                    $answered = true;
                    break;
                }
            }
        }
        if (
            ($ismanager && !$answered) ||
                ($answered == false && $challenge->phase == SUBMISSION_PHASE_ACTIVE &&
                $challenge->state == SUBMISSION_STATE_APROVED)
        ) {
            $answerurl = new moodle_url(
                '/mod/quest/answer.php',
                ['id' => $cm->id, 'uid' => $USER->id, 'action' => 'answer', 'sid' => $challenge->id]
            );
            $answerbutton = '<a href="' . $answerurl->out() . '" class="btn btn-sm btn-primary">' .
                '<i class="fa fa-reply me-1" aria-hidden="true"></i>' . get_string('reply', 'quest') . '</a>';
            $actionbarbtns .= $answerbutton . ' ';
        }
    }
    // See assessment (author or manager).
    if ($ismanager || ($challenge->userid == $USER->id || $challenge->phase != SUBMISSION_PHASE_ACTIVE)) {
        $assessmentautor = quest_get_challenge_assessment($challenge);
        if ($assessmentautor) {
            $actionbarbtns .= '<a href="viewassessmentautor.php?aid=' . $assessmentautor->id .
                '" class="btn btn-sm btn-outline-info">' .
                '<i class="fa fa-eye me-1" aria-hidden="true"></i>' .
                get_string('seeassessmentautor', 'quest') . '</a> ';
        }
    }
    // Re-assess / Evaluate (manager only).
    if ($ismanager) {
        $assessmentautor = quest_get_challenge_assessment($challenge);
        $evalurl = new moodle_url(
            '/mod/quest/assess_autors.php',
            ['id' => $cm->id, 'sid' => $challenge->id, 'action' => 'evaluate']
        );
        $evallabel = $assessmentautor ? get_string('reevaluate', 'quest') : get_string('evaluate', 'quest');
        $actionbarbtns .= '<a href="' . $evalurl->out() . '" class="btn btn-sm btn-outline-warning">' .
            '<i class="fa fa-star-half-o me-1" aria-hidden="true"></i>' . $evallabel . '</a> ';
    }
    // Recalc (manager, debug).
    if ($ismanager) {
        $recalcurl = new moodle_url(
            '/mod/quest/challenges.php',
            ['id' => $cm->id, 'action' => 'showsubmission', 'sid' => $challenge->id,
            'recalculate' => 'yes',
            'sesskey' => sesskey()]
        );
        $actionbarbtns .= '<a href="' . $recalcurl->out() . '" class="btn btn-sm btn-outline-secondary" title="Recalc stats">' .
            '<i class="fa fa-refresh me-1" aria-hidden="true"></i>Recalc</a> ';
    }
    // Specimen assessment form link (always).
    $specimenurl = new moodle_url(
        '/mod/quest/assessments.php',
        ['id' => $cm->id, 'sid' => $challenge->id, 'viewgeneral' => 0,
        'action' => 'displaygradingform',
        'sesskey' => sesskey()]
    );
    $actionbarbtns .= '<a href="' . $specimenurl->out() . '" class="btn btn-sm btn-outline-secondary">' .
        '<i class="fa fa-list-alt me-1" aria-hidden="true"></i>' .
        get_string('specimenassessmentformanswer', 'quest') . '</a> ';
    $actionbarbtns .= $OUTPUT->help_icon('specimenanswer', 'quest');
    $anylinkedq = \mod_quest\question\question_reference_service::get_question_for_challenge((int)$challenge->id);
    // Offer export for open challenges; linked question challenges need no bank notice.
    if (($ismanager || $isownpending) && !$anylinkedq) {
        $exporturl = new moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id, 'sid' => $challenge->id,
            'action' => 'exporttoqbank', 'sesskey' => sesskey(),
        ]);
        $actionbarbtns .= '<a href="' . $exporturl->out() . '" class="btn btn-sm btn-outline-dark ms-2">' .
            '<i class="fa fa-upload me-1"></i> ' . get_string('exporttoquestionbank', 'quest') . '</a>';
    }
    // Render the action bar.
    echo '<div class="quest-challenge-action-bar d-flex flex-wrap align-items-center gap-2 mb-3 p-2' .
         ' bg-light rounded border">' . $actionbarbtns . '</div>';
    // End action bar.
    echo $OUTPUT->heading($title);
    $attentionstatuses = quest_get_challenge_attention_status($challenge, $cm, $context);
    $challengebadges = [];
    foreach ($attentionstatuses as $attentionstatus) {
        $attentionlabel = html_writer::tag('i', '', ['class' => $attentionstatus['icon'], 'aria-hidden' => 'true']) .
            ' ' . s($attentionstatus['label']);
        $challengebadges[] = html_writer::link(
            $attentionstatus['url'],
            $attentionlabel,
            ['class' => $attentionstatus['class'], 'title' => $attentionstatus['label']]
        );
    }
    if ($anylinkedq && \mod_quest\service\autograde_service::is_fully_automatic_challenge((int)$challenge->id)) {
        $challengebadges[] = html_writer::span(get_string('autograding', 'quest'), 'badge bg-success');
    }
    $challengephaselabel = quest_challenge_phase($challenge, $quest, $course);
    if (quest_should_show_challenge_phase_badge($challenge, $attentionstatuses, $challengephaselabel)) {
        $challengebadges[] = html_writer::span(
            $challengephaselabel,
            'badge quest-phase-badge quest-challenge-phase-badge'
        );
    }
    echo '<div class="quest-challenge-status-badges d-flex flex-wrap gap-2 mb-3">' .
        implode(' ', $challengebadges) . '</div>';
    echo '<div class="row g-4 align-items-start mb-4">';
    echo '<div class="col-lg-8 col-md-7">';
    quest_print_challenge_info($quest, $challenge);
    echo quest_challenge_comment_html($challenge, $context);
    echo '</div>';
    echo '<div class="col-lg-4 col-md-5 d-flex justify-content-end">';
    // Score evolution graph (1:1, floated right).
    quest_print_score_graph($quest, $challenge);
    echo '</div>';
    echo '</div>';
    if (!$anylinkedq) {
        echo $OUTPUT->heading(get_string('description', 'quest'));
        /*
        * Wording of the challenge
        */
        quest_print_challenge($quest, $challenge);
    } else {
        echo $OUTPUT->heading(get_string('description', 'quest'));
        echo \mod_quest\service\autograde_service::render_challenge_questions_preview(
            $quest,
            $challenge,
            $context,
            (int)$cm->id,
            $ismanager || $isownpending
        );
    }
    if (!empty($answerbutton)) {
        echo '<div class="quest-question-answer-action d-flex justify-content-center mb-4">';
        echo $answerbutton;
        echo '</div>';
    }
    // End question bank preview.
    $changegroup = optional_param('group', -1, PARAM_INT);// Group change requested?
    $groupmode = groups_get_activity_group($cm); // Groups are being used?
    $currentgroup = groups_get_course_group($course);
    if (
        ($challenge->datestart < $timenow) && ($challenge->dateend > $timenow) &&
        ($challenge->nanswerscorrect < $quest->nmaxanswers)
    ) {
        $challenge->phase = SUBMISSION_PHASE_ACTIVE;
    }
    $sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
    $dir = optional_param('dir', 'ASC', PARAM_ALPHA);
    quest_print_table_answers($quest, $challenge, $course, $cm, $sort, $dir);
    if (!empty($repeatactionsbelow)) {
        echo $actionlinks . $recalculatelink;
    }
    \mod_quest\event\challenge_viewed::create_from_parts($USER, $challenge, $cm)->trigger();
    echo $OUTPUT->continue_button("view.php?id=$cm->id");
} else if ($action == 'approve') {
    $challenge = $DB->get_record("quest_submissions", ["id" => $sid, "questid" => $quest->id], '*', MUST_EXIST);
    $authorid = $challenge->userid;
    quest_add_breadcrumbs($cm, $challenge, null, get_string('approve', 'quest'));
    if (!$canapprove) {
        throw new \moodle_exception('nopermissions', 'error', '', "Approve challenge: Not enought permissions to take this action");
    }
    $linkedquestion = \mod_quest\question\question_reference_service::get_question_for_challenge((int)$challenge->id);
    $descriptionoptions = ['trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes,
                    'context' => $context];
    $attachmentoptions = ['subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes];
    if (!$linkedquestion) {
        $challenge = file_prepare_standard_editor(
            $challenge,
            'description',
            $descriptionoptions,
            $context,
            'mod_quest',
            'submission',
            $challenge->id
        );
    }
    $challenge = file_prepare_standard_filemanager(
        $challenge,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_quest',
        'attachment',
        $challenge->id
    );
    $mform = new \mod_quest\form\challenge_form(
        null,
        ['challenge' => $challenge, 'quest' => $quest,
                            'cm' => $cm, 'definitionoptions' => $descriptionoptions,
                            'attachmentoptions' => $attachmentoptions, 'action' => $action,
        'linkedquestion' => $linkedquestion]
    );
    if ($mform->is_cancelled()) {
        redirect("challenges.php?id=$cm->id&amp;action=showsubmission&amp;sid=$sid");
    } else if ($challenge = $mform->get_data()) {
        if ((int)$challenge->sid !== (int)$sid) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        if (isset($challenge->submitbuttonapprove)) {
            quest_upload_challenge(
                $quest,
                $challenge,
                $canapprove,
                $cm,
                $descriptionoptions,
                $attachmentoptions,
                $context,
                'approve',
                $authorid,
                (bool)$linkedquestion
            );
        } else { // ...save but not approve.
            $action = 'modif';
            quest_upload_challenge(
                $quest,
                $challenge,
                $canapprove,
                $cm,
                $descriptionoptions,
                $attachmentoptions,
                $context,
                'modif',
                $authorid,
                (bool)$linkedquestion
            );
        }
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string("approvesubmission", "quest"), "approvesubmission", "quest");
        if ($linkedquestion) {
            [$quba, $slots] = \mod_quest\service\autograde_service::get_or_create_challenge_preview_usage(
                $quest,
                $challenge,
                \mod_quest\question\question_reference_service::get_challenge_questions((int)$challenge->id),
                $context
            );
            echo '<div class="card border-0 mb-4" id="quest-qpreview-panel"><div class="card-body p-4 bg-white rounded shadow-sm">';
            echo \mod_quest\service\autograde_service::render_questions_preview($quba, $context, (int)$cm->id, true);
            echo '</div></div>';
        }
        $mform->display();
    }
} else if ($action == 'showsubmissionsuser') {
    if (!$canpreview) {
        throw new \moodle_exception('nopermissions', 'error', '', "Only teachers can look at this page");
    }
    $userid = required_param('uid', PARAM_INT);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    // Now prepare table with student assessments and submissions.
    $tablesort = new stdclass();
    $tablesort->data = [];
    $tablesort->sortdata = [];
    $indice = 0;
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('nostudentsyet'));
        echo $OUTPUT->footer();
        exit();
    }
    $usertemp = null;
    foreach ($users as $u) {
        if ($u->id == $userid) {
            $usertemp = $u;
            break;
        }
    }
    if (!$usertemp) {
        $usertemp = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }
    $user = $usertemp;
    $title = get_string('showsubmissions', 'quest');
    if ($canpreview) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . quest_fullname($user->id, $course->id);
    }
    echo $OUTPUT->heading($title);
    // Skip if student not in group.
    $initialpoints = [];
    $nanswerscorrect = [];
    $datesstart = [];
    $datesend = [];
    $dateanswercorrect = [];
    $pointsmax = [];
    $pointsmin = [];
    $pointsanswercorrect = [];
    $tinitial = [];
    $state = [];
    $pointsnmaxanswers = [];
    $forms = [];
    $incline = [];
    if ($challenges = quest_get_user_challenges($quest, $user)) {
        foreach ($challenges as $challenge) {
            $data = [];
            $sortdata = [];
            if (
                ($challenge->datestart < $timenow) && ($challenge->dateend > $timenow) &&
                     ($challenge->nanswerscorrect < $quest->nmaxanswers)
            ) {
                $challenge->phase = SUBMISSION_PHASE_ACTIVE;
            }
            $data[] = quest_print_challenge_title($quest, $challenge) .
                     " <a href=\"challenges.php?action=modif&amp;id=$cm->id&amp;cid=$challenge->id\">" .
                     $OUTPUT->pix_icon('/t/edit', get_string('modif', 'quest')) . '</a>' .
                     " <a href=\"challenges.php?action=confirmdelete&amp;id=$cm->id&amp;cid=$challenge->id\">" .
                     $OUTPUT->pix_icon('/t/delete', get_string('delete', 'quest')) . '</a>';
            $sortdata['title'] = strtolower($challenge->title);
            $data[] = quest_challenge_phase($challenge, $quest, $course);
            $sortdata['phase'] = quest_challenge_phase($challenge, $quest, $course);
            $nanswersassess = 0;
            if (
                $answers = $DB->get_records_select(
                    "quest_answers",
                    "questid=? AND submissionid=?",
                    [$quest->id, $challenge->id]
                )
            ) {
                foreach ($answers as $answer) {
                    if (($answer->phase == 1) || ($answer->phase == 2)) {
                        $nanswersassess++;
                    }
                }
            }
            $nanswerswhithoutassess = $challenge->nanswers - $nanswersassess;
            $image = '';
            if (
                $answer = $DB->get_record(
                    "quest_answers",
                    ['questid' => $quest->id, "submissionid" => $challenge->id, "userid" => $USER->id]
                )
            ) {
                $image = $OUTPUT->pix_icon('/t/clear', 'OK');
            }
            $data[] = "<b>" . $challenge->nanswers . ' (' . $challenge->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' .
                     $image . '</b>';
            $sortdata['nanswersshort'] = $challenge->nanswers;
            $sortdata['nanswerscorrectshort'] = $challenge->nanswerscorrect;
            $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;
            $data[] = userdate($challenge->datestart, get_string('strftimedatetimeshort', 'langconfig'));
            $sortdata['datestart'] = $challenge->datestart;
            $data[] = userdate($challenge->dateend, get_string('strftimedatetimeshort', 'langconfig'));
            $sortdata['dateend'] = $challenge->dateend;
            $currentpoints = quest_get_points($challenge, $quest, '');
            $sortdata['calification'] = $currentpoints;
            $tinitialval = (int)$quest->tinitial * 86400;
            $grade = "<form name=\"puntos$indice\"><input name=\"calificacion\" id=\"formscore$indice\" " .
                    "class=\"quest-score-counter\" type=\"text\" value=\"$currentpoints\" size=\"10\" readonly=\"1\" " .
                    "data-datestart=\"{$challenge->datestart}\" " .
                    "data-dateend=\"{$challenge->dateend}\" " .
                    "data-tinitial=\"{$tinitialval}\" " .
                    "data-dateanswercorrect=\"{$challenge->dateanswercorrect}\" " .
                    "data-initialpoints=\"{$challenge->initialpoints}\" " .
                    "data-pointsmax=\"{$challenge->pointsmax}\" " .
                    "data-pointsmin=\"{$challenge->pointsmin}\" " .
                    "data-type=\"{$quest->typecalification}\" " .
                    "style=\"background-color : White; border : Black; color : Black; font-size : 14pt; " .
                    "text-align : center;\"></form>";
            $data[] = $grade;
            $indice++;
            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
    }
    $sort = optional_param('sort', "datestart", PARAM_ALPHA);
    $dir = optional_param('dir', "ASC", PARAM_ALPHA);
    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = [];
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    $table->align = ['left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'];
    $columns = ['title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess',
                    'datestart', 'dateend', 'calification'];
    $table->width = "95%";
    $string = [];
    foreach ($columns as $column) {
        $string[$column] = get_string("$column", 'quest');
        if ($sort != $column) {
            $columnicon = '';
            $columndir = 'ASC';
        } else {
            $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
            if ($column == 'lastaccess') {
                $columnicon = $dir == 'ASC' ? 'up' : 'down';
            } else {
                $columnicon = $dir == 'ASC' ? 'down' : 'up';
            }
            $columnicon = $OUTPUT->pix_icon("t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"challenges.php?id=$id&amp;sid=$sid&amp;uid=$user->id&amp;action=showsubmissionsuser&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }
    $table->head = ["$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart",
                    "$dateend", "$calification"];
    echo html_writer::table($table);
    $grafic = $OUTPUT->pix_icon('t/clear', 'OK');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";
    // Javascript counter support via DOM data attributes.
    $servertime = time();
    $PAGE->requires->js_call_amd('mod_quest/counter', 'init', [$servertime]);
    $continueurl = new moodle_url('viewclasification.php', ['id' => $cm->id]);
    echo $OUTPUT->continue_button($continueurl);
} else if ($action == "showanswersuser") {
    $uid = required_param('uid', PARAM_INT);
    if (
        !$canpreview && !has_capability('mod/quest:viewotherattemptsowners', $context) &&
            (int)$uid !== (int)$USER->id
    ) {
        throw new \moodle_exception('nopermissions', 'error', '', 'view another participant answers');
    }
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }
    $usertemp = null;
    foreach ($users as $u) {
        if ($u->id == $uid) {
            $usertemp = $u;
            break;
        }
    }
    if (!$usertemp) {
        $usertemp = $DB->get_record('user', ['id' => $uid], '*', MUST_EXIST);
    }
    $user = $usertemp;
    $title = get_string('showanswers', 'quest');
    if ($canpreview) {
        $user->imagealt = quest_fullname($user->id, $course->id);
        $title .= ' ' . get_string('of', 'quest') . ' ' . $OUTPUT->user_picture($user) . $user->imagealt;
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    // Now prepare table with student assessments and submissions.
    $tablesort = new stdClass();
    $tablesort->data = [];
    $tablesort->sortdata = [];
    // ...skip if student not in group.
    if ($answers = quest_get_answers($quest, $user)) {
        foreach ($answers as $answer) {
            $data = [];
            $sortdata = [];
            $challenge = $DB->get_record("quest_submissions", ["id" => $answer->submissionid], '*', MUST_EXIST);
            $data[] = quest_print_answer_title($quest, $answer, $challenge) .
                     " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id\">" . "<img src=\"" . $CFG->wwwroot .
                     "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                     " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;aid=$answer->id\">" . "<img src=\"" .
                     $CFG->wwwroot . "/pix/t/delete.svg\" " . 'height="11" width="11" border="0" alt="' .
                     get_string('delete', 'quest') . '" /></a>';
            $sortdata['title'] = strtolower($answer->title);
            $phasehtml = quest_answer_phase($answer, $course);
            $data[] = $phasehtml;
            $sortdata['phase'] = strip_tags($phasehtml);
            $data[] = userdate($answer->date, get_string('strftimedatetimeshort', 'langconfig'));
            $sortdata['dateanswer'] = $answer->date;
            if (($answer->phase == ANSWER_PHASE_GRADED) || ($answer->phase == ANSWER_PHASE_PASSED)) {
                $assessment = $DB->get_record("quest_assessments", ["answerid" => $answer->id]);
            } else {
                $assessment = null;
            }
            $challenge = $DB->get_record('quest_submissions', ['id' => $answer->submissionid], '*', MUST_EXIST);
            $data[] = quest_print_actions_answers($cm, $answer, $challenge, $course, $assessment);
            $sortdata['tassmnt'] = 1;
            $score = quest_answer_grade($quest, $answer, 'ALL');
            if ($answer->pointsmax == 0) {
                $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
            } else {
                $grade = number_format($score, 4) . ' (' . number_format(100 * $score / $answer->pointsmax, 0) .
                        '%) [max ' . number_format($answer->pointsmax, 4) . ']';
            }
            $data[] = $grade;
            $sortdata['calification'] = $score;
            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
    }
    $sort = optional_param('sort', "dateanswer", PARAM_ALPHA);
    $dir = optional_param('dir', "ASC", PARAM_ALPHA);
    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = [];
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    $table->align = ['left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'];
    $columns = ['title', 'phase', 'dateanswer', 'actions', 'calification'];
    $table->width = "95%";
    $string = [];
    foreach ($columns as $column) {
        $string[$column] = get_string("$column", 'quest');
        if ($sort != $column) {
            $columnicon = '';
            $columndir = 'ASC';
        } else {
            $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
            if ($column == 'lastaccess') {
                $columnicon = $dir == 'ASC' ? 'up' : 'down';
            } else {
                $columnicon = $dir == 'ASC' ? 'down' : 'up';
            }
            $columnicon = $OUTPUT->pix_icon("t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"challenges.php?id=$cm->id&amp;sid=$sid&amp;uid=$user->id&amp;action=showanswersuser&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }
    $table->head = ["$title", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification"];
    echo html_writer::table($table);
    print('<br><p>*' . get_string('calification_provisional_msg', 'quest') . '</p>');
    $continueurl = new moodle_url('viewclasification.php', ['id' => $cm->id]);
    echo $OUTPUT->continue_button($continueurl);
} else if ($action == 'showsubmissionsteam') {
    if (!$canpreview) {
        throw new \moodle_exception('nopermissions', 'error', '', "Only teachers can look at this page");
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    // Now prepare table with student assessments and submissions...
    $tablesort = new stdClass();
    $tablesort->data = [];
    $tablesort->sortdata = [];
    $indice = 0;
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('nostudentsyet'));
        echo $OUTPUT->footer();
        exit();
    }
    $team = $DB->get_record("quest_teams", ["id" => required_param('tid', PARAM_INT)], '*', MUST_EXIST);
    $userstemp = [];
    foreach ($users as $user) {
        if (
            $calificationuser = $DB->get_record(
                "quest_calification_users",
                ["questid" => $quest->id, "userid" => $user->id]
            )
        ) {
            if ($calificationuser->teamid == $team->id) {
                $userstemp[] = $user;
            }
        }
    }
    $users = $userstemp;
    $title = get_string('showsubmissions', 'quest');
    if ($canpreview) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . get_string('team', 'quest') . ': ' . s($team->name);
    }
    // Skip if student not in group.
    foreach ($users as $user) {
        if ($challenges = quest_get_user_challenges($quest, $user)) {
            foreach ($challenges as $challenge) {
                $data = [];
                $sortdata = [];
                if (
                    ($challenge->datestart < $timenow) && ($challenge->dateend > $timenow) &&
                         ($challenge->nanswerscorrect < $quest->nmaxanswers)
                ) {
                    $challenge->phase = SUBMISSION_PHASE_ACTIVE;
                }
                $data[] = quest_print_challenge_title($quest, $challenge) .
                         " <a href=\"challenges.php?action=modif&amp;id=$cm->id&amp;cid=$challenge->id\">" .
                         $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest')) . '</a>' .
                         " <a href=\"challenges.php?action=confirmdelete&amp;id=$cm->id&amp;cid=$challenge->id\">" .
                         $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest')) . '</a>';
                $sortdata['title'] = strtolower($challenge->title);
                $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?" .
                        "id=$user->id&amp;course=$course->id\">" . fullname($user) . '</a>';
                $sortdata['firstname'] = strtolower($user->firstname);
                $sortdata['lastname'] = strtolower($user->lastname);
                $phase = quest_challenge_phase($challenge, $quest, $course);
                $data[] = $phase;
                $sortdata['phase'] = $phase;
                $nanswersassess = 0;
                if (
                    $answers = $DB->get_records_select(
                        "quest_answers",
                        "questid=? AND submissionid=?",
                        [$quest->id, $challenge->id]
                    )
                ) {
                    foreach ($answers as $answer) {
                        if (($answer->phase == 1) || ($answer->phase == 2)) {
                            $nanswersassess++;
                        }
                    }
                }
                $nanswerswhithoutassess = $challenge->nanswers - $nanswersassess;
                $image = '';
                if (
                    $answer = $DB->get_record(
                        "quest_answers",
                        ["questid" => $quest->id, "submissionid" => $challenge->id, "userid" => $USER->id]
                    )
                ) {
                    $image = " <img src=\"" . $CFG->wwwroot . "pix/t/clear.png\" />";
                }
                $data[] = "<b>" . $challenge->nanswers . ' (' . $challenge->nanswerscorrect . ') [' .
                        $nanswerswhithoutassess . ']' . $image . '</b>';
                $sortdata['nanswersshort'] = $challenge->nanswers;
                $sortdata['nanswerscorrectshort'] = $challenge->nanswerscorrect;
                $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;
                $data[] = userdate($challenge->datestart, get_string('strftimedatetimeshort', 'langconfig'));
                $sortdata['datestart'] = $challenge->datestart;
                $data[] = userdate($challenge->dateend, get_string('strftimedatetimeshort', 'langconfig'));
                $sortdata['dateend'] = $challenge->dateend;
                $currentpoints = quest_get_points($challenge, $quest, '');
                $sortdata['calification'] = $currentpoints;
                $currentpoints = number_format($currentpoints, 4);
                $tinitialval = (int) $quest->tinitial * 86400;
                $grade = "<form name=\"puntos$indice\">" .
                         "<input id=\"formscore$indice\" class=\"quest-score-counter\" name=\"calificacion\" " .
                         "type=\"text\" value=\"$currentpoints\" size=\"10\" readonly=\"1\" " .
                         "data-datestart=\"{$challenge->datestart}\" " .
                         "data-dateend=\"{$challenge->dateend}\" " .
                         "data-tinitial=\"{$tinitialval}\" " .
                         "data-dateanswercorrect=\"{$challenge->dateanswercorrect}\" " .
                         "data-initialpoints=\"{$challenge->initialpoints}\" " .
                         "data-pointsmax=\"{$challenge->pointsmax}\" " .
                         "data-pointsmin=\"{$challenge->pointsmin}\" " .
                         "data-type=\"{$quest->typecalification}\" " .
                         "style=\"background-color : White; border : Black; color : Black; " .
                         "font-size : 14pt; text-align : center;\" ></form>";
                $data[] = $grade;
                $indice++;
                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }
        }
    }
    $sort = optional_param('sort', "datestart", PARAM_ALPHA);
    $dir = optional_param('dir', "ASC", PARAM_ALPHA);
    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = [];
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    $table->align = ['left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'];
    $columns = ['title', 'firstname', 'lastname', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess',
                    'datestart', 'dateend', 'calification'];
    $table->width = "95%";
    $string = [];
    foreach ($columns as $column) {
        $string[$column] = get_string("$column", 'quest');
        if ($sort != $column) {
            $columnicon = '';
            $columndir = 'ASC';
        } else {
            $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
            if ($column == 'lastaccess') {
                $columnicon = $dir == 'ASC' ? 'up' : 'down';
            } else {
                $columnicon = $dir == 'ASC' ? 'down' : 'up';
            }
            $columnicon = $OUTPUT->pix_icon("t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"challenges.php?id=$id&amp;sid=$sid&amp;tid=$team->id&amp;action=showsubmissionsteam&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "$columnicon</a>";
    }
    $table->head = ["$title", "$firstname / $lastname", "$phase",
                    "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart",
                    "$dateend", "$calification"];
    echo $OUTPUT->heading(get_string('showsubmissionsteam', 'quest'));
    echo html_writer::table($table);
    $grafic = $OUTPUT->pix_icon('t/clear', 'OK');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";
    // Javascript counter support via DOM data attributes.
    $servertime = time();
    $PAGE->requires->js_call_amd('mod_quest/counter', 'init', [$servertime]);
    $continueurl = new moodle_url('challenges.php', ['action' => 'showsubmission', 'sid' => $challenge->id, 'id' => $cm->id]);
    echo $OUTPUT->continue_button($continueurl);
} else if ($action == "showanswersteam") {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer($course);
        exit();
    }
    $team = $DB->get_record("quest_teams", [
        'id' => required_param('tid', PARAM_INT),
        'questid' => $quest->id,
    ], '*', MUST_EXIST);
    $ownteamid = $DB->get_field('quest_calification_users', 'teamid', [
        'questid' => $quest->id,
        'userid' => $USER->id,
    ]);
    if (
        !$canpreview && !has_capability('mod/quest:viewotherattemptsowners', $context) &&
            (int)$ownteamid !== (int)$team->id
    ) {
        throw new \moodle_exception('nopermissions', 'error', '', 'view another team answers');
    }
    $userstemp = [];
    foreach ($users as $user) {
        if (
            $calificationuser = $DB->get_record(
                "quest_calification_users",
                ["questid" => $quest->id, "userid" => $user->id]
            )
        ) {
            if ($calificationuser->teamid == $team->id) {
                $userstemp[] = $user;
            }
        }
    }
    $users = $userstemp;
    $title = get_string('showanswers', 'quest');
    if ($canpreview) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . get_string('team', 'quest') . ': ' . s($team->name);
    }
    echo $OUTPUT->heading($title);
    // Now prepare table with student assessments and submissions.
    $tablesort = new stdClass();
    $tablesort->data = [];
    $tablesort->sortdata = [];
    foreach ($users as $user) {
        // ...skip if student not in group.
        if ($answers = quest_get_answers($quest, $user)) {
            foreach ($answers as $answer) {
                $data = [];
                $sortdata = [];
                $challenge = $DB->get_record("quest_submissions", ["id" => $answer->submissionid], '*', MUST_EXIST);
                $data[] = quest_print_answer_title($quest, $answer, $challenge) .
                         " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id\">" .
                         $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest')) . '</a>' .
                         " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;aid=$answer->id\">" .
                         $OUTPUT->pix_icon('/t/delete', get_string('delete', 'quest')) . '</a>';
                $sortdata['title'] = strtolower($answer->title);
                if ($canpreview) {
                    $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?" .
                            "id=$user->id&amp;course=$course->id\">" . fullname($user) . '</a>';
                    $sortdata['firstname'] = strtolower($user->firstname);
                    $sortdata['lastname'] = strtolower($user->lastname);
                }
                $phasehtml = quest_answer_phase($answer, $course);
                $data[] = $phasehtml;
                $sortdata['phase'] = strip_tags($phasehtml);
                $data[] = userdate($answer->date, get_string('strftimedatetimeshort', 'langconfig'));
                $sortdata['dateanswer'] = $answer->date;
                if (($answer->phase == 1) || ($answer->phase == 2)) {
                    $assessment = $DB->get_record("quest_assessments", ["answerid" => $answer->id]);
                } else {
                    $assessment = null;
                }
                $data[] = quest_print_actions_answers($cm, $answer, $challenge, $course, $assessment);
                $sortdata['tassmnt'] = 1;
                if ($answer->pointsmax == 0) {
                    $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
                } else {
                    $grade = number_format(quest_answer_grade($quest, $answer, 'ALL'), 4) . ' [max ' . number_format(
                        $answer->pointsmax,
                        4
                    ) . ']';
                }
                $data[] = $grade;
                $sortdata['calification'] = quest_answer_grade($quest, $answer, 'ALL');
                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }
        }
    }
    $sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
    $dir = optional_param('dir', "ASC", PARAM_ALPHA);
    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = [];
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    $table->align = ['left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'];
    $columns = ['title', 'firstname', 'lastname', 'phase', 'dateanswer', 'actions', 'calification'];
    $table->width = "95%";
    $string = [];
    foreach ($columns as $column) {
        $string[$column] = get_string("$column", 'quest');
        if ($sort != $column) {
            $columnicon = '';
            $columndir = 'ASC';
        } else {
            $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
            if ($column == 'lastaccess') {
                $columnicon = $dir == 'ASC' ? 'up' : 'down';
            } else {
                $columnicon = $dir == 'ASC' ? 'down' : 'up';
            }
            $columnicon = $OUTPUT->pix_icon("t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"challenges.php?id=$cm->id&amp;sid=$sid&amp;tid=$team->id&amp;action=showanswersteam&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }
    $table->head = ["$title", "$firstname / $lastname", "$phase", "$dateanswer", get_string('actions', 'quest'),
                    "$calification"];
    echo html_writer::table($table);
    $continueurl = (!empty($sid) && $nextchallenge = $DB->get_record('quest_submissions', ['id' => $sid, 'questid' => $quest->id]))
        ? "challenges.php?action=showsubmission&sid=$nextchallenge->id&id=$cm->id"
        : "view.php?id=$cm->id";
    echo $OUTPUT->continue_button($continueurl);
} else if ($action == "recalificationall" && false) { // This action is deprecated.
    $challenge = $DB->get_record("quest_submissions", ["id" => $sid, "questid" => $quest->id], '*', MUST_EXIST);
    quest_recalification_all($challenge, $quest, $course);
    redirect("challenges.php?id=$id&amp;sid=$sid&amp;action=showsubmission");
} else if ($action == "confirmchangeform") {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo "<br><br>";
    $assessmentsurl = new moodle_url(
        '/mod/quest/assessments.php',
        ['id' => $cm->id, 'sid' => $sid, 'newform' => 1, 'change_form' => 0, 'action' => 'editelements',
        'sesskey' => sesskey()]
    );
    $challengesurl = new moodle_url(
        '/mod/quest/challenges.php',
        ['id' => $cm->id, 'sid' => $sid, 'action' => 'showsubmission']
    );
    echo $OUTPUT->confirm(get_string("doyouwantparticularform", "quest"), $assessmentsurl, $challengesurl);
} else {
    throw new \moodle_exception('unknownactionerror', 'quest', '', $action);
}
echo $OUTPUT->footer();
