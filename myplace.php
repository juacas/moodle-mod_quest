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
 */
require_once("../../config.php");
require_once("lib.php");
require("locallib.php");

$id = required_param('id', PARAM_INT);    // Course Module ID
$action = optional_param('action', '', PARAM_ALPHA);
$sort = optional_param('sort', 'datestart', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

$sortanswer = optional_param('sortanswer', 'dateanswer', PARAM_ALPHA);
$diranswer = optional_param('diranswer', 'DESC', PARAM_ALPHA);

global $DB, $OUTPUT, $PAGE;
$timenow = time();

list($course,$cm)=quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance),'*',MUST_EXIST);
require_login($course->id, false, $cm);

$url = new moodle_url('/mod/quest/myplace.php',
        array('id' => $id, 'action' => $action, 'sort' => $sort, 'dir' => $dir, 'sortanswer' => $sortanswer, 'diranswer' => $diranswer));
$PAGE->set_url($url);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
// Print the page header.
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$straction = ($action) ? '-> ' . get_string($action, 'quest') : '-> ' . get_string('myplace', 'quest');
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, $_POST['userpassword']);
}

$changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
$groupmode = groups_get_activity_group($cm);   // Groups are being used?
$currentgroup = groups_get_course_group($course);
$groupmode = $currentgroup = false; //JPC group support desactivation in this version.
// Allow the teacher to change groups (for this session).
if ($groupmode and $ismanager) {
    if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {

        groups_print_activity_menu($cm, $CFG->wwwroot . "mod/quest/myplace.php?id=$cm->id", $return = false,
                $hideallparticipants = false); //evp revise this
    }
}

$title = get_string('myplace', 'quest',$quest);
echo $OUTPUT->heading_with_help($title, "myplace", "quest");

$text = '';
$text = "<center><b>";
if ($quest->dateend > $timenow) {
    $text .= "<a href=\"submissions.php?action=submitchallenge&amp;id=$cm->id\">" .
            get_string('addsubmission', 'quest') . "</a>";
}
if ($quest->allowteams) {
    if ($ismanager) {
        $text .= "&nbsp;/&nbsp;<a href=\"team.php?id=$cm->id\">" . get_string('changeteamteacher', 'quest') . "</a>";
    }
}

$text .= "&nbsp;/&nbsp;<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
        get_string('viewclasificationglobal', 'quest') . "</a>";

if ((!$ismanager) && ($quest->allowteams)) {
    $text .= "&nbsp;/&nbsp;<a href=\"viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
            get_string('viewclasificationteams', 'quest') . "</a>";
}
$text .= "</b></center>";
echo $text;

if ($ismanager and $quest->nelements) {
    echo "<center>(<a href=\"assessments_autors.php?id=$cm->id&amp;action=editelements\">"
    . get_string('editelementsautor', 'quest') . "</a> / <a href=\"assessments.php?id=$cm->id&newform=0&amp;action=editelements\">" . get_string('editelementsanswer',
            'quest') . "</a>)</center>";
}

$title = get_string('mysubmissions', 'quest');
echo $OUTPUT->heading($title);

// Get all the students
if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
    echo $OUTPUT->heading(get_string("nostudentsyet"));
    echo $OUTPUT->footer();
    exit;
}
?>
<script language="JavaScript">

    function redondear(cantidad, decimales) {
        var cantidad = parseFloat(cantidad);
        var decimales = parseFloat(decimales);
        decimales = (!decimales ? 2 : decimales);
        var valor = Math.round(cantidad * Math.pow(10, decimales)) / Math.pow(10, decimales);
        return valor.toFixed(4);
    }
    var servertime =<?php echo time() * 1000; ?>;
    var browserDate = new Date();
    var browserTime = browserDate.getTime();
    var correccion = servertime - browserTime;
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
$table = new html_table();
$table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
$columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');

$table->width = "95%";
$indice = 0;


if ($submissions = quest_get_user_submissions($quest, $USER)) {
    foreach ($submissions as $submission) {
        $data = array();
        $sortdata = array();

        if ($submission->userid == $USER->id) {

            if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
                $submission->phase = SUBMISSION_PHASE_ACTIVE;
            }

            if ($ismanager) {
                $data[] = quest_print_submission_title($quest, $submission) .
                        " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                        " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('delete', 'quest') . '" /></a>';
                $sortdata['title'] = strtolower($submission->title);
            } else if (($submission->nanswers == 0)and ( $timenow < $submission->dateend)and ( $submission->state < 2)) {

                $data[] = quest_print_submission_title($quest, $submission) .
                        " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                        " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('delete', 'quest') . '" /></a>';
                $sortdata['title'] = strtolower($submission->title);
            } else {
                $data[] = quest_print_submission_title($quest, $submission);
                $sortdata['title'] = strtolower($submission->title);
            }

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
                $image = " <img src=\"" . $CFG->wwwroot . "/pix/t/clear.png\" />";
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


            $columns[] = 'grade';
            $table->align[] = 'center';
            if ($submission->evaluated != 0 && $assessment = $DB->get_record("quest_assessments_autors",
                    array("questid" => $quest->id,
                "submissionid" => $submission->id))) {
                if ($submission->pointsanswercorrect > 0) {
                    $data[] = number_format($assessment->points * 100 / $submission->pointsanswercorrect, 1) . '% (' . number_format($assessment->points,
                                    4) . ')';
                } else {
                    $data[] = ''; //evp check this, to avoid division by 0
                }
                $sortdata['grade'] = $submission->points;
            } else {
                $data[] = get_string("evaluation_pending", "quest");
            }

            $indice++;

            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
    }
}

uasort($tablesort->sortdata, 'quest_sortfunction');
$table->data = array();
foreach ($tablesort->sortdata as $key => $row) {
    $table->data[] = $tablesort->data[$key];
}



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
        $columnicon = " <img src=\"" . $CFG->wwwroot . "/pix/t/$columnicon.png\" alt=\"$columnicon\" />";
    }
    $$column = "<a href=\"myplace.php?id=$id&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
}

$table->head = array("$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart", "$dateend", /* get_string('actions','quest'), */ "$calification", "Grade");



echo '<tr><td>';
echo html_writer::table($table);
$clear_icon = $OUTPUT->pix_icon('t/check' ,'');

echo "<center>";
echo get_string('legend', 'quest', $clear_icon);
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

echo '</td></tr>';


$title = get_string('myanswers', 'quest');
echo $OUTPUT->heading($title);

if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
    echo $OUTPUT->heading(get_string("nostudentsyet"));
    echo $OUTPUT->footer();
    exit;
}

// Now prepare table with student assessments and submissions
$tablesort->data = array();
$tablesort->sortdata = array();

if ($answers = $DB->get_records_select("quest_answers", "questid=? AND userid=?", array($quest->id, $USER->id))) {
    foreach ($answers as $answer) {
        $data = array();
        $sortdata = array();

        $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid));

        if ($answer->userid == $USER->id) {

            if ($ismanager) {
                $data[] = quest_print_answer_title($quest, $answer, $submission) .
                        " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                        " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id&amp;aid=$answer->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('delete', 'quest') . '" /></a>';

                $sortdata['title'] = strtolower($answer->title);
            } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase == 0)) {
                $data[] = quest_print_answer_title($quest, $answer, $submission) .
                        " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>' .
                        " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id&amp;aid=$answer->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('delete', 'quest') . '" /></a>';

                $sortdata['title'] = strtolower($answer->title);
            } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase > 0) && ($answer->permitsubmit == 1)) {
                $data[] = quest_print_answer_title($quest, $answer, $submission) .
                        " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                        "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " .
                        'height="11" width="11" border="0" alt="' . get_string('modif', 'quest') . '" /></a>';

                $sortdata['title'] = strtolower($answer->title);
            } else {
                $data[] = quest_print_answer_title($quest, $answer, $submission);
                $sortdata['title'] = strtolower($answer->title);
            }

            $data[] = userdate($answer->date, get_string('datestr', 'quest'));
            //$sortdata['dateanswer'] = $submission->date;
            $sortdata['dateanswer'] = $answer->date;

            if (($answer->phase == 1) || ($answer->phase == 2)) {
                $assessment = $DB->get_record("quest_assessments", array('answerid' => $answer->id));
            } else {
                $assessment = null;
            }

            $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
            $sortdata['tassmnt'] = 1;

            $grade = number_format(quest_answer_grade($quest, $answer, 'ALL'), 4) . ' [max ' . number_format($answer->pointsmax, 4) . ']';
            $data[] = $grade;
            $sortdata['calification'] = quest_answer_grade($quest, $answer, 'ALL');

            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
    }
}

function quest_sortfunction_answers($a, $b) {
    global $sortanswer, $diranswer;
    if ($diranswer == 'ASC') {
        return ($a[$sortanswer] > $b[$sortanswer]);
    } else {
        return ($a[$sortanswer] < $b[$sortanswer]);
    }
}

uasort($tablesort->sortdata, 'quest_sortfunction_answers');
$table->data = array();
foreach ($tablesort->sortdata as $key => $row) {
    $table->data[] = $tablesort->data[$key];
}


$table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
$columnsanswer = array('title', 'dateanswer', 'actions', 'calification');

$table->width = "95%";

foreach ($columnsanswer as $columnanswer) {
    $string[$columnanswer] = get_string("$columnanswer", 'quest');
    if ($sortanswer != $columnanswer) {
        $columniconanswer = '';
        $columndiranswer = 'ASC';
    } else {
        $columndiranswer = $diranswer == 'ASC' ? 'DESC' : 'ASC';
        if ($columnanswer == 'lastaccess') {
            $columniconanswer = $diranswer == 'ASC' ? 'up' : 'down';
        } else {
            $columniconanswer = $diranswer == 'ASC' ? 'down' : 'up';
        }
        $columniconanswer = " <img src=\"" . $CFG->wwwroot . "/pix/t/$columniconanswer.png\" alt=\"$columniconanswer\" />";
    }
    $$columnanswer = "<a href=\"myplace.php?id=$cm->id&amp;sortanswer=$columnanswer&amp;diranswer=$columndiranswer\">" . $string[$columnanswer] . "</a>$columniconanswer";
}


$table->head = array("$title", "$dateanswer", get_string('actions', 'quest'), "$calification");


echo '<tr><td>';
echo html_writer::table($table);
echo '</td></tr>';

if (!$ismanager) {

    $title = get_string('mycalification', 'quest');
    echo $OUTPUT->heading($title);

    $tablesort->data = array();
    $tablesort->sortdata = array();

    if ($clasification = $DB->get_record("quest_calification_users", array("questid" => $quest->id, "userid" => $USER->id))) {


        $data = array();
        $sortdata = array();

        $data[] = $OUTPUT->user_picture($USER, array('courseid' => $course->id));
        $sortdata['picture'] = 1;

        $data[] = "<a name=\"userid$USER->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$USER->id&amp;course=$course->id\">" .
                fullname($USER) . '</a>';
        $sortdata['firstname'] = $USER->firstname;
        $sortdata['lastname'] = $USER->lastname;

        $data[] = $clasification->nanswers;
        $sortdata['nanswers'] = $clasification->nanswers;

        $data[] = $clasification->nanswersassessment;
        $sortdata['nanswersassessment'] = $clasification->nanswersassessment;

        $data[] = $clasification->nsubmissions;
        $sortdata['nsubmissions'] = $clasification->nsubmissions;

        $data[] = $clasification->nsubmissionsassessment;
        $sortdata['nsubmissionsassessment'] = $clasification->nsubmissionsassessment;

        $data[] = $clasification->pointssubmission;
        $sortdata['pointssubmission'] = $clasification->nsubmissions;

        $data[] = $clasification->pointsanswers;
        $sortdata['pointsanswers'] = $clasification->pointsanswers;

        if ($quest->allowteams) {
            if ($clasification_team = $DB->get_record("quest_calification_teams",
                    array("teamid" => $clasification->teamid, "questid" => $quest->id))) {
                $data[] = $clasification_team->points * $quest->teamporcent / 100;
                $sortdata['pointsteam'] = $clasification_team->points * $quest->teamporcent / 100;

                $data[] = $clasification->points + $clasification_team->points * $quest->teamporcent / 100;
                $sortdata['points'] = $clasification->points + $clasification_team->points * $quest->teamporcent / 100;
            }
        } else {
            $data[] = $clasification->points;
            $sortdata['points'] = $clasification->points;
        }

        $tablesort->data[] = $data;
        $tablesort->sortdata[] = $sortdata;
    }

    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }


    $table->align = array('left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->valign = array('center', 'center', 'center', 'center', 'left', 'center', 'center', 'center', 'center', 'center', 'center');

    if ($quest->allowteams) {
        $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment', 'nsubmissions', 'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'pointsteam', 'points');
    } else {
        $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment', 'nsubmissions', 'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'points');
    }

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
            $columnicon = " <img src=\"" . $CFG->wwwroot . "/pix/t/$columnicon.png\" alt=\"$columnicon\" />";
        }
        $$column = "<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }


    if ($quest->allowteams) {
        $table->head = array("",
            get_string('firstname', 'quest') . '/' . get_string('lastname', 'quest'),
            get_string('nanswers','quest'),
            get_string('nanswersassessment', 'quest'),
            get_string('nsubmissions', 'quest'),
            get_string('nsubmissionsassessment','quest'),
            get_string('pointssubmission', 'quest'),
            get_string('pointsanswers', 'quest'),
            get_string('pointsteam','quest'),
            get_string('points', 'quest'));
    } else {

        $table->head = array("",
                            get_string('firstname', 'quest') . '/' . get_string('lastname', 'quest'),
                            get_string('nanswers','quest'),
                            get_string('nanswersassessment', 'quest'),
                            get_string('nsubmissions', 'quest'),
                            get_string('nsubmissionsassessment','quest'),
                            get_string('pointssubmission', 'quest'),
                            get_string('pointsanswers', 'quest'),
                            get_string('points','quest'));
    }
    echo '<tr><td>';
    echo '<div valign="center">';
    echo html_writer::table($table);
    echo '</div>';
    echo '</td></tr>';
}
echo '<tr><td>';
echo '</td></tr>';
if ((!$ismanager) && ($quest->allowteams)) {

    $title = get_string('mycalificationteam', 'quest');
    $OUTPUT->heading($title);

    // Now prepare table with student assessments and submissions.
    $tablesort->data = array();
    $tablesort->sortdata = array();
    if ($clasification_user = $DB->get_record("quest_calification_users", array("userid" => $USER->id, "questid" => $quest->id))) {
        if ($calification_team = $DB->get_record("quest_calification_teams",
                array("teamid" => $clasification_user->teamid, "questid" => $quest->id))) {

            if ($team = $DB->get_record("quest_teams", array("id" => $calification_team->teamid))) {

                $data = array();
                $sortdata = array();

                $data[] = $team->name;
                $sortdata['team'] = $team->name;

                $data[] = $calification_team->nanswers;
                $sortdata['nanswers'] = $calification_team->nanswers;

                $data[] = $calification_team->nanswerassessment;
                $sortdata['nanswersassessment'] = $calification_team->nanswerassessment;

                $data[] = $calification_team->nsubmissions;
                $sortdata['nsubmissions'] = $calification_team->nsubmissions;

                $data[] = $calification_team->nsubmissionsassessment;
                $sortdata['nsubmissionsassessment'] = $calification_team->nsubmissionsassessment;

                $data[] = $calification_team->pointssubmission;
                $sortdata['pointssubmission'] = $calification_team->pointssubmission;

                $data[] = $calification_team->pointsanswers;
                $sortdata['pointsanswers'] = $calification_team->pointsanswers;

                $data[] = $calification_team->points;
                $sortdata['points'] = $calification_team->points;

                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }
        }
    }


    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }


    $table->align = array('left', 'center', 'center', 'left', 'center', 'center', 'center', 'center', 'center', 'center');
    $columns = array('team', 'nanswers', 'nanswersassessment', 'nsubmissions', 'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'points');

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
            $columnicon = " <img src=\"" . $CFG->wwwroot . "/pix/t/$columnicon.png\" alt=\"$columnicon\" />";
        }
        $$column = "<a href=\"view.php?id=$id&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    $table->head = array(get_string('team', 'quest'), get_string('nanswers', 'quest'), get_string('nanswersassessment', 'quest'), get_string('nsubmissions',
                'quest'), get_string('nsubmissionsassessment', 'quest'), get_string('pointssubmission', 'quest'), get_string('pointsanswers',
                'quest'), get_string('points', 'quest'));


    echo '<tr><td>';
    echo html_writer::table($table);
    echo '</td></tr>';
    echo '<tr><td>';

    echo '</td></tr>';
}

echo '</table>';
if ($REPEAT_ACTIONS_BELOW) {
    $text = '';
    $text = "<center><b>";
    if ($quest->dateend > $timenow) {
        $text .= "<a href=\"submissions.php?action=submitchallenge&amp;id=$cm->id\">" .
                get_string('addsubmission', 'quest') . "</a>";
    }
    if ($quest->allowteams) {
        if ($ismanager) {
            $text .= "&nbsp;/&nbsp;<a href=\"team.php?id=$cm->id\">" . get_string('changeteamteacher', 'quest') . "</a>";
        }
    }

    $text .= "&nbsp;/&nbsp;<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
            get_string('viewclasificationglobal', 'quest') . "</a>";

    if ((!$ismanager) && ($quest->allowteams)) {
        $text .= "&nbsp;/&nbsp;<a href=\"viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
                get_string('viewclasificationteams', 'quest') . "</a>";
    }
    $text .= "</b></center>";
    echo $text;

    if (isteacheredit($course->id) and $quest->nelements) {
        echo "<center>(<a href=\"assessments_autors.php?id=$cm->id&amp;action=editelements\">"
        . get_string('editelementsautor', 'quest') . "</a> / <a href=\"assessments.php?id=$cm->id&amp;action=editelements\">" . get_string('editelementsanswer',
                'quest') . "</a>)</center>";
    }
}

echo $OUTPUT->continue_button('view.php?id=' . $cm->id);

// Finish the page.
echo $OUTPUT->footer();
