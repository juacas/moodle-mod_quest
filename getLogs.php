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
 * Export of activity events
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */
require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

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

$params = [];
$params['id'] = $id;
$params['queryid'] = $queryid;
$thispageurl = new moodle_url('/mod/quest/index.php', $params);
$PAGE->set_url($thispageurl);
switch ($queryid) {
    case 'ip':
        $query = $DB->get_records_select("logstore_standard_log", "component='mod_quest' and contextid=?",
                                        array($context->id), "timecreated", "id,ip,timecreated");
        break;
    case 'logs':
        $query = $DB->get_records_select("logstore_standard_log", "component='mod_quest' and contextid=?",
                                        array($context->id), "timecreated");
        break;
    case 'activity':
        list($insql, $inparams) = $DB->get_in_or_equal(array($cm->id));
        $allparams = array_merge(array($cm->module), $inparams);
        $sqlquery = <<<SQL
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
ORDER BY id_alumno ASC, id_desafio, tpo_lectura;
SQL;

        $query = $DB->get_records_sql($sqlquery, $allparams);
        break;

    default:
        $query = '';
}

if ($query) {
    quest_export_csv($query, $queryid, $cm);
} else {
    $strquests = get_string("modulenameplural", "quest");
    $strquest = get_string("modulename", "quest");

    $url = new moodle_url('/mod/quest/getLogs.php', array('id' => $id));
    $PAGE->set_url($url);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    $localelang = current_language();
    setlocale(LC_ALL, $localelang );
    $localeconfig = localeconv();
    $a = (object)['localelang' => $localelang, 'localeconfigdecimal' => $localeconfig['decimal_point']];
    echo $OUTPUT->notification(get_string('quest:notifylocale', 'quest', $a), 'info');
    if (!empty($sqlquery)) {
        echo $OUTPUT->notification(get_string('quest:notifyemptylogs', 'quest'));
    }

    echo '<p>' . get_string('quest:generateCSVlogs', 'quest');
    echo '<ul>';
    $thispageurl->param('query', 'logs');
    echo '<li>' . $OUTPUT->action_icon($thispageurl,  new pix_icon('t/download', get_string('quest:generateLogsReport', 'quest') . ' '), null, null, true);
    $thispageurl->param('query', 'ip');
    echo '<li>' . $OUTPUT->action_icon($thispageurl,  new pix_icon('t/download', get_string('quest:generateIPAccessesReport', 'quest') . ' '), null, null, true);
    $thispageurl->param('query', 'activity');
    echo '<li>' . $OUTPUT->action_icon($thispageurl,  new pix_icon('t/download', get_string('quest:generateActivityReport', 'quest') . ' '), null, null, true);
    echo '</ul>';

    echo $OUTPUT->footer();
}