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
 * Quest answer controller.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("scores_lib.php");

global $DB, $OUTPUT;
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_LOCALURL);

// Allows the script to use only AnswerId..
$aid = optional_param('aid', null, PARAM_INT); // Answer ID..
if ($aid) {
    $answer = $DB->get_record('quest_answers', ['id' => $aid], '*', MUST_EXIST);
    $challenge = $DB->get_record('quest_submissions', [
        'id' => $answer->submissionid,
        'questid' => $answer->questid,
    ], '*', MUST_EXIST);
    quest_require_answer_ownership($answer, (int)$challenge->questid);
    $sid = $challenge->id;
} else {
    $sid = required_param('sid', PARAM_INT); // Submission ID.
    $challenge = $DB->get_record('quest_submissions', ['id' => $sid], '*', MUST_EXIST);
}
$quest = $DB->get_record("quest", ["id" => $challenge->questid], '*', MUST_EXIST);
[$course, $cm] = quest_get_course_and_cm_from_quest($quest);

if (!$redirect && isset($_SERVER["HTTP_REFERER"])) {
    $redirect = urlencode($_SERVER["HTTP_REFERER"] . '#sid=' . $challenge->id);
}

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$isteacher = $ismanager || has_capability('mod/quest:grade', $context);

$action = required_param('action', PARAM_ALPHA);

$url = new moodle_url(
    '/mod/quest/answer.php',
    ['sid' => $sid, 'action' => $action, 'allowcomments' => $allowcomments, 'redirect' => $redirect, 'aid' => $aid]
);
$PAGE->set_url($url);
if ($action === 'answer') {
    quest_add_breadcrumbs($cm, $challenge, null, get_string('answer', 'quest'));
} else if ($aid) {
    $breadcrumppage = $action === 'confirmdelete' ? get_string('delete') : null;
    quest_add_breadcrumbs($cm, $challenge, $answer, $breadcrumppage);
} else {
    quest_add_breadcrumbs($cm, $challenge);
}
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");

$challengeurl = "challenges.php?id=$cm->id&amp;sid=$challenge->id&amp;action=showchallenge";

// Now check whether we need to display a frameset..
if ($action == "answer") {
    if (!quest_user_can_answer_challenge($quest, $challenge, $context)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'answer this challenge');
    }
    if (!quest_user_passed_password($quest, $context)) {
        quest_require_password($quest, $course, optional_param('userpassword', '', PARAM_RAW_TRIMMED));
    }
    // Check if challenge is linked to a Question Bank question.
    $linkedquestion = \mod_quest\question\question_reference_service::get_question_for_challenge((int)$challenge->id);
    if ($linkedquestion) {
        [$quba, $slots] = \mod_quest\service\autograde_service::get_or_create_attempt($quest, $challenge, $USER->id, $context);

        if (data_submitted() && confirm_sesskey() && optional_param('submitqbankanswer', 0, PARAM_BOOL)) {
            $result = \mod_quest\service\autograde_service::process_submission($quest, $challenge, $USER->id, $quba);
            $returnurl = new moodle_url('/mod/quest/challenges.php', [
                'id' => $cm->id,
                'cid' => $challenge->id,
                'action' => 'showchallenge',
            ]);
            $notificationtype = $result['passed']
                ? \core\output\notification::NOTIFY_SUCCESS
                : \core\output\notification::NOTIFY_ERROR;
            redirect($returnurl, $result['message'], null, $notificationtype);
        } else {
            $PAGE->set_title(format_string($quest->name));
            $PAGE->set_heading($course->fullname);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($challenge->title));
            quest_print_challenge($quest, $challenge);
            echo $OUTPUT->heading(get_string('answer', 'quest'));

            echo '<form method="post" action="' . $url->out() . '" class="m-3">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="submitqbankanswer" value="1">';
            echo \mod_quest\service\autograde_service::render_questions($quba);
            echo '<div class="mt-3"><button type="submit" class="btn btn-primary">' . get_string('submit') . '</button></div>';
            echo '</form>';
            echo $OUTPUT->footer();
            exit;
        }
    }

    $answer = new stdClass();
    $answer->id = null;
    $answer->submissionid = $sid;

    $maxfiles = 99; // Limit used for the html editor..

    $descriptionoptions = ['trusttext' => true, 'subdirs' => false, 'maxfiles' => $maxfiles, 'maxbytes' => $course->maxbytes,
                    'context' => $context];
    $attachmentoptions = ['subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes];

    $answer = file_prepare_standard_editor(
        $answer,
        'description',
        $descriptionoptions,
        $context,
        'mod_quest',
        'answer',
        $answer->id
    );
    $answer = file_prepare_standard_filemanager(
        $answer,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_quest',
        'answer_attachment',
        $answer->id
    );

    $mform = new \mod_quest\form\answer_form(
        null,
        ['current' => $answer, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions,
        'attachmentoptions' => $attachmentoptions,
        'action' => $action]
    );
    // The first parameter is $action, null will case the form action to be determined.
    // ...automatically)..

    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($answer = $mform->get_data()) {
        require_sesskey();
        quest_uploadanswer($quest, $answer, $ismanager, $cm, $descriptionoptions, $attachmentoptions, $context);
        redirect(
            new moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id,
            'cid' => $challenge->id,
            'action' => 'showchallenge',
            ]),
            get_string('submittedanswer', 'quest') . " " . get_string('ok'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        $title = '"' . $challenge->title . '" ';
        if ($ismanager || has_capability('mod/quest:viewotherattemptsowners', $context)) {
            $title .= get_string('by', 'quest') . ' ' . quest_fullname($challenge->userid, $course->id);
        }
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading($title);
        echo ("<center><b><a href=\"assessments.php?id=$cm->id&amp;sid=$challenge->id&amp;action=displaygradingform\">" .
                 get_string("specimenassessmentform", "quest") . "</a>" . $OUTPUT->help_icon('specimensubmission', 'quest') .
                 "</b></center>");

        quest_print_challenge($quest, $challenge);
        echo $OUTPUT->heading_with_help(get_string("answersubmission", "quest"), "answersubmission", "quest");

        $mform->display();
        echo $OUTPUT->footer();
    }
} else if ($action == "showanswer") {
    if (($quest->usepassword) && (!$isteacher)) {
        quest_require_password($quest, $course, optional_param('userpassword', '', PARAM_RAW_TRIMMED));
    }
    $aid = required_param('aid', PARAM_INT); // Answer ID..
    $answer = $DB->get_record("quest_answers", ["id" => $aid]);
    if (!$answer) {
        throw new \moodle_exception('answer_not_found', 'quest', $challengeurl, $aid);
    }
    $challenge = $DB->get_record("quest_submissions", ["id" => $answer->submissionid]);
    quest_require_answer_ownership($answer, (int)$quest->id);

    if (
        (!$isteacher) && ($challenge->userid != $USER->id) && ($answer->userid != $USER->id) && ($challenge->dateend > time()) &&
             ($challenge->nanswerscorrect < $quest->nmaxanswers)
    ) {
        throw new \moodle_exception('notpermissionanswer', 'quest');
    }

    $title = get_string('answername', 'quest', $answer);
    $subject = get_string('tothechallenge', 'quest');
    $url = (new moodle_url('challenges.php', ['id' => $cm->id, 'action' => 'showchallenge', 'cid' => $challenge->id]))->out();
    $subject .= "<a name=\"sid_$challenge->id\" href=\"$url\">$challenge->title</a>";

    if (($isteacher) || ($answer->userid == $USER->id)) {
        $title .= ' ' . get_string('by', 'quest') . ' ' . quest_fullname($answer->userid, $course->id);
    }

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title . ' ' . $subject);

    quest_print_answer_info($quest, $answer);

    echo $OUTPUT->heading(get_string('answercontent', 'quest'));
    quest_print_answer($quest, $answer);

    // The teacher's assessment comment is separate from question-bank feedback.
    if (!empty($answer->questionusageid) && ($isteacher || (int)$answer->userid === (int)$USER->id)) {
        $assessment = $DB->get_record('quest_assessments', [
            'answerid' => $answer->id,
            'questid' => $quest->id,
            'state' => ASSESSMENT_STATE_BY_TEACHER,
        ], 'id, commentsteacher');
        if (
            $assessment && !empty($assessment->commentsteacher) &&
                $assessment->commentsteacher !== ($answer->commentsteacher ?? '')
        ) {
            echo $OUTPUT->heading(get_string('commentsteacher', 'quest'));
            echo $OUTPUT->box(format_text($assessment->commentsteacher), 'center');
        }
    }

    $timenow = time();

    if (
        ($challenge->datestart < $timenow) && ($challenge->dateend > $timenow) &&
             ($challenge->nanswerscorrect < $quest->nmaxanswers)
    ) {
        $challenge->phase = SUBMISSION_PHASE_ACTIVE;
    }

    $string = '';
    if ($ismanager) {
        if ($assessment = $DB->get_record('quest_assessments', ['answerid' => $answer->id, 'questid' => $quest->id])) {
            $string = quest_print_actions_answers($cm, $answer, $challenge, $course, $assessment);
        }
    } else if (($challenge->userid == $USER->id)) {
        if ($assessment = $DB->get_record("quest_assessments", ['answerid' => $answer->id, 'questid' => $quest->id])) {
            $string = quest_print_actions_answers($cm, $answer, $challenge, $course, $assessment);
        }
    }
    echo html_writer::div($string, "text-center quest-answer-page-actions my-3");
    echo "<br><br>";

    // Log the event.
    \mod_quest\event\answer_viewed::create_from_parts($USER, $challenge, $answer, $cm)->trigger();

    if (isset($_SERVER['HTTP_REFERER'])) {
        echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER']);
    }

    echo $OUTPUT->footer();
} else if ($action == 'updatecomment') {
    require_sesskey();
    $aid = required_param('aid', PARAM_INT); // Answer ID..

    $answer = $DB->get_record("quest_answers", ["id" => $aid], '*', MUST_EXIST);
    quest_require_answer_ownership($answer, (int)$quest->id);
    $challenge = $DB->get_record("quest_submissions", ["id" => $answer->submissionid], '*', MUST_EXIST);
    if (!$ismanager && (int)$answer->userid !== (int)$USER->id) {
        throw new \moodle_exception('nopermissions', 'error');
    }
    $answer->commentforteacher = optional_param('teachercomment', null, PARAM_RAW);
    $DB->set_field("quest_answers", "commentforteacher", $answer->commentforteacher, ["id" => $answer->id]);
    $requestedsid = required_param('sid', PARAM_INT);
    if ((int)$requestedsid !== (int)$challenge->id) {
        throw new \moodle_exception('answer_not_found', 'quest');
    }
    $sid = $challenge->id;

    if (!empty($answer->commentforteacher)) {
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit();
        }

        foreach ($users as $user) {
            if ($ismanager) {
                quest_send_message(
                    $user,
                    "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=showanswer#Claims",
                    'evaluatecomment',
                    $quest,
                    $challenge,
                    $answer,
                    $USER
                );
            } else if ($user->id == $challenge->userid) {
                quest_send_message(
                    $user,
                    "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=showanswer#Claims",
                    'evaluatecomment',
                    $quest,
                    $challenge,
                    $answer,
                    null
                ); // Write in name of.
                                                                 // ...a teacher..
            }
        }
    }
    redirect("answer.php?sid=$sid&amp;action=showanswer&amp;aid=$aid");
} else if ($action == 'confirmdelete') {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    $aid = required_param('aid', PARAM_INT); // Answer ID..
    $id = required_param('id', PARAM_INT); // CourseModule ID..
    echo "<br><br>";
    $answer = $DB->get_record('quest_answers', ['id' => $aid], '*', MUST_EXIST);
    quest_require_answer_ownership($answer, (int)$quest->id);
    $sid = $answer->submissionid;
    if (!$ismanager && (int)$answer->userid !== (int)$USER->id) {
        throw new \moodle_exception('nopermissions', 'error');
    }
    quest_print_answer_info($quest, $answer);

    quest_print_answer($quest, $answer);
    echo '<br/>';
    echo $OUTPUT->confirm(
        get_string("confirmdeletionofthisitem", "quest", get_string("answername", "quest", $answer)),
        "answer.php?action=delete&amp;id=$id&amp;aid=$aid",
        "challenges.php?id=$id&amp;sid=$sid&amp;action=showchallenge"
    );
    echo $OUTPUT->footer();
} else if ($action == 'delete') { // Deletion..
    require_sesskey();
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);

    $aid = required_param('aid', PARAM_INT); // Answer ID..

    if (!$answer = $DB->get_record("quest_answers", ["id" => $aid])) {
        throw new \moodle_exception('answer_not_found', 'quest', $challengeurl, $aid);
    }
    quest_require_answer_ownership($answer, (int)$quest->id);
    $sid = $answer->submissionid;

    if (!$challenge = $DB->get_record("quest_submissions", ["id" => $sid])) {
        throw new \moodle_exception("cannotgetsubmissionrecord", 'quest');
    }
    $timenow = time();

    if (!($ismanager || (($USER->id == $answer->userid) && ($timenow < $quest->dateend) && ($timenow < $challenge->dateend)))) {
        throw new \moodle_exception("notauthorizedtodeleteanswer", 'quest');
    }

    // ...first get any assessments....
    if ($assessments = quest_get_assessments($answer, 'ALL')) {
        foreach ($assessments as $assessment) {
            $DB->delete_records("quest_elements_assessments", ["assessmentid" => $assessment->id]);
        }

        // Now delete the assessments....
        $DB->delete_records("quest_assessments", ["answerid" => $answer->id]);
    }
    $DB->delete_records("quest_answers", ["id" => $answer->id]);

    // ...now get rid of all files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_quest', 'answer', $answer->id);
    $fs->delete_area_files($context->id, 'mod_quest', 'answer_attachment', $answer->id);

    $challenge = quest_update_challenge_counts($challenge->id);
    // Update scores and statistics..
    // Update current User and team scores..
    // ...recalculate points and report to gradebook..
    quest_grade_updated($quest, $answer->userid);
    // Notify teachers.
    $users = quest_get_course_members($course->id, "u.lastname, u.firstname");
    foreach ($users as $user) {
        if (has_capability('mod/quest:manage', $context, $user->id)) {
            quest_send_message(
                $user,
                "challenges.php?id=$cm->id&amp;sid=$challenge->id&amp;action=showchallenge",
                'answerdelete',
                $quest,
                $challenge,
                $answer
            );
        }
    }
    if (!has_capability('mod/quest:manage', $context, $challenge->userid)) {
        $user = get_complete_user_data('id', $challenge->userid);
        if ($user) {
            quest_send_message(
                $user,
                "challenges.php?id=$cm->id&amp;sid=$challenge->id&amp;action=showchallenge",
                'answerdelete',
                $quest,
                $challenge,
                $answer
            );
        }
    }

    redirect(new moodle_url('/mod/quest/challenges.php', [
        'id' => $cm->id,
        'sid' => $challenge->id,
        'action' => 'showchallenge',
    ]), get_string('emailanswerdeletesubject', 'quest'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($action == 'modif') {
    $aid = required_param('aid', PARAM_INT); // Answer ID..
    $answer = $DB->get_record("quest_answers", ["id" => $aid], '*', MUST_EXIST);
    quest_require_answer_ownership($answer, (int)$quest->id);
    $answerautor = $answer->userid;
    $challenge = $DB->get_record("quest_submissions", ["id" => $answer->submissionid], '*', MUST_EXIST);
    if (
        !$ismanager && ((int)$answer->userid !== (int)$USER->id
            || time() >= (int)$quest->dateend || time() >= (int)$challenge->dateend)
    ) {
        throw new \moodle_exception('nopermissions', 'error');
    }
    $maxfiles = 99; // ......limit of image files for the html editor..
    $descriptionoptions = ['trusttext' => true, 'subdirs' => false, 'maxfiles' => $maxfiles, 'maxbytes' => $course->maxbytes,
                    'context' => $context]; // Evp limito para el editor por el tama?o del curso.
                                            // ...permitido, no tengo.
                                            // ...claro si es la mejor opci?n..
    $attachmentoptions = ['subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes];

    $answer = file_prepare_standard_editor(
        $answer,
        'description',
        $descriptionoptions,
        $context,
        'mod_quest',
        'answer',
        $answer->id
    );
    $answer = file_prepare_standard_filemanager(
        $answer,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_quest',
        'answer_attachment',
        $answer->id
    );
    $draftitemid = file_get_submitted_draft_itemid('answer_attachment');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_quest', 'answer_attachment', 0, ['subdirs' => 0]);
    $answer->attachment = $draftitemid;
    $mform = new \mod_quest\form\answer_form(
        null,
        ['current' => $answer, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions,
        'attachmentoptions' => $attachmentoptions,
        'action' => $action]
    );
    // ......the first parameter is $action, null will case the form action to be determined.
    // ...automatically)..

    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($answer = $mform->get_data()) {
        require_sesskey();
        $answer->userid = $answerautor;
        quest_uploadanswer($quest, $answer, $ismanager, $cm, $descriptionoptions, $attachmentoptions, $context);
        redirect(
            new moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id,
            'cid' => $challenge->id,
            'action' => 'showchallenge',
            ]),
            get_string('submittedanswer', 'quest') . " " . get_string('ok'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        $title = '"' . $challenge->title . '" ';
        echo $OUTPUT->heading(get_string("modifanswersubmission", "quest", $title));
        // Print information about the submission..
        echo $OUTPUT->box_start('block');
        echo $OUTPUT->heading($title);
        echo ("<center><b><a href=\"assessments.php?id=$cm->id&amp;sid=$challenge->id&amp;action=displaygradingform\">" .
                get_string("specimenassessmentform", "quest") . "</a></b></center>");
        quest_print_challenge($quest, $challenge);
        echo $OUTPUT->box_end();
        echo $OUTPUT->heading_with_help(get_string("answersubmission", "quest"), "answersubmission", "quest");
        $mform->display();
        echo $OUTPUT->footer();
    }
} else if ($action == "permitsubmit") {
    require_sesskey();
    $aid = required_param('aid', PARAM_INT); // Answer ID..
    $answer = $DB->get_record("quest_answers", ["id" => $aid], '*', MUST_EXIST);
    quest_require_answer_ownership($answer, (int)$quest->id);
    $challenge = $DB->get_record("quest_submissions", ["id" => $answer->submissionid], '*', MUST_EXIST);
    if (!$ismanager && (int)$challenge->userid !== (int)$USER->id) {
        throw new \moodle_exception('nopermissions', 'error');
    }
    $answer->permitsubmit = 1;
    $DB->set_field("quest_answers", "permitsubmit", $answer->permitsubmit, ["id" => $answer->id]);
    redirect("answer.php?sid=$challenge->id&amp;aid=$answer->id&amp;action=showanswer");
}
