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

$a = optional_param('a', '', PARAM_ALPHA); // Quest ID.

$action = optional_param('action', 'global', PARAM_ALPHA);
$sort = optional_param('sort', 'lastname', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

/*
 * Flag to force a recalculation of team statistics and scores.
 */
$debugrecalculate = optional_param('recalculate', 'no', PARAM_ALPHA);

$timenow = time();
$numberprecission = 2;
$local = setlocale(LC_CTYPE, 'esn');
global $DB, $PAGE, $OUTPUT;
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);

$thispageurl = new moodle_url('/mod/quest/viewclasification.php', array('id' => $id));
if ($a !== '') {
    $thispageurl->param('a', $a);
}
if ($action !== 'global') {
    $thispageurl->param('action', $action);
}
if ($sort !== 'lastname') {
    $thispageurl->param('sort', $sort);
}
if ($dir !== 'ASC') {
    $thispageurl->param('dir', $dir);
}

$PAGE->set_url($thispageurl);
$PAGE->set_title(format_string($quest->name));
$PAGE->navbar->add(get_string('globalranking', 'quest'));
$PAGE->set_heading($course->fullname);
if ($action != 'export') {
    echo $OUTPUT->header();
}

if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, required_param('userpassword', PARAM_RAW_TRIMMED));
}

/*
 * Flag to force a recalculation of team statistics and scores.
 * Only to solve bugs.
 */
if ($debugrecalculate == 'yes') {
    require_once("scores_lib.php");
    print("<p>Recalculating...</p>");
    updateallusers($quest->id);
    updateallteams($quest->id);
}

$showauthoringdetails = $ismanager || has_capability('mod/quest:viewotherattemptsowners', $context) || $quest->showauthoringdetails;

if ($quest->allowteams && !$quest->showclasifindividual) {
    $action = 'teams';
}

if ($action == 'global') {

    // Check to see if groups are being used in this quest
    // and if so, set $currentgroup to reflect the current group.
    $changegroup = optional_param('group', -1, PARAM_INT); // Group change requested?
    $groupmode = groups_get_activity_group($cm); // Groups are being used?
    $currentgroup = groups_get_course_group($course);
    $groupmode = $currentgroup = false; // JPC group support desactivation in this version.
                                        // Print settings and things in a table across the top.
    echo '<table width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';

    // Allow the teacher to change groups (for this session).
    if ($groupmode and $ismanager) {
        if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {
            echo '<td>';
            groups_print_activity_menu($cm,
                    $CFG->wwwroot . "/mod/quest/viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC",
                    $return = false, $hideallparticipants = false);
            echo '</td>';
        }
    }
    // Print admin links.
    echo "<td align=\"right\">";
    echo '</td></tr>';
    echo '<tr><td>';
    echo '</td></tr>';
    echo '</table>';
    $classificationtitle = get_string('globalranking', 'quest');
    echo $OUTPUT->heading_with_help($classificationtitle, "globalranking", "quest");
    // Get all the students.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }
    // Now prepare table with student assessments and submissions.
    $tablesort = new stdclass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    foreach ($users as $user) {
        // Skip if student not in group.
        if ($currentgroup) {
            if (!groups_is_member($currentgroup, $user->id)) {
                continue;
            }
        }
        if ($clasifications = quest_get_user_clasification($quest, $user)) {
            foreach ($clasifications as $clasification) {
                $data = array();
                $sortdata = array();
                // ...user picture.
                $user->imagealt = get_string('pictureof', 'quest') . " " . fullname($user);
                $data[] = $OUTPUT->user_picture($user, array('courseid' => $course->id, 'link' => true));
                $sortdata['picture'] = 1;
                // ...link to user profile or just fullname.
                if ($ismanager) {
                    $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?" .
                            "id=$user->id&amp;course=$course->id\">" . fullname($user) . '</a>';
                } else {
                    $data[] = "<b>" . fullname($user) . '</b>';
                }
                // ...first name for sorting.
                $sortdata['firstname'] = strtolower($user->firstname);
                // ...last name for sorting.
                $sortdata['lastname'] = strtolower($user->lastname);
                // ...answers submitted.
                if ($ismanager) {
                    $data[] = "<a href=\"submissions.php?uid=$user->id&amp;action=showanswersuser&amp;id=$cm->id\">" .
                             $clasification->nanswers . '</a>';
                } else {
                    $data[] = $clasification->nanswers;
                }
                $sortdata['nanswers'] = $clasification->nanswers;
                // ...answers marked.
                $data[] = $clasification->nanswersassessment;
                $sortdata['nanswersassessment'] = $clasification->nanswersassessment;

                $showauthoringdetails = $ismanager || $quest->showauthoringdetails;
                if ($showauthoringdetails) { // START AUTHORING ANONYMIZING
                                               // ...number of challenges authored.
                    if ($ismanager) {
                        $data[] = "<a href=\"submissions.php?uid=$user->id&amp;action=showsubmissionsuser&amp;id=$cm->id\">" .
                                 $clasification->nsubmissions . '</a>';
                    } else {
                        $data[] = $clasification->nsubmissions;
                    }
                    $sortdata['nsubmissions'] = $clasification->nsubmissions;
                    // ...challenges marked.
                    $data[] = $clasification->nsubmissionsassessment;
                    $sortdata['nsubmissionsassessment'] = $clasification->nsubmissionsassessment;
                    // ...score for challenges.
                    $data[] = number_format($clasification->pointssubmission, $numberprecission);
                    $sortdata['pointssubmission'] = $clasification->pointssubmission;
                    // ...score for answers.
                    $data[] = number_format($clasification->pointsanswers, $numberprecission);
                    $sortdata['pointsanswers'] = $clasification->pointsanswers;
                } // END AUTHORING ANONYMIZING.

                if ($quest->allowteams) {
                    if ($clasificationteam = $DB->get_record("quest_calification_teams",
                            array("teamid" => $clasification->teamid, "questid" => $quest->id))) {
                        // Team points.
                        $data[] = number_format($clasificationteam->points * $quest->teamporcent / 100, 2);
                        $sortdata['pointsteam'] = $clasificationteam->points * $quest->teamporcent / 100;
                        // ...personal+team points.
                        $data[] = number_format($clasification->points + $clasificationteam->points * $quest->teamporcent / 100,
                                $numberprecission);
                        $sortdata['points'] = $clasification->points + $clasificationteam->points * $quest->teamporcent / 100;
                    } else {
                        $data[] = number_format(0, $numberprecission);
                        $sortdata['pointsteam'] = 0;

                        $data[] = number_format(0, $numberprecission);
                        $sortdata['points'] = $clasification->points;
                    }
                } else {
                    // ...personal points.
                    $data[] = number_format($clasification->points, $numberprecission);
                    $sortdata['points'] = $clasification->points;
                }

                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }
        }
    }

    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = array();

    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }

    $table->align = array('left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->valign = array('center', 'center', 'center', 'center', 'left', 'center', 'center', 'center', 'center', 'center',
                    'center');

    $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment');
    $showauthoringdetails = $ismanager || $quest->showauthoringdetails;
    if ($showauthoringdetails) {
        foreach (array('nsubmissions', 'nsubmissionsassessment', 'pointssubmission', 'pointsanswers') as $col) {
            $columns[] = $col;
        }
    }
    if ($quest->allowteams) {
        $columns[] = 'pointsteam';
    }

    $columns[] = 'points';

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
        $$column = "<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=$column&amp;dir=$columndir\">" .
        $string[$column] .
        (get_string("{$column}_help", 'quest') == '' ? '' : $OUTPUT->help_icon("$column", 'quest')) .
        "</a>$columnicon";
    }

    $table->head = array("", "$firstname / $lastname", "$nanswers", "$nanswersassessment");
    $showauthoringdetails = $ismanager || $quest->showauthoringdetails;
    if ($showauthoringdetails) {
        foreach (array("$nsubmissions", "$nsubmissionsassessment", "$pointssubmission", "$pointsanswers") as $head) {
            $table->head[] = $head;
        }
    }

    if ($quest->allowteams) {
        $table->head[] = "$pointsteam";
    }
    $table->head[] = "$points";

    echo '<tr><td>';
    echo '<div valign="center">';
    echo html_writer::table($table);
    echo '</div>';
    echo '</td></tr>';
    echo '<tr><td>';

    if ($quest->allowteams) {
        echo ("<center><b><a href=\"viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
                 get_string('viewclasificationteams', 'quest') . "</a></b></center>");
    }
    echo '</td></tr>';

    echo '</table>';
} else if ($action == 'teams') {

    // Check to see if groups are being used in this quest
    // and if so, set $currentgroup to reflect the current group.
    $changegroup = optional_param('group', -1, PARAM_INT); // Group change requested?
    $groupmode = groups_get_activity_group($cm); // Groups are being used?

    $currentgroup = groups_get_course_group($course);
    $groupmode = $currentgroup = false; // JPC group support desactivation.
                                        // Print settings and things in a table across the top.
    echo '<table width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';

    // Print admin links.
    echo "<td align=\"right\">";

    echo '</td></tr>';

    echo '<tr><td>';

    echo '</td></tr>';
    echo '</table>';
    $classificationtitle = get_string('teams', 'quest');
    echo $OUTPUT->heading_with_help($classificationtitle, "teams", "quest");

    // Get all the students.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }

    // Now prepare table with student assessments and submissions.
    $tablesort = new stdclass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $teamstemp = array();

    if ($teams = $DB->get_records('quest_teams', array('questid' => $quest->id))) {
        foreach ($teams as $team) {
            foreach ($users as $user) {
                // ...skip if student not in group.
                if ($currentgroup) {
                    if (!groups_is_member($currentgroup, $user->id)) {
                        continue;
                    }
                }

                $clasification = $DB->get_record("quest_calification_users", array("userid" => $user->id, "questid" => $quest->id));
                if ($clasification) {
                    if ($clasification->teamid == $team->id) {
                        $existy = false;
                        foreach ($teamstemp as $teamtemp) {
                            if ($teamtemp->id == $team->id) {
                                $existy = true;
                            }
                        }
                        if (!$existy) {
                            $teamstemp[] = $team;
                        }
                    }
                }
            }
        }
    }
    $teams = $teamstemp;

    foreach ($teams as $team) {

        $data = array();
        $sortdata = array();

        if ($clasificationteam = $DB->get_record("quest_calification_teams",
                                    array("teamid" => $team->id, "questid" => $quest->id))) {
            // ...team name.
            $data[] = $team->name;
            $sortdata['team'] = strtolower($team->name);
            // ...number of answers.
            if ($ismanager) {
                $data[] = "<a href=\"submissions.php?tid=$team->id&amp;action=showanswersteam&amp;id=$cm->id\">" .
                         $clasificationteam->nanswers . '</a>';
            } else {
                $data[] = $clasificationteam->nanswers;
            }
            $sortdata['nanswers'] = $clasificationteam->nanswers;
            // ...number of marked answers.
            $data[] = $clasificationteam->nanswerassessment;
            $sortdata['nanswersassessment'] = $clasificationteam->nanswerassessment;

            if ($showauthoringdetails) { // START AUTHORING ANONYMIZING.
                                           // ...number of challenges submitted.
                if ($ismanager) {
                    $data[] = "<a href=\"submissions.php?tid=$team->id&amp;action=showsubmissionsteam&amp;id=$cm->id\">" .
                             $clasificationteam->nsubmissions . '</a>';
                } else {
                    $data[] = $clasificationteam->nsubmissions;
                }
                $sortdata['nsubmissions'] = $clasificationteam->nsubmissions;
                // ...challenges marked.
                $data[] = $clasificationteam->nsubmissionsassessment;
                $sortdata['nsubmissionsassessment'] = $clasificationteam->nsubmissionsassessment;
                // ...score for challenges marked.
                $data[] = number_format($clasificationteam->pointssubmission, $numberprecission);
                $sortdata['pointssubmission'] = $clasificationteam->pointssubmission;
                // ...score for answers.
                $data[] = number_format($clasificationteam->pointsanswers, $numberprecission);
                $sortdata['pointsanswers'] = $clasificationteam->pointsanswers;
            } // END AUTHORING ANONYMIZING
              // ...total score.
            $data[] = number_format($clasificationteam->points, $numberprecission);
            $sortdata['points'] = $clasificationteam->points;

            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
    }

    uasort($tablesort->sortdata, 'quest_sortfunction');
    $table = new html_table();
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $columns = array('team', 'nanswers', 'nanswersassessment');
    $showauthoringdetails = $ismanager || $quest->showauthoringdetails;
    if ($showauthoringdetails) {
        foreach (array('nsubmissions', 'nsubmissionsassessment', 'pointssubmission', 'pointsanswers') as $col) {
            $columns[] = $col;
        }
    }
    $columns[] = 'points';

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
        $$column = "<a href=\"viewclasification.php?id=$id&amp;action=teams&amp;sort=$column&amp;dir=$columndir\">" .
        $string[$column] .
        (get_string("{$column}_help", 'quest') == '' ? '' : $OUTPUT->help_icon("$column", 'quest')) .
        "</a>$columnicon";
    }

    $table->head = array("$team", "$nanswers", "$nanswersassessment");
    if ($showauthoringdetails) {
        foreach (array("$nsubmissions", "$nsubmissionsassessment", "$pointssubmission", "$pointsanswers") as $head) {
            $table->head[] = $head;
        }
    }
    $table->head[] = "$points";

    echo '<tr><td>';
    echo html_writer::table($table);
    echo '</td></tr>';
    echo '<tr><td>';

    if ($quest->showclasifindividual) {
        echo ("<center><b><a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
                 get_string('viewclasificationglobal', 'quest') . "</a></b></center>");
    }
    echo '</td></tr>';
    echo '</table>';
} else if ($action == 'export') {
    require_capability('mod/quest:viewreports', $context);
    // Get all the students.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }
    $clasifications = quest_get_calification($quest);
    $records = [];
    foreach ($clasifications as $calif) {
        if (isset($users[$calif->userid])) {
            $user = $users[$calif->userid];
            $record = ['firstname' => $user->firstname, 'lastname' => $user->lastname];
            $record = array_merge($record, get_object_vars($calif));
            $records[] = $record;
        }
    }
    quest_export_csv($records, 'Classification', $cm);
}
// Finish the page.
echo $OUTPUT->continue_button(new moodle_url('view.php', array('id' => $id)));
$thispageurl->param('action', 'export');
echo $OUTPUT->action_icon($thispageurl,
        new pix_icon('t/download', get_string('quest:generateCSVlogs', 'quest') . ' ' . $classificationtitle), null, null, true);
echo $OUTPUT->footer();
