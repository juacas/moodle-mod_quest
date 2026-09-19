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
$sid = optional_param('sid', null, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$group = optional_param('group', -1, PARAM_INT);

$actionclasification = optional_param('actionclasification', 'global', PARAM_ALPHA);
$repeatactionsbelow = false;

if (empty($actionclasification)) {
    if (!isset($USER->showclasifindividual)) {
        $actionclasification = 'global';
    } else {
        $actionclasification = $USER->showclasifindividual;
    }
}
global $DB, $PAGE, $OUTPUT, $USER;
$timenow = time();
// Print the page header.

list($course, $cm) = quest_get_course_and_cm($id);

$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
require_capability('mod/quest:view', $context);

if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $context)) {
    throw new \moodle_exception('modulehiddenerror', 'quest');
}

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$url = new moodle_url('/mod/quest/view.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
$strquests = get_string("modulenameplural", "quest");
$strquest = get_string("modulename", "quest");

$changegroup = optional_param('group', -1, PARAM_INT); // Group change requested?
$groupmode = groups_get_activity_group($cm); // Groups are being used?
$currentgroup = groups_get_activity_group($cm);
$groupmode = $currentgroup = false; // JPC group support desactivation.

$teamname = optional_param('team', null, PARAM_RAW);
if (($quest->usepassword) && (!$ismanager)) {
    quest_require_password($quest, $course, required_param('userpassword', PARAM_RAW));
}

// Teachers must complete grading elements and students must enroll in a team if enabled.
if (has_capability('mod/quest:manage', $context)) {

    if (empty($action)) { // No action specified, either go straight to elements page else the admin
                          // page has the assignment any elements.
        $elementsforthissubmission = $DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0));

        if (isset($sid)) {
            $submissions = $DB->get_record("quest_submissions", array('id' => $sid));
            $numelementsexpectedinsubmission = $submissions == false ? 0 : $submissions->numelements;
        } else {
            $numelementsexpectedinsubmission = 0;
        }

        if ($quest->gradingstrategy == 0 ||
                 ($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => 0)) >= $quest->nelements)
                 ||
                 (($elementsforthissubmission >= $numelementsexpectedinsubmission) && ($elementsforthissubmission != 0))) {
            $numelements = $DB->count_records("quest_elementsautor", array("questid" => $quest->id));

            if ($quest->gradingstrategyautor == 0 || $numelements >= $quest->nelementsautor) {
                $action = "teachersview";
            } else {
                redirect("assessments_autors.php?action=editelements&id=$cm->id&sesskey=" . sesskey());
            }
        } else {
            redirect("assessments.php?action=editelements&id=$cm->id&sesskey=" . sesskey());
        }
    }
} else if (has_capability('mod/quest:preview', $context)) { // It's a non-editing teacher.
    $action = 'teachersview';
} else if (has_capability('mod/quest:attempt', $context)) {
    // He's a student then...
    // Create a grade record and register the user as active in the quest.

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
            $action = 'studentsview';
        }
    }

    if ($calificationuser = $DB->get_record("quest_calification_users", array("userid" => $USER->id, "questid" => $quest->id))) {
        if ($quest->allowteams == 1) {
            if (empty($calificationuser->teamid)) {
                if (empty($teamname) || trim($teamname) == '') { // JPC: 20-11-2008: prevent
                                                                 // creation of teams without name.
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
                    echo "<input type=\"button\" value=\"" . get_string("continue") . "\" onclick=\"document.teams.submit();\" />";
                    echo "</td></tr></table>";
                    echo $OUTPUT->box_end();
                    $sortteam = optional_param('sortteam', '', PARAM_ALPHA);
                    $dirteam = optional_param('dirteam', '', PARAM_ALPHA);
                    quest_print_table_teams($quest, $course, $cm, $sortteam, $dirteam);
                    echo $OUTPUT->footer();
                    exit();
                } else {
                    // Team assignation or creation.
                    if ($team = $DB->get_record("quest_teams",
                            array("name" => $teamname, "questid" => $quest->id, "currentgroup" => $currentgroup))) {
                        if ($quest->ncomponents > $team->ncomponents) {
                            $team->ncomponents++;
                            $DB->set_field("quest_teams", "ncomponents", $team->ncomponents, array("id" => $team->id));
                            $calificationuser->teamid = $team->id;
                            $DB->set_field("quest_calification_users", "teamid", $calificationuser->teamid,
                                    array("id" => $calificationuser->id));
                        } else {
                            echo $OUTPUT->header();
                            echo ('<center><b>The Team is complete</b></center>');
                            echo $OUTPUT->continue_button("view.php?id=$cm->id");
                            exit();
                        }
                    } else { // ...new team.
                        $team = new stdClass();
                        $team->ncomponents = 1;
                        $team->questid = $quest->id;
                        $team->currentgroup = $currentgroup;
                        $team->name = trim($teamname);
                        // JPC: 20-11-2008: prevent creation of teams without name.
                        if ($team->name == '') {
                            $team->name = 'Team_user(' . $calificationuser->id . ")";
                        }
                        $team->id = $DB->insert_record("quest_teams", $team);
                        $calificationteam = new stdClass();
                        $calificationteam->teamid = $team->id;
                        $calificationteam->questid = $quest->id;
                        $calificationteam->id = $DB->insert_record("quest_calification_teams", $calificationteam);
                        $calificationuser->teamid = $team->id;
                        $DB->set_field("quest_calification_users", "teamid", $calificationuser->teamid,
                                array("id" => $calificationuser->id));
                    }
                } // ...end team assignation.
            }
        }
    } else if (!is_siteadmin($USER)) { // User do not have a $calification_user entry...prevent the
                                       // admin
                                       // to be captured as a participant in the
                                       // questournament.
        $calificationuser = new stdClass();
        $calificationuser->userid = $USER->id;
        $calificationuser->questid = $quest->id;
        $calificationuser->id = $DB->insert_record("quest_calification_users", $calificationuser);
        if ($quest->allowteams == 1) {
            if (!isset($teamname) || trim($teamname) == '') {
                echo $OUTPUT->header();
                echo "<br><br>";
                echo $OUTPUT->box_start("center");
                echo "<form name=\"teams\" method=\"post\" action=\"view.php\">\n";
                echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />\n";
                echo "<table cellpadding=\"7px\">";
                if (isset($teamname)) {
                    echo "<tr align=\"center\" style='color:#DF041E;'><td>" . get_string("wrongteam", "quest") . "</td></tr>";
                }
                echo "<tr align=\"center\"><td>" . get_string("teamforquest", "quest", format_string($quest->name)) . "</td></tr>";
                echo "<tr align=\"center\"><td>" . get_string("enterteam", "quest") .
                         " <input type=\"text\" name=\"team\" /></td></tr>";

                echo "<tr align=\"center\"><td>";

                echo "<input type=\"button\" value=\"" . get_string("continue") . "\" onclick=\"document.teams.submit();\" />";
                echo "</td></tr></table>";
                echo $OUTPUT->box_end();
                $sortteam = optional_param('sortteam', '', PARAM_ALPHA);
                $dirteam = optional_param('dirteam', '', PARAM_ALPHA);

                quest_print_table_teams($quest, $course, $cm, $sortteam, $dirteam);
                echo $OUTPUT->footer();
                exit();
            } else if (null !== optional_param('team', null, PARAM_INT)) {
                if ($team = $DB->get_record("quest_teams",
                        array("name" => $teamname, "questid" => $quest->id, "currentgroup" => $currentgroup))) {
                    if ($quest->ncomponents > $team->ncomponents) {
                        $team->ncomponents++;
                        $DB->set_field("quest_teams", "ncomponents", $team->ncomponents, array("id" => $team->id));
                        $calificationuser->teamid = $team->id;
                        $DB->set_field("quest_calification_users", "teamid", $calificationuser->teamid,
                                array("id" => $calificationuser->id));
                    } else {
                        echo $OUTPUT->header();
                        echo ('<center><b>The Team is complete</b></center>');
                        echo $OUTPUT->continue_button("view.php?id=$cm->id");
                        echo $OUTPUT->footer();
                        exit();
                    }
                }
            }
        } // Endif allow teams.
    }
}
// Log event.
\mod_quest\event\quest_viewed::create_from_parts($USER, $quest, $cm)->trigger();
echo $OUTPUT->header();

if ($action == 'notavailable') {
    echo $OUTPUT->notification($message);
} else if ($action == 'teachersview' || $action == 'studentsview' || $action == 'displayfinalgrade') {
    $canviewauthors = has_capability('mod/quest:viewotherattemptsowners', $context);
    $currentgroup = groups_get_course_group($course);
    $currentgroup = false; // JPC group support desactivation.

    // 1. Gather Summary Data (Resumen de datos en la parte inicial de la página).
    $summarydata = [
        'ismanager' => $ismanager,
        'challengegradinghtml' => '',
        'answergradinghtml' => '',
        'introattachments' => '',
        'simplecalificationhtml' => '',
        'clasificationswitchurl' => '',
        'clasificationswitchlabel' => '',
    ];

    ob_start();
    quest_print_challenge_grading_link($cm, $context, $quest);
    $summarydata['challengegradinghtml'] = ob_get_clean();

    ob_start();
    quest_print_answer_grading_link($cm, $context, $quest);
    $summarydata['answergradinghtml'] = ob_get_clean();

    ob_start();
    quest_print_attachments($context, 'introattachment', false, 'timemodified');
    $summarydata['introattachments'] = ob_get_clean();

    if (($quest->allowteams) && ($quest->showclasifindividual == 1)) {
        if ($actionclasification == 'global') {
            $summarydata['clasificationswitchurl'] = (new moodle_url('/mod/quest/view.php', [
                'actionclasification' => 'teams',
                'id' => $cm->id,
            ]))->out(false);
            $summarydata['clasificationswitchlabel'] = get_string('resumeteams', 'quest');
        } else {
            $summarydata['clasificationswitchurl'] = (new moodle_url('/mod/quest/view.php', [
                'actionclasification' => 'global',
                'id' => $cm->id,
            ]))->out(false);
            $summarydata['clasificationswitchlabel'] = get_string('resumeindividual', 'quest');
        }
    }

    ob_start();
    quest_print_simple_calification($quest, $course, $currentgroup, $actionclasification);
    $summarydata['simplecalificationhtml'] = ob_get_clean();

    // 2. Prepare detail table with student assessments and submissions.
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $indice = 0;
    $initialpoints = [];
    $nanswerscorrect = [];
    $datesstart = [];
    $datesend = [];
    $dateanswercorrect = [];
    $pointsmax = [];
    $pointsmin = [];
    $pointsanswercorrect = [];
    $tinitial = [];
    $state = [];
    $pointsnmaxanswers = [];
    $forms = [];
    if ($submissions = quest_get_submissions($quest)) {
        foreach ($submissions as $submission) {
            // Get the author of this submission.
            if ($submission->userid == 0) { // Anonymous user.
                $user = false;
            } else { // Guest user.
                $user = $DB->get_record('user', array('id' => $submission->userid));
            }
            // Skip if student not in group.
            if (!has_capability('mod/quest:manage', $context)) {
                if ($currentgroup) {
                    if ($user !== null && !groups_is_member($currentgroup, $user->id)) {
                        continue;
                    }
                }
            }

            $data = array();
            $sortdata = array();

            if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
                     ($submission->nanswerscorrect < $quest->nmaxanswers) && $submission->phase != SUBMISSION_PHASE_ACTIVE) {
                $submission->phase = SUBMISSION_PHASE_ACTIVE; // Running...
                $DB->update_record('quest_submissions', $submission);
            }

            // Skip a submission not viewable by this user...
            if ($submission->state == SUBMISSION_STATE_APPROVAL_PENDING &&
                    !has_capability('mod/quest:approvechallenge', $context) &&
                    !has_capability('mod/quest:manage', $context) && $submission->userid != $USER->id) {
                continue;
            }
            // Skip challenge for student if the challenge is not started...
            if (!has_capability('mod/quest:manage', $context) && // manage permission
                $submission->datestart > $timenow && // Challenge in StartPending
                $submission->userid != $USER->id) { // USER is not the author
                continue; // Omit it...
            }
            $mineicon = $submission->userid == $USER->id && !$canviewauthors ? $OUTPUT->user_picture($USER) : '';
            $titletext = $mineicon . quest_print_submission_title($quest, $submission);

            // Show or not the edit controls.
            if (((has_capability('mod/quest:editchallengeall', $context) or ($submission->userid == $USER->id)) and
                    ($submission->nanswers == 0) and ($timenow < $submission->dateend) and
                     ($submission->state != SUBMISSION_STATE_APROVED)) or ($ismanager)) {
                $editicon = $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest'));
                $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest'));
                $titletext .= "<a href=\"challenges.php?action=modif&amp;id=$cm->id&amp;cid=$submission->id\">" . $editicon .
                        '</a>' .
                         " <a href=\"challenges.php?action=confirmdelete&amp;id=$cm->id&amp;cid=$submission->id\">" . $deleteicon .
                         '</a>';
            }

            $data[] = $titletext;
            $sortdata['title'] = strtolower($submission->title);
            if ($canviewauthors) {
                if ($user === false) { // Sometimes the author is no longer in the system.
                    $data[] = "?";
                    $data[] = "Unknown author ($submission->userid)";
                    $sortdata['firstname'] = "Unknown";
                    $sortdata['lastname'] = "Unknown";
                } else {
                    $data[] = $OUTPUT->user_picture($user);
                    $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?" .
                              "id=$user->id&amp;course=$course->id\">" .
                              fullname($user) . '</a>';
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

            $data[] = "<b>" . $submission->nanswers . ' (' . $submission->nanswerscorrect . ') [' . $nanswerswhithoutassess . ']' .
                     $image . '</b>';
            $sortdata['nanswersshort'] = $submission->nanswers;
            $sortdata['nanswerscorrectshort'] = $submission->nanswerscorrect;
            $sortdata['nanswerswhithoutassess'] = $nanswerswhithoutassess;

            $data[] = userdate($submission->datestart, get_string('strftimedatetimeshort', 'langconfig'));
            $sortdata['datestart'] = $submission->datestart;

            $data[] = userdate($submission->dateend, get_string('strftimedatetimeshort', 'langconfig'));
            $sortdata['dateend'] = $submission->dateend;

            $tinitialval = (int)$quest->tinitial * 86400;
            $currentpoints = quest_get_points($submission, $quest, '');
            $currentformatted = number_format($currentpoints, 4);
            $grade = "<form class=\"d-inline\"><input id=\"formscore$indice\" class=\"quest-score-counter\" " .
                    "name=\"calificacion\" type=\"text\" value=\"$currentformatted\" size=\"10\" readonly=\"1\" " .
                    "data-datestart=\"{$submission->datestart}\" " .
                    "data-dateend=\"{$submission->dateend}\" " .
                    "data-tinitial=\"{$tinitialval}\" " .
                    "data-dateanswercorrect=\"{$submission->dateanswercorrect}\" " .
                    "data-initialpoints=\"{$submission->initialpoints}\" " .
                    "data-pointsmax=\"{$submission->pointsmax}\" " .
                    "data-pointsmin=\"{$submission->pointsmin}\" " .
                    "data-type=\"{$quest->typecalification}\" " .
                    "style=\"background-color : White; border : 1px solid #ced4da; border-radius: 4px; color : Black; " .
                    "font-size : 12pt; text-align : center; font-weight: bold;\"></form>";

            $data[] = $grade;
            $sortdata['calification'] = $currentpoints;

            $indice++;

            $tablesort->data[] = $data;
            $tablesort->sortdata[] = $sortdata;
        }
        // Javascript counter support via DOM data attributes.
        $servertime = time();
        $PAGE->requires->js_call_amd('mod_quest/counter', 'init', [$servertime]);
    }
    $sort = optional_param('sort', 'dateend', PARAM_ALPHA);
    uasort($tablesort->sortdata, 'quest_sortfunction');

    $table = new html_table();
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }
    if ($canviewauthors) {
        $columns = array('title', 'firstname', 'lastname', 'phase', 'nanswersshort', 'nanswerscorrectshort',
                        'nanswerswhithoutassess', 'datestart', 'dateend', 'calification');
    } else { // Removed personal info column.
        $columns = array('title', 'phase', 'nanswersshort', 'nanswerscorrectshort', 'nanswerswhithoutassess',
                        'datestart', 'dateend', 'calification');
    }
    // Define a new variable for each column with heading texts.
    $string = [];
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
        $$column = "<a href=\"view.php?id=$id&amp;sort=$column&amp;dir=$columndir&amp;view=list\">" . $string[$column] . "</a>$columnicon";
    }

    if ($canviewauthors) {
        $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
        $table->head = array("$title", "$firstname / $lastname", "$phase",
                        "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart",
                        "$dateend", "$calification");
        $table->headspan = array(1, 2, 1, 1, 1, 1, 1);
    } else { // ...hide personal column.
        $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
        $table->head = array("$title", "$phase", "$nanswersshort($nanswerscorrectshort)[$nanswerswhithoutassess]", "$datestart",
                        "$dateend", "$calification");
    }
    $table->attributes['class'] = 'table table-hover table-striped align-middle mb-0';

    $detailtablehtml = !empty($table->data) ? html_writer::table($table) : '';
    $grafic = $OUTPUT->pix_icon('t/check', 'ok');
    $legendhtml = !empty($table->data) ? get_string('legend', 'quest', $grafic) : '';

    // 3. Get challenges for cards.
    \mod_quest\question\bank_provider::ensure_student_question_capabilities($context);
    $challenges = \mod_quest\service\tournament_manager::get_challenges($quest->id, $USER->id);
    $canaddchallenge = has_capability('mod/quest:addchallenge', $context) && ($quest->dateend > $timenow);

    // 4. Render unified view page (Resumen al inicio + Desafíos Cards/List).
    $servertime = time();
    $PAGE->requires->js_call_amd('mod_quest/counter', 'init', [$servertime]);

    $viewpage = new \mod_quest\output\view_page(
        $quest,
        $course,
        $cm,
        $challenges,
        $canaddchallenge,
        $summarydata,
        $detailtablehtml,
        $legendhtml
    );
    $renderer = $PAGE->get_renderer('mod_quest');
    echo $renderer->render_view_page($viewpage);
} else {
    throw new \moodle_exception('unknownactionerror', 'quest', '', $action);
}
// Finish the page.
echo $OUTPUT->footer();

