<?php  // $Id: report.php
/******************************************************
 * Module developed at the University of Valladolid
* Designed and directed by Juan Pablo de Castro with the effort of many other
* students of telecommunciation engineering
* this module is provides as-is without any guarantee. Use it as your own risk.
*
* @author Juan Pablo de Castro and many others.
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quest
***************************************/
/// This page prints a long report of this QUEST


require_once("../../config.php");
require_once("lib.php");
require("locallib.php");
 
$cmid = required_param('cmid', PARAM_INT);    // Course Module ID

global $DB, $PAGE, $OUTPUT;
$timenow = time();


$cm = get_coursemodule_from_id('quest', $cmid,0,false,MUST_EXIST);
$course = $DB->get_record("course", array("id"=> $cm->course),'*',MUST_EXIST);
$quest = $DB->get_record("quest", array("id"=> $cm->instance),'*',MUST_EXIST);
/// Print the page header and check login
require_login($course->id, false, $cm);

$context = context_module::instance( $cm->id);
$ismanager=has_capability('mod/quest:manage',$context);
require_capability('mod/quest:view',$context);

if ($cm->visible==0 && !has_capability('moodle/course:viewhiddenactivities', $context))
{
	print_error("Module hidden.",'quest',"view.php?id=$cmid");
}

$url =  new moodle_url('/mod/quest/report.php',array('cmid'=>$cmid));
$PAGE->set_url($url);
$PAGE->set_title(format_string($quest->name));
//$PAGE->set_context($context);
$PAGE->set_heading($course->fullname);

$strquests = get_string("modulenameplural", "quest");
$strquest  = get_string("modulename", "quest");

add_to_log($course->id, "quest", "report", "report.php?cmid=$cm->id", "$quest->id", "$cm->id");

echo $OUTPUT->header();


quest_print_quest_heading($quest);

echo $OUTPUT->box(format_text($quest->description));

echo '<br/>';
// iterate through submissions

if ($submissions=quest_get_submissions($quest))
foreach ($submissions as $submission)
{
	echo $OUTPUT->heading("Challenge: ".$submission->title,1);
	echo $OUTPUT->box_start();
	// output a submission
	$user = get_complete_user_data('id', $submission->userid);
	echo "<b>Author:</b>";
	// User Name Surname
	echo $OUTPUT->user_picture($user);
	echo "<a name=\"userid->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">".fullname($user).'</a>';
	echo '</td><td width="100%">';
	
			echo '<table border="0"><tr><td>';
			quest_print_submission_info($quest,$submission);
			echo '</td><td>';
			/**
			 * INCRUSTA GRÁFICO DE EVOLUCION DE PUNTOS
			 */
			quest_print_score_graph($quest,$submission);
			echo '</td></tr></table>';
			
			echo $OUTPUT->heading($submission->title);
			//echo $OUTPUT->heading(get_string('description','quest'));
			/***
			 *  Wording of the challenge
			***/
			quest_print_submission($quest, $submission);
			echo $OUTPUT->box_end();
			echo '<br/>';
			// list answers
			$sort = optional_param('sort', 'dateanswer', PARAM_ALPHA);
			$dir= optional_param('dir', "ASC", PARAM_ALPHA);
			if ($answers = quest_get_submission_answers($submission))
			{
				echo $OUTPUT->heading("Answers of challenge: $submission->title",2);
				echo $OUTPUT->box_start();
				quest_print_table_answers($quest,$submission,$course,$cm,$sort,$dir);
				echo '<br/>';
				foreach ($answers as $answer)
				{
					
					$user = get_complete_user_data('id', $answer->userid);
					echo '<table border="0"><tr><td>';
					 
							// User Name Surname
					echo $OUTPUT->user_picture($user);
					echo "<a name=\"userid->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">".fullname($user).'</a>';
					echo '</td><td width="100%">';
						
					echo $OUTPUT->heading("Answer: ".$answer->title,3);
					quest_print_answer_info($quest,$answer);
					
			       // echo $OUTPUT->heading(get_string('answercontent','quest'));
		    	    quest_print_answer($quest, $answer);
		    	    echo '</td></tr></table>';
		    	    	
				}
				echo $OUTPUT->box_end();
			}
				
	echo '<br/>';
}


