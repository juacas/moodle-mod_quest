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
 * Module developed at the University of Valladolid.
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License.
 * @package quest
 */
// This page prints a long report of this QUEST.
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$cmid = required_param('id', PARAM_INT); // Course Module ID.

global $DB, $PAGE, $OUTPUT;
$timenow = time();
list($course, $cm) = get_course_and_cm_from_cmid($cmid, "quest");
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
// Print the page header and check login.
require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quest:downloadlogs', $context);

if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $context)) {
    print_error("modulehiddenerror.", 'quest', "view.php?id=$cmid");
}

$url = new moodle_url('/mod/quest/report.php', array('id' => $cmid));
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");

// Log..

if ($CFG->version >= 2014051200) {
    require_once( 'classes/event/quest_viewed.php');
    \mod_quest\event\briefting_viewed::create_from_parts($USER, $quest, $cm)->trigger();
} else {
    add_to_log($course->id, "quest", "report", "report.php?id=$cm->id", "$quest->id", "$cm->id");
}

echo $OUTPUT->header();
quest_print_quest_heading($quest);
echo $OUTPUT->box(format_module_intro('quest', $quest, $cm->id));

echo '<br/>';
// ...iterate through submissions..

if ($submissions = quest_get_submissions($quest)) {
    foreach ($submissions as $submission) {
        echo $OUTPUT->heading("Challenge: " . $submission->title, 1);
        echo $OUTPUT->box_start();
        // Output a submission.
        $user = get_complete_user_data('id', $submission->userid);
        echo "<b>Author:</b>";
        if ($user) {
            // User Name Surname.
            echo $OUTPUT->user_picture($user);
            echo "<a name=\"userid->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
                     fullname($user) . '</a>';
        } else {
            echo "Unknown ($submission->userid)";
        }
        echo '</td><td width="100%">';
        echo '<table border="0"><tr><td>';
        quest_print_submission_info($quest, $submission);
        echo '</td><td>';
        /*
         * INCRUSTA GRÁFICO DE EVOLUCION DE PUNTOS
         */
        quest_print_score_graph($quest, $submission);
        echo '</td></tr></table>';

        echo $OUTPUT->heading($submission->title);
        /*
         * Wording of the challenge
         */
        quest_print_submission($quest, $submission);
        echo $OUTPUT->box_end();
        echo '<br/>';
        // ...list answers..
        $sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
        $dir = optional_param('dir', "ASC", PARAM_ALPHA);
        if ($answers = quest_get_submission_answers($submission)) {
            echo $OUTPUT->heading("Answers of challenge: $submission->title", 2);
            echo $OUTPUT->box_start();
            quest_print_table_answers($quest, $submission, $course, $cm, $sort, $dir);
            echo '<br/>';
            foreach ($answers as $answer) {

                $user = get_complete_user_data('id', $answer->userid);
                echo '<table border="0"><tr><td>';

                // User Name Surname..
                echo $OUTPUT->user_picture($user);
                echo "<a name=\"userid->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
                         fullname($user) . '</a>';
                echo '</td><td width="100%">';

                echo $OUTPUT->heading("Answer: " . $answer->title, 3);
                quest_print_answer_info($quest, $answer);

                quest_print_answer($quest, $answer);
                echo '</td></tr></table>';
            }
            echo $OUTPUT->box_end();
        }

        echo '<br/>';
    }
}