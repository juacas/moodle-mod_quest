<?php //$Id: backuplib.php
/******************************************************
* Module developed at the University of Valladolid
* Designed and directed by Juan Pablo de Castro with the effort of many other
* students of telecommunciation engineering
* this module is provides as-is without any guarantee. Use it as your own risk.
*
* @author Juan Pablo de Castro and many others.
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quest
************************************************************/

    //This php script contains all the stuff to backup/restore
    //quest mods



    //This function executes all the backup procedure about this mod
    function quest_backup_mods($bf,$preferences) {

        global $CFG;

        $status = true;

        //Iterate over quest table
        $quests = $DB->get_records ("quest","course",$preferences->backup_course,"id");
        if ($quests) {
            foreach ($quests as $quest) {
            	$number = $quest->id;
              quest_backup_one_mod($bf,$preferences,$number);
            }
        }
        //if we've selected to backup users info, then backup files too
        if ($status) {
            if ($preferences->mods["quest"]->userinfo) {
                $status = backup_quest_files($bf,$preferences);
            }
        }
        return $status;
    }
      //function quest_backup_one_mod($bf,$preferences,$quest)
      function quest_backup_one_mod($bf,$preferences,$number)
      {
	  	$quest = $DB->get_record("quest", "id", $number);
        //Start mod
                fwrite ($bf,start_tag("MOD",3,true));
                //Print quest data
                fwrite ($bf,full_tag("ID",4,false,$quest->id));
                fwrite ($bf,full_tag("MODTYPE",4,false,"quest"));
                fwrite ($bf,full_tag("NAME",4,false,$quest->name));
                fwrite ($bf,full_tag("DESCRIPTION",4,false,$quest->description));
                fwrite ($bf,full_tag("VALIDATEASSESSMENT",4,false,$quest->validateassessment));
                fwrite ($bf,full_tag("TIMEMAXQUESTION",4,false,$quest->timemaxquestion));
                fwrite ($bf,full_tag("NMAXANSWERS",4,false,$quest->nmaxanswers));
                fwrite ($bf,full_tag("MAXCALIFICATION",4,false,$quest->maxcalification));
                fwrite ($bf,full_tag("TYPECALIFICATION",4,false,$quest->typecalification));
                fwrite ($bf,full_tag("ALLOWTEAMS",4,false,$quest->allowteams));
                fwrite ($bf,full_tag("NCOMPONENTS",4,false,$quest->ncomponents));
                fwrite ($bf,full_tag("PHASE",4,false,$quest->phase));
                fwrite ($bf,full_tag("VISIBLE",4,false,$quest->visible));
                fwrite ($bf,full_tag("TINITIAL",4,false,$quest->tinitial));
                fwrite ($bf,full_tag("GRADINGSTRATEGYAUTOR",4,false,$quest->gradingstrategyautor));
                fwrite ($bf,full_tag("NELEMENTSAUTOR",4,false,$quest->nelementsautor));
                fwrite ($bf,full_tag("INITIALPOINTS",4,false,$quest->initialpoints));
                fwrite ($bf,full_tag("TEAMPORCENT",4,false,$quest->teamporcent));
                fwrite ($bf,full_tag("NELEMENTS",4,false,$quest->nelements));
                fwrite ($bf,full_tag("NATTACHMENTS",4,false,$quest->nattachments));
                fwrite ($bf,full_tag("FORMAT",4,false,$quest->format));
                fwrite ($bf,full_tag("GRADINGSTRATEGY",4,false,$quest->gradingstrategy));
                fwrite ($bf,full_tag("MAXBYTES",4,false,$quest->maxbytes));
                fwrite ($bf,full_tag("DATESTART",4,false,$quest->datestart));
                fwrite ($bf,full_tag("DATEEND",4,false,$quest->dateend));
                fwrite ($bf,full_tag("USEPASSWORD",4,false,$quest->usepassword));
                fwrite ($bf,full_tag("PASSWORD",4,false,$quest->password));
                fwrite ($bf,full_tag("SHOWCLASIFINDIVIDUAL",4,false,$quest->showclasifindividual));
                fwrite ($bf,full_tag("TYPEGRADE",4,false,$quest->typegrade));
                fwrite ($bf,full_tag("PERMITVIEWAUTORS",4,false,$quest->permitviewautors));
                //Now we backup quest elements
                $status = backup_quest_elements($bf,$preferences,$quest->id);
                if (!$status) return false;
                $status = backup_quest_elementsautor($bf,$preferences,$quest->id);
                if (!$status) return false;

                //if we've selected to backup users info, then execute ...
                if ($preferences->mods["quest"]->userinfo) {
                    $status = backup_quest_submissions($bf,$preferences,$quest->id);
	                if (!$status) return false;
                    $status = backup_quest_calification_users($bf,$preferences,$quest->id);
	                if (!$status) return false;
                    if($quest->allowteams){
                     $status = backup_quest_teams($bf,$preferences,$quest->id);
                    }
	                if (!$status) return false;
                }
                //End mod
                $status =fwrite ($bf,end_tag("MOD",3,true));
                return $status;
      }


    //Backup quest_elements contents
    function backup_quest_elements ($bf,$preferences,$quest) {

        global $CFG, $DB;

        $status = true;

        $quest_elements = $DB->get_records("quest_elements","questid",$quest,"id");
        //If there is quest_elements
        if ($quest_elements) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ELEMENTS",4,true));
            //Iterate over each element
            foreach ($quest_elements as $que_ele) {
            	if ($que_ele->submissionsid==0) {
                //Start element
                $status =fwrite ($bf,start_tag("ELEMENT",5,true));
                //Print element contents
                fwrite ($bf,full_tag("SUBMISSIONSID",6,false,$que_ele->submissionsid));
                fwrite ($bf,full_tag("ELEMENTNO",6,false,$que_ele->elementno));
                fwrite ($bf,full_tag("DESCRIPTION",6,false,$que_ele->description));
                fwrite ($bf,full_tag("SCALE",6,false,$que_ele->scale));
                fwrite ($bf,full_tag("MAXSCORE",6,false,$que_ele->maxscore));
                fwrite ($bf,full_tag("WEIGHT",6,false,$que_ele->weight));

                //Now we backup quest rubrics
                $status = backup_quest_rubrics($bf,$preferences,$quest,$que_ele->elementno);

                //End element
                $status =fwrite ($bf,end_tag("ELEMENT",5,true));
           		}
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ELEMENTS",4,true));

        }
        return $status;
    }

    //Backup quest_rubrics contents
    function backup_quest_rubrics ($bf,$preferences,$quest,$elementno) {

        global $CFG, $DB;

        $status = true;

        $quest_rubrics = $DB->get_records_sql("SELECT * from {quest_rubrics} r
                                             WHERE r.questid = ? and r.elementno = ?
                                             ORDER BY r.elementno", array($quest,$elementno));

        //If there is quest_rubrics
        if ($quest_rubrics) {
            //Write start tag
            $status =fwrite ($bf,start_tag("RUBRICS",6,true));
            //Iterate over each element
            foreach ($quest_rubrics as $que_rub) {
                //Start rubric
                $status =fwrite ($bf,start_tag("RUBRIC",7,true));
                //Print rubric contents
                fwrite ($bf,full_tag("RUBRICNO",8,false,$que_rub->rubricno));
                fwrite ($bf,full_tag("SUBMISSIONSID",8,false,$que_rub->rubricno));
                fwrite ($bf,full_tag("DESCRIPTION",8,false,$que_rub->description));
                //End rubric
                $status =fwrite ($bf,end_tag("RUBRIC",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("RUBRICS",6,true));
        }
        return $status;
    }

    //Backup quest_elementsautor contents
    function backup_quest_elementsautor ($bf,$preferences,$quest) {

        global $CFG, $DB;

        $status = true;

        $quest_elements = $DB->get_records("quest_elementsautor",array("questid"=>$quest),"id");  
        //If there is quest_elementsautor
        if ($quest_elements) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ELEMENTS_AUTOR",4,true));
            //Iterate over each element
            foreach ($quest_elements as $que_ele) {
                //Start element
                $status =fwrite ($bf,start_tag("ELEMENT_AUTOR",5,true));
                //Print element contents
                fwrite ($bf,full_tag("ELEMENTNO",6,false,$que_ele->elementno));
                fwrite ($bf,full_tag("DESCRIPTION",6,false,$que_ele->description));
                fwrite ($bf,full_tag("SCALE",6,false,$que_ele->scale));
                fwrite ($bf,full_tag("MAXSCORE",6,false,$que_ele->maxscore));
                fwrite ($bf,full_tag("WEIGHT",6,false,$que_ele->weight));

                //Now we backup quest rubrics
                $status = backup_quest_rubrics_autor($bf,$preferences,$quest,$que_ele->elementno);

                //End element
                $status =fwrite ($bf,end_tag("ELEMENT_AUTOR",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ELEMENTS_AUTOR",4,true));
        }
        return $status;
    }

    //Backup quest_rubrics_autor contents
    function backup_quest_rubrics_autor ($bf,$preferences,$quest,$elementno) {

        global $CFG;

        $status = true;

        $quest_rubrics = $DB->get_records_sql("SELECT * from {quest_rubrics_autor} r
                                             WHERE r.questid = ? and r.elementno = ?
                                             ORDER BY r.elementno", array($quest,$elemntno));

        //If there is quest_rubrics_autor
        if ($quest_rubrics) {
            //Write start tag
            $status =fwrite ($bf,start_tag("RUBRICS_AUTOR",6,true));
            //Iterate over each element
            foreach ($quest_rubrics as $que_rub) {
                //Start rubric
                $status =fwrite ($bf,start_tag("RUBRIC_AUTOR",7,true));
                //Print rubric contents
                fwrite ($bf,full_tag("RUBRICNO",8,false,$que_rub->rubricno));
                fwrite ($bf,full_tag("DESCRIPTION",8,false,$que_rub->description));
                //End rubric
                $status =fwrite ($bf,end_tag("RUBRIC_AUTOR",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("RUBRICS_AUTOR",6,true));
        }
        return $status;
    }

    //Backup quest_teams contents
    function backup_quest_teams ($bf,$preferences,$quest) {

        global $CFG;

        $status = true;

        $quest_teams = $DB->get_records("quest_teams",array("questid"=>$quest),"id");

        if ($quest_teams) {

            //Write start tag
            $status =fwrite ($bf,start_tag("TEAMS",4,true));
            //Iterate over each team
            foreach ($quest_teams as $que_tea) {
                //Start element
                $status =fwrite ($bf,start_tag("TEAM",5,true));
                //Print team contents
                fwrite ($bf,full_tag("ID",6,false,$que_tea->id));
                fwrite ($bf,full_tag("NAME",6,false,$que_tea->name));
                fwrite ($bf,full_tag("NCOMPONENTS",6,false,$que_tea->ncomponents));
                fwrite ($bf,full_tag("CURRENTGROUP",6,false,$que_tea->currentgroup));

                //Now we backup quest calification teams
                $status = backup_quest_calification_teams($bf,$preferences,$quest,$que_tea->id);

                //End team
                $status =fwrite ($bf,end_tag("TEAM",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("TEAMS",4,true));
        }
        return $status;
    }

    //Backup quest_calification_teams contents
    function backup_quest_calification_teams ($bf,$preferences,$quest,$teamid) {

        global $CFG;

        $status = true;

        $quest_califications_teams = $DB->get_records_sql("SELECT * from {quest_calification_teams} r
                                             WHERE r.questid = ? and r.teamid = ?
                                             ORDER BY r.teamid", array($quest,$teamid));
        //If there is quest_calification_teams
        if ($quest_califications_teams) {

            //Write start tag
            $status =fwrite ($bf,start_tag("CALIFICATIONS_TEAMS",6,true));
            //Iterate over each calification team
            foreach ($quest_califications_teams as $que_cal) {
                //Start calification team
                $status =fwrite ($bf,start_tag("CALIFICATION_TEAM",7,true));
                //Print calification team contents
                fwrite ($bf,full_tag("POINTS",8,false,$que_cal->points));
                fwrite ($bf,full_tag("NANSWERS",8,false,$que_cal->nanswers));
                fwrite ($bf,full_tag("NANSWERASSESSMENT",8,false,$que_cal->nanswerassessment));
                fwrite ($bf,full_tag("NSUBMISSIONS",8,false,$que_cal->nsubmissions));
                fwrite ($bf,full_tag("NSUBMISSIONSASSESSMENT",8,false,$que_cal->nsubmissionsassessment));
                fwrite ($bf,full_tag("POINTSSUBMISSION",8,false,$que_cal->pointssubmission));
                fwrite ($bf,full_tag("POINTSANSWERS",8,false,$que_cal->pointsanswers));

                //End calification team
                $status =fwrite ($bf,end_tag("CALIFICATION_TEAM",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("CALIFICATIONS_TEAMS",6,true));
        }
        return $status;
    }

    //Backup quest_calification_users contents
    function backup_quest_calification_users ($bf,$preferences,$quest) {

        global $CFG,$DB;

        $status = true;

        $quest_califications_users = $DB->get_records("quest_calification_users",array("questid"=>$quest),"id");

        //If there is quest_calification_users
        if ($quest_califications_users) {
            //Write start tag
            $status =fwrite ($bf,start_tag("CALIFICATIONS_USERS",4,true));
            //Iterate over each calification user
            foreach ($quest_califications_users as $que_cal) {
                //Start calification user
                $status =fwrite ($bf,start_tag("CALIFICATION_USER",5,true));
                //Print calification user contents
                fwrite ($bf,full_tag("USERID",6,false,$que_cal->userid));
                fwrite ($bf,full_tag("TEAMID",6,false,$que_cal->teamid));
                fwrite ($bf,full_tag("POINTS",6,false,$que_cal->points));
                fwrite ($bf,full_tag("NANSWERS",6,false,$que_cal->nanswers));
                fwrite ($bf,full_tag("NANSWERSASSESSMENT",6,false,$que_cal->nanswersassessment));
                fwrite ($bf,full_tag("NSUBMISSIONS",6,false,$que_cal->nsubmissions));
                fwrite ($bf,full_tag("NSUBMISSIONSASSESSMENT",6,false,$que_cal->nsubmissionsassessment));
                fwrite ($bf,full_tag("POINTSSUBMISSION",6,false,$que_cal->pointssubmission));
                fwrite ($bf,full_tag("POINTSANSWERS",6,false,$que_cal->pointsanswers));
                //End calification user
                $status =fwrite ($bf,end_tag("CALIFICATION_USER",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("CALIFICATIONS_USERS",4,true));
        }
        return $status;
    }

    //Backup quest_submissions contents
    function backup_quest_submissions ($bf,$preferences,$quest) {

        global $CFG,$DB;

        $status = true;

        $quest_submissions = $DB->get_records("quest_submissions",array("questid"=>$quest),"id");
        //If there is submissions
        if ($quest_submissions) {
            //Write start tag
            $status =fwrite ($bf,start_tag("SUBMISSIONS",4,true));
            //Iterate over each submission
            foreach ($quest_submissions as $que_sub) {
                //Start submission
                $status =fwrite ($bf,start_tag("SUBMISSION",5,true));
                //Print submission contents
                fwrite ($bf,full_tag("ID",6,false,$que_sub->id));
                fwrite ($bf,full_tag("QUESTID",6,false,$que_sub->questid));
                fwrite ($bf,full_tag("USERID",6,false,$que_sub->userid));
                fwrite ($bf,full_tag("NUMELEMENTS",6,false,$que_sub->numelements));
                fwrite ($bf,full_tag("TITLE",6,false,$que_sub->title));
                fwrite ($bf,full_tag("TIMECREATED",6,false,$que_sub->timecreated));
                fwrite ($bf,full_tag("POINTS",6,false,$que_sub->points));
                fwrite ($bf,full_tag("PHASE",6,false,$que_sub->phase));
                fwrite ($bf,full_tag("COMENTTEACHERPUPIL",6,false,$que_sub->comentteacherpupil));
                fwrite ($bf,full_tag("COMENTTEACHERAUTOR",6,false,$que_sub->comentteacherautor));
                fwrite ($bf,full_tag("DATEEND",6,false,$que_sub->dateend));

                fwrite ($bf,full_tag("NANSWERS",6,false,$que_sub->nanswers));
                fwrite ($bf,full_tag("NANSWERSCORRECT",6,false,$que_sub->nanswerscorrect));

                fwrite ($bf,full_tag("STATE",6,false,$que_sub->state));
                fwrite ($bf,full_tag("DATESTART",6,false,$que_sub->datestart));
                fwrite ($bf,full_tag("POINTSMAX",6,false,$que_sub->pointsmax));
                fwrite ($bf,full_tag("DATEANSWERCORRECT",6,false,$que_sub->dateanswercorrect));
                fwrite ($bf,full_tag("INITIALPOINTS",6,false,$que_sub->initailpoints));
                fwrite ($bf,full_tag("POINTSANSWERCORRECT",6,false,$que_sub->pointsanswercorrect));
                fwrite ($bf,full_tag("DESCRIPTION",6,false,$que_sub->description));
                fwrite ($bf,full_tag("MAILED",6,false,$que_sub->mailed));

                //Now we backup workshop assessments autors and answers
                $status = backup_quest_assessments_autors($bf,$preferences,$quest,$que_sub->id);
                $status = backup_quest_answers($bf,$preferences,$quest,$que_sub->id);
                $status = backup_quest_particular_elements($bf, $preferences, $quest, $que_sub->id);
                //End submission
                $status =fwrite ($bf,end_tag("SUBMISSION",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("SUBMISSIONS",4,true));
        }
        return $status;
    }
    //Backup particular submission quest_elements contents
    function backup_quest_particular_elements($bf, $preferences, $quest, $submission) {

    	global $CFG,$DB;

    	$status = true;

    	$quest_elements = $DB->get_records_sql("SELECT * from {quest_elements} a
                                                 WHERE a.questid = ? and a.submissionsid = ?
                                                 ORDER BY a.id", array($quest,$submission));
    	//If there is quest_elements
    	if ($quest_elements) {
    		//Write start tag
    		$status =fwrite ($bf,start_tag("PARTICULAR_ELEMENTS",6,true));

			//Iterate over each element
    		foreach ($quest_elements as $que_part_ele) {

	    		//Start particular element
	            $status =fwrite ($bf,start_tag("PARTICULAR_ELEMENT",7,true));
	            //Print particular element contents
	            fwrite ($bf,full_tag("SUBMISSIONSID",8,false,$que_part_ele->submissionsid));
	            fwrite ($bf,full_tag("ELEMENTNO",8,false,$que_part_ele->elementno));
	            fwrite ($bf,full_tag("DESCRIPTION",8,false,$que_part_ele->description));
	            fwrite ($bf,full_tag("SCALE",8,false,$que_part_ele->scale));
	            fwrite ($bf,full_tag("MAXSCORE",8,false,$que_part_ele->maxscore));
	            fwrite ($bf,full_tag("WEIGHT",8,false,$que_part_ele->weight));

	    		//End assessment autor
	            $status =fwrite ($bf,end_tag("PARTICULAR_ELEMENT",7,true));
    		}
			 //Write end tag
            $status =fwrite ($bf,end_tag("PARTICULAR_ELEMENTS",6,true));
    	}
    	return $status;
    }


    //Backup questp_assessments_autors contents
    function backup_quest_assessments_autors ($bf,$preferences,$quest,$submission) {

        global $CFG;

        $status = true;


        $quest_assessments_autor = $DB->get_records_sql("SELECT * from {quest_assessments_autors} a
                                                 WHERE a.questid = ? and a.submissionid = ?
                                                 ORDER BY a.id", array($quest,$submission));

        //If there is quest_assessments_autors
        if ($quest_assessments_autor) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ASSESSMENTS_AUTOR",6,true));
            //Iterate over each assessment autor
            foreach ($quest_assessments_autor as $que_ass) {
                //Start assessment autor
                $status =fwrite ($bf,start_tag("ASSESSMENT_AUTOR",7,true));
                //Print assessment autor contents
                fwrite ($bf,full_tag("ID",8,false,$que_ass->id));
                fwrite ($bf,full_tag("USERID",8,false,$que_ass->userid));
                fwrite ($bf,full_tag("POINTSMAX",8,false,$que_ass->pointsmax));
                fwrite ($bf,full_tag("POINTS",8,false,$que_ass->points));
                fwrite ($bf,full_tag("DATEASSESSMENT",8,false,$que_ass->dateassessment));
                fwrite ($bf,full_tag("COMMENTSFORTEACHER",8,false,$que_ass->commentsforteacher));
                fwrite ($bf,full_tag("COMMENTSTEACHER",8,false,$que_ass->commentsteacher));
                fwrite ($bf,full_tag("PHASE",8,false,$que_ass->phase));
                fwrite ($bf,full_tag("STATE",8,false,$que_ass->state));
                //Now we backup quest elements assessments autor
                $status = backup_quest_elements_assessments_autor($bf,$preferences,$quest,$que_ass->id);

                //End assessment autor
                $status =fwrite ($bf,end_tag("ASSESSMENT_AUTOR",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ASSESSMENTS_AUTOR",6,true));
        }
        return $status;
    }

    //Backup quest_elements_assessments_autor contents
    function backup_quest_elements_assessments_autor ($bf,$preferences,$quest,$assessmentid) {

        global $CFG,$DB;

        $status = true;


        $quest_elements_assess_autor = $DB->get_records_sql("SELECT * from {quest_items_assessments_autor} c
                                              WHERE c.questid = ? and c.assessmentautorid = ?
                                              ORDER BY c.id", array($quest,$assessmentid));

        //If there is quest_elements_assessments_autor
        if ($quest_elements_assess_autor) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ELEMENTS_ASSESS_AUTOR",8,true));
            //Iterate over each element assessment autor
            foreach ($quest_elements_assess_autor as $que_ele) {
                //Start element assessment autor
                $status =fwrite ($bf,start_tag("ELEMENT_ASSESS_AUTOR",9,true));
                //Print element assessment autor contents
                fwrite ($bf,full_tag("USERID",10,false,$que_ele->userid));
                fwrite ($bf,full_tag("ELEMENTNO",10,false,$que_ele->elementno));
                fwrite ($bf,full_tag("ANSWER",10,false,$que_ele->answer));
                fwrite ($bf,full_tag("COMMENTTEACHER",10,false,$que_ele->commentteacher));
                fwrite ($bf,full_tag("CALIFICATION",10,false,$que_ele->calification));
                fwrite ($bf,full_tag("PHASE",10,false,$que_ele->phase));

                //End element assessment autor
                $status =fwrite ($bf,end_tag("ELEMENT_ASSESS_AUTOR",9,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ELEMENTS_ASSESS_AUTOR",8,true));
        }
        return $status;
    }

    //Backup quest_answers contents
    function backup_quest_answers ($bf,$preferences,$quest,$submission) {

        global $CFG,$DB;

        $status = true;


        $quest_answers = $DB->get_records_sql("SELECT * from {quest_answers} a
                                                 WHERE a.questid = ? and a.submissionid = ?
                                                 ORDER BY a.id",array($quest,$submission));

        //If there is quest_answers
        if ($quest_answers) {

            //Write start tag
            $status =fwrite ($bf,start_tag("ANSWERS",6,true));
            //Iterate over each answer
            foreach ($quest_answers as $que_ans) {
                //Start answer
                $status =fwrite ($bf,start_tag("ANSWER",7,true));
                //Print answer contents
                fwrite ($bf,full_tag("ID",8,false,$que_ans->id));
                fwrite ($bf,full_tag("QUESTID",8,false,$que_ans->questid));
                fwrite ($bf,full_tag("SUBMISSIONID",8,false,$que_ans->submissionid));
                fwrite ($bf,full_tag("USERID",8,false,$que_ans->userid));
                fwrite ($bf,full_tag("POINTSMAX",8,false,$que_ans->pointsmax));

                fwrite ($bf,full_tag("TITLE",8,false,$que_ans->title));
                fwrite ($bf,full_tag("DESCRIPTION",8,false,$que_ans->description));
				fwrite ($bf,full_tag("GRADE",8,false,$que_ans->grade));
                fwrite ($bf,full_tag("DATE",8,false,$que_ans->date));
                fwrite ($bf,full_tag("COMMENTFORTEACHER",8,false,$que_ans->commentforteacher));
                fwrite ($bf,full_tag("PHASE",8,false,$que_ans->phase));
                fwrite ($bf,full_tag("STATE",8,false,$que_ans->state));
                //Now we backup quest assessments
                $status = backup_quest_assessments($bf,$preferences,$quest,$que_ans->id);

                //End answer
                $status =fwrite ($bf,end_tag("ANSWER",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ANSWERS",6,true));
        }
        return $status;
    }

    //Backup quest_assessments contents
    function backup_quest_assessments ($bf,$preferences,$quest,$answer) {

        global $CFG,$DB;

        $status = true;


        $quest_assessments = $DB->get_records_sql("SELECT * from {quest_assessments} a
                                                 WHERE a.questid = ? and a.answerid = ?
                                                 ORDER BY a.id",array($quest,$answer));

        //If there is quest_assessments
        if ($quest_assessments) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ASSESSMENTS",6,true));
            //Iterate over each assessment
            foreach ($quest_assessments as $que_ass) {
                //Start assessment
                $status =fwrite ($bf,start_tag("ASSESSMENT",7,true));
                //Print assessment contents
                fwrite ($bf,full_tag("ID",8,false,$que_ass->id));
                fwrite ($bf,full_tag("USERID",8,false,$que_ass->userid));
                fwrite ($bf,full_tag("TEACHERID",8,false,$que_ass->teacherid));
                fwrite ($bf,full_tag("POINTSTEACHER",8,false,$que_ass->pointsteacher));
                fwrite ($bf,full_tag("POINTSAUTOR",8,false,$que_ass->pointsautor));
                fwrite ($bf,full_tag("POINTSMAX",8,false,$que_ass->pointsmax));
                fwrite ($bf,full_tag("DATEASSESSMENT",8,false,$que_ass->dateassessment));
                fwrite ($bf,full_tag("COMMENTSFORTEACHER",8,false,$que_ass->commentsforteacher));
                fwrite ($bf,full_tag("COMMENTSTEACHER",8,false,$que_ass->commentsteacher));
                fwrite ($bf,full_tag("PHASE",8,false,$que_ass->phase));
                fwrite ($bf,full_tag("STATE",8,false,$que_ass->state));
                //Now we backup quest elements assessments
                $status = backup_quest_elements_assessments($bf,$preferences,$quest,$que_ass->id);

                //End assessment
                $status =fwrite ($bf,end_tag("ASSESSMENT",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ASSESSMENTS",6,true));
        }
        return $status;
    }

    //Backup quest_elements_assessments contents
    function backup_quest_elements_assessments ($bf,$preferences,$quest,$assessmentid) {

        global $CFG,$DB;

        $status = true;


        $quest_elements_assess_autor = $DB->get_records_sql("SELECT * from {quest_elements_assessments} c
                                              WHERE c.questid = ? and c.assessmentid = ?
                                              ORDER BY c.id",array($quest,$assessmentid));

        //If there is quest_elements_assessments
        if ($quest_elements_assess_autor) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ELEMENTS_ASSESS",8,true));
            //Iterate over each element assessment
            foreach ($quest_elements_assess_autor as $que_ele) {
                //Start element assessment
                $status =fwrite ($bf,start_tag("ELEMENT_ASSESS",9,true));
                //Print element assessment    contents
                fwrite ($bf,full_tag("USERID",10,false,$que_ele->userid));
                fwrite ($bf,full_tag("ELEMENTNO",10,false,$que_ele->elementno));
                fwrite ($bf,full_tag("ANSWER",10,false,$que_ele->answer));
                fwrite ($bf,full_tag("COMMENTTEACHER",10,false,$que_ele->commentteacher));
                fwrite ($bf,full_tag("CALIFICATION",10,false,$que_ele->calification));
                fwrite ($bf,full_tag("PHASE",10,false,$que_ele->phase));

                //End element assessment
                $status =fwrite ($bf,end_tag("ELEMENT_ASSESS",9,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ELEMENTS_ASSESS",8,true));
        }
        return $status;
    }
    //Backup quest files because we've selected to backup user info
    //and files are user info's level
    function backup_quest_files($bf,$preferences) {

        global $CFG;

        $status = true;

        //First we check to moddata exists and create it as necessary
        //in temp/backup/$backup_code  dir
        $status = check_and_create_moddata_dir($preferences->backup_unique_code);
        //Now copy the quest dir
        if ($status) {

            if (is_dir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/quest")) {
                $status = backup_copy_file($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/quest",
                                           $CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/quest");
            }

        }

        return $status;

    }

    //Return an array of info (name,value)
    function quest_check_backup_mods($course,$user_data=false,$backup_unique_code) {
        //First the course data
        $info[0][0] = get_string("modulenameplural","quest");
        if ($ids = quest_ids ($course)) {
            $info[0][1] = count($ids);
        } else {
            $info[0][1] = 0;
        }

        //Now, if requested, the user_data
        if ($user_data) {
            $info[1][0] = get_string("submissions","quest");
            if ($ids = quest_submission_ids_by_course ($course)) {
                $info[1][1] = count($ids);
            } else {
                $info[1][1] = 0;
            }
            $info[2][0] = get_string("answers","quest");
            if ($ids = quest_answer_ids_by_course ($course)) {
                $info[2][1] = count($ids);
            } else {
                $info[2][1] = 0;
            }
            $info[3][0] = get_string("elements","quest");
            if ($ids = quest_elements_ids_by_course ($course))  {
            	$info[3][1] = count($ids);
            } else {
            	$info[3][1] = 0;
        	}
        	$info[4][0] = get_string("assessments","quest");
            if ($ids = quest_assessments_ids_by_course ($course))  {
            	$info[4][1] = count($ids);
            } else {
            	$info[4][1] = 0;
        	}
        return $info;
        }
    }

    //Return a content encoded to support interactivities linking. Every module
    //should have its own. They are called automatically from the backup procedure.
    function quest_encode_content_links ($content,$preferences) {

        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        //Link to the list of quests
        $buscar="/(".$base."\/mod\/quest\/index.php\?id\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@QUESTINDEX*$2@$',$content);

        //Link to quest view by moduleid
        $buscar="/(".$base."\/mod\/quest\/view.php\?id\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@QUESTVIEWBYID*$2@$',$result);

        return $result;
    }

    // INTERNAL FUNCTIONS. BASED IN THE MOD STRUCTURE

    //Returns an array of quest id
    function quest_ids ($course) {

        global $CFG,$DB;

        return $DB->get_records_sql ("SELECT q.id, q.course
                                 FROM {quest} q
                                 WHERE q.course = ?",array($course));
    }

    //Returns an array of quest_submissions id
    function quest_submission_ids_by_course ($course) {

        global $CFG,$DB;

        return $DB->get_records_sql ("SELECT s.id , s.questid
                                 FROM {quest_submissions} s,
                                      {quest} q
                                 WHERE q.course = ? AND
                                       s.questid = q.id",array($course));
    }

    function quest_answer_ids_by_course ($course) {

        global $CFG,$DB;

        return $DB->get_records_sql ("SELECT a.id , a.questid
                                 FROM {quest_answers} a,
                                      {quest} q
                                 WHERE q.course = ? AND
                                       a.questid = q.id",array($course));
    }

    function quest_elements_ids_by_course ($course) {

        global $CFG,$DB;

        return $DB->get_records_sql ("SELECT a.id , a.questid
                                 FROM {quest_elements} a,
                                      {quest} q
                                 WHERE q.course = ? AND
                                       a.questid = q.id",array($course));
    }
    function quest_assessments_ids_by_course ($course) {

        global $CFG,$DB;

        return $DB->get_records_sql ("SELECT a.id , a.questid
                                 FROM {quest_assessments} a,
                                      {quest} q
                                 WHERE q.course = ? AND
                                       a.questid = q.id");
    }



?>
