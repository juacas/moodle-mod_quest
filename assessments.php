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
 *  ACTIONS:
  - displaygradingform
  - editelements
  - insertelements
  - updateassessment

 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 * ***************************************************** */

require("../../config.php");
require("lib.php");
require("locallib.php");
require_once('scores_lib.php');
$id = required_param('id', PARAM_INT);     // Course Module ID.
$action = required_param('action', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT);     // Quest Submission ID.
$newform = optional_param('newform', null, PARAM_INT); // Flag: if you want new form for one submission...newform=1. If form is general...newform=0.
$numelemswhenchange = optional_param('num_elems_when_change', '', PARAM_INT); // New number of elements when you add or remove elements.
$changeform = optional_param('change_form', null, PARAM_INT); // Flag: if you change the number of elements in forms, change_form=1, else 0.
$viewgeneral = optional_param('viewgeneral', -1, PARAM_INT); // Flag: view general form =1, particular form view of one submission = 0

global $DB, $OUTPUT, $PAGE;
list($course,$cm)=get_course_and_cm_from_cmid($id,"quest");
$quest = $DB->get_record("quest", array("id" => $cm->instance),'*',MUST_EXIST);

$context = context_module::instance($id);
$isteacher = has_capability('mod/quest:manage', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassessments = get_string("assessments", "quest");

require_login($course->id, false, $cm);
$url = new moodle_url('/mod/quest/assessments.php', array('action' => $action, 'id' => $cm->id,'sesskey'=>sesskey()));
if ($sid != '') {
    $url->param('sid', $sid);
}
if ($newform != '') {
    $url->param('newform', $newform);
}
if ($numelemswhenchange != '') {
    $url->param('$numelemswhenchange', $numelemswhenchange);
}
if ($changeform != '') {
    $url->param('$changeform', $changeform);
}
if ($viewgeneral !== -1) {
    $url->param('viewgeneral', $viewgeneral);
}

$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_context($context);
$PAGE->set_heading($course->fullname);

// ...display grading form (viewed by student) .
if ($action == 'displaygradingform') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string("specimenassessmentformanswer", "quest"), 'specimenanswer', "quest");

    quest_print_assessment($quest, $sid, false, null);
    // ...called with no assessment.
    echo '<p>';
    if ($viewgeneral == 1) {
        echo $OUTPUT->continue_button(new moodle_url("view.php", array('id'=>$id)));
    } else {
        if ($sid == '') {
           echo $OUTPUT->continue_button(new moodle_url("view.php", array('id'=>$id)));
        } else {
            echo $OUTPUT->continue_button(new moodle_url("submissions.php",
                    array('id'=>$cm->id,'sid'=>$sid,'action'=>'showsubmission')));
        }
    }
}

// ... edit assessment elements (for teachers).
else if ($action == 'editelements') {
    require_sesskey();
    $authorid = isset($sid)?$DB->get_field('quest_submissions', 'userid', array('id' => $sid)):null;
    if (!$isteacher && $authorid != $USER->id) {
        error("Only teachers or author can look at this page");
    }
    // If the elements have not been defined for the questournament $newform=0.
    if ($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0)) == 0) {
        $newform = 0;
    }
    // ...set up heading, form and table.
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string("editingassessmentelements", "quest"), "elements", "quest");
    if (quest_count_submission_assessments($sid) > 0) {
        echo $OUTPUT->notification(get_string("warningonamendingelements", "quest"));
    }
    ?>

    <form name="form" method="post" action="assessments.php">
        <input type="hidden" name="id" value="<?php echo $cm->id ?>" />
        <input type="hidden" name="action" value="insertelements" />
        <center><table cellpadding="5" border="1">
                <?php
                // ...get existing elements, if none set up appropriate default ones.

                if ($newform == 0) {
                    $sidtarget = 0;
                } else {
                    if (($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => $sid)) != 0)) {
                        $sidtarget = $sid;
                    } else {
                        $sidtarget = 0;
                    }
                }

                if ($elementsraw = $DB->get_records("quest_elements", array("submissionsid" => $sidtarget), "elementno ASC")) {
                    foreach ($elementsraw as $element) {
                        if ($element->questid == $quest->id) {
                            $elements[] = $element;   // ...to renumber index 0,1,2...
                        }
                    }
                }
                if ($DB->count_records('quest_elements', array('submissionsid' => $sid)) == 0) {
                    $num = $quest->nelements;
                }
                if (($newform == 1) && ($changeform == 1)) {
                    $num = $numelemswhenchange;
                }
                if (($DB->get_field("quest_submissions", "numelements", array("id" => $sid)) != 0) && ($changeform == 0) && ($newform == 1)) {
                    $num = $DB->get_field("quest_submissions", "numelements", array("id" => $sid));
                }
                // TODO check this if (($DB->get_field ("quest_submissions", "numelements", "id", $sid)!=0)&&($changeform==1)&&($newform==0)).
                if (($changeform == 1) && ($newform == 0)) {
                    $num = $numelemswhenchange;
                }
                if (($newform == 0) && ($changeform == 0)) {
                    $num = $quest->nelements;
                }
                $changeform = 0;
                // ...check for missing elements (this happens either the first time round or when the number of elements is increased).
                for ($i = 0; $i < $num; $i++) {
                    if (!isset($elements[$i])) {
                        $elements[$i] = new stdClass();
                        $elements[$i]->description = '';
                        $elements[$i]->scale = 0;
                        $elements[$i]->maxscore = 0;
                        $elements[$i]->weight = 11;
                    }
                }
                if ($elements[0]->description == '') {   // ...to return view.php when complete general elements the first time.
                    $viewgeneral = 1;
                }
// TODO: replace  with quest_print_assessment from locallib.php!
                switch ($quest->gradingstrategy) {
                    case 0: // ...no grading.
                        for ($i = 0; $i < $num; $i++) {
                            $iplus1 = $i + 1;
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                            echo "<td><textarea name=\"description[]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                            echo "  </td></tr>\n";
                            echo "<tr valign=\"top\">\n";
                            echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                            echo "</tr>\n";
                        }
                        if ($newform == 1) {
                            $DB->set_field("quest_submissions", "numelements", $num, array("id" => $sid));
                        } else if ($newform == 0) {
                            $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                            $DB->set_field("quest", "nelements", $num, array("id" => $var));
                        }
                        break;
                    case 1: // ...accumulative grading.
                        // ...set up scales name.

                        foreach ($QUEST_SCALES as $KEY => $SCALE) {
                            $SCALES[] = $SCALE['name'];
                        }
                        for ($i = 0; $i < $num; $i++) {
                            $iplus1 = $i + 1;

                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                            echo "<td><textarea name=\"description[]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                            echo "  </td></tr>\n";
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><b>" . get_string("typeofscale", "quest") . ":</b></td>\n";
                            echo "<td valign=\"top\">\n";
                            // ...choose_from_menu($SCALES, "scale[]", $elements[$i]->scale, "");.
                            echo html_writer::select($SCALES, "scale[]", $elements[$i]->scale, "");
                            if ($elements[$i]->weight == '') { // not set
                                $elements[$i]->weight = 11; // unity
                            }
                            echo "</td></tr>\n";
                            echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
                            quest_choose_from_menu($QUEST_EWEIGHTS, "weight[]", $elements[$i]->weight, "");
                            echo "      </td>\n";
                            echo "</tr>\n";
                            echo "<tr valign=\"top\">\n";
                            echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                            echo "</tr>\n";
                        }
                        if ($newform == 1) {
                            $DB->set_field("quest_submissions", "numelements", $num, array("id" => $sid));
                        } else if ($newform == 0) {
                            $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                            $DB->set_field("quest", "nelements", $num, array("id" => $var));
                        }
                        break;

                    case 2: // ...error banded grading.
                        for ($i = 0; $i < $num; $i++) {
                            $iplus1 = $i + 1;
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                            echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                            echo "  </td></tr>\n";
                            if ($elements[$i]->weight == '') { // ...not set.
                                $elements[$i]->weight = 11; // ...unity.
                            }
                            echo "</tr>\n";
                            echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
                            quest_choose_from_menu($QUEST_EWEIGHTS, "weight[]", $elements[$i]->weight, "");
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
                        for ($i = 0; $i <= $num; $i++) {
                            echo "<tr><td align=\"CENTER\">$i</td><td align=\"CENTER\">";
                            if (!isset($elements[$i])) {  // ...the "last one" will be!
                                $elements[$i]->description = "";
                                $elements[$i]->maxscore = 0;
                            }
                            echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
                            echo "</td></tr>\n";
                        }
                        echo "</table></center>\n";
                        if ($newform == 1) {
                            $DB->set_field("quest_submissions", "numelements", $num, array("id" => $sid));
                        } else if ($newform == 0) {
                            $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                            $DB->set_field("quest", "nelements", $num, array("id" => $var));
                        }
                        break;

                    case 3: // ...criterion grading.
                        for ($j = $quest->maxcalification; $j >= 0; $j--) {
                            $numbers[$j] = $j;
                        }
                        for ($i = 0; $i < $num; $i++) {
                            $iplus1 = $i + 1;
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><b>" . get_string("criterion", "quest") . " $iplus1:</b></td>\n";
                            echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                            echo "  </td></tr>\n";
                            echo "<tr><td><b>" . get_string("suggestedgrade", "quest") . ":</b></td><td>\n";
                            echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
                            echo "</td></tr>\n";
                            echo "<tr valign=\"top\">\n";
                            echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                            echo "</tr>\n";
                        }
                        if ($newform == 1) {
                            $DB->set_field("quest_submissions", "numelements", $num, array("id" => $sid));
                        } else if ($newform == 0) {
                            $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                            $DB->set_field("quest", "nelements", $num, array("id" => $var));
                        }
                        break;

                    case 4: // ...rubric.
                        for ($j = $quest->maxcalification; $j >= 0; $j--) {
                            $numbers[$j] = $j;
                        }

                        if (($newform == 0) || ($DB->count_records("quest_rubrics", array("submissionsid" => $sid)) == 0)) {
                            $var = 0;
                        } else if ($newform == 1) {
                            $var = $sid;
                        }
                        if ($rubricsraw = $DB->get_records_select("quest_rubrics", "questid = ? AND submissionsid = ?",
                                array($quest->id, $var), "elementno ASC")) {
                            foreach ($rubricsraw as $rubric) {
                                $rubrics[$rubric->elementno][$rubric->rubricno] = $rubric->description;   // ...reindex 0,1,2...
                            }
                        }
                        for ($i = 0; $i < $num; $i++) {
                            $iplus1 = $i + 1;
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                            echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" . $elements[$i]->description . "</textarea>\n";
                            echo "  </td></tr>\n";
                            echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
                            quest_choose_from_menu($QUEST_EWEIGHTS, "weight[]", $elements[$i]->weight, "");
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
                        if ($newform == 1) {
                            $DB->set_field("quest_submissions", "numelements", $num, "id", $sid);
                        } else if ($newform == 0) {
                            $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                            $DB->set_field("quest", "nelements", $num, array("id" => $var));
                        }
                        break;
                }
                // ...close table and form.
                if ($newform == 0) {
                    $nf = 0;
                } else if ($newform == 1) {
                    $nf = 1;
                }
                ?>

            </table><br/>
            <center>
                <input type="hidden" name="newform" value="<?php echo $nf ?>" />
                <input type="hidden" name="sid" value="<?php echo $sid ?>" />
                <input type="hidden" name="viewgeneral" value="<?php echo $viewgeneral ?>" />
                <input type="hidden" name="n_elem_when_change" value="<?php echo $num ?>" />
                <input type="submit" value="<?php print_string("savechanges") ?>" />
                <input type="submit" name="cancel" value="<?php print_string("cancel") ?>" />
                <input type="hidden" name="sesskey" value="<?php echo sesskey()?>" />
            </center>
    </form>
    <center>
        <form ACTION="assessments.php">
            <input type="hidden" name="newform" value="<?php echo $nf ?>" />
            <input type="hidden" name="change_form" value="1" />
            <input type="hidden" name="id" value="<?php echo $cm->id ?>" />
            <input type="hidden" name="sid" value="<?php echo $sid ?>" />
            <input type="hidden" name="viewgeneral" value="<?php echo $viewgeneral ?>" />
            <input type="hidden" name="num_elems_when_change" value="<?php echo $num + 1; ?>" />
            <input type="hidden" name="action" value="editelements" />
            <input type="submit" value="<?php print_string("addelement", "quest") ?>" />
            <input type="hidden" name="sesskey" value="<?php echo sesskey()?>" />
        </form>

        <form ACTION="">
            <input type="hidden" name="newform" value="<?php echo $nf ?>" />
            <input type="hidden" name="change_form" value="1" />
            <input type="hidden" name="id" value="<?php echo $cm->id ?>" />
            <input type="hidden" name="sid" value="<?php echo $sid ?>" />
            <input type="hidden" name="viewgeneral" value="<?php echo $viewgeneral ?>" />
            <input type="hidden" name="num_elems_when_change" value="<?php echo $num - 1; ?>" />
            <input type="hidden" name="action" value="editelements" />
            <input type="submit" value="<?php print_string("removeelement", "quest") ?>" />
            <input type="hidden" name="sesskey" value="<?php echo sesskey()?>" />
        </form>
    </center>
    <?php
}
// ... insert/update assignment elements (for teachers).
else if ($action == 'insertelements') {
    require_sesskey();
    $authorid = $DB->get_field('quest_submissions', 'userid', array('id' => $sid));
    if (!$isteacher && $authorid != $USER->id) {
        error("Only teachers or author can look at this page");
    }
    $form = data_submitted();
    // let's not fool around here, dump the junk!
    if ($newform == 0) {
        $DB->delete_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0));
    } else {
        $DB->delete_records("quest_elements", array("questid" => $quest->id, "submissionsid" => $sid));
    }
    // determine wich type of grading
    switch ($quest->gradingstrategy) {
        case 0: // no grading
            // Insert all the elements that contain something
            foreach ($form->description as $key => $description) {
                if ($description) {
                    unset($element);
                    $element->description = $description;
                    $element->questid = $quest->id;
                    if ($newform == 0) {
                        $element->submissionsid = 0;
                    } else if ($newform == 1) {
                        $element->submissionsid = $sid;
                    }
                    $element->elementno = $key;
                    if (!$element->id = $DB->insert_record("quest_elements", $element)) {
                        error("Could not insert quest element!");
                    }
                }
            }
            break;

        case 1: // accumulative grading
            // Insert all the elements that contain something
            foreach ($form->description as $key => $description) {
                if ($description) {
                    unset($element);
                    $element = new stdClass();
                    $element->description = $description;
                    $element->questid = $quest->id;
                    if ($newform == 1) {
                        $element->submissionsid = $sid;
                    } else if (($newform == 0) || (($DB->count_records("quest_elements",
                                    array("questid" => $quest->id, "submissionsid" => 0)) == 0))) {
                        $element->submissionsid = 0;
                    }
                    $element->elementno = $key;
                    if (isset($form->scale[$key])) {
                        $element->scale = $form->scale[$key];
                        switch ($QUEST_SCALES[$form->scale[$key]]['type']) {
                            case 'radio' : $element->maxscore = $QUEST_SCALES[$form->scale[$key]]['size'] - 1;
                                break;
                            case 'selection' : $element->maxscore = $QUEST_SCALES[$form->scale[$key]]['size'];
                                break;
                        }
                    }
                    if (isset($form->weight[$key])) {
                        $element->weight = $form->weight[$key];
                    }
                    if (!$element->id = $DB->insert_record("quest_elements", $element)) {
                        error("Could not insert quest element!");
                    }
                }
            }
            break;

        case 2: // error banded grading...
        case 3: // ...and criterion grading
            // Insert all the elements that contain something, the number of descriptions is one less than the number of grades
            foreach ($form->maxscore as $key => $themaxscore) {
                unset($element);
                $element->questid = $quest->id;
                if ($newform == 0) {
                    $element->submissionsid = 0;
                } else if ($newform == 1) {
                    $element->submissionsid = $sid;
                }
                $element->elementno = $key;
                $element->maxscore = $themaxscore;
                if (isset($form->description[$key])) {
                    $element->description = $form->description[$key];
                }
                if (isset($form->weight[$key])) {
                    $element->weight = $form->weight[$key];
                }
                if (!$element->id = $DB->insert_record("quest_elements", $element)) {
                    error("Could not insert quest element!");
                }
            }
            break;

        case 4: // ...and criteria grading
            // Insert all the elements that contain something
            foreach ($form->description as $key => $description) {
                unset($element);
                $element->questid = $quest->id;
                if ($newform == 0) {
                    $element->submissionsid = 0;
                } else if ($newform == 1) {
                    $element->submissionsid = $sid;
                }
                $element->elementno = $key;
                $element->description = $description;
                $element->weight = $form->weight[$key];
                for ($j = 0; $j < 5; $j++) {
                    if (empty($form->rubric[$key][$j])) {
                        break;
                    }
                }
                $element->maxscore = $j - 1;
                if (!$element->id = $DB->insert_record("quest_elements", $element)) {
                    error("Could not insert quest element!");
                }
            }
            // let's not fool around here, dump the junk!
            if ($newform == 0) {
                $num = $quest->nelements;
                $var = 0;
            } else if ($newform == 1) {
                $var = $sid;
                $num = $DB->get_field("quest_submissions", "numelements", array("id" => $sid));
            }
            $DB->delete_records("quest_rubrics", array("questid" => $quest->id, "submissionsid" => $var));

            for ($i = 0; $i < $num; $i++) {
                for ($j = 0; $j < 5; $j++) {

                    unset($element);
                    if (empty($form->rubric[$i][$j])) {  // OK to have an element with fewer than 5 items
                        break;
                    }
                    $element->questid = $quest->id;
                    if ($newform == 0) {
                        $element->submissionsid = 0;
                    } else if ($newform == 1) {
                        $element->submissionsid = $sid;
                    }
                    $element->elementno = $i;
                    $element->rubricno = $j;
                    $element->description = $form->rubric[$i][$j];
                    if (!$element->id = $DB->insert_record("quest_rubrics", $element)) {
                        error("Could not insert quest element!");
                    }
                }
            }
            break;
    } // end of switch

    if ($viewgeneral == 1) {
        echo $OUTPUT->redirect_message("view.php?id=$cm->id", '<center>' . get_string("savedok", "quest") . '</center>', 1, false);
    } else {
        echo $OUTPUT->redirect_message("submissions.php?id=$cm->id&sid=$sid&action=showsubmission",
                get_string("savedok", "quest"), 1, false);
    }
}
/* ************* update assessment (by teacher or student) ************************** */ 
else if ($action == 'updateassessment') {
    $aid = required_param('aid', PARAM_INT);
    $sid = optional_param('sid', 0, PARAM_INT);
    require_sesskey();
    if (!$answer = $DB->get_record("quest_answers", array("id" => $aid))) {
        error("quest answer is misconfigured");
    }
    if (!$assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id))) {
        error("quest assessment is misconfigured");
    }
    if (!$submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid))) {
        error("quest submission is misconfigured");
    }
    // Check access
    if (!$isteacher && $USER->id != $submission->userid) {
        error("Can't access this script. You should be teacher or challenge's author.");
    }
    $timenow = time();
    $form = data_submitted('nomatch'); //Nomatch because we can come from assess.php.
    $isvalidating = false; // marca si se está validando la pregunta en cuyo caso no hay puntuacion previa que restar
    if ($quest->validateassessment == 1) {
// necesita validar evaluacion
        if ($isteacher) {
            // El profesor puede validar pasando a phase=1
            if ($assessment->phase == ASSESSMENT_PHASE_APPROVAL_PENDING) {// contabiliza la nueva evaluación
                $isvalidating = true;
                $assessment->phase = 1; // ya está validada ahora OJO: ¿se había sumado esta nota?
            }
        }// END profesor valida
        else { // si no es profesor la fase siempre será phase=0. La nota queda pendiente
            if ($assessment->phase != ASSESSMENT_PHASE_APPROVAL_PENDING) {
                error("Error grave: no puede actualizar una evaluacion ya validada por el profesor.");
            }
        }
    }else { // Este QUEST no requiere validación
        if ($assessment->phase == ASSESSMENT_PHASE_APPROVAL_PENDING) {
            $isvalidating = true;
            $assessment->phase = ASSESSMENT_PHASE_APPROVED; // pasa directamente a phase=1 (validada)
        }
    }
    if ($answer->phase == ANSWER_PHASE_UNGRADED) {//respuesta no evaluada
        $answer->phase = ANSWER_PHASE_GRADED; // respuesta evaluada
    }
    $recalification = false;
    $revision = false;
    // determine what kind of grading we have
    // and calculate grade as a percentage.
    /**
     * Manual grading
     */
    $manualgrade = optional_param('manualcalification', null, PARAM_ALPHANUM);
    if ($manualgrade != null) {
        // ... "Grading manually!";
        $percent = ((int) $manualgrade) / 100;
    } else {
        /*
         * Form grading
         */
        // ... "Grading by criteria!";
        $percent = quest_get_answer_grade($quest, $answer, $form);
    }
    $points = quest_get_points($submission, $quest, $answer);
    $grade = $points * $percent;
    /*
     * Process the grade
     * update registries
     */
    $answer->grade = 100 * $percent;

    if (($percent) >= 0.5000) {
        $answer->phase = ANSWER_PHASE_PASSED;
// ...hay respuestas correctas posteriores o no hay ninguna
// la actual es la nueva correcta y hay que recalificar el resto.
        if ($submission->nanswerscorrect > 0) {
            if ($answer->date < $submission->dateanswercorrect) {
                $recalification = true;
                $submission->nanswerscorrect = 0;
                $submission->dateanswercorrect = $answer->date;
            }
        } else if ($submission->nanswerscorrect == 0) {
            $recalification = true;
            $submission->dateanswercorrect = $answer->date;
        }
// FIN comprobación respuestas correctas.
        $submission->points = $grade;
// ...no hay resp.correctas y la evaluacion esta aprobada.
        if (($submission->nanswerscorrect == 0) && ($assessment->phase == ASSESSMENT_PHASE_APPROVED)) {
            $submission->dateanswercorrect = $answer->date;
            $submission->pointsanswercorrect = $points;
        }
        if (($answer->phase != ANSWER_PHASE_PASSED) && ($assessment->phase == ASSESSMENT_PHASE_APPROVED)) {
            $submission->nanswerscorrect++;
            $answer->phase = ANSWER_PHASE_PASSED;
        }
    } else { // La respuesta no ha aprobado.
        $submission->points = $grade;
        if ($answer->phase == 2) { // ...ya estaba calificada por lo que es una recalificacion.
            $submission->nanswerscorrect--;
        }
        $answer->phase = 1;

        if ($answer->date == $submission->dateanswercorrect) {// ...si es la primera correcta hay que recalificar todas.
            $submission->nanswerscorrect = 0;
            $submission->dateanswercorrect = 0;
            $recalification = true; // ...recalifica todas.
        }
    }
    /**
     * assesment->state
     * 0 sin realizar
     * 1 realizada autor
     * 2 realizada profesor
     *
     * assessment->phase
     * 0 sin aprobar
     * 1 aprobada
     */
    $answer->pointsmax = number_format($points, 4); // ...weird bug with mysql if $points is double of numeric.
    // ...update the time of the assessment record (may be re-edited)...
    $assessment->dateassessment = $timenow;

// ...update submission
// get first answer correct
// update pointsanswercorrect.
    if ($query = $DB->get_record_select("quest_answers", "submissionid=? and grade>=50", array($submission->id), "date,pointsmax",
            IGNORE_MULTIPLE)) {
        $submission->dateanswercorrect = $query->date;
        $submission->pointsanswercorrect = number_format($query->pointsmax, 4);
    } else {
        $submission->dateanswercorrect = 0;
        $submission->pointsanswercorrect = 0;
    }
    $answer->permitsubmit = 0;
    /*
     * answer->state
     * 0 sin editar
     * 1 editada
     * 2 modificada (evaluada manualmente?)   //evp this should be clearly defined
     *
     * answer->phase
     * 0 sin evaluar
     * 1 evaluada
     * 2 aprobada (evaluada >50%)
     *
     * answer->permitsubmit
     * 0 no editable
     * 1 editable
     */
    if ($answer->state == ANSWER_STATE_MODIFIED) {
        $answer->state = ANSWER_STATE_EDITTED;
    }
    if ($isteacher) {
        $assessment->pointsteacher = $grade;
        $assessment->teacherid = $USER->id;
    } else {
        $assessment->pointsautor = $grade;
    }
// state 0 no realizada 1 por autor 2 por profesor.
    if ($isteacher) {
        $assessment->state = ASSESSMENT_STATE_BY_TEACHER;
    } else {
        $assessment->state = ASSESSMENT_STATE_BY_AUTOR;
    }
    // any comment?
    if (!empty($form->generalcomment)) {
        $assessment->commentsteacher = $form->generalcomment;
    }

    if (!empty($form->generalteachercomment)) {
        $assessment->commentsforteacher = $form->generalteachercomment;
    }

    $DB->update_record('quest_answers', $answer);
    quest_update_submission($submission);
    quest_update_assessment($assessment);
    quest_update_submission_counts($submission->id);
// ...points recalculation.
    $recalification = true; // ...To disable this optimization it's not worth as evaluation is not a frequent action.
// ...Recalcula los puntos de las respuestas del quest.
    if ($recalification) {
        quest_update_grade_for_answer($answer, $submission, $quest, $course);
    }
    $userid = $answer->userid;
// ...recalculate points and report to gradebook.
    quest_grade_updated($quest, $userid);
// NOTIFICATIONS.
    if ($isteacher) {
        if ($user = get_complete_user_data('id', $answer->userid)) {
            quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
        }
        if ($user = get_complete_user_data('id', $assessment->userid)) {
            quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
        }
    } else {
        if ($user = get_complete_user_data('id', $answer->userid)) {
            quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
        }
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            print_heading(get_string("nostudentsyet"));
            print_footer($course);
            exit;
        }
// JPC 2013-11-28 disable excesive notifications.
//          foreach($users as $user){
//           if(!has_capability('mod/quest:manage',$context,$user->id)){
//            continue;
//           }
//           quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
//          }
    }
// Log the event
    if ($CFG->version >= 2014051200) {
    require_once 'classes/event/answer_assessed.php';
    \mod_quest\event\answer_assessed::create_from_parts($submission,$answer,$assessment,$cm)->trigger();
} else {
    add_to_log($course->id, "quest", "assess_answer", "viewassessment.php?id=$cm->id&amp;asid=$assessment->id", "$assessment->id", "$cm->id");
}
    // ...set up return address.
    $returnto = $_POST['returnto'];
    if (!$returnto) {
        $returnto = "view.php?id=$cm->id";
    }
    // ...show grade if grading strategy is not zero.
    if ($quest->gradingstrategy) {
        echo $OUTPUT->redirect_message($returnto,
                get_string("thegradeis", "quest") . ": " . number_format($grade, 4) . " (" . get_string("maximumgrade") . " " . number_format($points,
                        4) . ")", 10, false);
    } else {
        echo $OUTPUT->redirect_message($returnto, '', 1, false);
    }
}// ...updateassessment.
/*
 * no man's land
*/ else {
    print_error("Fatal Error: Unknown Action", 'quest', $action, "Unknown action:$action");
}

echo $OUTPUT->footer();