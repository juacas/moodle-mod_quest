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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Questournament for Moodle. If not, see <http://www.gnu.org/licenses/>.

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
 *          ************************************* */
require ("../../config.php");
require ("lib.php");
require ("locallib.php");

$id = required_param('id', PARAM_INT); // CM ID
global $DB;
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);
$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$canaddchallenge = has_capability('mod/quest:addchallenge', $context);

// Filter access to guest user.
if (!has_capability('moodle/legacy:admin', $context) && has_capability('moodle/legacy:guest', $context)) {
    print_error('nopermissions', 'error', null, 'You are not enrolled in this course!!');
}

$strquests = get_string('modulenameplural', 'quest');
$strquest = get_string('modulename', 'quest');
$strsubmission = get_string('submission', 'quest');
$action = 'upload';
$straction = ($action) ? '-> ' . get_string($action, 'quest') : '';

$changegroup = isset($_GET['group']) ? $_GET['group'] : -1; // Group change requested?
$groupmode = groups_get_activity_group($cm); // Groups are being used?
$currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);
$groupmode = $currentgroup = false; // JPC group support desactivation

$url = new moodle_url('/mod/quest/upload.php', array('id' => $id));

$PAGE->set_url($url);
$PAGE->navbar->add($strquests, "index.php?id=$course->id");
$PAGE->navbar->add($strsubmission, "view.php?id=$quest->id");
$PAGE->set_title($strquests);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$timenow = time();

$form = data_submitted("nomatch"); // POST may come from two forms...

if (isset($form->operation)) {
    if ($form->operation == get_string("approve", "quest")) {

        if (!$submission = $DB->get_record("quest_submissions", array("id" => $form->sid))) {
            error("Submission id is incorrect");
        }

        $newsubmission->id = $form->sid;
        $newsubmission->questid = $quest->id;
        $newsubmission->title = $form->title;

        $newsubmission->description = trim($form->description);

        $newsubmission->datestart = make_timestamp($form->submissionstartyear, $form->submissionstartmonth,
                $form->submissionstartday, $form->submissionstarthour, $form->submissionstartminute);

        $newsubmission->dateend = make_timestamp($form->submissionendyear, $form->submissionendmonth, $form->submissionendday,
                $form->submissionendhour, $form->submissionendminute);

        $newsubmission->initialpoints = $form->initialpoints;
        $newsubmission->pointsmax = $form->pointsmax;
        $newsubmission->tinitial = $quest->tinitial;

        if ($newsubmission->dateend > $quest->dateend) {
            $newsubmission->dateend = $quest->dateend;
        }
        if ($newsubmission->initialpoints > $newsubmission->pointsmax) {
            $newsubmission->initialpoints = $newsubmission->pointsmax;
        }

        if (!quest_check_submission_dates($newsubmission, $quest)) {
            error(get_string('invaliddates', 'quest'), "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=approve");
        }
        if (!quest_check_submission_text($newsubmission)) {
            error(get_string('invalidtext', 'quest'), "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=approve");
        }

        $newsubmission->comentteacherautor = $form->comentteacherautor;
        $newsubmission->comentteacherpupil = $form->comentteacherpupil;
        $newsubmission->state = 2;

        if (!isteacher($course->id, $submission->userid) &&
                 ($groupmember = $DB->get_record("groups_members", "userid", $submission->userid))) {
            $idgroup = $groupmember->groupid;
        } else {
            $idgroup = 0;
        }
        if ($ismanager) {
            $newsubmission->perceiveddifficulty = $form->perceiveddifficulty;
            $newsubmission->predictedduration = $form->predictedduration;
        }

        if ($DB->update_record("quest_submissions", $newsubmission)) {
            if ($newsubmission->datestart <= time()) {

                $event = null;
                $event->name = get_string('datestartsubmissionevent', 'quest', $newsubmission->title);
                $event->description = "<a href=\"{$CFG->wwwroot}/mod/quest/submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission\">" .
                         $newsubmission->title . "</a>";
                $event->courseid = $quest->course;
                $event->groupid = $idgroup;
                $event->userid = 0;
                $event->modulename = '';
                $event->instance = $quest->id;
                $event->eventtype = 'datestartsubmission';
                $event->timestart = $newsubmission->datestart;
                $event->timeduration = 0;
                add_event($event);

                $event->name = get_string('dateendsubmissionevent', 'quest', $newsubmission->title);
                $event->eventtype = 'dateendsubmission';
                $event->timestart = $newsubmission->dateend;
                add_event($event);
            }
        }

        if ($ismanager) {
            if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                print_heading(get_string("nostudentsyet"));
                print_footer($course);
                exit();
            }
            if ($submissiongroup = $DB->get_record("groups_members", array("userid" => $submission->userid))) {
                $currentgroup = $submissiongroup->groupid;
            }
        }

        add_to_log($course->id, "quest", "approve_submission",
                "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id", "$cm->id");
        print_heading(get_string("submitted", "quest") . " " . get_string("ok"));
        echo $OUTPUT->continue_button("view.php?id=$cm->id");
        print_footer($course);
    } else if ($form->operation == get_string("save", "quest")) {
        // add new submission record
        $newsubmission->id = $form->sid;
        $newsubmission->questid = $quest->id;

        $newsubmission->title = $form->title;
        $newsubmission->description = trim($form->description);

        $newsubmission->datestart = make_timestamp($form->submissionstartyear, $form->submissionstartmonth,
                $form->submissionstartday, $form->submissionstarthour, $form->submissionstartminute);

        $newsubmission->dateend = make_timestamp($form->submissionendyear, $form->submissionendmonth, $form->submissionendday,
                $form->submissionendhour, $form->submissionendminute);

        $newsubmission->pointsmax = $form->pointsmax;
        $newsubmission->initialpoints = $form->initialpoints;
        $newsubmission->tinitial = $quest->tinitial;

        $newsubmission->comentteacherautor = $form->comentteacherautor;
        $newsubmission->comentteacherpupil = $form->comentteacherpupil;

        if ($newsubmission->dateend > $quest->dateend) {
            $newsubmission->dateend = $quest->dateend;
        }
        if ($newsubmission->initialpoints > $newsubmission->pointsmax) {
            $newsubmission->initialpoints = $newsubmission->pointsmax;
        }

        if (!quest_check_submission_dates($newsubmission, $quest)) {
            error(get_string('invaliddates', 'quest'), "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=approve");
        }
        if (!quest_check_submission_text($newsubmission)) {
            error(get_string('invalidtext', 'quest'), "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=approve");
        }
        if ($ismanager) {
            $newsubmission->perceiveddifficulty = $form->perceiveddifficulty;
            $newsubmission->predictedduration = $form->predictedduration;
        }
        $DB->update_record("quest_submissions", $newsubmission);

        if ($submission = $DB->get_record("quest_submissions", array("id" => $newsubmission->id))) {
            $user = get_complete_user_data('id', $submission->userid);

            quest_send_message($user, "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", 'save',
                    $quest, $newsubmission, '');

            add_to_log($course->id, "quest", "save_submission",
                    "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id",
                    "$cm->id");
        }
        echo $OUTPUT->continue_button("view.php?id=$cm->id");
        print_footer($course);
    } else if ($form->operation == get_string("delete", "quest")) {

        if (!$submission = $DB->get_record("quest_submissions", array("id" => $form->sid))) {
            error("Submission id is incorrect");
        }

        // students are only allowed to delete their own submission and only up to the deadline
        if (!($ismanager or (($USER->id == $submission->userid) and ($timenow < $quest->dateend) and ($submission->nanswers == 0) and
                 ($timenow < $submission->dateend)))) {
            error("You are not authorized to delete this submission");
        }

        print_string("deleting", "quest");
        if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id))) {
            foreach ($answers as $answer) {
                // first get any assessments...
                if ($assessments = quest_get_assessments($answer, 'ALL')) {
                    foreach ($assessments as $assessment) {
                        // ...and all the associated records...
                        $DB->delete_records("quest_elements_assessments", "assessmentid", $assessment->id);

                        echo ".";
                    }
                    // ...now delete the assessments...
                    $DB->delete_records("quest_assessments", array("submissionid" => $submission->id));
                }
                $DB->delete_records("quest_answers", array("id" => $answer->id));
            }
        }

        $DB->delete_records('event',
                array('modulename' => 'quest', 'instance' => $quest->id, 'description' => $submission->description));
        // ...and the submission record...
        $DB->delete_records("quest_submissions", array("id" => $submission->id));
        // ..and finally the submitted file

        quest_delete_submitted_files_submissions($quest, $submission);

        $user = get_complete_user_data('id', $submission->userid);
        if (!$user) {
            quest_send_message($user, "view.php?id=$cm->id", 'deletesubmission', $quest, $submission, '');
        }
        add_to_log($course->id, "quest", "delete_submission", "view.php?id=$cm->id", "$quest->id", "$cm->id");
        redirect("view.php?id=$cm->id");
    }
} else {
    if ($form->save == "submitassignment") {
        // don't be picky about not having a title

        if (!$title = $form->title) {
            $title = get_string("notitle", "quest");
        }

        // check that this is not a "rapid" second submission, caused by using the back button
        // only check if a student, teachers may want to submit a set of quest examples rapidly
        if (isstudent($course->id)) {
            if ($submissions = quest_get_user_submissions($quest, $USER)) {
                // returns all submissions, newest on first
                foreach ($submissions as $submission) {

                    if ($submission->timecreated > $timenow) {
                        // ignore this new submission
                        redirect("view.php?id=$cm->id");
                        print_footer($course);
                        exit();
                    }
                }
            }
        }

        // get the current set of submissions
        $submissions = quest_get_user_submissions($quest, $USER);

        // add new submission record
        $newsubmission->questid = $quest->id;
        $newsubmission->userid = $USER->id;
        $newsubmission->title = $title;
        $newsubmission->description = trim($form->description);
        $newsubmission->timecreated = $timenow;
        if ($ismanager || has_capability('quest:addchallenge', $context)) {
            $newsubmission->comentteacherpupil = $form->comentteacherpupil;

            if (isset($form->perceiveddifficulty)) {
                $newsubmission->perceiveddifficulty = $form->perceiveddifficulty;
            } else {
                $newsubmission->perceiveddifficulty = -1;
            }
            $newsubmission->predictedduration = $form->predictedduration;
        }
        if ($ismanager || has_capability('quest:editchallengeall', $context)) {
            $newsubmission->datestart = make_timestamp($form->submissionstartyear, $form->submissionstartmonth,
                    $form->submissionstartday, $form->submissionstarthour, $form->submissionstartminute);

            $newsubmission->dateend = make_timestamp($form->submissionendyear, $form->submissionendmonth, $form->submissionendday,
                    $form->submissionendhour, $form->submissionendminute);
        } else {
            $newsubmission->datestart = $form->datestart;
            $newsubmission->dateend = $form->dateend;
        }

        $newsubmission->initialpoints = $form->initialpoints;
        $newsubmission->pointsmax = $form->pointsmax;
        $newsubmission->tinitial = $quest->tinitial;

        if ($ismanager || has_capability('quest:approvechallenge', $context)) {
            $newsubmission->state = 2;
        } else {
            $newsubmission->state = 1; // approval pending
        }

        if ($newsubmission->dateend > $quest->dateend) {
            $newsubmission->dateend = $quest->dateend;
        }
        if ($newsubmission->initialpoints > $newsubmission->pointsmax) {
            $newsubmission->initialpoints = $newsubmission->pointsmax;
        }

        if (!quest_check_submission_dates($newsubmission, $quest)) {
            error(get_string('invaliddates', 'quest'), "view.php?id=$cm->id&amp;action=submitchallenge");
        }
        if (!quest_check_submission_text($newsubmission)) {
            error(get_string('invalidtext', 'quest'), "view.php?id=$cm->id&amp;action=submitchallenge");
        }

        if (!$newsubmission->id = $DB->insert_record("quest_submissions", $newsubmission)) {
            error("Quest submission: Failure to create new submission record!");
        }

        if ($calificationuser = $DB->get_record("quest_calification_users", array("userid" => $USER->id, "questid" => $quest->id))) {
            $calificationuser->nsubmissions++;
            $DB->set_field("quest_calification_users", "nsubmissions", $calificationuser->nsubmissions,
                    array("id" => $calificationuser->id));
        }

        if ($quest->allowteams) {
            if ($calificationteam = $DB->get_record("quest_calification_teams",
                    array("teamid" => $calificationuser->teamid, "questid" => $quest->id))) {
                $calificationteam->nsubmissions++;
                $DB->set_field("quest_calification_teams", "nsubmissions", $calificationteam->nsubmissions,
                        array("id" => $calificationteam->id));
            }
        }

        if ($ismanager || has_capability('quest:approvechallenge', $context)) {
            // TODO: Check. This avoids lo log a challenge
            // if started retrospectively
            if ($newsubmission->datestart <= time()) {

                $event = null;
                $event->name = get_string('datestartsubmissionevent', 'quest', $newsubmission->title);
                $event->description = "<a href=\"{$CFG->wwwroot}/mod/quest/submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission\">" .
                         $newsubmission->title . "</a>";
                $event->courseid = $quest->course;
                $event->groupid = 0;
                $event->userid = 0;
                $event->modulename = '';
                $event->instance = $quest->id;
                $event->eventtype = 'datestartsubmission';
                $event->timestart = $newsubmission->datestart;
                $event->timeduration = 0;
                add_event($event);

                $event->name = get_string('dateendsubmissionevent', 'quest', $newsubmission->title);
                $event->eventtype = 'dateendsubmission';
                $event->timestart = $newsubmission->dateend;
                add_event($event);
            }
        }

        // do something about the attachments, if there are any
        if ($quest->nattachments) {
            require_once ($CFG->dirroot . '/lib/uploadlib.php');
            $um = new upload_manager(null, false, false, $course, false, $quest->maxbytes);
            if ($um->preprocess_files()) {
                $dir = quest_file_area_name_submissions($quest, $newsubmission);
                if ($um->save_files($dir)) {
                    add_to_log($course->id, "quest", "newattachment",
                            "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id",
                            "$cm->id");
                    print_heading(get_string("uploadsuccess", "quest"));
                }
                // um will take care of printing errors.
            }
        }

        print_heading(get_string("submitted", "quest") . " " . get_string("ok"));

        if (!$ismanager) {

            if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname, u.secret")) {
                print_heading(get_string("nostudentsyet"));
                print_footer($course);
                exit();
            }
            foreach ($users as $user) {
                if (!has_capability('mod/quest:manage', $context, $user)) {
                    continue;
                }
                quest_send_message($user, "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission",
                        'addsubmission', $quest, $newsubmission, '');
            }
        }

        add_to_log($course->id, "quest", "submit_submissi",
                "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id", "$cm->id");

        echo $OUTPUT->continue_button("view.php?id=$cm->id");
        print_footer($course);
    } else if (isset($form->save1) && $form->save1 == "Preview") {

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

        print_simple_box(format_text($submission->description), 'center');

        close_window_button();

        print_footer($course);
        exit();
    }
}

