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
 * @package mod_quest */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT); // Course Module ID.
$action = optional_param('action', '', PARAM_ALPHA);
$sort = optional_param('sort', 'datestart', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

$sortanswer = optional_param('sortanswer', 'dateanswer', PARAM_ALPHA);
$diranswer = optional_param('diranswer', 'DESC', PARAM_ALPHA);

global $DB, $OUTPUT, $PAGE;
$timenow = time();

list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
require_login($course->id, false, $cm);

$url = new moodle_url('/mod/quest/myplace.php',
        array('id' => $id, 'action' => $action, 'sort' => $sort, 'dir' => $dir, 'sortanswer' => $sortanswer,
                        'diranswer' => $diranswer));
$PAGE->set_url($url);

quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$canpreview = has_capability('mod/quest:preview', $context);
// Print the page header.
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$straction = ($action) ? '-> ' . get_string($action, 'quest') : '-> ' . get_string('myplace', 'quest');
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, required_param('userpassword', PARAM_RAW_TRIMMED));
}

$changegroup = optional_param('group', -1, PARAM_INT); // Group change requested?
$groupmode = groups_get_activity_group($cm); // Groups are being used?
$currentgroup = groups_get_course_group($course);
$groupmode = $currentgroup = false; // JPC group support desactivation in this version.
                                    // Allow the teacher to change groups (for this session).
if ($groupmode and $ismanager) {
    if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {

        groups_print_activity_menu($cm, $CFG->wwwroot . "mod/quest/myplace.php?id=$cm->id", $return = false,
                $hideallparticipants = false);
    }
}

$title = get_string('myplace', 'quest', $quest);
echo $OUTPUT->heading_with_help($title, "myplace", "quest");

$text = '';
$text = "<center><b>";
if ($quest->dateend > $timenow) {
    $text .= "<a href=\"submissions.php?action=submitchallenge&amp;id=$cm->id\">" . get_string('addsubmission', 'quest') . "</a>";
}
if ($quest->allowteams) {
    if ($ismanager) {
        $text .= "&nbsp;/&nbsp;<a href=\"team.php?id=$cm->id\">" . get_string('changeteamteacher', 'quest') . "</a>";
    }
}

$text .= "&nbsp;<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
         get_string('viewclasificationglobal', 'quest') . "</a>";

if ((!$canpreview) && ($quest->allowteams)) {
    $text .= "&nbsp;<a href=\"viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
             get_string('viewclasificationteams', 'quest') . "</a>";
}
$text .= "</b></center>";
echo $text;

echo "<center><b>";
quest_print_challenge_grading_link($cm, $context, $quest);
quest_print_answer_grading_link($cm, $context, $quest);
echo "</center>";
$title = get_string('mychallenges', 'quest');
echo $OUTPUT->heading_with_help($title, 'mychallenges', 'quest');

// Get all the students.
if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
    echo $OUTPUT->heading(get_string("nostudentsyet"));
    echo $OUTPUT->footer();
    exit();
}

// Now prepare table with student assessments and submissions...
$tablesort = new stdClass();
$tablesort->data = array();
$tablesort->sortdata = array();
$table = new html_table();
$table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
$columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort',
                'nanswerswhithoutassess', 'datestart', 'dateend', 'calification');

$indice = 0;

if ($submissions = quest_get_user_submissions($quest, $USER)) {
    foreach ($submissions as $submission) {
        $data = array();
        $sortdata = array();

        if ($submission->userid == $USER->id) {

            if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
                     ($submission->nanswerscorrect < $quest->nmaxanswers)) {
                $submission->phase = SUBMISSION_PHASE_ACTIVE;
            }

            if ($canpreview) {
                $data[] = quest_print_submission_title($quest, $submission) .
                         " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" . "<img src=\"" .
                         $CFG->wwwroot . "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' .
                         get_string('modif', 'quest') . '" /></a>' .
                         " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                         "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " . 'height="11" width="11" border="0" alt="' .
                         get_string('delete', 'quest') . '" /></a>';
                $sortdata['title'] = strtolower($submission->title);
            } else if (($submission->nanswers == 0) and ($timenow < $submission->dateend) and ($submission->state < 2)) {

                $data[] = quest_print_submission_title($quest, $submission) .
                         " <a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" . "<img src=\"" .
                         $CFG->wwwroot . "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' .
                         get_string('modif', 'quest') . '" /></a>' .
                         " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                         "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " . 'height="11" width="11" border="0" alt="' .
                         get_string('delete', 'quest') . '" /></a>';
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

            $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' .
                     $image . '</b>';
            $sortdata['nanswersshort'] = $submission->nanswers;
            $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
            $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

            $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
            $sortdata['datestart'] = $submission->datestart;

            $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
            $sortdata['dateend'] = $submission->dateend;

            $grade = "<form><input name=\"calificacion\" id=\"formscore$indice\" type=\"text\" value=\"\" " .
                    "size=\"10\" readonly=\"1\" " .
                    "style=\"background-color : White; border : Black; color : Black; font-size : 14pt; " .
                    "text-align : center;\" ></form>";

            $initialpoints[] = (float) $submission->initialpoints;
            $nanswerscorrect[] = (int) $submission->nanswerscorrect;
            $datesstart[] = (int) $submission->datestart;
            $datesend[] = (int) $submission->dateend;
            $dateanswercorrect[] = (int) $submission->dateanswercorrect;
            $pointsmax[] = (float) $submission->pointsmax;
            $pointsmin[] = (float) $submission->pointsmin;
            $pointsanswercorrect[] = (float) $submission->pointsanswercorrect;
            $tinitial[] = (int) $quest->tinitial * 86400;
            $state[] = (int) $submission->state;
            $type = $quest->typecalification;
            $nmaxanswers = (int) $quest->nmaxanswers;
            $pointsnmaxanswers[] = (float) $submission->points;

            $data[] = $grade;
            $sortdata['calification'] = quest_get_points($submission, $quest, '');

            $columns[] = 'grade';
            $table->align[] = 'center';
            if ($submission->evaluated != 0 && $assessment = $DB->get_record("quest_assessments_autors",
                    array("questid" => $quest->id, "submissionid" => $submission->id))) {
                if ($submission->pointsanswercorrect > 0) {
                    $data[] = number_format($assessment->points * 100 / $submission->pointsanswercorrect, 1) . '% (' .
                              number_format($assessment->points, 4) . ')';
                } else {
                    $data[] = '';
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

$table->head = array("$title", "$phase",
                "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]",
                "$datestart", "$dateend", "$calification", "Grade");

echo '<tr><td>';
echo html_writer::table($table);
$clearicon = $OUTPUT->pix_icon('t/check', '');

echo "<center>";
echo get_string('legend', 'quest', $clearicon);
echo "</center>";

// Javascript counter support.
for ($i = 0; $i < $indice; $i++) {
    $forms[$i] = "#formscore$i";
    $incline[$i] = 0;
}
$servertime = time();
if ($indice > 0) {
    $params = [$indice, $pointsmax, $pointsmin, $initialpoints, $tinitial, $datesstart, $state, $nanswerscorrect,
                $dateanswercorrect, $pointsanswercorrect, $datesend, $forms, $type, $nmaxanswers, $pointsnmaxanswers,
                $servertime, null];
    $PAGE->requires->js_call_amd('mod_quest/counter', 'puntuacionarray', $params);
}
echo '</td></tr>';

$title = get_string('myanswers', 'quest');
echo $OUTPUT->heading_with_help($title, 'myanswers', 'quest');

if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
    echo $OUTPUT->heading(get_string("nostudentsyet"));
    echo $OUTPUT->footer();
    exit();
}

// Now prepare table with student assessments and submissions.
$tablesort->data = array();
$tablesort->sortdata = array();

$answers = $DB->get_records_select("quest_answers", "questid=? AND userid=?", array($quest->id, $USER->id));
if ($answers) {
    foreach ($answers as $answer) {
        $data = array();
        $sortdata = array();
        $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid));
        if ($answer->userid == $USER->id) {
            if ($canpreview) {
                $data[] = quest_print_answer_title($quest, $answer, $submission) .
                     " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                     "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' .
                     get_string('modif', 'quest') . '" /></a>' .
                     " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id&amp;aid=$answer->id\">" .
                     "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " . 'height="11" width="11" border="0" alt="' .
                     get_string('delete', 'quest') . '" /></a>';

                $sortdata['title'] = strtolower($answer->title);
            } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase == 0)) {
                $data[] = quest_print_answer_title($quest, $answer, $submission) .
                     " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                     "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' .
                     get_string('modif', 'quest') . '" /></a>' .
                     " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id&amp;aid=$answer->id\">" .
                     "<img src=\"" . $CFG->wwwroot . "/pix/t/delete.svg\" " . 'height="11" width="11" border="0" alt="' .
                     get_string('delete', 'quest') . '" /></a>';

                $sortdata['title'] = strtolower($answer->title);
            } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase > 0) &&
                             ($answer->permitsubmit == 1)) {
                        $data[] = quest_print_answer_title($quest, $answer, $submission) .
                         " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                         "<img src=\"" . $CFG->wwwroot . "/pix/t/edit.svg\" " . 'height="11" width="11" border="0" alt="' .
                         get_string('modif', 'quest') . '" /></a>';

                $sortdata['title'] = strtolower($answer->title);
            } else {
                $data[] = quest_print_answer_title($quest, $answer, $submission);
                $sortdata['title'] = strtolower($answer->title);
            }

            $data[] = userdate($answer->date, get_string('datestr', 'quest'));
            $sortdata['dateanswer'] = $answer->date;

            if (($answer->phase == 1) || ($answer->phase == 2)) {
                $assessment = $DB->get_record("quest_assessments", array('answerid' => $answer->id));
            } else {
                $assessment = null;
            }

            $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
            $sortdata['tassmnt'] = 1;

            $grade = number_format(quest_answer_grade($quest, $answer, 'ALL'), 4) .
                    ' [max ' . number_format($answer->pointsmax, 4) . ']';
            $data[] = $grade;
            $sortdata['calification'] = quest_answer_grade($quest, $answer, 'ALL');

            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
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
    $$columnanswer = "<a href=\"myplace.php?id=$cm->id&amp;sortanswer=$columnanswer&amp;diranswer=$columndiranswer\">" .
         $string[$columnanswer] . "</a>$columniconanswer";
}

$table->head = array("$title", "$dateanswer", get_string('actions', 'quest'), "$calification");

echo '<tr><td>';
echo html_writer::table($table);
echo '</td></tr>';

if (!$ismanager) {
    $title = get_string('myranking', 'quest');
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
            if ($clasificationteam = $DB->get_record("quest_calification_teams",
                    array("teamid" => $clasification->teamid, "questid" => $quest->id))) {
                $data[] = $clasificationteam->points * $quest->teamporcent / 100;
                $sortdata['pointsteam'] = $clasificationteam->points * $quest->teamporcent / 100;

                $data[] = $clasification->points + $clasificationteam->points * $quest->teamporcent / 100;
                $sortdata['points'] = $clasification->points + $clasificationteam->points * $quest->teamporcent / 100;
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
    $table->valign = array('center', 'center', 'center', 'center', 'left', 'center', 'center',
                            'center', 'center', 'center', 'center');

    if ($quest->allowteams) {
        $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment', 'nsubmissions',
                        'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'pointsteam', 'points');
    } else {
        $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment', 'nsubmissions',
                        'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'points');
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
        $$column = "<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=$column&amp;dir=$columndir\">" .
                    $string[$column] . "</a>$columnicon";
    }

    if ($quest->allowteams) {
        $table->head = array("", get_string('firstname', 'quest') . '/' . get_string('lastname', 'quest'),
                    get_string('nanswers', 'quest'),
                    get_string('nanswersassessment', 'quest'), get_string('nsubmissions', 'quest'),
                    get_string('nsubmissionsassessment', 'quest'), get_string('pointssubmission', 'quest'),
                    get_string('pointsanswers', 'quest'), get_string('pointsteam', 'quest'), get_string('points', 'quest'));
    } else {

        $table->head = array("", get_string('firstname', 'quest') . '/' . get_string('lastname', 'quest'),
                    get_string('nanswers', 'quest'),
                    get_string('nanswersassessment', 'quest'), get_string('nsubmissions', 'quest'),
                    get_string('nsubmissionsassessment', 'quest'), get_string('pointssubmission', 'quest'),
                    get_string('pointsanswers', 'quest'), get_string('points', 'quest'));
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

    $title = get_string('myranking', 'quest');
    $OUTPUT->heading_with_help($title, 'myrankingteam', 'quest');

    // Now prepare table with student assessments and submissions.
    $tablesort->data = array();
    $tablesort->sortdata = array();
    if ($clasificationuser = $DB->get_record("quest_calification_users",
                                                array("userid" => $USER->id, "questid" => $quest->id))) {
        if ($calificationteam = $DB->get_record("quest_calification_teams",
                    array("teamid" => $clasificationuser->teamid, "questid" => $quest->id))) {

            if ($team = $DB->get_record("quest_teams", array("id" => $calificationteam->teamid))) {

                        $data = array();
                        $sortdata = array();

                        $data[] = $team->name;
                        $sortdata['team'] = $team->name;

                        $data[] = $calificationteam->nanswers;
                        $sortdata['nanswers'] = $calificationteam->nanswers;

                        $data[] = $calificationteam->nanswerassessment;
                        $sortdata['nanswersassessment'] = $calificationteam->nanswerassessment;

                        $data[] = $calificationteam->nsubmissions;
                        $sortdata['nsubmissions'] = $calificationteam->nsubmissions;

                        $data[] = $calificationteam->nsubmissionsassessment;
                        $sortdata['nsubmissionsassessment'] = $calificationteam->nsubmissionsassessment;

                        $data[] = $calificationteam->pointssubmission;
                        $sortdata['pointssubmission'] = $calificationteam->pointssubmission;

                        $data[] = $calificationteam->pointsanswers;
                        $sortdata['pointsanswers'] = $calificationteam->pointsanswers;

                        $data[] = $calificationteam->points;
                        $sortdata['points'] = $calificationteam->points;

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
    $columns = array('team', 'nanswers', 'nanswersassessment', 'nsubmissions', 'nsubmissionsassessment', 'pointssubmission',
                'pointsanswers', 'points');

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

    $table->head = array(get_string('team', 'quest'), get_string('nanswers', 'quest'), get_string('nanswersassessment', 'quest'),
                get_string('nsubmissions', 'quest'), get_string('nsubmissionsassessment', 'quest'),
                get_string('pointssubmission', 'quest'), get_string('pointsanswers', 'quest'), get_string('points', 'quest'));

    echo '<tr><td>';
    echo html_writer::table($table);
    echo '</td></tr>';
    echo '<tr><td>';

    echo '</td></tr>';
}

echo '</table>';

echo $OUTPUT->continue_button('view.php?id=' . $cm->id);
// Finish the page.
echo $OUTPUT->footer();
/**
 *
 * @param unknown $a
 * @param unknown $b
 * @return boolean
 */
function quest_sortfunction_answers($a, $b) {
    global $sortanswer, $diranswer;
    if ($diranswer == 'ASC') {
        return ($a[$sortanswer] > $b[$sortanswer]);
    } else {
        return ($a[$sortanswer] < $b[$sortanswer]);
    }
}