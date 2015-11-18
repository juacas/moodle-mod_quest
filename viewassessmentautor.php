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
// along with Questournament for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Questournament activity for Moodle
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

    require("../../config.php");
    require("lib.php");
    require("locallib.php");

    $aid=required_param('aid',PARAM_INT);   // Assessment ID
   $allowcomments=optional_param('allowcomments',false,PARAM_BOOL);
    $redirect=optional_param('redirect','',PARAM_URL);
    $sort=optional_param('sort','dateanswer',PARAM_ALPHA);
    $dir=optional_param('dir','ASC',PARAM_ALPHA);

  	global $DB,$PAGE,$OUTPUT;

    if (! $assessment = $DB->get_record("quest_assessments_autors", array("id"=> $aid))) {
        error("Assessment id is incorrect");
    }
    if (! $submission = $DB->get_record('quest_submissions', array('id'=> $assessment->submissionid))) {
        error("Incorrect submission id");
    }
    if (! $quest = $DB->get_record("quest", array("id"=> $submission->questid))) {
        print_error("incorrectQuest",'quest');;
    }
    if (! $course = $DB->get_record("course",array("id"=> $quest->course))) {
        print_error("course_misconfigured",'quest');
    }
    if (! $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
        error("No coursemodule found");
    }
    if (!$redirect) {
        //$redirect = urlencode($_SERVER["HTTP_REFERER"].'#sid='.$submission->id);
    	//!!!!!!!!!!!!!evp poner $redirect  igual a la página a la que queremos que vaya después
    }

    require_login($course->id, false, $cm);

    $url =  new moodle_url('/mod/quest/viewassessmentautor.php',array('aid'=>$aid,'allowcomments'=>$allowcomments,'redirect'=>$redirect,'dir'=>$dir,'sort'=>$sort));
    $PAGE->set_url($url);

    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

	quest_check_visibility($course, $cm);

	$context = context_module::instance( $cm->id);
	$ismanager=has_capability('mod/quest:manage',$context);

    $strquests = get_string("modulenameplural", "quest");
    $strquest  = get_string("modulename", "quest");
    $strassess = get_string("viewassessmentautor", "quest");


        if(isset($_POST['newcalification'])){

         if(($ismanager)&&($assessment->state != 0)){

          if($calification_user = $DB->get_record("quest_calification_users", "userid", $submission->userid, "questid", $quest->id)){
              $calification_user->points -= $assessment->points;
              $calification_user->pointssubmission -= $assessment->points;
              $calification_user->points += $_POST['newcalification'];
              $calification_user->pointssubmission += $_POST['newcalification'];
              $DB->set_field("quest_calification_users", "points", $calification_user->points, array("id"=> $calification_user->id));
              $DB->set_field("quest_calification_users", "pointssubmission", $calification_user->pointssubmission, array("id"=> $calification_user->id));

              if($quest->allowteams){
               if($calification_team = $DB->get_record("quest_calification_teams", array("teamid"=> $calification_user->teamid, "questid"=>$quest->id))){
                  $calification_team->points -= $assessment->points;
                  $calification_team->pointssubmission -= $assessment->points;
                  $calification_team->points += $_POST['newcalification'];
                  $calification_team->pointssubmission += $_POST['newcalification'];
                  $DB->set_field("quest_calification_teams", "points", $calification_team->points, array("id"=> $calification_team->id));
                  $DB->set_field("quest_calification_teams", "pointssubmission", $calification_team->pointssubmission, array("id"=> $calification_team->id));
               }
              }
           }
           $assessment->points = $_POST['newcalification'];
           $DB->set_field("quest_assessments_autors", "points", $assessment->points, array("id"=> $assessment->id));
           $DB->set_field("quest_assessments_autors", "dateassessment", time(), array("id"=> $assessment->id));


         }

        }

     //   $OUTPUT->heading_with_help(get_string('seeassessment','quest'),"seeassessmentautor","quest");

        // if($ismanager){
         // echo "<form name=\"change\" method=\"post\" action=\"viewassessmentautor.php?frameset=top\">";
         // echo "<input type=\"hidden\" name=\"aid\" value=\"$assessment->id\">";
         // echo "<center><table cellpadding=\"5\" border=\"1\">";
         // echo "<tr valign=\"top\">\n";
         // echo "<td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
         // echo get_string('changemanualcalification','quest').'</b></center></td></tr>';

         // echo "<tr valign=\"top\">";
         // echo "<td align=\"right\"><p><b>".get_string('oldcalification','quest'). ": </b></p></td>\n";
         // echo '<td>'.number_format($assessment->points,4).'</td></tr>';

         // echo "<tr valign=\"top\">";
         // echo "<td align=\"right\"><p><b>".get_string('newcalification','quest'). ": </b></p></td>\n";
         // echo "<td><input name=\"newcalification\" type=\"text\"></td></tr>";
         // echo "<tr><td colspan=\"2\" align=\"center\" valign=\"middle\"><input type=\"submit\" value=" .get_string("changecalification","quest")."></td></tr>";
         // echo "<tr valign=\"top\">\n";
         // echo "<td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
         // echo "</tr>\n";
         // echo "</table></center>";
         // echo "</form>";
        // }

        // show assessment but don't allow changes
        quest_print_assessment_autor($quest, $assessment, false, $allowcomments);


       // echo $OUTPUT->continue_button($redirect);
        //print_footer($course);
       // exit;
    //}

    // print bottom frame with the submission

   // print_header('', '', '', '', '<base target="_parent" />');

    $submission = $DB->get_record("quest_submissions", array("id"=> $submission->id));
    $title = '"'.$submission->title.'" ';
    if (($ismanager||($submission->userid == $USER->id))) {
        $title .= get_string('by', 'quest').' '.quest_fullname($submission->userid, $course->id);
    }

    echo $OUTPUT->heading($title);


    quest_print_submission_info($quest,$submission);

    echo("<center><b><a href=\"assessments.php?cmid=$cm->id&amp;action=displaygradingform\">".
                get_string("specimenassessmentform", "quest")."</a></b></center>");

    $OUTPUT->heading(get_string('description','quest'));
    quest_print_submission($quest, $submission);

    $timenow=time();
    if(($submission->datestart < $timenow)&&($submission->dateend > $timenow)&&($submission->nanswerscorrect < $quest->nmaxanswers)){
                    $submission->phase = SUBMISSION_PHASE_ACTIVE;
    }


    echo"<br><br>";
    //quest_print_table_answers($quest,$submission,$course,$cm,$sort,$dir);



  echo $OUTPUT->continue_button($_SERVER['HTTP_REFERER'].'#sid='.$submission->id);

  echo $OUTPUT->footer();

?>
