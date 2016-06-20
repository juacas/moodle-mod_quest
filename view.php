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
// This page prints a particular instance of QUEST
require_once("../../config.php");
require_once("lib.php");
require("locallib.php");

$id = required_param('id', PARAM_INT);    // Course Module ID
$sid = optional_param('sid', null, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$group = optional_param('group', -1, PARAM_INT);

$actionclasification = optional_param('actionclasification', 'global', PARAM_ALPHA);

$local = setlocale(LC_CTYPE, 'esn');

if (empty($actionclasification)) {
    if (!isset($USER->showclasifindividual)) {
        $actionclasification = 'global';
    } else {
        $actionclasification = $USER->showclasifindividual;
    }
}
global $DB, $PAGE, $OUTPUT, $USER;
$timenow = time();
// Print the page header

list($course,$cm)=quest_get_course_and_cm($id);

$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
require_capability('mod/quest:view', $context);

if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $context)) {
    error("Module hidden.");
}

// Mark as viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$url = new moodle_url('/mod/quest/view.php', array('id' => $id)); //evp debería añadir los otros posibles parámetros tal y como se ha hecho en assessments_autors.php
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
//$PAGE->set_context($context);
$PAGE->set_heading($course->fullname);


$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$straction = ($action) ? '-> ' . get_string($action, 'quest') : '';

$changegroup = optional_param('group',-1,PARAM_INT); // Group change requested?
$groupmode = groups_get_activity_group($cm);   // Groups are being used?
$currentgroup = groups_get_activity_group($cm); //evp esto de los grupos hay que comprobar que funciona bien
$groupmode = $currentgroup = false; //JPC group support desactivation


if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, required_param('userpassword',PARAM_RAW));
}

/**
 * Teachers must complete grading elements
 * and students must enroll in a team if enabled.
 */
if ($ismanager) {

    if (empty($action)) { // no action specified, either go straight to elements page else the admin page
        // has the assignment any elements
        $elements_for_this_submission = $DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0));

        if (isset($sid)) {
            $submissions = $DB->get_record("quest_submissions", array('id' => $sid));
            $num_elements_expected_in_submission = $submissions == false ? 0 : $submissions->numelements;
        } else {
            $num_elements_expected_in_submission = 0;
        }

        if ($quest->gradingstrategy == 0 ||
                ($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0)) >= $quest->nelements) ||
                (( $elements_for_this_submission >= $num_elements_expected_in_submission ) && ($elements_for_this_submission != 0))) {
            $num_elements = $DB->count_records("quest_elementsautor", array("questid" => $quest->id));

            if ($quest->gradingstrategyautor == 0 || $num_elements >= $quest->nelementsautor) {
                $action = "teachersview";
            } else {
                redirect("assessments_autors.php?action=editelements&id=$cm->id&sesskey=".sesskey());
            }
        } else {
            redirect("assessments.php?action=editelements&id=$cm->id&sesskey=".sesskey());
        }
    }
} else
/*
 * Create a grade record and register the user as active in the quest.
 */
if (has_capability('mod/quest:attempt', $context)) {
    // it's a student then

    if (!$cm->visible) {
        notice(get_string("activityiscurrentlyhidden"));
    }
    if ($timenow < $quest->datestart) {
        $action = 'notavailable';
        $message = get_string('phase1', 'quest');
    } else if (!$action) {
        if ($timenow < $quest->dateend) {
            $action = 'studentsview';
        } else {
            $action = 'displayfinalgrade';
        }
    }

    if ($calification_user = $DB->get_record("quest_calification_users", array("userid" => $USER->id, "questid" => $quest->id))) {
        if ($quest->allowteams == 1) {
            if (empty($calification_user->teamid)) {
                if (empty($_POST['team'])
                        /*
                          JPC: 20-11-2008: prevent creation of teams without name.
                         */ || trim($_POST['team']) == '') {
                    echo $OUTPUT->header();
                    echo "<br><br>";
                    echo $OUTPUT->box_start("center");
                    echo "<form name=\"teams\" method=\"post\" action=\"view.php\">\n";
                    echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />\n";
                    echo "<table cellpadding=\"7px\">";

                    echo "<tr align=\"center\"><td>" . get_string("teamforquest", "quest", format_string($quest->name)) .
                    "</td></tr>";
                    echo "<tr align=\"center\"><td>" . get_string("enterteam", "quest") .
                    " <input type=\"text\" name=\"team\" /></td></tr>";

                    echo "<tr align=\"center\"><td>";
                    echo "<input type=\"button\" value=\"" . get_string("continue") .
                    "\" onclick=\"document.teams.submit();\" />";
                    echo "</td></tr></table>";
                    echo $OUTPUT->box_end();
                    $sortteam = optional_param('sortteam', '', PARAM_ALPHA);
                    $dirteam = optional_param('dirteam', '', PARAM_ALPHA);
                    quest_print_table_teams($quest, $course, $cm, $sortteam, $dirteam);
                    echo $OUTPUT->footer();
                    exit();
                } else if (!empty($_POST['team']) && trim($_POST['team']) != '') {
                    // team assignation or creation
                    if ($team = $DB->get_record("quest_teams",
                            array("name" => $_POST['team'], "questid" => $quest->id, "currentgroup" => $currentgroup))) {
                        if ($quest->ncomponents > $team->ncomponents) {
                            $team->ncomponents++;
                            $DB->set_field("quest_teams", "ncomponents", $team->ncomponents, array("id" => $team->id));
                            $calification_user->teamid = $team->id;
                            $DB->set_field("quest_calification_users", "teamid", $calification_user->teamid,
                                    array("id" => $calification_user->id));
                        } else {
                            echo $OUTPUT->header();
                            echo ('<center><b>The Team is complete</b></center>');
                            echo $OUTPUT->continue_button("view.php?id=$cm->id");
                            exit();
                        }
                    } else { // ...new team.
                        $team = new stdClass();
                        $team->ncomponents=1;
                        $team->questid = $quest->id;
                        $team->currentgroup = $currentgroup;
                        $team->name = trim($_POST['team']);
                        /*******
                          JPC: 20-11-2008: prevent creation of teams without name
                         *********/
                        if ($team->name == '')
                            $team->name = 'Team_user(' . $calification_user->id . ")";
                        $team->id = $DB->insert_record("quest_teams", $team);
                        $calification_team = new stdClass();
                        $calification_team->teamid = $team->id;
                        $calification_team->questid = $quest->id;
                        $calification_team->id = $DB->insert_record("quest_calification_teams", $calification_team);
                        $calification_user->teamid = $team->id;
                        $DB->set_field("quest_calification_users", "teamid", $calification_user->teamid,
                                array("id" => $calification_user->id));
                    }
                } // ...end team assignation.
            }
        }
    }else
    /*
     * User do not have a $calification_user entry.
     */ {
        if (!is_siteadmin($USER)) { // ...prevent the admin to be captured as a participant in the questournament.
            $calification_user = new stdClass();
            $calification_user->userid = $USER->id;
            $calification_user->questid = $quest->id;
            $calification_user->id = $DB->insert_record("quest_calification_users", $calification_user);
            if ($quest->allowteams == 1) {
                if (!isset($_POST['team']) || trim($_POST['team']) == '') {
                    echo $OUTPUT->header();
                    echo "<br><br>";
                    echo $OUTPUT->box_start("center");
                    echo "<form name=\"teams\" method=\"post\" action=\"view.php\">\n";
                    echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />\n";
                    echo "<table cellpadding=\"7px\">";
                    if (isset($_POST['team'])) {
                        echo "<tr align=\"center\" style='color:#DF041E;'><td>" . get_string("wrongteam", "quest") .
                        "</td></tr>";
                    }
                    echo "<tr align=\"center\"><td>" . get_string("teamforquest", "quest", format_string($quest->name)) .
                    "</td></tr>";
                    echo "<tr align=\"center\"><td>" . get_string("enterteam", "quest") .
                    " <input type=\"text\" name=\"team\" /></td></tr>";

                    echo "<tr align=\"center\"><td>";

                    echo "<input type=\"button\" value=\"" . get_string("continue") .
                    "\" onclick=\"document.teams.submit();\" />";
                    echo "</td></tr></table>";
                    echo $OUTPUT->box_end();
                    $sortteam = optional_param('sortteam', '', PARAM_ALPHA);
                    $dirteam = optional_param('dirteam', '', PARAM_ALPHA);

                    quest_print_table_teams($quest, $course, $cm, $sortteam, $dirteam);
                    echo $OUTPUT->footer();
                    exit();
                } else if (null!==optional_param('team', null, PARAM_INT)) {
                    if ($team = $DB->get_record("quest_teams",
                            array("name" => $_POST['team'], "questid" => $quest->id, "currentgroup" => $currentgroup))) {
                        if ($quest->ncomponents > $team->ncomponents) {
                            $team->ncomponents++;
                            $DB->set_field("quest_teams", "ncomponents", $team->ncomponents, array("id" => $teamid));
                            $calification_user->teamid = $team->id;
                            $DB->set_field("quest_calification_users", "teamid", $calification_user->teamid,
                                    array("id" => $calification_user->id));
                        } else {
                            echo $OUTPUT->header();
                            echo ('<center><b>The Team is complete</b></center>');
                            echo $OUTPUT->continue_button("view.php?id=$cm->id");
                            echo $OUTPUT->footer();
                            exit();
                        }
                    }
                    /* else{
                      $team->ncomponents++;
                      $team->questid = $quest->id;
                      $team->currentgroup = $currentgroup;
                      $team->name = $_POST['team'];
                      $team->id = $DB->insert_record("quest_teams", $team);
                      $calification_team->teamid = $team->id;
                      $calification_team->questid = $quest->id;
                      $calification_team->id = $DB->insert_record("quest_calification_teams", $calification_team);
                      $calification_user->teamid = $team->id;
                      $DB->set_field("quest_calification_users","teamid", $calification_user->teamid, "id", $calification_user->id);
                      } */
                }
            } // allow teams
        } //  !is_siteadmin($USER)
    } // not have calification_user
}
// Log event.
if ($CFG->version >= 2014051200) {
    require_once 'classes/event/quest_viewed.php';
    \mod_quest\event\quest_viewed::create_from_parts($USER, $quest, $cm)->trigger();
} else {
    $url = "view.php?id=$cm->id";
    add_to_log($course->id, "quest", "view", $url, "$quest->id");
}
echo $OUTPUT->header();


/* * **************** display final grade (for students) *********************************** */
if ($action == 'displayfinalgrade') {
    // Check to see if groups are being used in this quest
    // and if so, set $currentgroup to reflect the current group
    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
    $groupmode = groups_get_activity_groupmode($cm, $course);
    $currentgroup = groups_get_course_group($course);
    $groupmode = $currentgroup = false; //JPC group support desactivation
    // Print settings and things in a table across the top
    echo '<table align="center" width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';

    // Allow the teacher to change groups (for this session)
    if ($groupmode and $ismanager) {
        if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {
            echo '<td>';
            print_group_menu($groups, $groupmode, $currentgroup, "view.php?id=$cm->id");
            echo '</td>';
        }
    }
    // Print admin links
    echo "<td align=\"right\">";
    echo '</td></tr>';
    echo "</table>";

    quest_print_quest_heading($quest);

    $text = "<center><b>";

    $text .= "<a href=\"assessments_autors.php?id=$cm->id&amp;sid=&amp;action=displaygradingform\">" .
            get_string("specimenassessmentformsubmission", "quest") . "</a>";
    $text .= $OUTPUT->help_icon('specimensubmission', 'quest');

    if ($ismanager and $quest->nelements) {
        $edit_icon = $OUTPUT->pix_icon('t/edit', get_string('amendassessmentelements', 'quest'));

        $text .= " <a href=\"assessments_autors.php?id=$cm->id&amp;action=editelements&sesskey=".sesskey()."\">" .
                $edit_icon . '</a>';
    }
    $text .= "</b></center>";

    echo($text);

    echo "<table width=\"100%\" border=\"0\" cellpadding=\"3\" cellspacing=\"0\">";
    echo "<tr><td height=\"30\"> </td></tr>";
    echo "<tr valign=\"top\">";
    echo "<td width=\"70%\" align=\"center\">";
    echo "<b>" . get_string('description', 'quest') . "</b>";
    echo "</td><td width=\"30%\" align=\"center\">";
    echo "<b>" . get_string('clasification', 'quest') . "</b>";
    echo "</td></tr>";
    echo "<tr><td width=\"70%\" valign=\"top\">";
    echo $OUTPUT->box(format_module_intro('quest',$quest,$cm->id), 'left', '100%');
    quest_print_attachments($context, 'introattachment', false, 'timemodified');
    echo "</td><td width=\"30%\" valign=\"top\">";

    if (($quest->allowteams) && ($quest->showclasifindividual == 1)) {
        if ($actionclasification == 'global') {
            echo " <center><a href=\"view.php?actionclasification=teams&amp;id=$cm->id\">" .
            get_string('resumeteams', 'quest') . "</a></center>";
            echo '<br>';
        } else {
            echo " <center><a href=\"view.php?actionclasification=global&amp;id=$cm->id\">" .
            get_string('resumeindividual', 'quest') . "</a></center>";
            echo '<br>';
        }
    }
    $users = quest_get_course_members($course->id, "u.lastname, u.firstname");
    quest_print_simple_calification($quest, $course, $currentgroup, $actionclasification);
    if ($users)
        echo " <center><b><a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
        get_string('viewclasification', 'quest') . "</a></b></center>";
    echo "</td></tr></table>";

    echo "<br><b><a href=\"myplace.php?id=$cm->id\">" . get_string('myplace', 'quest') . "</a></b>";
    echo "<br>";

    // Get all the students
    if (!$users) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit;
    }


    // Now prepare table with student assessments and submissions
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();

    foreach ($users as $user) {
        // skip if student not in group
        if (!has_capability('mod/quest:manage', $context, $user->id) && ($groupmode == 1)) {
            if ($currentgroup) {
                if (!groups_is_member($currentgroup, $user->id)) {
                    continue;
                }
            }
        }
        if ($submissions = quest_get_user_submissions($quest, $user)) {
            foreach ($submissions as $submission) {
                $data = array();
                $sortdata = array();

                if (( $submission->userid == $USER->id) || (( $submission->state == 2) && ($submission->datestart < $timenow))) {

                    $data[] = quest_print_submission_title($quest, $submission);
                    $sortdata['title'] = strtolower($submission->title);

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
                        $image = $OUTPUT->pix_icon('t/check', 'ok');
                    }

                    $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' . $image . '</b>';
                    $sortdata['nanswersshort'] = $submission->nanswers;
                    $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
                    $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;


                    $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
                    $sortdata['datestart'] = $submission->datestart;

                    $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
                    $sortdata['dateend'] = $submission->dateend;

                    $points = quest_get_points($submission, $quest);
                    $points = number_format($points, 4);

                    $grade = "<form name=\"puntos\"><input name=\"calificacion\" type=\"text\" value=\"$points\" size=\"10\" readonly=\"1\" style=\"background-color : White; border : Black; color : #cccccc; font-family : Verdana, Arial, Helvetica; font-size : 14pt; text-align : center;\" ></form>";

                    $data[] = $grade;
                    $sortdata['calification'] = quest_get_points($submission, $quest, '');

                    $tablesort->data[] = $data;
                    $tablesort->sortdata[] = $sortdata;
                }
            }
        }
    }
    $sort = optional_param('sort', 'datestart', PARAM_ALPHA);
    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }


    $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');

    $columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', 'calification');
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
            $columnicon = $OUTPUT->pix_icon("t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"view.php?id=$id&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }



    $table->head = array("$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart", "$dateend", "$calification");


    echo html_writer::table($table);

    $grafic = $OUTPUT->pix_icon('t/check', 'ok');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
    echo "</center>";

    echo "<br><b><a href=\"myplace.php?id=$cm->id\">" . get_string('myplace', 'quest') . "</a></b>";
}
/* * **************** assignment not available (for students)********************** */
else if ($action == 'notavailable') {

    // Check to see if groups are being used in this quest
    // and if so, set $currentgroup to reflect the current group
    $groupmode = groups_get_activity_groupmode($cm, $course);   // Groups are being used?
    $currentgroup = groups_get_course_group($course, true);
    $groupmode = $currentgroup = false; //JPC group support desactivation
    // Print settings and things in a table across the top
    echo '<table align="center" width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';
    // Allow the teacher to change groups (for this session)
    if ($groupmode and has_capability('mod/quest:manage', $context)) {
        if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {
            echo '<td>';
            print_group_menu($groups, $groupmode, $currentgroup, "view.php?id=$cm->id");
            echo '</td>';
        }
    }
    // Print admin links
    echo "<td align=\"right\">";
    echo '</td></tr>';
    echo "</table>";
    quest_print_quest_heading($quest);
    echo $OUTPUT->notification($message);
    echo $OUTPUT->heading(get_string('description', 'quest'));
    echo $OUTPUT->box(format_module_intro('quest',$quest,$cm->id));
}
// Student's and teacher's unified view.
else if ($action == 'teachersview' || $action == 'studentsview') {
    $canViewAuthors = has_capability('mod/quest:viewotherattemptsowners', $context);
    // Check to see if groups are being used in this quest
    // and if so, set $currentgroup to reflect the current group.
    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
    $groupmode = groups_get_activity_group($cm);   // Groups are being used?
    //$currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);
    $currentgroup = groups_get_course_group($course);
    $groupmode = $currentgroup = false; //JPC group support desactivation
    // Print settings and things in a table across the top.
    echo '<table align="right"  border="0" cellpadding="3" cellspacing="0"><tr valign="top">';
    // Allow the teacher to change groups (for this session).
    if ($groupmode and $ismanager) {
        if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {
            echo '<td>';
    //print_group_menu($groups, $groupmode, $currentgroup, "view.php?id=$cm->id");  evp no he sabido sustituir esto, es posible que ya no se utilice y no haga falta.
            echo '</td>';
        }
    }
    quest_print_quest_heading($quest);

    $text = "<center><b>";
    $text .= "<a href=\"assessments_autors.php?id=$cm->id&amp;action=displaygradingform\">" .
            get_string("specimenassessmentformsubmission", "quest") . "</a>";
    $text .= $OUTPUT->help_icon('specimensubmission', 'quest');

    if (has_capability('mod/quest:manage', $context) and $quest->nelements) {
        $edit_icon = $OUTPUT->pix_icon('t/edit', get_string('amendassessmentelements', 'quest'));
        $text .= "<a href=\"assessments_autors.php?id=$cm->id&amp;action=editelements&sesskey=".sesskey()."\">" .
                $edit_icon . '</a>';
    }
    $text .= "</b></center>";
    echo($text);

    $text = "<center><b>";
    $text .= "<a href=\"assessments.php?id=$cm->id&amp;viewgeneral=1&amp;action=displaygradingform\">" .
            get_string("specimenassessmentformanswer", "quest") . "</a>";
    $text .= $OUTPUT->help_icon('specimenanswer', 'quest');

    if (has_capability('mod/quest:manage', $context) and $quest->nelements) {
        $edit_icon = $OUTPUT->pix_icon('t/edit', get_string('amendassessmentelements', 'quest'));
        $text .="&nbsp;<a href=\"assessments.php?id=$cm->id&newform=0&cambio=0&amp;viewgeneral=1&amp;action=editelements&sesskey=".sesskey()."\">" . $edit_icon . '</a>';
    }
    $text .= "</b></center>";
    echo($text);

    echo "<table width=\"100%\" border=\"0\" cellpadding=\"3\" cellspacing=\"0\">";
    echo "<tr><td height=\"30\"> </td></tr>";
    echo "<tr valign=\"top\">";
    echo "<td width=\"70%\" align=\"center\">";
    echo "<b>" . get_string('description', 'quest') . "</b>";
    echo "</td><td width=\"30%\" align=\"center\">";
    echo "<b>" . get_string('clasification', 'quest') . "</b>";
    echo "</td></tr>";
    echo "<tr><td width=\"70%\" valign=\"top\">";
    echo $OUTPUT->box(format_module_intro('quest',$quest,$cm->id));
    quest_print_attachments($context, 'introattachment', false, 'timemodified');

    echo "</td><td width=\"30%\" valign=\"top\">";

    if (($quest->allowteams) && ($quest->showclasifindividual == 1)) {
        if ($actionclasification == 'global') {
            echo " <center><a href=\"view.php?actionclasification=teams&amp;id=$cm->id\">" .
            get_string('resumeteams', 'quest') . "</a></center>";
            echo '<br>';
        } else {
            echo " <center><a href=\"view.php?actionclasification=global&amp;id=$cm->id\">" .
            get_string('resumeindividual', 'quest') . "</a></center>";
            echo '<br>';
        }
    }

    quest_print_simple_calification($quest, $course, $currentgroup, $actionclasification);

    echo " <center><b><a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
    get_string('viewclasification', 'quest') . "</a></b></center>";
    echo '<br><br>';
    echo "</td></tr></table>";

    echo "<br><b><a href=\"myplace.php?id=$cm->id\">" . get_string('myplace', 'quest') . "</a></b>";
    echo $OUTPUT->help_icon('myplace', 'quest');


    if (($ismanager) && ($quest->allowteams)) {
        echo "&nbsp;/&nbsp;<b><a href=\"team.php?id=$cm->id\">" . get_string('changeteamteacher', 'quest') . "</a></b>";
        echo $OUTPUT->help_icon("changeteamteacher", "quest");
    }
    if (!has_capability('mod/quest:addchallenge', $context)) {
        echo("&nbsp;/&nbsp;" . get_string('need_to_be_editor', 'quest'));
    } else if ($quest->dateend > $timenow) {
        echo( "<a href=\"submissions.php?action=submitchallenge&amp;id=$cm->id\">" .
        '&nbsp;/&nbsp;<b>' . get_string('addsubmission', 'quest') . "</b></a>");
    } else {
        echo "&nbsp;/&nbsp;" . get_string('phase3', 'quest', '');
    }

    echo $OUTPUT->help_icon('submitchallengeassignment', 'quest');
    echo '<br/>';
    ?>
    <script language="JavaScript">
        var servertime =<?php echo time() * 1000; ?>;
        var browserDate = new Date();
        var browserTime = browserDate.getTime();
        var correccion = servertime - browserTime;

        function redondear(cantidad, decimales) {
            var cantidad = parseFloat(cantidad);
            var decimales = parseFloat(decimales);
            decimales = (!decimales ? 2 : decimales);
            var valor = Math.round(cantidad * Math.pow(10, decimales)) / Math.pow(10, decimales);
            return valor.toFixed(4);
        }

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
    /*     * **
     * Now prepare table with student assessments and submissions
     */
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $indice = 0;

    if ($submissions = quest_get_submissions($quest)) {
        foreach ($submissions as $submission) {
            // get the author of this submission
            if ($submission->userid == 0) { // anonymous user
                $user = false;
            } // Guest user
            else {
                $user = $DB->get_record('user', array('id' => $submission->userid));
            }
            // skip if student not in group
            if (!has_capability('mod/quest:manage', $context)) {
                if ($currentgroup) {
                    if ($user!==null && !groups_is_member($currentgroup, $user->id)) {
                        continue;
                    }
                }
            }

            $data = array();
            $sortdata = array();

            if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) && ($submission->nanswerscorrect < $quest->nmaxanswers) && $submission->phase != SUBMISSION_PHASE_ACTIVE) {
                $submission->phase = SUBMISSION_PHASE_ACTIVE; //running
                $DB->update_record('quest_submissions', $submission);
            }



            /**
             * Skip a submission not viewable by this user
             */
            if (
                    $submission->state == SUBMISSION_STATE_APPROVAL_PENDING && !has_capability('mod/quest:approvechallenge',
                            $context) && !has_capability('mod/quest:manage', $context) && $submission->userid != $USER->id) {
                continue;
            }
            // skip challenge for student if the challenge is not started
            if (!has_capability('mod/quest:manage', $context) // manage permission
                    && $submission->datestart > $timenow // Challenge in StartPending
                    && $submission->userid != $USER->id) { // USER is not the author
                continue; // omit it
            }
            $mine_icon = $submission->userid == $USER->id && !$canViewAuthors ? $OUTPUT->user_picture($USER) : '';
            $titleText = $mine_icon . quest_print_submission_title($quest, $submission);

            /**
             * Show or not the edit controls.
             */
            if ((
                    (
                    has_capability('mod/quest:editchallengeall', $context)
                    or (
                    $submission->userid == $USER->id
                    //and has_capability('mod/quest:editchallengemine', $context)
                    )
                    )
                    and ( $submission->nanswers == 0)
                    and ( $timenow < $submission->dateend)
                    and ( $submission->state != SUBMISSION_STATE_APROVED)
                 )
                    or
                 ( $ismanager)) {
                $edit_icon = $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest'));
                $delete_icon = $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest'));
                $titleText .= "<a href=\"submissions.php?action=modif&amp;id=$cm->id&amp;sid=$submission->id\">" .
                        $edit_icon . '</a>' .
                        " <a href=\"submissions.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id\">" .
                        $delete_icon . '</a>';
            }

            $data[] = $titleText;
            $sortdata['title'] = strtolower($submission->title);
            if ($canViewAuthors) {
                if ($user === false) { // Sometimes the author is no longer in the system.
                    $data[] = "?";
                    $data[] = "Unknown author ($submission->userid)";
                    $sortdata['firstname'] = "Unknown";
                    $sortdata['lastname'] = "Unknown";
                } else {
                    $data[] = $OUTPUT->user_picture($user);
                    $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" . fullname($user) . '</a>';
                    $sortdata['firstname'] = strtolower($user->firstname);
                    $sortdata['lastname'] = strtolower($user->lastname);
                }
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
                $image = $OUTPUT->pix_icon('t/check', 'ok');
            }

            $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' . $image . '</b>';
            $sortdata['nanswersshort'] = $submission->nanswers;
            $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
            $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

            $data[] = userdate($submission->datestart, get_string('datestr', 'quest'));
            $sortdata['datestart'] = $submission->datestart;

            $data[] = userdate($submission->dateend, get_string('datestr', 'quest'));
            $sortdata['dateend'] = $submission->dateend;

            $grade = "<form name=\"puntos$indice\"><input name=\"calificacion\" type=\"text\" value=\"0.000\" size=\"10\" readonly=\"1\" style=\"background-color : White; border : Black; color : Black; font-family : Verdana, Arial, Helvetica; font-size : 14pt; text-align : center;\" ></form>";

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

            $indice++;

            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
    }
    if ($canViewAuthors) {
        $sort = optional_param('sort', 'dateend', PARAM_ALPHA);
    } else {
        $sort = optional_param('sort', 'dateend', PARAM_ALPHA);
    }
    uasort($tablesort->sortdata, 'quest_sortfunction');

    $table = new html_table();
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    if ($canViewAuthors) {
        $columns = array('title', 'firstname', 'lastname', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');
    } else { // removed personal info column
        $columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');
    }
    // Define a new variable for each column with heading texts
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
            $columnicon = $OUTPUT->pix_icon("t/$columnicon", $columnicon);
        }
        $$column = "<a href=\"view.php?id=$id&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    if ($canViewAuthors) {
        $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
        $columns = array('title', 'firstname', 'lastname', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');
        $table->head = array("$title", "$firstname / $lastname", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart", "$dateend", /* get_string('actions','quest'), */ "$calification");
        $table->headspan = array(1, 2, 1, 1, 1, 1, 1);
    } else { // hide personal column
        $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
        $columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess', 'datestart', 'dateend', /* 'actions', */ 'calification');
        $table->head = array("$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart", "$dateend", /* get_string('actions','quest'), */ "$calification");
        //$table->headspan=array(1,1,1,1,1,1);
    }

    $table->width = "95%";

    // $table->data = array ("$title", "$firstname / $lastname","$phase","$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]","$datestart", "$dateend", /*get_string('actions','quest'),*/ "$calification");


    echo html_writer::table($table);

    $grafic = $OUTPUT->pix_icon('t/check', 'ok');
    echo "<center>";
    echo get_string('legend', 'quest', $grafic);
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
    if ($REPEAT_ACTIONS_BELOW) {
        if ($quest->dateend > $timenow) {
            echo( "<center><b><a href=\"view.php?action=submitchallenge&amp;id=$cm->id\">" .
            get_string('addsubmission', 'quest') . "</a></b></center>");
        }

        echo "<br><b><a href=\"myplace.php?id=$cm->id\">" . get_string('myplace', 'quest') . "</a></b>";

        if ((has_capability('mod/quest:manage', $context)) && ($quest->allowteams)) {
            echo "&nbsp;/&nbsp;<b><a href=\"team.php?id=$cm->id\">" . get_string('changeteamteacher', 'quest') . "</a></b><br><br>";
        }
    }
}
/* * ************* no man's land ************************************* */ else {
    print_error("Fatal Error: Unknown Action: " . $action . "\n");
}
// Finish the page
echo $OUTPUT->footer();

