<?PHP  // $Id: viewclasification.php

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
    require_once("lib.php");
    require("locallib.php");

    $id=required_param('id',PARAM_INT);    // Course Module ID, or
       
	$a=optional_param('a','',PARAM_ALPHA); // quest ID

    $action=optional_param('action', 'global',PARAM_ALPHA);
    $sort=optional_param('sort','lastname',PARAM_ALPHA);
    $dir=optional_param('dir','ASC',PARAM_ALPHA);

	/**
	*  Flag to force a recalculation of team statistics and scores.
	* Only to solve bugs.
	*/
	$debug_recalculate=optional_param('recalculate','no',PARAM_ALPHA);
	
   $timenow = time();
$NUMBER_PRECISSION=2;
   $local = setlocale(LC_CTYPE, 'esn');
	global $DB, $PAGE,$OUTPUT;

    if ($id) {
        if (! $cm = $DB->get_record("course_modules", array("id"=> $id))) {
            error("Course Module ID was incorrect");
        }
    
        if (! $course = $DB->get_record("course", array("id"=> $cm->course))) {
            error("Course is misconfigured");
        }

        if (! $quest = $DB->get_record("quest", array("id"=> $cm->instance))) {
            error("Quest is incorrect");
        }
    }

    require_login($course->id, false, $cm);
	quest_check_visibility($course, $cm);
	
	$context = context_module::instance( $cm->id);
	$ismanager=has_capability('mod/quest:manage',$context);
	
	
	$url =  new moodle_url('/mod/quest/viewclasification.php',array('id'=>$id));
	if ($a!== ''){
		$url->param('a', $a);
	}
	if ($action!== 'global'){
		$url->param('action', $action);
	}
	if ($sort!== 'lastname'){
		$url->param('sort', $sort);
	}
	if ($dir!== 'ASC'){
		$url->param('dir', $dir);
	}
	
	 
	$PAGE->set_url($url);
	$PAGE->set_title(format_string($quest->name));
	//$PAGE->set_context($context);
	$PAGE->set_heading($course->fullname);
	echo $OUTPUT->header();
    
	/// Print the page header

    $strquests = get_string("modulenameplural", "quest");
    $strquest  = get_string("modulename", "quest");
    $straction = ($action) ? '-> '.get_string($action, 'quest') : '';

    if (($quest->usepassword)&&(!$ismanager)) {
         quest_require_password($quest,$course,$_POST['userpassword']);
    }

    add_to_log($course->id, "quest", "view_clasificat", "viewclasification.php?id=$cm->id", "$quest->id", "$cm->id");

	/** Awful debug section
	*  Flag to force a recalculation of team statistics and scores.
	* Only to solve bugs.
	*/
	if($debug_recalculate == 'yes')
		{
		require("debugJP_lib.php");
		print("<p>Recalculating...</p>");
		updateallusers($quest->id);
		updateallteams($quest->id);
		}
	/**********END DEBUG*********************************/	

		
	$show_authoring_details = 	$ismanager 
							||	has_capability('mod/quest:viewotherattemptsowners', $context)
							|| 	$quest->showauthoringdetails;
	
	
   if($quest->allowteams && !$quest->showclasifindividual)
   {
    $action = 'teams';
   }

   if($action == 'global')
   {

        /// Check to see if groups are being used in this quest
        /// and if so, set $currentgroup to reflect the current group
        $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
        $groupmode = groupmode($course, $cm);   // Groups are being used?
//        $currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);
        $currentgroup = groups_get_course_group($course);
        $groupmode=$currentgroup=false;//JPC group support desactivation
        
        /// Print settings and things in a table across the top
        echo '<table width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';

        /// Allow the teacher to change groups (for this session)
        if ($groupmode and $ismanager) {
            if ($groups = $DB->get_records_menu("groups", array("courseid"=> $course->id), "name ASC", "id,name")) {
                echo '<td>';
                //print_group_menu($groups, $groupmode, $currentgroup, "viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC");
                groups_print_activity_menu($cm, $CFG->wwwroot."/mod/quest/viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC", $return=false, $hideallparticipants=false);
                echo '</td>';
            }
        }
        /// Print admin links
        echo "<td align=\"right\">";

        echo '</td></tr>';

        echo '<tr><td>';

        echo '</td></tr>';
        echo '</table>';
        echo $OUTPUT->heading_with_help(get_string('global','quest'),"global","quest");
        // Get all the students
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname"))
        {
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit;
        }

        /// Now prepare table with student assessments and submissions
        $tablesort = new stdclass();
        $tablesort->data = array();
        $tablesort->sortdata = array();
        foreach ($users as $user) {
            // skip if student not in group
            if ($currentgroup) {
                if (!groups_is_member($currentgroup, $user->id)) {
                    continue;
                }
            }
            if ($clasifications = quest_get_user_clasification($quest, $user)) {
                foreach ($clasifications as $clasification) {

                    $data = array();
                    $sortdata = array();
// user picture
                    //$data[] = print_user_picture($user->id, $course->id, $user->picture,0,true);
                    $user->imagealt=get_string('pictureof','quest')." ".fullname($user);
                    $data[] = $OUTPUT->user_picture($user, array('courseid' => $course->id, 'link' => true));
                    $sortdata['picture'] = 1;
// link to user profile or just fullname
                    if($ismanager){
                        $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">".
                        fullname($user).'</a>';                 
                    }
                    else{
                        $data[] = "<b>".fullname($user).'</b>';
                    }
// first name for sorting                    
                    $sortdata['firstname'] = strtolower($user->firstname);
// last name for sorting
                    $sortdata['lastname'] = strtolower($user->lastname);
// answers submitted
                    if($ismanager){
                      $data[] = "<a href=\"submissions.php?uid=$user->id&amp;action=showanswersuser&amp;cmid=$cm->id\">".$clasification->nanswers.'</a>';
                    }
                    else{
                      $data[] = $clasification->nanswers;
                    }
                    $sortdata['nanswers'] = $clasification->nanswers;
// answers marked
                    $data[] = $clasification->nanswersassessment;
                    $sortdata['nanswersassessment'] = $clasification->nanswersassessment;
                    
                    $show_authoring_details=$ismanager || $quest->showauthoringdetails;
if ($show_authoring_details)
{// START AUTHORING ANONYMIZING

//number of challenges authored
                    if($ismanager){
                      $data[] = "<a href=\"submissions.php?uid=$user->id&amp;action=showsubmissionsuser&amp;cmid=$cm->id\">".$clasification->nsubmissions.'</a>';
                    }
                    else{
                      $data[] = $clasification->nsubmissions;
                    }
                    $sortdata['nsubmissions'] = $clasification->nsubmissions;
//challenges marked
                    $data[] = $clasification->nsubmissionsassessment;
                    $sortdata['nsubmissionsassessment'] = $clasification->nsubmissionsassessment;
//score for  challenges
                    $data[] = number_format($clasification->pointssubmission,$NUMBER_PRECISSION);
                    $sortdata['pointssubmission'] = $clasification->pointssubmission;
//score for answers
                    $data[] = number_format($clasification->pointsanswers,$NUMBER_PRECISSION);
                    $sortdata['pointsanswers'] = $clasification->pointsanswers;
}// END AUTHORING ANONYMIZING

                    if($quest->allowteams)
                    {
                     if($clasification_team = $DB->get_record("quest_calification_teams", array("teamid"=> $clasification->teamid, "questid"=> $quest->id)))
                     {
 // team points
                      $data[] = number_format($clasification_team->points*$quest->teamporcent/100,2);
                      $sortdata['pointsteam'] = $clasification_team->points*$quest->teamporcent/100;
// personal+team points
                      $data[] = number_format($clasification->points + $clasification_team->points*$quest->teamporcent/100,$NUMBER_PRECISSION);
                      $sortdata['points'] = $clasification->points + $clasification_team->points*$quest->teamporcent/100;
                     }
                     else
                     	{
                      	$data[] = number_format(0,$NUMBER_PRECISSION);
                      	$sortdata['pointsteam'] = 0;

                      	$data[] = number_format(0,$NUMBER_PRECISSION);
                      	$sortdata['points'] = $clasification->points;
                     	}
                    }
                    else{
// personal points
                     $data[] = number_format($clasification->points,$NUMBER_PRECISSION);
                     $sortdata['points'] = $clasification->points;
                    }

                    $tablesort->data[] = $data;
                    $tablesort->sortdata[] = $sortdata;
                }
            }
        }

        uasort($tablesort->sortdata, 'quest_sortfunction');
        $table = new html_table();
        $table->data = array();
        
        foreach($tablesort->sortdata as $key => $row) {
            $table->data[] = $tablesort->data[$key];
        }

                $table->align = array ('left','left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
                $table->valign = array ('center','center', 'center', 'center', 'left', 'center', 'center', 'center', 'center', 'center', 'center');

                 $columns = array('picture','firstname','lastname', 'nanswers', 'nanswersassessment');
                 $show_authoring_details=$ismanager || $quest->showauthoringdetails;
                 if ($show_authoring_details)
                 	foreach(array('nsubmissions', 'nsubmissionsassessment', 'pointssubmission', 'pointsanswers') as $col)
                 		$columns[]=$col;
                if($quest->allowteams)
                   	$columns[]='pointsteam';
                             
                 $columns[]='points';
            
        $table->width = "95%";

        foreach ($columns as $column) {
            $string[$column] = get_string("$column", 'quest');
            if ($sort != $column) {
                $columnicon = '';
                $columndir = 'ASC';
            } else {
                $columndir = $dir == 'ASC' ? 'DESC':'ASC';
                if ($column == 'lastaccess') {
                    $columnicon = $dir == 'ASC' ? 'up':'down';
                } else {
                    $columnicon = $dir == 'ASC' ? 'down':'up';
                }
			$columnicon = 	$OUTPUT->pix_icon("t/$columnicon", $columnicon);
       
            }
            $$column = "<a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=$column&amp;dir=$columndir\">".$string[$column]."</a>$columnicon";
        }

		$table->head = array ("","$firstname / $lastname", "$nanswers", "$nanswersassessment");
		$show_authoring_details=$ismanager || $quest->showauthoringdetails;
		if ($show_authoring_details)
			foreach(array("$nsubmissions", "$nsubmissionsassessment", "$pointssubmission", "$pointsanswers") as $head)
				$table->head[]=$head;
	
		if($quest->allowteams)
         	$table->head[]="$pointsteam";
        $table->head[] = "$points";
      
        echo '<tr><td>';
        echo '<div valign="center">';
        echo html_writer::table($table);
        echo '</div>';
        echo '</td></tr>';
        echo '<tr><td>';

        if($quest->allowteams){
         echo( "<center><b><a href=\"viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">".
                 get_string('viewclasificationteams', 'quest')."</a></b></center>");
        }
        echo '</td></tr>';


       echo '</table>';
   }
   elseif($action == 'teams')
   {

        /// Check to see if groups are being used in this quest
        /// and if so, set $currentgroup to reflect the current group
        $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
        $groupmode = groupmode($course, $cm);   // Groups are being used?
        //$currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);
        $currentgroup = groups_get_course_group($course); //evp esta función habrá que comprobar si es la que hay que usar
        $groupmode=$currentgroup=false;//JPC group support desactivation
        
        /// Print settings and things in a table across the top
        echo '<table width="100%" border="0" cellpadding="3" cellspacing="0"><tr valign="top">';

        /// Allow the teacher to change groups (for this session)
        if ($groupmode and isteacheredit($course->id)) {
            if ($groups = $DB->get_records_menu("groups", "courseid", $course->id, "name ASC", "id,name")) {
                echo '<td>';
                print_group_menu($groups, $groupmode, $currentgroup, "viewclasification.php?action=teams&amp;id=$cm->id&amp;sort=points&amp;dir=DESC");
                echo '</td>';
            }
        }
        /// Print admin links
        echo "<td align=\"right\">";

        echo '</td></tr>';

        echo '<tr><td>';

        echo '</td></tr>';
        echo '</table>';
        
        echo $OUTPUT->heading_with_help(get_string('teams','quest'),"teams","quest");

        // Get all the students
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")){
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer();
            exit;
        }

        /// Now prepare table with student assessments and submissions
        $tablesort = new stdclass();
        $tablesort->data = array();
        $tablesort->sortdata = array();
        $teamstemp = array();

        if($teams = $DB->get_records('quest_teams', array('questid'=>$quest->id)))
        {
         foreach($teams as $team)
         {
         foreach ($users as $user) {
            // skip if student not in group
                if ($currentgroup) {
                        if (!groups_is_member($currentgroup, $user->id)) {
                         continue;
                        }
                }

                $clasification = $DB->get_record("quest_calification_users", array("userid"=> $user->id, "questid"=> $quest->id));
                if ($clasification)
                if($clasification->teamid == $team->id)
                {
                 $existy = false;
                   foreach($teamstemp as $teamtemp){
                    if($teamtemp->id == $team->id){
                     $existy = true;
                    }
                   }
                   if(!$existy){
                    $teamstemp[] = $team;
                   }

                }
         }
        }
        }
        $teams = $teamstemp;
        
        foreach ($teams as $team){

               $data = array();
               $sortdata = array();

               if ($clasification_team = $DB->get_record("quest_calification_teams", array("teamid"=> $team->id, "questid"=> $quest->id))) 
               {
// team name
                   
                    
                  $data[] = $team->name;
                  $sortdata['team'] = strtolower($team->name);
// number of answers
                  if($ismanager){
                    $data[] = "<a href=\"submissions.php?tid=$team->id&amp;action=showanswersteam&amp;cmid=$cm->id\">".$clasification_team->nanswers.'</a>';
                  }
                  else{
                    $data[] = $clasification_team->nanswers;
                  }
                  $sortdata['nanswers'] = $clasification_team->nanswers;
//number of marked answers
                  $data[] = $clasification_team->nanswerassessment;
                  $sortdata['nanswersassessment'] = $clasification_team->nanswerassessment;



if ($show_authoring_details)
{// START AUTHORING ANONYMIZING                  
 //number of chellenges submitted
                  if($ismanager){
                    $data[] = "<a href=\"submissions.php?tid=$team->id&amp;action=showsubmissionsteam&amp;cmid=$cm->id\">".$clasification_team->nsubmissions.'</a>';
                  }
                  else{
                    $data[] = $clasification_team->nsubmissions;
                  }
                  $sortdata['nsubmissions'] = $clasification_team->nsubmissions;
// challenges marked
                  $data[] = $clasification_team->nsubmissionsassessment;
                  $sortdata['nsubmissionsassessment'] = $clasification_team->nsubmissionsassessment;
// score for challenges marked
                  $data[] = number_format($clasification_team->pointssubmission,$NUMBER_PRECISSION);
                  $sortdata['pointssubmission'] = $clasification_team->pointssubmission;
// score for answers
                  $data[] = number_format($clasification_team->pointsanswers,$NUMBER_PRECISSION);
                  $sortdata['pointsanswers'] = $clasification_team->pointsanswers;
}// END AUTHORING ANONYMIZING

// total score
                  $data[] = number_format($clasification_team->points,$NUMBER_PRECISSION);
                  $sortdata['points'] = $clasification_team->points;

                  $tablesort->data[] = $data;
                  $tablesort->sortdata[] = $sortdata;
               }

          }

        uasort($tablesort->sortdata, 'quest_sortfunction');
        $table= new html_table();
        $table->data = array();
        foreach($tablesort->sortdata as $key => $row) {
            $table->data[] = $tablesort->data[$key];
        }


        $table->align = array ('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
        $columns = array('team', 'nanswers', 'nanswersassessment');
        $show_authoring_details=$ismanager || $quest->showauthoringdetails;
        if ($show_authoring_details)
         foreach (array('nsubmissions','nsubmissionsassessment','pointssubmission', 'pointsanswers') as $col)
          $columns[]=$col;
        $columns[]='points';

        $table->width = "95%";

        foreach ($columns as $column) {
            $string[$column] = get_string("$column", 'quest');
            if ($sort != $column) {
                $columnicon = '';
                $columndir = 'ASC';
            } else {
                $columndir = $dir == 'ASC' ? 'DESC':'ASC';
                if ($column == 'lastaccess') {
                    $columnicon = $dir == 'ASC' ? 'up':'down';
                } else {
                    $columnicon = $dir == 'ASC' ? 'down':'up';
                }
                $columnicon = 	$OUTPUT->pix_icon("t/$columnicon", $columnicon);
                
            }
            $$column = "<a href=\"viewclasification.php?id=$id&amp;action=teams&amp;sort=$column&amp;dir=$columndir\">".$string[$column]."</a>$columnicon";
        }


        $table->head = array ("$team", "$nanswers", "$nanswersassessment");
        if ($show_authoring_details)
        foreach(array("$nsubmissions", "$nsubmissionsassessment","$pointssubmission", "$pointsanswers") as $head)
        	$table->head[]=$head;
        $table->head[]="$points";


        echo '<tr><td>';
        echo html_writer::table($table);
        echo '</td></tr>';
        echo '<tr><td>';

        if($quest->showclasifindividual){
         echo( "<center><b><a href=\"viewclasification.php?action=global&amp;id=$cm->id&amp;sort=points&amp;dir=DESC\">".
                get_string('viewclasificationglobal', 'quest')."</a></b></center>");
        }

        echo '</td></tr>';


       echo '</table>';
   }  
/// Finish the page
       // print_continue($_SERVER['HTTP_REFERER'].'#id='.$id.'#sort=points#dir=DESC');
        echo $OUTPUT->continue_button(new moodle_url('view.php', array('id' => $id)));
    echo $OUTPUT->footer();

?>
