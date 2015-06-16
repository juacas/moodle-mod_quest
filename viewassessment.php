<?php  // $Id: viewassessment.php
/******************************************************
* Module developed at the University of Valladolid
* Designed and directed by Juan Pablo de Castro with the effort of many other
* students of telecommunciation engineering
* this module is provides as-is without any guarantee. Use it as your own risk.
*
* @author Juan Pablo de Castro and many others.
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quest
*****************************************/

    require("../../config.php");
    require("lib.php");
    require("locallib.php");

	global $DB, $OUTPUT, $PAGE;
    
	$asid=required_param('asid',PARAM_INT);   // Assessment ID
    $sid=optional_param('sid',0,PARAM_INT);
    $allowcomments=optional_param('allowcomments',false,PARAM_BOOL);
    $redirect=optional_param('redirect','',PARAM_LOCALURL);

           if (! $assessment = $DB->get_record("quest_assessments", array("id"=> $asid)))
               error("Assessment id is incorrect");

           if (! $answer = $DB->get_record('quest_answers', array('id'=> $assessment->answerid)))
               error("Incorrect answer id");

           if (! $submission = $DB->get_record('quest_submissions', array('id'=> $answer->submissionid)))
               error("Incorrect submission id");

           if (! $quest = $DB->get_record("quest", array("id"=> $submission->questid)))
               error("Quest is incorrect");

           if (! $course = $DB->get_record("course", array("id"=> $quest->course)))
               error("Course is misconfigured");
           if (! $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id))
               error("No coursemodule found");

   require_login($course->id, false, $cm);
   
   quest_check_visibility($course, $cm);
   
   $context = context_module::instance( $cm->id);
   $ismanager=has_capability('mod/quest:manage',$context);
    
   $url =  new moodle_url('/mod/quest/viewassessment.php',array('asid'=>$asid,'sid'=>$sid,'allowcomments'=>$allowcomments,'redirect'=>$redirect));
   $PAGE->set_url($url);
    
   $PAGE->set_title(format_string($quest->name));
   $PAGE->set_heading($course->fullname);
   echo $OUTPUT->header();
   
   
	if (!$ismanager && $answer->userid != $USER->id && $assessment->userid != $USER->id) 
	{
	print_error("Unauthorized access!");
	}
   $strquests = get_string("modulenameplural", "quest");
   $strquest  = get_string("modulename", "quest");
   $strassess = get_string("viewassessment", "quest");
      
   if (!$redirect)
   {
       $redirect = "submissions.php?cmid=$cm->id&sid=$sid&action=showsubmission#sid=$sid";
   }

   
		
       

        echo $OUTPUT->heading_with_help(get_string('seeassessment','quest'),"seeassessment","quest");

       if (($ismanager)||($answer->userid == $USER->id)||($assessment->userid == $USER->id))
	   {
	   	 // show assessment but don't allow changes
				quest_print_assessment($quest, $sid, $assessment, false, $allowcomments);
		}

        echo "<br>";
        if ($answer->userid == $USER->id) {
			
		
                if (!isset($answer->commentforteacher)) {
                      $answer->commentforteacher = '';
                }

                echo "<form name=\"gradingform\" action=\"answer.php\" method=\"post\">";
                echo "<a name=\"Claims\"><input type=\"hidden\" name=\"action\" value=\"updatecomment\" /></a>";
                echo "<input type=\"hidden\" name=\"redirect\" value=\"$redirect\"/>";
                echo "<input type=\"hidden\" name=\"sid\" value=\"$submission->id\" /> ";
                echo "<input type=\"hidden\" name=\"aid\" value=\"$answer->id\" /> ";
                echo "<center>";
                echo "<table cellpadding=\"5\" border=\"1\">";
                echo "<tr valign=\"top\">";
                echo "<td align=\"right\"><b>".get_string("commentsforteacher", "quest")."</b></td>";
                echo "<td>";
                echo "<textarea name=\"teachercomment\" rows=\"5\" cols=\"75\">$answer->commentforteacher</textarea>";
                echo " </td>";
                echo "</tr>";

                echo "</table>";
                echo "<input type=\"submit\" value=\"".get_string("save", "quest")."\" />";
                echo "</center>";
                echo "</form>";

        }
        if($ismanager){
                if(!empty($answer->commentforteacher)){
                        echo "<a name=\"Claims\"></a>";
                        echo "<b>".get_string("commentsforteacher", "quest"). "</b><br>";
                        print_simple_box(format_text($answer->commentforteacher), 'center');
                }

        }

       

    if (! $answer = $DB->get_record('quest_answers', array('id'=> $assessment->answerid)))
	        error("Incorrect answer id");

	if (! $submission = $DB->get_record('quest_submissions', array('id'=> $answer->submissionid)))
	        error("Incorrect submission id");

	if (! $quest = $DB->get_record("quest", array("id"=> $submission->questid)))
	        error("Quest is incorrect");
    if (! $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id))
	        error("No coursemodule found");

    $title = get_string('answername','quest',$answer);
  
    if (($ismanager||($answer->userid == $USER->id))) {
            $title .= ' '.get_string('by', 'quest').' '.quest_fullname($answer->userid, $course->id);
    }
	
    $title .= " ".get_string('tothechallenge','quest')."<a name=\"sid_$submission->id\" href=\"submissions.php?cmid=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\">$submission->title</a>";

    echo $OUTPUT->heading($title);
    
    quest_print_answer_info($quest,$answer);

//     echo("<center><b><a href=\"assessments.php?cmid=$cm->id&amp;action=displaygradingform\">".
//                 get_string("specimenassessmentform", "quest")."</a></b></center>");
	echo $OUTPUT->box_start();
    echo $OUTPUT->heading(get_string('answercontent','quest'));
   
    
    quest_print_answer($quest, $answer);
 	echo $OUTPUT->box_end();
 	if (!empty($redirect))
  		echo $OUTPUT->continue_button($redirect);

 	echo $OUTPUT->footer();
	
?>

