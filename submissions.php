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

/** Questournament activity for Moodle
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 *
 *          ACTIONS handled are:
 *          - submitchallenge
 *          - confirmdelete
 *          - delete
 *          - modif
 *          - showsubmission
 *          - approve
 *          - showsubmissionsuser
 *          - showanswersuser
 *          - team
 *          - showanswersteam
 *          - recalificationall
 *          - confirmchangeform */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("scores_lib.php");

$id = required_param('id', PARAM_INT); // Quest coursemoduleID.
global $DB, $OUTPUT, $PAGE, $sort, $dir;

$timenow = time();
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);


require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$candeletechallenge = has_capability('mod/quest:deletechallengeall', $context);
$canpreview = has_capability('mod/quest:preview', $context);
$caneditchallenges = has_capability('mod/quest:editchallengeall', $context);
$canapprove = has_capability('mod/quest:approvechallenge', $context);

$action = optional_param('action', 'listallsubmissions', PARAM_ALPHA);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");

$sid = optional_param('sid', null, PARAM_INT);

$sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$url = new moodle_url('/mod/quest/submissions.php',
        array('id' => $id, 'sid' => $sid, 'action' => $action, 'sort' => $sort, 'dir' => $dir));
$PAGE->set_url($url);

if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, required_param('userpassword', PARAM_RAW_TRIMMED));
}
// Confirm delete.
if ($action == 'confirmdelete') {
    $sid = required_param('sid', PARAM_INT); // ...submission id.
    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add(\format_string($submission->title));
    echo $OUTPUT->header();
    echo "<br>";
    echo $OUTPUT->confirm(get_string("confirmdeletionofthisitem", "quest", $submission->title),
            "submissions.php?action=delete&amp;id=$cm->id&amp;sid=$sid", "view.php?id=$cm->id#sid=$sid");
} else if ($action == 'delete') {
    $sid = required_param('sid', PARAM_INT); // ...submission id.
    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add(\format_string($submission->title));

    echo $OUTPUT->header();
    // ...check if the user has enough capability to delete the submission and only up to the
    // deadline.
    if (!((has_capability('mod/quest:deletechallengeall', $context) or
            (has_capability('mod/quest:deletechallengemine', $context) and
             ($USER->id == $submission->userid)) and ($timenow < $quest->dateend) and ($submission->nanswers == 0) and
             ($timenow < $submission->dateend)))) {
        print_error("notauthorizedtodeletesubmission", 'quest');
    }

    if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id))) {
        foreach ($answers as $answer) {
            // ...first get any assessments...
            if ($assessments = quest_get_assessments($answer, 'ALL')) {
                foreach ($assessments as $assessment) {
                    // ...and all the associated records...
                    $DB->delete_records("quest_elements_assessments",
                            array("assessmentid" => $assessment->id, "questid" => $quest->id));
                    echo ".";
                }
                // ...now delete the assessments...
                $DB->delete_records("quest_assessments", array("answerid" => $answer->id, "questid" => $quest->id));
            }
            $DB->delete_records("quest_answers", array("id" => $answer->id));

            // ...now get rid of all answer files.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_quest', 'answer', $answer->id);
            $fs->delete_area_files($context->id, 'mod_quest', 'answer_attachment', $answer->id);
        }
    }
    if ($assessmentautor = $DB->get_record("quest_assessments_autors",
            array("submissionid" => $submission->id, "questid" => $quest->id))) {
        $DB->delete_records("quest_items_assesments_autor",
                array("assessmentautorid" => $assessmentautor->id, "questid" => $quest->id));
        $DB->delete_records("quest_assessments_autors", array("id" => $assessmentautor->id));
    }
    // Recalculate points and report to gradebook...
    quest_grade_updated($quest, $submission->userid);
    $DB->delete_records_select('event', 'modulename = ? AND instance = ? and ' . $DB->sql_compare_text('description') . ' = ?',
            array('modulename' => 'quest', 'instance' => $quest->id, 'description' => $submission->description));
    // ...and the submission record...
    $DB->delete_records("quest_submissions", array("id" => $submission->id));
    // ...and finally the submitted files
    // now get rid of all files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_quest', 'submission', $submission->id);
    $fs->delete_area_files($context->id, 'mod_quest', 'attachment', $submission->id);

    if ($candeletechallenge) {
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit();
        }
        if ($submissiongroup = $DB->get_record("groups_members", array("userid" => $submission->userid))) {
            $currentgroup = $submissiongroup->groupid;
        }
        // JPC 2013-11-28 disable excesive notifications.
        if (false) {
            foreach ($users as $user) {
                if (!$ismanager) {
                    if (isset($currentgroup)) {
                        if (!groups_is_member($currentgroup, $user->id)) {
                            continue;
                        }
                    }
                }
                quest_send_message($user, "view.php?id=$cm->id", 'deletesubmission', $quest, $submission, '');
            }
        }
        // JPC block disabled.
    } else {
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit();
        }
        // JPC: Disable excessive notifications.
        if (false) {
            foreach ($users as $user) {
                if ($ismanager) {
                    quest_send_message($user, "view.php?id=$cm->id", 'deletesubmission', $quest, $submission, '');
                }
            }
        }
        // JPC Block disabled.
    }
    // Log the action.
    if ($CFG->version >= 2014051200) {
        require_once('classes/event/challenge_deleted.php');
        $viewevent = mod_quest\event\challenge_deleted::create_from_parts($USER, $submission, $cm);
        $viewevent->trigger();
    } else {
        add_to_log($course->id, "quest", "delete_submission", "view.php?id=$cm->id", "$submission->id", "$cm->id");
    }
    echo "<center>" . get_string("deletechallenge", "quest") . "</center>";
    echo $OUTPUT->continue_button("view.php?id=$cm->id");
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
    $newsubmission = new stdClass();
    $newsubmission->id = null;

    $descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes,
                    'context' => $context);
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes,
                    'context' => $context);

    $newsubmission = file_prepare_standard_editor($newsubmission, 'description', $descriptionoptions, $context, 'mod_quest',
            'submission', $newsubmission->id);
    $newsubmission = file_prepare_standard_filemanager($newsubmission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $newsubmission->id);

    $mform = new quest_print_upload_form(null,
            array('submission' => $newsubmission, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions,
                            'attachmentoptions' => $attachmentoptions, 'action' => $action));
    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($newsubmission = $mform->get_data()) {
        $authorid = $USER->id;
        quest_upload_challenge($quest, $newsubmission, $canaddchallenge, $cm, $descriptionoptions, $attachmentoptions, $context,
                $action, $authorid);
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string("submitchallengeassignment", "quest") . ":",
                "submitchallengeassignment", "quest");
        $mform->display();
    }
} else if ($action == 'modif') {
    $sid = required_param('sid', PARAM_INT); // ...submission id.
    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);
    $titlesubmission = $submission->title;
    $PAGE->navbar->add(\format_string($submission->title));

    if (($submission->userid != $USER->id) && (!$caneditchallenges)) {
        print_error('nopermissions', 'error', null, "Edit submission: Only teachers and autors can look this page");
    }

    $descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes,
                    'context' => $context);
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes);

    $submission = file_prepare_standard_editor($submission, 'description', $descriptionoptions, $context, 'mod_quest', 'submission',
            $submission->id);
    $submission = file_prepare_standard_filemanager($submission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $submission->id);
    $draftitemid = file_get_submitted_draft_itemid('introattachments');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_quest', 'attachment', 0, array('subdirs' => 0));
    $submission->attachment = $draftitemid;
    $mform = new quest_print_upload_form(null,
            array('submission' => $submission, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions,
                            'attachmentoptions' => $attachmentoptions, 'action' => $action));
    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($modifsubmission = $mform->get_data()) {
        $authorid = $submission->userid;
        quest_upload_challenge($quest, $modifsubmission, $caneditchallenges, $cm, $descriptionoptions,
                $attachmentoptions, $context, $action, $authorid);
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string("modifsubmission", "quest", $titlesubmission), "modifsubmission", "quest");
        $mform->display();
    }
} else if ($action == 'showsubmission') {
    $sid = required_param('sid', PARAM_INT); // ...submission id.
    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);
    if ((!($canpreview)) && ($submission->userid != $USER->id && ($submission->datestart > time() || $submission->state == 1))) {
        print_error('notpermissionsubmission', 'quest');
    }

    if (($submission->datestart < time()) && ($submission->dateend > time()) &&
            ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE; // ...active.
    } else {
        $submission->phase = SUBMISSION_PHASE_CLOSED; // ...closed.
    }
    if (($quest->permitviewautors == 1) && ($submission->phase == SUBMISSION_PHASE_CLOSED) &&
             ($submission->state == SUBMISSION_STATE_APROVED) && ($submission->datestart < time()) ||
             has_capability('mod/quest:viewotherattemptsowners', $context)) {
        $permitviewautors = 1;
    } else {
        $permitviewautors = 0;
    }

    $title = '"' . $submission->title . '"';
    // Convenient editing button for teachers.
    if (has_capability('mod/quest:editchallengeall', $context)) {
        $title .= "<a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                 $OUTPUT->pix_icon('/t/edit', get_string('modif', 'quest')) . '</a> ';
    }
    if (($canpreview) || ($submission->userid == $USER->id) || ($permitviewautors == 1)) {
        $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
    }

    $PAGE->set_title(format_string($quest->name . ' ' . $submission->title));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add(\format_string($submission->title));

    echo $OUTPUT->header();
    /*
     * Flag to force a recalculation of team statistics and scores.
     * Only to solve errors in calculations.
     */
    $debugrecalculate = optional_param('recalculate', 'no', PARAM_ALPHA);
    /*
     * Flag to force a recalculation of team statistics and scores.
     * Only to solve errors in calcularions.
     */
    $recalculatelink = '';
    if ($debugrecalculate === 'yes') {
        require_once("scores_lib.php");
        print("<p>Fixing submission stats...</p>");
        $submission = quest_update_submission_counts($submission->id);
    } else if ($ismanager) {
        // Link to recalculate challenge stats.
        $recalculatelink = '/ <a href="' . $CFG->wwwroot .
                 "/mod/quest/submissions.php?id=$cm->id&action=showsubmission&sid=$submission->id&recalculate=yes" .
                 '">Recalc.</a>';
    }
    echo $OUTPUT->heading($title);
    echo ("<center><table width=100% ><tr><td>");
    quest_print_submission_info($quest, $submission);
    echo ("</td><td>");
    // INCRUSTA GRÁFICO DE EVOLUCION DE PUNTOS.
    quest_print_score_graph($quest, $submission);
    echo "</td></tr></table></center>";
    
    $text = "<center><b>";
    $text .= "<a href=\"assessments.php?" .
    "id=$cm->id&amp;sid=$submission->id&amp;viewgeneral=0&amp;action=displaygradingform&amp;sesskey=" .
    sesskey() . "\">" . get_string("specimenassessmentformanswer", "quest") . "</a>";
    $text .= $OUTPUT->help_icon('specimenanswer', 'quest');
    // Actions links.
    if ((($ismanager || $USER->id == $submission->userid) and $quest->nelementsautor) && ($submission->numelements == 0)) {
        $text .= "&nbsp;<a href=\"submissions.php?id=$cm->id&newform=1&sid=$sid&cambio=0&amp;action=confirmchangeform\">" .
        $OUTPUT->pix_icon('/t/edit', get_string('amendassessmentelements', 'quest')) . '</a>';
    } else if ((($ismanager || $USER->id == $submission->userid) and $quest->nelementsautor) && ($submission->numelements != 0)) {
        $assessmentsurl = new moodle_url('/mod/quest/assessments.php',
        array('id' => $cm->id, 'sid' => $sid, 'newform' => 1, 'change_form' => 0, 'action' => 'editelements',
        'sesskey' => sesskey()));
        $text .= "&nbsp;<a href=\"$assessmentsurl\">" .
        $OUTPUT->pix_icon('/t/edit', get_string('amendassessmentelements', 'quest')) .
        '</a>';
    }
    $text .= "</b></center>";
    echo ($text);
    
    echo $OUTPUT->heading(get_string('description', 'quest'));
    /*
    * *
    * Wording of the challenge
    * *
    */
    quest_print_submission($quest, $submission);
    
    $changegroup = optional_param('group', -1, PARAM_INT);// Group change requested?
    $groupmode = groups_get_activity_group($cm); // Groups are being used?
    $currentgroup = groups_get_course_group($COURSE);
    
    if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
    ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE;
    }
    
   
    if (!has_capability('mod/quest:manage', $context, $submission->userid) && ($groupmode == 2)) {
        
        if ($currentgroup && !groups_is_member($currentgroup, $submission->userid) && !($submission->dateend < time())) {
            echo get_string('cantRespond_WARN_notingroup_or_challengeended', 'quest');
        } else {
            $actionlinks = quest_actions_submission($course, $submission, $quest, $cm, array('recalification' => false));
        }
    } else {
        $actionlinks = quest_actions_submission($course, $submission, $quest, $cm, array('recalification' => false));
    }
    echo $actionlinks . $recalculatelink;
    echo "<br>";

    $sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
    $dir = optional_param('dir', 'ASC', PARAM_ALPHA);
    quest_print_table_answers($quest, $submission, $course, $cm, $sort, $dir);
    
    if ($repeatactionsbelow) {
        echo $actionlinks . $recalculatelink;
    }
    if ($CFG->version >= 2014051200) {
        require_once('classes/event/challenge_viewed.php');
        $viewevent = mod_quest\event\challenge_viewed::create_from_parts($USER, $submission, $cm);
        $viewevent->trigger();
    } else {
        add_to_log($course->id, "quest", "read_submission",
        "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", "$submission->id", "$cm->id");
    }
    echo $OUTPUT->continue_button("view.php?id=$cm->id");
} else if ($action == 'approve') {
    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);
    $authorid = $submission->userid;
    $PAGE->navbar->add(\format_string($submission->title));
    if (!$canapprove) {
        print_error('nopermissions', 'error', null, "Approve challenge: Not enought permissions to take this action");
    }

    $descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes,
                    'context' => $context);
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes);

    $submission = file_prepare_standard_editor($submission, 'description', $descriptionoptions, $context, 'mod_quest', 'submission',
            $submission->id);
    $submission = file_prepare_standard_filemanager($submission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $submission->id);

    $mform = new quest_print_upload_form(null,
            array('submission' => $submission, 'quest' => $quest,
                            'cm' => $cm, 'definitionoptions' => $descriptionoptions,
                            'attachmentoptions' => $attachmentoptions, 'action' => $action));

    if ($mform->is_cancelled()) {
        redirect("submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$sid");
    } else if ($submission = $mform->get_data()) {

        if (isset($submission->submitbuttonapprove)) {
            quest_upload_challenge($quest, $submission, $canapprove, $cm, $descriptionoptions,
                                    $attachmentoptions, $context, 'approve', $authorid);
        } else { // ...save but not approve.
            $action = 'modif';
            quest_upload_challenge($quest, $submission, $canapprove, $cm, $descriptionoptions,
                    $attachmentoptions, $context, 'modif', $authorid);
        }
    } else {

        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        echo $OUTPUT->heading_with_help(get_string("approvesubmission", "quest"), "approvesubmission", "quest");

        $mform->display();
    }
} else if ($action == 'showsubmissionsuser') {
    if (!$canpreview) {
        print_error('nopermissions', 'error', null, "Only teachers can look at this page");
    }

    $userid = required_param('uid', PARAM_INT);

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    // Now prepare table with student assessments and submissions.
    $tablesort = new stdclass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $indice = 0;
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('nostudentsyet'));
        echo $OUTPUT->footer();
        exit();
    }

    foreach ($users as $user) {
        if ($user->id == $userid) {
            $usertemp = $user;
        }
    }
    $user = $usertemp;

    $title = get_string('showsubmissions', 'quest');
    if ($canpreview) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . quest_fullname($user->id, $course->id);
    }
    echo $OUTPUT->heading($title);

    // Skip if student not in group.
    if ($submissions = quest_get_user_submissions($quest, $user)) {
        foreach ($submissions as $submission) {
            $data = array();
            $sortdata = array();

            if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
                     ($submission->nanswerscorrect < $quest->nmaxanswers)) {
                $submission->phase = SUBMISSION_PHASE_ACTIVE;
            }
            $data[] = quest_print_submission_title($quest, $submission) .
                     " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                     $OUTPUT->pix_icon('/t/edit', get_string('modif', 'quest')) . '</a>' .
                     " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                     $OUTPUT->pix_icon('/t/delete', get_string('delete', 'quest')) . '</a>';
            $sortdata['title'] = strtolower($submission->title);

            $data[] = quest_submission_phase($submission, $quest, $course);
            $sortdata['phase'] = quest_submission_phase($submission, $quest, $course);

            $nanswersassess = 0;
            if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?",
                    array($quest->id, $submission->id))) {
                foreach ($answers as $answer) {
                    if (($answer->phase == 1) || ($answer->phase == 2)) {
                        $nanswersassess++;
                    }
                }
            }
            $nanswerswhithoutassess = $submission->nanswers - $nanswersassess;
            $image = '';
            if ($answer = $DB->get_record("quest_answers",
                    array('questid' => $quest->id, "submissionid" => $submission->id, "userid" => $USER->id))) {
                $image = $OUTPUT->pix_icon('/t/clear', 'OK');
            }

            $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' .
                     $image . '</b>';
            $sortdata['nanswersshort'] = $submission->nanswers;
            $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
            $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

            $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
            $sortdata['datestart'] = $submission->datestart;

            $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
            $sortdata['dateend'] = $submission->dateend;
            $currentpoints = quest_get_points($submission, $quest, '');
            $sortdata['calification'] = $currentpoints;
            $currentpoints = number_format($currentpoints, 4);
            $grade = "<form name=\"puntos$indice\"><input name=\"calificacion\" id=\"formscore$indice\" ".
                    "type=\"text\" value=\"$currentpoints\" size=\"10\" readonl=\"1\" style=\"background-color : White; " .
                    "border : Black; color : Black; font-size : 14pt; text-align : center;\" ></form>";

            $initialpoints[] = (float) $submission->initialpoints;
            $nanswerscorrect[] = (int) $submission->nanswerscorrect;
            $datesstart[] = (int) $submission->datestart;
            $datesend[] = (int) $submission->dateend;
            $dateanswercorrect[] = (int) $submission->dateanswercorrect;
            $pointsmax[] = (float) $submission->pointsmax;
            $pointsmin[] = (float) $submission->pointsmin;
            $pointsanswercorrect[] = (float) $submission->pointsanswercorrect;
            $tinitial[] = (int) $quest->tinitial * 86400;
            $state[] = $submission->state;
            $type = $quest->typecalification;
            $nmaxanswers = (int) $quest->nmaxanswers;
            $pointsnmaxanswers[] = (float) $submission->points;
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
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }

    $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess',
                    'datestart', 'dateend', 'calification');

    $table->width = "95%";

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
            $columnicon = $OUTPUT->pix_icon("/t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"submissions.php?id=$id&amp;sid=$sid&amp;uid=$user->id&amp;action=showsubmissionsuser&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    $table->head = array("$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart",
                    "$dateend", "$calification");

    echo html_writer::table($table);

    $grafic = $OUTPUT->pix_icon('t/clear', 'OK');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";
    $servertime = time();

    // Javascript counter support.
    $servertime = time();
    for ($i = 0; $i < $indice; $i++) {
        $forms[$i] = "#formscore$i";
        $incline[$i] = 0;
    }
    $params = [$indice, $pointsmax, $pointsmin, $initialpoints, $tinitial, $datesstart, $state, $nanswerscorrect,
                    $dateanswercorrect, $pointsanswercorrect, $datesend, $forms, $type, $nmaxanswers,
                    $pointsnmaxanswers, $servertime, null];
    $PAGE->requires->js_call_amd('mod_quest/counter', 'puntuacionarray', $params);

    $continueurl = new moodle_url('viewclasification.php', ['id' => $cm->id]);
    echo $OUTPUT->continue_button($continueurl);
} else if ($action == "showanswersuser") {
    $uid = required_param('uid', PARAM_INT);

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }

    foreach ($users as $user) {
        if ($user->id == $uid) {
            $usertemp = $user;
        }
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
    $tablesort->data = array();
    $tablesort->sortdata = array();

    // ...skip if student not in group.
    if ($answers = quest_get_answers($quest, $user)) {
        foreach ($answers as $answer) {
            $data = array();
            $sortdata = array();

            $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);
            $data[] = quest_print_answer_title($quest, $answer, $submission) .
                     " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id\">" . "<img src=\"" . $CFG->wwwroot .
                     "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                     " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;aid=$answer->id\">" . "<img src=\"" .
                     $CFG->wwwroot . "/pix/t/delete.svg\" " . 'height="11" width="11" border="0" alt="' .
                     get_string('delete', 'quest') . '" /></a>';

            $sortdata['title'] = strtolower($answer->title);

            $data[] = quest_answer_phase($answer, $course);
            $sortdata['phase'] = quest_answer_phase($answer, $course);

            $data[] = userdate($answer->date, get_string('datestr', 'quest'));
            $sortdata['dateanswer'] = $answer->date;

            if (($answer->phase == ANSWER_PHASE_GRADED) || ($answer->phase == ANSWER_PHASE_PASSED)) {
                $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id));
            } else {
                $assessment = null;
            }
            $submission = $DB->get_record('quest_submissions', array('id' => $answer->submissionid), '*', MUST_EXIST);
            $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
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
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }

    $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $columns = array('title', 'phase', 'dateanswer', 'actions', 'calification');

    $table->width = "95%";

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
            $columnicon = " <img src=\"" . $CFG->wwwroot . "pix/t/$columnicon.png\" alt=\"$columnicon\" />";
        }
        $$column = "<a href=\"submissions.php?id=$cm->id&amp;sid=$sid&amp;uid=$user->id&amp;action=showanswersuser&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    $table->head = array("$title", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification");

    echo html_writer::table($table);
    print('<br><p>*' . get_string('calification_provisional_msg', 'quest') . '</p>');
    $continueurl = new moodle_url('viewclasification.php', ['id' => $cm->id]);
    echo $OUTPUT->continue_button($continueurl);
} else if ($action == 'showsubmissionsteam') {

    if (!$canpreview) {
        print_error('nopermissions', 'error', null, "Only teachers can look at this page");
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    // Now prepare table with student assessments and submissions...
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $indice = 0;

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('nostudentsyet'));
        echo $OUTPUT->footer();
        exit();
    }

    $team = $DB->get_record("quest_teams", array("id" => required_param('tid', PARAM_INT)), '*', MUST_EXIST);
    $userstemp = array();
    foreach ($users as $user) {
        if ($calificationuser = $DB->get_record("quest_calification_users",
                array("questid" => $quest->id, "userid" => $user->id))) {

            if ($calificationuser->teamid == $team->id) {
                $userstemp[] = $user;
            }
        }
    }
    $users = $userstemp;

    $title = get_string('showsubmissions', 'quest');
    if ($canpreview) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . get_string('team', 'quest') . ': ' . $team->name;
    }

    // Skip if student not in group.
    foreach ($users as $user) {

        if ($submissions = quest_get_user_submissions($quest, $user)) {
            foreach ($submissions as $submission) {
                $data = array();
                $sortdata = array();

                if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
                         ($submission->nanswerscorrect < $quest->nmaxanswers)) {
                    $submission->phase = SUBMISSION_PHASE_ACTIVE;
                }
                $data[] = quest_print_submission_title($quest, $submission) .
                         " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                         $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest')) . '</a>' .
                         " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                         $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest')) . '</a>';
                $sortdata['title'] = strtolower($submission->title);

                $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?" .
                        "id=$user->id&amp;course=$course->id\">" . fullname($user) . '</a>';
                $sortdata['firstname'] = strtolower($user->firstname);
                $sortdata['lastname'] = strtolower($user->lastname);

                $phase = quest_submission_phase($submission, $quest, $course);
                $data[] = $phase;
                $sortdata['phase'] = $phase;

                $nanswersassess = 0;
                if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?",
                        array($quest->id, $submission->id))) {
                    foreach ($answers as $answer) {
                        if (($answer->phase == 1) || ($answer->phase == 2)) {
                            $nanswersassess++;
                        }
                    }
                }
                $nanswerswhithoutassess = $submission->nanswers - $nanswersassess;
                $image = '';
                if ($answer = $DB->get_record("quest_answers",
                        array("questid" => $quest->id, "submissionid" => $submission->id, "userid" => $USER->id))) {
                    $image = " <img src=\"" . $CFG->wwwroot . "pix/t/clear.png\" />";
                }

                $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' .
                        $nanswerswhithoutassess . ']' . $image . '</b>';
                $sortdata['nanswersshort'] = $submission->nanswers;
                $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
                $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

                $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
                $sortdata['datestart'] = $submission->datestart;

                $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
                $sortdata['dateend'] = $submission->dateend;
                $currentpoints = quest_get_points($submission, $quest, '');
                $sortdata['calification'] = $currentpoints;
                $currentpoints = number_format($currentpoints, 4);
                $grade = "<form name=\"puntos$indice\">" .
                         "<input id=\"formscore$indice\" name=\"calificacion\" type=\"text\" value=\"$currentpoints\" " .
                        "size=\"10\" readonly=\"1\" style=\"background-color : White; border : Black; color : Black; " .
                        "font-size : 14pt; text-align : center;\" ></form>";

                $initialpoints[] = (float) $submission->initialpoints;
                $nanswerscorrect[] = (int) $submission->nanswerscorrect;
                $datesstart[] = (int) $submission->datestart;
                $datesend[] = (int) $submission->dateend;
                $dateanswercorrect[] = (int) $submission->dateanswercorrect;
                $pointsmax[] = (float) $submission->pointsmax;
                $pointsmin[] = (float) $submission->pointsmin;
                $pointsanswercorrect[] = (float) $submission->pointsanswercorrect;
                $tinitial[] = (int) $quest->tinitial * 86400;
                $state[] = $submission->state;
                $type = $quest->typecalification;
                $nmaxanswers = (int) $quest->nmaxanswers;
                $pointsnmaxanswers[] = (float) $submission->points;

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
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }

    $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $columns = array('title', 'firstname', 'lastname', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess',
                    'datestart', 'dateend', 'calification');

    $table->width = "95%";

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
        $$column = "<a href=\"submissions.php?id=$id&amp;sid=$sid&amp;tid=$team->id&amp;action=showsubmissionsteam&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "$columnicon</a>";
    }

    $table->head = array("$title", "$firstname / $lastname", "$phase",
                    "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart",
                    "$dateend", "$calification");

    echo $OUTPUT->heading(get_string('showsubmissionsteam', 'quest'));
    echo html_writer::table($table);

    $grafic = $OUTPUT->pix_icon('t/clear', 'OK');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";

    // Javascript counter support.
    $servertime = time();
    for ($i = 0; $i < $indice; $i++) {
        $forms[$i] = "#formscore$i";
        $incline[$i] = 0;
    }
    $params = [$indice, $pointsmax, $pointsmin, $initialpoints, $tinitial, $datesstart, $state, $nanswerscorrect,
                    $dateanswercorrect, $pointsanswercorrect, $datesend, $forms, $type, $nmaxanswers,
                    $pointsnmaxanswers, $servertime, null];
    $PAGE->requires->js_call_amd('mod_quest/counter', 'puntuacionarray', $params);

    $continueurl = new moodle_url('submissions.php', ['action' => 'showsubmission', 'sid' => $submission->id, 'id' => $cm->id]);
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

    $team = $DB->get_record("quest_teams", array('id' => required_param('tid', PARAM_INT)), '*', MUST_EXIST);
    $userstemp = array();
    foreach ($users as $user) {
        if ($calificationuser = $DB->get_record("quest_calification_users",
                array("questid" => $quest->id, "userid" => $user->id))) {

            if ($calificationuser->teamid == $team->id) {
                $userstemp[] = $user;
            }
        }
    }
    $users = $userstemp;

    $title = get_string('showanswers', 'quest');
    if ($canpreview) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . get_string('team', 'quest') . ': ' . $team->name;
    }
    echo $OUTPUT->heading($title);

    // Now prepare table with student assessments and submissions.
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();

    foreach ($users as $user) {

        // ...skip if student not in group.
        if ($answers = quest_get_answers($quest, $user)) {
            foreach ($answers as $answer) {
                $data = array();
                $sortdata = array();

                $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);

                $data[] = quest_print_answer_title($quest, $answer, $submission) .
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

                $data[] = quest_answer_phase($answer, $course);
                $sortdata['phase'] = quest_answer_phase($answer, $course);

                $data[] = userdate($answer->date, get_string('datestr', 'quest'));
                $sortdata['dateanswer'] = $answer->date;

                if (($answer->phase == 1) || ($answer->phase == 2)) {
                    $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id));
                } else {
                    $assessment = null;
                }

                $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
                $sortdata['tassmnt'] = 1;
                if ($answer->pointsmax == 0) {
                    $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
                } else {
                    $grade = number_format(quest_answer_grade($quest, $answer, 'ALL'), 4) . ' [max ' . number_format(
                            $answer->pointsmax, 4) . ']';
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
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }

    $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $columns = array('title', 'firstname', 'lastname', 'phase', 'dateanswer', 'actions', 'calification');

    $table->width = "95%";

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
        $$column = "<a href=\"submissions.php?id=$cm->id&amp;sid=$sid&amp;tid=$team->id&amp;action=showanswersteam&amp;" .
                "sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    $table->head = array("$title", "$firstname / $lastname", "$phase", "$dateanswer", get_string('actions', 'quest'),
                    "$calification");
    echo html_writer::table($table);
    echo $OUTPUT->continue_button("submissions.php?action=showsubmission&sid=$submission->id&id=$cm->id");
} else if ($action == "recalificationall" && false) { // This action is deprecated.

    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);
    quest_recalification_all($submission, $quest, $course);
    redirect("submissions.php?id=$id&amp;sid=$sid&amp;action=showsubmission");
} else if ($action == "confirmchangeform") {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();
    echo "<br><br>";
    $assessmentsurl = new moodle_url('/mod/quest/assessments.php',
            array('id' => $cm->id, 'sid' => $sid, 'newform' => 1, 'change_form' => 0, 'action' => 'editelements',
                            'sesskey' => sesskey()));
    $submissionsurl = new moodle_url('/mod/quest/submissions.php',
            array('id' => $cm->id, 'sid' => $sid, 'action' => 'showsubmission'));
    echo $OUTPUT->confirm(get_string("doyouwantparticularform", "quest"), $assessmentsurl, $submissionsurl);
} else {
    print_error('unknownactionerror', 'quest', null, $action);
}
echo $OUTPUT->footer();