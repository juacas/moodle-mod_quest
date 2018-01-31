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
 * @package mod_quest */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT); // CM ID.

global $DB;
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);
$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

// Filter access to guest user.
$context = context_module::instance($cm->id);
if (!has_capability('moodle/legacy:admin', $context) && has_capability('moodle/legacy:guest', $context)) {
    print_error('nopermissions', 'error', null, 'You are not enrolled in this course!!');
}

$strquests = get_string('modulenameplural', 'quest');
$strquest = get_string('modulename', 'quest');
$stranswer = get_string('answer', 'quest');
$groupmode = $currentgroup = false; // JPC group support desactivation.

print_header_simple(format_string($quest->name) . " : $stranswer", "",
        "<a href=\"index.php?id=$course->id\">$strquests</a> -> <a href=\"view.php?a=$quest->id\">" . format_string($quest->name, true) . "</a> -> $stranswer", "", "", true);
$timenow = time();

$form = data_submitted("nomatch"); // POST may come from two forms.

$submission = $DB->get_record("quest_submissions", "id", $form->sid);

if ($form->save == 'SaveAnswer') {
    // Don't be picky about not having a title.

    if (!$title = $form->title) {
        $title = get_string("notitle", "quest");
    }
    if (!$validate = quest_validate_user_answer($quest, $submission)) {
        error(get_string('answerexisty', 'quest'), "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission");
    }

    // Add new answer record.
    $newanswer->questid = $quest->id;
    $newanswer->userid = $USER->id;
    $newanswer->submissionid = $submission->id;
    $newanswer->title = $title;
    $newanswer->description = trim($form->description);
    $newanswer->date = $timenow;

    $points = quest_get_points($submission, $quest, $newanswer);
    $newanswer->pointsmax = $points;
    $newanswer->phase = 0;
    $newanswer->state = 1;
    $submission->nanswers++;

    $newanswer->perceiveddifficulty = $form->perceiveddifficulty;

    $DB->set_field("quest_submissions", "nanswers", $submission->nanswers, "id", $submission->id);

    if (!$newanswer->id = $DB->insert_record("quest_answers", $newanswer)) {
        error("Quest submission: Failure to create new submission record!");
    }

    if ($quest->nattachments) {
        require_once($CFG->dirroot . '/lib/uploadlib.php');
        $um = new upload_manager(null, false, false, $course, false, $quest->maxbytes);
        $dir = quest_file_area_name_answers($quest, $newanswer);

        if ($um->process_file_uploads($dir)) {
            add_to_log($course->id, "quest", "newattachment",
                    "answer.php?sid=$submission->id&amp;aid=$newanswer->id&amp;action=showanswer", "$newanswer->id", "$cm->id");
            print_heading(get_string("uploadsuccess", "quest"));
            // ...um will take care of printing errors.
        } else {
            print_heading(get_string('upload'));
            notify(get_string('uploaderror', 'quest'));
            echo $um->get_errors();

            $errorreturnurl = "answer.php?sid=$submission->id&amp;aid=$newanswer->id&amp;action=modif";
            $CFG->framename = "top";
            echo $OUTPUT->continue_button($errorreturnurl);
            print_footer($course);
            die();
        }
    } else {
        print_heading(get_string("submittedanswer", "quest") . " " . get_string("ok"));
    }

    // Update scores and statistics.
    quest_update_submission_counts($answer->submissionid);

    // Update current User scores.
    quest_update_user_scores($quest, $newanswer->userid);
    // Update answer current team totals.
    if ($quest->allowteams) {
        quest_update_team_scores($quest->id, quest_get_user_team($quest->id, $newanswer->userid));
    }
    // NOTIFICATIONS.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        print_heading(get_string("nostudentsyet"));
        print_footer($course);
        exit();
    }
    // JPC 2013-11-28 disable excesive notifications...
    if (false) {
        foreach ($users as $user) {
            if ($ismanager) {
                quest_send_message($user, "answer.php?sid=$sid&amp;aid=$newanswer->id&amp;action=showanswer", 'answeradd', $quest,
                        $submission, $newanswer);
            }
        }
    }
    // Challenge author is always notified...
    $user = get_complete_user_data('id', $submission->userid);
    quest_send_message($user, "answer.php?sid=$sid&amp;aid=$newanswer->id&amp;action=showanswer", 'answeradd', $quest, $submission,
            $newanswer);

    add_to_log($course->id, "quest", "submit_answer", "answer.php?sid=$submission->id&amp;aid=$newanswer->id&amp;action=showanswer",
            "$newanswer->id", "$cm->id");
    echo $OUTPUT->continue_button("submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission");
    print_footer($course);
} else if ($form->save1 == "PreviewAnswer") {

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
}