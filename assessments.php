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
 * Display and save assessment forms for Quest answers.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
global $DB, $OUTPUT, $PAGE, $questscales, $questeweights;
if (!is_array($questscales)) {
    $questscales = quest_get_default_scales();
}
if (!is_array($questeweights)) {
    $questeweights = quest_get_default_weights();
}
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", ["id" => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);
$isteacher = has_capability('mod/quest:manage', $context);

$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strassessments = get_string("assessments", "quest");

require_login($course->id, false, $cm);
$url = new moodle_url('/mod/quest/assessments.php', ['action' => $action, 'id' => $cm->id, 'sesskey' => sesskey()]);
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
$PAGE->set_activity_record($quest);
$PAGE->activityheader->set_attrs([
    'description' => quest_get_activity_header_description($quest, $cm, $context),
]);

// ...display grading form (viewed by student) ..
if ($action == 'displaygradingform') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string("specimenassessmentformanswer", "quest"), 'specimenanswer', "quest");

    if ($isteacher) {
        $editurl = new moodle_url('/mod/quest/assessments.php', [
            'id' => $cm->id,
            'viewgeneral' => 1,
            'action' => 'editelements',
            'sesskey' => sesskey(),
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

    quest_print_assessment($quest, $sid, false, false);
    // ...called with no assessment..
    echo '<p>';
    if ($viewgeneral == 1) {
        echo $OUTPUT->continue_button(new moodle_url("view.php", ['id' => $id]));
    } else {
        if ($sid == '') {
            echo $OUTPUT->continue_button(new moodle_url("view.php", ['id' => $id]));
        } else {
            echo $OUTPUT->continue_button(
                    new moodle_url("challenges.php", ['id' => $cm->id, 'cid' => $sid, 'action' => 'showchallenge']));
        }
    }
    echo $OUTPUT->footer();

} else if ($action == 'editelements') {
    // ... edit assessment elements (for teachers)..
    require_sesskey();
    $authorid = isset($sid) ? $DB->get_field('quest_submissions', 'userid', ['id' => $sid]) : null;
    if (!$isteacher && $authorid != $USER->id) {
        throw new \moodle_exception('nopermissions', 'error', '', "Only teachers or author can look at this page");
    }
    // If the elements have not been defined for the questournament $newform=0..
    if ($DB->count_records("quest_elements", ["questid" => $quest->id, "submissionsid" => 0]) == 0) {
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
        $elementstemplate = $DB->get_records("quest_elements", ["questid" => $quest->id, "submissionsid" => $sid],
                "elementno ASC");
    }
    if (count($elementstemplate) == 0) {
        // Template elements.
        $elementstemplate = $DB->get_records("quest_elements", ["questid" => $quest->id, "submissionsid" => 0],
                "elementno ASC");
    }
    // Reindex the array.
    $elements = array_values($elementstemplate);

    $num = count($elements);
    if ($num == 0 && $DB->count_records('quest_elements', ['submissionsid' => $sid, 'questid' => $quest->id]) == 0) {
        $num = $quest->nelements;
    }
    if (($newform == 1) && ($changeform == 1)) {
        $num = $numelemswhenchange;
    }
    if ($sid) {
        $submissionnumelements = $DB->get_field("quest_submissions", "numelements", ["id" => $sid]);
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
    $nf = !empty($newform) ? 1 : 0;
    $stringsavechanges = get_string("savechanges");
    $stringcancel = get_string("cancel");
    $stringadd = get_string("addelement", 'quest');
    $stringremove = get_string("removeelement", 'quest');
    $numincr = $num + 1;
    $numdecr = $num - 1;
    $sesskey = sesskey();

    echo '<div class="quest-assessment-container my-4">';

    // Element count and Add / Remove toolbar.
    echo '<div class="card shadow-sm border-0 bg-light p-3 mb-4">';
    echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
    echo '<div class="text-muted">';
    echo '<i class="fa fa-list-ol me-1" aria-hidden="true"></i>'
            . get_string('elements', 'quest')
            . ': <strong class="badge bg-primary fs-7 ms-1">' . $num . '</strong>';
    echo '</div>';
    echo '<div class="d-flex gap-2">';

    // Add element.
    echo '<form action="assessments.php" method="get" class="d-inline m-0">';
    echo '<input type="hidden" name="newform" value="' . $nf . '" />';
    echo '<input type="hidden" name="change_form" value="1" />';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
    echo '<input type="hidden" name="sid" value="' . s($sid) . '" />';
    echo '<input type="hidden" name="viewgeneral" value="' . s($viewgeneral) . '" />';
    echo '<input type="hidden" name="num_elems_when_change" value="' . $numincr . '" />';
    echo '<input type="hidden" name="action" value="editelements" />';
    echo '<input type="hidden" name="sesskey" value="' . $sesskey . '" />';
    echo '<button type="submit" class="btn btn-outline-success btn-sm">'
            . '<i class="fa fa-plus me-1" aria-hidden="true"></i>' . $stringadd . '</button>';
    echo '</form>';

    if ($num > 1) {
        // Remove element.
        echo '<form action="assessments.php" method="get" class="d-inline m-0">';
        echo '<input type="hidden" name="newform" value="' . $nf . '" />';
        echo '<input type="hidden" name="change_form" value="1" />';
        echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
        echo '<input type="hidden" name="sid" value="' . s($sid) . '" />';
        echo '<input type="hidden" name="viewgeneral" value="' . s($viewgeneral) . '" />';
        echo '<input type="hidden" name="num_elems_when_change" value="' . $numdecr . '" />';
        echo '<input type="hidden" name="action" value="editelements" />';
        echo '<input type="hidden" name="sesskey" value="' . $sesskey . '" />';
        echo '<button type="submit" class="btn btn-outline-danger btn-sm">'
                . '<i class="fa fa-minus me-1" aria-hidden="true"></i>' . $stringremove . '</button>';
        echo '</form>';
    }
    echo '</div></div></div>';

    // Main edit form.
    echo '<form name="form" method="post" action="assessments.php">';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
    echo '<input type="hidden" name="action" value="insertelements" />';
    echo '<input type="hidden" name="newform" value="' . $nf . '" />';
    echo '<input type="hidden" name="sid" value="' . s($sid) . '" />';
    echo '<input type="hidden" name="viewgeneral" value="' . s($viewgeneral) . '" />';
    echo '<input type="hidden" name="n_elem_when_change" value="' . $num . '" />';
    echo '<input type="hidden" name="sesskey" value="' . $sesskey . '" />';

    // Ensure $questscales is populated.
    if (!is_array($questscales)) {
        $questscales = quest_get_default_scales();
    }
    $scales = [];
    foreach ($questscales as $key => $scale) {
        $scales[] = $scale['name'];
    }

    // Render criterion cards.
    for ($i = 0; $i < $num; $i++) {
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
        quest_print_editor("description[$i]", "id_desc_$i", $elements[$i]->description, $context, 3);
        echo '</div>';

        if ($quest->gradingstrategy == 1) { // Accumulative.
            echo '<div class="row g-3">';
            echo '<div class="col-md-6">';
            echo '<label class="form-label fw-semibold text-secondary">' . get_string('typeofscale', 'quest') . '</label>';
            echo html_writer::select($scales, "scale[]", $elements[$i]->scale, false, ['class' => 'form-select']);
            echo '</div>';
            if ($elements[$i]->weight == '') {
                $elements[$i]->weight = 11;
            }
            echo '<div class="col-md-6">';
            echo '<label class="form-label fw-semibold text-secondary">' . get_string('elementweight', 'quest') . '</label>';
            echo html_writer::select($questeweights, "weight[]", $elements[$i]->weight, false, ['class' => 'form-select']);
            echo '</div>';
            echo '</div>';
        }
        echo '</div></div>';
    }

    if ($newform == 1) {
        $DB->set_field("quest_submissions", "numelements", $num, ["id" => $sid]);
    } else if ($newform == 0) {
        $var = $DB->get_field("course_modules", "instance", ["id" => $id]);
        $DB->set_field("quest", "nelements", $num, ["id" => $var]);
    }

    // Sticky action bar.
    echo '<div class="quest-action-bar-sticky d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">';
    echo '<div class="d-flex gap-2">';
    echo '<button type="submit" class="btn btn-primary px-4">'
            . '<i class="fa fa-floppy-o me-1" aria-hidden="true"></i>' . $stringsavechanges . '</button>';
    echo '<button type="submit" name="cancel" value="1" class="btn btn-outline-secondary px-3">'
            . '<i class="fa fa-times me-1" aria-hidden="true"></i>' . $stringcancel . '</button>';
    echo '</div>';
    echo '</div>';

    echo '</form>';
    echo '</div>'; // Quest assessment container.
    echo $OUTPUT->footer();

} else if ($action == 'insertelements') {
    if (!optional_param('cancel', null, PARAM_ALPHA)) {
        // ... insert/update assignment elements (for teachers)..
        require_sesskey();
        $authorid = $DB->get_field('quest_submissions', 'userid', ['id' => $sid]);
        if (!$isteacher && $authorid != $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', "Only teachers or author can look at this page");
        }
        // ...let's not fool around here, dump the junk!.
        if ($newform == 0) {
            $DB->delete_records("quest_elements", ["questid" => $quest->id, "submissionsid" => 0]);
        } else {
            $DB->delete_records("quest_elements", ["questid" => $quest->id, "submissionsid" => $sid]);
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
                        $element = new stdClass();
                        $element->description = $description;
                        $element->questid = $quest->id;
                        if ($newform == 0) {
                            $element->submissionsid = 0;
                        } else if ($newform == 1) {
                            $element->submissionsid = $sid;
                        }
                        $element->elementno = $key;
                        if (!$element->id = $DB->insert_record("quest_elements", $element)) {
                            throw new \moodle_exception('inserterror', 'quest', '', "quest_elements");
                        }
                    }
                }
                break;
            case 1: // ...accumulative grading.
                    // Insert all the elements that contain something.
                foreach ($descriptions as $key => $description) {
                    if ($description) {
                        $element = new stdClass();
                        $element->description = $description;
                        $element->questid = $quest->id;
                        if ($newform == 1) {
                            $element->submissionsid = $sid;
                        } else if (($newform == 0) || (($DB->count_records("quest_elements",
                                ["questid" => $quest->id, "submissionsid" => 0]) == 0))) {
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
                            throw new \moodle_exception('inserterror', 'quest', '', "quest_elements");
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
        $urlto = new moodle_url("challenges.php",
                ['id' => $cm->id, 'cid' => $sid, 'action' => 'showchallenge']);
    }
    redirect($urlto, $msg);

} else if ($action == 'updateassessment') {
    // Update assessment (by teacher or student)....
    $aid = required_param('aid', PARAM_INT);
    $sid = optional_param('sid', 0, PARAM_INT);
    require_sesskey();
    $answer = $DB->get_record("quest_answers", ["id" => $aid], '*', MUST_EXIST);
    $assessment = $DB->get_record("quest_assessments", ["answerid" => $answer->id], '*', MUST_EXIST);
    $submission = $DB->get_record("quest_submissions", ["id" => $answer->submissionid], '*', MUST_EXIST);
    // Check access.
    if (!$isteacher && $USER->id != $submission->userid) {
        throw new \moodle_exception('nopermissionassessment', 'quest');
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
                throw new \moodle_exception(
                    'unknownactionerror',
                    'quest',
                    '',
                    'Bad PHASE of assessment',
                    'Error grave: no puede actualizar una evaluacion ya validada por el profesor.'
                );
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
    if ($query = $DB->get_record_select("quest_answers", "submissionid=? and grade>=50", [$submission->id], "date,pointsmax",
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
    }
    // Log the event.
    \mod_quest\event\answer_assessed::create_from_parts($submission, $answer, $assessment, $cm)->trigger();
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
    throw new \moodle_exception('unknownactionerror', 'quest', '', $action);
}
