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
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest

  ACTIONS handled are:
  - submitchallenge
  - confirmdelete
  - delete
  - modif
  - updatesubmission
  - removeattachments
  - showsubmission
  - approve
  - showsubmissionsuser
  - showanswersuser
  - team
  - showanswersteam
  - preview
  - recalificationall
  - confirmchangeform


 * ********************************************** */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("scores_lib.php");

$id = required_param('id', PARAM_INT);    // quest coursemoduleID
global $DB, $OUTPUT, $PAGE, $sort, $dir;

$timenow = time();
list($course,$cm)=quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$action = optional_param('action', 'listallsubmissions', PARAM_ALPHA);


$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");

$sid = optional_param('sid', null,PARAM_INT);

$sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$url = new moodle_url('/mod/quest/submissions.php',
        array('id' => $id, 'sid' => $sid, 'action' => $action, 'sort' => $sort, 'dir' => $dir)); // evp debería añadir los otros posibles parámetros tal y como se ha hecho en assessments_autors.php
$PAGE->set_url($url);

if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, $_POST['userpassword']);
}
/* * ***************** confirm delete *********************************** */
if ($action == 'confirmdelete') {
    $sid = required_param('sid', PARAM_INT); //submission id
    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add(\format_string($submission->title));
    echo $OUTPUT->header();

    echo "<br><br>";
    echo $OUTPUT->confirm(get_string("confirmdeletionofthisitem", "quest", $submission->title),
            "submissions.php?action=delete&amp;id=$cm->id&amp;sid=$sid", "view.php?id=$cm->id#sid=$sid");
} else if ($action == 'delete') {
    $sid = required_param('sid', PARAM_INT); //submission id
    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add(\format_string($submission->title));

    echo $OUTPUT->header();
    // ...check if the user has enough capability to delete the submission and only up to the deadline.
    if (!($ismanager or
            ( has_capability('mod/quest:deletechallengeall', $context)
                or (has_capability('mod/quest:deletechallengemine',$context)
                    and ( $USER->id == $submission->userid))
            and ( $timenow < $quest->dateend)
            and ( $submission->nanswers == 0)
            and ( $timenow < $submission->dateend)
            )
          )
        ) {
        print_error("notauthorizedtodeletesubmission", 'quest');
    }

    if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id))) {
        foreach ($answers as $answer) {
            // first get any assessments...
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

            // now get rid of all answer files
                $fs = get_file_storage();
                $fs->delete_area_files($context->id,'mod_quest','answer',$answer->id);
                $fs->delete_area_files($context->id,'mod_quest','answer_attachment',$answer->id);
        }
    }

    if ($assessment_autor = $DB->get_record("quest_assessments_autors",
            array("submissionid" => $submission->id, "questid" => $quest->id))) {

        $DB->delete_records("quest_items_assesments_autor",
                array("assessmentautorid" => $assessment_autor->id, "questid" => $quest->id));
        $DB->delete_records("quest_assessments_autors", array("id" => $assessment_autor->id));
    }
    /////////////////////
    // recalculate points and report to gradebook
    //////////////////////
    quest_grade_updated($quest, $submission->userid);

    $DB->delete_records_select('event', 'modulename = ? AND instance = ? and ' . $DB->sql_compare_text('description') . ' = ?',
            array('modulename' => 'quest', 'instance' => $quest->id, 'description' => $submission->description));
    // ...and the submission record...
    $DB->delete_records("quest_submissions", array("id" => $submission->id));
    // ..and finally the submitted files.
   // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id,'mod_quest','submission',$submission->id);
    $fs->delete_area_files($context->id,'mod_quest','attachment',$submission->id);

    if ($ismanager) {
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit;
        }
        if ($submissiongroup = $DB->get_record("groups_members", array("userid" => $submission->userid))) {
            $currentgroup = $submissiongroup->groupid;
        }
//          /** JPC 2013-11-28 disable excesive notifications
//          foreach($users as $user){
//             if(!$ismanager){
//              if (isset($currentgroup))
//   			{
//                         if (!groups_is_member($currentgroup, $user->id)) {
//                             continue;
//                         }
//              }
//             }
//             quest_send_message($user, "view.php?id=$cm->id", 'deletesubmission', $quest, $submission, '');
//          }
    } else {
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit;
        }
//          foreach($users as $user){
//           if(!$ismanager){
//            continue;
//           }
//           quest_send_message($user, "view.php?id=$cm->id", 'deletesubmission', $quest, $submission, '');
//          }


    }
 // Log the action

    if ($CFG->version >= 2014051200) {
        require_once('classes/event/challenge_deleted.php');
        $view_event = mod_quest\event\challenge_deleted::create_from_parts($USER, $submission, $cm);
        $view_event->trigger();
    } else {
        add_to_log($course->id, "quest", "delete_submission",
                "view.php?id=$cm->id", "$submission->id", "$cm->id");
    }
   
    echo "<center>" . get_string("deletechallenge", "quest") . "</center>";

    echo $OUTPUT->continue_button("view.php?id=$cm->id");
}

/* * **************** submission of a challenge by teacher or proposal from student ********************** */
else if ($action == 'submitchallenge') {
    // check if the user has enough capability to add the submission
    if (!($ismanager or ( has_capability('mod/quest:addchallenge', $context)))) {
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

    $descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes, 'context' => $context);
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes, 'context' => $context);

    $newsubmission = file_prepare_standard_editor($newsubmission, 'description', $descriptionoptions, $context, 'mod_quest',
            'submission', $newsubmission->id);
    $newsubmission = file_prepare_standard_filemanager($newsubmission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $newsubmission->id);

    $mform = new quest_print_upload_form(null,
            array('submission' => $newsubmission, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions, 'attachmentoptions' => $attachmentoptions, 'action' => $action)); //the first parameter is $action, null will case the form action to be determined automatically)

    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($newsubmission = $mform->get_data()) {
        $authorid = $USER->id;
        quest_upload_challenge($quest, $newsubmission, $ismanager, $cm, $descriptionoptions, $attachmentoptions, $context, $action,
                $authorid);
    } else {
        $PAGE->set_title(format_string($quest->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string("submitchallengeassignment", "quest") . ":", "submitchallengeassignment",
                "quest");
        $mform->display();
    }
}
// Edit submission. 
else if ($action == 'modif') {
    // $usehtmleditor = can_use_html_editor();
    $sid = required_param('sid', PARAM_INT); //submission id

    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);
    $titlesubmission = $submission->title;
    $PAGE->navbar->add(\format_string($submission->title));

    if (($submission->userid != $USER->id) && (!($ismanager))) {
        error("Edit submission: Only teachers and autors can look this page");
    }

    $descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes, 'context' => $context); //evp limito para el editor por el tama�o del curso permitido, no tengo claro si es la mejor opci�n
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes);

    $submission = file_prepare_standard_editor($submission, 'description', $descriptionoptions, $context, 'mod_quest',
            'submission', $submission->id);
    $submission = file_prepare_standard_filemanager($submission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $submission->id);
    $draftitemid = file_get_submitted_draft_itemid('introattachments');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_quest', 'attachment',0, array('subdirs' => 0));
    $submission->attachment = $draftitemid;
    $mform = new quest_print_upload_form(null,
            array('submission' => $submission, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions, 'attachmentoptions' => $attachmentoptions, 'action' => $action)); //the first parameter is $action, null will case the form action to be determined automatically)

    if ($mform->is_cancelled()) {
        redirect("view.php?id=$cm->id");
    } else if ($modif_submission = $mform->get_data()) {
//         	$submission->id=$submission->sid;// id param is used in the page for coursemodule
//         	unset($submission->sid);
        $authorid = $submission->userid;
        quest_upload_challenge($quest, $modif_submission, $ismanager, $cm, $descriptionoptions, $attachmentoptions, $context,
                $action, $authorid);
    } else {
        $PAGE->set_title(format_string($quest->name));
        //$PAGE->set_context($context);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        echo $OUTPUT->heading_with_help(get_string("modifsubmission", "quest", $titlesubmission), "modifsubmission", "quest");

        $mform->display();
    }
}
/*
 * remove (all) attachments
 */ else if ($action == 'removeattachments') {

    $form = data_submitted();
    $sid = required_param('sid', PARAM_INT); // ...submission id.
    $title = required_param('title', PARAM_TEXT); // ...title.
    $description = required_param('description', PARAM_RAW_TRIMMED);

    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);
    $PAGE->navbar->add(\format_string($submission->title));

    // ...students are only allowed to remove their own attachments and only up to the deadline.
    if (!($ismanager or ( ($USER->id == $submission->userid) and ( $timenow < $submission->dateend)))) {
        error("You are not authorized to delete these attachments");
    }
    $submission->title = $title;
    $submission->description = $description;
    // ...amend title... just in case they were modified.
    $DB->update_record('quest_submissions', $submission);

    print_string("removeallattachments", "quest");
    quest_delete_submitted_files_submissions($quest, $submission);
    add_to_log($course->id, "quest", "removeattachments",
            "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", "$submission->id", "$cm->id");

    echo $OUTPUT->continue_button("submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=$form->beforeaction");
}
/*
 * show submission
 */ else if ($action == 'showsubmission') {

    $sid = required_param('sid', PARAM_INT); //submission id
    $submission = $DB->get_record("quest_submissions", array("id" => $sid), '*', MUST_EXIST);
    if (
            (!($ismanager)) &&
            ($submission->userid != $USER->id &&
            ($submission->datestart > time() || $submission->state == 1))) {
        print_error('notpermissionsubmission', 'quest');
    }

    if (($submission->datestart < time()) && ($submission->dateend > time()) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE; // active
    } else {
        $submission->phase = SUBMISSION_PHASE_CLOSED; //closed
    }
    if (($quest->permitviewautors == 1) &&
            ($submission->phase == SUBMISSION_PHASE_CLOSED) &&
            ($submission->state == SUBMISSION_STATE_APROVED) &&
            ($submission->datestart < time()) ||
            has_capability('mod/quest:viewotherattemptsowners', $context)
    ) {
        $permitviewautors = 1;
    } else {
        $permitviewautors = 0;
    }

    $title = '"' . $submission->title . '"';
    // convenient editing button for teachers
    if (has_capability('mod/quest:editchallengeall', $context)) {
        $title.= "<a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                $OUTPUT->pix_icon('/t/edit', get_string('modif', 'quest'))
                . '</a> ';
    }
    if (($ismanager) || ($submission->userid == $USER->id) || ($permitviewautors == 1)) {
        $title .= get_string('by', 'quest') . ' ' . quest_fullname($submission->userid, $course->id);
    }

    $PAGE->set_title(format_string($quest->name.' '.$submission->title));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add(\format_string($submission->title));

    echo $OUTPUT->header();

    /*
     *  Flag to force a recalculation of team statistics and scores.
     * Only to solve bugs.
     */
    $debug_recalculate = optional_param('recalculate', 'no', PARAM_ALPHA);
    /*
     *  Flag to force a recalculation of team statistics and scores.
     * Only to solve bugs.
     */
    $recalculatelink = '';
    if ($debug_recalculate === 'yes') {
        require_once("scores_lib.php");
        print("<p>Fixing submission stats...</p>");
        $submission = quest_update_submission_counts($submission->id);

    } else if ($ismanager) {
        /*
         *  Link for recalculate challenge stats
         */
        $recalculatelink = '/ <a href="' . $CFG->wwwroot . "/mod/quest/submissions.php?id=$cm->id&action=showsubmission&sid=$submission->id&recalculate=yes" . '">Recalc.</a>';
    }

    echo $OUTPUT->heading($title);
    echo("<center><table width=100% ><tr><td>");
    quest_print_submission_info($quest, $submission);
    echo("</td><td>");
    /**
     * INCRUSTA GRÁFICO DE EVOLUCION DE PUNTOS
     */
    quest_print_score_graph($quest, $submission);

    echo"</td></tr></table></center>";
    $text = "<center><b>";
    $text .= "<a href=\"assessments.php?id=$cm->id&amp;sid=$submission->id&amp;viewgeneral=0&amp;action=displaygradingform\">" .
            get_string("specimenassessmentformanswer", "quest") . "</a>";
    $text.=$OUTPUT->help_icon('specimenanswer', 'quest');

    if ((($ismanager || $USER->id == $submission->userid)
            and $quest->nelementsautor) && ($submission->numelements == 0)) {
        $text .= "&nbsp;<a href=\"submissions.php?id=$cm->id&newform=1&sid=$sid&cambio=0&amp;action=confirmchangeform\">" .
                $OUTPUT->pix_icon('/t/edit', get_string('amendassessmentelements', 'quest')) . '</a>';
    } else
    if ((($ismanager || $USER->id == $submission->userid)
            and $quest->nelementsautor) && ($submission->numelements != 0)) {
        $text .= "&nbsp;<a href=\"assessments.php?id=$cm->id&amp;sid=$sid&amp;newform=1&amp;change_form=0&amp;action=editelements\">" .
                $OUTPUT->pix_icon('/t/edit', get_string('amendassessmentelements', 'quest')) . '</a>';
    }
    $text .= "</b></center>";
    echo($text);

    echo $OUTPUT->heading(get_string('description', 'quest'));
    /*     * *
     *  Wording of the challenge
     * * */
    quest_print_submission($quest, $submission);

    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
    $groupmode = groups_get_activity_group($cm);   // Groups are being used?
    //$currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);  evp no estoy segura de que sea este el mejor cambio
    $currentgroup = groups_get_course_group($COURSE);

    if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE;
    }

    if (!has_capability('mod/quest:manage', $context, $submission->userid) && ($groupmode == 2)) {

        if ($currentgroup && !groups_is_member($currentgroup, $submission->userid) && !($submission->dateend < time())) {
            print(get_string('cantRespond_WARN_notingroup_or_challengeended', 'quest'));
        } else {
            $actionlinks = quest_actions_submission($course, $submission, $quest, $cm);
        }
    } else {
        $actionlinks = quest_actions_submission($course, $submission, $quest, $cm);
    }
    echo $actionlinks . $recalculatelink;
    echo "<br>";

    $sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
    $dir = optional_param('dir', 'ASC', PARAM_ALPHA);
    quest_print_table_answers($quest, $submission, $course, $cm, $sort, $dir);

    if ($REPEAT_ACTIONS_BELOW) {
        if (!has_capability('mod/quest:manage', $context, $submission->userid) && ($groupmode == 2)) {
            if ($currentgroup) {
                if (groups_is_member($currentgroup, $submission->userid)) {
                    $actionlinks = quest_actions_submission($course, $submission, $quest, $cm);
                } else if ($submission->dateend < time()) {
                    $actionlinks = quest_actions_submission($course, $submission, $quest, $cm);
                }
            } else {
                $actionlinks = quest_actions_submission($course, $submission, $quest, $cm);
            }
        } else {
            $actionlinks = quest_actions_submission($course, $submission, $quest, $cm);
        }
    }
    echo $actionlinks . $recalculatelink;
    if ($CFG->version >= 2014051200) {
        require_once('classes/event/challenge_viewed.php');
        $view_event = mod_quest\event\challenge_viewed::create_from_parts($USER, $submission, $cm);
        $view_event->trigger();
    } else {
        add_to_log($course->id, "quest", "read_submission",
                "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", "$submission->id", "$cm->id");
    }

    echo $OUTPUT->continue_button("view.php?id=$cm->id");
}

/* * ************* update submission ************************** */
else if ($action == 'updatesubmission') {
    $form = data_submitted();
    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);
    $PAGE->navbar->add(\format_string($submission->title));

    // students are only allowed to update their own submission and only up to the deadline
    if (!($ismanager or ( ($USER->id == $submission->userid) and ( $timenow < $quest->dateend)))) {
        error("You are not authorized to update your submission");
    }
    $title = required_param('title', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW_TRIMMED);
    $submission->title = $title;
    $submission->description = $description;

    $submission->datestart = make_timestamp(required_param('submissionstartyear', PARAM_INT),
            required_param('submissionstartmonth', PARAM_INT), required_param('submissionstartday', PARAM_INT),
            required_param('submissionstarthour', PARAM_INT), required_param('submissionstartminute', PARAM_INT));

    $submission->dateend = make_timestamp(required_param('submissionendyear', PARAM_INT),
            required_param('submissionendmonth', PARAM_INT), required_param('submissionendday', PARAM_INT),
            required_param('submissionendhour', PARAM_INT), required_param('submissionendminute', PARAM_INT));
    $submission->timecreated = time();
    $submission->tinitial = $quest->tinitial;

    if ($submission->dateend > $quest->dateend) {
        $submission->dateend = $quest->dateend;
    }
    if ($form->initialpoints > $form->pointsmax) {
        $form->initialpoints = $form->pointsmax;
    }
    if (!quest_check_submission_dates($submission, $quest)) {
        error(get_string('invaliddates', 'quest'), "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=modif");
    }
    if (!quest_check_submission_text($submission)) {
        error(get_string('invalidtext', 'quest'), "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=modif");
    }
    $submission->mailed = 0;
    $submission->pointsmax = required_param('pointsmax', PARAM_INT);
    $submission->initialpoints = required_param('initialpoints', PARAM_INT);

    if ($ismanager) {
        $submission->perceiveddifficulty = $form->perceiveddifficulty;
        $submission->predictedduration = $form->predictedduration;
        $submission->comentteacherautor = optional_param('comentteacherautor', $submission->comentteacherautor, PARAM_TEXT);
        $submission->comentteacherpupil = optional_param('comentteacherpupil', $submission->comentteacherpupil, PARAM_TEXT);
    }
    quest_update_submission($submission);
    quest_update_challenge_calendar($cm,$quest,$submission);

    if ($ismanager) {
        if ($submission->datestart < time() &&
                $submission->state != 1) { //Approval pending
            if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                print_heading(get_string("nostudentsyet"));
                print_footer($course);
                exit;
            }
            if ($submissiongroup = $DB->get_record("groups_members", array("userid" => $submission->userid))) {
                $currentgroup = $submissiongroup->groupid;
            }
//    JPC 2013-11-28 disable excesive notifications
//           foreach($users as $user){
//              if(!$ismanager){
//               if (isset($currentgroup)) {
//                          if (!groups_is_member($currentgroup, $user->id)) {
//                              continue;
//                          }
//               }
//              }
//              quest_send_message($user, "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", 'modifsubmission', $quest, $submission, '');
//           }
//           $DB->set_field("quest_submissions","maileduser",1,array("id"=>$submission->id));
        }
    } else { //not teacher
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            print_heading(get_string("nostudentsyet"));
            print_footer($course);
            exit;
        }
//    JPC 2013-11-28 disable excesive notifications
//          foreach($users as $user){ //mail to teachers
//           if(!$ismanager){
//            continue;
//           }
//           quest_send_message($user, "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", 'modifsubmission', $quest, $submission, '');
//          }
    }


    if ($quest->nattachments) {
        require_once($CFG->dirroot . '/lib/uploadlib.php');
        $um = new upload_manager(null, false, false, $course, false, $quest->maxbytes);
        if ($um->preprocess_files()) {
            $dir = quest_file_area_name_submissions($quest, $submission);
            if ($um->save_files($dir)) {
                add_to_log($course->id, "quest", "newattachment",
                        "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", "$submission->id",
                        "$cm->id");
                print_heading(get_string("uploadsuccess", "quest"));
            }
            // upload manager will print errors.
        }
    }

    // Log the action

    if ($CFG->version >= 2014051200) {
        require_once('classes/event/challenge_changed.php');
        $view_event = mod_quest\event\challenge_changed::create_from_parts($USER, $submission, $cm);
        $view_event->trigger();
    } else {
        add_to_log($course->id, "quest", "modif_submission",
                "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", "$submission->id", "$cm->id");
    }



    print_heading(get_string("submitted", "quest") . " " . get_string("ok"));
    echo $OUTPUT->continue_button("view.php?id=$cm->id");
} else if ($action == 'approve') {
    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);
    $authorid = $submission->userid;
    $PAGE->navbar->add(\format_string($submission->title));

    if (!$ismanager)
        print_error("Approve submission: No enougth permissions to take this action");

    $descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes, 'context' => $context); //evp limito para el editor por el tama�o del curso permitido, estudiar si es la mejor opci�n
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $quest->nattachments, 'maxbytes' => $quest->maxbytes);

    $submission = file_prepare_standard_editor($submission, 'description', $descriptionoptions, $context, 'mod_quest',
            'submission', $submission->id);
    $submission = file_prepare_standard_filemanager($submission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $submission->id);

    $mform = new quest_print_upload_form(null,
            array('submission' => $submission, 'quest' => $quest, 'cm' => $cm, 'definitionoptions' => $descriptionoptions, 'attachmentoptions' => $attachmentoptions, 'action' => $action)); //the first parameter is $action, null will case the form action to be determined automatically)

    if ($mform->is_cancelled()) {
        redirect("submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$sid");
    } else if ($submission = $mform->get_data()) {


        if (isset($submission->submitbuttonapprove)) {
            quest_upload_challenge($quest, $submission, $ismanager, $cm, $descriptionoptions, $attachmentoptions, $context,
                    $action, $authorid);
        } else {  //save but not approve
            //echo" save but not approve";print_object($submission);die;
            $action = 'modif';
            quest_upload_challenge($quest, $submission, $ismanager, $cm, $descriptionoptions, $attachmentoptions, $context,
                    $action, $authorid);
            //!!!!comprobar si al modificar un profesor pero no aprobar, el desafío sigue bien su estado
        }
    } else {

        $PAGE->set_title(format_string($quest->name));
        //$PAGE->set_context($context);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        echo $OUTPUT->heading_with_help(get_string("approvesubmission", "quest"), "approvesubmission", "quest");

        $mform->display();
    }
}
/* * ************* no man's land ************************************* */ else if ($action == 'showsubmissionsuser') {

    if (!$ismanager) {
        error("Only teachers can look at this page");
    }
    ?>
    <script language="JavaScript">
        var servertime =<?php echo time() * 1000; ?>;
        var browserDate = new Date();
        var browserTime = browserDate.getTime();
        var correccion = servertime - browserTime;

        function redondear(cantidad, decimales) {
            var cantidad = parseFloat(cantidad);
            var decimales = parseFloat(decimales);
            decimales = (!decimales ? 2 : decimales);
            var valor = Math.round(cantidad * Math.pow(10, decimales)) / Math.pow(10, decimales);
            return valor.toFixed(4);
        }

        function puntuacion(indice, incline, pointsmax, initialpoints, tinitial, datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios, type, nmaxanswers, pointsnmaxanswers) {
            global $DB;
            for (i = 0; i < indice; i++) {

                tiempoactual = new Date();
                tiempo = parseInt((tiempoactual.getTime() + correccion) / 1000);

                if ((dateend[i] - datestart[i] - tinitial[i]) == 0) {
                    incline[i] = 0;
                }
                else {
                    if (type == 0) {
                        incline[i] = (pointsmax[i] - initialpoints[i]) / (dateend[i] - datestart[i] - tinitial[i]);
                    }
                    else {
                        if (initialpoints[i] == 0) {
                            initialpoints[i] = 0.0001;
                        }
                        incline[i] = (1 / (dateend[i] - datestart[i] - tinitial[i])) * Math.log(pointsmax[i] / initialpoints[i]);

                    }
                }

                if (state[i] < 2) {
                    grade = initialpoints[i];
                    formularios[i].style.color = "#cccccc";
                }
                else {

                    if (datestart[i] > tiempo) {
                        grade = initialpoints[i];
                        formularios[i].style.color = "#cccccc";
                    }
                    else {
                        if (nanswerscorrect[i] >= nmaxanswers) {
                            grade = 0;
                            formularios[i].style.color = "#cccccc";
                        }
                        else {
                            if (dateend[i] < tiempo) {
                                if (nanswerscorrect[i] == 0) {
                                    t = dateend[i] - datestart[i];
                                    if (t <= tinitial[i]) {
                                        grade = initialpoints[i];
                                        formularios[i].style.color = "#cccccc";
                                    }
                                    else {
                                        grade = pointsmax[i];
                                        formularios[i].style.color = "#cccccc";
                                    }

                                }
                                else {

                                    grade = 0;
                                    formularios[i].style.color = "#cccccc";
                                }


                            }
                            else {
                                if (nanswerscorrect[i] == 0) {
                                    t = tiempo - datestart[i];
                                    if (t < tinitial[i]) {
                                        grade = initialpoints[i];
                                        formularios[i].style.color = "#000000";
                                    }
                                    else {
                                        if (t >= (dateend[i] - datestart[i])) {
                                            grade = pointsmax[i];
                                            formularios[i].style.color = "#000000";
                                        }
                                        else {
                                            if (type == 0) {
                                                grade = (t - tinitial[i]) * incline[i] + initialpoints[i];
                                                formularios[i].style.color = "#000000";
                                            }
                                            else {
                                                grade = initialpoints[i] * Math.exp(incline[i] * (t - tinitial[i]));
                                                formularios[i].style.color = "#000000";
                                            }
                                        }
                                    }
                                }
                                else {
                                    t = tiempo - dateanswercorrect[i];
                                    if ((dateend[i] - dateanswercorrect[i]) == 0) {
                                        incline[i] = 0;
                                    }
                                    else {
                                        if (type == 0) {
                                            incline[i] = (-pointsanswercorrect[i]) / (dateend[i] - dateanswercorrect[i]);
                                        }
                                        else {
                                            incline[i] = (1 / (dateend[i] - dateanswercorrect[i])) * Math.log(0.0001 / pointsanswercorrect[i]);
                                        }
                                    }
                                    if (type == 0) {
                                        grade = pointsanswercorrect[i] + incline[i] * t;
                                        formularios[i].style.color = "#000000";
                                    }
                                    else {
                                        grade = pointsanswercorrect[i] * Math.exp(incline[i] * t);
                                        formularios[i].style.color = "#000000";
                                    }
                                }

                            }

                        }

                    }
                }
                if (grade < 0) {
                    grade = 0;
                }
                grade = redondear(grade, 4);
                formularios[i].value = grade;
            }

            setTimeout("puntuacion(indice,incline,pointsmax,initialpoints,tinitial,datestart,state,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers)", 100);

        }
    </script>

    <?php
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    // Now prepare table with student assessments and submissions
    $tablesort = new stdclass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $indice = 0;
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        print_heading(get_string("nostudentsyet"));
        print_footer($course);
        exit;
    }

    foreach ($users as $user) {
        if ($user->id == $_GET['uid']) {
            $usertemp = $user;
        }
    }
    $user = $usertemp;

    $title = get_string('showsubmissions', 'quest');
    if ($ismanager) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . quest_fullname($user->id, $course->id);
    }
    echo $OUTPUT->heading($title);

    // skip if student not in group

    if ($submissions = quest_get_user_submissions($quest, $user)) {
        foreach ($submissions as $submission) {
            $data = array();
            $sortdata = array();

            if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
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
                ;
            }

            $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' . $image . '</b>';
            $sortdata['nanswersshort'] = $submission->nanswers;
            $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
            $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

            $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
            $sortdata['datestart'] = $submission->datestart;

            $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
            $sortdata['dateend'] = $submission->dateend;

            $grade = "<form name=\"puntos$indice\"><input name=\"calificacion\" type=\"text\" value=\"0.0000\" size=\"10\" readonl=\"1\" style=\"background-color : White; border : Black; color : Black; font-family : Verdana, Arial, Helvetica; font-size : 14pt; text-align : center;\" ></form>";

            $initialpoints[] = $submission->initialpoints;
            $nanswerscorrect[] = $submission->nanswerscorrect;
            $datesstart[] = $submission->datestart;
            $datesend[] = $submission->dateend;
            $dateanswercorrect[] = $submission->dateanswercorrect;
            $pointsmax[] = $submission->pointsmax;
            $pointsanswercorrect[] = $submission->pointsanswercorrect;
            $tinitial[] = $quest->tinitial * 86400;
            $state[] = $submission->state;
            $type = $quest->typecalification;
            $nmaxanswers = $quest->nmaxanswers;
            $pointsnmaxanswers[] = $submission->points;

            $data[] = $grade;
            $sortdata['calification'] = quest_get_points($submission, $quest, '');

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
    $columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');

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
        $$column = "<a href=\"submissions.php?id=$id&amp;sid=$sid&amp;uid=$user->id&amp;action=showsubmissionsuser&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }


    $table->head = array("$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart", "$dateend", /* get_string('actions','quest'), */ "$calification");

    echo html_writer::table($table);

    $grafic = $OUTPUT->pix_icon('t/clear', 'OK');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";

    echo "<script language=\"JavaScript\">\n";
    echo "var initialpoints = new Array($indice);\n";
    echo "var nanswerscorrect = new Array($indice);\n";
    echo "var datestart = new Array($indice);\n";
    echo "var dateend = new Array($indice);\n";
    echo "var dateanswercorrect = new Array($indice);\n";
    echo "var pointsmax = new Array($indice);\n";
    echo "var formularios = new Array($indice);\n";
    echo "var state = new Array($indice);\n";
    echo "var tinitial = new Array($indice);\n";
    echo "var pointsanswercorrect = new Array($indice);\n";
    echo "var incline = new Array($indice);\n";
    echo "var pointsnmaxanswers = new Array($indice);\n";



    for ($i = 0; $i < $indice; $i++) {
        echo "initialpoints[$i] = $initialpoints[$i];\n";
        echo "nanswerscorrect[$i] = $nanswerscorrect[$i];\n";
        echo "datestart[$i] = $datesstart[$i];\n";
        echo "dateend[$i] = $datesend[$i];\n";
        echo "dateanswercorrect[$i] = $dateanswercorrect[$i];\n";
        echo "pointsmax[$i] = $pointsmax[$i];\n";
        echo "state[$i] = $state[$i];\n";
        echo "tinitial[$i] = $tinitial[$i];\n";
        echo "pointsanswercorrect[$i] = $pointsanswercorrect[$i];\n";
        echo "formularios[$i] = document.forms.puntos$i.calificacion;\n";
        echo "incline[$i] = 0;\n";
        echo "pointsnmaxanswers[$i] = $pointsnmaxanswers[$i];\n";
    }
    echo "var indice = $indice;\n";
    echo "var type = $type;\n";
    echo "var nmaxanswers = $nmaxanswers;\n";

    echo "puntuacion(indice,incline,pointsmax,initialpoints,tinitial,datestart,state,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers);\n";

    echo "</script>\n";

    echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER'] . '#sid=' . $submission->id);
}
////////////////////////////////////////////////////////////
else if ($action == "showanswersuser") {

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit;
    }

    foreach ($users as $user) {
        if ($user->id == $_GET['uid']) {
            $usertemp = $user;
        }
    }
    $user = $usertemp;

    $title = get_string('showanswers', 'quest');
    if ($ismanager) {
        $user->imagealt = quest_fullname($user->id, $course->id);
        $title .= ' ' . get_string('of', 'quest') . ' ' . $OUTPUT->user_picture($user) . $user->imagealt;
    }

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);

    // Now prepare table with student assessments and submissions
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();

    // skip if student not in group

    if ($answers = quest_get_answers($quest, $user)) {
        foreach ($answers as $answer) {
            $data = array();
            $sortdata = array();

            $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid),'*',MUST_EXIST);
            $data[] = quest_print_answer_title($quest, $answer, $submission) .
                    " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id\">" .
                    "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " .
                    'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                    " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;aid=$answer->id\">" .
                    "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " .
                    'height="11" width="11" border="0" alt="' . get_string('delete', 'quest') . '" /></a>';

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
            $submission = $DB->get_record('quest_submissions', array('id' => $answer->submissionid),'*',MUST_EXIST);
            $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
            $sortdata['tassmnt'] = 1;

            $score = quest_answer_grade($quest, $answer, 'ALL');

            if ($answer->pointsmax == 0)
                $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
            else
                $grade = number_format($score, 4) . ' (' . number_format(100 * $score / $answer->pointsmax, 0) . '%) [max ' . number_format($answer->pointsmax,
                                4) . ']';

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
        $$column = "<a href=\"submissions.php?id=$cm->id&amp;sid=$sid&amp;uid=$user->id&amp;action=showanswersuser&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }


    $table->head = array("$title", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification");

    echo html_writer::table($table);
    print('<br><p>*' . get_string('calification_provisional_msg', 'quest') . '</p>');

    //echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER'].'#sid='.$submission->id);
    echo $OUTPUT->continue_button($CFG->wwwroot . "/mod/quest/view.php?id=$cm->id");
}
/////////////////////////////////////////////////////////////////
else if ($action == 'showsubmissionsteam') {

    if (!$ismanager) {
        error("Only teachers can look at this page");
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    ?>
    <script language="JavaScript">
        var servertime =<?php echo time() * 1000; ?>;
        var browserDate = new Date();
        var browserTime = browserDate.getTime();
        var correccion = servertime - browserTime;

        function redondear(cantidad, decimales) {
            var cantidad = parseFloat(cantidad);
            var decimales = parseFloat(decimales);
            decimales = (!decimales ? 2 : decimales);
            var valor = Math.round(cantidad * Math.pow(10, decimales)) / Math.pow(10, decimales);
            return valor.toFixed(4);
        }

        function puntuacion(indice, incline, pointsmax, initialpoints, tinitial, datestart, state, nanswerscorrect, dateanswercorrect, pointsanswercorrect, dateend, formularios, type, nmaxanswers, pointsnmaxanswers) {

            for (i = 0; i < indice; i++) {

                tiempoactual = new Date();
                tiempo = parseInt((tiempoactual.getTime() + correccion) / 1000);

                if ((dateend[i] - datestart[i] - tinitial[i]) == 0) {
                    incline[i] = 0;
                }
                else {
                    if (type == 0) {
                        incline[i] = (pointsmax[i] - initialpoints[i]) / (dateend[i] - datestart[i] - tinitial[i]);
                    }
                    else {
                        if (initialpoints[i] == 0) {
                            initialpoints[i] = 0.0001;
                        }
                        incline[i] = (1 / (dateend[i] - datestart[i] - tinitial[i])) * Math.log(pointsmax[i] / initialpoints[i]);

                    }
                }

                if (state[i] < 2) {
                    grade = initialpoints[i];
                    formularios[i].style.color = "#cccccc";
                }
                else {

                    if (datestart[i] > tiempo) {
                        grade = initialpoints[i];
                        formularios[i].style.color = "#cccccc";
                    }
                    else {
                        if (nanswerscorrect[i] >= nmaxanswers) {
                            grade = 0;
                            formularios[i].style.color = "#cccccc";
                        }
                        else {
                            if (dateend[i] < tiempo) {
                                if (nanswerscorrect[i] == 0) {
                                    t = dateend[i] - datestart[i];
                                    if (t <= tinitial[i]) {
                                        grade = initialpoints[i];
                                        formularios[i].style.color = "#cccccc";
                                    }
                                    else {
                                        grade = pointsmax[i];
                                        formularios[i].style.color = "#cccccc";
                                    }

                                }
                                else {

                                    grade = 0;
                                    formularios[i].style.color = "#cccccc";
                                }


                            }
                            else {
                                if (nanswerscorrect[i] == 0) {
                                    t = tiempo - datestart[i];
                                    if (t < tinitial[i]) {
                                        grade = initialpoints[i];
                                        formularios[i].style.color = "#000000";
                                    }
                                    else {
                                        if (t >= (dateend[i] - datestart[i])) {
                                            grade = pointsmax[i];
                                            formularios[i].style.color = "#000000";
                                        }
                                        else {
                                            if (type == 0) {
                                                grade = (t - tinitial[i]) * incline[i] + initialpoints[i];
                                                formularios[i].style.color = "#000000";
                                            }
                                            else {
                                                grade = initialpoints[i] * Math.exp(incline[i] * (t - tinitial[i]));
                                                formularios[i].style.color = "#000000";
                                            }
                                        }
                                    }
                                }
                                else {
                                    t = tiempo - dateanswercorrect[i];
                                    if ((dateend[i] - dateanswercorrect[i]) == 0) {
                                        incline[i] = 0;
                                    }
                                    else {
                                        if (type == 0) {
                                            incline[i] = (-pointsanswercorrect[i]) / (dateend[i] - dateanswercorrect[i]);
                                        }
                                        else {
                                            incline[i] = (1 / (dateend[i] - dateanswercorrect[i])) * Math.log(0.0001 / pointsanswercorrect[i]);
                                        }
                                    }
                                    if (type == 0) {
                                        grade = pointsanswercorrect[i] + incline[i] * t;
                                        formularios[i].style.color = "#000000";
                                    }
                                    else {
                                        grade = pointsanswercorrect[i] * Math.exp(incline[i] * t);
                                        formularios[i].style.color = "#000000";
                                    }
                                }

                            }

                        }

                    }
                }
                if (grade < 0) {
                    grade = 0;
                }
                grade = redondear(grade, 4);
                formularios[i].value = grade;
            }

            setTimeout("puntuacion(indice,incline,pointsmax,initialpoints,tinitial,datestart,state,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers)", 100);

        }

    </script>

    <?php
    // Now prepare table with student assessments and submissions
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $indice = 0;

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        print_heading(get_string("nostudentsyet"));
        print_footer($course);
        exit;
    }

    if (!$team = $DB->get_record("quest_teams", array("id" => required_param('tid', PARAM_INT)))) {
        error('Team id is incorrect');
    }

    $userstemp = array();
    foreach ($users as $user) {
        if ($calification_user = $DB->get_record("quest_calification_users", array("questid" => $quest->id, "userid" => $user->id))) {

            if ($calification_user->teamid == $team->id) {
                $userstemp[] = $user;
            }
        }
    }
    $users = $userstemp;


    $title = get_string('showsubmissions', 'quest');
    if ($ismanager) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . get_string('team', 'quest') . ': ' . $team->name;
    }

    // skip if student not in group

    foreach ($users as $user) {

        if ($submissions = quest_get_user_submissions($quest, $user)) {
            foreach ($submissions as $submission) {
                $data = array();
                $sortdata = array();

                if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
                    $submission->phase = SUBMISSION_PHASE_ACTIVE;
                }
                $data[] = quest_print_submission_title($quest, $submission) .
                        " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
//                         "<img src=\"".$CFG->wwwroot."/pix/t/edit.svg\" ".'height="11" width="11" border="0" alt="'.get_string('modif', 'quest').'" />'
                        $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest'))
                        . '</a>' .
                        " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
//                         "<img src=\"".$CFG->wwwroot."/pix/t/delete.svg\" ".'height="11" width="11" border="0" alt="'.get_string('delete', 'quest').'" />'
                        $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest'))
                        . '</a>';
                $sortdata['title'] = strtolower($submission->title);

                $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
                        fullname($user) . '</a>';
                $sortdata['firstname'] = strtolower($user->firstname);
                $sortdata['lastname'] = strtolower($user->lastname);

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
                        array("questid" => $quest->id, "submissionid" => $submission->id, "userid" => $USER->id))) {
                    $image = " <img src=\"" . $CFG->wwwroot . "pix/t/clear.png\" />";
                }

                $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' . $image . '</b>';
                $sortdata['nanswersshort'] = $submission->nanswers;
                $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
                $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

                $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
                $sortdata['datestart'] = $submission->datestart;

                $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
                $sortdata['dateend'] = $submission->dateend;

                $grade = "<form name=\"puntos$indice\"><input name=\"calificacion\" type=\"text\" value=\"0.0000\" size=\"10\" readonly=\"1\" style=\"background-color : White; border : Black; color : Black; font-family : Verdana, Arial, Helvetica; font-size : 14pt; text-align : center;\" ></form>";

                $initialpoints[] = $submission->initialpoints;
                $nanswerscorrect[] = $submission->nanswerscorrect;
                $datesstart[] = $submission->datestart;
                $datesend[] = $submission->dateend;
                $dateanswercorrect[] = $submission->dateanswercorrect;
                $pointsmax[] = $submission->pointsmax;
                $pointsanswercorrect[] = $submission->pointsanswercorrect;
                $tinitial[] = $quest->tinitial * 86400;
                $state[] = $submission->state;
                $type = $quest->typecalification;
                $nmaxanswers = $quest->nmaxanswers;
                $pointsnmaxanswers[] = $submission->points;

                $data[] = $grade;
                $sortdata['calification'] = quest_get_points($submission, $quest, '');

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
    $columns = array('title', 'firstname', 'lastname', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');

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
        $$column = "<a href=\"submissions.php?id=$id&amp;sid=$sid&amp;tid=$team->id&amp;action=showsubmissionsteam&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "$columnicon</a>";
    }


    $table->head = array("$title", "$firstname / $lastname", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart", "$dateend", /* get_string('actions','quest'), */ "$calification");

    echo $OUTPUT->heading(get_string('showsubmissionsteam', 'quest'));
    echo html_writer::table($table);

    $grafic = $OUTPUT->pix_icon('t/clear', 'OK');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";

    echo "<script language=\"JavaScript\">\n";
    echo "var initialpoints = new Array($indice);\n";
    echo "var nanswerscorrect = new Array($indice);\n";
    echo "var datestart = new Array($indice);\n";
    echo "var dateend = new Array($indice);\n";
    echo "var dateanswercorrect = new Array($indice);\n";
    echo "var pointsmax = new Array($indice);\n";
    echo "var formularios = new Array($indice);\n";
    echo "var state = new Array($indice);\n";
    echo "var tinitial = new Array($indice);\n";
    echo "var pointsanswercorrect = new Array($indice);\n";
    echo "var incline = new Array($indice);\n";
    echo "var pointsnmaxanswers = new Array($indice);\n";



    for ($i = 0; $i < $indice; $i++) {
        echo "initialpoints[$i] = $initialpoints[$i];\n";
        echo "nanswerscorrect[$i] = $nanswerscorrect[$i];\n";
        echo "datestart[$i] = $datesstart[$i];\n";
        echo "dateend[$i] = $datesend[$i];\n";
        echo "dateanswercorrect[$i] = $dateanswercorrect[$i];\n";
        echo "pointsmax[$i] = $pointsmax[$i];\n";
        echo "state[$i] = $state[$i];\n";
        echo "tinitial[$i] = $tinitial[$i];\n";
        echo "pointsanswercorrect[$i] = $pointsanswercorrect[$i];\n";
        echo "formularios[$i] = document.forms.puntos$i.calificacion;\n";
        echo "incline[$i] = 0;\n";
        echo "pointsnmaxanswers[$i] = $pointsnmaxanswers[$i];\n";
    }
    echo "var indice = $indice;\n";
    echo "var type = $type;\n";
    echo "var nmaxanswers = $nmaxanswers;\n";

    echo "puntuacion(indice,incline,pointsmax,initialpoints,tinitial,datestart,state,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers);\n";

    echo "</script>\n";

    echo $OUTPUT->continue_button("submissions.php?action=showsubmission&sid=$submission->id&id=$cm->id");
}
////////////////////////////////////////////////////////////
else if ($action == "showanswersteam") {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer($course);
        exit;
    }

    if (!$team = $DB->get_record("quest_teams", array('id' => required_param('tid', PARAM_INT)))) {
        error('Team id is incorrect');
    }

    $userstemp = array();
    foreach ($users as $user) {
        if ($calification_user = $DB->get_record("quest_calification_users", array("questid" => $quest->id, "userid" => $user->id))) {

            if ($calification_user->teamid == $team->id) {
                $userstemp[] = $user;
            }
        }
    }
    $users = $userstemp;


    $title = get_string('showanswers', 'quest');
    if ($ismanager) {
        $title .= ' ' . get_string('of', 'quest') . ' ' . get_string('team', 'quest') . ': ' . $team->name;
    }
    echo $OUTPUT->heading($title);

    // Now prepare table with student assessments and submissions
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();

    foreach ($users as $user) {

        // skip if student not in group

        if ($answers = quest_get_answers($quest, $user)) {
            foreach ($answers as $answer) {
                $data = array();
                $sortdata = array();

                $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid),'*',MUST_EXIST);

                $data[] = quest_print_answer_title($quest, $answer, $submission) .
                        " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id\">" .
                        $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest'))
                        . '</a>' .
                        " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;aid=$answer->id\">" .
                        $OUTPUT->pix_icon('/t/delete', get_string('delete', 'quest'))
                        . '</a>';

                $sortdata['title'] = strtolower($answer->title);

                if ($ismanager) {
                    $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
                            fullname($user) . '</a>';
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
                if ($answer->pointsmax == 0)
                    $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
                else
                    $grade = number_format(quest_answer_grade($quest, $answer, 'ALL'), 4) . ' [max ' . number_format($answer->pointsmax,
                                    4) . ']';
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
        $$column = "<a href=\"submissions.php?id=$cm->id&amp;sid=$sid&amp;tid=$team->id&amp;action=showanswersteam&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    $table->head = array("$title", "$firstname / $lastname", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification");
    echo html_writer::table($table);
    echo $OUTPUT->continue_button("submissions.php?action=showsubmission&sid=$submission->id&id=$cm->id");
} else if ($action == "preview") {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();
    $form = data_submitted();

    echo "<hr size=\"1\" noshade=\"noshade\" />";

    print_heading_with_help(get_string('windowpreviewsubmission', 'quest'), "windowpreviewsubmission", "quest");

    $title = $form->title;
    echo "<center><b>" . get_string('title', 'quest') . ": " . $title . "</b></center><br>";
    echo "<center><b>" . get_string('description', 'quest') . "</b></center>";
    // print upload form
    $submission->title = $form->title;
    $temp = '\\';
    $temp1 = $temp . $temp;
    $submission->description = str_replace($temp1, $temp, $form->description);

    echo $OUTPUT->box(format_text($submission->description), 'center');

    close_window_button();

    print_footer($course);
    exit;
} else if ($action == "recalificationall") {

    $submission = $DB->get_record("quest_submissions", array("id" => $sid),'*',MUST_EXIST);
    quest_recalification_all($submission, $quest, $course);
    redirect("submissions.php?id=$id&amp;sid=$sid&amp;action=showsubmission");
}
/* * ***********confirmar particularizar formulario para desafios********************** */ else if ($action == "confirmchangeform") {
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();
    echo "<br><br>";
    echo $OUTPUT->confirm(get_string("doyouwantparticularform", "quest"),
            "assessments.php?id=$cm->id&amp;sid=$sid&amp;newform=1&amp;change_form=0&amp;action=editelements",
            "submissions.php?id=$cm->id&amp;sid=$sid&amp;action=showsubmission");
} else {

    print_error("Fatal Error: Unknown Action", 'quest', null, $action);
}



echo $OUTPUT->footer();
?>
