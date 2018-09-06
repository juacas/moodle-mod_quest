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
 * Structure step to restore one quest activity
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
class restore_quest_activity_structure_step extends restore_activity_structure_step {
    /**
     *
     * {@inheritDoc}
     * @see restore_structure_step::define_structure()
     */
    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('quest', '/activity/quest');
        $paths[] = new restore_path_element('quest_element', '/activity/quest/elements/element');
        $paths[] = new restore_path_element('quest_rubric', '/activity/quest/elements/element/rubrics/rubric');
        $paths[] = new restore_path_element('quest_element_autor', '/activity/quest/elements_autor/element_autor');
        $paths[] = new restore_path_element('quest_rubric_autor', '/activity/quest/elements_autor/element_autor/rubrics/rubric');
        $paths[] = new restore_path_element('quest_challenge', '/activity/quest/challenges/challenge');
        $paths[] = new restore_path_element('quest_particular_element',
                '/activity/quest/challenges/challenge/particular_elements/particular_element');

        if ($userinfo) {
            $paths[] = new restore_path_element('quest_answer', '/activity/quest/challenges/challenge/answers/answer');
            $paths[] = new restore_path_element('quest_assessment',
                    '/activity/quest/challenges/challenge/answers/answer/assessments/assessment');
            $paths[] = new restore_path_element('quest_assessment_autor',
                    '/activity/quest/challenges/challenge/assessments_autor/assessment_autor');
            $paths[] = new restore_path_element('quest_element_assess',
                    '/activity/quest/challenges/challenges/elements_assess/elements_assess');
            $paths[] = new restore_path_element('quest_team', '/activity/quest/teams/team');
            $paths[] = new restore_path_element('quest_calification_user', '/activity/quest/califications_users/calification_user');
            $paths[] = new restore_path_element('quest_calification_team',
                    '/activity/quest/teams/team/califications_teams/calification_team');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->datestart = $this->apply_date_offset($data->datestart);
        $data->dateend = $this->apply_date_offset($data->dateend);
        if (isset($data->description)) { // Old-version backup.
            $data->intro = $data->description;
            $data->introformat = 0;
        }
        // ...insert the quest record.
        $newitemid = $DB->insert_record('quest', $data);
        // ...immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_element($data) {
        global $DB;

        $data = (object) $data;
        $data->questid = $this->get_new_parentid('quest');
        $newitemid = $DB->insert_record('quest_elements', $data);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_particular_element($data) {
        global $DB;

        $data = (object) $data;
        $data->questid = $this->get_new_parentid('quest');
        $data->submissionsid = $this->get_new_parentid('quest_challenge');

        $newitemid = $DB->insert_record('quest_elements', $data);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_element_autor($data) {
        global $DB;

        $data = (object) $data;
        $data->questid = $this->get_new_parentid('quest');

        $newitemid = $DB->insert_record('quest_elementsautor', $data);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_rubric_autor($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->questid = $this->get_new_parentid('quest');
        $data->submissionsid = $this->get_mappingid('quest_challenge', $data->submissionsid);

        $newitemid = $DB->insert_record('quest_rubrics_autor', $data);
        $this->set_mapping('quest_rubric_autor', $oldid, $newitemid);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_rubric($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->questid = $this->get_new_parentid('quest');

        $newitemid = $DB->insert_record('quest_rubrics_autor', $data);
        $this->set_mapping('quest_rubric_autor', $oldid, $newitemid);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_challenge($data) {
        global $DB, $USER;

        $data = (object) $data;
        $oldid = $data->id;

        $data->questid = $this->get_new_parentid('quest');
        // If userid==0 assign it to the user that is restoring the course or unknown user?
        // unknown user may generate incongruences if group mode is enabled.
        $data->userid = $this->get_mappingid('user', $data->userid, $USER->id);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->dateend = $this->apply_date_offset($data->dateend);
        $data->datestart = $this->apply_date_offset($data->datestart);
        $data->dateanswercorrect = $this->apply_date_offset($data->dateanswercorrect);
        // Answers' info are part of the user information that may be ignored...
        $userinfo = $this->get_setting_value('userinfo');
        if (!$userinfo) {
            $data->dateanswercorrect = 0;
            $data->pointsanswercorrect = 0;
            $data->nanswers = 0;
            $data->nanswerscorrect = 0;
            $data->maileduser = 0;
        }
        if ($data->initialpoints == null) {
            $data->initialpoints = 0; // TODO JPC circunvents a bug with legacy backup.
        }
        $newitemid = $DB->insert_record('quest_submissions', $data);
        $this->set_mapping('quest_challenge', $oldid, $newitemid, true);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_element_assess($data) {
        global $DB;

        $data = (object) $data;
        $data->questid = $this->get_new_parentid('quest');
        $data->assessmentid = $this->get_mappingid('quest_assessment', $data->assessmentid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('quest_elements_assessments', $data);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_team($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->questid = $this->get_new_parentid('quest');

        $newitemid = $DB->insert_record('quest_teams', $data);

        $this->set_mapping('quest_team', $oldid, $newitemid);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_answer($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->questid = $this->get_new_parentid('quest');
        $data->submissionid = $this->get_new_parentid('quest_challenge');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->date = $this->apply_date_offset($data->date);

        $newitemid = $DB->insert_record('quest_answers', $data);

        $this->set_mapping('quest_answer', $oldid, $newitemid, true);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_calification_user($data) {
        global $DB;

        $data = (object) $data;

        $data->questid = $this->get_new_parentid('quest');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teamid = $this->get_mappingid('quest_team', $data->teamid);

        $newitemid = $DB->insert_record('quest_calification_users', $data);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_calification_team($data) {
        global $DB;

        $data = (object) $data;
        $data->questid = $this->get_new_parentid('quest');
        $data->teamid = $this->get_new_parentid('quest_team', $data->teamid);

        $newitemid = $DB->insert_record('quest_calification_teams', $data);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_assessment($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->questid = $this->get_new_parentid('quest');
        $data->answerid = $this->get_new_parentid('quest_answer');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacherid = $this->get_mappingid('user', $data->teacherid);

        $data->dateassessment = $this->apply_date_offset($data->dateassessment);

        $newitemid = $DB->insert_record('quest_assessments', $data);

        $this->set_mapping('quest_assessment', $oldid, $newitemid);
    }
    /**
     * Process data for this level of the backup.
     * @param \stdClass $data
     */
    protected function process_quest_assessment_autor($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->questid = $this->get_new_parentid('quest');
        $data->submissionid = $this->get_new_parentid('quest_challenge');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $data->dateassessment = $this->apply_date_offset($data->dateassessment);

        $newitemid = $DB->insert_record('quest_assessments', $data);

        $this->set_mapping('quest_assessment', $oldid, $newitemid);
    }
    /**
     *
     * {@inheritDoc}
     * @see restore_structure_step::after_execute()
     */
    protected function after_execute() {
        // Add quest related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_quest', 'intro', null);
        $this->add_related_files('mod_quest', 'introattachment', null);
        $this->add_related_files('mod_quest', 'submission', 'quest_challenge');
        $this->add_related_files('mod_quest', 'attachment', 'quest_challenge');
        $this->add_related_files('mod_quest', 'answer', 'quest_answer');
        $this->add_related_files('mod_quest', 'answer_attachment', 'quest_answer');
    }
}