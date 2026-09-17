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
 *          ACTIONS:
 *          - displaygradingform
 *          - editelements
 *          - insertelements
 *          - updateassessment
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("scores_lib.php");

$id = required_param('id', PARAM_INT); // Course Module ID.
$action = required_param('action', PARAM_ALPHA);
global $DB, $OUTPUT, $PAGE, $questscales, $questeweights;
if (!is_array($questscales)) {
    $questscales = quest_get_default_scales();
}
if (!is_array($questeweights)) {
    $questeweights = quest_get_default_weights();
}
// Get some useful stuff...
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
$url = new moodle_url('/mod/quest/assessments_autors.php', array('action' => $action, 'id' => $id));
$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$cangrade = has_capability('mod/quest:grade', $context);
require_login($course->id, false, $cm);
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_context($context);
$PAGE->set_heading($course->fullname);

quest_check_visibility($course, $cm);
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassessments = get_string("assessments", "quest");

if ($action == 'displaygradingform') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string("specimenassessmentformsubmission", "quest"), "specimensubmission", "quest");
    if ($ismanager) {
        $editurl = new moodle_url('/mod/quest/assessments_autors.php', [
            'id' => $cm->id,
            'action' => 'editelements',
            'sesskey' => sesskey()
        ]);
        echo html_writer::div(
            html_writer::link(
                $editurl,
                '<i class="fa fa-sliders me-1" aria-hidden="true"></i> ' . get_string('amendassessmentelements', 'quest'),
                ['class' => 'btn btn-outline-primary']
            ),
            'text-end mb-3'
        );
    }
    quest_print_assessment_autor($quest);
    $id = required_param('id', PARAM_INT);
    // Called with no assessment.
    echo $OUTPUT->continue_button("view.php?id=$id");
    echo $OUTPUT->footer();
} else if ($action == 'editelements') {
    // Edit assessment elements (for teachers).
    if (!$ismanager) {
        throw new \moodle_exception('nopermissions', 'error', '', "Only teachers can look at this page");
    }
    // Set up heading, form and table.
    echo $OUTPUT->header();

    $count = $DB->count_records("quest_items_assesments_autor", array("questid" => $quest->id));
    if ($count) {
        echo $OUTPUT->notification(get_string("warningonamendingelements", "quest"));
    }

    $gradingstrategy = $quest->gradingstrategyautor == 0 ? get_string('nograde', 'quest') : get_string('accumulative', 'quest');
    $heading = get_string("editingassessmentelementsofautors", "quest") . ' (' . $gradingstrategy . ')';
    echo $OUTPUT->heading_with_help($heading, "elementsautor", "quest");

    echo '<div class="quest-assessment-container my-4">';
    echo '<form name="form" method="post" action="assessments_autors.php">';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
    echo '<input type="hidden" name="action" value="insertelements" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';

    $elements = [];
    if ($elementsraw = $DB->get_records("quest_elementsautor", array("questid" => $quest->id), "elementno ASC")) {
        foreach ($elementsraw as $element) {
            $elements[] = $element; // ...to renumber index 0,1,2...
        }
    }
    // Check for missing elements (this happens either the first time round or when the number of
    // elements is increased).
    for ($i = 0; $i < $quest->nelementsautor; $i++) {
        if (!isset($elements[$i])) {
            $elements[$i] = new stdClass();
            $elements[$i]->description = '';
            $elements[$i]->scale = 0;
            $elements[$i]->maxscore = 0;
            $elements[$i]->weight = 11;
        }
    }

    // Set up scales name.
    $scales = [];
    foreach ($questscales as $key => $scale) {
        $scales[] = $scale['name'];
    }

    for ($i = 0; $i < $quest->nelementsautor; $i++) {
        $iplus1 = $i + 1;
        echo '<div class="card shadow-sm mb-4 quest-criterion-card">';
        echo '<div class="card-header quest-criterion-header d-flex justify-content-between align-items-center py-2 px-3">';
        echo '<span class="fw-bold text-dark">';
        echo '<i class="fa fa-sliders text-primary me-2" aria-hidden="true"></i>' . get_string('element', 'quest') . " $iplus1";
        echo '</span>';
        echo '</div>';
        echo '<div class="card-body p-3">';
        echo '<div class="mb-3">';
        echo '<label class="form-label fw-semibold text-secondary">' . get_string('description', 'quest') . '</label>';
        quest_print_editor("description[$i]", "id_autor_desc_$i", $elements[$i]->description, $context, 3);
        echo '</div>';

        if ($quest->gradingstrategyautor == 1) { // Accumulative grading.
            echo '<div class="row g-3">';
            echo '<div class="col-md-6">';
            echo '<label class="form-label fw-semibold text-secondary">' . get_string('typeofscale', 'quest') . '</label>';
            echo html_writer::select($scales, "scale[]", $elements[$i]->scale, false, ['class' => 'form-select']);
            echo '</div>';
            if ($elements[$i]->weight == '') { // Not set.
                $elements[$i]->weight = 11; // ...unity.
            }
            echo '<div class="col-md-6">';
            echo '<label class="form-label fw-semibold text-secondary">' . get_string('elementweight', 'quest') . '</label>';
            echo html_writer::select($questeweights, "weight[]", $elements[$i]->weight, false, ['class' => 'form-select']);
            echo '</div>';
            echo '</div>';
        }
        echo '</div></div>';
    }

    // Sticky action bar.
    echo '<div class="quest-action-bar-sticky d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">';
    echo '<div class="d-flex gap-2">';
    echo '<button type="submit" class="btn btn-primary px-4"><i class="fa fa-floppy-o me-1" aria-hidden="true"></i>' . get_string('savechanges') . '</button>';
    echo '<button type="submit" name="cancel" value="1" class="btn btn-outline-secondary px-3"><i class="fa fa-times me-1" aria-hidden="true"></i>' . get_string('cancel') . '</button>';
    echo '</div>';
    echo '</div>';

    echo '</form>';
    echo '</div>'; // .quest-assessment-container
    echo $OUTPUT->footer();

} else if ($action == 'insertelements') {
    // Insert/update assignment elements (for teachers).
    if (!$ismanager) {
        throw new \moodle_exception('nopermissions', 'error', '', "Only teachers can look at this page");
    }
    require_sesskey();
    if (optional_param('cancel', null, PARAM_ALPHA)) {
        redirect("view.php?id=$cm->id");
    }
    $descriptions = required_param_array('description', PARAM_RAW);
    $weights = optional_param_array('weight', null, PARAM_INT);
    $scales = optional_param_array('scale', null, PARAM_INT);
    // Let's not fool around here, dump the junk!
    $DB->delete_records("quest_elementsautor", array("questid" => $quest->id));
    // Determine wich type of grading.
    switch ($quest->gradingstrategyautor) {
        case 0: // ...no grading insert all the elements that contain something.
            foreach ($descriptions as $key => $description) {
                if ($description) {
                    $element = new stdClass();
                    $element->description = $description;
                    $element->questid = $quest->id;
                    $element->elementno = $key;
                    if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                        throw new \moodle_exception('inserterror', 'quest', '', "quest_elementsautor");
                    }
                }
            }
            break;
        case 1: // Accumulative grading.
                // Insert all the elements that contain something.
            foreach ($descriptions as $key => $description) {
                if ($description) {
                    $element = new stdClass();
                    $element->description = $description;
                    $element->questid = $quest->id;
                    $element->elementno = $key;
                    if (isset($scales[$key])) {
                        $element->scale = $scales[$key];
                        switch ($questscales[$scales[$key]]['type']) {
                            case 'radio':
                                $element->maxscore = $questscales[$scales[$key]]['size'] - 1;
                                break;
                            case 'selection':
                                $element->maxscore = $questscales[$scales[$key]]['size'];
                                break;
                        }
                    }
                    if (isset($weights[$key])) {
                        $element->weight = $weights[$key];
                    }
                    if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                        throw new \moodle_exception('inserterror', 'quest', '', "quest_elementsautor");
                    }
                }
            }
            break;
        default:
            throw new InvalidArgumentException('Unknown grading strategy.');
    } // end of switch
    redirect("view.php?id=$cm->id", get_string("savedok", "quest"));
} else if ($action == 'updateassessment') {
    // Update assessment (by teacher or student).
    $message = '';

    $aid = required_param('aid', PARAM_INT);
    $assessment = $DB->get_record("quest_assessments_autors", array("id" => $aid), '*', MUST_EXIST);
    $submission = $DB->get_record("quest_submissions", array("id" => $assessment->submissionid), '*', MUST_EXIST);
    // First get the assignment elements for maxscores and weights...
    $elementsraw = $DB->get_records("quest_elementsautor", array("questid" => $quest->id), "elementno ASC");

    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            $elements[] = $element; // ...to renumber index 0,1,2...
        }
    } else {
        $elements = null;
    }
    $timenow = time();
    $manualgrade = optional_param('manualcalification', null, PARAM_ALPHANUM);
    $points = $submission->initialpoints;
    if ($manualgrade != null) {
        $percent = ((int) $manualgrade) / 100;
        $grade = $points * $percent;
        $message .= "Grading manually! $points * $percent = $grade";
    } else { // Form grading
             // don't fiddle about, delete all the old and add the new!
        $DB->delete_records("quest_items_assesments_autor", array("assessmentautorid" => $assessment->id));
        $numelements = count($elementsraw);
        // Determine what kind of grading we have.
        switch ($quest->gradingstrategyautor) {
            case 0: // No grading.
                    // Insert all the elements that contain something.
                for ($i = 0; $i < $numelements; $i++) {
                    $element = new stdClass();
                    $element->questid = $quest->id;
                    $element->assessmentautorid = $assessment->id;
                    $element->elementno = $i;
                    $feedb = optional_param_array("feedback", null, PARAM_TEXT);
                    $element->answer = $feedb == null ? '':$feedb[$i];
                    $element->commentteacher = optional_param('generalcomment', null, PARAM_TEXT);
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        throw new \moodle_exception('inserterror', 'quest', '', "quest_items_assesments_autor");
                    }
                }
                $grade = $assessment->points; // Set to satisfy save to db.
                break;
            case 1: // Accumulative grading.
                    // Insert all the elements that contain something.
                $grades = optional_param_array('grade', [], PARAM_FLOAT);
                foreach ($grades as $key => $thegrade) {
                    $element = new stdClass();
                    $element->questid = $quest->id;
                    $element->userid = $USER->id;
                    $element->assessmentautorid = $assessment->id;
                    $element->elementno = $key;
                    $feedb = optional_param_array("feedback", null, PARAM_TEXT);
                    $element->answer = $feedb == null ? '':$feedb[$key];
                    $element->calification = $thegrade;
                    $element->commentteacher = optional_param('generalcomment', null, PARAM_TEXT);
                                                                      // TODO: EVP CHECK THIS... DATA BASE
                                                                      // CONTAINS THIS FIELD BUT I
                                                                      // do not find it in the form and
                                                                      // I have included this to
                                                                      // avoid errors. I think this
                                                                      // field is no longer used.
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        throw new \moodle_exception('inserterror', 'quest', '', "quest_items_assesments_autor");
                    }
                }
                // Now work out the grade...
                $rawgrade = 0;
                $totalweight = 0;
                foreach ($grades as $key => $grade) {
                    $maxscore = $elements[$key]->maxscore;
                    $weight = $questeweights[$elements[$key]->weight];
                    if ($weight > 0) {
                        $totalweight += $weight;
                    }
                    $rawgrade += ($grade / $maxscore) * $weight;
                }
                // If there is no defined, positive weights just use rawgrade.
                if ($totalweight == 0) {
                    $totalweight = 1;
                }
                $grade = $points * ($rawgrade / $totalweight);
                break;
            default:
                throw new InvalidArgumentException('Unknown grading strategy.');
        }
    }

    $assessment->state = ASSESSMENT_STATE_BY_AUTOR;
    $assessment->points = $grade;
    $assessment->dateassessment = $timenow;
    $submission->evaluated = 1;
    // Any comment?
    $generalcomment = optional_param('generalcomment', null, PARAM_TEXT);
    if (!empty($generalcomment)) {
        $assessment->commentsteacher = $generalcomment;
    }
    $generalteachercomment = optional_param('generalteachercomment', null, PARAM_TEXT);
    if (!empty($generalteachercomment)) {
        $assessment->commentsforteacher = $generalteachercomment;
    }
    quest_update_submission($submission); // Weird bug with number precission and decimal point in
                                          // Moodle 2.5+.
    quest_update_assessment_author($assessment); // Weird bug with number precission and decimal
                                                 // point in Moodle 2.5+.
    quest_update_submission_counts($submission->id);
    // Recalculate points and report to gradebook.
    quest_grade_updated($quest, $submission->userid);

    if ($cangrade) {
        if ($user = get_complete_user_data('id', $submission->userid)) {
            quest_send_message($user, "viewassessmentautor.php?aid=$aid", 'assessmentautor', $quest, $submission);
        }
    }
    // Log the event.
    \mod_quest\event\challenge_assessed::create_from_parts($submission, $assessment, $cm)->trigger();
    $returnto = optional_param('returnto', "view.php?id=$cm->id", PARAM_RAW);
    // ...show grade if grading strategy is not zero.
    if ($quest->gradingstrategyautor) {
        if (count($elementsraw) < $quest->nelementsautor) {
            echo $OUTPUT->notification(get_string("noteonassessmentelements", "quest"));
        }
        $message .= get_string("thegradeis", "quest") . ": " . number_format($grade, 4) . " (" .
                 get_string("initialpoints", 'quest') . " " . number_format($points, 2) . ")";
    } else {
        $message .= get_string("thegradeis", "quest") . ": " . number_format($grade, 4) . " (Activity ignores this grading.)";
    }
    echo $OUTPUT->header();
    echo $OUTPUT->notification($message, 'info');
    echo $OUTPUT->continue_button($returnto);
    echo $OUTPUT->footer();

} else {
    throw new \moodle_exception('unknownactionerror', 'quest', '', $action);
}