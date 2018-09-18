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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/** This file defines de main questournament configuration form
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
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot. "/mod/quest/locallib.php");
/**
 *
 *          It uses the standard core Moodle (>1.8) formslib. For
 *          more info about them, please visit:
 *
 *          http://docs.moodle.org/en/Development:lib/formslib.php
 *
 *          The form must provide support for, at least these fields:
 *          - name: text element of 64cc max
 *
 *          Also, it's usual to use these fields:
 *          - intro: one htmlarea element to describe the activity
 *          (will be showed in the list of activities of
 *          questournament type (index.php) and in the header
 *          of the questournament main page (view.php).
 *          - introformat: The format used to write the contents
 *          of the intro field. It automatically defaults
 *          to HTML when the htmleditor is used and can be
 *          manually selected if the htmleditor is not used
 *          (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *          See lib/weblib.php Constants and the format_text()
 *          function for more info
 * @author juacas
 *
 */
class mod_quest_mod_form extends moodleform_mod {
    /**
     *
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        global $COURSE;
        $mform = & $this->_form;
        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('title', 'quest'), array('size' => '60'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        // Adding the description fields.
        if (method_exists($this, 'standard_intro_elements')) {
            $this->standard_intro_elements(get_string('description', 'quest'));
        } else {
            $this->add_intro_editor(true, get_string('description', 'quest'));
        }
        $mform->addElement('filemanager', 'introattachments', get_string('introattachments', 'quest'), null,
                array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes));
        $mform->addHelpButton('introattachments', 'introattachments', 'quest');
        $arraynattachments = array(0, 1, 2, 3, 4, 5);
        $mform->addElement('select', 'nattachments', get_string('numberofattachments', 'quest'), $arraynattachments);
        $mform->addHelpButton('nattachments', "numberofattachments", "quest");

        $mform->addElement('selectyesno', 'validateassessment', get_string('validateassessment', 'quest'));
        $mform->addHelpButton('validateassessment', "validateassessment", "quest");

        $mform->addElement('selectyesno', 'usepassword', get_string('usepassword', 'quest'));
        $mform->addHelpButton('usepassword', "usepassword", "quest");

        $mform->addElement('text', 'password', get_string('password'), array('size' => '10'));
        $mform->addHelpButton('password', "password", "quest");
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

        $mform->addElement('date_time_selector', 'datestart', get_string('dateofstart', 'quest'));
        $mform->addHelpButton('datestart', 'datestart', 'quest');
        $mform->setDefault('datestart', time());
        $mform->addElement('date_time_selector', 'dateend', get_string('dateofend', 'quest'));
        $mform->addHelpButton('dateend', 'dateend', 'quest');
        $mform->setDefault('dateend', time() + 7 * 24 * 3600);

        $mform->addElement('selectyesno', 'allowteams', get_string('allowteams', 'quest'));
        $mform->addHelpButton('allowteams', 'allowteams', 'quest');

        for ($i = 2; $i <= 20; $i++) {
            $arrayncomponents[$i] = $i;
        }
        $mform->addElement('select', 'ncomponents', get_string('ncomponents', 'quest'), $arrayncomponents);
        $mform->addHelpButton('ncomponents', 'ncomponents', 'quest');

        $mform->addElement('header', 'privacy', get_string('showresultssection', 'quest'));
        $mform->addElement('selectyesno', 'showclasifindividual', get_string('showclasifindividual', 'quest'));
        $mform->addHelpButton('showclasifindividual', "showclasifindividual", "quest");
        $mform->setDefault('showclasifindividual', 1);

        $mform->addElement('selectyesno', 'showauthoringdetails', get_string('showauthoringdetails', 'quest'));
        $mform->addHelpButton('showauthoringdetails', "showauthoringdetails", "quest");

        $mform->addElement('selectyesno', 'permitviewautors', get_string('permitviewautors', 'quest'));
        $mform->addHelpButton('permitviewautors', "permitviewautors", "quest");

        $mform->addElement('header', 'assessmentcharacteristics', get_string('assessmentcharacteristics', 'quest'));

        for ($i = 1; $i <= 20; $i++) {
            $arraynelements[$i] = $i;
        }
        $mform->addElement('select', 'nelements', get_string('nelements', 'quest'), $arraynelements);
        $mform->addHelpButton('nelements', "nelements", "quest");
        $mform->addElement('select', 'gradingstrategy', get_string('gradingstrategy', 'quest'),
                array('0' => get_string('nograde', 'quest'), '1' => get_string('accumulative', 'quest')));
        $mform->setDefault('gradingstrategy', '1');
        $mform->addHelpButton('gradingstrategy', "gradingstrategy", "quest");

        $mform->addElement('select', 'nelementsautor', get_string('nelementsautor', 'quest'), $arraynelements);
        $mform->addHelpButton('nelementsautor', "nelementsautor", "quest");
        $mform->addElement('select', 'gradingstrategyautor', get_string('gradingstrategyautor', 'quest'),
                array('0' => get_string('nograde', 'quest'), '1' => get_string('accumulative', 'quest')));
        $mform->addHelpButton('gradingstrategyautor', "gradingstrategyautor", "quest");

        // Final grading for the activity.
        $questypegrades = array(QUEST_TYPE_GRADE_INDIVIDUAL => get_string('typeindividual', 'quest'),
                        QUEST_TYPE_GRADE_TEAM => get_string('typeteam', 'quest'));
        $mform->addElement('header', 'gradingcharacteristics', get_string('gradingcharacteristics', 'quest'));
        $mform->addElement('select', 'typegrade', get_string('typegrade', 'quest'), $questypegrades);
        $mform->addHelpButton('typegrade', "typegrade", "quest");

        for ($i = 0; $i <= 91; $i++) {
            $arraydays[$i] = $i;
        }
        $mform->addElement('select', 'timemaxquestion', get_string('timemaxoflife', 'quest'), $arraydays);
        $mform->addHelpButton('timemaxquestion', "timemaxquestion", "quest");
        $mform->setDefault('timemaxquestion', 14);

        for ($i = 1; $i <= 300; $i++) {
            $arraymaxnumanswers[$i] = $i;
        }
        $mform->addElement('select', 'nmaxanswers', get_string('numbermaxofanswers', 'quest'), $arraymaxnumanswers);
        $mform->addHelpButton('nmaxanswers', "nmaxanswers", "quest");
        $mform->setDefault('nmaxanswers', 25);

        $questtypepoints = array(0 => get_string('linear', 'quest'));
        // Currently disabled: 1 => get_string('exponential', 'quest') ). This scoring pattern is not enabled by now.
        $mform->addElement('select', 'typecalification', get_string('typecalification', 'quest'), $questtypepoints);
        $mform->addHelpButton('typecalification', "typecalification", "quest");

        // Points from 0 to 300.
        $arraypoints = array_combine(range(0, 300), range(0, 300));

        $mform->addElement('select', 'maxcalification', get_string('maxcalification', 'quest'), $arraypoints);
        $mform->addHelpButton('maxcalification', "maxcalification", "quest");
        $mform->setDefault('maxcalification', 100);

        $mform->addElement('select', 'mincalification', get_string('mincalification', 'quest'), $arraypoints);
        $mform->addHelpButton('mincalification', "mincalification", "quest");
        $mform->setDefault('mincalification', 0);

        $mform->addElement('select', 'initialpoints', get_string('initialpoints', 'quest'), $arraypoints);
        $mform->addHelpButton('initialpoints', "initialpoints", "quest");
        $mform->setDefault('initialpoints', 10);

        $arrayinitialtime = [];
        $arrayinitialtime['0'] = get_string('none');

        $arrayinitialtime[strval(1.0 / 24)] = '1 ' . get_string('hour');
        $arrayinitialtime[strval(2.0 / 24)] = '2 ' . get_string('hours');
        $arrayinitialtime[strval(3.0 / 24)] = '3 ' . get_string('hours');
        $arrayinitialtime[strval(4.0 / 24)] = '4 ' . get_string('hours');
        $arrayinitialtime[strval(5.0 / 24)] = '5 ' . get_string('hours');
        $arrayinitialtime[strval(6.0 / 24)] = '6 ' . get_string('hours');
        $arrayinitialtime[strval(12.0 / 24)] = '12 ' . get_string('hours');
        $arrayinitialtime['1'] = '1 ' . get_string('day');
        for ($i = 2; $i <= 90; $i++) {
            $arrayinitialtime[$i] = $i . ' ' . get_string('days');
        }
        $mform->addElement('select', 'tinitial', get_string('tinitial', 'quest'), $arrayinitialtime);
        $mform->addHelpButton('tinitial', "tinitial", "quest");
        $mform->setDefault('tinitial', 0);

        for ($i = 0; $i <= 100; $i++) {
            $arrayteampercent[$i] = $i;
        }
        $mform->addElement('select', 'teamporcent', get_string('teamporcent', 'quest'), $arrayteampercent);
        $mform->addHelpButton('teamporcent', "teamporcent", "quest");
        $mform->setDefault('teamporcent', 25);

        // Mod_cluster support disabled by now.
        if (false) {
            // Get clusterer instances available in the course.
            if ($clusterersmods = get_all_instances_in_courses("clusterer", array($COURSE->id => $COURSE))) {
                $mform->addElement('header', 'general', get_string('clusterer', 'questournament'));
                $mform->addElement('selectyesno', 'clustererleagues',
                get_string('createligasfromclusterer', 'questournament'));
                $mform->setHelpButton('clustererleagues', array("clustererleagues",
                get_string("createligasfromclusterer", "questournament"), "questournament"));
                $mform->setDefault('clustererleagues', $CFG->questournament_clustererleagues);
                $mform->disabledIf('clustererleagues', 'allowteams', 'eq', 1);
                $arrayclusterers = array();
                foreach ($clusterersmods as $clusterermod) {
                    $modname = $clusterermod->name . " :";
                    if ($clusterers = $DB->get_records("clusterer_clusterers", "moduleinstanceid", $clusterermod->id)) {
                        foreach ($clusterers as $clusterer) {
                            $clusterername = "$clusterer->name :";
                            if ($clustererinstances = $DB->get_records("clusterer_clusterer_instances",
                                    "clustererid", $clusterer->id)) {
                                foreach ($clustererinstances as $clustererinstance) {
                                    $date = date("d\.m\.y => g\:i\:s", $clustererinstance->version);
                                    $arrayclusterers[$clustererinstance->id] = "$modname $clusterername version: $date";
                                    // ...clear the name for a tidier list.
                                    $clusterername = "&nbsp;".str_repeat("-", strlen($clusterername)) . ">";
                                }
                                $modname = "-" . str_repeat("-", strlen($modname)) . ">";// ...clear the name for a list more tidy.
                            }
                        }
                    }
                }
                $mform->addElement('select', 'clustererid', get_string('selectclusterer',
                'questournament'), $arrayclusterers);
                $mform->setHelpButton('clustererid', array("clustererid",
                get_string("selectclusterer", "questournament"), "questournament"));
                $mform->disabledIf('clustererid', 'allowteams', 'eq', 1);
                echo <<< SCRIPT
function pasarvariable() {
    var valor=document.forms[0].clustererid.value;
    var opciones="toolbar=no, location=no, directories=no, status=no, menubar=no,
    scrollbars=yes, resizable=yes, width=1100, height=800, top=85, left=140";
    window.open("../mod/questournament/popupviewcluster.php?cid="+valor+"","",opciones);
}
</script>'
SCRIPT;
                $strshowselectedcluster = get_string("showselectedcluster", "questournament");
                $mform->addElement('html', '<br/><center><a onClick="javascript:pasarvariable()">' .
                                $strshowselectedcluster . '</a>');
                $mform->addElement('selectyesno', 'visibleleagues', get_string('visibleleagues', 'questournament'));
                $mform->setHelpButton('visibleleagues', array("visibleleagues",
                                    get_string("visibleleagues", "questournament"), "questournament"));
                $mform->setDefault('visibleleagues', $CFG->questournament_visibleleagues);
                $mform->disabledIf('visibleleagues', 'allowteams', 'eq', 1);
                $mform->addElement('selectyesno', 'anonymousleague', get_string('anonymousleague', 'questournament'));
                $mform->setHelpButton('anonymousleague', array("anonymousleague",
                get_string("anonymousleague", "questournament"), "questournament"));
                $mform->setDefault('anonymousleague', $CFG->questournament_anonymousleague);
                $mform->disabledIf('anonymousleague', 'allowteams', 'eq', 1);
            } else {
                $arrayclusterers = null;
                $mform->addElement('html', get_string('clustererModuleNotFound', 'questournament'));
                $mform->addElement('hidden', 'clustererleagues', 0);
                $mform->addElement('hidden', 'visibleleagues', 1);
                $mform->addElement('hidden', 'anonymousleague', 1);
            }
        } // Clusterer support.
        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
    /**
     *
     * {@inheritDoc}
     * @see moodleform_mod::validation()
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['datestart'] != 0 && $data['dateend'] != 0 && $data['dateend'] < $data['datestart']) {
            $errors['dateend'] = get_string('closebeforeopen', 'quest');
        }
        if ($data['mincalification'] > $data['maxcalification']) {
            $errors['maxcalification'] = get_string('checkthat', 'quest'). ': ' .
                get_string('mincalification', 'quest') . ' (' . $data['mincalification'] . ') < ' .
                get_string('maxcalification', 'quest') . ' (' . $data['maxcalification'] . ')';
            $errors['mincalification'] = $errors['maxcalification'];
        }
        if ($data['initialpoints'] > $data['maxcalification']) {
            $errors['initialpoints'] = get_string('checkthat', 'quest') . ': ' .
                    get_string('initialpoints', 'quest') . ' (' .  $data['initialpoints']  . ')' .
            ' < ' . get_string('maxcalification', 'quest') . ' (' .  $data['maxcalification']  . ')';
        }
        if ($data['initialpoints'] < $data['mincalification']) {
            $errors['initialpoints'] = get_string('checkthat', 'quest'). ': ' .
                    get_string('initialpoints', 'quest') . ' (' .  $data['initialpoints']  . ')' .
                    ' > ' . get_string('mincalification', 'quest') . ' (' .  $data['mincalification'] . ')';
        }
        if (count($errors) == 0) {
            return true;
        } else {
            return $errors;
        }
    }

    /** Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('quest', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }

        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id' => $this->current->course), '*', MUST_EXIST);
        }

        $draftitemid = file_get_submitted_draft_itemid('introattachments');
        file_prepare_draft_area($draftitemid, $ctx->id, 'mod_quest', 'introattachment', 0, array('subdirs' => 0));
        $defaultvalues['introattachments'] = $draftitemid;
    }
    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('advcheckbox', 'completionpass', '', get_string('completionpass', 'quiz'));
        $mform->disabledIf('completionpass', 'completionusegrade', 'notchecked');
        $mform->addHelpButton('completionpass', 'completionpass', 'quiz');
        // Enable this completion rule by default.
        $mform->setDefault('completionpass', 0);
        return array('completionpass');
    }
    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionpass']);
    }
}

