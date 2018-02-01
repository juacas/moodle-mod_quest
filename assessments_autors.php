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
global $DB, $OUTPUT, $PAGE;
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
    quest_print_assessment_autor($quest);
    $id = required_param('id', PARAM_INT);
    // Called with no assessment.
    echo $OUTPUT->continue_button("view.php?id=$id");
} else if ($action == 'editelements') {
    // Edit assessment elements (for teachers).
    if (!$ismanager) {
        print_error('nopermissions', 'error', null, "Only teachers can look at this page");
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

    echo '<form name="form" method="post" action="assessments_autors.php">';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" /> <input type="hidden" name="action" value="insertelements" />';
    echo '<table align="center" border="1">';
    // Get existing elements, if none set up appropriate default ones.
    if ($elementsraw = $DB->get_records("quest_elementsautor", array("questid" => $quest->id), "elementno ASC")) {
        foreach ($elementsraw as $element) {
            $elements[] = $element; // ...to renumber index 0,1,2...
        }
    }
    // Check for missing elements (this happens either the first time round or when the number of
    // elements is icreased).
    for ($i = 0; $i < $quest->nelementsautor; $i++) {
        if (!isset($elements[$i])) {
            $elements[$i] = new stdClass();
            $elements[$i]->description = '';
            $elements[$i]->scale = 0;
            $elements[$i]->maxscore = 0;
            $elements[$i]->weight = 11;
        }
    }
    switch ($quest->gradingstrategyautor) {
        case 0: // ...no grading.
            for ($i = 0; $i < $quest->nelementsautor; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                echo "<td><textarea name=\"description[]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                echo "  </td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            break;

        case 1: // Accumulative grading.
                // Set up scales name.
            foreach ($questscales as $key => $scale) {
                $scales[] = $scale['name'];
            }
            for ($i = 0; $i < $quest->nelementsautor; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                echo "<td><textarea name=\"description[]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                echo "  </td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><b>" . get_string("typeofscale", "quest") . ":</b></td>\n";
                echo "<td valign=\"top\">\n";
                echo html_writer::select($scales, "scale[]", $elements[$i]->scale);
                if ($elements[$i]->weight == '') { // Not set.
                    $elements[$i]->weight = 11; // ...unity.
                }
                echo "</td></tr>\n";
                echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
                quest_choose_from_menu($questeweights, "weight[]", $elements[$i]->weight, "");
                echo "      </td>\n";
                echo "</tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            break;
        case 2: // Error banded grading.
            for ($i = 0; $i < $quest->nelementsautor; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" .
                        $elements[$i]->description . "</textarea>\n";
                echo "  </td></tr>\n";
                if ($elements[$i]->weight == '') { // ...not set.
                    $elements[$i]->weight = 11; // ...unity.
                }
                echo "</tr>\n";
                echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
                quest_choose_from_menu($questeweights, "weight[]", $elements[$i]->weight, "");
                echo "      </td>\n";
                echo "</tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            echo "</center></table><br />\n";
            echo "<center><b>" . get_string("gradetable", "quest") . "</b></center>\n";
            echo "<center><table cellpadding=\"5\" border=\"1\"><tr><td align=\"CENTER\">" .
                     get_string("numberofnegativeresponses", "quest");
            echo "</td><td>" . get_string("suggestedgrade", "quest") . "</td></tr>\n";
            for ($j = $quest->maxcalification; $j >= 0; $j--) {
                $numbers[$j] = $j;
            }
            for ($i = 0; $i <= $quest->nelementsautor; $i++) {
                echo "<tr><td align=\"CENTER\">$i</td><td align=\"CENTER\">";
                if (!isset($elements[$i])) { // ...the "last one" will be!
                    $elements[$i]->description = "";
                    $elements[$i]->maxscore = 0;
                }
                echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
                echo "</td></tr>\n";
            }
            echo "</table></center>\n";
            break;
        case 3: // Criterion grading.
            for ($j = $quest->maxcalification; $j >= 0; $j--) {
                $numbers[$j] = $j;
            }
            for ($i = 0; $i < $quest->nelementsautor; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><b>" . get_string("criterion", "quest") . " $iplus1:</b></td>\n";
                echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" .
                     $elements[$i]->description . "</textarea>\n";
                echo "  </td></tr>\n";
                echo "<tr><td><b>" . get_string("suggestedgrade", "quest") . ":</b></td><td>\n";
                echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
                echo "</td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            break;
        case 4: // Rubric.
            for ($j = $quest->maxcalification; $j >= 0; $j--) {
                $numbers[$j] = $j;
            }
            if ($rubricsraw = $DB->get_records("quest_rubrics_autor", array("questid" => $quest->id))) {
                foreach ($rubricsraw as $rubric) {
                    $rubrics[$rubric->elementno][$rubric->rubricno] = $rubric->description; // Reindex.
                }
            }
            for ($i = 0; $i < $quest->nelementsautor; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" .
                     $elements[$i]->description . "</textarea>\n";
                echo "  </td></tr>\n";
                echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
                quest_choose_from_menu($questeweights, "weight[]", $elements[$i]->weight, "");
                echo "      </td>\n";
                echo "</tr>\n";

                for ($j = 0; $j < 5; $j++) {
                    $jplus1 = $j + 1;
                    if (empty($rubrics[$i][$j])) {
                        $rubrics[$i][$j] = "";
                    }
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>" . get_string("grade", "quest") . " $j:</b></td>\n";
                    echo "<td><textarea name=\"rubric[$i][$j]\" rows=\"3\" cols=\"75\">" . $rubrics[$i][$j] . "</textarea>\n";
                    echo "  </td></tr>\n";
                }
                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            break;
    }
    // Close table and form.
    echo "</table><br />";
    echo '<input type="submit" value="' . get_string("savechanges") . '" />';
    echo '<input type="submit" name="cancel" value="' . get_string("cancel") . '" />';
    echo '</form>';
} else if ($action == 'insertelements') {
    // Insert/update assignment elements (for teachers).
    if (!$ismanager) {
        error("Only teachers can look at this page");
    }
    $form = data_submitted();
    // Let's not fool around here, dump the junk!
    $DB->delete_records("quest_elementsautor", array("questid" => $quest->id));
    // Determine wich type of grading.
    switch ($quest->gradingstrategyautor) {
        case 0: // ...no grading insert all the elements that contain something.
            foreach ($form->description as $key => $description) {
                if ($description) {
                    unset($element);
                    $element = new stdClass();
                    $element->description = $description;
                    $element->questid = $quest->id;
                    $element->elementno = $key;
                    if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                        error("Could not insert quest element!");
                    }
                }
            }
            break;
        case 1: // Accumulative grading.
                // Insert all the elements that contain something.
            foreach ($form->description as $key => $description) {
                if ($description) {
                    $element = new stdClass();
                    $element->description = $description;
                    $element->questid = $quest->id;
                    $element->elementno = $key;
                    if (isset($form->scale[$key])) {
                        $element->scale = $form->scale[$key];
                        switch ($questscales[$form->scale[$key]]['type']) {
                            case 'radio':
                                $element->maxscore = $questscales[$form->scale[$key]]['size'] - 1;
                                break;
                            case 'selection':
                                $element->maxscore = $questscales[$form->scale[$key]]['size'];
                                break;
                        }
                    }
                    if (isset($form->weight[$key])) {
                        $element->weight = $form->weight[$key];
                    }
                    if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                        error("Could not insert quest element!");
                    }
                }
            }
            break;

        case 2: // Error banded grading...
        case 3: // ...and criterion grading
                // Insert all the elements that contain something, the number of descriptions is one
                // less than the number of grades.
            foreach ($form->maxscore as $key => $themaxscore) {
                unset($element);
                $element->questid = $quest->id;
                $element->elementno = $key;
                $element->maxscore = $themaxscore;
                if (isset($form->description[$key])) {
                    $element->description = $form->description[$key];
                }
                if (isset($form->weight[$key])) {
                    $element->weight = $form->weight[$key];
                }
                if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                    error("Could not insert quest element!");
                }
            }
            break;

        case 4: // ...and criteria grading
                // Insert all the elements that contain something.
            foreach ($form->description as $key => $description) {
                unset($element);
                $element->questid = $quest->id;
                $element->elementno = $key;
                $element->description = $description;
                $element->weight = $form->weight[$key];
                for ($j = 0; $j < 5; $j++) {
                    if (empty($form->rubric[$key][$j])) {
                        break;
                    }
                }
                $element->maxscore = $j - 1;
                if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                    error("Could not insert quest element!");
                }
            }
            // Let's not fool around here, dump the junk!
            $DB->delete_records("quest_rubrics_autor", "questid", $quest->id);
            for ($i = 0; $i < $quest->nelementsautor; $i++) {
                for ($j = 0; $j < 5; $j++) {
                    unset($element);
                    if (empty($form->rubric[$i][$j])) { // OK to have an element with fewer than 5 items.
                        break;
                    }
                    $element->questid = $quest->id;
                    $element->elementno = $i;
                    $element->rubricno = $j;
                    $element->description = $form->rubric[$i][$j];
                    if (!$element->id = $DB->insert_record("quest_rubrics_autor", $element)) {
                        error("Could not insert quest element!");
                    }
                }
            }
            break;
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
        $form = data_submitted('nomatch'); // Nomatch because we can come from assess.php.
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
                    $element->answer = $form->{"feedback_$i"};
                    $element->commentteacher = $form->generalcomment;
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        print_error('inserterror', 'quest', null, "quest_items_assesments_autor");
                    }
                }
                $grade = $assessment->points; // Set to satisfy save to db.
                break;
            case 1: // Accumulative grading.
                    // Insert all the elements that contain something.
                foreach ($form->grade as $key => $thegrade) {
                    unset($element);
                    $element = new stdclass();
                    $element->questid = $quest->id;
                    $element->userid = $USER->id;
                    $element->assessmentautorid = $assessment->id;
                    $element->elementno = $key;
                    $element->answer = $form->{"feedback_$key"};
                    $element->calification = $thegrade;
                    $element->commentteacher = $form->generalcomment; // TODO: EVP CHECK THIS... DATA BASE
                                                                      // CONTAINS THIS FIELD BUT I
                                                                      // do not find it in the form and
                                                                      // I have included this to
                                                                      // avoid errors. I think this
                                                                      // field is no longer used.
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        print_error('inserterror', 'quest', null, "quest_items_assesments_autor");
                    }
                }
                // Now work out the grade...
                $rawgrade = 0;
                $totalweight = 0;
                foreach ($form->grade as $key => $grade) {
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
            case 2: // Error banded graded.
                    // Insert all the elements that contain something.
                $error = 0.0;
                for ($i = 0; $i < $numelements; $i++) {
                    unset($element);
                    $element->questid = $quest->id;
                    $element->assessmentid = $assessment->id;
                    $element->elementno = $i;
                    $element->answer = $form->{"feedback_$i"};
                    $element->calification = $form->grade[$i];
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        error("Could not insert quest grade!");
                    }
                    if (empty($form->grade[$i])) {
                        $error += $questeweights[$elements[$i]->weight];
                    }
                }
                // Now save the adjustment.
                $element = new stdClass();
                $i = $numelements;
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $i;
                $element->calification = $form->grade[$i];
                if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                    error("Could not insert quest grade!");
                }
                $rawgrade = ($elements[intval($error + 0.5)]->maxscore + $form->grade[$i]);
                // Do sanity check.
                if ($rawgrade < 0) {
                    $rawgrade = 0;
                } else if ($rawgrade > $quest->maxcalification) {
                    $rawgrade = $quest->maxcalification;
                }
                echo "<b>" . get_string("weightederrorcount", "quest", intval($error + 0.5)) . "</b>\n";
                $grade = $points * ($rawgrade / $quest->maxcalification);
                break;
            case 3: // Criteria grading.
                    // Save in the selected criteria value in element zero.
                unset($element);
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = 0;
                $element->calification = $form->grade[0];
                if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                    error("Could not insert quest grade!");
                }
                // Now save the adjustment in element one.
                unset($element);
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = 1;
                $element->calification = $form->grade[1];
                if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                    error("Could not insert quest grade!");
                }
                $rawgrade = ($elements[$form->grade[0]]->maxscore + $form->grade[1]);

                $grade = $points * ($rawgrade / $quest->maxcalification);

                break;

            case 4: // Rubric grading (identical to accumulative grading).
                    // Insert all the elements that contain something.
                foreach ($form->grade as $key => $thegrade) {
                    unset($element);
                    $element->questid = $quest->id;
                    $element->assessmentid = $assessment->id;
                    $element->elementno = $key;
                    $element->answer = $form->{"feedback_$key"};
                    $element->calification = $thegrade;
                    if (!$element->id = $DB->insert_record("quest_items_asesments_autor", $element)) {
                        error("Could not insert quest grade!");
                    }
                }
                // Now work out the grade...
                $rawgrade = 0;
                $totalweight = 0;
                foreach ($form->grade as $key => $grade) {
                    $maxscore = $elements[$key]->maxscore;
                    $weight = $questeweights[$elements[$key]->weight];
                    if ($weight > 0) {
                        $totalweight += $weight;
                    }
                    $rawgrade += ($grade / $maxscore) * $weight;
                }
                $grade = $points * ($rawgrade / $totalweight);
                break;
        }
    }

    $assessment->state = ASSESSMENT_STATE_BY_AUTOR;
    $assessment->points = $grade;
    $assessment->dateassessment = $timenow;
    $submission->evaluated = 1;
    // Any comment?
    if (!empty($form->generalcomment)) {
        $assessment->commentsteacher = $form->generalcomment;
    }
    if (!empty($form->generalteachercomment)) {
        $assessment->commentsforteacher = $form->generalteachercomment;
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
    if ($CFG->version >= 2014051200) {
        require_once('classes/event/challenge_assessed.php');
        \mod_quest\event\challenge_assessed::create_from_parts($submission, $assessment, $cm)->trigger();
    } else {
        add_to_log($course->id, "quest", "assess_challenge", "viewassessmentautor.php?id=$cm->id&amp;aid=$assessment->id",
                "$assessment->id", "$cm->id");
    }
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
    echo $OUTPUT->notification($message, 'info');
    echo $OUTPUT->continue_button($returnto);
} else {
    print_error('unkownactionerror', 'quest', null, $action);
}

echo $OUTPUT->footer();