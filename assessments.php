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
 * ACTIONS:
 * - displaygradingform
 * - editelements
 * - insertelements
 * - updateassessment
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once('scores_lib.php');
$id = required_param('id', PARAM_INT); // Course Module ID..
$action = required_param('action', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT); // Quest Submission ID..
$newform = optional_param('newform', null, PARAM_INT); // Flag: if you want new form for one.
                                                       // ...submission...newform=1. If form is.
                                                       // ...general...newform=0..
$numelemswhenchange = optional_param('num_elems_when_change', '', PARAM_INT); // New number of.
                                                                              // ...elements when
                                                                              // you.
                                                                              // ...add or remove.
                                                                              // ...elements..
$changeform = optional_param('change_form', null, PARAM_INT); // Flag: if you change the number of.
                                                              // ...elements in forms,
                                                              // change_form=1, else 0..
$viewgeneral = optional_param('viewgeneral', -1, PARAM_INT); // Flag: view general form =1,.
                                                             // ...particular form view of one
                                                             // submission = 0.
global $DB, $OUTPUT, $PAGE;
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);

$context = context_module::instance($id);
$isteacher = has_capability('mod/quest:manage', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassessments = get_string("assessments", "quest");

require_login($course->id, false, $cm);
$url = new moodle_url('/mod/quest/assessments.php', array('action' => $action, 'id' => $cm->id, 'sesskey' => sesskey()));
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

// ...display grading form (viewed by student) ..
if ($action == 'displaygradingform') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string("specimenassessmentformanswer", "quest"), 'specimenanswer', "quest");

    quest_print_assessment($quest, $sid, false, null);
    // ...called with no assessment..
    echo '<p>';
    if ($viewgeneral == 1) {
        echo $OUTPUT->continue_button(new moodle_url("view.php", array('id' => $id)));
    } else {
        if ($sid == '') {
            echo $OUTPUT->continue_button(new moodle_url("view.php", array('id' => $id)));
        } else {
            echo $OUTPUT->continue_button(
                    new moodle_url("submissions.php", array('id' => $cm->id, 'sid' => $sid, 'action' => 'showsubmission')));
        }
    }
    echo $OUTPUT->footer();

} else if ($action == 'editelements') {
    // ... edit assessment elements (for teachers)..
    require_sesskey();
    $authorid = isset($sid) ? $DB->get_field('quest_submissions', 'userid', array('id' => $sid)) : null;
    if (!$isteacher && $authorid != $USER->id) {
        print_error("Only teachers or author can look at this page");
    }
    // If the elements have not been defined for the questournament $newform=0..
    if ($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0)) == 0) {
        $newform = 0;
    } else {
        $newform = 1;
    }
    // ...set up heading, form and table..
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string("editingassessmentelements", "quest"), "elements", "quest");
    if (quest_count_submission_assessments($sid) > 0) {
        echo $OUTPUT->notification(get_string("warningonamendingelements", "quest"));
    }
    echo '<form name="form" method="post" action="assessments.php">';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" /> <input type="hidden" name="action" value="insertelements" />';
    echo '<center> <table cellpadding="5" border="1">';

    // Get existing elements, if none set up appropriate default ones..
    $elementstemplate = [];
    if ($sid) {
        $elementstemplate = $DB->get_records("quest_elements", array("questid" => $quest->id, "submissionsid" => $sid),
                "elementno ASC");
    }
    if (count($elementstemplate) == 0) {
        // Template elements.
        $elementstemplate = $DB->get_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0),
                "elementno ASC");
    }
    // Reindex the array.
    $elements = array_values($elementstemplate);

    $num = count($elements);
    if ($num == 0 && $DB->count_records('quest_elements', array('submissionsid' => $sid, 'questid' => $quest->id)) == 0) {
        $num = $quest->nelements;
    }
    if (($newform == 1) && ($changeform == 1)) {
        $num = $numelemswhenchange;
    }
    if ($sid) {
        $submissionnumelements = $DB->get_field("quest_submissions", "numelements", array("id" => $sid));
        if (($submissionnumelements != 0) && ($changeform == 0) && ($newform == 1)) {
            $num = $submissionnumelements;
        }
    }

    if ($newform == 0) {
        if ($changeform == true) {
            $num = $numelemswhenchange;
        } else {
            $num = $quest->nelements;
        }
    }
    // If form is to be empty create an empty element as template.
    $num = max([1, $num]);
    // ...check for missing elements (this happens either the first time round or when the number
    // of elements is increased)..
    for ($i = 0; $i < $num; $i++) {
        if (!isset($elements[$i])) {
            $elements[$i] = new stdClass();
            $elements[$i]->description = '';
            $elements[$i]->scale = 0;
            $elements[$i]->maxscore = 0;
            $elements[$i]->weight = 11;
        }
    }
    if (empty($elements[0]->description)) { // ...to return view.php when complete general elements.
                                           // ...the first time..
        $viewgeneral = 1;
    }
    // TODO: replace with quest_print_assessment from locallib.php!.
    switch ($quest->gradingstrategy) {
        case 0: // ...no grading..
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
                $DB->set_field("quest_submissions", "numelements", $num, array("id" => $sidtarget));
            } else if ($newform == 0) {
                $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                $DB->set_field("quest", "nelements", $num, array("id" => $var));
            }
            break;
        case 1: // ...accumulative grading..
                // ...set up scales name..
            $scales = [];
            foreach ($questscales as $key => $scale) {
                $scales[] = $scale['name'];
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
                echo html_writer::select($scales, "scale[]", $elements[$i]->scale, "");
                if ($elements[$i]->weight == '') { // ...not set.
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
            if ($newform == 1) {
                $DB->set_field("quest_submissions", "numelements", $num, array("id" => $sid));
            } else if ($newform == 0) {
                $var = $DB->get_field("course_modules", "instance", array("id" => $id));
                $DB->set_field("quest", "nelements", $num, array("id" => $var));
            }
            break;
        default:
            throw new InvalidArgumentException('Unknown grading strategy.');
    }
    // ...close table and form..
    if ($newform == 0) {
        $nf = 0;
    } else if ($newform == 1) {
        $nf = 1;
    }
    $stringsavechanges = get_string("savechanges");
    $stringcancel = get_string("cancel");
    $stringadd = get_string("addelement", 'quest');
    $stringremove = get_string("removeelement", 'quest');
    $numincr = $num + 1;
    $numdecr = $num - 1;
    $sesskey = sesskey();
    $formfragment = <<<FORM
</table>
<br />
<center>
	<input type="hidden" name="newform" value="$nf" /> <input
		type="hidden" name="sid" value="$sid" /> <input
		type="hidden" name="viewgeneral" value="$viewgeneral" />
	<input type="hidden" name="n_elem_when_change"
		value="$num" /> <input type="submit"
		value="$stringsavechanges" />
    <input type="submit"
		name="cancel" value="$stringcancel" /> <input
		type="hidden" name="sesskey" value="$sesskey" />
</center>

</form>
<center>
	<form ACTION="assessments.php">
		<input type="hidden" name="newform" value="$nf" /> <input
			type="hidden" name="change_form" value="1" /> <input type="hidden"
			name="id" value="$cm->id" /> <input type="hidden"
			name="sid" value="$sid" /> <input type="hidden"
			name="viewgeneral" value="$viewgeneral" /> <input
			type="hidden" name="num_elems_when_change"
			value="$numincr" /> <input type="hidden" name="action"
			value="editelements" /> <input type="submit"
			value="$stringadd" /> <input
			type="hidden" name="sesskey" value="$sesskey" />
	</form>

	<form ACTION="">
		<input type="hidden" name="newform" value="$nf" /> <input
			type="hidden" name="change_form" value="1" /> <input type="hidden"
			name="id" value="$cm->id" /> <input type="hidden"
			name="sid" value="$sid" /> <input type="hidden"
			name="viewgeneral" value="$viewgeneral" /> <input
			type="hidden" name="num_elems_when_change"
			value="$numdecr" /> <input type="hidden" name="action"
			value="editelements" /> <input type="submit"
			value="$stringremove" /> <input
			type="hidden" name="sesskey" value="$sesskey" />
	</form>
</center>
FORM;
    echo $formfragment;
    echo $OUTPUT->footer();

} else if ($action == 'insertelements') {
    if (!optional_param('cancel', null, PARAM_ALPHA)) {
        // ... insert/update assignment elements (for teachers)..
        require_sesskey();
        $authorid = $DB->get_field('quest_submissions', 'userid', array('id' => $sid));
        if (!$isteacher && $authorid != $USER->id) {
            print_error("Only teachers or author can look at this page");
        }
        // ...let's not fool around here, dump the junk!.
        if ($newform == 0) {
            $DB->delete_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0));
        } else {
            $DB->delete_records("quest_elements", array("questid" => $quest->id, "submissionsid" => $sid));
        }
        $descriptions = required_param_array('description', PARAM_RAW);
        $weights = optional_param_array('weight', null, PARAM_INT);
        $scales = optional_param_array('scale', null, PARAM_INT);

        // ...determine wich type of grading.
        switch ($quest->gradingstrategy) {
            case 0: // ...no grading.
                    // Insert all the elements that contain something.
                foreach ($descriptions as $key => $description) {
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
                            print_error('inserterror', 'quest', null, "quest_elements");
                        }
                    }
                }
                break;
            case 1: // ...accumulative grading.
                    // Insert all the elements that contain something.
                foreach ($descriptions as $key => $description) {
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
                        if (!$element->id = $DB->insert_record("quest_elements", $element)) {
                            print_error('inserterror', 'quest', null, "quest_elements");
                        }
                    }
                }
                break;
            default:
                throw new InvalidArgumentException('Unknown grading strategy.');
        } // ...end of switch.
        $msg = get_string("savedok", "quest");
    } else {
        $msg = '';
    }
    if ($viewgeneral == 1) {
        $urlto = new moodle_url("view.php", ['id' => $cm->id]);
    } else {
        $urlto = new moodle_url("submissions.php",
                ['id' => $cm->id, 'sid' => $sid, 'action' => 'showsubmission']);
    }
    redirect($urlto, $msg);

} else if ($action == 'updateassessment') {
    // Update assessment (by teacher or student)....
    $aid = required_param('aid', PARAM_INT);
    $sid = optional_param('sid', 0, PARAM_INT);
    require_sesskey();
    $answer = $DB->get_record("quest_answers", array("id" => $aid), '*', MUST_EXIST);
    $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id), '*', MUST_EXIST);
    $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);
    // Check access.
    if (!$isteacher && $USER->id != $submission->userid) {
        print_error('nopermissionassessment', 'quest');
    }
    $timenow = time();
    if ($quest->validateassessment == 1) {
        // ...necesita validar evaluacion.
        if ($isteacher) {
            // El profesor puede validar pasando a phase=1.
            if ($assessment->phase == ASSESSMENT_PHASE_APPROVAL_PENDING) { // ...contabiliza la
                                                                           // nueva.
                                                                           // ...evaluación.
                $assessment->phase = 1; // ...ya está validada ahora OJO: ¿se había sumado esta
                                        // nota?.
            }
            // END profesor valida....
        } else { // Si no es profesor la fase siempre será phase=0. La nota queda pendiente....
            if ($assessment->phase != ASSESSMENT_PHASE_APPROVAL_PENDING) {
                print_error('unkownactionerror', 'quest', null, 'Bad PHASE of assessment', "Error grave: no puede actualizar una evaluacion ya validada por el profesor.");
            }
        }
    } else { // Este QUEST no requiere validación....
        if ($assessment->phase == ASSESSMENT_PHASE_APPROVAL_PENDING) {
            $assessment->phase = ASSESSMENT_PHASE_APPROVED; // Pasa directamente a phase=1: validada.
        }
    }
    if ($answer->phase == ANSWER_PHASE_UNGRADED) {
        $answer->phase = ANSWER_PHASE_GRADED;
    }
    $recalification = false;
    $revision = false;
    // Determine what kind of grading we have.
    // ...and calculate grade as a percentage..
    // Manual grading....
    $manualgrade = optional_param('manualcalification', null, PARAM_ALPHANUM);
    if ($manualgrade != null) {
        // Grading manually!.
        $percent = ((int) $manualgrade) / 100;
    } else {
        // Form grading....
        // Grading by criteria!.
        $percent = quest_get_answer_grade($quest, $answer, optional_param_array('grade', [], PARAM_FLOAT),
                                                            optional_param_array('feedback', [], PARAM_TEXT));
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
        // ...hay respuestas correctas posteriores o no hay ninguna.
        // ...la actual es la nueva correcta y hay que recalificar el resto..
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
        // FIN comprobación respuestas correctas..
        $submission->points = $grade;
        // ...no hay resp.correctas y la evaluacion esta aprobada..
        if (($submission->nanswerscorrect == 0) && ($assessment->phase == ASSESSMENT_PHASE_APPROVED)) {
            $submission->dateanswercorrect = $answer->date;
            $submission->pointsanswercorrect = $points;
        }
        if (($answer->phase != ANSWER_PHASE_PASSED) && ($assessment->phase == ASSESSMENT_PHASE_APPROVED)) {
            $submission->nanswerscorrect++;
            $answer->phase = ANSWER_PHASE_PASSED;
        }
    } else { // La respuesta no ha aprobado..
        $submission->points = $grade;
        if ($answer->phase == 2) { // ...ya estaba calificada por lo que es una recalificacion..
            $submission->nanswerscorrect--;
        }
        $answer->phase = 1;

        if ($answer->date == $submission->dateanswercorrect) { // ...si es la primera correcta hay.
                                                               // ...que recalificar todas..
            $submission->nanswerscorrect = 0;
            $submission->dateanswercorrect = 0;
            $recalification = true; // ...recalifica todas..
        }
    }

    // Assesment->state.
    // ...0 sin realizar.
    // ...1 realizada autor.
    // ...2 realizada profesor.
    // ... // assessment->phase.
    // ...0 sin aprobar.
    // ...1 aprobada.
    $answer->pointsmax = number_format($points, 4); // ...weird bug with mysql if $points is double.
                                                    // ...of numeric..
                                                    // ...update the time of the assessment record.
                                                    // ...(may be re-edited)....
    $assessment->dateassessment = $timenow;

    // ...update submission.
    // ...get first answer correct.
    // ...update pointsanswercorrect..
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
     * 2 modificada (evaluada manualmente?) //evp this should be clearly defined
     * answer->phase
     * 0 sin evaluar
     * 1 evaluada
     * 2 aprobada (evaluada >50%)
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
    // ...state 0 no realizada 1 por autor 2 por profesor..
    if ($isteacher) {
        $assessment->state = ASSESSMENT_STATE_BY_TEACHER;
    } else {
        $assessment->state = ASSESSMENT_STATE_BY_AUTOR;
    }
    // ...any comment?.
    $generalcomment = optional_param('generalcomment', null, PARAM_TEXT);
    if (!empty($generalcomment)) {
        $assessment->commentsteacher = $generalcomment;
    }
    $generalteachercomment = optional_param('generalteachercomment', null, PARAM_TEXT);
    if (!empty($generalteachercomment)) {
        $assessment->commentsforteacher = $generalteachercomment;
    }

    $DB->update_record('quest_answers', $answer);
    quest_update_submission($submission);
    quest_update_assessment($assessment);
    quest_update_submission_counts($submission->id);
    // ...points recalculation..
    $recalification = true; // ...To disable this optimization it's not worth as evaluation is not
                            // a.
                            // ...frequent action..
                            // ...Recalcula los puntos de las respuestas del quest..
    if ($recalification) {
        quest_update_grade_for_answer($answer, $submission, $quest, $course);
    }
    $userid = $answer->userid;
    // ...recalculate points and report to gradebook..
    quest_grade_updated($quest, $userid);
    // NOTIFICATIONS..
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
            global $OUTPUT;
            echo $OUTPUT->heading("nostudentsyet");
            echo $OUTPUT->footer();
            exit();
        }
        // JPC 2013-11-28 disable excesive notifications..
        if (false) {
            foreach ($users as $user) {
                if (has_capability('mod/quest:manage', $context, $user->id)) {
                    quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment',
                            $quest, $submission, $answer);
                }
            }
        }
    }
    // Log the event.
    if ($CFG->version >= 2014051200) {
        require_once('classes/event/answer_assessed.php');
        \mod_quest\event\answer_assessed::create_from_parts($submission, $answer, $assessment, $cm)->trigger();
    } else {
        add_to_log($course->id, "quest", "assess_answer", "viewassessment.php?id=$cm->id&amp;asid=$assessment->id",
                "$assessment->id", "$cm->id");
    }
    // ...set up return address..
    $returnto = optional_param('returnto', "view.php?id=$cm->id", PARAM_URL);
    // ...show grade if grading strategy is not zero..
    if ($quest->gradingstrategy) {
        $msg = get_string("thegradeis", "quest") . ": " . number_format($grade, 4) . " (" . get_string("maximumgrade") .
                " " . number_format($points, 4) . ")";
    } else {
        $msg = "";
    }
    redirect($returnto, $msg);
} else {
    print_error('unkownactionerror', 'quest', null, $action);
}
