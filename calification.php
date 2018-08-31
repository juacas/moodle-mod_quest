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
 */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT); // Submission ID.
$allowcomments = optional_param('allowcomments', false, PARAM_BOOL);
$redirect = optional_param('redirect', '', PARAM_ALPHA);

$action = optional_param('action', '', PARAM_ALPHA);
$sort = optional_param('sort', 'points', PARAM_ALPHA);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);

global $DB;
$timenow = time();
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
if (!$redirect) {
    $redirect = urlencode($_SERVER["HTTP_REFERER"] . '#id=' . $cm->id);
}

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$cangrade = has_capability('mod/quest:grade', $context);
$canpreview = has_capability('mod/quest:preview', $context);
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");
$strcalification = get_string("calification", "quest");

// Now check whether we need to display a frameset.
$frameset = optional_param('frameset', null, PARAM_ALPHA);
if (empty($frameset)) {
    echo "<head><title>{$course->shortname}: " . format_string($quest->name, true) . "</title></head>\n";
    echo "<frameset rows=\"50%,*\" border=\"10\">";
    echo "<frame src=\"calification.php?id=$id&amp;frameset=top&amp;redirect=$redirect\" border=\"10\">";
    echo "<frame src=\"calification.php?id=$id&amp;frameset=bottom&amp;redirect=$redirect&amp;" .
        "action=global&amp;sort=points&amp;dir=DESC\">";
    echo "</frameset>";
    exit();
}

// ...top frame with the navigation bar and the assessment form.

if (!empty($frameset) and $frameset == "top") {

    print_header_simple(format_string($quest->name), "",
            "<a href=\"index.php?id=$course->id\">$strquests</a> -> <a href=\"view.php?id=$cm->id\">" .
                     format_string($quest->name, true) . "</a> -> $strcalification", "", '<base target="_parent" />', true);

    print_heading_with_help(get_string("calificationthisquest", "quest"), "grading", "quest");

    if ($cangrade) {
        echo "<form name=\"calification\" method=\"post\" action=\"viewcalification.php\">";
        echo "<input type=\"hidden\" name=\"id\" value=\"$id\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"upgradecalification\">";
        echo "<center>";
        echo "<table cellpadding=\"5\" border=\"1\">";
        echo "<tr valign=\"top\">\n";
        echo "<td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
        echo get_string('tocalification', 'quest') . '</b></center></td></tr>';

        echo "<tr valign=\"top\">";
        echo "<td colspan=\"2\"><center><b>";
        echo get_string('individualcalification', 'quest') . "</b></center></td></tr>";

        echo "<tr valign=\"top\">";
        echo "  <td align=\"right\"><p><b>" . get_string('porcent', 'quest') . ": </b></p></td>\n";
        echo '<td>';
        for ($i = 100; $i >= 0; $i--) {
            $grades[$i] = $i;
        }
        $form->individualporcent = 50;
        echo html_writer::select($grades, "individualporcent", "$form->individualporcent", "");
        echo '</td></tr>';

        echo "<tr valign=\"top\">";
        echo "  <td align=\"right\"><p><b>" . get_string('nivel', 'quest') . ": </b></p></td>\n";
        echo '<td>';
        unset($grades);
        for ($i = 10; $i >= 0; $i--) {
            $grades[$i] = $i;
        }
        $form->individualnivel = 5;
        echo html_writer::select($grades, "individualnivel", "$form->individualnivel", "");
        echo '</td></tr>';

        if ($quest->allowteams == 1) {
            echo "<tr valign=\"top\">";
            echo "<td colspan=\"2\"><center><b>";
            echo get_string('teamcalification', 'quest') . "</b></center></td>\n";
            echo "</tr>\n";

            echo "<tr valign=\"top\">";
            echo "  <td align=\"right\"><p><b>" . get_string('porcent', 'quest') . ": </b></p></td>\n";
            echo '<td>';
            unset($grades);
            for ($i = 100; $i >= 0; $i--) {
                $grades[$i] = $i;
            }
            $form->teamporcent = 50;
            echo html_writer::select($grades, "teamporcent", "$form->teamporcent", "");
            echo '</td></tr>';

            echo "<tr valign=\"top\">";
            echo "  <td align=\"right\"><p><b>" . get_string('nivel', 'quest') . ": </b></p></td>\n";
            echo '<td>';
            unset($grades);
            for ($i = 10; $i >= 0; $i--) {
                $grades[$i] = $i;
            }
            $form->teamnivel = 5;
            echo html_writer::select($grades, "teamnivel", "$form->teamnivel", "");
            echo '</td></tr>';
        }

        echo "<tr valign=\"top\">\n";
        echo "<td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
        echo "</tr>\n";
        echo "</table>";
        echo "<center><input type=\"submit\" value=" . get_string("savecalification", "quest") . "></center>";
        echo "</form>";
    }

    print_heading("<a target=\"{$CFG->framename}\" href=\"$redirect\">" . get_string("cancel") . "</a>");
    print_footer($course);
    exit();
}

// Print bottom frame with the submission.

print_header('', '', '', '', '<base target="_parent" />');

if ($action == 'global') {
    $groupmode = $currentgroup = false; // JPC group support desactivation.
    // Print settings and things in a table across the top.
    echo '<table width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';
    // Print admin links.
    echo "<td align=\"right\">";
    echo "<a href=\"submissions.php?id=$cm->id&amp;action=adminlist\">" . get_string("administration") . "</a>\n";

    echo '</td></tr>';

    echo '<tr><td>';

    echo '</td></tr>';
    echo '</table>';
    print_heading(get_string('global', 'quest'));
    // Get all the students.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        print_heading(get_string("nostudentsyet"));
        print_footer($course);
        exit();
    }

    // Now prepare table with student assessments and submissions.
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

                $data[] = print_user_picture($user->id, $course->id, $user->picture, 0, true);
                $sortdata['picture'] = 1;

                if ($canpreview) {

                    $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?" .
                            "id=$user->id&amp;course=$course->id\">" .
                             fullname($user) . '</a>';
                    $sortdata['firstname'] = $user->firstname;
                    $sortdata['lastname'] = $user->lastname;
                } else {

                    $data[] = "<b>" . fullname($user) . '</b>';
                    $sortdata['firstname'] = $user->firstname;
                    $sortdata['lastname'] = $user->lastname;
                }
                if ($canpreview) {
                    $data[] = "<a href=\"submissions.php?uid=$user->id&amp;action=showanswersuser&amp;id=$cm->id\">" .
                             $clasification->nanswers . '</a>';
                } else {
                    $data[] = $clasification->nanswers;
                }
                $sortdata['nanswers'] = $clasification->nanswers;

                $data[] = $clasification->nanswersassessment;
                $sortdata['nanswersassessment'] = $clasification->nanswersassessment;

                if ($canpreview) {
                    $data[] = "<a href=\"submissions.php?uid=$user->id&amp;action=showsubmissionsuser&amp;id=$cm->id\">" .
                             $clasification->nsubmissions . '</a>';
                } else {
                    $data[] = $clasification->nsubmissions;
                }
                $sortdata['nsubmissions'] = $clasification->nsubmissions;

                $data[] = $clasification->nsubmissionsassessment;
                $sortdata['nsubmissionsassessment'] = $clasification->nsubmissionsassessment;

                $data[] = $clasification->pointssubmission;
                $sortdata['pointssubmission'] = $clasification->nsubmissions;

                $data[] = $clasification->pointsanswers;
                $sortdata['pointsanswers'] = $clasification->pointsanswers;

                $data[] = $clasification->points;
                $sortdata['points'] = $clasification->points;

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

    if ($canpreview) {
        $table->align = array('left', 'left', 'center', 'center', 'left', 'center', 'center', 'center',
                        'center', 'center', 'center');
        $table->valign = array('center', 'center', 'center', 'center', 'left', 'center', 'center', 'center', 'center', 'center',
                        'center');
        $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment', 'nsubmissions',
                        'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'points');
    } else {

        $table->align = array('left', 'left', 'center', 'center', 'left', 'center', 'center', 'center',
                        'center', 'center', 'center');
        $table->valign = array('center', 'center', 'center', 'center', 'left', 'center', 'center', 'center', 'center', 'center',
                        'center');
        $columns = array('picture', 'firstname', 'lastname', 'nanswers', 'nanswersassessment', 'nsubmissions',
                        'nsubmissionsassessment', 'pointssubmission', 'pointsanswers', 'points');
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
            $columnicon = " <img src=\"" . $CFG->wwwroot . "pix/t/$columnicon.png\" alt=\"$columnicon\" />";
        }
        $$column = "<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=$column&amp;dir=$columndir\">" .
                 $string[$column] . "</a>$columnicon";
    }

    if ($canpreview) {

        $table->head = array("", "$firstname / $lastname", "$nanswers", "$nanswersassessment", "$nsubmissions",
                        "$nsubmissionsassessment", "$pointssubmission", "$pointsanswers", "$points");
    } else {

        $table->head = array("", "$firstname / $lastname", "$nanswers", "$nanswersassessment", "$nsubmissions",
                        "$nsubmissionsassessment", "$pointssubmission", "$pointsanswers", "$points");
    }

    echo '<tr><td>';
    echo '<div valign="center">';
    print_table($table);
    echo '</div>';
    echo '</td></tr>';
    echo '<tr><td>';

    print_heading(
            " <a href=\"viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
                     get_string('viewclasificationteams', 'quest') . "</a>");

    echo '</td></tr>';

    echo '</table>';
} else if ($action == 'teams') {
    $groupmode = $currentgroup = false; // JPC group support desactivation.

    // Print settings and things in a table across the top.
    echo '<table width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';
    // Print admin links.
    echo "<td align=\"right\">";
    echo "<a href=\"submissions.php?id=$cm->id&amp;action=adminlist\">" . get_string("administration") . "</a>\n";

    echo '</td></tr>';

    echo '<tr><td>';

    echo '</td></tr>';
    echo '</table>';
    print_heading(get_string('teams', 'quest'));

    // Get all the students.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        print_heading(get_string("nostudentsyet"));
        print_footer($course);
        exit();
    }

    // Now prepare table with student assessments and submissions.
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $teamstemp = array();

    if ($teams = $DB->get_records_select("quest_teams", "questid = ? ", array($quest->id))) {
        foreach ($teams as $team) {
            foreach ($users as $user) {
                // Skip if student not in group.
                if ($currentgroup) {
                    if (!groups_is_member($currentgroup, $user->id)) {
                        continue;
                    }
                }
                $clasification = $DB->get_record("quest_calification_users", array("userid" => $user->id, "questid" => $quest->id));
                if ($clasification->teamid == $team->id) {
                    $teamstemp[] = $team;
                }
            }
        }
    }
    $teams = $teamstemp;

    foreach ($teams as $team) {

        $data = array();
        $sortdata = array();

        $points = 0;
        $nanswers = 0;
        $nanswersassessment = 0;
        $nsubmissions = 0;
        $pointssubmission = 0;
        $pointsanswers = 0;
        $nsubmissionsassessment = 0;

        foreach ($users as $user) {
            // Skip if student not in group.
            if ($currentgroup) {
                if (!groups_is_member($currentgroup, $user->id)) {
                    continue;
                }
            }

            if ($clasification = $DB->get_record("quest_calification_users",
                    array("userid" => $user->id, "questid" => $quest->id))) {

                if ($team->id == $clasification->teamid) {

                    $points += $clasification->points;
                    $nanswers += $clasification->nanswers;
                    $nanswersassessment += $clasification->nanswersassessment;
                    $nsubmissions += $clasification->nsubmissions;
                    $pointssubmission += $clasification->pointssubmission;
                    $pointsanswers += $clasification->pointsanswers;
                    $nsubmissionsassessment += $clasification->nsubmissionsassessment;
                }
            }
        }

        $data[] = $team->name;
        $sortdata['team'] = $team->name;

        if ($canpreview) {
            $data[] = "<a href=\"submissions.php?tid=$team->id&amp;action=showanswersteam&amp;id=$cm->id\">" . $nanswers . '</a>';
        } else {
            $data[] = $nanswers;
        }
        $sortdata['nanswers'] = $nanswers;

        $data[] = $nanswersassessment;
        $sortdata['nanswersassessment'] = $nanswersassessment;

        if ($canpreview) {
            $data[] = "<a href=\"submissions.php?tid=$team->id&amp;action=showsubmissionsteam&amp;id=$cm->id\">" . $nsubmissions .
                     '</a>';
        } else {
            $data[] = $nsubmissions;
        }
        $sortdata['nsubmissions'] = $nsubmissions;

        $data[] = $nsubmissionsassessment;
        $sortdata['nsubmissionsassessment'] = $nsubmissionsassessment;

        $data[] = $pointssubmission;
        $sortdata['pointssubmission'] = $pointssubmission;

        $data[] = $pointsanswers;
        $sortdata['pointsanswers'] = $pointsanswers;

        $data[] = $points;
        $sortdata['points'] = $points;

        $tablesort->data[] = $data;
        $tablesort->sortdata[] = $sortdata;

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
            $columnicon = " <img src=\"" . $CFG->wwwroot . "pix/t/$columnicon.png\" alt=\"$columnicon\" />";
        }
        $$column = "<a href=\"viewclasification.php?" .
                "id=$id&amp;action=teams&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] .
                 "</a>$columnicon";
    }

    $table->head = array("$team", "$nanswers", "$nanswersassessment", "$nsubmissions", "$nsubmissionsassessment",
                    "$pointssubmission", "$pointsanswers", "$points");

    echo '<tr><td>';
    print_table($table);
    echo '</td></tr>';
    echo '<tr><td>';

    print_heading(
            " <a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">" .
                     get_string('viewclasificationglobal', 'quest') . "</a>");

    echo '</td></tr>';

    echo '</table>';
}

echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER'] . '#sid=' . $submission->id);

print_footer('none');

