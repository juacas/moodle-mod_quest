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
 *          Show the page that allow to do the assess of a answer
 *
 *          **************************************** */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$aid = required_param('aid', PARAM_INT); // Answer ID..
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_LOCALURL);
require_sesskey();
global $DB, $OUTPUT, $PAGE;

$answer = $DB->get_record('quest_answers', array('id' => $aid), '*', MUST_EXIST);
$submission = $DB->get_record('quest_submissions', array('id' => $answer->submissionid), '*', MUST_EXIST);
$quest = $DB->get_record("quest", array("id" => $submission->questid), '*', MUST_EXIST);
list($course, $cm) = quest_get_course_and_cm_from_quest($quest);
require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$cangrade = has_capability('mod/quest:grade', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassess = get_string("assess", "quest");

$strsubmission = "<a href=\"submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\">$submission->title</a>";

$url = new moodle_url('/mod/quest/assess.php',
        array('aid' => $aid, 'sid' => $submission->id, 'allowcomments' => $allowcomments, 'redirect' => $redirect,
                        'sesskey' => sesskey()));
$PAGE->set_url($url);

$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('submission', 'quest') . ': ' . $submission->title,
        new moodle_url('submissions.php', array('id' => $cm->id, 'sid' => $submission->id, 'action' => 'showsubmission')));
$PAGE->navbar->add(get_string('answername', 'quest', $answer));
echo $OUTPUT->header();

// ...there can be an assessment record , if there isn't...
if (!$assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id, "questid" => $quest->id))) {

    $now = time();
    // ...create one and set timecreated way in the future, this is reset when record is updated.
    $assessment = new stdClass();
    $assessment->questid = $quest->id;
    if ($cangrade) {
        $assessment->teacherid = $USER->id;
    } else if (($submission->userid == $USER->id) && (!$cangrade)) {
        $assessment->userid = $USER->id;
    } else {
        print_error('assess_forbidden', 'quest');
    }

    $assessment->answerid = $answer->id;
    $assessment->dateassessment = $now;
    $assessment->commentsforteacher = '';
    $assessment->commentsteacher = '';

    if (!$assessment->id = $DB->insert_record("quest_assessments", $assessment)) {
        print_error('inserterror', 'quest', null, "quest_assessments");
    }
    // ...if it's the teacher and the quest is error banded set all the elements to Yes.
    if ($cangrade and ($quest->gradingstrategy == 2)) {
        if ($DB->get_field("quest_submissions", "numelements", array("id" => $submission->id)) == 0) {
            $num = $DB->get_field("quest", "nelements", array("id" => $quest->id));
        } else {
            $num = $DB->get_field("quest_submissions", "numelements", array("id" => $submission->id));
        }
        for ($i = 0; $i < $num; $i++) {
            unset($element);
            $element->questid = $quest->id;
            $element->assessmentid = $assessment->id;
            $element->elementno = $i;
            $element->userid = $USER->id;
            $element->calification = 1;
            if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                print_error('inserterror', 'quest', null, "quest_elements_assessments");
            }
        }
        // ...now set the adjustment.
        unset($element);
        $i = $num;
        $element->questid = $quest->id;
        $element->assessmentid = $assessment->id;
        $element->elementno = $i;
        $element->userid = $USER->id;
        $element->calification = 0;
        if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
            print_error('inserterror', 'quest', null, "quest_elements_assessments");
        }
    }
}

echo $OUTPUT->heading_with_help(get_string("assessthisanswer", "quest"), "grading", "quest");

// ...show assessment and allow changes.
// ...print bottom frame with the submission.

$title = get_string('answername', 'quest', $answer);

if (has_capability('mod/quest:preview', $context)) {
    $title .= get_string('by', 'quest') . ' ' . quest_fullname($answer->userid, $course->id);
}

$title .= " " . get_string('tothechallenge', 'quest') .
         "<a name=\"sid_$submission->id\" href=\"submissions.php?" .
        "id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\">$submission->title</a>";

echo $OUTPUT->heading($title);

quest_print_answer_info($quest, $answer);
// Link to assessment elements preview.
echo "<center><b><a href=\"assessments.php?id=$cm->id&amp;action=displaygradingform\">" .
         get_string("specimenassessmentform", "quest") . "</a></b>";
echo $OUTPUT->help_icon('specimenanswer', 'quest');
echo "</center>";

echo $OUTPUT->heading(get_string('answercontent', 'quest'));
quest_print_answer($quest, $answer);
// If user has general assess privileges get next answer to evaluate.
if ($cangrade) {
    $nextanswer = quest_next_unassesed_answer($answer);
} else {
    // ... else redirect to answers list.
    $nextanswer = null;
}
if ($nextanswer !== null ) {
    $returnto = new moodle_url('assess.php', ['id' => $cm->id, 'sid' => $submission->id, 'aid' => $nextanswer->id, 'sesskey' => sesskey() ]);
} else {
    $returnto = new moodle_url('submissions.php', ['id' => $cm->id, 'sid' => $submission->id, 'action' => 'showsubmission' ]);
}
quest_print_assessment($quest, $submission->id, $assessment, true, $allowcomments, $returnto);

echo $OUTPUT->footer();