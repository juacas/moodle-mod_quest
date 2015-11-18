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


    $action=optional_param('action','change',PARAM_ALPHA);
    $sort=optional_param('sort','lastname',PARAM_ALPHA);
    $dir=optional_param('dir','ASC',PARAM_ALPHA);

    global $DB, $PAGE, $OUTPUT;
   $timenow = time();


    $cm = get_coursemodule_from_id('quest', $id,0,false,MUST_EXIST);
    $course = get_course($cm->course);
    $quest = $DB->get_record("quest", array("id"=> $cm->instance),'*',MUST_EXIST);
    require_login($course->id, false, $cm);



	quest_check_visibility($course,$cm);
	$context = context_module::instance( $cm->id);
	$ismanager=has_capability('mod/quest:manage',$context);



// Print the page header

	$url =  new moodle_url('/mod/quest/team.php',array('id'=>$id,'action'=>$action,'sort'=>$sort,'dir'=>$dir));
	$PAGE->set_url($url);
	$PAGE->set_title(format_string($quest->name));
	$PAGE->set_heading($course->fullname);
	echo $OUTPUT->header();

    $strquests = get_string("modulenameplural", "quest");
    $strquest  = get_string("modulename", "quest");
    $straction = ($action) ? '-> '.$action : '-> '.get_string('changeteam', 'quest');

    /*print_header_simple(format_string($quest->name), "",
                 "<a href=\"index.php?id=$course->id\">$strquests</a> ->
                  <a href=\"view.php?id=$cm->id\">".format_string($quest->name,true)."</a> $straction",
                  "", "", true);
*/

    add_to_log($course->id, "quest", "view_teams", "team.php?id=$cm->id", "$quest->id");


    if($quest->allowteams != 1)
    {
        error('It is not allowed teams for this Questournament.');
    }

    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
    $groupmode = groupmode($course, $cm);   // Groups are being used?
    $currentgroup = groups_get_course_group($course);
    $groupmode=$currentgroup=false;//JPC group support desactivation


        // Allow the teacher to change groups (for this session)
    if ($groupmode and isteacheredit($course->id))
    	{
        if ($groups = $DB->get_records_menu("groups", array("courseid"=> $course->id), "name ASC", "id,name"))
        	{
        		print_group_menu($groups, $groupmode, $currentgroup, "team.php?id=$cm->id");
            }
        }


    if($ismanager)
     {

     $form = data_submitted("nomatch");
	 if ($form == false) $form = new stdclass();

     $title = get_string('changeteamteacher','quest');
     echo $OUTPUT->heading_with_help($title,"changeteamteacher","quest");


     if($action == 'change')
      {
      if(($groupmode == false)||($currentgroup != 0))
      {
//        for($i=0;$i<$form->i;$i++)
    if (isset($form->team))
       foreach($form->team as $i=>$team_field)
		{

		$team_field=trim($team_field);
	    if(!empty($team_field))
			{
	         	//JPC unused $user = get_complete_user_data('id', $form->userid[$i]);
	         	$userid=$form->userid[$i];
	         	if($calification_user = $DB->get_record("quest_calification_users",array("userid"=> $userid, "questid"=>$quest->id)))
				{
				if(!empty($calification_user->teamid))
					{
					   $oldteam = $DB->get_record("quest_teams", array("id"=> $calification_user->teamid));
					}
					else
					{
					    $oldteam=null;
					}

				if($team = $DB->get_record("quest_teams", array("name"=> $team_field,"currentgroup"=> $currentgroup,"questid"=>$quest->id)))
					{// Exist the team with this name
					    if ($calification_user->teamid == $team->id)
					        continue;
					    $members =quest_get_team_members($quest->id, $team->id);
					    $team->ncomponents=count($members);

					    if($quest->ncomponents > $team->ncomponents)
					    {
					        $calification_user->teamid = $team->id;
					        $DB->set_field("quest_calification_users","teamid", $calification_user->teamid, array("id"=> $calification_user->id));
					        quest_update_team_scores($quest->id,$team->id);
					    }
					    else{
					        echo ("<center><b>The Team \"$team->name\" is complete</b></center>");
					        echo $OUTPUT->continue_button("view.php?id=$cm->id");
					        exit();
					    }
					if($oldteam!=null && $team->id != $oldteam->id) // user change of team
						{
						    quest_update_team_scores($quest->id,$oldteam->id); // update stats for oldteam
						} // change of team

	              }// exist the team with this name
	              else
		      { // Create a new team
				    $team = new stdClass();
	               $team->ncomponents=1;
	               $team->questid = $quest->id;
	               $team->currentgroup = $currentgroup;
	               $team->name = $form->team[$i];

	               $team->id = $DB->insert_record("quest_teams", $team);

	               $calification_user->teamid = $team->id;
	               $DB->set_field("quest_calification_users","teamid", $calification_user->teamid, array("id"=> $calification_user->id));

	               quest_update_team_scores($quest->id,$team->id);
	               if(!empty($oldteam) )
	               {
	                    quest_update_team_scores($quest->id,$oldteam->id);
	                }

	              }

	        if(!empty($calification_user->teamid) && !empty($oldteam))
				{
	               	if($team->id != $oldteam->id){
	                	$oldteam->ncomponents--;
		                if($oldteam->ncomponents == 0)
					{
					print("<p>Team: $oldteam->name ($oldteam->id) empty.Deleted.</p>");
					$DB->delete_records("quest_teams", array("id"=> $oldteam->id));
					$DB->delete_records('quest_calification_teams',array('questid'=>$quest->id,'teamid'=>$oldteam->id));

					}

	               }

	              }
	         }
	        }
	       }
      }
     }
     else
     {
     	print_error($action,'quest');
     }


     if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")){
            echo $OUTPUT->heading(get_string("nostudentsyet"));
            echo $OUTPUT->footer($course);
            exit;
        }


        // Now prepare table with student assessments and submissions
        $tablesort = new stdClass();
        $tablesort->data = array();
        $tablesort->sortdata = array();
        $i = 0;

        echo "<form enctype=\"multipart/form-data\" name=\"team\" method=\"POST\" action=\"team.php?id=$id\">";
        foreach ($users as $user)
        {
            // skip if student not in group
        	if($ismanager)
        	{

        			if ($currentgroup) {
        				if (!groups_is_member($currentgroup, $user->id)) {
        					continue;
        				}
        			}
        	}
            else if(!$ismanager&&($groupmode == 1)){
             if ($currentgroup) {
                 if (!groups_is_member($currentgroup, $user->id)) {
                     continue;
                 }
             }
            }


         	$calification_user = $DB->get_record("quest_calification_users", array("userid"=> $user->id, "questid"=> $quest->id));

         	if ($calification_user)
         		$team = $DB->get_record("quest_teams", array("id"=> $calification_user->teamid));

         	if (empty($calification_user))
         	{
         		$team = new stdClass();
         		$team->name="<font color=\"#ff0000\"><i>Not participant</i></font>";
         		$team->ncomponents="<font color=\"#ff0000\"><i>Undefined</i></font>";
         	}
         	else
         	if (empty($team))
         	{
         		$team = new stdClass();
         		$team->name="<font color=\"#ff0000\"><i>Undefined</i></font>";
         		$team->ncomponents="<font color=\"#ff0000\"><i>Undefined</i></font>";
         	}

         	$data = array();
         	$sortdata = array();
			$user->imagealt=fullname($user);
         	$data[] = $OUTPUT->user_picture($user,array('size'=>20))."<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">".
           	fullname($user).'</a>';
         	$sortdata['firstname'] = strtolower($user->firstname);
         	$sortdata['lastname'] = strtolower($user->lastname);

         	$data[] = $team->name;
         	$sortdata['teamname'] = strtolower($team->name);

         	$data[] = $team->ncomponents;
         	$sortdata['ncomponents'] = $team->ncomponents;
         	if (empty($calification_user))
         	{
         		$data[]="";
         	}else{
         		$data[] = "<input name=\"team[$i]\" type=\"text\"><input name=\"userid[$i]\" type=\"hidden\" value=\"$user->id\">";
         	}
         	$sortdata['newteam'] = "<input name=\"team[$i]\" type=\"text\"><input name=\"userid[$i]\" type=\"hidden\" value=\"$user->id\">";

         	$i++;

         	$tablesort->data[] = $data;
         	$tablesort->sortdata[] = $sortdata;


        }

        uasort($tablesort->sortdata, 'quest_sortfunction');
        $table = new html_table();
        $table->data = array();
        foreach($tablesort->sortdata as $key => $row) {
            $table->data[] = $tablesort->data[$key];
        }


        $table->align = array ('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');

        $columns = array('firstname', 'lastname', 'teamname', 'ncomponents','newteam');
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
                $columnicon = " <img src=\"".$CFG->wwwroot."pix/t/$columnicon.png\" alt=\"$columnicon\" />";

            }
            $$column = "<a href=\"team.php?id=$id&amp;sort=$column&amp;dir=$columndir\">".$string[$column]."</a>$columnicon";
        }

        $table->head = array ("$firstname / $lastname", "$teamname", "$ncomponents", get_string('newteam','quest'));

        echo html_writer::table($table);
        echo "<input name=\"i\" type=\"hidden\" value=\"$i\">\n";
        echo "<input name=\"id\" type=\"hidden\" value=\"$cm->id\">\n";
        echo "<center>\n<input name=\"action\" type=\"submit\" onClick=\"document.form=this.value;document.form.submit()\" value=\"change\" />\n</center>\n";
        echo "</form>";

    }

// Finish the page
    echo $OUTPUT->footer($course);

?>
