<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL') || die();
/** Backup Questournament module
 *
 * Define the complete choice structure for backup, with file and id annotations
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest */
class backup_quest_activity_structure_step extends backup_activity_structure_step {
    /**
     *
     * {@inheritDoc}
     * @see backup_structure_step::define_structure()
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $quest = new backup_nested_element('quest', array('id'),
                array('name', 'intro', 'introformat', 'nattachments', 'validateassessment', 'usepassword', 'password', 'maxbytes',
                                'datestart', 'dateend', 'gradingstrategy', 'nelements', 'timemaxquestion', 'nmaxanswers',
                                'maxcalification', 'mincalification', 'typecalification', 'allowteams', 'ncomponents', 'phase', 'format', 'visible',
                                'tinitial', 'gradingstrategyautor', 'nelementsautor', 'initialpoints', 'teamporcent',
                                'showclasifindividual', 'showauthoringdetails', 'typegrade', 'permitviewautors', 'completionpass'));
        // Grading Elements for Submissions.
        $defaultelements = new backup_nested_element('elements');
        $defaultelement = new backup_nested_element('element', null,
                array('elementno', 'description', 'scale', 'maxscore', 'weight'));
        $particularelements = new backup_nested_element('particular_elements');
        $particularelement = new backup_nested_element('particular_element', null,
                array('elementno', 'description', 'scale', 'maxscore', 'weight'));
        $rubrics = new backup_nested_element('rubrics');
        $rubric = new backup_nested_element('rubric', array('id'), array('submissionsid', 'elementno', 'rubricno', 'description'));
        // Grading Elements for autors.
        $elementsautor = new backup_nested_element('elements_autor');
        $elementautor = new backup_nested_element('element_autor', null,
                array('elementno', 'description', 'scale', 'maxscore', 'weight'));

        $rubricsautor = new backup_nested_element('rubrics_autor');
        $rubricautor = new backup_nested_element('rubric_autor', array('id'), array('elementno', 'rubricno', 'description'));
        // Submissions (challenges).

        $challenges = new backup_nested_element('challenges');
        $challenge = new backup_nested_element('challenge', array('id'),
                array('userid', 'numelements', 'title', 'timecreated', 'description', 'descriptionformat', 'descriptiontrust',
                                'attachment', 'points', 'phase', 'commentteacherpupil', 'commentteacherauthor',
                                'dateend', 'nanswers',
                                'nanswerscorrect', 'state', 'datestart', 'pointsmax', 'pointsmin', 'dateanswercorrect', 'initialpoints',
                                'pointsanswercorrect', 'mailed', 'maileduser', 'predictedduration', 'preceiveddifficulty',
                                'evaluated'));

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', array('id'),
                array('userid', 'title', 'description', 'descriptionformat', 'descriptiontrust',
                                'attachment', 'date',
                                'pointsmax', 'grade', 'commentforteacher', 'phase', 'state',
                                'permitsubmit', 'perceiveddifficulty'));
        $assessments = new backup_nested_element('assessments');
        $assessment = new backup_nested_element('assessment', array('id'),
                array('questid', 'userid', 'teacherid', 'pointsautor', 'pointsteacher', 'dateassessment', 'pointsmax',
                                'commentsforteacher', 'commentsteacher', 'phase', 'state'));
        $elementassessments = new backup_nested_element('elements_assess');
        $elementassessment = new backup_nested_element('element_assess', null,
                array('questid', 'userid', 'elementno', 'answer', 'commentteacher', 'calification', 'phase'));

        $assessmentsautor = new backup_nested_element('assessments_autor');
        $assessmentautor = new backup_nested_element('assessment_autor', array('id'),
                array('questid', 'submissionid', 'userid', 'points', 'dateassessment', 'pointsmax', 'commentsforteacher',
                                'commentsteacher', 'phase', 'state'));
        $elementassessmentsautor = new backup_nested_element('elements_assess_autor');
        $elementassessmentautor = new backup_nested_element('element_assess_autor', array('id'),
                array('questid', 'assessmentautorid', 'userid', 'elementno', 'answer', 'commentteacher', 'calification', 'phase'));

        $calificationsusers = new backup_nested_element('califications_users');
        // Teamid is an identification.
        $calificationusers = new backup_nested_element('calification_user', array('id'),
                array('userid', 'teamid', 'points', 'nanswers', 'nanswerassessment', 'nsubmissions', 'nsubmissionsassessment',
                                'pointssubmission', 'pointsanswers'));
        $teams = new backup_nested_element('teams');
        $team = new backup_nested_element('team', array('id'), array('name', 'ncomponents', 'currentgroup', 'phase'));
        $calificationsteams = new backup_nested_element('calification_teams');
        $calificationteam = new backup_nested_element('calification_team', array(),
                array('points', 'nanswers', 'nanswerassessment', 'nsubmissions', 'nsubmissionsassessment', 'pointssubmission',
                                'pointsanswers'));
        // Build the tree.
        $quest->add_child($defaultelements);
        $defaultelements->add_child($defaultelement);
        $defaultelement->add_child($rubrics);
        $rubrics->add_child($rubric);

        $quest->add_child($elementsautor);
        $elementsautor->add_child($elementautor);
        $elementautor->add_child($rubricsautor);
        $rubricsautor->add_child($rubricautor);

        $quest->add_child($challenges);
        $challenges->add_child($challenge);
        $challenge->add_child($assessmentsautor);
        $challenge->add_child($answers);
        $challenge->add_child($particularelements);
        $particularelements->add_child($particularelement);

        $answers->add_child($answer);
        $answer->add_child($assessments);
        $assessments->add_child($assessment);
        $assessment->add_child($elementassessments);
        $elementassessments->add_child($elementassessment);
        $assessmentsautor->add_child($assessmentautor);

        $quest->add_child($teams);
        $teams->add_child($team);
        $team->add_child($calificationsteams);
        $calificationsteams->add_child($calificationteam);
        $quest->add_child($calificationsusers);
        $calificationsusers->add_child($calificationusers);
        // Define sources.
        $quest->set_source_table('quest', array('id' => backup::VAR_ACTIVITYID));
        // ...default element has submissionsid=0.
        $defaultelement->set_source_sql('SELECT * FROM {quest_elements} WHERE questid= ? and submissionsid=0',
                array(backup::VAR_PARENTID));
        $particularelement->set_source_sql('SELECT * FROM {quest_elements} WHERE questid= ? and submissionsid= ?',
                array(backup::VAR_ACTIVITYID, backup::VAR_PARENTID));
        $elementautor->set_source_table('quest_elementsautor', array('questid' => backup::VAR_PARENTID));

        $rubric->set_source_sql(
                'SELECT * FROM {quest_rubrics} WHERE questid = ? and submissionsid=0 and elementno = ? ORDER BY elementno',
                array(backup::VAR_ACTIVITYID, '../../elementno'));
        $rubricautor->set_source_sql(
                'SELECT * FROM {quest_rubrics_autor} WHERE questid = ? and elementno = ? ORDER BY elementno',
                array(backup::VAR_ACTIVITYID, '../../elementno'));

        $challenge->set_source_table('quest_submissions', array('questid' => backup::VAR_PARENTID));
        if ($userinfo) { // TODO con userinfo copiar también los challenges.

            $assessmentautor->set_source_table('quest_assessments_autors',
                    array('questid' => backup::VAR_ACTIVITYID, 'submissionid' => backup::VAR_PARENTID));
            $answer->set_source_table('quest_answers',
                    array('questid' => backup::VAR_ACTIVITYID, 'submissionid' => backup::VAR_PARENTID));
            $assessment->set_source_table('quest_assessments',
                    array('questid' => backup::VAR_ACTIVITYID, 'answerid' => backup::VAR_PARENTID));
            $elementassessment->set_source_table('quest_elements_assessments',
                    array('questid' => backup::VAR_ACTIVITYID, 'assessmentid' => backup::VAR_PARENTID), 'elementno');
            $calificationusers->set_source_table('quest_calification_users', array('questid' => backup::VAR_ACTIVITYID));
            $team->set_source_table('quest_teams', array('questid' => backup::VAR_ACTIVITYID), 'id');
            $calificationteam->set_source_table('quest_calification_teams',
                    array('questid' => backup::VAR_ACTIVITYID, 'teamid' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $answer->annotate_ids('user', 'userid');
        $challenge->annotate_ids('user', 'userid');
        $assessmentautor->annotate_ids('user', 'userid');
        $assessment->annotate_ids('user', 'userid');
        $elementassessment->annotate_ids('user', 'userid');
        $calificationusers->annotate_ids('user', 'userid');
        $calificationusers->annotate_ids('team', 'teamid');

        // Define file annotations.
        $quest->annotate_files('mod_quest', 'intro', null);
        $quest->annotate_files('mod_quest', 'introattachment', null);
        $challenge->annotate_files('mod_quest', 'submission', 'id');
        $challenge->annotate_files('mod_quest', 'attachment', 'id');
        $answer->annotate_files('mod_quest', 'answer', 'id');
        $answer->annotate_files('mod_quest', 'answer_attachment', 'id');

        // Return the root element (quest), wrapped into standard activity structure.
        return $this->prepare_activity_structure($quest);
    }
}