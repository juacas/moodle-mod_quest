<?php
// This file is part of Questournament activity for Moodle - http://moodle.org/
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
 * Interactive calendar and timeline for scheduling and reordering challenges.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once('locallib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$action = optional_param('action', 'view', PARAM_ALPHA);

list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record('quest', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course->id, false, $cm);
quest_check_visibility($course, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quest:manage', $context);

// Handle AJAX save.
if ($action === 'saveschedule') {
    require_sesskey();
    header('Content-Type: application/json; charset=utf-8');

    $schedules = [];
    $autoexpand = optional_param('autoexpand', 1, PARAM_BOOL);
    $rawjson = optional_param('schedules', '', PARAM_RAW);
    if (!empty($rawjson)) {
        $decoded = json_decode($rawjson, true);
        if (is_array($decoded)) {
            $schedules = $decoded;
        }
    } else {
        $body = file_get_contents('php://input');
        if (!empty($body)) {
            $data = json_decode($body, true);
            if (!empty($data['schedules']) && is_array($data['schedules'])) {
                $schedules = $data['schedules'];
            }
            if (isset($data['autoexpand'])) {
                $autoexpand = !empty($data['autoexpand']);
            }
        }
    }

    if (empty($schedules)) {
        echo json_encode([
            'success' => false,
            'message' => get_string('scheduleerrorinvaliditem', 'quest'),
            'errors' => [get_string('scheduleerrorinvaliditem', 'quest')],
        ]);
        exit;
    }

    $result = \mod_quest\service\tournament_manager::update_challenge_schedule(
        $quest,
        $cm,
        $schedules,
        $autoexpand
    );
    echo json_encode($result);
    exit;
}

// Default action: View interactive timeline calendar.
$url = new moodle_url('/mod/quest/schedule.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name) . ': ' . get_string('reorderchallengestitle', 'quest'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('reorderchallengestitle', 'quest'));

// Retrieve all challenges in this tournament with full user name fields.
$namefields = \core_user\fields::for_name()->get_sql('u')->selects;
$sql = "SELECT s.*, u.email $namefields
          FROM {quest_submissions} s
          JOIN {user} u ON u.id = s.userid
         WHERE s.questid = :questid
      ORDER BY s.datestart ASC, s.id ASC";
$submissions = $DB->get_records_sql($sql, ['questid' => $quest->id]);

$challengesdata = [];
$tabledateformat = get_string('strftimedatetimeshort', 'langconfig');
$headerdateformat = get_string('strftimedatetime', 'langconfig');

foreach ($submissions as $s) {
    $datestart = (int)$s->datestart;
    $dateend = (int)$s->dateend;
    $diffsec = max(0, $dateend - $datestart);
    $durdays = round($diffsec / 86400, 1);
    $durhours = round($diffsec / 3600, 1);

    $challengesdata[] = [
        'id' => (int)$s->id,
        'title' => format_string($s->title),
        'author' => fullname($s),
        'datestart' => $datestart,
        'dateend' => $dateend,
        'datestartstr' => userdate($datestart, $tabledateformat),
        'dateendstr' => userdate($dateend, $tabledateformat),
        'duration_days' => $durdays,
        'duration_hours' => $durhours,
        'state' => (int)$s->state,
        'pointsmax' => (float)$s->pointsmax,
        'pointsmin' => (float)$s->pointsmin,
        'nanswers' => (int)$s->nanswers,
        'viewurl' => (new moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id,
            'cid' => $s->id,
            'action' => 'showchallenge',
        ]))->out(false),
    ];
}

$queststart = (int)$quest->datestart;
$questend = (int)$quest->dateend;
$totalquestdays = max(1, round(($questend - $queststart) / 86400, 1));

$PAGE->requires->js_call_amd('mod_quest/schedule_calendar', 'init', [[
    'cmid' => (int)$cm->id,
    'questid' => (int)$quest->id,
    'questStart' => $queststart,
    'questEnd' => $questend,
    'sesskey' => sesskey(),
    'saveUrl' => (new moodle_url('/mod/quest/schedule.php', ['id' => $cm->id, 'action' => 'saveschedule']))->out(false),
    'lang' => current_language(),
]]);

$templatedata = [
    'questname' => format_string($quest->name),
    'backurl' => (new moodle_url('/mod/quest/view.php', ['id' => $cm->id]))->out(false),
    'queststartstr' => userdate($queststart, $headerdateformat),
    'questendstr' => userdate($questend, $headerdateformat),
    'totaldays' => $totalquestdays,
    'challengecount' => count($challengesdata),
    'haschallenges' => !empty($challengesdata),
    'challenges' => $challengesdata,
    'challengesjson' => json_encode($challengesdata, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    'sesskey' => sesskey(),
    'saveurl' => (new moodle_url('/mod/quest/schedule.php', ['id' => $cm->id, 'action' => 'saveschedule']))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_quest/schedule', $templatedata);
echo $OUTPUT->footer();
