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
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest */
class backup_quest_activity_structure_step extends backup_questions_activity_structure_step {
    /**
     *
     * {@inheritDoc}
     * @see backup_structure_step::define_structure()
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $quest = new backup_nested_element('quest', ['id'],
                ['name', 'intro', 'introformat', 'nattachments', 'validateassessment', 'usepassword', 'password', 'maxbytes',
                                'datestart', 'dateend', 'gradingstrategy', 'nelements', 'timemaxquestion', 'nmaxanswers',
                                'maxcalification', 'mincalification', 'typecalification', 'allowteams', 'ncomponents', 'phase',
                                'format', 'visible',
                                'tinitial', 'gradingstrategyautor', 'nelementsautor', 'initialpoints', 'teamporcent',
                                'showclasifindividual', 'showauthoringdetails', 'typegrade', 'permitviewautors', 'completionpass',
                                'allowqbankquestions', 'autoexportqbank']);
        // Grading Elements for Submissions.
        $defaultelements = new backup_nested_element('elements');
        $defaultelement = new backup_nested_element('element', null,
                ['elementno', 'description', 'scale', 'maxscore', 'weight']);
        $particularelements = new backup_nested_element('particular_elements');
        $particularelement = new backup_nested_element('particular_element', null,
                ['elementno', 'description', 'scale', 'maxscore', 'weight']);
        // Grading Elements for autors.
        $elementsautor = new backup_nested_element('elements_autor');
        $elementautor = new backup_nested_element('element_autor', null,
                ['elementno', 'description', 'scale', 'maxscore', 'weight']);
        // Submissions (challenges).

        $challenges = new backup_nested_element('challenges');
        $challenge = new backup_nested_element('challenge', ['id'],
                ['userid', 'numelements', 'title', 'timecreated', 'description', 'descriptionformat', 'descriptiontrust',
                                'attachment', 'points', 'phase', 'commentteacherpupil', 'commentteacherauthor',
                                'dateend', 'nanswers',
                                'nanswerscorrect', 'state', 'datestart', 'pointsmax', 'pointsmin', 'dateanswercorrect',
                                'initialpoints',
                                'pointsanswercorrect', 'mailed', 'maileduser', 'predictedduration', 'preceiveddifficulty',
                                'evaluated']);

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'],
                ['userid', 'title', 'description', 'descriptionformat', 'descriptiontrust',
                                'attachment', 'date',
                                'pointsmax', 'grade', 'commentforteacher', 'phase', 'state',
                                'permitsubmit', 'perceiveddifficulty', 'questionusageid']);
        $this->add_question_references($challenge, 'mod_quest', 'challenge_question');
        $assessments = new backup_nested_element('assessments');
        $assessment = new backup_nested_element('assessment', ['id'],
                ['questid', 'userid', 'teacherid', 'pointsautor', 'pointsteacher', 'dateassessment', 'pointsmax',
                                'commentsforteacher', 'commentsteacher', 'phase', 'state']);
        $elementassessments = new backup_nested_element('elements_assess');
        $elementassessment = new backup_nested_element('element_assess', null,
                ['questid', 'userid', 'elementno', 'answer', 'commentteacher', 'calification', 'phase']);

        $assessmentsautor = new backup_nested_element('assessments_autor');
        $assessmentautor = new backup_nested_element('assessment_autor', ['id'],
                ['questid', 'submissionid', 'userid', 'points', 'dateassessment', 'pointsmax', 'commentsforteacher',
                                'commentsteacher', 'phase', 'state']);
        $elementassessmentsautor = new backup_nested_element('elements_assess_autor');
        $elementassessmentautor = new backup_nested_element('element_assess_autor', ['id'],
                ['questid', 'assessmentautorid', 'userid', 'elementno', 'answer', 'commentteacher', 'calification', 'phase']);

        $calificationsusers = new backup_nested_element('califications_users');
        // Teamid is an identification.
        $calificationusers = new backup_nested_element('calification_user', ['id'],
                ['userid', 'teamid', 'points', 'nanswers', 'nanswerassessment', 'nsubmissions', 'nsubmissionsassessment',
                                'pointssubmission', 'pointsanswers']);
        $teams = new backup_nested_element('teams');
        $team = new backup_nested_element('team', ['id'], ['name', 'ncomponents', 'currentgroup', 'phase']);
        $calificationsteams = new backup_nested_element('calification_teams');
        $calificationteam = new backup_nested_element('calification_team', [],
                ['points', 'nanswers', 'nanswerassessment', 'nsubmissions', 'nsubmissionsassessment', 'pointssubmission',
                                'pointsanswers']);
        // Build the tree.
        $quest->add_child($defaultelements);
        $defaultelements->add_child($defaultelement);

        $quest->add_child($elementsautor);
        $elementsautor->add_child($elementautor);

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
        $quest->set_source_table('quest', ['id' => backup::VAR_ACTIVITYID]);
        // ...default element has submissionsid=0.
        $defaultelement->set_source_sql('SELECT * FROM {quest_elements} WHERE questid= ? and submissionsid=0',
                [backup::VAR_PARENTID]);
        $particularelement->set_source_sql('SELECT * FROM {quest_elements} WHERE questid= ? and submissionsid= ?',
                [backup::VAR_ACTIVITYID, backup::VAR_PARENTID]);
        $elementautor->set_source_table('quest_elementsautor', ['questid' => backup::VAR_PARENTID]);

        $challenge->set_source_table('quest_submissions', ['questid' => backup::VAR_PARENTID]);
        if ($userinfo) { // Include challenge data when user information is selected.

            $assessmentautor->set_source_table('quest_assessments_autors',
                    ['questid' => backup::VAR_ACTIVITYID, 'submissionid' => backup::VAR_PARENTID]);
            $answer->set_source_table('quest_answers',
                    ['questid' => backup::VAR_ACTIVITYID, 'submissionid' => backup::VAR_PARENTID]);
            $assessment->set_source_table('quest_assessments',
                    ['questid' => backup::VAR_ACTIVITYID, 'answerid' => backup::VAR_PARENTID]);
            $elementassessment->set_source_table('quest_elements_assessments',
                    ['questid' => backup::VAR_ACTIVITYID, 'assessmentid' => backup::VAR_PARENTID], 'elementno');
            $calificationusers->set_source_table('quest_calification_users', ['questid' => backup::VAR_ACTIVITYID]);
            $team->set_source_table('quest_teams', ['questid' => backup::VAR_ACTIVITYID], 'id');
            $calificationteam->set_source_table('quest_calification_teams',
                    ['questid' => backup::VAR_ACTIVITYID, 'teamid' => backup::VAR_PARENTID]);
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
