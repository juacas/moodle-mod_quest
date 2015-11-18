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
// along with Questournament for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Questournament activity for Moodle
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * ACTIONS:
 * - answer
 * - showanswer
 * - updatecomment
 * - confirmdelete
 * - delete
 * - modif
 * - updateanswer
 * - removeattachments
 * - preview
 * - permitsubmit
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("scores_lib.php");

global $DB, $OUTPUT;

$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_LOCALURL);

// Allows the script to use only AnswerId.
$aid = optional_param('aid', - 1, PARAM_INT); // Answer ID.
if ($aid != - 1) {
    $answer = $DB->get_record('quest_answers', array(
        'id' => $aid
    ));
}
if (!empty($answer)) {
    $sid = $answer->submissionid;
} else {
    $sid = required_param('sid', PARAM_INT); // Submission ID.
}

if (!$submission = $DB->get_record('quest_submissions', array(
    'id' => $sid
        ))) {
    print_error("Incorrect submission id");
}
if (!$quest = $DB->get_record("quest", array(
    "id" => $submission->questid
        ))) {
    print_print_error("incorrectQuest",'quest');;
}
if (!$course = $DB->get_record("course", array(
    "id" => $quest->course
        ))) {
    print_print_error("course_misconfigured",'quest');
}
if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
    print_error("No coursemodule found");
}

if (!$redirect && isset($_SERVER["HTTP_REFERER"])) {
    $redirect = urlencode($_SERVER["HTTP_REFERER"] . '#sid=' . $submission->id);
}

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$action = required_param('action', PARAM_ALPHA);

$url = new moodle_url('/mod/quest/answer.php',
        array(
    'sid' => $sid,
    'action' => $action,
    'allowcomments' => $allowcomments,
    'redirect' => $redirect,
    'aid' => $aid
        ));
$PAGE->set_url($url);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");

$stranswer = ($action) ? get_string($action, 'quest') : get_string("answer", "quest");

$submissionurl = "submissions.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=showsubmission";

// Now check whether we need to display a frameset.
if ($action == "answer") {

    $answer = new stdClass();
    $answer->id = null;
    $answer->submissionid = $sid;

    $maxfiles = 99; // Limit used for the html editor.

    $definitionoptions = array(
        'trusttext' => true,
        'subdirs' => false,
        'maxfiles' => $maxfiles,
        'maxbytes' => $course->maxbytes,
        'context' => $context
    );
    $attachmentoptions = array(
        'subdirs' => false,
        'maxfiles' => $quest->nattachments,
        'maxbytes' => $quest->maxbytes
    );

    $answer = file_prepare_standard_editor($answer, 'description', $definitionoptions, $context, 'mod_quest', 'answer',
            $answer->id);
    $answer = file_prepare_standard_filemanager($answer, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'answer_attachment', $answer->id);

    $mform = new quest_print_answer_form(null,
            array(
        'current' => $answer,
        'quest' => $quest,
        'cm' => $cm,
        'definitionoptions' => $definitionoptions,
        'attachmentoptions' => $attachmentoptions,
        'action' => $action
    ));
    // The first parameter is $action, null will case the form action to be determined automatically).

    if ($mform->is_cancelled()) {

        redirect("view.php?id=$cm->id");
    } else if ($answer = $mform->get_data()) {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        quest_uploadanswer($quest, $answer, $ismanager, $cm, $definitionoptions, $attachmentoptions, $context);
        echo $OUTPUT->heading(get_string('submittedanswer', 'quest') . " " . get_string('ok'));
        echo $OUTPUT->continue_button($submissionurl);
        echo $OUTPUT->footer();
    } else {
        $title = '"' . $submission->title . '" ';
        if ($ismanager || has_capability('mod/quest:viewotherattemptsowners', $context)) {
            $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
        }
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading($title);
        // Disabled by now: quest_print_submission_info($quest,$submission); to show additional information about the submission.
        echo ("<center><b><a href=\"assessments.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=displaygradingform\">"
        . get_string("specimenassessmentform", "quest") . "</a>"
        . $OUTPUT->help_icon('specimensubmission', 'quest') . "</b></center>");

        quest_print_submission($quest, $submission);
        echo $OUTPUT->heading_with_help(get_string("answersubmission", "quest"), "answersubmission", "quest");

        $mform->display();
        echo $OUTPUT->footer();
    }
} else if ($action == "showanswer") {
    if (($quest->usepassword) && (!$ismanager)) {
        quest_require_password($quest, $course, $_POST['userpassword']);
    }
    $aid = required_param('aid', PARAM_INT); // Answer ID.
    $answer = $DB->get_record("quest_answers", array(
        "id" => $aid
    ));
    if (!$answer) {
        error("Answer not found!");
    }
    $submission = $DB->get_record("quest_submissions", array(
        "id" => $answer->submissionid
    ));

    if ((!$ismanager) && ($submission->userid != $USER->id)
            && ($answer->userid != $USER->id)
            && ($submission->dateend > time())
            && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        error(get_string('notpermissionanswer', 'quest'));
    }

    $title = get_string('answername', 'quest', $answer);

    $subject = get_string('subject', 'quest');
    $subject .= "<a name=\"sid_$submission->id\" href=\"submissions.php?cmid=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\">$submission->title</a>";

    if (($ismanager) || ($answer->userid == $USER->id)) {
        $title .= get_string('by', 'quest') . ' ' . quest_fullname($answer->userid, $course->id);
    }

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo $OUTPUT->heading($subject);

    quest_print_answer_info($quest, $answer);

    echo $OUTPUT->heading(get_string('answercontent', 'quest'));
    quest_print_answer($quest, $answer);

    $timenow = time();

    if (($submission->datestart < $timenow)
            && ($submission->dateend > $timenow)
            && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE;
    }

    $string = '';
    if ($ismanager) {
        if ($assessment = $DB->get_record('quest_assessments',
                array(
            'answerid' => $answer->id,
            'questid' => $quest->id
                ))) {
            $string = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
        }
    } else if (($submission->userid == $USER->id)) {
        if ($assessment = $DB->get_record("quest_assessments",
                array(
            'answerid' => $answer->id,
            'questid' => $quest->id
                ))) {
            $string = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
        }
    }
    echo "<center><b>" . $string . "</b></center>";
    echo "<br><br>";

    // .. log the event
    if ($CFG->version >= 2014051200) {
            require_once('classes/event/answer_viewed.php');
            $view_event = mod_quest\event\answer_viewed::create_from_parts($USER, $submission, $answer, $cm);
            $view_event->trigger();
        } else {
           add_to_log($course->id, "quest", "read_answer", "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", "$answer->id", "$cm->id");
        }




    if (isset($_SERVER['HTTP_REFERER'])) {
        echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER']);
    }

    echo $OUTPUT->footer();
} else if ($action == 'updatecomment') {

    $aid = required_param('aid', PARAM_INT); // Answer ID.

    $answer = $DB->get_record("quest_answers", ["id"=>$aid]);
    $submission = $DB->get_record("quest_submissions", ["id"=>$answer->submissionid]);
    $answer->commentforteacher = $_POST['teachercomment'];
    $DB->set_field("quest_answers", "commentforteacher", $answer->commentforteacher, ["id"=>$answer->id]);
    $sid = $_POST['sid'];

    if (!empty($answer->commentforteacher)) {
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit();
        }

        foreach ($users as $user) {
            if ($ismanager) {
                quest_send_message($user, "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=showanswer#Claims",
                        'evaluatecomment', $quest, $submission, $answer, $USER);
            } else if ($user->id == $submission->userid) {
                quest_send_message($user, "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=showanswer#Claims",
                        'evaluatecomment', $quest, $submission, $answer, null); // Write in name of a teacher.
            }
        }
    }
    redirect("answer.php?sid=$sid&amp;action=showanswer&amp;aid=$aid");
} else if ($action == 'confirmdelete') {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    $aid = required_param('aid', PARAM_INT); // Answer ID.
    $id = required_param('id', PARAM_INT); // CourseModule ID.
    echo "<br><br>";
    $answer = $DB->get_record('quest_answers', ['id' => $aid], '*', MUST_EXIST);
    $sid = $answer->submissionid;
    quest_print_answer_info($quest, $answer);

    quest_print_answer($quest, $answer);
    echo '<br/>';
    echo $OUTPUT->confirm(get_string("confirmdeletionofthisitem", "quest", get_string("answername", "quest", $answer)),
            "answer.php?action=delete&amp;id=$id&amp;aid=$aid", "submissions.php?cmid=$id&amp;sid=$sid&amp;action=showsubmission");
    echo $OUTPUT->footer();
} else if ($action == 'delete') { // Deletion.
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);

    $aid = required_param('aid', PARAM_INT); // Answer ID.

    if (!$answer = $DB->get_record("quest_answers", array("id" => $aid))) {
        print_error('answer_not_found', 'quest', $submissionurl, $aid);
    }
    $sid = $answer->submissionid;

    if (!$submission = $DB->get_record("quest_submissions", array(
        "id" => $sid
            ))) {
        print_error("cannotgetsubmissionrecord",'quest');
    }
    $timenow = time();

    if (!($ismanager or ( ($USER->id == $answer->userid)
            and ( $timenow < $quest->dateend)
            and ( $timenow < $submission->dateend)))) {
        print_error("notauthorizedtodeleteanswer",'quest');
    }

    // if($answer->phase == ANSWER_PHASE_PASSED)
    // {
    // $submission->nanswerscorrect--;
    // $DB->set_field("quest_submissions","nanswerscorrect", $submission->nanswerscorrect, array("id"=> $submission->id));
    // }
    // first get any assessments...
    if ($assessments = quest_get_assessments($answer, 'ALL')) {
        foreach ($assessments as $assessment) {
            $DB->delete_records("quest_elements_assessments",
                    array("assessmentid" => $assessment->id));
            echo ".";
        }

        // Now delete the assessments...
        $DB->delete_records("quest_assessments", array("answerid" => $answer->id));
    }
    $DB->delete_records("quest_answers", array("id" => $answer->id));

    // ...and finally the submitted file
    // TODO: elever Eliminar esta función y sustituirla por el mecanismo nuevo de Moodle 2.
    quest_delete_submitted_files_answers($quest, $answer);
    // Update scores and statistics.

    $submission = quest_update_submission_counts($submission->id);
    // Update current User and team scores.
    // recalculate points and report to gradebook.

    quest_grade_updated($quest, $answer->userid);

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }
    foreach ($users as $user) {
        if (!$ismanager) {
            continue;
        }

        quest_send_message($user, "submissions.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=showsubmission",'answerdelete', $quest, $submission, $answer);
    }
    if (!has_capability('mod/quest:manage', $context, $submission->userid)) {
        $user = get_complete_user_data('id', $submission->userid);
        if ($user){
        quest_send_message($user, "submissions.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=showsubmission",
                'answerdelete', $quest, $submission, $answer);
        }
    }

    echo $OUTPUT->redirect_message($submissionurl, get_string('emailanswerdeletesubject', 'quest'), 3, false);
    echo $OUTPUT->header();
    print_string("deleting", "quest");
    echo $OUTPUT->footer;
} else if ($action == 'modif') {

    $aid = required_param('aid', PARAM_INT); // Answer ID.
    if (!$answer = $DB->get_record("quest_answers", array(
        "id" => $aid
            ))) {
        print_error('Edit answer:  invalid answer identification.');
    }

    $submission = $DB->get_record("quest_submissions", array(
        "id" => $answer->submissionid
    ));

    $maxfiles = 99; // ...limit of image files for the html editor.

    $definitionoptions = array(
        'trusttext' => true,
        'subdirs' => false,
        'maxfiles' => $maxfiles,
        'maxbytes' => $course->maxbytes,
        'context' => $context
    ); // Evp limito para el editor por el tama?o del curso permitido, no tengo claro si es la mejor opci?n.
    $attachmentoptions = array(
        'subdirs' => false,
        'maxfiles' => $quest->nattachments,
        'maxbytes' => $quest->maxbytes
    );

    $answer = file_prepare_standard_editor($answer, 'description', $definitionoptions, $context, 'mod_quest', 'answer',
            $answer->id);
    $answer = file_prepare_standard_filemanager($answer, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'answer_attachment', $answer->id);

    $mform = new quest_print_answer_form(null,
            array(
        'current' => $answer,
        'quest' => $quest,
        'cm' => $cm,
        'definitionoptions' => $definitionoptions,
        'attachmentoptions' => $attachmentoptions,
        'action' => $action
    ));
    // ...the first parameter is $action, null will case the form action to be determined automatically).

    if ($mform->is_cancelled()) {

        redirect("view.php?id=$cm->id");
    } else if ($answer = $mform->get_data()) {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('submittedanswer', 'quest') . " " . get_string('ok'));
        quest_uploadanswer($quest, $answer, $ismanager, $cm, $definitionoptions, $attachmentoptions, $context);
        echo $OUTPUT->continue_button("submissions.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=showsubmission");
        echo $OUTPUT->footer();
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string("modifanswersubmission", "quest", ":"));

        // Print information about the submission.
        $title = '"' . $submission->title . '" ';
        echo $OUTPUT->heading($title);

        echo ("<center><b><a href=\"assessments.php?cmid=$cm->id&amp;sid=$submission->id&amp;action=displaygradingform\">" . get_string("specimenassessmentform",
                "quest") . "</a></b></center>");

        quest_print_submission($quest, $submission);

        echo $OUTPUT->heading_with_help(get_string("answersubmission", "quest"), "answersubmission", "quest");

        $mform->display();

        echo $OUTPUT->footer();
    }
} else if ($action == 'updateanswer') { // Evp esta acción es la que actualiza la respuesta y esto se sustituye en la función quest_uploadanswer.
    print_header_simple(format_string($quest->name), "",
            "<a href=\"index.php?id=$course->id\">$strquests</a> ->
                      <a href=\"view.php?id=$cm->id\">" . format_string($quest->name, true) . "</a> -> $stranswer", "",
            '<base target="_self" />', true);

    $form = data_submitted();

    $aid = required_param('aid', PARAM_INT); // Answer ID.
    $answer = $DB->get_record("quest_answers", array(
        "id" => $aid
    ));

    $submission = $DB->get_record("quest_submissions", array(
        "id" => $answer->submissionid
    ));

    if (!($ismanager or ( ($USER->id == $answer->userid) and ( $timenow < $quest->dateend)))) {
        error("You are not authorized to update your answer");
    }

    // Check existence of title.
    if (empty($form->title)) {
        $form->title = get_string("notitle", "quest");
    }

    $answer->date = time();
    $points = quest_get_points($submission, $quest, $answer);
    $answer->pointsmax = $points;
    $answer->title = $form->title;
    $answer->description = trim($form->description);
    $answer->date = $answer->date;
    $answer->perceiveddifficulty = $form->perceiveddifficulty;

    if (($answer->phase == 1) || ($answer->phase == 2)) {
        $answer->state = 2;
    }
    $DB->update_record("quest_answers", $answer);
    // TODO: Check if merge this code with uploadanswer.php.

    if ($quest->nattachments) {
        require_once($CFG->dirroot . '/lib/uploadlib.php');
        $um = new upload_manager(null, false, false, $course, false, $quest->maxbytes);
        $dir = quest_file_area_name_answers($quest, $answer);

        if ($um->process_file_uploads($dir)) {
            add_to_log($course->id, "quest", "newattachment",
                    "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", "$answer->id", "$cm->id");
            print_heading(get_string("uploadsuccess", "quest"));
            // ...will take care of printing errors.
        } else {
            print_heading(get_string('upload'));
            notify(get_string('uploaderror', 'quest'));
            echo $um->get_errors();

            $errorreturnurl = "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=modif";

            echo $OUTPUT->continue_button($errorreturnurl);
            print_footer($course);
            die();
        }
    } else {
        print_heading(get_string("submittedanswer", "quest") . " " . get_string("ok"));
    }

    // Update scores and statistics.
    $submission = quest_update_submission_counts($submission->id);
    // Update current User and team scores.
    // recalculate points and report to gradebook.

    quest_grade_updated($quest, $answer->userid);

    // NOTIFICATIONS.

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        print_heading(get_string("nostudentsyet"));
        print_footer($course);
        exit();
    }
    /*
     * JPC 2013-11-28 disable excesive notifications
     * foreach($users as $user){ if($ismanager) {
     * quest_send_message($user, "answer.php?sid=$answer->submissionid&amp;aid=$answer->id&amp;action=showanswer", 'answeradd',
     * $quest, $submission, $answer, $USER); } }
     */
    // if(!isteacher($course->id,$submission->userid))
    // {
    $user = get_complete_user_data('id', $submission->userid);
    if ($user){
        quest_send_message($user, "answer.php?sid=$answer->submissionid&amp;aid=$answer->id&amp;action=showanswer", 'answeradd',$quest, $submission, $answer);
    }
    // }

//    add_to_log($course->id, "quest", "modif_answer", "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=showanswer",
//            "$answer->id", "$cm->id");

    print_heading(get_string("submittedanswer", "quest") . " " . get_string("ok"));

    echo $OUTPUT->continue_button("submissions.php?cmid=$cm->id&amp;sid=$sid&amp;action=showsubmission");
} else if ($action == 'removeattachments') {

    print_header_simple(format_string($quest->name), "",
            "<a href=\"index.php?id=$course->id\">$strquests</a> ->
                      <a href=\"view.php?id=$cm->id\">" . format_string($quest->name, true) . "</a> -> $stranswer", "",
            '<base target="_parent" />', true);

    $form = data_submitted();

    $aid = required_param('aid', PARAM_INT); // Answer ID.
    $answer = $DB->get_record("quest_answers", "id", $aid);

    if (!($ismanager or ( ($USER->id == $answer->userid)))) {
        error("You are not authorized to delete these attachments");
    }

    // Check existence of title.
    if (empty($form->title)) {
        notify(get_string("notitlegiven", "quest"));
    } else {
        $DB->set_field("quest_answers", "title", $form->title, array(
            "id" => $answer->id
        ));
        $DB->set_field("quest_answers", "description", trim($form->description), array(
            "id" => $answer->id
        ));
    }
    print_string("removeallattachments", "quest");
    quest_delete_submitted_files_answers($quest, $answer);
    add_to_log($course->id, "quest", "removeattachments", "answer.php?sid=$sid&amp;aid=$answer->id&amp;action=showanswer",
            "$answer->id", "$cm->id");

    echo $OUTPUT->continue_button("answer.php?id=$cm->id&amp;aid=$answer->id&amp;sid=$sid&amp;action=$form->beforeaction");
} else if ($action == "preview") {

    print_header_simple(format_string($quest->name), "",
            "<a href=\"index.php?id=$course->id\">$strquests</a> ->
                      <a href=\"view.php?id=$cm->id\">" . format_string($quest->name, true) . "</a> -> $stranswer", "",
            '<base target="_parent" />', true);

    $form = data_submitted();

    echo "<hr size=\"1\" noshade=\"noshade\" />";

    print_heading_with_help(get_string('windowpreview', 'quest'), "windowpreview", "quest");

    $title = $form->title;
    echo "<center><b>" . get_string('title', 'quest') . ": " . $title . "</b></center><br>";
    echo "<center><b>" . get_string('description', 'quest') . "</b></center>";
    // Print upload form.
    $answer->title = $form->title;
    $temp = '\\';
    $temp1 = $temp . $temp;
    $answer->description = str_replace($temp1, $temp, $form->description);

    print_simple_box(format_text($answer->description), 'center');

    close_window_button();

    print_footer($course);
    exit();
} else if ($action == "permitsubmit") {
    $aid = required_param('aid', PARAM_INT); // Answer ID.
    $answer = $DB->get_record("quest_answers", array(
        "id" => $aid
    ));
    $submission = $DB->get_record("quest_submissions", array(
        "id" => $answer->submissionid
    ));
    $answer->permitsubmit = 1;
    $DB->set_field("quest_answers", "permitsubmit", $answer->permitsubmit, array(
        "id" => $answer->id
    ));

    redirect("answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer");
}