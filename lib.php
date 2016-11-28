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
 * Library of functions and constants for module quest
 * quest constants and standard Moodle functions plus the quest functions
 * called by the standard functions
 *  see also locallib.php for other non-standard quest functions
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 *
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/calendar/lib.php');
require_once($CFG->dirroot.'/enrol/locallib.php');

function quest_add_instance($quest) {
// Given an object containing all the necessary data,
// (defined by the form in mod.html) this function
// will create a new instance and return the id number
// of the new instance.
	global $DB;

    $quest->timemodified = time();

    if($quest->initialpoints > $quest->maxcalification){
     $quest->initialpoints = $quest->maxcalification;
    }
    if(($quest->showclasifindividual == 0)&&($quest->allowteams == 0)){
     $quest->showclasifindividual = 1;
    }
    if(($quest->typegrade == QUEST_TYPE_GRADE_TEAM)&&($quest->allowteams == 0)){
     $quest->typegrade = 0;
    }

    // ...encode password if necessary.
    if (!empty($quest->password)) {
        $quest->password = md5($quest->password);
    } else {
        unset($quest->password);
    }

    if ($returnid = $DB->insert_record("quest", $quest)) {

        $event = new stdClass();
        $event->name        = get_string('datestartevent','quest', $quest->name);
        $event->description = strip_pluginfile_content($quest->intro);
        $event->courseid    = $quest->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'quest';
        $event->instance    = $returnid;
        $event->eventtype   = 'open';
        $event->timestart   = $quest->datestart;
        $event->timeduration = 0;
        calendar_event::create($event);

        $event->name        = get_string('dateendevent','quest', $quest->name);
        $event->eventtype   = 'close';
        $event->timestart   = $quest->dateend;
        calendar_event::create($event);
    }
    $ctx= context_module::instance($quest->coursemodule);
    quest_save_intro_draft_files($quest,$ctx);
    return $returnid;
}


/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 *
 * Features are explained in moodlelib.php
 */
function quest_supports($feature) {
	switch($feature) {
		case FEATURE_GROUPS:                  return false;
		case FEATURE_GROUPINGS:               return false;
		case FEATURE_GROUPMEMBERSONLY:        return false;
		case FEATURE_MOD_INTRO:               return true;
		case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
		case FEATURE_COMPLETION_HAS_RULES:    return false;
		case FEATURE_GRADE_HAS_GRADE:         return true;
		case FEATURE_GRADE_OUTCOMES:          return false;
		case FEATURE_ADVANCED_GRADING:        return false;
		case FEATURE_RATE:                    return false;
		case FEATURE_NO_VIEW_LINK:			  return false;
		case FEATURE_SHOW_DESCRIPTION:        return true;
		case FEATURE_BACKUP_MOODLE2:		  return true;
		default: return null;
	}
}

/**
 * 
 * @param type $newsubmission
 * @return type
 */
function quest_check_submission_dates($newsubmission){

 return ($newsubmission->datestart >= $newsubmission->questdatestart and $newsubmission->dateend <= $newsubmission->questdateend and $newsubmission->questdateend > $newsubmission->questdatestart);
}

/**
 * 
 * @param type $newsubmission
 * @return boolean
 */
function quest_check_submission_text($newsubmission) {

 $validate = true;

 if(empty($newsubmission->title)){
   $validate = false;
 }
 if(empty($newsubmission->description)){
   $validate = false;
 }
 return $validate;
}
/**
 * Update the configuration of the Quest
 *
 * @global stdClass $CFG
 * @global stdClass $DB
 * @param stdClass $quest
 * @return type
 */
function quest_update_instance($quest, $form) {
// Given an object containing all the necessary data,
// (defined by the form in mod_.ht_form.php) this function
// will update an existing instance with new data.
    global $CFG, $DB;
    if($quest->initialpoints > $quest->maxcalification){
     $quest->initialpoints = $quest->maxcalification;
    }
    if(($quest->showclasifindividual == 0)&&($quest->allowteams == 0)){
     $quest->showclasifindividual = 1;
    }
    if(($quest->typegrade == 1)&&($quest->allowteams == 0)){
     $quest->typegrade = 0;
    }
    // encode password if necessary
    if (!empty($quest->password)) {
        $quest->password = md5($quest->password);
    } else {
        unset($quest->password);
    }
    $quest->id = $quest->instance;  
    if ($returnid = $DB->update_record("quest", $quest)) {

        $dates = array(
            'datestart' => $quest->datestart,
            'dateend' => $quest->dateend
        );
// ...update the calendar
        foreach ($dates as $type => $date) {

            $event = $DB->get_record('event', array('modulename'=>'quest', 'instance'=> $quest->id, 'eventtype'=> $type));
            if ($event) {
                $event = calendar_event::load($event->id);
                $event_data = new stdClass();
                $event_data->name        = get_string($type.'event','quest', $quest->name);
                $event_data->description = strip_pluginfile_content($quest->intro);
                $event_data->eventtype   = $type;
                $event_data->timestart   = $date;
                $event->update($event_data);
            } else if ($date) {
                $event = new stdClass();
                $event->name        = get_string($type.'event','quest', $quest->name);
                $event->description = strip_pluginfile_content($quest->intro);
                $event->courseid    = $quest->course;
                $event->groupid     = 0;
                $event->userid      = 0;
                $event->modulename  = 'quest';
                $event->instance    = $quest->instance;
                $event->eventtype   = $type;
                $event->timestart   = $date;
                $event->timeduration = 0;
                $event->visible     = instance_is_visible('quest',$quest);
                calendar_event::create($event);
            }
        }
    $ctx= context_module::instance($quest->coursemodule);
    quest_save_intro_draft_files($quest,$ctx);
    }
 
    return $returnid;
}

function quest_delete_instance($id) {
	global $CFG, $DB;
require_once('locallib.php');
// Given an ID of an instance of this module,
// this function will permanently delete the instance
// and any data that depends on it.
    if (! $quest = $DB->get_record("quest", array("id"=> $id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('quest', $quest->id)) {
    	return false;
    }
    // delete all the associated records in the quest tables, start positive...
    $result = true;
    if (! $DB->delete_records("quest_elements", array("questid"=> $quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records("quest_elements_assessments", array("questid"=> $quest->id))) {
        $result = false;
    }

    if (! $DB->delete_records("quest_items_assesments_autor", array("questid"=> $quest->id))) {
        $result = false;
    }

    if (! $DB->delete_records("quest_elementsautor", array("questid"=>$quest->id))) {
        $result = false;
    }

    if (! $DB->delete_records("quest_assessments", array("questid"=>$quest->id))) {
        $result = false;
    }

    if (! $DB->delete_records("quest_assessments_autors", array("questid"=>$quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records("quest_submissions", array("questid"=>$quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records("quest_answers", array("questid"=> $quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records("quest_calification_users", array("questid"=>$quest->id))) {
        $result = false;
    }
    if($quest->allowteams){
     if (! $DB->delete_records("quest_teams", array("questid"=> $quest->id))) {
         $result = false;
     }
     if (! $DB->delete_records("quest_calification_teams", array("questid"=> $quest->id))) {
        $result = false;
     }
    }
    if (! $DB->delete_records("quest_rubrics", array("questid"=>$quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records("quest_rubrics_autor", array("questid"=>$quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records("quest", array("id"=> $quest->id))) {
        $result = false;
    }
    if (! $DB->delete_records('event', array('modulename'=> 'quest', 'instance'=> $quest->id))) {
        $result = false;
    }
    $context = context_module::instance($cm->id);
    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);
    return $result;
}



function quest_user_outline($course, $user, $mod, $quest) {
// Return a small object with summary information about what a
// user has done with a given particular instance of this module
// Used for user activity reports.
// $return->time = the time they did it
// $return->info = a short text description

  $result = NULL;
  if ($submissions = quest_get_user_submissions($quest, $user)) {
        $result->info = count($submissions)." ".get_string("submissions", "quest")."<br>";
        foreach ($submissions as $submission) {
            $result->time = $submission->timecreated;
            break;
            }

  }
  if($answers = quest_get_user_answer($quest,$user)){
         $result->info .= count($answers)." ".get_string("answers","quest")."<br>";
         foreach ($answers as $answer) {
            $result->time = $answer->date;
            break;
            }
  }
  if($assessments = quest_get_user_assessments($quest,$user)){
         $result->info .= count($assessments)." ".get_string("assessments","quest")."<br>";
         foreach ($assessments as $assessment) {
            $result->time = $assessment->dateassessment;
            break;
            }
  }
   return $result;


}

function quest_user_complete($course, $user, $mod, $quest) {
// Print a detailed representation of what a  user has done with
// a given particular instance of this module, for user activity reports.
global $DB;
if($submissions = $DB->get_records_select("quest_submissions","questid=? AND userid=?", array($quest->id,$user->id))){
 foreach($submissions as $submission){

        print_simple_box_start();

        echo get_string('submission','quest').': '.$submission->title.'<br />';

        quest_print_feedback($course, $submission, $user);

        print_simple_box_end();
 }

    } else {
        print_string('notsubmittedyet', 'quest');
    }

    $nanswers = 0;
    if($submissions = $DB->get_records_select("quest_submissions", "questid = ?",array($quest->id))){

     foreach($submissions as $submission){
     if($answers = $DB->get_records_select("quest_answers", "questid=? and submissionid=? and userid=?",array($quest->id,$submission->id,$user->id))){
      foreach($answers as $answer){
       $nanswers++;
       print_simple_box_start();
       echo '<table cellspacing="0" class="workshop_feedbackbox">';

        echo '<tr>';

        echo get_string('submission','quest').': '.$submission->title.'<br />'.'</td>';

        echo '</tr>';

        echo '<tr>';
        echo '<td>';
        echo get_string('answername','quest').' '.$answer->title.': </td>';
        echo '</tr>';

        echo "<tr>";
        echo '<td>'.get_string('dateanswer','quest').': ';
        echo userdate($answer->date, get_string('datestrmodel', 'quest')).'</td>';

        echo '</tr>';

        echo '</table>';
        print_simple_box_end();

     }
     }

     }
    }
    if($nanswers == 0){
     echo ' ';
     print_string('notsubmittedanswers', 'quest');
    }



}

//////////////////////////////////////////////////////////
function quest_print_feedback($course, $submission, $user) {
    global $CFG, $RATING,$DB;

    $strgrade = get_string('grade','quest');
    $strnograde = get_string('nograde','quest');
    $strnoanswers = get_string('noanswers','quest');
    $strnoassessments = get_string('noassessments','quest');


    if(! $answers = $DB->get_records('quest_answers','submissionid', $submission->id)){

        echo '<table cellspacing="0" class="workshop_feedbackbox">';
        echo '<tr>';
        echo '<td>';
        print_user_picture($user->id, $course->id, $user->picture);
        echo '</td>';
        echo '<td>'.fullname($user).'</td>';

        echo '</tr>';
        echo '<tr>';
        echo '<td></td><td>';
        echo  $strnoanswers.'</td>';
        echo '</tr>';
        echo '</table>';

      return;
    }
    foreach($answers as $answer){


    if (! $feedbacks = $DB->get_records('quest_assessments', array('answerid'=> $answer->id))) {
     echo '<table cellspacing="0" class="workshop_feedbackbox">';
        echo '<tr>';
        echo '<td>';
        print_user_picture($user->id, $course->id, $user->picture);
        echo '</td>';
        echo '<td>'.fullname($user).'</td>';

        echo '</tr>';
        echo '<tr>';
        echo '<td></td><td>';
        echo  $strnoassessments.'</td>';
        echo '</tr>';
        echo '</table>';
        return;
    }


    foreach ($feedbacks as $feedback) {


        echo '<table cellspacing="0" class="workshop_feedbackbox">';

        echo '<tr>';
        echo '<td>';
        print_user_picture($user->id, $course->id, $user->picture);
        echo '</td>';
        echo '<td align="left">'.fullname($user).'</td>';

        echo '</tr>';

        echo '<tr>';
        echo '<td></td><td>';
        echo get_string('answername','quest').' '.$answer->title.': </td>';
        echo '</tr>';

        echo "<tr><td></td>";

        echo '<td>'.get_string('timeassessment','quest').': ';
        echo format_time($feedback->dateassessment - $answer->date).'</td>';

        echo '</tr><tr>';

        echo '<td></td><td>';

        $context = context_course::instance( $course->id);
        $ismanager=has_capability('mod/quest:manage',$context);
        $canpreview = has_capability('mod/quest:preview', $context);

        if($ismanager){
         if($feedback->teacherid == $user->id){
          if ($feedback->pointsteacher) {
              echo $strgrade.': '.$feedback->pointsteacher;
          } else {
              echo $strnograde;
          }
         }
         else{
          echo $strnograde;
         }
        }
        else{
         if ($feedback->pointsautor) {
             echo $strgrade.': '.$feedback->pointsautor;
         } else {
             echo $strnograde;
         }
        }

        echo '</td></tr>';

        echo '</table>';

    }
    }
}

//////////////////////////////////////////////////////////
function quest_is_recent_activity($course, $isteacher, $timestart) {
// Given a course and a time, this module should find recent activity
// that has occurred in QUEST activities and print it out.
// Return true if there was output, or false is there was none.

    global $CFG;

    $assessmentcontent = false;
    if (!$isteacher) { // teachers only need to see submissions
        if ($logs = quest_get_assessments_logs($course, $timestart)) {
            // got some, see if any belong to a visible module
            foreach ($logs as $log) {
                // Create a temp valid module structure (only need courseid, moduleid)
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                //Obtain the visible property from the instance
                if (instance_is_visible("quest",$tempmod)) {
                    $assessmentcontent = true;
                    break;
                }
            }
        }
    }
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Get one user that act as a teacher
 * @param unknown $courseid
 */
function quest_get_teacher($courseid)
{
    $context = context_course::instance($courseid);
    $members=get_users_by_capability($context, 'mod/quest:manage');
    return reset($members);
}
function quest_cron () {
// Function to be run periodically according to the moodle cron
// This function searches for things that need to be done, such
// as sending out mail, toggling flags etc ...

    global $CFG, $USER, $SITE, $DB;

	mtrace("\n===============================");
	mtrace(" Starting CRON for module QUEST");
	$timestart=time();
    $timeref = time() - 24*3600;
    $userfrom = null;

    if($quests = $DB->get_records("quest"))
    {


     $urlinfo = parse_url($CFG->wwwroot);
     $hostname = $urlinfo['host'];
       /*
        * daily actions :
        *
        * - Day brief of QUESTs activities
        */
    if (!isset($CFG->digestmailtimelast))
    {    // To catch the first time
        set_config('questdigestmailtimelast', 0,'quest');
    }
	$timenow = time();
	$sitetimezone = $CFG->timezone;
    $digesttime = usergetmidnight($timenow, $sitetimezone);// + ($CFG->questdigestmailtime * 3600);
	$questdigestmailtimelast=get_config('quest','questdigestmailtimelast');
    if ($questdigestmailtimelast < $digesttime and $timenow > $digesttime)
    {
    set_config('questdigestmailtimelast', $timenow,'quest');
    mtrace('Sending QUEST digests: '.userdate($timenow, '', $sitetimezone));

     foreach($quests as $quest)
     {

      if (!$course = $DB->get_record("course", array("id"=> $quest->course))) {
            mtrace("Course is misconfigured");
            continue;
      }
      if (! $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
           mtrace("Coursemodule is misconfigured");
           continue;
      }

     if ($cm->visible==0 )
		{
			 mtrace("Coursemodule is disabled");
			continue;
		}
	  $context= context_course::instance( $course->id);
	  $userfrom = quest_get_teacher($course->id);
      $mailcount = 0;
	  mtrace("DAILY TASKs for quest no: $quest->id");
      mtrace(get_string('processingquest', 'quest', $quest->id));

      if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")){
            mtrace("ERROR!: There is no users");
            continue;
      }

      foreach($users as $userto)
      {


       if(has_capability('mod/quest:manage', $context,$userto->id))
       {

         $indice = 0;

         $postsubject = get_string('resumequest','quest',$quest);

         $posttext = get_string('resume24hours','quest',$quest);
         $posttext .= "\n\r----------------------------------------------------------------------------------------------------------------------\n\r";

         $posthtml = '<head>';
// JPC: Moodle 2.x does not include stylesheet list in $CFG
//          foreach ($CFG->stylesheets as $stylesheet) {
//               $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
//          }
         $posthtml .= '</head>';
         $posthtml .= "\n<body id=\"email\">\n\n";
         $posthtml .= get_string('resume24hours','quest',$quest);
         $posthtml .= "<br>----------------------------------------------------------------------------------------------------------------------<br>";

        if($submissions = $DB->get_records("quest_submissions", array("questid"=>$quest->id)))
        {

         //Imprimir cabecera del m�dulo QUEST en mensaje
         foreach($submissions as $submission)
         {
         // Challenge unnotified and recently created
          if(($submission->timecreated > $timeref)&&($submission->mailed == 0))
          {

           $indice++;
           $user = get_complete_user_data('id', $submission->userid);

           $cleanquestname = str_replace('"', "'", strip_tags($quest->name));
           $userfrom->customheaders = array (  // Headers to make emails easier to track
                         'Precedence: Bulk',
                         'List-Id: "'.$cleanquestname.'" <moodlequest'.$quest->id.'@'.$hostname.'>',
                         'List-Help: '.$CFG->wwwroot.'/mod/quest/view.php?f='.$quest->id,
                         'X-Course-Id: '.$course->id,
                         'X-Course-Name: '.strip_tags($course->fullname)
           );
           if (!empty($course->lang)) {
                  $CFG->courselang = $course->lang;
              } else {
                  unset($CFG->courselang);
           }
           $USER->lang = $userto->lang;
           $USER->timezone = $userto->timezone;

           $posttext .= quest_make_mail_text($course, $quest, $submission, $userfrom, $userto, $user,$cm);

           $posthtml .= quest_make_mail_html($course, $quest, $submission, $userfrom, $userto, $user,$cm);

           } // if teacher
          }// for submissions
         }

		// count of messages to send
         if($indice >0)
         {

          $posthtml .= "</body>";

          $posttext = format_text($posttext,1);

          if (!$mailresult = email_to_user($userto, $userfrom, $postsubject, $posttext, $posthtml)) {
            mtrace("Error: mod/quest/cron.php: Could not send out mail to user $userto->id".
                    " ($userto->email) .. not trying again.");
            add_to_log($course->id, 'quest', 'mail error', "view.php?id=$cm->id",
                    substr(format_string($postsubject,true),0,30), $cm->id, $userto->id);

          } else if ($mailresult === 'emailstop') {
            add_to_log($course->id, 'quest', 'mail blocked', "view.php?id=$cm->id",
                    substr(format_string($postsubject,true),0,30), $cm->id, $userto->id);
          } else {
            $mailcount++;

          }
         }

       } // if teacher
      }//foreach user

	/**
	 * Mark submissions as mailed
	 */
      if($submissions = $DB->get_records("quest_submissions", array("questid"=> $quest->id)))
      {

         //Imprimir cabecera del m�dulo QUEST en mensaje
         foreach($submissions as $submission){
          if(($submission->timecreated > $timeref)&&($submission->mailed == 0)){

           $submission->mailed = 1;
           $DB->set_field("quest_submissions", "mailed", $submission->mailed,array("id"=>$submission->id));
          }
         }
      }
      mtrace(".... mailed to $mailcount users.");
     } // foreach quests

    }// midnight checks
  // END DAILY MAILS
  	else
  	mtrace("Posponing Daily tasks.");

/*
 * Process all quests
 * Notify challenges recently started and unmailed
 * to studens
 * submissions already notified are marked with maileduser=1
 * maileduser marks the instant notification (actually cron tick time)
 * mailed marks the submissions mailed in a daily digest
*/
mtrace("Searching events to notify to all users...");
     foreach($quests as $quest)
     {

      if (!$course = $DB->get_record("course", array("id"=> $quest->course))) {
            mtrace("Course is misconfigured");
            return false;
      }
      if (! $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
           mtrace("Coursemodule is misconfigured");
           return false;
      }

	if ($cm->visible==0 )
	{
		 mtrace("Coursemodule is disabled");
		continue;
	}
	$context= context_course::instance( $course->id);
      $submissionscount = 0;
      $userscount = 0;
      $userfrom = class_exists('core_user')?core_user::NOREPLY_USER:quest_get_teacher($course->id);

      if($submissions = $DB->get_records("quest_submissions", array("questid"=> $quest->id)))
      {
		mtrace("Processing ".count($submissions )."challenges for quest: $quest->id.");

         //Imprimir cabecera del m�dulo QUEST en mensaje
         foreach($submissions as $submission)
         {
		//mtrace("Submission $submission->id starts $submission->datestart. Anterior:".($submission->datestart < time())." mailed: $submission->maileduser");
          if(($submission->datestart < time())&&($submission->maileduser == 0) && ($submission->state!=1) )
          {

           $submissionscount++;
            if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")){
                  mtrace("There is not users");
                  continue;
            }
            $userscount = 0;
            mtrace("Challenge $submission->id has started. Mailing advice" .
            		" to ".count($users)." users.");
            foreach($users as $user){

             if(!has_capability('mod/quest:manage',$context,$user)) // JPC: I prefer to get advices as teacher
             {
              $userscount++;
	print("Sending message to user $user->username in name of $userfrom->username\n");
              quest_send_message($user, "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission", 'addsubmission', $quest, $submission,'',$userfrom);
             }
            }
            $DB->set_field("quest_submissions","maileduser",1,array('id'=>$submission->id));

             $dates = array(
              'datestartsubmission' => $submission->datestart,
              'dateendsubmission' => $submission->dateend
             );

             $moduleid = $DB->get_field('modules', 'id', array('name'=> 'quest'));//evp creo que hay una funci�n de moodle para esto

             if (!has_capability('mod/quest:manage', $context, $submission->userid)
                     && ($group_member = $DB->get_record("groups_members", array("userid" => $submission->userid)))){
                    $idgroup = $group_member->groupid;
                } else {
                    $idgroup = 0;
                }

            foreach ($dates as $type => $date) {
              if($submission->datestart <= time() ){
                 if ($event = $DB->get_record('event', array('modulename'=> 'quest', 'instance'=> $quest->id, 'eventtype'=> $type))) {
                     if($type == 'datestartsubmission'){
                      $stringevent = 'datestartsubmissionevent';
                     }
                     else if($type == 'dateendsubmission'){
                      $stringevent = 'dateendsubmissionevent';
                     }
                     $event->name        = get_string($stringevent,'quest', $submission->title);
                     $event->description = "<a href=\"{$CFG->wwwroot}/mod/quest/submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission\">".$submission->title."</a>";
                     $event->eventtype   = $type;
                     $event->timestart   = $date;
                     update_event($event);
                 } else if ($date) {
                     if($type == 'datestartsubmission'){
                      $stringevent = 'datestartsubmissionevent';
                     }
                     else if($type == 'dateendsubmission'){
                      $stringevent = 'dateendsubmissionevent';
                     }
                     $event = new stdClass();
                     $event->name        = get_string($stringevent,'quest', $submission->title);
                     $event->description = "<a href=\"{$CFG->wwwroot}/mod/quest/submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission\">".$submission->title."</a>";
                     $event->courseid    = $quest->course;
                     $event->groupid     = $idgroup;
                     $event->userid      = 0;
                     $event->modulename  = 'quest';
                     $event->instance    = $quest->id;
                     $event->eventtype   = $type;
                     $event->timestart   = $date;
                     $event->timeduration = 0;
                     $event->visible     = $DB->get_field('course_modules', 'visible', array('module'=>$moduleid, 'instance'=> $quest->id));
                     add_event($event);
                 }
              }
             }
          }  // if submission started an unmailed to students
         } // for submissions
      }
      mtrace("$submissionscount submissions mailed to $userscount users.\n");
     }


    }

	mtrace("QUESTOURnament processed (".(time()-$timestart)." ms)");
    return true;

}

function quest_make_mail_text($course, $quest, $submission, $userfrom, $userto, $user, $cm) {
    global $CFG;

    $userto = get_complete_user_data('id', $userto->id);
    $by = New stdClass;
    $by->name = fullname($user);
    $by->date = userdate($submission->timecreated, "", $userto->timezone);

    $strbynameondate = get_string('bynameondate', 'quest', $by);

    $strquests = get_string('quests', 'quest');

    $posttext = '';

    $posttext  = "$course->shortname -> $strquests -> ".format_string($quest->name,true);

    $subject = get_string('emailaddsubmissionsubject', 'quest');

    $posttext  .= " -> ".format_string($submission->title,true);

    $posttext .= "\n\r---------------------------------------------------------------------\n\r";
    $posttext .= format_string($subject,true);

    $posttext .= "\n\r".$strbynameondate."\n\r";
    $posttext .= "\n\r---------------------------------------------------------------------\n\r";
    $site = get_site();
    $data = new stdClass();
    $data->firstname = fullname($userto);
    $data->sitename = $site->fullname;
    $data->admin = $CFG->supportname .' ('. $CFG->supportemail .')';
    $data->title = $submission->title;
    $data->name = $quest->name;
    $data->link = $CFG->wwwroot ."/mod/quest/submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission";
    $message = get_string('emailaddsubmission', 'quest', $data);

    $posttext .= format_text_email($message,1);
    $posttext .= "\n\r";


    return $posttext;
}

function quest_make_mail_html($course, $quest, $submission, $userfrom, $userto, $user, $cm) {
    global $CFG;

    $userto = get_complete_user_data('id', $userto->id);

    if ($userto->mailformat != 1) {  // Needs to be HTML
        return '';
    }

    $strquests = get_string('quests', 'quest');

    $posthtml = '<div class="navbar">'.
    '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quest/index.php?id='.$course->id.'">'.$strquests.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quest/view.php?id='.$cm->id.'">'.format_string($quest->name,true).'</a>';

    $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/quest/submissions.php?id='.$cm->id.
                '&amp;action=showsubmission&amp;id='.$submission->id.'">'.
                     format_string($submission->title,true).'</a></div>';

    $posthtml .= quest_make_mail_post($quest, $userfrom, $userto, $course, $user, $submission, $cm);

    return $posthtml;
}
function quest_make_mail_post($quest, $userfrom, $userto, $course, $user, $submission, $cm) {
// Given the data about a posting, builds up the HTML to display it and
// returns the HTML in a string.  This is designed for sending via HTML email.

    global $CFG,$OUTPUT;

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT->user_picture($user,array('popup'=>false));
    $output .= '</td>';

    $output .= '<td class="topic starter">';

    $subject = get_string('emailaddsubmissionsubject', 'quest');

    $output .= '<div class="subject">'.$subject.'</div>';

    $context = context_module::instance( $cm->id);
    $ismanagerto=has_capability('mod/quest:manage',$context,$userto->id);

    $fullname = fullname($user, $ismanagerto);
    $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id.'">'.$fullname.'</a>';
    $by->date = userdate($submission->timecreated, '', $user->timezone);
    $output .= '<div class="author">'.get_string('bynameondate', 'forum', $by).'</div>';

    $output .= '</td></tr>';

    $output .= '<tr><td class="left side"> </td><td class="content">';


    $site = get_site();


    $data->admin = $CFG->supportname .' ('. $CFG->supportemail .')';
    $data->firstname = fullname($userto);
    $data->sitename = $site->fullname;
    $data->title = $submission->title;
    $data->name = $quest->name;
    $data->link = $CFG->wwwroot ."/mod/quest/submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission".'&amp;p='. $userto->secret .'&amp;s='. $userto->username;
    $message = get_string('emailaddsubmission', 'quest', $data);

    $messagehtml = text_to_html($message, false, false, true);

    $output .= $messagehtml;

    $output .= '</td></tr></table>'."\n\n";


    return $output;
}

//////////////////////7
// Grading
//////////////////////
/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function quest_grading_areas_list() {
    return array('individual'=>get_string('individualcalification', 'quest'),
    			'team'=>get_string('pointsteam', 'quest'));
}

/**
 * Create grade item for given quest.
 *
 * @param stdClass $assign record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function quest_grade_item_update($quest, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($quest->courseid)) {
        $quest->courseid = $quest->course;
    }

    $params = array('itemname'=>$quest->name, 'idnumber'=>$quest->id);

    // questournament grades as a % of the maxscore in the ranking table
    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax']  = floatval(100); // Grade is always normalized to other users maxcalification
    $params['grademin']  = 0;


    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

setlocale(LC_NUMERIC,'C'); // JPC Moodle aplies  numeric locale to casts of strings cheating mysql
//echo "locale=". setlocale(LC_NUMERIC,0);
//print_object($grades);die;
   return grade_update('mod/quest',
                        $quest->courseid,
                        'mod',
                        'quest',
                        $quest->id,
                        0,
                        $grades,
                        $params);
}
/**
 * Return grade for given user or all users.
 *
 * @param stdClass $assign record of assign with an additional cmidnumber
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function quest_get_user_grades($quest, $userid=0)
{
    global $CFG,$DB;
    require_once($CFG->dirroot . '/mod/quest/locallib.php');
	if ($quest = $DB->get_record("quest", array("id"=> $quest->id),'*',MUST_EXIST))
	{
		$course = get_course($quest->course);
		$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id,null,null,MUST_EXIST);
		$groupmode = groups_get_activity_group($cm);
	 	$maxpoints=-1;
		$maxpointsgroup=null;
// 	 if ($quest->gradingstrategy)
// 	 {
	 	// select users queried
	 	if ($userid!=0)
	 	{
	 		$students=array($userid=>get_complete_user_data('id', $userid));
	 	}
	 	else
	 	{
	 		$students = get_course_students($quest->course);
	 	}
	 	if ($students)
	 	{
	 		$return=array();

	 			$maxpoints=-1; // uncalculated start value
	 			$maxpointsgroup= array(); // group points cache
	 			$textinfo="";
	 			foreach ($students as $student)
	 			{

	 		/////////////////////
	 		// Get maximum scores
	 		/////////////////////
	 				if($groupmode != false)
	 				{
	 					if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id)))
		 					{
		 						// cache maxpoints for this group
		 						if ($maxpointsgroup[$group_member->groupid])
		 						{
		 							$maxpoints=$maxpointsgroup[$group_member->groupid];
		 						}
		 						else
		 						{
		 							if($quest->typegrade == QUEST_TYPE_GRADE_INDIVIDUAL) //Grading by individuals
		 							{
		 								$maxpoints = quest_get_maxpoints_group($group_member->groupid,$quest);
		 							}
		 							else if($quest->typegrade == QUEST_TYPE_GRADE_TEAM) //Grading by teams
		 							{
		 								$maxpoints = quest_get_maxpoints_group_teams($group_member->groupid,$quest);
		 							}

		 							$maxpointsgroup[$group_member->groupid]=$maxpoints;
		 						}
		 					}

	 				} //no se usan grupos
	 				else if ($maxpoints==-1) //avoid to query more than once
	 				{
	 					if($quest->typegrade == QUEST_TYPE_GRADE_INDIVIDUAL) //Grading by individuals
	 					{
	 						$maxpoints = quest_get_maxpoints($quest);
	 					}
	 					else if($quest->typegrade == QUEST_TYPE_GRADE_TEAM) //Grading by teams
	 					{
	 						$maxpoints = quest_get_maxpoints_teams($quest);
	 					}

	 				}
			//////////////////
			//	Calculate proportionally
			//////////////////
	 				if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$quest->id,"userid"=>$student->id)))
		    		{
		    		$points=0;
		    		if($quest->typegrade == QUEST_TYPE_GRADE_INDIVIDUAL) //Grading by individuals
		    			{
		    			$points = $calification_student->points;
		    			}
					$textinfo = number_format($calification_student->points,1)." points";

		    		if($quest->allowteams) // add team score
		    		{
		    			if($calification_team = $DB->get_record("quest_calification_teams",array("questid"=> $quest->id, "teamid"=> $calification_student->teamid)))
		    			{
		    				$points += $calification_team->points*$quest->teamporcent/100;
		    				$textinfo.="+ $quest->teamporcent% of ".number_format($calification_team->points,1)." team points";
		    			}
		    		}
		    		$textinfo = number_format($points,1)." points = ". $textinfo;
		    		$textinfo.="/Max. ".number_format($maxpoints,1);
		    		$rawgrade=$maxpoints==0?0:$points/$maxpoints*$quest->maxcalification;
		    		$textinfo.=" (".number_format($points,1)."/".number_format($maxpoints,1).") (".number_format($rawgrade,1)."% of $quest->maxcalification)";
		     	//Grade API needs userid, rawgrade, feedback, feedbackformat, usermodified, dategraded, datesubmitted
		     		$grade=new stdClass();
		     		$grade->userid = $student->id;
		     		$grade->maxgrade = "100";
//				$grade->rawgrade = number_format($rawgrade,4,'.','');//
				$grade->rawgrade = floatval($rawgrade); //TODO: check bug with decimal point in moodle 2.5??
		     		$grade->feedback=$textinfo;
		     		$grade->feedbackformat=FORMAT_PLAIN;
		     		$return[$student->id] = $grade;
		    		}// student has calification
	 			} //foreach student in list
	 	}// there are students
        else // No students.
        {
           $return=false;
        }
	}
	else
	{
		$return =false;
	}
	return $return;
}
/**
 * Update activity grades.
 *
 * @param stdClass $quest database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used
 */
function quest_update_grades($quest, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
//print("<!-- <p>Updating grades... for user $userid</p>-->");

$grades = quest_get_user_grades($quest, $userid);

   if ($grades) {
        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        quest_grade_item_update($quest, $grades);

    } else {
        quest_grade_item_update($quest);
    }
}
/**
 * @deprecated
 * @param int $questid
 */
function create_user_grade($questid)
{
if ($quest = $DB->get_record("quest", array("id"=> $questid))) {
		$course = get_course($quest->course);
		$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id);
		$groupmode = groups_get_activity_group($cm);
	 $maxpoints=-1;
	 $maxpointsgroup=null;
	 $maxpointsgroupteams=null;
	 if ($quest->gradingstrategy)
	 {
	 	if ($userid==0)
	 	{
	 		$students=array($userid=>get_complete_user_data('id', $userid));
	 	}
	 	else
	 	{
	 		$students = get_course_students($quest->course);
	 	}
	 	if ($students)
	 	{
	 		if($quest->typegrade == 0) //Grading by individuals
	 		{
	 			foreach ($students as $student)
	 			{

	 				if($groupmode !=0)
	 				{
	 					if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id)))
	 					{
	 						// cache maxpoints for this group
	 						if ($maxpointsgroup[$group_member->groupid])
	 						{
	 							//print("<li>QUEST group cached: $maxpoints</li>");
	 							$maxpoints=$maxpointsgroup[$group_member->groupid];
	 						}
	 						else
	 						{
	 							//print("<li>QUEST group cached: $maxpoints</li>");
	 							$maxpoints = quest_get_maxpoints_group($group_member->groupid,$quest);
	 							$maxpointsgroup[$group_member->groupid]=$maxpoints;
	 						}
	 					}
	 				} //no se usan grupos
	 				else if ($maxpoints==-1) //avoid to query more than once
	 				{

	 					// print("<li>QUEST maxpoints cached: $maxpoints</li>");
	 					$maxpoints = quest_get_maxpoints($quest);
	 				}

	 				if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$questid,"userid"=>$student->id)))
		    {
		    	$points = $calification_student->points;

		    	if($quest->allowteams)
		    	{
		    		if($calification_team = $DB->get_record("quest_calification_teams",array("questid"=> $questid, "teamid"=> $calification_student->teamid))){
		    			$points += $calification_team->points*$quest->teamporcent/100;
		    		}
		    	}

		    	if($maxpoints > 0)
		     {
		     	$return->grades[$student->id] = $quest->maxcalification*$points/$maxpoints;
		     }
		     else
		     {
		     	$return->grades[$student->id] = 0;
		     }
		    }
	 			} //foreach
	 			print_object($return);
	 		}
	 		else if($quest->typegrade == 1) //Grading by teams
	 		{

	 			foreach ($students as $student) {

	 				if($groupmode !=0){
	 					if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id)))
	 					{
	 						// cache maxpoints for this group
	 						if ($maxpointsgroupteams[$group_member->groupid])
	 						$maxpoints=$maxpointsgroupteams[$group_member->groupid];
	 						else
	 						{
	 							$maxpoints = quest_get_maxpoints_group_teams($group_member->groupid,$quest);
	 							$maxpointsgroupteams[$group_member->groupid]=$maxpoints;
	 						}

	 					}
	 				}
	 				else if ($maxpoints==-1)
	 				{
	 					$maxpoints = quest_get_maxpoints_teams($quest);
	 				}

	 				if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$questid,"userid"=>$student->id))){

	 					if($quest->allowteams){
	 						if($calification_team = $DB->get_record("quest_calification_teams", array("questid"=> $questid, "teamid"=> $calification_student->teamid))){

	 							$points = $calification_team->points;

	 						}
	 					}
	 					if($maxpoints > 0){
	 						$return->grades[$student->id] = $quest->maxcalification*$points/$maxpoints;
	 					}
	 					else{
	 						$return->grades[$student->id] = 0;
	 					}
	 				}

	 			}
	 		}


	 	}


	 	// set maximum grade if graded
	 	$return->maxgrade = $quest->maxcalification;
	 }
	}

}
/**
 *
 * Enter description here ...
 * @param int $questid
 * @param int $userid
 * @deprecated
 */
function quest_grades($questid, $userid=0) {
	global $DB;

	// Must return an array of grades for a given instance of this module,
	// indexed by user.  It also returns a maximum allowed grade.
	//
	//    $return->grades = array of grades;
	//    $return->maxgrade = maximum allowed grade;
	//
	//    return $return;
	//    $return = null;
//	print("<p>Getting QUESTOURnament grades for questid:$questid</p>");
	if ($quest = $DB->get_record("quest", array("id"=> $questid))) {
		$course = $DB->get_record("course", array("id"=> $quest->course));
		$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id);
		$groupmode = groups_get_activity_group($cm);
	 $maxpoints=-1;
	 $maxpointsgroup=null;
	 $maxpointsgroupteams=null;
	 if ($quest->gradingstrategy)
	 {
	 	if ($userid==0)
	 	{
	 		$students=array($userid=>get_complete_user_data('id', $userid));
	 	}
	 	else
	 	{
	 		$students = get_course_students($quest->course);
	 	}
	 	if ($students)
	 	{
	 		if($quest->typegrade == 0) //Grading by individuals
	 		{
	 			foreach ($students as $student)
	 			{

	 				if($groupmode !=0)
	 				{
	 					if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id)))
	 					{
	 						// cache maxpoints for this group
	 						if ($maxpointsgroup[$group_member->groupid])
	 						{
	 							//print("<li>QUEST group cached: $maxpoints</li>");
	 							$maxpoints=$maxpointsgroup[$group_member->groupid];
	 						}
	 						else
	 						{
	 							//print("<li>QUEST group cached: $maxpoints</li>");
	 							$maxpoints = quest_get_maxpoints_group($group_member->groupid,$quest);
	 							$maxpointsgroup[$group_member->groupid]=$maxpoints;
	 						}
	 					}
	 				} //no se usan grupos
	 				else if ($maxpoints==-1) //avoid to query more than once
	 				{

	 					// print("<li>QUEST maxpoints cached: $maxpoints</li>");
	 					$maxpoints = quest_get_maxpoints($quest);
	 				}

	 				if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$questid,"userid"=>$student->id)))
		    {
		    	$points = $calification_student->points;

		    	if($quest->allowteams)
		    	{
		    		if($calification_team = $DB->get_record("quest_calification_teams",array("questid"=> $questid, "teamid"=> $calification_student->teamid))){
		    			$points += $calification_team->points*$quest->teamporcent/100;
		    		}
		    	}

		    	if($maxpoints > 0)
		     {
		     	$return->grades[$student->id] = $quest->maxcalification*$points/$maxpoints;
		     }
		     else
		     {
		     	$return->grades[$student->id] = 0;
		     }
		    }
	 			} //foreach
	 			print_object($return);
	 		}
	 		else if($quest->typegrade == 1) //Grading by teams
	 		{

	 			foreach ($students as $student) {

	 				if($groupmode !=0){
	 					if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id)))
	 					{
	 						// cache maxpoints for this group
	 						if ($maxpointsgroupteams[$group_member->groupid])
	 						$maxpoints=$maxpointsgroupteams[$group_member->groupid];
	 						else
	 						{
	 							$maxpoints = quest_get_maxpoints_group_teams($group_member->groupid,$quest);
	 							$maxpointsgroupteams[$group_member->groupid]=$maxpoints;
	 						}

	 					}
	 				}
	 				else if ($maxpoints==-1)
	 				{
	 					$maxpoints = quest_get_maxpoints_teams($quest);
	 				}

	 				if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$questid,"userid"=>$student->id))){

	 					if($quest->allowteams){
	 						if($calification_team = $DB->get_record("quest_calification_teams", array("questid"=> $questid, "teamid"=> $calification_student->teamid))){

	 							$points = $calification_team->points;

	 						}
	 					}
	 					if($maxpoints > 0){
	 						$return->grades[$student->id] = $quest->maxcalification*$points/$maxpoints;
	 					}
	 					else{
	 						$return->grades[$student->id] = 0;
	 					}
	 				}

	 			}
	 		}


	 	}


	 	// set maximum grade if graded
	 	$return->maxgrade = $quest->maxcalification;
	 }
	}
	print("RETURN:");
	print_object($return);
	//exit();
	return $return;
}
/**
 * TODO reuse grade calculation with quest_get_maxpoints
 * @param unknown $groupid
 * @param unknown $quest
 * @return number
 */
function quest_get_maxpoints_group($groupid,$quest)
{
 global $DB;
 $maxpoints = 0;
 if ($students = get_course_students($quest->course)) {
        foreach ($students as $student) {
                if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id))){
                  if($groupid == $group_member->groupid){
                    if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$quest->id,"userid"=>$student->id))){

                     $grade = $calification_student->points;

                     if($quest->allowteams){
                      if($calification_team = $DB->get_record("quest_calification_teams", array("questid"=> $quest->id, "teamid"=> $calification_student->teamid))){

                       $grade += $calification_team->points*$quest->teamporcent/100;

                      }
                     }

                      if($grade > $maxpoints){
                       $maxpoints = $grade;
                      }
                    }
                  }
                }



        }
 }

 return $maxpoints;

}
/**
 * get max score achieved by participants
 * @param quest record $quest
 * @return number
 */
function quest_get_maxpoints($quest){
 global $DB;
 $maxpoints = 0;
 if ($students = get_course_students($quest->course)) {
        foreach ($students as $student) {

                    if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$quest->id,"userid"=>$student->id))){

                     $grade = $calification_student->points;

                     if($quest->allowteams){
                      if($calification_team = $DB->get_record("quest_calification_teams", array("questid"=> $quest->id, "teamid"=> $calification_student->teamid))){

                       $grade += $calification_team->points*$quest->teamporcent/100;

                      }
                     }

                      if($grade > $maxpoints){
                       $maxpoints = $grade;
                      }
                    }

        }
 }

 return $maxpoints;

}

function quest_get_maxpoints_group_teams($groupid,$quest){
global $DB;
 $maxpoints = 0;
 if ($students = get_course_students($quest->course)) {
        foreach ($students as $student) {
                if($group_member = $DB->get_record("groups_members", array("userid"=> $student->id))){
                  if($groupid == $group_member->groupid){
                    if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$quest->id,"userid"=>$student->id))){

                     if($quest->allowteams){
                      if($calification_team = $DB->get_record("quest_calification_teams", array("questid"=> $quest->id, "teamid"=> $calification_student->teamid))){

                       $grade = $calification_team->points;

                      }
                     }

                      if($grade > $maxpoints){
                       $maxpoints = $grade;
                      }
                    }
                  }
                }

        }
 }

 return $maxpoints;

}


function quest_get_participants($questid) {
//Must return an array of user records (all data) who are participants
//for a given instance of QUEST. Must include every user involved
//in the instance, independient of his role (student, teacher, admin...)
//See other modules as example.

    global $CFG,$DB;

    //Get students from quest_submissions
    $st_submissions = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                       FROM {user} u,
                                            {quest_submissions} s
                                       WHERE s.questid = ? and
                                             u.id = s.userid",array($questid));
    //Get students from quest_assessments
    $st_assessments = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                 FROM {user} u,
                                      {quest_assessments} a
                                 WHERE a.questid = ? and ( u.id = a.userid or u.id = a.teacherid )",array($questid));


    //Get students from quest_comments
    $st_answers = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                   FROM {user} u,
                                        {quest_answers} c
                                   WHERE c.questid = ? and
                                         u.id = c.userid",array($questid));

   //Add st_answers to st_submissions
    if ($st_answers) {
        foreach ($st_answers as $st_answer) {
            $st_submissions[$st_answer->id] = $st_answer;
        }
    }
    //Add st_assessments to st_submissions
    if ($st_assessments) {
        foreach ($st_assessments as $st_assessment) {
            $st_submissions[$st_assessment->id] = $st_assessment;
        }
    }

    //Return st_submissions array (it contains an array of unique users)
    return ($st_submissions);
}
/**
 * This function returns if a scale is being used by one QUEST
 * it it has support for grading and scales.
 */
function quest_scale_used ($questid,$scaleid) {
    $return = false;
    return $return;
}/**
 * This function returns if a scale is being used by any QUEST instance
 * it it has support for grading and scales.
 */
function quest_scale_used_anywhere ($questid,$scaleid) {
    $return = false;
    return $return;
}

/////////////////////////////////////////////////////
function quest_refresh_events($courseid = 0) {
// This standard function will check all instances of this module
// and make sure there are up-to-date events created for each of them.
// If courseid = 0, then every quest event in the site is checked, else
// only quest events belonging to the course specified are checked.
// This function is used, in its new format, by restore_refresh_events()
	global $DB;
    if ($courseid == 0) {
        if (! $quests = $DB->get_records("quest")) {
            return true;
        }
    } else {
        if (! $quests = $DB->get_records("quest", array("course"=> $courseid))) {
            return true;
        }
    }
    $moduleid = $DB->get_field('modules', 'id', array('name'=> 'quest')); //EVp creo que hay funci�n de moodle para esto

    foreach ($quests as $quest) {

        $dates = array(
            'datestart' => $quest->datestart,
            'dateend' => $quest->dateend

        );

        foreach ($dates as $type => $date) {

            if ($date) {
                if ($event = $DB->get_record('event', array('modulename'=> 'quest', 'instance'=> $quest->id, 'eventtype'=> $type))) {
                    $event->name        = get_string($type.'event','quest', $quest->name);
                    $event->description = strip_pluginfile_content($quest->intro);
                    $event->eventtype   = $type;
                    $event->timestart   = $date;
                    update_event($event);
                } else {
                    $event = new stdClass();
                    $event->courseid    = $quest->course;
                    $event->modulename  = 'quest';
                    $event->instance    = $quest->id;
                    $event->name        = get_string($type.'event','quest', $quest->name);
                    $event->description = strip_pluginfile_content($quest->intro);
                    $event->eventtype   = $type;
                    $event->timestart   = $date;
                    $event->timeduration = 0;
                    $event->visible     = $DB->get_field('course_modules', 'visible', array('module'=> $moduleid, 'instance'=> $quest->id));
                    add_event($event);
                }
            }
        }
    }
    return true;
}
/**
 *
 * @param unknown $activities
 * @param unknown $index
 * @param unknown $sincetime
 * @param unknown $courseid
 * @param string $questcmid cmid del modulo quest
 * @param string $user
 * @param string $groupid
 */
 function quest_get_recent_mod_activity(&$activities, &$index, $sincetime, $courseid,
                                           $questcmid="0", $user="", $groupid="") {
    // Returns all quest posts since a given time.  If quest is specified then
    // this restricts the results

    global $CFG,$USER, $DB;

    if ($questcmid) {
        $questselect = " AND cm.id = :quest";
        $params=array('quest'=>$questcmid);
    } else {
        $questselect = "";
        $params=array();
    }

    if ($user) {
        $userselect = " AND u.id = :user";
        $params=array_merge($params,array('user'=>$user)); //evp comprobar que funciona bien, $params podr�a ser un array vacio
     } else {
        $userselect = "";
	}
	$context = context_module::instance( $questcmid);

    if(!has_capability('mod/quest:manage', $context)){
     $selectuser = " AND s.userid = :userid";
     	$params=array_merge($params, array('userid'=>$USER->id));
     }
    else{
     $selectuser = "";
    }
// .. get challenges submitted
	$params=array_merge($params, array('sincetime'=>$sincetime),array('course'=>$courseid));
    $posts = $DB->get_records_sql("SELECT s.id, s.userid, s.title, s.timecreated, u.firstname, u.lastname,
            u.picture, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, cm.instance, q.name, cm.section
            FROM {quest_submissions} s
            JOIN {user} u ON s.userid = u.id
            JOIN {quest} q ON s.questid = q.id
            JOIN {course_modules} cm ON cm.instance = q.id
            WHERE s.timecreated  > :sincetime
            $questselect
            $userselect $selectuser
            AND q.course = :course
            AND cm.course = q.course
            ORDER BY s.id",$params);


    if (!empty($posts)) {
     foreach ($posts as $post) {

         if (empty($groupid) || groups_is_member($groupid, $post->userid)) {

             $tmpactivity = new stdClass();
             $tmpactivity->cmid         = $questcmid;
             $tmpactivity->type = "quest";
             $tmpactivity->defaultindex = $index;
             $tmpactivity->instance = $post->instance;
             $tmpactivity->name = $post->name;
             $tmpactivity->section = $post->section;

             $tmpactivity->content = new stdClass();
             $tmpactivity->content->id = 'submissions.php?action=showsubmission&amp;id='.$questcmid.'&amp;id='.$post->id;
             $tmpactivity->content->title = $post->title;

            $tmpactivity->user= new stdClass();
            $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
            $additionalfields = explode(',', user_picture::fields());
            $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
            $tmpactivity->user->userid = $post->userid;
            $tmpactivity->timestamp = $post->timecreated;
            $activities[$index++] = $tmpactivity;
         }
     }
    }

// ... get the answers submitted

    $posts = $DB->get_records_sql("SELECT a.*, u.firstname, u.lastname,
            u.picture, cm.instance, q.name, cm.section
            FROM {quest_answers} a
            JOIN {user} u ON a.userid = u.id
            JOIN {quest} q ON a.questid = q.id
            JOIN {course_modules} cm ON cm.instance = q.id
            WHERE a.date  > :sincetime $questselect
            $userselect $selectuser
            AND q.course = :course
            AND cm.course = q.course
            ORDER BY a.id",$params);

    if (!empty($posts)) {
     foreach ($posts as $post) {

         if (empty($groupid) || groups_is_member($groupid, $post->userid)) {

             $tmpactivity = new Object;

             $tmpactivity->type = "quest";
             $tmpactivity->defaultindex = $index;
             $tmpactivity->instance = $post->instance;
             $tmpactivity->name = $post->name;
             $tmpactivity->section = $post->section;

             $tmpactivity->content->id = 'answer.php?action=showanswer&amp;sid='.$post->submissionid.'&amp;aid='.$post->id;
             $tmpactivity->content->title = $post->title;

              $tmpactivity->user= new stdClass();
            $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
            $additionalfields = explode(',', user_picture::fields());
            $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
            $tmpactivity->user->userid = $post->userid;

             $tmpactivity->timestamp = $post->date;
             $activities[] = $tmpactivity;
             $index++;
         }
     }
    }
    return;
}

/**
 * API funtion for reporting recent aactivity
 *
 * @global type $CFG
 * @global type $USER
 * @global type $OUTPUT
 * @param type $activity
 * @param type $course
 * @param type $detail
 * @return type
 */
function quest_print_recent_mod_activity($activity, $course, $detail=false) {

    global $CFG, $USER,$OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0">';

    if (!empty($activity->content->parent)) {
        $openformat = "<font size=\"2\"><i>";
        $closeformat = "</i></font>";
    } else {
        $openformat = "<b>";
        $closeformat = "</b>";
    }

    echo "<tr>";


     echo "<td class=\"workshoppostpicture\" width=\"35\" valign=\"top\">";
     echo $OUTPUT->user_picture($activity->user);
     echo "</td>";


    echo "<td>$openformat";


    if ($detail) {
        echo "<img src=\"$CFG->modpixpath/$activity->type/icon.gif\" ".
            "height=\"16\" width=\"16\" alt=\"".strip_tags(format_string($activity->name,true))."\" />  ";
    }

    echo "<a href=\"$CFG->wwwroot/mod/quest/".$activity->content->id."\">".$activity->content->title;


    echo "</a>$closeformat";


     echo "<br /><font size=\"2\">";
     echo "<a href=\"$CFG->wwwroot/user/view.php?id=" . $activity->user->userid . "&amp;course=" . "$course\">"
         . fullname($activity->user) . "</a>";
     echo " - " . userdate($activity->timestamp) . "</font></td></tr>";

    echo "</table>";

    return;

}

//////////////////////////////////////////////////////////
// Any other quest functions go here.  Each of them must have a name that
// starts with quest

//////////////////////////////////////////////////////////
function quest_get_submissions($quest)
{
	global $DB;
	return $DB->get_records_select("quest_submissions", "questid = ?  AND timecreated > 0", array($quest->id), "timecreated DESC" );
}
/////////////////////////////////////////////////////
function quest_get_user_submissions($quest, $user) {
	global $DB;
    // return real submissions of user newest first, oldest last. Ignores the dummy submissions
    // which get created to hold the final grades for users that make no submissions
    return $DB->get_records_select("quest_submissions", "questid = ? AND
        userid = ? AND timecreated > 0", array($quest->id,$user->id), "timecreated DESC" );
}
//////////////////////////////////////////////////////////
function quest_get_student_submission($quest, $user) {
// Return a submission for a particular user
    global $CFG,$DB;

    $submission = $DB->get_record("quest_submissions", array("questid"=> $quest->id, "userid"=> $user->id));
    if (!empty($submission->timecreated)) {
        return $submission;
    }
    return NULL;
}
//////////////////////////////////////////////////////////
function quest_get_student_submissions($quest, $order = "title") {
// Return all  ENROLLED student submissions
    global $CFG,$DB;

    if ($order == "title") {
        $order = "s.title";
        }
    if ($order == "name") {
        $order = "a.lastname, a.firstname";
        }
    if ($order == "time") {
        $order = "s.timecreated ASC";
    }
    // make sure it works on the site course
    $site = get_site();
    if ($quest->course == $site->id) {
        $select = '';
        $params=array('quest'=>$quest->id);
    }else{   //evp quiz�s haya que probar que esto funciona como se quiere
    	$select = "u.course = :course AND";
     	$params=array('course'=>$quest->course,'quest'=>$quest->id);
    }

    return $DB->get_records_sql("SELECT s.* FROM {quest_submissions} s,
                            {user_students} u, {user} a
                            WHERE $select s.userid = u.userid
                              AND a.id = u.userid
                              AND s.questid = :quest
    		                  AND s.timecreated > 0
                              ORDER BY $order",$params);
}


//////////////////////////////////////////////////////////
function quest_get_assessments($answer, $all = '', $order = '') {
    // Return assessments for this submission ordered oldest first, newest last
    // new assessments made within the editing time are NOT returned unless they
    // belong to the user or the second argument is set to ALL
    global $CFG, $USER, $DB;

    $timenow = time();
    if (!$order) {
        $order = "dateassessment DESC";
    }
    if ($all != 'ALL') {
        return $DB->get_records_select("quest_assessments", "answerid = ? AND dateassessment < ? AND userid = ?",array($answer->id,$timenow,$USER->id), $order);
    } else {
        return $DB->get_records_select("quest_assessments", "answerid = ? AND dateassessment < ?",array($answer->id,$timenow), $order);
    }
}




function quest_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);
    if (!has_capability('mod/quest:view', $context)) {
        return false;
    }
    if (!$quest = get_coursemodule_from_id('quest', $cm->id)) {
        return false;
    }

    if ($filearea === 'introattachment') {
        $relativepath = implode('/', $args);
        $entryid = 0;
    } else {
        $entryid = (int) array_shift($args);
        if ($filearea === 'attachment' or $filearea === 'submission') {
            if (!$entry = $DB->get_record('quest_submissions', array('id' => $entryid))) {
                return false;
            }
        } else if ($filearea === 'answer_attachment' or $filearea === 'answer') {
            if (!$entry = $DB->get_record('quest_answers', array('id' => $entryid))) {
                return false;
            }
        } else {
            return false; // unknown filearea
        }

        $relativepath = implode('/', $args);
    }
    $fs = get_file_storage();
    $hash = $fs->get_pathname_hash($context->id, 'mod_quest', $filearea, $entryid, '', '/'.$relativepath);
    if (!$file = $fs->get_file_by_hash($hash) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}

/**
     * Save the attachments in the draft areas.
     *
     * @param stdClass $formdata
     */
  function quest_save_intro_draft_files($formdata,$ctx) {
        if (isset($formdata->introattachments)) {
            file_save_draft_area_files($formdata->introattachments, $ctx->id,
                                       'mod_quest', 'introattachment', 0);
        }
    }
////////////////////////////////////////////////////
function quest_fullname($userid, $courseid) {
    global $CFG,$DB;
    if (!$user = $DB->get_record('user',array('id'=>$userid))) {
        return get_string('unknownauthor','quest');
    }
    return '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$courseid.'">'.
        fullname($user).'</a>';
}

/**
 * Get challenges submitted from timestart in a course
 *
 * @global type $CFG
 * @global type $USER
 * @global type $DB
 * @param type $course
 * @param type $timestart
 * @return boolean
 */
function quest_get_submitsubmission_logs($course, $timestart) {

    global $CFG, $USER,$DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    $submissions = $DB->get_records_sql(
            "SELECT s.*
              FROM {quest} q,{quest_submissions} s
              WHERE s.timecreated > :timestart AND s.timecreated < :timethen
                   AND q.course = :course
                   AND s.questid = q.id",
            array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id));
//    return $DB->get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, s.questid, s.userid, s.title
//                             FROM {log} l,
//                                {quest} e,
//                                {quest_submissions} s,
//                                {user} u
//                            WHERE l.time > :timestart AND l.time < :timethen
//                                AND l.course = :course AND l.module = 'quest' AND l.action = 'submit_submissi'
//                                AND l.info = s.id AND u.id = s.userid AND e.id = s.questid",array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id));
    return $submissions;
}
/**
 *
 * @global type $CFG
 * @global type $USER
 * @global type $DB
 * @param type $course
 * @param type $timestart
 * @return boolean
 */
function quest_get_submitsubmissionuser_logs($course, $timestart){

 global $CFG, $USER,$DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
//    return $DB->get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, s.questid, s.userid, s.title
//                             FROM {log} l,
//                                {quest} e,
//                                {quest_submissions} s,
//                                {user} u
//                            WHERE l.time > :timestart AND l.time < :timethen
//                                AND l.course = :course AND l.module = 'quest' AND l.action = 'submit_submissi'
//                                AND l.info = s.id AND s.userid = :userid AND u.id = s.userid AND e.id = s.questid",array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id,'userid'=>$USER->id));
     $submissions = $DB->get_records_sql(
            "SELECT s.*
              FROM {quest} q,{quest_submissions} s
              WHERE s.timecreated > :timestart AND s.timecreated < :timethen
                   AND q.course = :course
                   AND s.questid = q.id
                   AND s.userid = :userid",
            array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id,'userid'=>$USER->id));
     return $submissions;
}
/////////////////////////////////////////////////////////
function quest_get_approvesubmission_logs($course, $timestart) {

 global $CFG, $USER,$DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, s.questid, s.userid, s.title
                             FROM {log} l,
                                {quest} e,
                                {quest_submissions} s,
                                {user} u
                            WHERE l.time > :timestart AND l.time < :timethen
                                AND l.course = :course AND l.module = 'quest' AND l.action = 'approve_submiss'
                                AND l.info = s.id AND s.userid = :user AND u.id = s.userid AND e.id = s.questid",array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id,'user'=>$USER->id));
}

//////////////////////////////////////////////////////
function quest_get_submitanswer_logs($course, $timestart){

 global $CFG, $USER,$DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, a.questid, a.userid, a.title
                             FROM {log} l,
                                {quest} e,
                                {quest_answers} a,
                                {user} u
                            WHERE l.time > :timestart AND l.time < :timethen
                                AND l.course = :course AND l.module = 'quest' AND l.action = 'submit_answer'
                                AND l.info = a.id AND a.userid = :user AND u.id = a.userid AND e.id = a.questid",array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id,'user'=>$USER->id));
}
////////////////////////////////////////////////////////////////
function quest_get_assessments_logs($course, $timestart) {

    global $CFG, $USER,$DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, a.questid, a.userid, s.title
                             FROM {log} l,
                                {quest} e,
                                {quest_answers} s,
                                {quest_assessments} a,
                                {user} u
                            WHERE l.time > :timestart AND l.time < :timethen
                                AND l.course = :course AND l.module = 'quest' AND l.action = 'assess_answer'
                                AND a.id = l.info AND s.id = a.answerid AND s.userid = :user
                                AND u.id = a.userid AND e.id = a.questid",array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id,'user'=>$USER->id));
}

////////////////////////////////////////////////////////////////
function quest_get_assessmentsautor_logs($course, $timestart) {

    global $CFG, $USER,$DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, a.questid, a.userid, s.title
                             FROM {log} l,
                                {quest} e,
                                {quest_submissions} s,
                                {quest_assessments_autors} a,
                                {user} u
                            WHERE l.time > :timestart AND l.time < :timethen
                                AND l.course = :course AND l.module = 'quest' AND l.action = 'assess_submissi'
                                AND a.id = l.info AND s.id = a.submissionid AND s.userid = :user
                                AND u.id = a.userid AND e.id = a.questid",array('timestart'=>$timestart,'timethen'=>$timethen,'course'=>$course->id,'user'=>$USER->id));
}
//////////////////////////////////////////////////

function quest_get_course_members($courseid, $sort='s.timeaccess', $dir='', $page=0, $recordsperpage=99999,
                             $firstinitial='', $lastinitial='', $group=NULL, $search='', $fields='', $exceptions='') {
	global $CFG;




	if (!$fields) {
        $fields = 'u.*';
    }

    $context=context_course::instance( $courseid);
    $students=get_enrolled_users($context,'',0,$fields,$sort);
    return $students;
}

//////////////////////////////////////////////////////////
 function quest_send_message($user, $file, $text, $quest, $field1, $field2='', $from ='')
 {
    require_once 'locallib.php';
    global $CFG, $SITE, $DB,$COURSE;
    if (!$user){
        return;
    }
    $user = get_complete_user_data('id', $user->id);
    $site = get_site();
    if (empty($from) || $from==null)
    {
        //If there are a "no_reply" user use him. otherwise submit from any teacher.
        $userfrom = class_exists('core_user')?core_user::NOREPLY_USER:quest_get_teacher($COURSE->id);
    }
    else
    {
    	$userfrom = $from;
    }

    $data = new stdClass();
    $data->firstname = fullname($user);
    $data->sitename = $site->fullname;
    $data->admin = $CFG->supportname .' ('. $CFG->supportemail .')';
    $data->title = $field1->title;
    $data->name = $quest->name;
    if (!empty($field2))
    	$data->secondname = $field2->title;

    $subject = get_string('email'.$text.'subject', 'quest', $data->title);

    // Make the text version a normal link for normal people
    $data->link = $CFG->wwwroot ."/mod/quest/$file";
    $message = get_string('email'.$text, 'quest', $data);

    // Make the HTML version more XHTML happy  (&amp;)
    $data->link = $CFG->wwwroot ."/mod/quest/$file";
    $messagehtml = text_to_html($message, false, false, true);
    $user->mailformat = 1;  // Always send HTML version as well

    // messaging for Moodle 2.4+
    global $CFG;
    if ($CFG->version > 2012120300 )
    {
    $eventdata = new stdClass();
    $eventdata->component = 'mod_quest';

    // detect message type
    switch ($text)
    {
    	case 'assessment':
    	case 'assessmentautor':
    	case 'evaluatecomment':
    		$messagename='evaluation_update';
    		break;

    	case 'addsubmission':
    	case 'save':
    	case 'deletesubmission':
    	case 'answeradd':
    	case 'answerdelete':
    	case 'modifsubmission':
    	    $messagename='challenge_update';
    	    break;
    	default:
    		$messagename='challenge_update';
    }
    $eventdata->name	=	$messagename;
    $eventdata->userfrom = $userfrom;
    $eventdata->userto = $user;
    $eventdata->subject=$subject;
    $eventdata->fullmessage = $messagehtml;
    $eventdata->fullmessagehtml = $messagehtml;
    $eventdata->smallmessage = '';
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->notification = true;

    $msgid = message_send($eventdata);
    return $msgid;
    }
    else
    {// old code for Moodle 1.9.x

// Save the new message in the database

    $savemessage = new stdClass();
    $savemessage->useridfrom    = $userfrom->id;
    $savemessage->useridto      = $user->id;
    $savemessage->message       = $message;
    $savemessage->format        = 0;
    $savemessage->timecreated   = time();
    $savemessage->messagetype   = 'direct';
	$savemessage->id = $DB->insert_record('message', $savemessage);

    if (!$savemessage->id) {
    	print("Can not insert message in table message");
    	//print_object($savemessage);
        //return false; //JPC lets script continue and try to send email notice
    }

// Check to see if anything else needs to be done with it

    $preference = (object)get_user_preferences(NULL, NULL, $user->id);


    if (!empty($preference->message_emailmessages)) {  // Receiver wants mail forwarding
        if ((time() - $user->lastaccess) > ((int)$preference->message_emailtimenosee * 60)) { // Long enough

            $message = stripslashes_safe($message);
            $tagline = get_string('emailtagline', 'quest', userdate(time(), get_string('datestrmodel', 'quest')));

            $messagesubject = $subject;

            $format = 0;

            $messagetext = format_text_email($message, $format).
                           "\n\n--\n".$tagline."\n"."$CFG->wwwroot/message/index.php?popup=1";

            if ($preference->message_emailformat == FORMAT_HTML) {
                $format = 1;
                $messagehtml  = format_text_email($message, $format);
                $messagehtml .= '<hr /><p><a href="'.$CFG->wwwroot.'/message/index.php?popup=1">'.$tagline.'</a></p>';
                $messagehtml = quest_message_html($messagehtml,$quest->course,$userfrom,$subject);
            } else {
                $messagehtml = NULL;
            }

            $user->email = $preference->message_emailaddress;   // Use custom messaging address

            email_to_user($user, $userfrom, $messagesubject, $messagetext, $messagehtml);
        }
    }

    add_to_log(SITEID, 'message', 'write', 'history.php?user1='.$user->id.'&amp;user2='.$userfrom->id/*.'#m'.$messageid*/, "$user->id");
    return $savemessage->id;

    }// end code Moodle 1.9.x


}

function quest_message_html($messagehtml,$courseid,$userfrom,$subject) {

 global $CFG;

 $outputhtml = '<head>';
 foreach ($CFG->stylesheets as $stylesheet) {
        $outputhtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
 }
 $outputhtml .= '</head>';
 $outputhtml .= "\n<body id=\"email\">\n\n";
 $strquests = get_string('quests', 'quest');
 $outputhtml .= '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

 $outputhtml .= '<tr class="header"><td width="35" valign="top" class="picture left">';
 $outputhtml .= print_user_picture($userfrom->id, $courseid, $userfrom->picture, false, true);
 $outputhtml .= '</td>';

 $outputhtml .= '<td class="topic starter">';

 $outputhtml .= '<div class="subject">'.$subject.'</div>';

 $fullname = fullname($userfrom, isteacher($courseid));
 $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userfrom->id.'&amp;course='.$courseid.'">'.$fullname.'</a>';
 $by->date = userdate(time(), '', $userfrom->timezone);
 $outputhtml .= '<div class="author">'.get_string('bynameondate', 'forum', $by).'</div>';

 $outputhtml .= '</td></tr>';

 $outputhtml .= '<tr><td class="left side"> </td><td class="content">';

 $messagehtml = text_to_html($messagehtml, false, false, true);
 $outputhtml .= $messagehtml;

 $outputhtml .= '</td></tr></table>';

 $outputhtml .= '</body>';

 return $outputhtml;

}
/********
RESET course
***********/

/**
 * Called by course/reset.php
 * @param $mform form passed by reference
 */
function quest_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'questournamentheader', get_string('modulenameplural', 'quest'));

    $mform->addElement('checkbox', 'reset_quest_all_answers', get_string('resetquestallanswers','quest'));
}

/**
 * Course reset form defaults.
 */
function quest_reset_course_form_defaults($course) {
    return array('reset_quest_all_answers'=>0);
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all answers, teams, and evaluations from the specified questournament
 * and clean up any related data.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function quest_reset_userdata($data) {
    global $CFG,$DB;
    require_once($CFG->libdir.'/filelib.php');

    $componentstr = get_string('modulenameplural', 'quest');
    $status = array();

    $removeanswers = false;

    if (!empty($data->reset_quest_all_answers)) {
        $removeanswers = true;
        $typesql     = "";
        $typesstr    = get_string('resetquestallanswers', 'quest');
        $types       = array();

    }

//evp revisar que todo esto funciona bien!!!!!

    //$allquestssql      = "SELECT q.id
      //                      FROM {quest} q
        //                   WHERE q.course={$data->courseid}";

    $questidsSql = $DB->get_records('quest',array('course'=>$data->courseid),'','id');
    $questids=array();
    foreach ($questidsSql as $quid)
        $questids[]=$quid->id;

    list($insql, $inparams) = $DB->get_in_or_equal($questids);
    $answerssql   = "SELECT a.id as id FROM mdl_quest_answers a, mdl_quest q WHERE q.course=? and a.questid=q.id";
 	$answerparams = array($data->courseid);

    if ($removeanswers)
	{


//evp esto ya no hace falta, lo que no entiendo es por qu� se ha incluire $typesql
//       $questssql      =  str_replace("mdl_",$CFG->prefix,"$allquestssql $typesql");
 //       $answerssql       =  str_replace("mdl_",$CFG->prefix,"$allanswerssql $typesql");




        // remove assessments

        $DB->delete_records_select('quest_elements_assesments',  "questid $insql",$inparams);
        $DB->delete_records_select('quest_elements_assesments_autor',  "questid $insql",$inparams);
        $DB->delete_records_select('quest_assesments',  "questid $insql", $inparams);
        $DB->delete_records_select('quest_assesments_autor',  "questid $insql",$inparams);
        // remove califications
        $DB->delete_records_select('quest_calification_users',  "questid $insql",$inparams);
        $DB->delete_records_select('quest_calification_teams',  "questid $insql",$inparams);
        // delete all teams
        $DB->delete_records_select('quest_teams', "questid $insql",$inparams);



        // now get rid of all attachments
        if ($answers = $DB->get_records_sql($answerssql,$answerparams)) {
            foreach ($answers as $answerid=>$unused) {
                fulldelete($CFG->dataroot.'/'.$data->courseid.'/moddata/quest/answers/'.$answerid);
//                print("delete:".$CFG->dataroot.'/'.$data->courseid.'/moddata/quest/answers/'.$answerid);
            }
        }

        // delete all answers
        $DB->delete_records_select('quest_answers', "questid $insql",$inparams);

	// reset counters
//	$DB->set_field('quest','maxcalification',0,'course',$data->courseid);

        //evp hay que pensar si sustituir la siguiente consulta ya que no recomienda untilizar execute
	$resetsubmissions="UPDATE {quest_submissions} SET
			nanswers = 0,
			nanswerscorrect = 0,
			dateanswercorrect = null,
			pointsanswercorrect = 0,
			mailed = 0,
			maileduser = 0
                        WHERE questid $insql";

        $DB->execute($resetsubmissions, $inparams);


        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

// updating dates - shift may be negative too

    if ($data->timeshift)
	{

    shift_course_mod_dates('quest', array('datestart', 'dateend'), $data->timeshift, $data->courseid);
	$shifttimesql="UPDATE {quest_submissions}
                          SET datestart = datestart + ($data->timeshift), dateend = dateend + ($data->timeshift)
                        WHERE questid  $insql and datestart<>0";

    $DB->execute($shifttimesql, $inparams);


        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}
/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $questnode The node to add module settings to
 */
function quest_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $questnode)
{
	global $USER, $PAGE, $CFG, $DB, $OUTPUT;

	$questobject = $DB->get_record("quest", array("id" => $PAGE->cm->instance));
	if (empty($PAGE->cm->context)) {
		$PAGE->cm->context = context_module::instance($PAGE->cm->instance);
	}

	// For some actions you need to be enrolled, beiing admin is not enough sometimes here:
    // 	$enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    // 	$activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);
   
    $questnode->add('Questournaments',new moodle_url('/mod/quest/index.php',array('id'=>$PAGE->course->id)),navigation_node::TYPE_SETTING);

	//manage Teams
	if(has_capability('mod/quest:manage', $PAGE->cm->context))
	{
		if ($questobject->allowteams)
		  $questnode->add(get_string('changeteamteacher','quest'),new moodle_url('/mod/quest/team.php',array('id'=>$PAGE->cm->id,'action'=>'change')),navigation_node::TYPE_SETTING);
    }
    if (has_capability('mod/quest:downloadlogs', $PAGE->cm->context)){
		$catnode=$questnode->add('Admin logs',null,navigation_node::TYPE_CONTAINER);
		$catnode->add('Get technical logs',new moodle_url('/mod/quest/getLogs.php',array('id'=>$PAGE->cm->id)),navigation_node::TYPE_SETTING);
		$catnode->add('Full activity listing',new moodle_url('/mod/quest/report.php',array('id'=>$PAGE->cm->id)),navigation_node::TYPE_SETTING);
	}


}
