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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Questournament for Moodle. If not, see <http://www.gnu.org/licenses/>.

/** Questournament activity for Moodle
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 * Export of activity events
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 *          ******************************************************** */
require_once ("../../config.php");
require ("lib.php");
require ("locallib.php");

global $CFG, $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INTEGER);
list($course, $cm) = quest_get_course_and_cm($id);
$quest = $DB->get_record("quest", array("id" => $cm->instance), '*', MUST_EXIST);
require_login($course->id, false, $cm);
$context = context_module::instance($cm->id);
$ismanager = has_capability('mod/quest:manage', $context);
$candownloadlogs = has_capability('mod/quest:downloadlogs', $context);

if (!$candownloadlogs) {
    print_error('nopermissions', 'error', null, 'No enough permissions mod/quest:downloadlogs');
}
// Select various queries.
$queryid = optional_param('query', 'what', PARAM_ALPHA);

switch ($queryid) {
    case 'ip':
        $query = $DB->get_records_select("log", "module='quest' and cmid=?", array($cm->id), "time", "id,ip,time");
        break;
    case 'logs':
        $query = $DB->get_records_select("log", "module='quest' and cmid=?", array($cm->id), "time");
        break;
    case 'activity':
        list($insql, $inparams) = $DB->get_in_or_equal(array($cm->id));
        $allparams = array_merge(array($cm->module), $inparams);
        $sqlquery = "
SELECT {log}.id as id_log, {course_modules}.id AS id_QUEST_URL,
                {course_modules}.course AS id_course,
                {quest_answers}.submissionid AS id_desafio,
                {log}.userid AS id_alumno,
                {quest_answers}.grade AS grade,
                {quest_assessments}.pointsteacher AS nota_sin_normalizar,
                {quest}.datestart AS tpo_inicio,
                {log}.time AS tpo_lectura,
                {quest_answers}.date AS tpo_envio_respuesta,
                (({quest_answers}.date-{log}.time)/60) AS tpo_diferencia_min,
                {quest_answers}.perceiveddifficulty AS dificultad_alumno
FROM {log}, {quest_answers}, {course_modules}, {quest_assessments}, {quest}
WHERE {log}.module = 'quest'
                AND {log}.action = 'read_submission'
                AND {log}.cmid = {course_modules}.id /* id_QUEST_URL */
                AND {quest}.id = {quest_answers}.questid /* QUEST */
                AND {course_modules}.instance = {quest_answers}.questid /* QUEST */
                AND {quest_assessments}.questid = {quest_answers}.questid /* QUEST */
                AND {log}.info = {quest_answers}.submissionid /* Pregunta QUEST */
                AND {course_modules}.module = ? /* Mdulo = QUEST */
                AND {log}.cmid $insql /* cmid en mdl_log, id_QUEST_URL */
                AND {log}.userid = {quest_answers}.userid /* Usuario */
                AND {quest_answers}.id = {quest_assessments}.answerid /* mdl_quest_answersRespuesta QUEST */
                AND {quest_answers}.date > {log}.time /* tpo_envio_respuesta > tpo_lectura => Slo datos entre lectura y envio */
ORDER BY id_alumno ASC, id_desafio, tpo_lectura
;";

        $query = $DB->get_records_sql($sqlquery, $allparams);
        break;

    default:
        $query = '';
}
// Generate CSV report with $query.
$localelang = $CFG->locale;
// Moodle's bug Spanish RFC code is ES not ESP.
$localelang = str_replace("esp", "es", $localelang);
$localelang = str_replace("ESP", "ES", $localelang);

setlocale(LC_ALL, $localelang . ".utf8");

$localeconfig = localeconv();

// print_object($LocaleConfig);
// print(number_format(-123.23, 20 , $LocaleConfig[decimal_point],''));
// exit;

if ($query) {

    header("Content-Type: text/csv");
    header(
            'Content-Disposition: attachment; filename="' . date('Y-m-d', time()) . '_' . $queryid . '_questournament_' . $cm->id .
                     '.csv"');
    $firstrow = true;
    foreach ($query as $log) {

        $els = array();
        $elsk = array();
        foreach ($log as $key => $value) {
            // detect other fields not numeric like IPs
            if (is_numeric($value) && round($value) == $value) { // integer
                $els[] = $value;
            } else if (is_numeric($value) && abs($value - round($value)) < 1) { // number
                $val = number_format($value, 10, $localeconfig[decimal_point], '');
                $els[] = $val;
            } else {
                $els[] = $value;
            }

            $elsk[] = $key;
        }
        if ($firstrow) {
            echo implode(";", $elsk) . "\n";
            $firstrow = false;
        }
        echo implode(";", $els) . "\n";
    }
} else {
    $strquests = get_string("modulenameplural", "quest");
    $strquest = get_string("modulename", "quest");

    $url = new moodle_url('/mod/quest/getLogs.php', array('id' => $id));
    $PAGE->set_url($url);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    /*
     * if (function_exists('build_navigation'))
     * {
     * $navlinks = array();
     * $navlinks[] = array('name' => 'QUESTournament Reports', 'link' => '', 'type' => 'activity');
     * $navigation = build_navigation($navlinks,$cm);
     * //$navigation = build_navigation($quest->name.': '.$strsubmission);
     * print_header($course->shortname, $course->fullname, $navigation, '', '',
     * true, null, navmenu($course, $cm));
     * }
     * else
     * {
     * print_header_simple(format_string($quest->name)." Log page.", "",
     * "<a href=\"index.php?id=$course->id\">$strquests</a> ->
     * <a href=\"view.php?id=$cm->id\">".format_string($quest->name,true)."</a> -> QUESTournament
     * Reports",
     * "", "", true);
     * }
     */
    print
            ("<p>For your locale \"<b>$localelang</b>\" the decimal separator is \" <b>$localeconfig[decimal_point]</b> \". Check that your SpreadSheet interprets correctly this character.</p>");
    if (!empty($$$$sqlquery)) {
        print("Last query with no results.<br/>"); // "<pre>".$querySQL."</pre>");
    }

    echo '<p>Generate CSV report for:';
    echo '<ul>';
    echo '<li> <a href="getLogs.php?id=' . $cm->id . '&query=logs">Logs</a>';
    echo '<li> <a href="getLogs.php?id=' . $cm->id . '&query=ip">IP Addresses Accesses</a>';
    echo '<li> <a href="getLogs.php?id=' . $cm->id . '&query=activity">Activity</a>';
    echo '</ul>';

    echo $OUTPUT->footer();
}
