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
$sort = optional_param('sort', 'rank', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

/*
 * Flag to force a recalculation of team statistics and scores.
 */
$debugrecalculate = optional_param('recalculate', 'no', PARAM_ALPHA);

$timenow = time();
$numberprecission = 2;
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
if ($sort !== 'rank') {
    $thispageurl->param('sort', $sort);
}
if ($dir !== 'ASC') {
    $thispageurl->param('dir', $dir);
}

$classificationtitle = ($action === 'teams') ? get_string('teams', 'quest') : get_string('globalranking', 'quest');

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
    $standings = \mod_quest\service\leaderboard_service::get_individual_standings($quest->id, $sort, $dir);
    $renderer = $PAGE->get_renderer('mod_quest');
    echo $renderer->render_leaderboard_page(new \mod_quest\output\leaderboard_page($quest, $course, $cm, $standings, false, $sort, $dir));

    if ($quest->allowteams) {
        $teamsurl = new moodle_url('/mod/quest/viewclasification.php', ['action' => 'teams', 'id' => $cm->id]);
        echo '<div class="text-center my-3"><a href="' . $teamsurl->out() . '" class="btn btn-outline-primary">' .
             get_string('viewclasificationteams', 'quest') . '</a></div>';
    }
} else if ($action == 'teams') {
    $standings = \mod_quest\service\leaderboard_service::get_team_standings($quest->id, $sort, $dir);
    $renderer = $PAGE->get_renderer('mod_quest');
    echo $renderer->render_leaderboard_page(new \mod_quest\output\leaderboard_page($quest, $course, $cm, $standings, true, $sort, $dir));
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
$bottombuttons = [];
$continueurl = new moodle_url('/mod/quest/view.php', ['id' => $id]);
$bottombuttons[] = html_writer::link($continueurl, get_string('continue'), ['class' => 'btn btn-secondary']);

if (has_capability('mod/quest:viewreports', $context)) {
    $exporturl = new moodle_url('/mod/quest/viewclasification.php', ['id' => $id, 'action' => 'export']);
    $exportlabel = get_string('quest:generateCSVlogs', 'quest') . ' ' . $classificationtitle;
    $bottombuttons[] = html_writer::link(
        $exporturl,
        '<i class="fa fa-download me-1" aria-hidden="true"></i>' . $exportlabel,
        ['class' => 'btn btn-outline-success']
    );
}

echo html_writer::div(implode(' ', $bottombuttons), 'd-flex flex-wrap justify-content-center align-items-center gap-2 my-4');
echo $OUTPUT->footer();
