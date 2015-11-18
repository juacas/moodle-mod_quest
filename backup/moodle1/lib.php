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

/**
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 *
 * @package    mod
 * @subpackage quest
 * @copyright  2013 Juan Pablo de Castro <juacas@tel.uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Forum conversion handler
 */
class moodle1_mod_quest_handler extends moodle1_mod_handler {

    /** @var moodle1_file_manager */
    protected $fileman = null;

    /** @var int cmid */
    protected $moduleid = null;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the paths /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST do not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
       $paths =array(
            new convert_path('quest', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST'),
       		new convert_path('elements', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS'),
       		new convert_path('element', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS/ELEMENT'),
       		new convert_path('elements_autor', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS_AUTOR'),
       		new convert_path('element_autor', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS_AUTOR/ELEMENT_AUTOR'),
       		new convert_path('submissions', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS'),
            new convert_path('submission', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION'),
       		new convert_path('particular_elements', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/PARTICULAR_ELEMENTS'),
       		new convert_path('particular_element', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/PARTICULAR_ELEMENTS/PARTICULAR_ELEMENT'),

       		new convert_path('answers', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS'),
       		new convert_path('answer', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER'),
       		new convert_path('assessments', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER/ASSESSMENTS'),
       		new convert_path('assessment', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER/ASSESSMENTS/ASSESSMENT'),
       		new convert_path('elements_assess', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER/ASSESSMENTS/ASSESSMENT/ELEMENTS_ASSESS'),
       		new convert_path('element_assess', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER/ASSESSMENTS/ASSESSMENT/ELEMENTS_ASSESS/ELEMENT_ASSESS'),
       		new convert_path('califications_users', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/CALIFICATIONS_USERS'),
       		new convert_path('calification_user', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/CALIFICATIONS_USERS/CALIFICATION_USER'),
       		new convert_path('califications_teams', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/CALIFICATIONS_TEAMS'),
       		new convert_path('calification_team', '/MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/CALIFICATIONS_TEAMS/CALIFICATION_TEAM'),
        );
       return $paths;
    }

    /*****************************
     * QUEST node
     */
    /**
     * Converts /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST data
     */
    public function process_quest($data)
    {
    	// get the course module id and context id
    	$instanceid     = $data['id'];
    	$cminfo         = $this->get_cminfo($instanceid);
    	$this->moduleid = $cminfo['id'];
    	$contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

    	// get a fresh new file manager for this instance
    	$this->fileman = $this->converter->get_file_manager($contextid, 'mod_quest');

    	// convert course files embedded into the intro
    	$this->fileman->filearea = 'description';
    	$this->fileman->itemid   = 0;
    	$data['description'] = moodle1_converter::migrate_referenced_files($data['description'], $this->fileman);

    	// start writing quest.xml
    	$this->open_xml_writer("activities/quest_{$this->moduleid}/quest.xml");
    	$this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $this->moduleid,
    			'modulename' => 'quest', 'contextid' => $contextid));
    	$this->xmlwriter->begin_tag('quest', array('id' => $instanceid));

    	foreach ($data as $field => $value) {
    		if ($field <> 'id') {
    			$this->xmlwriter->full_tag($field, $value);
    		}
    	}
    	return $data;
    }

    /**
     * This is executed when we reach the closing </MOD> tag of our 'quest' path
     */
    public function on_quest_end() {
    	// finish writing quest.xml

    	$this->xmlwriter->end_tag('quest');
    	$this->xmlwriter->end_tag('activity');
    	$this->close_xml_writer();

    	// write inforef.xml
    	$this->open_xml_writer("activities/quest_{$this->moduleid}/inforef.xml");
    	$this->xmlwriter->begin_tag('inforef');
    	$this->xmlwriter->begin_tag('fileref');
    	foreach ($this->fileman->get_fileids() as $fileid) {
    		$this->write_xml('file', array('id' => $fileid));
    	}
    	$this->xmlwriter->end_tag('fileref');
    	$this->xmlwriter->end_tag('inforef');
    	$this->close_xml_writer();
    }

    /****************************+++
     * SUBMISSION node
     */

 /**
     * This is executed when the parser reaches the <SUBMISSIONS> opening element
     */
    public function on_submissions_start() {
        $this->xmlwriter->begin_tag('challenges');
    }

    function on_submission_end()
    {
    	$this->xmlwriter->end_tag('challenge');

    }
/**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION
     * data available
     */
    public function process_submission($data)
    {
    	$contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

    	// get a fresh new file manager for this instance
    	$this->fileman = $this->converter->get_file_manager($contextid, 'mod_quest');
    	// convert course files embedded into the intro
    	$this->fileman->filearea = 'description';
    	$this->fileman->itemid   = 0;
    	$data['description'] = moodle1_converter::migrate_referenced_files($data['description'], $this->fileman);

       // $this->write_xml('challenge', $data, array('/submission/id'));
        $this->xmlwriter->begin_tag('challenge', array('id' => $data['id']));

        foreach ($data as $field => $value) {
        	if ($field <> 'id') {
        		$this->xmlwriter->full_tag($field, $value);
        	}
        }
    }

    /**
     * This is executed when the parser reaches the closing </submissions> element
     */
    public function on_submissions_end() {
        $this->xmlwriter->end_tag('challenges');
    }


 /***************************************
     * ELEMENTS
     */
    /**
     * This is executed when the parser reaches the <ELEMENTS> opening element
     */
    public function on_elements_start() {
    	$this->xmlwriter->begin_tag('elements');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS/ELEMENT
     * data available
     */
    public function process_element($data)
    {
    	$this->write_xml('element', $data);
    }

    /**
     * This is executed when the parser reaches the closing </elements> element
     */
    public function on_elements_end() {
    	$this->xmlwriter->end_tag('elements');
    }

    /***************************************
     * PARTICULAR ELEMENTS
     */
    /**
     * This is executed when the parser reaches the <PARTICULAR_ELEMENTS> opening element
     */
    public function on_particular_elements_start() {
    	$this->xmlwriter->begin_tag('particular_elements');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS/ELEMENT
     * data available
     */
    public function process_particular_element($data)
    {
    	$this->write_xml('particular_element', $data);
    }

    /**
     * This is executed when the parser reaches the closing </particular_elements> element
     */
    public function on_particular_elements_end() {
    	$this->xmlwriter->end_tag('particular_elements');
    }

    /***************************************
     * ELEMENTS_AUTOR
    */
    /**
     * This is executed when the parser reaches the <ELEMENTS_AUTOR> opening element
     */
    public function on_elements_autor_start()
    {
    	$this->xmlwriter->begin_tag('elements_autor');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/ELEMENTS_AUTOR/ELEMENT_AUTOR
     * data available
     */
    public function process_element_autor($data)
    {
    	$this->write_xml('element_autor', $data);
    }

    /**
     * This is executed when the parser reaches the closing </elements_autor> element
     */
    public function on_elements_autor_end() {
    	$this->xmlwriter->end_tag('elements_autor');
    }

    /****************************+++
     * ANSWERS node
    */

    /**
     * This is executed when the parser reaches the <ANSWERS> opening element
     */
    public function on_answers_start() {
    	$this->xmlwriter->begin_tag('answers');
    }

    function on_answer_end()
    {
    	$this->xmlwriter->end_tag('answer');

    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER
     * data available
     */
    public function process_answer($data)
    {
    	$contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

    	// get a fresh new file manager for this instance
    	$this->fileman = $this->converter->get_file_manager($contextid, 'mod_quest');
    	// convert course files embedded into the intro
    	$this->fileman->filearea = 'description';
    	$this->fileman->itemid   = 0;
    	$data['description'] = moodle1_converter::migrate_referenced_files($data['description'], $this->fileman);

    	// $this->write_xml('challenge', $data, array('/submission/id'));
    	$this->xmlwriter->begin_tag('answer', array('id' => $data['id']));

    	foreach ($data as $field => $value) {
    		if ($field <> 'id') {
    			$this->xmlwriter->full_tag($field, $value);
    		}
    	}
    }

    /**
     * This is executed when the parser reaches the closing </answers> element
     */
    public function on_answers_end() {
    	$this->xmlwriter->end_tag('answers');
    }

    /***************************************
     * ASSESSMENTS
    */
    /**
     * This is executed when the parser reaches the <ASSESSMENTS> opening element
     */
    public function on_assessments_start() {
    	$this->xmlwriter->begin_tag('assessments');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER/ASSESSMENTS
     * data available
     */
    public function process_assessment($data)
    {
    	$this->xmlwriter->begin_tag('assessment', array('id' => $data['id']));

    	foreach ($data as $field => $value) {
    		if ($field <> 'id') {
    			$this->xmlwriter->full_tag($field, $value);
    		}
    	}
    }
    public function on_assessment_end() {
    	$this->xmlwriter->end_tag('assessment');
    }
    /**
     * This is executed when the parser reaches the closing </elements> element
     */
    public function on_assessments_end() {
    	$this->xmlwriter->end_tag('assessments');
    }
    /***************************************
     * ELEMENTS_ASSESS
    */
    /**
     * This is executed when the parser reaches the <ELEMENTS_ASSESS> opening element
     */
    public function on_elements_assess_start() {
    	$this->xmlwriter->begin_tag('elements_assess');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/SUBMISSIONS/SUBMISSION/ANSWERS/ANSWER/ASSESSMENTS/ASSESSMENT/ELEMENTS_ASSESSMENT
     * data available
     */
    public function process_element_assess($data)
    {
    	$this->write_xml('element_assess', $data);
    }

    /**
     * This is executed when the parser reaches the closing </elements_assess> element
     */
    public function on_elements_assess_end() {
    	$this->xmlwriter->end_tag('elements_assess');
    }
    /***************************************
     * CALIFICATIONS_USERS
    */
    /**
     * This is executed when the parser reaches the <CALIFICATIONS_USERS> opening element
     */
    public function on_califications_users_start() {
    	$this->xmlwriter->begin_tag('califications_users');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/CALIFICATION_USERS
     * data available
     */
    public function process_calification_user($data)
    {
    	$this->write_xml('calification_user', $data);
    }

    /**
     * This is executed when the parser reaches the closing </califications_users> element
     */
    public function on_califications_users_end() {
    	$this->xmlwriter->end_tag('califications_users');
    }
    /***************************************
     * CALIFICATIONS_TEAMS
    */
    /**
     * This is executed when the parser reaches the <CALIFICATIONS_TEAMS> opening element
     */
    public function on_califications_teams_start() {
    	$this->xmlwriter->begin_tag('califications_teams');
    }
    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/QUEST/CALIFICATION_TEAMS
     * data available
     */
    public function process_calification_team($data)
    {
    	$this->write_xml('calification_team', $data);
    }

    /**
     * This is executed when the parser reaches the closing </califications_teams> element
     */
    public function on_califications_teams_end() {
    	$this->xmlwriter->end_tag('califications_teams');
    }
}
