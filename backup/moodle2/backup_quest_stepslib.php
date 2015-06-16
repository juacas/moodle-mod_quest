<?php
 
/**
 * Define the complete choice structure for backup, with file and id annotations
 */     
class backup_quest_activity_structure_step extends backup_activity_structure_step {
 
    protected function define_structure() {
 
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');
 
 // Define each element separated
 		$quest = new backup_nested_element('quest', array('id'), 
 			array(
 			'name', 'description', 'nattachments', 'validateassessment','usepassword','password',
 			'maxbytes','datestart','dateend','gradingstrategy','nelements','timemaxquestion',
            'nmaxanswers', 'maxcalification', 'typecalification', 'allowteams',
            'ncomponents', 'phase','format', 'visible', 'tinitial', 'gradingstrategyautor',
 			'nelementsautor','initialpoints','teamporcent','showclasifindividual','showauthoringdetails',
 			'typegrade','permitviewautors'));
 		// Grading Elements for Submissions
        $default_elements = new backup_nested_element('elements');
        $default_element = new backup_nested_element('element', /*array('id')*/null, array(
            'elementno', 'description', 'scale','maxscore','weight'));
        $particular_elements = new backup_nested_element('particular_elements');
        $particular_element = new backup_nested_element('particular_element', /*array('id')*/null, array(
        		'elementno', 'description', 'scale','maxscore','weight'));
        $rubrics = new backup_nested_element('rubrics');
        $rubric = new backup_nested_element('rubric', array('id'), array(
            'submissionsid', 'elementno','rubricno','description'));
 		 // Grading Elements for autors
        $elements_autor = new backup_nested_element('elements_autor');
        $element_autor = new backup_nested_element('element_autor', /*array('id')*/null, array(
            'elementno', 'description', 'scale','maxscore','weight'));
        
        $rubrics_autor = new backup_nested_element('rubrics_autor');
        $rubric_autor = new backup_nested_element('rubric_autor', array('id'), array(
            'elementno','rubricno','description'));
  		// submissions (challenges)
  		
        $challenges = new backup_nested_element('challenges');
        $challenge = new backup_nested_element('challenge',array('id'),array(
        		'userid','numelements','title','timecreated','description','descriptionformat','descriptiontrust',
        		'attachment','points','phase','commentteacherpupil','commentteacherauthor','dateend','nanswers',
        		'nanswerscorrect','state','datestart','pointsmax','dateanswercorrect','initialpoints','pointsanswercorrect',
        		'mailed','maileduser','predictedduration','preceiveddifficulty','evaluated'));
       
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer',array('id'),array(/*'questid',*/'userid','title','description','descriptionformat',
        				'descriptiontrust','attachment','date','pointsmax','grade','commentforteacher','phase','state','permitsubmit','perceiveddifficulty'));
        $assessments = new backup_nested_element('assessments');
        $assessment = new backup_nested_element('assessment',array('id'),array('questid','userid','teacherid','pointsautor','pointsteacher','dateassessment','pointsmax','commentsforteacher','commentsteacher','phase','state'));
        $element_assessments = new backup_nested_element('elements_assess');
        $element_assessment = new backup_nested_element('element_assess', /*array('id')*/null,array('questid','userid','elementno','answer','commentteacher','calification','phase'));
          
        $assessments_autor = new backup_nested_element('assessments_autor');
        $assessment_autor = new backup_nested_element('assessment_autor',array('id'),array('questid','submissionid','userid','points','dateassessment','pointsmax','commentsforteacher','commentsteacher','phase','state'));
        $element_assessments_autor = new backup_nested_element('elements_assess_autor');
        $element_assessment_autor = new backup_nested_element('element_assess_autor', array('id'),array('questid','assessmentautorid','userid','elementno','answer','commentteacher','calification','phase'));
        
        $califications_users = new backup_nested_element('califications_users');
        // teamid is an identification
        $calification_users = new backup_nested_element('calification_user',array('id'),array('userid','teamid','points','nanswers','nanswerassessment','nsubmissions','nsubmissionsassessment','pointssubmission','pointsanswers'));
        $teams =  new backup_nested_element('teams');
        $team = new backup_nested_element('team',array('id'),array('name','ncomponents','currentgroup','phase'));
        $califications_teams = new backup_nested_element('calification_teams');
        $calification_team = new backup_nested_element('calification_team',array(),array('points','nanswers','nanswerassessment','nsubmissions','nsubmissionsassessment','pointssubmission','pointsanswers'));
 // Build the tree
 		$quest->add_child($default_elements);
 		$default_elements->add_child($default_element);
 		$default_element->add_child($rubrics);
 		$rubrics->add_child($rubric);
 		
 		$quest->add_child($elements_autor);
 		$elements_autor->add_child($element_autor);
 		$element_autor->add_child($rubrics_autor);
 		$rubrics_autor->add_child($rubric_autor);
 		
 		$quest->add_child($challenges);
 		$challenges->add_child($challenge);
 		$challenge->add_child($assessments_autor);
 		$challenge->add_child($answers);
 		$challenge->add_child($particular_elements);
 		$particular_elements->add_child($particular_element);
 		
 		$answers->add_child($answer);
 			$answer->add_child($assessments);
 			$assessments->add_child($assessment);
 		$assessment->add_child($element_assessments);
 			$element_assessments->add_child($element_assessment);
 			$assessments_autor->add_child($assessment_autor);
 		
 		
 		$quest->add_child($teams);
 		$teams->add_child($team);
 		$team->add_child($califications_teams);
 		$califications_teams->add_child($calification_team);
 		$quest->add_child($califications_users);
 		$califications_users->add_child($calification_users);
        // Define sources
 		$quest->set_source_table('quest', array('id'=>backup::VAR_ACTIVITYID));
 		// default element has submissionsid=0
 		$default_element->set_source_sql('SELECT * FROM {quest_elements} WHERE questid= ? and submissionsid=0',  array(backup::VAR_PARENTID));
 		$particular_element->set_source_sql('SELECT * FROM {quest_elements} WHERE questid= ? and submissionsid= ?',  array(backup::VAR_ACTIVITYID,backup::VAR_PARENTID));
 		$element_autor->set_source_table('quest_elementsautor',array( 'questid'=>backup::VAR_PARENTID));
		
 		$rubric->set_source_sql('SELECT * FROM {quest_rubrics} WHERE
 								 questid = ? and submissionsid=0 and elementno = ? ORDER BY elementno', array(backup::VAR_ACTIVITYID,'../../elementno'));
 		$rubric_autor->set_source_sql('SELECT * FROM {quest_rubrics_autor} WHERE
 								 questid = ? and elementno = ? ORDER BY elementno', array(backup::VAR_ACTIVITYID,'../../elementno'));
 		if ($userinfo)
 		{
		$challenge->set_source_table('quest_submissions',array('questid'=>backup::VAR_PARENTID));
		$assessment_autor->set_source_table('quest_assessments_autors',array('questid'=>backup::VAR_ACTIVITYID,'submissionid'=>backup::VAR_PARENTID));
 		$answer->set_source_table('quest_answers',array('questid'=>backup::VAR_ACTIVITYID,'submissionid'=>backup::VAR_PARENTID));
 		$assessment->set_source_table('quest_assessments',array('questid'=>backup::VAR_ACTIVITYID,'answerid'=>backup::VAR_PARENTID));
 		$element_assessment->set_source_table('quest_elements_assessments', array('questid'=>backup::VAR_ACTIVITYID,'assessmentid'=>backup::VAR_PARENTID),'elementno');
 		
 		$calification_users->set_source_table('quest_calification_users', array('questid'=>backup::VAR_ACTIVITYID));
 		$team->set_source_table('quest_teams', array('questid'=>backup::VAR_ACTIVITYID),'id');
 		$calification_team->set_source_table('quest_calification_teams', array('questid'=>backup::VAR_ACTIVITYID,'teamid'=>backup::VAR_PARENTID));
 		}
 		
        // Define id annotations
 		$answer->annotate_ids('user', 'userid');
 		$challenge->annotate_ids('user', 'userid');
 		$assessment_autor->annotate_ids('user', 'userid');
 		$assessment->annotate_ids('user', 'userid');
 		$element_assessment->annotate_ids('user', 'userid');
 		$calification_users->annotate_ids('user', 'userid');
 		$calification_users->annotate_ids('team', 'teamid');
 		
        // Define file annotations
        $quest->annotate_files('mod_quest','description',null);
 		$challenge->annotate_files('mod_quest','submission','id');
 		$challenge->annotate_files('mod_quest','attachment','id');
 		$answer->annotate_files('mod_quest','answer','id');
 		$answer->annotate_files('mod_quest','answer_attachment','id');
 		
 		
        // Return the root element (quest), wrapped into standard activity structure
 		return $this->prepare_activity_structure($quest);
    }
}