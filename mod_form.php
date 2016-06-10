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
 * This file defines de main questournament configuration form
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
 *
 * It uses the standard core Moodle (>1.8) formslib. For
 * more info about them, please visit:
 *
 * http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * The form must provide support for, at least these fields:
 *   - name: text element of 64cc max
 *
 * Also, it's usual to use these fields:
 *   - intro: one htmlarea element to describe the activity
 *            (will be showed in the list of activities of
 *             questournament type (index.php) and in the header
 *             of the questournament main page (view.php).
 *   - introformat: The format used to write the contents
 *             of the intro field. It automatically defaults
 *             to HTML when the htmleditor is used and can be
 *             manually selected if the htmleditor is not used
 *             (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *             See lib/weblib.php Constants and the format_text()
 *             function for more info
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once("$CFG->dirroot/mod/quest/locallib.php");

class mod_quest_mod_form extends moodleform_mod {

	function definition() {   
        global $COURSE;
		$mform    =& $this->_form;
    // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));
    // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('title', 'quest'), array('size'=>'60'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', null, 'required', null, 'client'); //??
    // Adding the description fields.
         $this->standard_intro_elements(get_string('description', 'quest'));
        $mform->addElement('filemanager', 'introattachments',
                            get_string('introattachments', 'assign'),
                            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes) );
        $mform->addHelpButton('introattachments', 'introattachments', 'assign');
	    $ARRAY_NATTACHMENTS = array(0,1,2,3,4,5);
	    $mform->addElement('select', 'nattachments', get_string('numberofattachments', 'quest'), $ARRAY_NATTACHMENTS);
	    $mform->addHelpButton('nattachments', "numberofattachments","quest");//???

	    $mform->addElement('selectyesno', 'validateassessment', get_string('validateassessment', 'quest'));
	    $mform->addHelpButton('validateassessment', "validateassessment","quest");

	    $mform->addElement('selectyesno', 'usepassword', get_string('usepassword', 'quest'));
	    $mform->addHelpButton('usepassword', "usepassword","quest");

	    $mform->addElement('text', 'password', 'Password', array('size'=>'10'));
	    $mform->addHelpButton('password', "password","quest");
	    $mform->setType('password', PARAM_RAW);
	    $sizelist = array("10Kb", "50Kb", "100Kb", "500Kb", "1Mb", "2Mb", "5Mb", "10Mb", "20Mb", "50Mb");
	    $maxsize = get_max_upload_file_size();

	    foreach ($sizelist as $size) {
	    	$sizebytes = get_real_size($size);
	     	if ($sizebytes < $maxsize) {
	    		$filesize[$sizebytes] = $size;
	       	}
	    }
	    $filesize[$maxsize] = display_size($maxsize);

	    ksort($filesize, SORT_NUMERIC);
	    $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'quest'), $filesize);

	    $mform->addElement('date_time_selector', 'datestart', get_string('dateofstart','quest'));
	    $mform->addHelpButton('datestart', 'datestart','quest');
	    $mform->setDefault('datestart',time());
	    $mform->addElement('date_time_selector', 'dateend', get_string('dateofend','quest'));
	    $mform->addHelpButton('dateend', 'dateend','quest');
		$mform->setDefault('dateend', time()+7*24*3600);

	    $mform->addElement('selectyesno', 'allowteams', get_string('allowteams', 'quest'));
	    $mform->addHelpButton('allowteams', 'allowteams','quest');

	    for ($i=2;$i<=20;$i++)
	    {
	    $ARRAY_NCOMPONENTS[$i] = $i;
	    }
	    $mform->addElement('select', 'ncomponents', get_string('ncomponents', 'quest'), $ARRAY_NCOMPONENTS);
	  	$mform->addHelpButton('ncomponents', 'ncomponents','quest');
        
		$mform->addElement('header', 'privacy', get_string('showresultssection', 'quest'));
		$mform->addElement('selectyesno', 'showclasifindividual', get_string('showclasifindividual', 'quest'));
		$mform->addHelpButton('showclasifindividual', "showclasifindividual","quest");
	    $mform->setDefault('showclasifindividual',1);

		$mform->addElement('selectyesno', 'showauthoringdetails', get_string('showauthoringdetails', 'quest'));
		$mform->addHelpButton('showauthoringdetails', "showauthoringdetails","quest");

		$mform->addElement('selectyesno', 'permitviewautors', get_string('permitviewautors', 'quest'));
		$mform->addHelpButton('permitviewautors', "permitviewautors","quest");

		$mform->addElement('header', 'assessmentcharacteristics', get_string('assessmentcharacteristics', 'quest'));

		for($i=1;$i<=20;$i++)
		{
		$ARRAY_NELEMENTS[$i] = $i;
		}
		$mform->addElement('select', 'nelements', get_string('nelements', 'quest'), $ARRAY_NELEMENTS);
		$mform->addHelpButton('nelements', "nelements","quest");
		$mform->addElement('select','gradingstrategy',get_string('gradingstrategy','quest'),array('0'=>get_string('nograde','quest'),'1'=>get_string('accumulative','quest')));
		$mform->setDefault('gradingstrategy', '1');
		$mform->addHelpButton('gradingstrategy', "gradingstrategy","quest");

		$mform->addElement('select', 'nelementsautor', get_string('nelementsautor', 'quest'), $ARRAY_NELEMENTS);
		$mform->addHelpButton('nelementsautor', "nelementsautor","quest");
		$mform->addElement('select','gradingstrategyautor',get_string('gradingstrategyautor','quest'),array('0'=>get_string('nograde','quest'),'1'=>get_string('accumulative','quest')));
		$mform->addHelpButton('gradingstrategyautor', "gradingstrategyautor","quest");

		// Final grading for the activity.
        $QUEST_TYPE_GRADES = array(QUEST_TYPE_GRADE_INDIVIDUAL => get_string('typeindividual', 'quest'),
                                    QUEST_TYPE_GRADE_TEAM => get_string('typeteam', 'quest'));
		$mform->addElement('header', 'gradingcharacteristics', get_string('gradingcharacteristics', 'quest'));
		$mform->addElement('select', 'typegrade', get_string('typegrade', 'quest'), $QUEST_TYPE_GRADES);
		$mform->addHelpButton('typegrade', "typegrade","quest");

		for($i=0;$i<=91;$i++)
		{
		$ARRAY_DAYS[$i] = $i;
		}
		$mform->addElement('select', 'timemaxquestion', get_string('timemaxoflife', 'quest'), $ARRAY_DAYS);
		$mform->addHelpButton('timemaxquestion', "timemaxquestion","quest");
		$mform->setDefault('timemaxquestion',14);

		for($i=1;$i<=300;$i++)
		{
		$ARRAY_MAXNUMANSWERS[$i] = $i;
		}
		$mform->addElement('select', 'nmaxanswers', get_string('numbermaxofanswers', 'quest'), $ARRAY_MAXNUMANSWERS);
		$mform->addHelpButton('nmaxanswers', "nmaxanswers","quest");
		$mform->setDefault('nmaxanswers',25);
        
        $QUEST_TYPE_POINTS = array(0 => get_string('linear', 'quest'));
        // Currently disabled: 1 => get_string('exponential', 'quest') );
		$mform->addElement('select', 'typecalification', get_string('typecalification', 'quest'), $QUEST_TYPE_POINTS);
		$mform->addHelpButton('typecalification', "typecalification","quest");

        // Points from 1 to 300.
		$ARRAY_POINTS = array_combine(range(1,300),range(1,300));

		$mform->addElement('select', 'maxcalification', get_string('maxcalification', 'quest'), $ARRAY_POINTS);
		$mform->addHelpButton('maxcalification', "maxcalification","quest");
		$mform->setDefault('maxcalification',100);

		$mform->addElement('select', 'initialpoints', get_string('initialpoints', 'quest'), $ARRAY_POINTS);
		$mform->addHelpButton('initialpoints', "initialpoints","quest");
		$mform->setDefault('initialpoints',10);

		$mform->addElement('select', 'tinitial', get_string('tinitial', 'quest'), $ARRAY_DAYS);
		$mform->addHelpButton('tinitial',"tinitial","quest");
		$mform->setDefault('tinitial', 3);

		for($i=0;$i<=100;$i++)
		{
				$ARRAY_TEAMPORCENT[$i] = $i;
		}
		$mform->addElement('select', 'teamporcent', get_string('teamporcent', 'quest'), $ARRAY_TEAMPORCENT);
		$mform->addHelpButton('teamporcent', "teamporcent","quest");
		$mform->setDefault('teamporcent',25);


/* Mod_cluster support */
/*
		if ($clusterers_mods=get_all_instances_in_courses("clusterer",array($COURSE->id=>$COURSE)))// get clusterer instances available in the course
		{
		$mform->addElement('header', 'general', get_string('clusterer', 'questournament'));
		$mform->addElement('selectyesno', 'clustererleagues', get_string('createligasfromclusterer', 'questournament'));
		$mform->setHelpButton('clustererleagues', array("clustererleagues", get_string("createligasfromclusterer","questournament"), "questournament"));
		$mform->setDefault('clustererleagues',$CFG->questournament_clustererleagues);
		$mform->disabledIf('clustererleagues', 'allowteams','eq', 1);

		$ARRAY_CLUSTERERS=array();
		foreach($clusterers_mods as $clusterer_mod)
		{
			$modName="$clusterer_mod->name :";
			if ($clusterers= $DB->get_records("clusterer_clusterers","moduleinstanceid",$clusterer_mod->id))
			foreach ($clusterers as $clusterer)
			{
				$clustererName="$clusterer->name :";
				if ($clusterer_instances = $DB->get_records("clusterer_clusterer_instances","clustererid",$clusterer->id))
				{
					foreach ($clusterer_instances as $clusterer_instance)
					{
						$date = date("d\.m\.y => g\:i\:s",$clusterer_instance->version);
						$ARRAY_CLUSTERERS[$clusterer_instance->id] = "$modName  $clustererName version: $date";
						$clustererName="&nbsp;".str_repeat("-", strlen($clustererName)).">"; // clear the name for a list more tidy
					}
					$modName="-".str_repeat("-", strlen($modName)).">";// clear the name for a list more tidy
				}
			}
		}

		$mform->addElement('select', 'clustererid', get_string('selectclusterer', 'questournament'), $ARRAY_CLUSTERERS);
		$mform->setHelpButton('clustererid', array("clustererid", get_string("selectclusterer","questournament"), "questournament"));
		$mform->disabledIf('clustererid', 'allowteams','eq', 1);

		?>

		<script>
		function pasarvariable()
		{
			var valor=document.forms[0].clustererid.value;
			var opciones="toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1100, height=800, top=85, left=140";

			window.open("../mod/questournament/popupviewcluster.php?cid="+valor+"","",opciones);

		}
		</script>

		<?php

		$strshowselectedcluster = get_string("showselectedcluster","questournament");
		$mform->addElement('html','<br/><center><a onClick="javascript:pasarvariable()">'.$strshowselectedcluster.'</a>');

		$mform->addElement('selectyesno', 'visibleleagues', get_string('visibleleagues', 'questournament'));
		$mform->setHelpButton('visibleleagues', array("visibleleagues", get_string("visibleleagues","questournament"), "questournament"));
		$mform->setDefault('visibleleagues',$CFG->questournament_visibleleagues);
		$mform->disabledIf('visibleleagues', 'allowteams','eq', 1);

		$mform->addElement('selectyesno', 'anonymousleague', get_string('anonymousleague', 'questournament'));
		$mform->setHelpButton('anonymousleague', array("anonymousleague", get_string("anonymousleague","questournament"), "questournament"));
		$mform->setDefault('anonymousleague',$CFG->questournament_anonymousleague);
		$mform->disabledIf('anonymousleague', 'allowteams','eq', 1);

		}
		else
		{
			$ARRAY_CLUSTERERS = null;
			$mform->addElement('html',get_string('clustererModuleNotFound','questournament'));
			$mform->addElement('hidden', 'clustererleagues', 0);
			$mform->addElement('hidden', 'visibleleagues', 1);
			$mform->addElement('hidden', 'anonymousleague', 1);

		}

*/
		/**********************CLUSTERER END***************************************************************************/

        // Add standard elements, common to all modules.
		$this->standard_coursemodule_elements();
        $this->add_action_buttons();
	}
	function validation($data,$files)
	{
		$errors = parent::validation($data,$files);

        // Check open and close times are consistent.
        if ($data['datestart'] != 0 && $data['dateend'] != 0 && $data['dateend'] < $data['datestart']) {
            $errors['dateend'] = get_string('closebeforeopen', 'quest');
        }

        if (count($errors) == 0) {
            return true;
        } else {
            return $errors;
        }
	}
}

?>
