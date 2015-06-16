<?php

/******************************************************
* Module developed at the University of Valladolid
* Designed and directed by Juan Pablo de Castro with the effort of many other
* students of telecommunciation engineering
* this module is provides as-is without any guarantee. Use it as your own risk.
*
* @author Juan Pablo de Castro and many others.
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quest




**********************************************************/

    require_once("../../config.php");
    require("lib.php");
    require("locallib.php");

global $CFG,$DB,$PAGE,$OUTPUT;

    $id=required_param('qid',PARAM_INTEGER);
    if (! $cm = get_coursemodule_from_id('quest', $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
            error("Course is misconfigured");
        }

        if (! $quest = $DB->get_record("quest",array("id"=> $cm->instance))) {
            error("Quest is incorrect");
        }

  
 
    require_login($course->id, false, $cm);
    $context = context_module::instance( $cm->id);
    $ismanager=has_capability('mod/quest:manage',$context);
    
if (!$ismanager)
{
error('No enough permissions');
}


/**
* Select various queries
*/
$query_id=optional_param('query','what',PARAM_ALPHA);

switch($query_id)
{
case 'ip':
	$query = $DB->get_records_select("log","module='quest' and cmid=?",array($cm->id),"time","ip,time,id");
	break;
case 'logs':
	$query = $DB->get_records_select("log","module='quest' and cmid=?",array($cm->id),"time");
	break;
case 'activity':
	list($insql,$inparams)=$DB->get_in_or_equal(array($cm->id));
	$allparams=array_merge(array($cm->module),$inparams);
	$querySQL ="
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
	
	$query= $DB->get_records_sql($querySQL,$allparams);
	break;

default:
	$query='';
}
/********
*
* Generate CSV report with $query
*
******************************/
$localeLang=$CFG->locale;

// Moodle's bug Spanish RFC code is ES not ESP
$localeLang=str_replace("esp","es",$localeLang);
$localeLang=str_replace("ESP","ES",$localeLang);

setlocale(LC_ALL, $localeLang.".utf8");

$LocaleConfig = localeConv();

//print_object($LocaleConfig);
//print(number_format(-123.23, 20 , $LocaleConfig[decimal_point],''));
//exit;

if($query)
    {
    	
    	header("Content-Type: text/csv");
	header('Content-Disposition: attachment; filename="'.date('Y-m-d',time()).'_'.$query_id.'_questournament_'.$cm->id.'.csv"');
    	$firstrow=true;
    	foreach($query as $log)
    	{
    		
    		$els=array();
    		$elsk=array();
    		foreach($log as $key=>$value)
    		{
//echo "<br/>".$value." == round:".round($value);
//echo "<br/>float?".abs($value-round($value));
//echo "isnum:".is_numeric($value)."<br/>";
//echo "isint:".is_integer($value)."<br/>";
//echo "isfloar:".is_float($value)."<br/>";
		// detect other fields not numeric like IPs
		if (is_numeric($value) && round($value)==$value) //integer
		{
			$els[]=$value;
		}
		else		
		if (is_numeric($value) && abs($value-round($value))<1) //number
		{
			$val=number_format($value,10,  $LocaleConfig[decimal_point], '');
			$els[]= $val;
		}
		else
		{
			$els[]=$value;
		}

		$elsk[]= $key;
    		}
    		if ($firstrow)
    		{
    			echo implode(";",$elsk)."\n";
    			$firstrow=false;    			
    		}
    		echo implode(";",$els)."\n";
    	}
    }
else
{
$strquests = get_string("modulenameplural", "quest");
$strquest  = get_string("modulename", "quest");

$url =  new moodle_url('/mod/quest/getLogs.php',array('qid'=>$id));
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();


/*if (function_exists('build_navigation'))
	{
	$navlinks = array();
    $navlinks[] = array('name' => 'QUESTournament Reports', 'link' => '', 'type' => 'activity');
    $navigation = build_navigation($navlinks,$cm);
	//$navigation = build_navigation($quest->name.': '.$strsubmission);
	print_header($course->shortname, $course->fullname, $navigation, '', '',
                     true, null, navmenu($course, $cm));
	}
	else
	{
	print_header_simple(format_string($quest->name)." Log page.", "",
                 "<a href=\"index.php?id=$course->id\">$strquests</a> ->
                  <a href=\"view.php?id=$cm->id\">".format_string($quest->name,true)."</a> -> QUESTournament Reports",
                  "", "", true);
	}*/
print("<p>For your locale \"<b>$localeLang</b>\" the decimal separator is \" <b>$LocaleConfig[decimal_point]</b> \". Check that your SpreadSheet interprets correctly this character.</p>");
if (!empty($querySQL))
{
print("Last query with no results.<br/>");//"<pre>".$querySQL."</pre>");
}


echo '<p>Generate CSV report for:';
echo '<ul>';
echo '<li> <a href="getLogs.php?qid='.$cm->id.'&query=logs">Logs</a>';
echo '<li> <a href="getLogs.php?qid='.$cm->id.'&query=ip">IP Addresses Accesses</a>';
echo '<li> <a href="getLogs.php?qid='.$cm->id.'&query=activity">Activity</a>';
echo '</ul>';

echo $OUTPUT->footer();
}    
?>
