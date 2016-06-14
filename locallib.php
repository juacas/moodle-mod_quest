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
 * Library of extra functions and module quest
 *
 * quest constants and standard Moodle functions plus the quest functions
 * called by the standard functions
 *  see also locallib.php for other non-standard quest functions
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
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->dirroot/mod/quest/lib.php");
//require_once($CFG->dirroot.'/config.php');
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/lib/formslib.php');
//evp
$REPEAT_ACTIONS_BELOW = false; //repeat actions at the bottom of pages to easy the access on long pages.

$QUEST_TYPE = array(0 => get_string('notgraded', 'quest'),
    1 => get_string('accumulative', 'quest'),
    2 => get_string('errorbanded', 'quest'),
    3 => get_string('criterion', 'quest'),
    4 => get_string('rubric', 'quest'));

$QUEST_SHOWGRADES = array(0 => get_string('dontshowgrades', 'quest'),
    1 => get_string('showgrades', 'quest'));

$QUEST_SCALES = array(
    0 => array('name' => get_string('scaleyes', 'quest'), 'type' => 'radio',
        'size' => 2, 'start' => get_string('yes'), 'end' => get_string('no')),
    1 => array('name' => get_string('scalepresent', 'quest'), 'type' => 'radio',
        'size' => 2, 'start' => get_string('present', 'quest'),
        'end' => get_string('absent', 'quest')),
    2 => array('name' => get_string('scalecorrect', 'quest'), 'type' => 'radio',
        'size' => 2, 'start' => get_string('correct', 'quest'),
        'end' => get_string('incorrect', 'quest')),
    3 => array('name' => get_string('scalegood3', 'quest'), 'type' => 'radio',
        'size' => 3, 'start' => get_string('good', 'quest'),
        'end' => get_string('poor', 'quest')),
    4 => array('name' => get_string('scaleexcellent4', 'quest'), 'type' => 'radio',
        'size' => 4, 'start' => get_string('excellent', 'quest'),
        'end' => get_string('verypoor', 'quest')),
    5 => array('name' => get_string('scaleexcellent5', 'quest'), 'type' => 'radio',
        'size' => 5, 'start' => get_string('excellent', 'quest'),
        'end' => get_string('verypoor', 'quest')),
    6 => array('name' => get_string('scaleexcellent7', 'quest'), 'type' => 'radio',
        'size' => 7, 'start' => get_string('excellent', 'quest'),
        'end' => get_string('verypoor', 'quest')),
    7 => array('name' => get_string('scale10', 'quest'), 'type' => 'selection',
        'size' => 10),
    8 => array('name' => get_string('scale20', 'quest'), 'type' => 'selection',
        'size' => 20),
    9 => array('name' => get_string('scale100', 'quest'), 'type' => 'selection',
        'size' => 100));
// Constants.
define('QUEST_TYPE_GRADE_INDIVIDUAL', 0);
define('QUEST_TYPE_GRADE_TEAM', 1);
$QUEST_EWEIGHTS = array(0 => -4.0, 1 => -2.0, 2 => -1.5, 3 => -1.0, 4 => -0.75, 5 => -0.5, 6 => -0.25,
    7 => 0.0, 8 => 0.25, 9 => 0.5, 10 => 0.75, 11 => 1.0, 12 => 1.5, 13 => 2.0,
    14 => 4.0);
$QUEST_FWEIGHTS = array(0 => 0, 1 => 0.1, 2 => 0.25, 3 => 0.5, 4 => 0.75, 5 => 1.0, 6 => 1.5,
    7 => 2.0, 8 => 3.0, 9 => 5.0, 10 => 7.5, 11 => 10.0, 12 => 50.0);
$QUEST_EWEIGHTS_RECALIF = array(0 => -4.0, 1 => -2.0, 2 => -1.5, 3 => -1.0, 4 => -0.75, 5 => -0.5, 6 => -0.25,
    7 => 0.0, 8 => 0.25, 9 => 0.5, 10 => 0.75, 11 => 1.0, 12 => 1.5, 13 => 2.0,
    14 => 4.0);
/**
 * assesment->state
 * 0 sin realizar
 * 1 realizada autor
 * 2 realizada profesor
 *
 * assessment->phase
 * 0 sin aprobar
 * 1 aprobada
 */
define('ASSESSMENT_STATE_UNDONE', 0);
define('ASSESSMENT_STATE_BY_AUTOR', 1);
define('ASSESSMENT_STATE_BY_TEACHER', 2);
define('ASSESSMENT_PHASE_APPROVAL_PENDING', 0);
define('ASSESSMENT_PHASE_APPROVED', 1);
/**
 * answer->state
 * 0 sin editar
 * 1 editada
 * 2 modificada (evaluada manualmente?)   //evp this should be clearly defined
 *
 * answer->phase
 * 0 sin evaluar
 * 1 evaluada
 * 2 aprobada (evaluada >50%)
 *
 * answer->permitsubmit
 * 0 no editable
 * 1 editable
 */
define('ANSWER_STATE_UNEDITTED', 0);
define('ANSWER_STATE_EDITTED', 1);
define('ANSWER_STATE_MODIFIED', 2);
define('ANSWER_PHASE_UNGRADED', 0);
define('ANSWER_PHASE_GRADED', 1);
define('ANSWER_PHASE_PASSED', 2);
define('ANSWER_PERMITSUBMIT_NO_EDITABLE', 0);
define('ANSWER_PERMITSUBMIT_EDITABLE', 1);

/**
 * submission->state
 * 2 teacher, approved statte
 * 1 approval pending state
 */
define('SUBMISSION_STATE_APROVED', 2);
define('SUBMISSION_STATE_APPROVAL_PENDING', 1);

define('SUBMISSION_PHASE_ACTIVE', 1);
define('SUBMISSION_PHASE_CLOSED', 0);

//////////////////////////////////////////////////////////

/* * * Functions for the QUEST module ******


 * ************************************* */

/**
 * 
 * @param type $options
 * @param type $name
 * @param type $selected
 * @param string $nothing
 * @param type $script
 * @param type $nothingvalue
 * @param type $return
 * @return string
 */
function quest_choose_from_menu($options, $name, $selected = "", $nothing = "choose", $script = "", $nothingvalue = "0",
        $return = false) {
    // Given an array of value, creates a popup menu to be part of a form
    // $options["value"]["label"]

    if ($nothing == "choose") {
        $nothing = get_string("choose") . "...";
    }

    if ($script) {
        $javascript = "onChange=\"$script\"";
    } else {
        $javascript = "";
    }

    $output = "<select name=\"$name\" $javascript>\n";
    if ($nothing) {
        $output .= "   <option value=\"$nothingvalue\"\n";
        if ($nothingvalue == $selected) {
            $output .= " selected=\"selected\"";
        }
        $output .= ">$nothing</option>\n";
    }
    if (!empty($options)) {
        foreach ($options as $value => $label) {
            $output .= "   <option value=\"$value\"";
            if ($value == $selected) {
                $output .= " selected=\"selected\"";
            }

            $output .= ">$label</option>\n";
        }
    }
    $output .= "</select>\n";

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

function quest_print_quest_heading($quest) {
    global $OUTPUT;
    echo $OUTPUT->pix_icon('icon', 'Quest', 'quest', array('align' => 'left'));
    echo $OUTPUT->heading(format_string($quest->name));
    quest_print_quest_info($quest);
}

//////////////////////////////////////////////////////////
function quest_print_quest_info($quest) {
    global $CFG, $DB, $OUTPUT;

    echo $OUTPUT->box_start();
    // print phase and date info
    $string = '<b>' . get_string('currentphase', 'quest') . '</b>: ' . quest_phase($quest) . '<br />';
    $dates = array(
        'dateofstart' => $quest->datestart,
        'dateofend' => $quest->dateend
            /*  'calificationdate' => $quest->calificationdate */
    );
    foreach ($dates as $type => $date) {
        if ($date) {
            $strdifference = format_time($date - time());
            if (($date - time()) < 0) {
                $strdifference = "<font color=\"red\">$strdifference</font>";
            }
            $string .= '<b>' . get_string($type, 'quest') . '</b>: ' . userdate($date) . " ($strdifference)<br />";
        }
    }
    $string .= '<b>' . get_string('nmaxanswers', 'quest') . '</b>: ' . $quest->nmaxanswers . '<br />';
    if ($quest->allowteams) {
        $string .= '<b>' . get_string('ncomponentsteam', 'quest') . '</b>: ' . $quest->ncomponents . '<br />';
    }
    echo $string;
    echo $OUTPUT->box_end();
}

/* * *********************************************************************************************************** */

function quest_phase($quest, $style = '') {
    $time = time();
    if ($time < $quest->datestart) {
        return get_string('phase1' . $style, 'quest');
    } else if ($time < $quest->dateend) {

        return get_string('phase2' . $style, 'quest');
    } else {
        return get_string('phase3' . $style, 'quest');
    }
}

//////////////////////////////////////////////////////////
function quest_print_submission_title($quest, $submission) {
    // Arguments are objects

    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }

    if (!$submission->timecreated) { // a "no submission"
        return $submission->title;
    }
    return "<a name=\"sid_$submission->id\" href=\"submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\">$submission->title</a>";
}

/**
 * Form for a Challenge
 */
class quest_print_upload_form extends moodleform {

    function definition() {

        global $CFG;
        $mform = & $this->_form;
        $submission = $this->_customdata['submission'];
        $quest = $this->_customdata['quest'];
        $cm = $this->_customdata['cm'];
        $definitionoptions = $this->_customdata['definitionoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];
        $action = $this->_customdata['action'];

        $context = context_module::instance($cm->id);
        $ismanager = has_capability('mod/quest:manage', $context);

        $mform->addElement('hidden', 'id', $cm->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'sid', $submission->id);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'nosubmit', 0); //!!!!evp esto tiene sentido si usamos el js definido. hay que ver si es necesario
        $mform->setType('nosubmit', PARAM_BOOL);

        $mform->addElement('text', 'title', get_string("title", "quest"), 'size="60" maxlength="100"');
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string("introductiontothechallenge", "quest"), null,
                $definitionoptions);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addRule('description_editor', null, 'required', null, 'client');

        if (time() < $quest->datestart) {
            $challengestart = $quest->datestart;
        } else {
            $challengestart = time();
        }

        if ($ismanager) {
            $mform->addElement('date_time_selector', 'datestart', get_string("challengestart", "quest"));
            $mform->setDefault('datestart', $challengestart);

            $mform->addHelpButton('datestart', 'challengestart', 'quest');
        } else {
            //$mform->addElement('html', '<div class="fitemtitle"> '.$stringchallengestart.' : '.$date.' </div>');
            $mform->addElement('html', get_string("challengestart", "quest") . ': ' . userdate($challengestart));
            $mform->addElement('hidden', 'datestart', $challengestart);
        }
        $mform->setType('datestart', PARAM_INT);

        $challengeend = $challengestart + $quest->timemaxquestion * 24 * 3600;
        if ($challengeend > $quest->dateend) {
            $challengeend = $quest->dateend;
        }
        if ($ismanager) {
            $mform->addElement('date_time_selector', 'dateend', get_string("challengeend", "quest"));
            $mform->setDefault('dateend', $challengeend);
            $mform->addHelpButton('dateend', 'challengeend', 'quest');
        } else {
            //$mform->addElement('html', '<div class="fitemtitle"> '.$stringchallengestart.' : '.$date.' </div>');
            $mform->addElement('html', '</br>' . get_string("challengeend", "quest") . ': ' . userdate($challengeend));
            $mform->addElement('hidden', 'dateend', $challengeend);
        }
        $mform->setType('dateend', PARAM_INT);

        for ($i = 0; $i <= $quest->maxcalification; $i++) {
            $numbers[$i] = $i;
        }
        $pointsmax = $quest->maxcalification;

        $mform->addElement('select', 'pointsmax', get_string("pointsmax", "quest"), $numbers);
        $mform->setDefault('pointsmax', $quest->maxcalification);
        $mform->addHelpButton('pointsmax', 'pointsmax', 'quest'); //evp pendiente crear esta ayuda

        unset($numbers);
        if ($ismanager) {
            for ($i = 0; $i <= $quest->maxcalification; $i++) {
                $numbers[$i] = $i;
            }
        } else {
            for ($i = 0; $i <= $quest->initialpoints; $i++) {
                $numbers[$i] = $i;
            }
        }

        $mform->addElement('select', 'initialpoints', get_string("initialpoints", "quest"), $numbers);
        $mform->addHelpButton('initialpoints', 'initialpoints', 'quest');
        $mform->setDefault('initialpoints', $quest->initialpoints);

        if ($quest->nattachments) {
            $mform->addElement('filemanager', 'attachment_filemanager', get_string("attachments", "quest"), null,
                    $attachmentoptions);
        }
        if ($action == 'approve') {
            $mform->addElement('textarea', 'commentteacherauthor', get_string("comentsforautor", "quest"), 'rows="6" cols="70"');
        }
        if ($ismanager) {
            $mform->addElement('textarea', 'commentteacherpupil', get_string("comentsforpupil", "quest"), 'rows="6" cols="70"');
            $difficultyScale = quest_get_difficulty_levels();
            $radioarray = array();
            foreach ($difficultyScale as $value => $item) {
                $radioarray[] = & $mform->createElement('radio', 'perceiveddifficulty', '', $item, $value);
            }
            $mform->addGroup($radioarray, 'perceiveddifficultyOps', get_string("perceivedTeacherDifficultyLevel", "quest"), array(' '), false);
            $mform->addHelpButton('perceiveddifficultyOps', 'perceivedTeacherDifficultyLevel', 'quest');

            $minutes = quest_get_durations();
            $mform->addElement('select', 'predictedduration', get_string("predictedDurationQuestion", "quest"), $minutes);
            $mform->addHelpButton('predictedduration', 'predictedDurationQuestion', 'quest');
        }

        if ($action == 'submitchallenge') {
            $mform->addElement('hidden', 'action', 'submitchallenge');
        }
        if ($action == 'modif') {
            $mform->addElement('hidden', 'action', 'modif');
        }
        if ($action == 'approve') {
            $mform->addElement('hidden', 'action', 'approve');
        }
        $mform->setType('action', PARAM_TEXT);

        if ($action == 'approve') {
            $buttonarray = array();
            $buttonarray[] = & $mform->createElement('submit', 'submitbuttonapprove', get_string('approve', 'quest'));
            $buttonarray[] = & $mform->createElement('submit', 'submitbuttonsave', get_string('savechanges'));
            $buttonarray[] = & $mform->createElement('reset', 'resetbutton', get_string('resetchanges', 'quest'));
            $buttonarray[] = & $mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        } else {
            $this->add_action_buttons();
        }
        // In the form the id hidden element is used to hold the cmid of the quest
        $submission->sid=$submission->id;
        $submission->id=$cm->id;
        $this->set_data($submission);
    }

    function validation($data, $files) {
        // TODO evp el tema de las fechas se podrÃ­a tratar de ver si se puede mejorar de tal manera que no se pueda seleccionar una fecha posterior a la del fin del quest...
        $errors = parent::validation($data, $files);
        $a=new stdClass();
        $quest=$this->_customdata['quest'];
        $a->questdatestart=userdate($quest->datestart);
        $a->questdateend=userdate($quest->dateend);
        
        if ($data['datestart'] < $quest->datestart)
            $errors['datestart'] = get_string('invaliddates', 'quest',$a);
        if ($data['dateend'] > $quest->dateend)
            $errors['dateend'] = get_string('invaliddates', 'quest',$a);
        if ($data['datestart'] >= $data['dateend'])
            $errors['datestart'] = get_string('invaliddates', 'quest',$a);

        return $errors;
    }

}

/**
 * Receive and store a new challenge for the quest
 *
 * @global stdClass $USER
 * @global stdClass $DB
 * @global stdClass $CFG
 * @global type $OUTPUT
 * @global type $COURSE
 * @global type $PAGE
 * @param stdClass $quest
 * @param stdClass $newsubmission
 * @param boolean $ismanager
 * @param type $cm
 * @param type $definitionoptions
 * @param type $attachmentoptions
 * @param type $context
 * @param type $action
 * @param int $authorid author of the $newsubmission will override $newsubmission->userid
 */
function quest_upload_challenge(stdClass $quest, stdClass $newsubmission, $ismanager, $cm, $definitionoptions, $attachmentoptions,
        $context, $action, $authorid) {

    global $USER, $DB, $CFG, $OUTPUT, $COURSE, $PAGE;

    // ...get the current set of submissions
    // ...add new submission record
    $newsubmission->questid = $quest->id;
    $newsubmission->userid = $authorid;
    $newsubmission->id = $newsubmission->sid; // ...id is overused in the form but must be named id for the database.
    //$newsubmission->title  = $entry->title;
    //$newsubmission->description = trim($form->description_editor['text']);
    $newsubmission->description = ''; // updated later
    $newsubmission->descriptionformat = FORMAT_HTML; // updated later
    $newsubmission->descriptiontrust = 0; //updated later
    $newsubmission->timecreated = time();

    if ($ismanager) {
        // $newsubmission->commentteacherpupil = $entry->commentteacherpupil;

        if (!isset($newsubmission->perceiveddifficulty)) {
            $newsubmission->perceiveddifficulty = -1;
        }
        // $newsubmission->predictedduration=$entry->predictedduration;
    }
    if ($newsubmission->dateend > $quest->dateend) {
        $newsubmission->dateend = $quest->dateend;
    }
    if ($newsubmission->initialpoints > $newsubmission->pointsmax) {
        $newsubmission->initialpoints = $newsubmission->pointsmax;
    }
    if (empty($newsubmission->id)) {  // $newsubmission->sid is not defined or empty if this is a new submission.
        $isnew = true;
        if ($ismanager) {
            $newsubmission->state = SUBMISSION_STATE_APROVED;  // ...if teacher, approved state.
        } else {
            $newsubmission->state = SUBMISSION_STATE_APPROVAL_PENDING;  //  ...if student, approval pending state.
        }
        if (!$newsubmission->id = $DB->insert_record("quest_submissions", $newsubmission)) {
            error("Quest submission: Failure to create new submission record!");
        }
    } else {
        $isnew = false;
        if ($ismanager && $action === 'approve') { // ...the challenge is approved by the teacher.
            $newsubmission->state = SUBMISSION_STATE_APROVED;
        } else {    // ...the challenge is modified, the status does not change.
            $newsubmission->state = $DB->get_field('quest_submissions', 'state', array('id' => $newsubmission->id));
        }
    }

    // ...management of files: save embedded images and attachments.

    $newsubmission = file_postupdate_standard_editor($newsubmission, 'description', $definitionoptions, $context, 'mod_quest',
            'submission', $newsubmission->id);
    $newsubmission = file_postupdate_standard_filemanager($newsubmission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $newsubmission->id);

    // ...store the updated values in table.
    $DB->update_record('quest_submissions', $newsubmission);

    if ($action == 'submitchallenge') {
        /*
         * recalculate points and report to gradebook
         */
        quest_grade_updated($quest, $USER->id);
    }

    /* evp, check what we want to do with this (there are some comments from JP about let the cron do the job... different messages will have to be send according to the action
      if (!$users = quest_get_course_members($COURSE->id, "u.lastname, u.firstname, u.secret")){
      continue;
      }else{
      foreach($users as $user){
      if(!$ismanager){
      continue;
      }else{
      quest_send_message($user, "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", 'addsubmission', $quest, $newsubmission, '');
      }
      }
     */
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'quest'));

    quest_update_challenge_calendar($cm,$quest,$newsubmission);

    if ($action == 'submitchallenge') {
        require_once('classes/event/challenge_created.php');
        mod_quest\event\challenge_created::create_from_parts($newsubmission, $cm)->trigger();
    } else if ($action == 'modif') {
        if ($CFG->version >= 2014051200) {
            require_once 'classes/event/challenge_updated.php';
            \mod_quest\event\challenge_updated::create_from_parts($USER,$newsubmission,$cm)->trigger();
        } else {
            add_to_log($COURSE->id, "quest", "modif_submission",
                "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id",
                "$cm->id");
        }
    } else if ($action == 'approve') {
        if ($CFG->version >= 2014051200) {
            require_once 'classes/event/challenge_aproved.php';
            \mod_quest\event\challenge_approved::create_from_parts($USER,$newsubmission,$cm)->trigger();
        } else {
            add_to_log($COURSE->id, "quest", "approve_submission",
                "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id",
                "$cm->id");
        }
        
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string("submitted", "quest") . " " . get_string("ok"));
    echo $OUTPUT->continue_button("view.php?id=$cm->id");
}

function quest_get_durations() {
    $minutes = array(
        -1 => "",
        1 => " 1 " . get_string("minutes", "moodle"),
        2 => " 2 " . get_string("minutes", "moodle"),
        5 => " 5 " . get_string("minutes", "moodle"),
        10 => "10 " . get_string("minutes", "moodle"),
        15 => "15 " . get_string("minutes", "moodle"),
        20 => "20 " . get_string("minutes", "moodle"),
        25 => "25 " . get_string("minutes", "moodle"),
        30 => "30 " . get_string("minutes", "moodle"),
        45 => "45 " . get_string("minutes", "moodle"),
        60 => " 1 " . get_string("hour", "moodle"),
    );

    // some half hours
    for ($i = 90; $i < 12 * 60; $i = $i + 30) {
        if ($i % 60 == 0)
            $minutes[$i] = floor($i / 60) . " " . get_string("hours", "moodle");
        else
            $minutes[$i] = floor($i / 60) . " " . get_string("hours", "moodle") . " 30 " . get_string("minutes", "moodle");
    }
    $minutes[24 * 60] = " 1 " . get_string("day");
    // some days
    for ($i = 1; $i <= 15; $i++)
        $minutes[24 * 60 * $i] = " " . $i . " " . get_string("days", "moodle");
    // some weeks
    for ($i = 3; $i <= 4; $i++)
        $minutes[24 * 60 * 7 * $i] = " " . $i . " " . get_string("weeks", "moodle");
    // some months
    for ($i = 5; $i <= 12; $i++)
        $minutes[24 * 60 * 30 * $i] = " " . $i . " " . get_string("months", "moodle");
    return $minutes;
}



function quest_get_difficulty_levels() {
    return array(0 => get_string("difficultyEasy", "quest")
        , 1 => get_string("difficultyAttainable", "quest")
        , 2 => get_string("difficultyHard", "quest")
            //	,3=> get_string("difficultyVeryHard","quest")
    );
}
//////////////////////////////////////////////////////////
function quest_print_submission($quest, $submission) {
    // prints the submission with optional attachments
    global $CFG, $USER, $OUTPUT;

    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }
    $description = $submission->description;
    $context = context_module::instance($cm->id);
    $description = file_rewrite_pluginfile_urls($description, 'pluginfile.php', $context->id, 'mod_quest', 'submission',
            $submission->id);

    $options = new stdClass();
    $options->para = false;
    $options->trusted = $submission->descriptiontrust;
    $options->context = $context;
    $options->overflowdiv = true;
    $description = format_text($description, $submission->descriptionformat, $options);
    echo $OUTPUT->box($description);
    $ismanager = has_capability('mod/quest:manage', $context);

    if (!empty($submission->comentteacherautor)) {
        if (($submission->userid == $USER->id) || ($ismanager)) {
            echo $OUTPUT->heading(get_string('comentsforautor', 'quest'));
            echo $OUTPUT->box(format_text($submission->comentteacherautor), 'center');
        }
    }
    if (!empty($submission->comentteacherpupil)) {
        echo $OUTPUT->heading(get_string('comentsforpupil', 'quest'));
        echo $OUTPUT->box(format_text($submission->comentteacherpupil), 'center');
    }

    if ($quest->nattachments) {
        if ($submission->attachment) {
            quest_print_attachments($context,'attachment',$submission->id,'timemodified');
        }
    }
    return;
}
function quest_print_attachments($context, $filearea, $itemid, $order) {
    global $OUTPUT,$CFG;
    $n = 1;
    echo "<table align=\"center\">\n";
    $fs = get_file_storage();
    if ($files = $fs->get_area_files($context->id, 'mod_quest', $filearea, $itemid, $order, false)) {
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle',
                    array('class' => 'icon'));
            $path = "/$context->id/mod_quest/$filearea/";
            if ($itemid){
                $path.=$itemid . '/';
            }
            $path.=$filename;
            $path =file_encode_url($CFG->wwwroot . '/pluginfile.php',$path);
            echo "<tr><td><b>" . get_string("attachment", "quest") . " $n:</b> \n";
            echo $iconimage;
            echo format_text("<a href=\"$path\">" . s($filename) . "</a>", FORMAT_HTML, array('context' => $context));
            $n++;
        }
    }
    echo "</table>\n";
}

function quest_print_submission_info($quest, $submission) {

    global $CFG, $USER, $DB, $OUTPUT;

    $timenow = time();

    if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
        print_error("course_misconfigured", 'quest');
    }
    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }
    // print standard assignment heading
    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    echo $OUTPUT->box_start("center");

    // print phase and date info
    $string = '<b>' . get_string('currentphasesubmission', 'quest') . '</b>: ' . quest_submission_phase($submission, $quest,
                    $course) . '<br />';
    $dates = array(
        'dateofstart' => $submission->datestart,
        'dateofend' => $submission->dateend
    );
    foreach ($dates as $type => $date) {
        if ($date) {
            $strdifference = format_time($date - time());
            if (($date - time()) < 0) {
                $strdifference = "<font color=\"red\">$strdifference</font>";
            }
            $string .= '<b>' . get_string($type, 'quest') . '</b>: ' . userdate($date) . " ($strdifference)<br />";
        }
    }

    $string .= '<b>' . get_string('nanswers', 'quest') . ":&nbsp;&nbsp;$submission->nanswers" . '</b><br>';
    $string .= '<b>' . get_string('nanswerscorrect', 'quest') . ":&nbsp;&nbsp;$submission->nanswerscorrect" . '</b><br>';
    if (($submission->dateend < time()) || ($submission->nanswerscorrect >= $quest->nmaxanswers)) {
        $string .= '<b>' . get_string('pointsmaxsubmission', 'quest') . ":&nbsp;&nbsp;$submission->pointsanswercorrect" . '</b><br>';
    }
    $string .= '<form name="puntos"><b>' . get_string('points', 'quest') . ":&nbsp;&nbsp;<input name=\"calificacion\" type=\"text\" value=\"0.000\" size=\"10\" readonly=\"1\" style=\"background-color : White; border : black; color : Black; font-family : Verdana, Arial, Helvetica; font-size : 14pt; text-align : center;\" ></form></b><br>";

    if (($USER->id == $submission->userid) || ($ismanager) || ($submission->dateend < time())) {
        if ($submission->evaluated == 1 && $assessment = $DB->get_record("quest_assessments_autors",
                array("questid" => $quest->id,
            "submissionid" => $submission->id))) {
            $string .= '<b>' . get_string('calificationautor', 'quest') . ': ';
            $string .= number_format(100 * $assessment->points / $submission->initialpoints, 1) . '% ';
            $string.= get_string('of', 'quest') . ' ' . get_string('initialpoints', 'quest') . ' ' . number_format($submission->initialpoints,
                            2);
            $string.=' (' . number_format($assessment->points, 1) . ')</b>';
        } else {
            $string .= '<br><b>' . get_string('calificationautor', 'quest') . ': ' . get_string('evaluation_pending', 'quest') . '</b>';
        }
    }
    $string .= "<script language=\"JavaScript\">\n";
    $string .= "function redondear(cantidad, decimales) {\n
var cantidad = parseFloat(cantidad);\n
var decimales = parseFloat(decimales);\n
decimales = (!decimales ? 2 : decimales);\n
var valor = Math.round(cantidad * Math.pow(10, decimales)) / Math.pow(10, decimales);\n
return valor.toFixed(4);\n
}\n
var servertime=" . time() * 1000 . ";
var browserDate=new Date();
var browserTime=browserDate.getTime();
var correccion=servertime-browserTime;
function puntuacion(state,pointsmax,initialpoints,tinitial,datestart,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers){\n
                     tiempoactual = new Date();\n
                     tiempo = parseInt((tiempoactual.getTime()+correccion)/1000);\n

                     if((dateend - datestart - tinitial) == 0){\n
                      var incline = 0;\n
                     }\n
                     else{\n
                      if(type == 0){\n
                       var incline = (pointsmax - initialpoints)/(dateend - datestart - tinitial);\n
                      }\n
                      else{\n
                       if(initialpoints == 0){\n
                        initialpoints = 0.0001;\n
                       }\n
                       incline = (1/(dateend - datestart - tinitial))*Math.log(pointsmax/initialpoints);\n

                      }\n
                     }\n
                    if(state < 2){\n
                     grade = initialpoints;\n
                     formularios.style.color = \"#cccccc\";\n
                    }\n
                    else{\n

                     if(datestart > tiempo){\n
                      grade = initialpoints;\n
                      formularios.style.color = \"#cccccc\";\n
                     }\n
                     else{\n
                       if(nanswerscorrect >= nmaxanswers){\n
                             grade =  0;\n
                             formularios.style.color = \"#cccccc\";\n
                       }\n
                       else{\n
                        if(dateend < tiempo){\n
                          if(nanswerscorrect == 0){\n
                                 t = dateend - datestart;\n
                                 if(t <= tinitial){\n
                                  grade = initialpoints;\n
                                  formularios.style.color = \"#cccccc\";\n
                                 }\n
                                 else{\n
                                  grade = pointsmax;\n
                                  formularios.style.color = \"#cccccc\";\n
                                 }\n
                          }\n
                          else{\n
                            grade = 0;\n
                            formularios.style.color = \"#cccccc\";\n
                          }\n
                        }\n
                        else{\n
                         if(nanswerscorrect == 0){\n
                                 t = tiempo - datestart;\n
                                 if(t < tinitial){\n
                                  grade = initialpoints;\n
                                  formularios.style.color = \"#000000\";\n
                                 }\n
                                 else{\n
                                 if(t >= (dateend-datestart)){\n
                                  grade = pointsmax;\n
                                  formularios.style.color = \"#000000\";\n
                                 }\n
                                 else{\n
                                  if(type == 0){\n
                                   grade = (t - tinitial)*incline + initialpoints;\n
                                   formularios.style.color = \"#000000\";\n
                                  }\n
                                  else{\n
                                   grade = initialpoints*Math.exp(incline*(t - tinitial));\n
                                   formularios.style.color = \"#000000\";\n

                                  }\n
                                 }\n
                                 }\n
                         }\n
                         else{\n
                                t = tiempo - dateanswercorrect;\n
                                if((dateend - dateanswercorrect) == 0){\n
                                 incline = 0;\n
                                }\n
                                else{\n
                                 if(type == 0){\n
                                  incline = (-pointsanswercorrect)/(dateend - dateanswercorrect);\n
                                 }\n
                                 else{\n
                                   incline = (1/(dateend - dateanswercorrect))*Math.log(0.0001/pointsanswercorrect);\n
                                 }\n
                                }\n
                                if(type == 0){\n
                                 grade = pointsanswercorrect + incline*t;\n
                                 formularios.style.color = \"#000000\";\n
                                }\n
                                else{\n
                                 grade = pointsanswercorrect*Math.exp(incline*t);\n
                                 formularios.style.color = \"#000000\";\n
                                }\n
                         }\n
                        }\n
                       }\n
                     }\n
                    }\n
                     if(grade < 0){\n
                      grade = 0;\n
                     }\n
                     grade = redondear(grade,4);\n
                     formularios.value = grade;\n
        setTimeout(\"puntuacion(state,pointsmax,initialpoints,tinitial,datestart,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers)\",500);\n
}\n
</script>\n";

    if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE;
    }
    $string .= "<script language=\"JavaScript\">\n";
    $string .= "initialpoints = $submission->initialpoints;\n";
    $string .= "nanswerscorrect =$submission->nanswerscorrect;\n";
    $string .= "datestart = $submission->datestart;\n";
    $string .= "dateend = $submission->dateend;\n";
    $string .= "dateanswercorrect= $submission->dateanswercorrect;\n";
    $string .= "pointsmax = $submission->pointsmax;\n";
    $string .= "pointsanswercorrect = $submission->pointsanswercorrect;\n";
    $string .= "tinitial = $quest->tinitial*86400;\n";
    $string .= "state = $submission->state;\n";
    $string .= "type = $quest->typecalification;\n";
    $string .= "nmaxanswers = $quest->nmaxanswers;\n";
    $string .= "pointsnmaxanswers = $submission->points;\n";
    $string .= "formularios = document.forms.puntos.calificacion;\n";
    $string .= "puntuacion(state,pointsmax,initialpoints,tinitial,datestart,nanswerscorrect,dateanswercorrect,pointsanswercorrect,dateend,formularios,type,nmaxanswers,pointsnmaxanswers);\n";
    $string .= "</script>\n";
    echo $string;
    echo $OUTPUT->box_end();
}

/* * *********************************************************************************************************** */

function quest_submission_phase($submission, $quest, $course, $style = '') {

    global $USER;

    $context = context_course::instance($course->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    $time = time();

    if ($submission->state == SUBMISSION_STATE_APPROVAL_PENDING) {
        if ($submission->evaluated == false) {
            return get_string('phase1submission' . $style, 'quest');
        } else if ($submission->evaluated == true) {
            if (($ismanager) || ($submission->userid == $USER->id)) {
                return get_string('phase5submission' . $style, 'quest');
            } else {
                return get_string('phase1submission' . $style, 'quest');
            }
        }
    } else if ($submission->state == SUBMISSION_STATE_APROVED) {
        if ($time < $submission->datestart) {
            if ($submission->evaluated == false) {
                return get_string('phase2submission' . $style, 'quest');
            } else if ($submission->evaluated == true) {
                if (($ismanager) || ($submission->userid == $USER->id)) {
                    return get_string('phase8submission' . $style, 'quest');
                } else {
                    return get_string('phase2submission' . $style, 'quest');
                }
            }
        } else if (($time < $submission->dateend) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
            if ($submission->evaluated == 0) {
                return get_string('phase3submission' . $style, 'quest');
            } else if ($submission->evaluated == 1) {
                if (($ismanager) || ($submission->userid == $USER->id)) {
                    return get_string('phase6submission' . $style, 'quest');
                } else {
                    return get_string('phase3submission' . $style, 'quest');
                }
            }
        } else {
            if ($submission->evaluated == 0) {
                return get_string('phase4submission' . $style, 'quest');
            } else if ($submission->evaluated == 1) {
                if (($ismanager) || ($submission->userid == $USER->id)) {
                    return get_string('phase7submission' . $style, 'quest');
                } else {
                    return get_string('phase4submission' . $style, 'quest');
                }
            }
        }
    }
}

//// this class replaces function quest_print_answer_form
// Prints form to submit an answer to a challenge

class quest_print_answer_form extends moodleform {

    function definition() {

        global $CFG;
        $mform = & $this->_form;
        $currententry = $this->_customdata['current'];
        $quest = $this->_customdata['quest'];
        $cm = $this->_customdata['cm'];
        $definitionoptions = $this->_customdata['definitionoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];
        $action = $this->_customdata['action'];

        $context = context_module::instance($cm->id);
        //$ismanager=has_capability('mod/quest:manage',$context);

        $mform->addElement('text', 'title', get_string("title", "quest"), 'size="60" maxlength="100"');
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string("responsetochallenge", "quest"), null, $definitionoptions);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addRule('description_editor', null, 'required', null, 'client');

        if ($quest->nattachments) {
            $mform->addElement('filemanager', 'attachment_filemanager', get_string("attachments", "quest"), null,
                    $attachmentoptions); //evp a lo mejor hay que poner una comprobaciï¿½n porque podrï¿½a ser que el limite de bytes de loos adjuntos se imponga a otro nivel $course->maxbytes, o lo comprueba el propio filemanager supongo
        } //evp hay que asegurarse que esto no se confunde con los adjuntos de la descripciÃ³n del desafÃ­o

        $difficultyScale = quest_get_difficulty_levels();
        $radioarray = array();
        foreach ($difficultyScale as $value => $item) {
            $radioarray[] = & $mform->createElement('radio', 'perceiveddifficulty', '', $item, $value);
        }
        $mform->addGroup($radioarray, 'radioar', get_string("perceiveddifficultyLevelQuestion", "quest"), array(' '), false); //evp pendiente hacer que no salga ninguna opciÃ³n seleccionada.
        $mform->addElement('hidden', 'aid', $currententry->id);
        $mform->setType('aid', PARAM_INT);
        $mform->addElement('hidden', 'id', $currententry->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', $currententry->submissionid);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'submissionid', $currententry->submissionid);
        $mform->setType('submissionid', PARAM_INT);


        if ($action == 'delete') {
            $mform->addElement('hidden', 'action', 'delete');
            $mform->setType('action', PARAM_TEXT);
        } else if ($action == 'modif') {
            $mform->addElement('hidden', 'action', 'modif');
            $mform->setType('action', PARAM_TEXT);
        } else {
            $mform->addElement('hidden', 'action', 'answer');
            $mform->setType('action', PARAM_TEXT);
        }

        $this->add_action_buttons();

        $this->set_data($currententry);
    }
}

/**
 * 
 * @global stdClass $DB
 * @global type $COURSE
 * @global type $OUTPUT
 * @global stdClass $USER
 * @param type $quest
 * @param type $answer
 * @param type $ismanager
 * @param type $cm
 * @param type $definitionoptions
 * @param type $attachmentoptions
 * @param type $context
 */
function quest_uploadanswer($quest, $answer, $ismanager, $cm, $definitionoptions, $attachmentoptions, $context) {

    global $DB, $COURSE, $OUTPUT, $USER;
    $strquests = get_string('modulenameplural', 'quest');
    $strquest = get_string('modulename', 'quest');
    $stranswer = get_string('answer', 'quest');

    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?  //Evp esto no lo entinedo bien aquÃ­
//	$groupmode = groupmode($COURSE, $cm);   // Groups are being used?
    $currentgroup = groups_get_course_group($COURSE);
    $groupmode = $currentgroup = false; //JPC group support desactivation

    $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);
    $timenow = time();
    // variable $modif to check if the answer is new of is being modified
    if (empty($answer->id)) {
        $modif = false;
        if (!$validate = quest_validate_user_answer($quest, $submission)) {
            print_error('answerexisty', 'quest', "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission");
        }
//		$answer= new stdClass();
        $answer->questid = $quest->id;
        $answer->userid = $USER->id;
        $answer->submissionid = $answer->sid;
    } else {
        $modif = true;
        $answer->id = $DB->get_field('quest_answers', 'id', array('id' => $answer->id), MUST_EXIST);
        if (!($ismanager or ( ($USER->id == $answer->userid) and ( $timenow < $quest->dateend)))) {
            print_error('answernoauthorizedupdate', 'quest',
                    "submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id");
        }
    }



    //$answer->title  = $title;
    //$answer->description = trim($form->description);
    $answer->date = $timenow;
    $answer->description = ''; // updated later
    $answer->descriptionformat = FORMAT_HTML; // updated later
    $answer->descriptiontrust = 0; //updated later
    $answer->commentforteacher = '';  //field defined in table as no null and no default value is indicated
    //Evp !!! aquÃ­ a lo mejor hay que hacer una comprobaciÃ³n previamente si es vacÃ­o ponlo igual a ""
    $points = quest_get_points($submission, $quest, $answer);
    $answer->pointsmax = $points;

    if ($modif == false) {
        $answer->phase = 0;
        $answer->state = 1;
    } else {
        $answer->phase = $DB->get_field('quest_answers', 'phase', array('id' => $answer->id));
        if (($answer->phase == ANSWER_PHASE_GRADED) || ($answer->phase == ANSWER_PHASE_PASSED))
            $answer->state = ANSWER_STATE_MODIFIED;
        else
            $answer->state = ANSWER_STATE_EDITTED;
    }
    if ($modif == false) {
        if (!$answer->id = $DB->insert_record("quest_answers", $answer)) {
            error("Quest answer: Failure to create new answer record!");
        }
    }
    $answer = file_postupdate_standard_editor($answer, 'description', $definitionoptions, $context, 'mod_quest', 'answer',
            $answer->id);

    // do something about the attachments, if there are any
    if ($quest->nattachments) {
        // management of files: save embedded images and attachments

        $answer = file_postupdate_standard_filemanager($answer, 'attachment', $attachmentoptions, $context, 'mod_quest',
                'answer_attachment', $answer->id);

        // store the updated values in table
        //	if ($um->process_file_uploads($dir))
        /* 		{
          add_to_log($course->id, "quest", "newattachment", "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", "$answer->id","$cm->id");
          print_heading(get_string("uploadsuccess", "quest"));
          // um will take care of printing errors.
          } */ //evp esto del add_to_log con los ficheros habrÃ¡ que ver si se va a hacer.
    }
    $DB->update_record('quest_answers', $answer);

    // TODO: en este punto no hay cambio de calificaciÃ³n
    //////////////////////////
    //Update scores and statistics
    ///////////////////////////
    // Update current User scores
    require_once('scores_lib.php');
    $submission->nanswers = quest_count_submission_answers($submission->id);
    $DB->update_record('quest_submissions', $submission);
    quest_update_user_scores($quest, $answer->userid);
    ///////////////////////////
    //  Update answer current team totals
    if ($quest->allowteams) {
        quest_update_team_scores($quest->id, quest_get_user_team($quest->id, $answer->userid));
    }
    /**
     * NOTIFICATIONS
     */
    if (!$users = quest_get_course_members($COURSE->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer($course);
        exit;
    }
//JPC 2013-11-28 disable excesive notifications
// 		foreach($users as $user){
// 			if($ismanager)
// 			{
// 				quest_send_message($user, "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", 'answeradd', $quest, $submission, $answer,$USER);
// 			}
// 		}
// 		if(!has_capability('mod/quest:manage',$context,$submission->userid))
// 		{
    $user = get_complete_user_data('id', $submission->userid);
    if ($user){
        quest_send_message($user, "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", 'answeradd', $quest,
            $submission, $answer);
 		}

    if ($modif == false) {
        \mod_quest\event\answer_created::create_from_parts($submission, $answer, $cm)->trigger();
//        add_to_log($COURSE->id, "quest", "submit_answer",
//                "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", "$answer->id", "$cm->id");
    } else {
        \mod_quest\event\answer_updated::create_from_parts($submission,$answer,$cm)->trigger();
//        add_to_log($COURSE->id, "quest", "modif_answer",
//                "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", "$answer->id", "$cm->id");
    }
}


/**
 *
 * Enter description here ...
 * @param unknown_type $quest
 * @param unknown_type $submission
 * @param unknown_type $course
 * @param unknown_type $cm
 * @param unknown_type $sort
 * @param unknown_type $dir
 */
function quest_print_table_answers($quest, $submission, $course, $cm, $sort, $dir) {
    global $CFG, $USER, $DB, $OUTPUT;

    $timenow = time();
    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    // Check to see if groups are being used in this quest
    // and if so, set $currentgroup to reflect the current group
    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
    $groupmode = groups_get_activity_group($cm);   // Groups are being used?
    //$currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);

    $currentgroup = groups_get_course_group($course);
    $groupmode = $currentgroup = false; //JPC group support desactivation
    // Allow the teacher to change groups (for this session)
    if ($groupmode and $ismanager) {
        if ($groups = $DB->get_records_menu("groups", array("courseid" => $course->id), "name ASC", "id,name")) {
            groups_print_activity_menu($cm,
                    $CFG->wwwroot . "mod/quest/submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission",
                    $return = false, $hideallparticipants = false);
        }
    }

    // Get all the students
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit;
    }

    // Now prepare table with student assessments and submissions
    $tablesort = new stdClass;
    $tablesort->data = array();
    $tablesort->sortdata = array();

    $ismanager_user = has_capability('mod/quest:manage', $context, $USER->id);


    if ($answers = quest_get_submission_answers($submission)) {
        foreach ($answers as $answer) {
            $data = array();
            $sortdata = array();
// Can show the answer?

            if (!$ismanager && $groupmode != false && $groupmode != VISIBLEGROUPS && !groups_is_member($currentgroup,
                            $answer->userid)) { // not in this group
                continue;
            }

            if (($ismanager) // admin
                    || ($submission->userid == $USER->id) // challenge owner
                    || ($answer->userid == $USER->id) // answer owner
                    || ($submission->dateend < $timenow) || ($submission->nanswerscorrect >= $quest->nmaxanswers)) { // challenge closed
                $edit_icon = $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest'));
                $delete_icon = $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest'));
                $mine_icon = $answer->userid == $USER->id && !$ismanager ? $OUTPUT->user_picture($USER) : '';
                $answer_title = $mine_icon . quest_print_answer_title($quest, $answer, $submission);
                $edit_link = " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                        $edit_icon .
                        '</a>';
                $delete_link = " <a href=\"answer.php?action=confirmdelete&amp;id=$cm->id&amp;sid=$submission->id&amp;aid=$answer->id\">" .
                        $delete_icon .
                        '</a>';
                if ($ismanager) {
                    $data[] = $answer_title . $edit_link . $delete_link; ;
                } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase == 0) && $submission->nanswerscorrect < $quest->nmaxanswers) {

                    $data[] = $answer_title . $edit_link . $delete_link;
                } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase > 0) && ($answer->permitsubmit == 1)) {
                    $data[] = $answer_title . $edit_link;
                } else {
                    $data[] = $answer_title;
                }
                $sortdata['title'] = strtolower($answer->title);

                //$user = $DB->get_record('user', array('id'=>$answer->userid));
                $user = get_complete_user_data('id', $answer->userid);
                // User Name Surname
                if ($ismanager) {
                    $data[] = $OUTPUT->user_picture($user);
                    $data[] = "<a name=\"userid->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" . fullname($user) . '</a>';
                    $sortdata['firstname'] = strtolower($user->firstname);
                    $sortdata['lastname'] = strtolower($user->lastname);
                }
                // Answer Phase
                $data[] = quest_answer_phase($answer, $course);
                $sortdata['phase'] = quest_answer_phase($answer, $course);

                $data[] = userdate($answer->date, get_string('datestr', 'quest'));
                $sortdata['dateanswer'] = $answer->date;

                if (($answer->phase == 1) || ($answer->phase == 2)) {
                    $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id));
                } else {
                    $assessment = NULL;
                }

                if (!$ismanager_user && ($groupmode == 2)) {
                    if ($currentgroup) {
                        if (!groups_is_member($currentgroup, $user->id)) {
                            $data[] = '----';
                            $sortdata['tassmnt'] = 1;
                        } else {
                            $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
                            $sortdata['tassmnt'] = 1;
                        }
                    } else {
                        $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
                        $sortdata['tassmnt'] = 1;
                    }
                } else {
                    $data[] = quest_print_actions_answers($cm, $answer, $submission, $course, $assessment);
                    $sortdata['tassmnt'] = 1;
                }

                $score = quest_answer_grade($quest, $answer, 'ALL');
                if ($answer->pointsmax == 0)
                    $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
                else
                    $grade = number_format($score, 4) . ' (' . number_format($answer->grade, 0) . '%) [max:' . number_format($answer->pointsmax,
                                    4) . ']';

                $data[] = $grade;
                $sortdata['calification'] = $score;
                $difflevels = quest_get_difficulty_levels();

                if ($answer->perceiveddifficulty == -1) {
                    $data[] = "--";
                } else {
                    $data[] = $difflevels[$answer->perceiveddifficulty] . " ($answer->perceiveddifficulty)";
                }
                $sortdata['perceiveddifficulty'] = $answer->perceiveddifficulty;

                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }// if user is authorized to view answer
        }// for each answer
    }// if there are answers
// uses global $sort and $dir
    uasort($tablesort->sortdata, 'quest_sortfunction');
    //$table = new stdClass();
    $table = new html_table();
    $table->data = array();
    foreach ($tablesort->sortdata as $key => $row) {
        $table->data[] = $tablesort->data[$key];
    }


    $table->align = array('left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    if ($ismanager) {
        $columns = array('title', 'firstname', 'lastname', 'phase', 'dateanswer', 'actions', 'calification');
    } else {
        $columns = array('title', 'phase', 'dateanswer', 'actions', 'calification');
    }

    $table->width = "95%";

    foreach ($columns as $column) {
        $string[$column] = get_string("$column", 'quest');
        if ($sort != $column) {
            $columnicon = '';
            $columndir = 'ASC';
        } else {
            $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
            if ($column == 'lastaccess') {
                $columnicon = $dir == 'ASC' ? 'up' : 'down';
            } else {
                $columnicon = $dir == 'ASC' ? 'down' : 'up';
            }
            $columnicon = " <img src=\"" . $CFG->wwwroot . "/pix/i/$columnicon.png\" alt=\"$columnicon\" />";
        }
        $$column = "<a href=\"submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission&amp;sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    }

    if ($ismanager) {
        $table->head = array("$title", "$firstname / $lastname", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification", get_string("perceiveddifficultyLevel",
                    'quest'));
        $table->headspan = array(1, 2, 1, 1, 1);
    } else {
        $table->head = array("$title", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification");
    }


    echo '<tr><td>';
    //print_table($table);
    echo html_writer::table($table);
    echo '</td></tr>';
}

//
function quest_print_answer_title($quest, $answer, $submission) {
    // Arguments are objects

    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }

    if (!$answer->date) { // a "no submission"
        return $submission->title;
    }
    return "<a name=\"sid_$answer->id\" href=\"answer.php?id=$cm->id&amp;sid=$submission->id&amp;action=showanswer&amp;aid=$answer->id\">$answer->title</a>";
}


function quest_print_actions_answers($cm, $answer, $submission, $course, $assessment) {
    global $USER;
    // Returns the teacher or peer grade and a hyperlinked list of grades for this submission

    $str = '';

    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if (!$ismanager && ($answer->userid == $USER->id)) {
        if (($answer->phase == 1) || ($answer->phase == 2)) {
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"viewassessment.php?asid=$assessment->id\">" . get_string('seevaluate',
                            'quest') . "</a>";
        } else {
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"answer.php?aid=$answer->id&amp;action=showanswer&amp;sid=$submission->id\">" . get_string('see',
                            'quest') . "</a>";
        }
    } else if ($ismanager) {
        $assessurl = new moodle_url("/mod/quest/assess.php", ['id' => $cm->id, 'sid' => $submission->id, 'aid' => $answer->id, 'sesskey' => sesskey()]);
        if (($answer->phase == 1) || ($answer->phase == 2)) {

            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$assessurl\">" . get_string('reevaluate', 'quest') . "</a>";

            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"viewassessment.php?sid=$submission->id&amp;asid=$assessment->id&amp;aid=$answer->id\">" . get_string('seevaluate',
                            'quest') . "</a>";
        } else if ($answer->phase == 0) {

            $str .= '&nbsp;&nbsp;<a href="'.$assessurl.'">' . get_string('evaluate', 'quest') . '</a>';
        } else {
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"answer.php?aid=$answer->id&amp;action=showanswer&amp;sid=$submission->id\">" . get_string('see',
                            'quest') . "</a>";
        }
    } else if ($submission->userid == $USER->id) {

        if ((($answer->phase == 1) || ($answer->phase == 2)) && ($assessment->state == 1)) {
   $assessurl = new moodle_url("/mod/quest/assess.php", ['id' => $cm->id, 'sid' => $submission->id, 'aid' => $answer->id, 'sesskey' => sesskey()]);
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"assess.php?id=$cm->id&amp;sid=$submission->id&amp;aid=$answer->id\">" . get_string('reevaluate',
                            'quest') . "</a>";

            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"viewassessment.php?sid='.$submission->id.'&amp;asid=$assessment->id&amp;aid=$answer->id\">" . get_string('seevaluate',
                            'quest') . "</a>";
        } else if ((($answer->phase == 1) || ($answer->phase == 2)) && ($assessment->state == 2)) {

            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"viewassessment.php?sid='.$submission->id.'&amp;asid=$assessment->id&amp;aid=$answer->id\">" . get_string('seevaluate',
                            'quest') . "</a>";
        } else if ($answer->phase == 0) {

            $str .= '&nbsp;&nbsp;<a href="assess.php?id=' .
                    $cm->id . '&amp;aid=' . $answer->id . '&amp;sid=' . $submission->id . '$amp;action=evaluate">' . get_string('evaluate',
                            'quest') . '</a>';
        } else {
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"answer.php?aid=$answer->id&amp;action=showanswer&amp;sid=$submission->id\">" . get_string('see',
                            'quest') . "</a>";
        }
    } else {
        $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"answer.php?aid=$answer->id&amp;action=showanswer&amp;sid=$submission->id\">" . get_string('see',
                        'quest') . "</a>";
    }
    if ((($ismanager) || ($submission->userid == $USER->id)) && (($answer->phase == 1) || ($answer->phase == 2)) && ($answer->permitsubmit == 0)) {
        $str .= "&nbsp;&nbsp;<a href=\"answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=permitsubmit\">" .
                get_string('permitsubmit', 'quest') . "</a>";
    }


    return $str;
}


function quest_print_answer_info($quest, $answer) {
    global $CFG, $DB, $OUTPUT;
    if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
        print_error("course_misconfigured", 'quest');
    }
    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }
    // Print standard assignment heading.
    echo $OUTPUT->box_start("center");
    // Print phase and date info.
    $string = '<b>' . get_string('currentphaseanswer', 'quest') . '</b>: ' . quest_answer_phase($answer, $course) . '<br />';
    $dates = array(
        'dateanswer' => $answer->date,
        'dateassess' => 0
    );
    $points = 0;
    if ($assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id))) {
        $dates['dateassess'] = $assessment->dateassessment;
        if ($assessment->state == 2) {
            $points = $assessment->pointsteacher;
        } else if ($assessment->state == 1) {
            $points = $assessment->pointsautor;
        }
    }
    foreach ($dates as $type => $date) {
        if ($date) {
            if ((($date == $dates['dateassess']) && ($answer->phase == 1)) || ($date == $dates['dateanswer'])) {
                $strdifference = format_time($date - time());
                if (($date - time()) < 0) {
                    $strdifference = "<font color=\"red\">$strdifference</font>";
                }
                $string .= '<b>' . get_string($type, 'quest') . '</b>: ' . userdate($date) . " ($strdifference)<br />";
            }
        }
    }
    $string .= '<b>' . get_string('pointsmax', 'quest') . ":&nbsp;&nbsp;" . number_format($answer->pointsmax, 4) . '</b><br>';
    if (($answer->phase == 1) || ($answer->phase == 2)) {
        $string .= '<b>' . get_string('points', 'quest') . ":&nbsp;&nbsp;$points" . '</b><br>';
    }
    echo $string;
    echo $OUTPUT->box_end();
}

/************************
 * 			  answer->phase
 * 							0			1					2
 * assessment->state	1	ungraded	graded autor		graded >0.5 autor
 * 						2				graded teacher		graded >0.5 teacher
 *
 * 					answer->phase
 * 							0			1
 * answer->state	1
 * 					2				modified
 * **************** */
function quest_answer_phase($answer, $course, $style = '') {

    global $USER, $DB;

    if ($answer->phase == ANSWER_PHASE_UNGRADED) {
        $string = get_string('phase1answer' . $style, 'quest');
    } else {


        $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id));

        if ($answer->phase == ANSWER_PHASE_GRADED) {
            if ($assessment->state == ASSESSMENT_STATE_BY_AUTOR) {
                $string = get_string('phase2answer' . $style, 'quest');
            } else if ($assessment->state == ASSESSMENT_STATE_BY_TEACHER) {
                $string = get_string('phase3answer' . $style, 'quest');
            }
            if ($answer->state == ANSWER_STATE_MODIFIED) {
                $string .= get_string('modificated', 'quest');
            }
        } else if ($answer->phase == ANSWER_PHASE_PASSED) {
            if ($assessment->state == ASSESSMENT_STATE_BY_AUTOR) {
                $string = get_string('phase4answer' . $style, 'quest');
            } else if ($assessment->state == ASSESSMENT_STATE_BY_TEACHER) {
                $string = get_string('phase5answer' . $style, 'quest');
            }
            if ($answer->state == ANSWER_STATE_MODIFIED) {
                $string .= " ".get_string('modified', 'quest');
            }
        }
        if ($assessment->phase == ASSESSMENT_PHASE_APPROVAL_PENDING) {
            if (!isset($string)) {
                $string = "*";
            } else {
                $string .= "*";
            }
        }
    }

    return $string;
}

/**
 * 
 * @global type $CFG
 * @global stdClass $USER
 * @global type $OUTPUT
 * @param type $quest
 * @param type $answer
 * @return type
 */
function quest_print_answer($quest, $answer) {
    // prints the answer with optional attachments
    global $CFG, $USER, $OUTPUT;

    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }

    $description = $answer->description;
    $context = context_module::instance($cm->id);

    $description = file_rewrite_pluginfile_urls($description, 'pluginfile.php', $context->id, 'mod_quest', 'answer', $answer->id);

    $options = new stdClass();
    $options->para = false;
    $options->trusted = $answer->descriptiontrust;
    $options->context = $context;
    $options->overflowdiv = true;
    $description = format_text($description, $answer->descriptionformat, $options);
    echo $OUTPUT->box($description);

    //echo $OUTPUT->box(format_text($answer->description), 'center');

    $ismanager = has_capability('mod/quest:manage', $context);

    if (!empty($answer->commentsforteacher)) {
        if (($answer->userid == $USER->id) || ($ismanager)) {
            echo $OUTPUT->heading(get_string('commentsforteacher', 'quest'));
            echo $OUTPUT->box(format_text($answer->commentsforteacher), 'center');
        }
    }
    if (!empty($answer->commentsteacher)) {
        if (($answer->userid == $USER->id) || ($ismanager)) {
            echo $OUTPUT->heading(get_string('commentsteacher', 'quest'));
            echo $OUTPUT->box(format_text($answer->commentsteacher), 'center');
        }
    }

    if ($quest->nattachments) {
        if ($answer->attachment) {
            quest_print_attachments($context, 'answer_attachment', $answer->id, 'timemodified');
        }
    }
    return;
}

/**
 *
 * @param stdClass $quest record
 * @param int $sid submissionid
 * @param stdClass $assessment
 * @param boolean $allowchanges
 * @param boolean $showcommentlinks
 * @param string $returnto
 */
function quest_print_assessment($quest, $sid, $assessment, $allowchanges = false, $showcommentlinks = false, $returnto = '') {

    global $CFG, $USER, $QUEST_SCALES, $QUEST_EWEIGHTS, $DB, $OUTPUT;
    if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
        print_error("course_misconfigured", 'quest');
    }
    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }
    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if ($assessment) {

        if (!$answer = $DB->get_record("quest_answers", array("id" => $assessment->answerid))) {
            error("Quest_print_assessment: Answer record not found");
        }
        if (!$submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid))) {
            error("Quest_print_assessment: Submission record not found");
        }

        echo $OUTPUT->heading(get_string('assessmentof', 'quest',
                        "<a href=\"answer.php?id=$cm->id&amp;sid=$submission->id&amp;action=showanswer&amp;aid=$answer->id\" target=\"submission\">" .
                        $answer->title . '</a>'));
    }

    $timenow = time();

    // reset the internal flags
    if ($assessment) {
        $showgrades = true;
    } else { // if no assessment, i.e. specimen grade form always show grading scales
        $showgrades = true;
    }

    echo "<center>\n";

    if (!isset($answer)) {
        $answer = new stdClass();
        $answer->id = -1;
    }
    // now print the grading form with the grading grade if any
    // FORM is needed for Mozilla browsers, else radio bttons are not checked
    ?>
    <form name="assessmentform" method="post" action="assessments.php"><input
            type="hidden" name="id" value="<?php echo $cm->id ?>" /> <input
            type="hidden" name="aid" value="<?php echo $answer->id ?>" /> <input
            type="hidden" name="sid" value="<?php echo $sid ?>" /> <input
            type="hidden" name="sesskey" value="<?php echo sesskey() ?>" /> <input
            type="hidden" name="action" value="updateassessment" /> <input
            type="hidden" name="returnto" value="<?php echo $returnto ?>" /> <input
            type="hidden" name="elementno" value="" /> <input type="hidden"
            name="stockcommentid" value="" />
    <?php
    echo '<center>
<table cellpadding="2" border="1">';

    echo "<tr valign=\"top\">\n";
    echo "  <td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
    if ($assessment) {
        if (($assessment->teacherid != 0)) {
            $user = $DB->get_record('user', array('id' => $assessment->teacherid));
            print_string("assessmentby", "quest", quest_fullname($user->id, $course->id));
        } else if ($assessment->userid != 0 && $ismanager) {
            $user = get_complete_user_data('id', $assessment->userid);
            print_string("assessmentby", "quest", quest_fullname($user->id, $course->id));
        } else if (($assessment->userid != 0) && ($assessment->userid == $USER->id) && !$ismanager) {
            $user = $DB->get_record('user', array('id' => $assessment->userid));
            print_string("assessmentby", "quest", quest_fullname($user->id, $course->id));
        } else {
            print_string('assessment', 'quest');
        }


        echo '</b><br />' . userdate($assessment->dateassessment) . "</center></td>\n";
        echo "</tr>\n";
    } else {
        print_string('assessment', 'quest');
    }

// get the assignment elements...
    if (($DB->count_records("quest_elements", array("submissionsid" => $sid))) == 0) {
        $condition = 0;
        $nelements = $quest->nelements;
    } else {
        $condition = $sid;
        if (isset($submissions->numelements)) {
            $nelements = $submissions->numelements;
        }
    }

    $elementsraw = $DB->get_records("quest_elements", array("submissionsid" => $condition), "elementno ASC");
    if (isset($nelements)) {
        if (count($elementsraw) < $nelements) {
            print_string("noteonassessmentelements", "quest");
        }
    }
    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            if ($element->questid == $quest->id) {
                $elements[] = $element;   // to renumber index 0,1,2...
            }
        }
    } else {
        $elements = null;
    }

    if ($assessment) {
        // get any previous grades...
        if ($gradesraw = $DB->get_records_select("quest_elements_assessments", "assessmentid = ?", array($assessment->id),
                "elementno")) {
            foreach ($gradesraw as $grade) {
                $grades[] = $grade;   // to renumber index 0,1,2...
            }
        }
    } else {
        // setup dummy grades array
        for ($i = 0; $i < count($elementsraw); $i++) { // gives a suitable sized loop
            $grades[$i] = new stdClass();
            $grades[$i]->answer = get_string("yourfeedbackgoeshere", "quest");
            $grades[$i]->calification = 0;
        }
    }
    if ($allowchanges == false)
        $enabled = "disabled=\"true\"";
    else
        $enabled = "";
// determine what sort of grading
    switch ($quest->gradingstrategy) {
        case 0:  // no grading
            // now print the form
            for ($i = 0; $i < count($elements); $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                echo "  <td>" . format_text($elements[$i]->description);
                echo "</td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                echo "  <td>\n";
                if ($allowchanges) {
                    echo "<textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                    if (isset($grades[$i]->answer)) {
                        echo $grades[$i]->answer;
                    }
                    echo "</textarea>\n";
                } else {
                    echo format_text($grades[$i]->answer);
                }
                echo "  </td>\n";
                echo "</tr>\n";

                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            break;

        case 1: // accumulative grading
            // now print the form
            for ($i = 0; $i < count($elements); $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                echo "  <td>" . format_text($elements[$i]->description);
                echo "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                number_format($QUEST_EWEIGHTS[$elements[$i]->weight], 2) . "</font></p>\n";
                echo "</td></tr>\n";
                if ($showgrades) {
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><p><b>" . get_string("grade") . ":</b></p></td>\n";
                    echo "  <td valign=\"top\">\n";

                    // get the appropriate scale
                    $scalenumber = $elements[$i]->scale;
                    $SCALE = (object) $QUEST_SCALES[$scalenumber];
                    switch ($SCALE->type) {
                        case 'radio' :
                            // show selections highest first
                            echo "<center><b>$SCALE->start</b>&nbsp;&nbsp;&nbsp;";
                            for ($j = $SCALE->size - 1; $j >= 0; $j--) {
                                $checked = false;
                                if (isset($grades[$i]->calification)) {
                                    if ($j == $grades[$i]->calification) {
                                        $checked = true;
                                    }
                                } else { // there's no previous grade so check the lowest option
                                    if ($j == 0) {
                                        $checked = true;
                                    }
                                }
                                if ($checked) {
                                    echo " <input type=\"radio\" $enabled name=\"grade[$i]\" value=\"$j\" checked=\"checked\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                } else {
                                    echo " <input type=\"radio\" $enabled name=\"grade[$i]\" value=\"$j\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                }
                            }
                            echo "&nbsp;&nbsp;&nbsp;<b>$SCALE->end</b></center>\n";
                            break;
                        case 'selection' :
                            unset($numbers);
                            for ($j = 0; $j <= $SCALE->size; $j++) {
                                $numbers[$j] = $j;
                            }
                            if (isset($grades[$i]->calification)) {
                                $selected = $grades[$i]->calification;
                            } else {
                                $selected = '';
                            }

                            //choose_from_menu($numbers, "grade[$i]", 0, "","",0,false,!$allowchanges);
                            echo html_writer::select($numbers, "grade[$i]", $selected, false,
                                    $allowchanges ? null : array('disabled' => 'true'));

                            break;
                    }

                    echo "  </td>\n";
                    echo "</tr>\n";
                }
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                echo "  <td>\n";
                if ($allowchanges) {

                    echo "<textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                    if (isset($grades[$i]->answer)) {
                        echo $grades[$i]->answer;
                    }
                    echo "</textarea>\n";
                } else {
                    if (isset($grades[$i]->answer)) {
                        echo format_text($grades[$i]->answer);
                    }
                }
                echo "</td>\n";
                echo "</tr>\n";

                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
            }
            break;

        case 2: // error banded grading
            // now run through the elements
            $negativecount = 0;
            for ($i = 0; $i < count($elements) - 1; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                echo "  <td>" . format_text($elements[$i]->description);
                echo "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                number_format($QUEST_EWEIGHTS[$elements[$i]->weight], 2) . "</font>\n";
                echo "</td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("grade") . ":</b></p></td>\n";
                echo "  <td valign=\"top\">\n";

                // get the appropriate scale - yes/no scale (0)
                $SCALE = (object) $QUEST_SCALES[0];
                switch ($SCALE->type) {
                    case 'radio' :
                        // show selections highest first
                        echo "<center><b>$SCALE->start</b>&nbsp;&nbsp;&nbsp;";
                        for ($j = $SCALE->size - 1; $j >= 0; $j--) {
                            $checked = false;
                            if (isset($grades[$i]->calification)) {
                                if ($j == $grades[$i]->calification) {
                                    $checked = true;
                                }
                            } else { // there's no previous grade so check the lowest option
                                if ($j == 0) {
                                    $checked = true;
                                }
                            }
                            if ($checked) {
                                echo " <input type=\"radio\" name=\"grade[$i]\" value=\"$j\" checked=\"checked\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                            } else {
                                echo " <input type=\"radio\" name=\"grade[$i]\" value=\"$j\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                            }
                        }
                        echo "&nbsp;&nbsp;&nbsp;<b>$SCALE->end</b></center>\n";
                        break;
                    case 'selection' :
                        unset($numbers);
                        for ($j = $SCALE->size; $j >= 0; $j--) {
                            $numbers[$j] = $j;
                        }
                        if (isset($grades[$i]->calification)) {
                            echo html_writer::select($numbers, "grade[$i]", $grades[$i]->calification, "");
                        } else {
                            echo html_writer::select($numbers, "grade[$i]", 0, "");
                        }
                        break;
                }

                echo "  </td>\n";
                echo "</tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                echo "  <td>\n";
                if ($allowchanges) {
                    echo "      <textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                    if (isset($grades[$i]->answer)) {
                        echo $grades[$i]->answer;
                    }
                    echo "</textarea>\n";
                } else {
                    if (isset($grades[$i]->answer)) {
                        echo format_text($grades[$i]->answer);
                    }
                }
                echo "&nbsp;</td>\n";
                echo "</tr>\n";

                echo "<tr valign=\"top\">\n";
                echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                echo "</tr>\n";
                if (empty($grades[$i]->calification)) {
                    $negativecount++;
                }
            }

            echo "</table></center>\n";
            // now print the grade table
            echo "<p><center><b>" . get_string("gradetable", "quest") . "</b></center>\n";
            echo "<center><table cellpadding=\"5\" border=\"1\"><tr><td align=\"CENTER\">" .
            get_string("numberofnegativeresponses", "quest");
            echo "</td><td>" . get_string("suggestedgrade", "quest") . "</td></tr>\n";
            for ($j = 100; $j >= 0; $j--) {
                $numbers[$j] = $j;
            }

            if ($DB->get_field("quest_submissions", "numelements", "id", $sid) == 0) {
                $num = $DB->get_field("quest", "nelements", array("id" => $quest->id));
            } else {
                $num = $DB->get_field("quest_submissions", "numelements", array("id" => $sid));
            }
            for ($i = 0; $i <= $num; $i++) {
                if ($i == $negativecount) {
                    echo "<tr><td align=\"CENTER\"><img src=\"" . $CFG->wwwroot . "pix/t/right.png\" alt=\"\" /> $i</td><td align=\"center\">{$elements[$i]->maxscore}</td></tr>\n";
                } else {
                    echo "<tr><td align=\"CENTER\">$i</td><td align=\"CENTER\">{$elements[$i]->maxscore}</td></tr>\n";
                }
            }
            echo "</table></center>\n";
            echo "<p><center><table cellpadding=\"5\" border=\"1\"><tr><td><b>" . get_string("optionaladjustment", "quest") . "</b></td><td>\n";
            unset($numbers);
            for ($j = 20; $j >= -20; $j--) {
                $numbers[$j] = $j;
            }
            if (isset($grades[$quest->nelements]->calification)) {
                echo html_writer::select($numbers, "grade[$quest->nelements]", $grades[$quest->nelements]->calification, "");
            } else {
                echo html_writer::select($numbers, "grade[$quest->nelements]", 0, "");
            }
            echo "</td></tr>\n";
            break;

        case 3: // criteria grading
            echo "<tr valign=\"top\">\n";
            echo "  <td class=\"workshopassessmentheading\">&nbsp;</td>\n";
            echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("criterion", "quest") . "</b></td>\n";
            echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("select", "quest") . "</b></td>\n";
            echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("suggestedgrade", "quest") . "</b></td>\n";
            // find which criteria has been selected (saved in the zero element), if any
            if (isset($grades[0]->calification)) {
                $selection = $grades[0]->calification;
            } else {
                $selection = 0;
            }
            // now run through the elements
            for ($i = 0; $i < count($elements); $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td>$iplus1</td><td>" . format_text($elements[$i]->description) . "</td>\n";
                if ($selection == $i) {
                    echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[0]\" value=\"$i\" checked=\"checked\" alt=\"$i\" /></td>\n";
                } else {
                    echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[0]\" value=\"$i\" alt=\"$i\" /></td>\n";
                }
                echo "<td align=\"center\">{$elements[$i]->maxscore}</td></tr>\n";
            }
            echo "</table></center>\n";
            echo "<p><center><table cellpadding=\"5\" border=\"1\"><tr><td><b>" . get_string("optionaladjustment", "quest") . "</b></td><td>\n";
            unset($numbers);
            for ($j = 20; $j >= -20; $j--) {
                $numbers[$j] = $j;
            }
            if (isset($grades[1]->calification)) {
                echo html_writer::select($numbers, "grade[1]", $grades[1]->calification, "");
            } else {
                echo html_writer::select($numbers, "grade[1]", 0, "");
            }
            echo "</td></tr>\n";
            break;

        case 4: // rubric grading
            // now run through the elements...
            for ($i = 0; $i < count($elements); $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "<td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                echo "<td>" . format_text($elements[$i]->description) .
                "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                number_format($QUEST_EWEIGHTS[$elements[$i]->weight], 2) . "</font></td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td class=\"workshopassessmentheading\" align=\"center\"><b>" . get_string("select", "quest") .
                "</b></td>\n";
                echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("criterion", "quest") .
                "</b></td></tr>\n";
                if (isset($grades[$i])) {
                    $selection = $grades[$i]->calification;
                } else {
                    $selection = 0;
                }
                // ...and the rubrics
                if ($DB->count_records("quest_rubrics", "questid", $quest->id, "submissionsid", $sid) == 0) {
                    $var = 0;
                } else {
                    $var = $sid;
                }
                if ($rubricsraw = $DB->get_records_select("quest_rubrics",
                        "questid = ? AND
                        elementno = ? AND submissionsid = ?", array($quest->id, $i, $var), "rubricno ASC")) {
                    unset($rubrics);
                    foreach ($rubricsraw as $rubic) {
                        $rubrics[] = $rubic;   // to renumber index 0,1,2...
                    }
                    for ($j = 0; $j < 5; $j++) {
                        if (empty($rubrics[$j]->description)) {
                            break; // out of inner for loop
                        }
                        echo "<tr valign=\"top\">\n";
                        if ($selection == $j) {
                            echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[$i]\" value=\"$j\"
                                checked=\"checked\" alt=\"$j\" /></td>\n";
                        } else {
                            echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[$i]\" value=\"$j\"
                                alt=\"$j\" /></td>\n";
                        }
                        echo "<td>" . format_text($rubrics[$j]->description) . "</td>\n";
                    }
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                    echo "  <td>\n";
                    if ($allowchanges) {
                        echo "      <textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                        if (isset($grades[$i]->answer)) {
                            echo $grades[$i]->answer;
                        }
                        echo "</textarea>\n";
                    } else {
                        echo format_text($grades[$i]->answer);
                    }
                    echo "  </td>\n";
                    echo "</tr>\n";

                    echo "<tr valign=\"top\">\n";
                    echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                    echo "</tr>\n";
                }
            }
            break;
    } // end of outer switch
// now get the general comment (present in all types)
    echo "<tr valign=\"top\">\n";
    switch ($quest->gradingstrategy) {
        case 0:
        case 1:
        case 4 : // no grading, accumulative and rubic
            echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . ":</b></p></td>\n";
            break;
        default :
            echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . "/<br />" .
            get_string("reasonforadjustment", "quest") . ":</b></p></td>\n";
    }
    echo "  <td>\n";
    quest_print_general_comment_box($course, $allowchanges, $assessment);


    echo "&nbsp;</td>\n";
    echo "</tr>\n";

    if (!$ismanager) {
        if (!empty($assessment->commentsteacher)) {
            echo "<tr valign=\"top\">\n";
            echo "  <td align=\"right\"><p><b>" . get_string("commentsteacher", "quest") . ":</b></p></td>\n";
            echo "  <td>\n";
            echo format_text($assessment->commentsteacher);
            echo "&nbsp;</td>\n";
            echo "</tr>\n";
        }
    } else {
        if (!empty($assessment->commentsforteacher)) {
            echo "<tr valign=\"top\">\n";
            echo "  <td align=\"right\"><p><b>" . get_string("commentsautor", "quest") . ":</b></p></td>\n";
            echo "  <td>\n";
            echo format_text($assessment->commentsforteacher);
            echo "&nbsp;</td>\n";
            echo "</tr>\n";
        }
    }

    $timenow = time();
// now show the grading grade if available...
    if (isset($assessment->state)) {
        echo "<tr valign=\"top\">\n";
        echo "<td colspan=\"2\" class=\"workshopassessmentheading\" align=\"center\"><b>" .
        get_string('assessmentglobal', 'quest') . "</b></td>\n";
        echo "</tr>\n";

        if ($assessment->state == 2) {
            if (!empty($assessment->pointsautor)) {
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>";
                print_string('gradeautor', 'quest');
                echo ":</b></p></td><td>\n";
                //		  if($answer->pointsmax ==0)
                //			echo number_format($assessment->pointsautor, 4).' ('.get_string('phase4submission','quest').')';
                //				else
                {
                    $perct = $assessment->pointsmax == 0 ? 0 : $assessment->pointsautor / $assessment->pointsmax;
                    echo number_format($perct, 1) . '% (';
                    echo number_format($assessment->pointsautor, 4);
                    echo ' ' . get_string('of', 'quest') . ' ' . number_format($answer->pointsmax, 4) . ') ';
                }
                echo "&nbsp;</td>\n";
                echo "</tr>\n";
            }
            echo "<tr valign=\"top\">\n";
            echo "  <td align=\"right\"><p><b>";
            print_string('grade', 'quest');
            echo ":</b></p></td><td>\n";
            //		  if($answer->pointsmax ==0)
            //			echo number_format($assessment->pointsteacher , 4).' ('.get_string('phase4submission','quest').')';
            //				else
            {
                echo number_format($answer->grade, 1) . '% (';
                echo number_format($assessment->pointsteacher, 4);

                echo ' ' . get_string('of', 'quest') . ' ' . number_format($answer->pointsmax, 4) . ') ';
            }
        }
        if ($assessment->state == 1) {
            echo "<tr valign=\"top\">\n";
            echo "  <td align=\"right\"><p><b>";
            print_string('grade', 'quest');
            echo ":</b></p></td><td>\n";

            echo number_format($assessment->pointsautor, 4);
            if ($answer->pointsmax == 0)
                echo ' ' . get_string('phase4submission', 'quest') . ')';
            else
                echo ' ' . get_string('of', 'quest') . ' (' . number_format($answer->pointsmax, 4) . ')';
        }
        echo "&nbsp;</td>\n";
        echo "</tr>\n";
    }


    /*
     * Manual Grading Form
     *
     *
     */
    if ($allowchanges == true) {
        echo "<tr valign=\"top\">\n";
        echo "<td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
        echo get_string('changemanualcalification', 'quest') . '</b></center></td></tr>';
        echo "<tr valign=\"top\">";
        echo "<td align=\"right\"><p><b>" . get_string('newcalification', 'quest') . ": </b></p></td>\n";
        echo "<td><input size=\"3\" maxlength=\"3\" name=\"manualcalification\" type=\"text\">%</td></tr>";
    }

// ...and close the table, show submit button if needed...
    echo "</table>\n";
    if ($assessment) {
        if ($allowchanges) {
            echo "<input type=\"submit\" value=\"" . get_string("savemyassessment", "quest") . "\" />\n";
        }
    }

    echo "</center>";
    echo "</form>\n";
}

/**
 *
 */
function quest_print_general_comment_box($course, $allowchanges, $assessment) {
    $context = context_course::instance($course->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if ($ismanager) {
        if ($allowchanges) {
            echo "      <textarea name=\"generalcomment\" rows=\"5\" cols=\"75\" >\n";
            if (isset($assessment->commentsteacher)) {
                echo $assessment->commentsteacher;
            }
            echo "</textarea>\n";
        } else {
            if ($assessment) {
                if (isset($assessment->commentsteacher)) {
                    echo format_text($assessment->commentsteacher);
                }
            } else {
                print_string("yourfeedbackgoeshere", "quest");
            }
        }
    } else {
        if ($allowchanges) {
            echo "      <textarea name=\"generalteachercomment\" rows=\"5\" cols=\"75\" >\n";
            if (isset($assessment->commentsforteacher)) {
                echo $assessment->commentsforteacher;
            }
            echo "</textarea>\n";
        } else {
            if ($assessment) {
                if (isset($assessment->commentsforteacher)) {
                    echo format_text($assessment->commentsforteacher);
                }
            } else {
                print_string("yourfeedbackgoeshere", "quest");
            }
        }
    }
}

/**
 *
 * Calculate a percentual grade for an answer.
 *
 */
function quest_get_answer_grade($quest, $answer, $form) {
    global $QUEST_EWEIGHTS, $DB;


    if (!$submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid))) {
        error("quest submission is misconfigured");
    }


    $sid = $answer->submissionid;

    if (!$assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id))) {
        error("quest assessment is misconfigured");
    }

    if ($DB->count_records("quest_elements", array("submissionsid" => $submission->id, "questid" => $quest->id)) == 0) {
        $id_submission = 0;
        $are_general_elements = true;
        $num = $DB->get_field("quest", "nelements", array("id" => $quest->id));
    } else {
        $are_general_elements = false;
        $id_submission = $submission->id;
        $num = $DB->get_field("quest_submissions", "numelements", array("id" => $submission->id));
    }



    // first get the assignment elements for maxscores and weights...
    // Puede ser $id_submission==0 (elementos generales) o algï¿½n $sid (elementos especï¿½ficos)
    $select = "submissionsid=? AND questid=?";
    $params = array($id_submission, $quest->id);
    $elementsraw = $DB->get_records_select("quest_elements", $select, $params, "elementno ASC");
// 	if (count($elementsraw) < $num)
//     {
// 		print_string("noteonassessmentelements", "quest");
// 	}


    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            $elements[] = $element;   // to renumber index 0,1,2...
        }
    } else {
        $elements = null;
    }
    $percent = 0;

    // don't fiddle about, delete all the old and add the new!
    $DB->delete_records("quest_elements_assessments", array("assessmentid" => $assessment->id));

    switch ($quest->gradingstrategy) {
        case 0: // no grading
            // Insert all the elements that contain something
            //                if ($DB->get_field("quest_submissions", "numelements", "id", $submission->id)==0)
            //                {
            //                	$num = $DB->get_field("quest", "nelements", "id", $quest->id);
            //                }
            //                else
            //                {
            //                	$num = $DB->get_field("quest_submissions", "numelements", "id", $submission->id);
            //                }
            for ($i = 0; $i < $num; $i++) {
                if (!isset($form->{"feedback_$i"}))
                    continue;

                $element = new stdClass();
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $i;
                $element->answer = $form->{"feedback_$i"};
                $element->commentteacher = '';

                if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                    error("Could not insert quest grade!");
                }
            }

            $percent = 0;
            break;

        case 1: // accumulative grading
            // Insert all the elements that contain something

            foreach ($form->grade as $key => $thegrade) {
                $element = new stdclass();
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $key;
                $element->answer = $form->{"feedback_$key"};
                $element->calification = $thegrade;
                $element->commentteacher = '';

                if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                    error("Could not insert quest grade!");
                }
            }
            // now work out the grade...
            $rawgrade = 0;
            $totalweight = 0;
            foreach ($form->grade as $key => $grade) {

                if (($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => $sid))) == 0) {
                    $var = 0;
                } else {
                    $var = $sid;
                }
                $maxscore = $DB->get_field("quest_elements", "maxscore",
                        array("questid" => $quest->id, "submissionsid" => $var, "elementno" => $key));
                $weight = $QUEST_EWEIGHTS[$elements[$key]->weight];
                if ($weight > 0) {
                    $totalweight += $weight;
                }

                $rawgrade += ($grade / $maxscore) * $weight;
            }

            /**
             * Process grade into quest assesment
             */
            $percent = ($rawgrade / $totalweight);
            break;

        case 2: // error banded graded
            // Insert all the elements that contain something
            $error = 0.0;
            if ($DB->get_field("quest_submissions", "numelements", array("id" => $submission->id)) == 0) {
                $num = $DB->get_field("quest", "nelements", array("id" => $quest->id));
            } else {
                $num = $DB->get_field("quest_submissions", "numelements", array("id" => $submission->id));
            }
            for ($i = 0; $i < $num; $i++) {
                unset($element);
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $i;
                $element->answer = $form->{"feedback_$i"};
                $element->calification = $form->grade[$i];
                if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                    error("Could not insert quest grade!");
                }
                if (empty($form->grade[$i])) {
                    $error += $QUEST_EWEIGHTS[$elements[$i]->weight];
                }
            }
            // now save the adjustment
            unset($element);

            $i = $num;
            $element->questid = $quest->id;
            $element->assessmentid = $assessment->id;
            $element->elementno = $i;
            $element->calification = $form->grade[$i];
            if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                error("Could not insert quest grade!");
            }

            $rawgrade = ($elements[intval($error + 0.5)]->maxscore + $form->grade[$i]);
            // do sanity check
            if ($rawgrade < 0) {
                $rawgrade = 0;
            } else if ($rawgrade > $quest->maxcalification) {
                $rawgrade = $quest->maxcalification;
            }
            echo "<b>" . get_string("weightederrorcount", "quest", intval($error + 0.5)) . "</b>\n";



            $percent = ($rawgrade / $quest->maxcalification);

            break;

        case 3: // criteria grading
            // save in the selected criteria value in element zero,
            unset($element);
            $element->questid = $quest->id;
            $element->assessmentid = $assessment->id;
            $element->elementno = 0;
            $element->calification = $form->grade[0];
            if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                error("Could not insert quest grade!");
            }
            // now save the adjustment in element one
            unset($element);
            $element->questid = $quest->id;
            $element->assessmentid = $assessment->id;
            $element->elementno = 1;
            $element->calification = $form->grade[1];
            if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                error("Could not insert quest grade!");
            }
            //$rawgrade = ($elements[$form->grade[0]]->maxscore + $form->grade[1]);
            if (($DB->count_records("quest_elements", array("questid" => $quest->id, "submissionsid" => $sid))) == 0) {
                $var = 0;
            } else {
                $var = $sid;
            }
            $rawgrade = ($DB->get_field("quest_elements", "maxscore",
                            array("elementno" => $form->grade[0], "questid" => $quest->id, "submissionsid" => $var)) + $form->grade[1]);
            $percent = ($rawgrade / $quest->maxcalification);
            break;

        case 4: // rubric grading (identical to accumulative grading)
            // Insert all the elements that contain something
            foreach ($form->grade as $key => $thegrade) {
                unset($element);
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $key;
                $element->answer = $form->{"feedback_$key"};
                $element->calification = $thegrade;
                if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                    error("Could not insert quest grade!");
                }
            }
            // now work out the grade...
            $rawgrade = 0;
            $totalweight = 0;
            foreach ($form->grade as $key => $grade) {
                //$maxscore = $elements[$key]->maxscore;
                $maxscore = 4;
                $weight = $QUEST_EWEIGHTS[$elements[$key]->weight];
                if ($weight > 0) {
                    $totalweight += $weight;
                }
                $rawgrade += ($grade / $maxscore) * $weight;
            }


            $percent = ($rawgrade / $totalweight);

            break;
    } // end of switch
    //print ("Grading answer $answer->id obtaining $percent.");
    return $percent;
}

//////////////////////////////////////////////////////////////
function quest_get_points($submission, $quest, $answer = '') {

    if (empty($answer)) {
        $timenow = time();
    } else {
        $timenow = $answer->date;
    }
    $grade = 0;

    $initialpoints = $submission->initialpoints;
    $nanswerscorrect = $submission->nanswerscorrect;
    $datestart = $submission->datestart;
    $dateend = $submission->dateend;
    $dateanswercorrect = $submission->dateanswercorrect;
    $pointsmax = $submission->pointsmax;

    $tinitial = $quest->tinitial * 86400;
    $type = $quest->typecalification;
    $nmaxanswers = $quest->nmaxanswers;
    $pointsnmaxanswers = $submission->points;
    $state = $submission->state;


    if ($state < 2) {
        $grade = $initialpoints;
    } else {
        $grade = quest_calculate_points($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax,
                $type);
    }

    return $grade;
}

function quest_calculate_points($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax, $type) {

    if (($dateend - $datestart - $tinitial) == 0) {
        $incline = 0;
    } else {
        if ($type == 0) {
            $incline = ($pointsmax - $initialpoints) / ($dateend - $datestart - $tinitial);
        } else {
            if ($initialpoints == 0) {
                $initialpoints = 0.0001;
            }
            $incline = (1 / ($dateend - $datestart - $tinitial)) * log($pointsmax / $initialpoints);
        }
    }

    //print("<p> tinitial $tinitial  timenow $timenow  d+ti:".($datestart+$tinitial)." dacorr: $dateanswercorrect");

    if ($timenow < $datestart) { // start pending
        $grade = $initialpoints;
    } else
    if ($dateend < $timenow) {
        $grade = 0;
    } else
    // stationary score
    if ($timenow < ($datestart + $tinitial) && ($dateanswercorrect == 0 || $timenow <= $dateanswercorrect)) {
        $grade = $initialpoints;
    } else
    // inflationary score
    if ($dateanswercorrect == 0 || $timenow <= $dateanswercorrect) {//there is no inflexion point
        $t = $timenow - $datestart;
        if ($type == 0)
            $grade = ($t - $tinitial) * $incline + $initialpoints;
        else
            $grade = $initialpoints * exp($incline * ($t - $tinitial));
    }
    else {
    // deflactionary score: is in decreasing zone
        $t = $timenow - $dateanswercorrect;
//print("dt $t type $type");
        if ($type == 0) {
            if ($dateanswercorrect <= $datestart + $tinitial) {
                // correct answer is in stationary part
                $pointscorrect = $initialpoints;
            } else {
                // correct answer was in inflactionary part
                $pointscorrect = $incline * ($dateanswercorrect - $datestart - $tinitial) + $initialpoints;
            }
//print("points correct $pointscorrect");
            $incline2 = $pointscorrect / ($dateend - $dateanswercorrect);
            $grade = $pointscorrect - $incline2 * $t;
        } else {//type =1 deprecated
            if ($dateanswercorrect <= $datestart + $tinitial)
                $pointscorrect = $initialpoints;
            else
                $pointscorrect = $initialpoints * exp($incline * ($dateanswercorrect - $datestart - $tinitial));
            $incline2 = (1 / ($dateend - $dateanswercorrect)) * log(0.0001 / $pointscorrect);
            $grade = $pointsanswercorrect * exp($incline2 * $t);
        }
    }

    if ($grade < 0) {
        $grade = 0;
    }
    return $grade;
}

//////////////////////////////////////////////////////////
function quest_print_assessment_autor($quest, $assessment = false, $allowchanges = false, $showcommentlinks = false,
        $returnto = '') {

    global $CFG, $USER, $QUEST_SCALES, $QUEST_EWEIGHTS, $DB, $OUTPUT;

    if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
        print_error("course_misconfigured", 'quest');
    }
    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
        print_error("CourseModuleIDwasincorrect", 'quest'); ;
    }

    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if ($assessment) {

        if (!$submission = $DB->get_record("quest_submissions", array("id" => $assessment->submissionid))) {
            error("Quest_print_assessment: Submission record not found");
        }

        echo $OUTPUT->heading(get_string('assessmentof', 'quest',
                        "<a href=\"submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\" target=\"submission\">" .
                        $submission->title . '</a>'));
    }

    $timenow = time();

    // reset the internal flags
    if ($assessment) {
        $showgrades = true;
    } else { // if no assessment, i.e. specimen grade form always show grading scales
        $showgrades = true;
    }

    if ($assessment) {
        // set the internal flag if necessary
        if ($allowchanges) {
            $showgrades = true;
        }

        echo "<center>\n";
    }

    if (!$assessment) {
        $assessment = new stdClass();
        $assessment->id = false;
        $assessment->userid = 0;
        $assessment->dateassessment = null;
    }
    // now print the grading form with the grading grade if any
    // FORM is needed for Mozilla browsers, else radio bttons are not checked
    ?>
        <form name="assessmentform" method="post"
              action="assessments_autors.php"><input type="hidden" name="id"
                                               value="<?php echo $cm->id ?>" /> <input type="hidden" name="aid"
                                               value="<?php echo $assessment->id ?>" /> <input type="hidden"
                                               name="action" value="updateassessment" /> <input type="hidden"
                                               name="returnto" value="<?php echo $returnto ?>" /> <input
                                               type="hidden" name="elementno" value="" /> <input type="hidden"
                                               name="stockcommentid" value="" />
            <center>
                <table cellpadding="2" border="1">
            <?php
            echo "<tr valign=\"top\">\n";
            echo "  <td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
            if ($assessment) {
                if ($assessment->userid != 0) {
                    $user = $DB->get_record('user', array('id' => $assessment->userid));
                    print_string("assessmentby", "quest", quest_fullname($user->id, $course->id));
                } else {
                    print_string('assessment', 'quest');
                }
            }

            if ($assessment->dateassessment != null) {
                echo '</b><br />' . userdate($assessment->dateassessment) . "</center></td>\n";
            }
            echo "</tr>\n";

            // get the assignment elements...
            $elementsraw = $DB->get_records("quest_elementsautor", array("questid" => $quest->id), "elementno ASC");
            if (count($elementsraw) < $quest->nelementsautor) {
                print_string("noteonassessmentelements", "quest");
            }
            if ($elementsraw) {
                foreach ($elementsraw as $element) {
                    $elements[] = $element;   // to renumber index 0,1,2...
                }
            } else {
                $elements = null;
            }
            $grades = array();
            if ($assessment) {
                // get any previous grades...
                if ($gradesraw = $DB->get_records("quest_items_assesments_autor", array("assessmentautorid" => $assessment->id),
                        "elementno")) {
                    foreach ($gradesraw as $grade) {
                        $grades[] = $grade;   // to renumber index 0,1,2...
                    }
                }
            }
            $num_elements = min(count($elementsraw), $quest->nelementsautor);

            if (empty($grades)) {
                // setup dummy grades array
                for ($i = 0; $i < $num_elements; $i++) { // gives a suitable sized loop
                    $grades[$i] = new stdClass();
                    $grades[$i]->answer = ''; get_string("yourfeedbackgoeshere", "quest");
                    $grades[$i]->calification = 0;
                }
            }
            // determine what sort of grading
            switch ($quest->gradingstrategyautor) {
                case 0:  // no grading
                    // now print the form
                    for ($i = 0; $i < $num_elements; $i++) {
                        $iplus1 = $i + 1;
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                        echo "  <td>" . format_text($elements[$i]->description);
                        echo "</td></tr>\n";
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                        echo "  <td>\n";
                        if ($allowchanges) {
                            echo "      <textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                            if (isset($grades[$i]->answer)) {
                                echo $grades[$i]->answer;
                            }
                            echo "</textarea>\n";
                        } else {
                            echo format_text($grades[$i]->answer);
                        }
                        echo "  </td>\n";
                        echo "</tr>\n";


                        echo "<tr valign=\"top\">\n";
                        echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                        echo "</tr>\n";
                    }
                    break;

                case 1: // accumulative grading
                    // now print the form
                    for ($i = 0; $i < $num_elements; $i++) {
                        $iplus1 = $i + 1;
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                        echo "  <td>" . format_text($elements[$i]->description);
                        echo "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                        number_format($QUEST_EWEIGHTS[$elements[$i]->weight], 2) . "</font></p>\n";
                        echo "</td></tr>\n";
                        if ($showgrades) {
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><p><b>" . get_string("grade") . ":</b></p></td>\n";
                            echo "  <td valign=\"top\">\n";

                            // get the appropriate scale
                            $scalenumber = $elements[$i]->scale;
                            $SCALE = (object) $QUEST_SCALES[$scalenumber];
                            switch ($SCALE->type) {
                                case 'radio' :
                                    // show selections highest first
                                    echo "<center><b>$SCALE->start</b>&nbsp;&nbsp;&nbsp;";
                                    for ($j = $SCALE->size - 1; $j >= 0; $j--) {
                                        $checked = false;
                                        if (isset($grades[$i]->calification)) {
                                            if ($j == $grades[$i]->calification) {
                                                $checked = true;
                                            }
                                        } else { // there's no previous grade so check the lowest option
                                            if ($j == 0) {
                                                $checked = true;
                                            }
                                        }
                                        if ($checked) {
                                            echo " <input type=\"radio\" name=\"grade[$i]\" value=\"$j\" checked=\"checked\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                        } else {
                                            echo " <input type=\"radio\" name=\"grade[$i]\" value=\"$j\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                        }
                                    }
                                    echo "&nbsp;&nbsp;&nbsp;<b>$SCALE->end</b></center>\n";
                                    break;
                                case 'selection' :
                                    unset($numbers);
                                    for ($j = $SCALE->size; $j >= 0; $j--) {
                                        $numbers[$j] = $j;
                                    }
                                    if (isset($grades[$i]->calification)) {
                                        echo html_writer::select($numbers, "grade[$i]", $grades[$i]->calification, "");
                                    } else {
                                        echo html_writer::select($numbers, "grade[$i]", 0, "");
                                    }
                                    break;
                            }

                            echo "  </td>\n";
                            echo "</tr>\n";
                        }
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                        echo "  <td>\n";
                        if ($allowchanges) {
                            echo "      <textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                            if (isset($grades[$i]->answer)) {
                                echo $grades[$i]->answer;
                            }
                            echo "</textarea>\n";
                        } else {
                            if (isset($grades[$i]->answer)) {
                                echo format_text($grades[$i]->answer);
                            }
                        }
                        echo "  </td>\n";
                        echo "</tr>\n";


                        echo "<tr valign=\"top\">\n";
                        echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                        echo "</tr>\n";
                    }
                    break;

                case 2: // error banded grading
                    // now run through the elements
                    $negativecount = 0;
                    for ($i = 0; $i < $num_elements; $i++) {
                        $iplus1 = $i + 1;
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                        echo "  <td>" . format_text($elements[$i]->description);
                        echo "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                        number_format($QUEST_EWEIGHTS[$elements[$i]->weight], 2) . "</font>\n";
                        echo "</td></tr>\n";
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("grade") . ":</b></p></td>\n";
                        echo "  <td valign=\"top\">\n";

                        // get the appropriate scale - yes/no scale (0)
                        $SCALE = (object) $QUEST_SCALES[0];
                        switch ($SCALE->type) {
                            case 'radio' :
                                // show selections highest first
                                echo "<center><b>$SCALE->start</b>&nbsp;&nbsp;&nbsp;";
                                for ($j = $SCALE->size - 1; $j >= 0; $j--) {
                                    $checked = false;
                                    if (isset($grades[$i]->calification)) {
                                        if ($j == $grades[$i]->calification) {
                                            $checked = true;
                                        }
                                    } else { // there's no previous grade so check the lowest option
                                        if ($j == 0) {
                                            $checked = true;
                                        }
                                    }
                                    if ($checked) {
                                        echo " <input type=\"radio\" name=\"grade[$i]\" value=\"$j\" checked=\"checked\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                    } else {
                                        echo " <input type=\"radio\" name=\"grade[$i]\" value=\"$j\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                    }
                                }
                                echo "&nbsp;&nbsp;&nbsp;<b>$SCALE->end</b></center>\n";
                                break;
                            case 'selection' :
                                unset($numbers);
                                for ($j = $SCALE->size; $j >= 0; $j--) {
                                    $numbers[$j] = $j;
                                }
                                if (isset($grades[$i]->calification)) {
                                    echo html_writer::select($numbers, "grade[$i]", $grades[$i]->calification, "");
                                } else {
                                    echo html_writer::select($numbers, "grade[$i]", 0, "");
                                }
                                break;
                        }

                        echo "  </td>\n";
                        echo "</tr>\n";
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                        echo "  <td>\n";
                        if ($allowchanges) {
                            echo "      <textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                            if (isset($grades[$i]->answer)) {
                                echo $grades[$i]->answer;
                            }
                            echo "</textarea>\n";
                        } else {
                            if (isset($grades[$i]->answer)) {
                                echo format_text($grades[$i]->answer);
                            }
                        }
                        echo "&nbsp;</td>\n";
                        echo "</tr>\n";


                        echo "<tr valign=\"top\">\n";
                        echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                        echo "</tr>\n";
                        if (empty($grades[$i]->calification)) {
                            $negativecount++;
                        }
                    }

                    echo "</table></center>\n";
                    // now print the grade table
                    echo "<p><center><b>" . get_string("gradetable", "quest") . "</b></center>\n";
                    echo "<center><table cellpadding=\"5\" border=\"1\"><tr><td align=\"CENTER\">" .
                    get_string("numberofnegativeresponses", "quest");
                    echo "</td><td>" . get_string("suggestedgrade", "quest") . "</td></tr>\n";
                    for ($j = 100; $j >= 0; $j--) {
                        $numbers[$j] = $j;
                    }
                    for ($i = 0; $i <= $num_elements; $i++) {
                        if ($i == $negativecount) {
                            echo "<tr><td align=\"CENTER\"><img src=\"" . $CFG->wwwroot . "pix/t/right.png\" alt=\"\" /> $i</td><td align=\"center\">{$elements[$i]->maxscore}</td></tr>\n";
                        } else {
                            echo "<tr><td align=\"CENTER\">$i</td><td align=\"CENTER\">{$elements[$i]->maxscore}</td></tr>\n";
                        }
                    }
                    echo "</table></center>\n";
                    echo "<p><center><table cellpadding=\"5\" border=\"1\"><tr><td><b>" . get_string("optionaladjustment", "quest") . "</b></td><td>\n";
                    unset($numbers);
                    for ($j = 20; $j >= -20; $j--) {
                        $numbers[$j] = $j;
                    }
                    if (isset($grades[$quest->nelements]->calification)) {
                        echo html_writer::select($numbers, "grade[$quest->nelements]", $grades[$quest->nelements]->calification,
                                "");
                    } else {
                        echo html_writer::select($numbers, "grade[$quest->nelements]", 0, "");
                    }
                    echo "</td></tr>\n";
                    break;

                case 3: // criteria grading
                    echo "<tr valign=\"top\">\n";
                    echo "  <td class=\"workshopassessmentheading\">&nbsp;</td>\n";
                    echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("criterion", "quest") . "</b></td>\n";
                    echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("select", "quest") . "</b></td>\n";
                    echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("suggestedgrade", "quest") . "</b></td>\n";
                    // find which criteria has been selected (saved in the zero element), if any
                    if (isset($grades[0]->calification)) {
                        $selection = $grades[0]->calification;
                    } else {
                        $selection = 0;
                    }
                    // now run through the elements
                    for ($i = 0; $i < count($elements); $i++) {
                        $iplus1 = $i + 1;
                        echo "<tr valign=\"top\">\n";
                        echo "  <td>$iplus1</td><td>" . format_text($elements[$i]->description) . "</td>\n";
                        if ($selection == $i) {
                            echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[0]\" value=\"$i\" checked=\"checked\" alt=\"$i\" /></td>\n";
                        } else {
                            echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[0]\" value=\"$i\" alt=\"$i\" /></td>\n";
                        }
                        echo "<td align=\"center\">{$elements[$i]->maxscore}</td></tr>\n";
                    }
                    echo "</table></center>\n";
                    echo "<p><center><table cellpadding=\"5\" border=\"1\"><tr><td><b>" . get_string("optionaladjustment", "quest") . "</b></td><td>\n";
                    unset($numbers);
                    for ($j = 20; $j >= -20; $j--) {
                        $numbers[$j] = $j;
                    }
                    if (isset($grades[1]->calification)) {
                        echo html_writer::select($numbers, "grade[1]", $grades[1]->calification, "");
                    } else {
                        echo html_writer::select($numbers, "grade[1]", 0, "");
                    }
                    echo "</td></tr>\n";
                    break;

                case 4: // rubric grading
                    // now run through the elements...
                    for ($i = 0; $i < $num_elements; $i++) {
                        $iplus1 = $i + 1;
                        echo "<tr valign=\"top\">\n";
                        echo "<td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
                        echo "<td>" . format_text($elements[$i]->description) .
                        "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                        number_format($QUEST_EWEIGHTS[$elements[$i]->weight], 2) . "</font></td></tr>\n";
                        echo "<tr valign=\"top\">\n";
                        echo "  <td class=\"workshopassessmentheading\" align=\"center\"><b>" . get_string("select", "quest") .
                        "</b></td>\n";
                        echo "  <td class=\"workshopassessmentheading\"><b>" . get_string("criterion", "quest") .
                        "</b></td></tr>\n";
                        if (isset($grades[$i])) {
                            $selection = $grades[$i]->calification;
                        } else {
                            $selection = 0;
                        }
                        // ...and the rubrics
                        if ($rubricsraw = $DB->get_records_select("quest_rubrics_autor",
                                "questid = ? AND
                        elementno = ?", array($quest->id, $i), "rubricno ASC")) {
                            unset($rubrics);
                            foreach ($rubricsraw as $rubic) {
                                $rubrics[] = $rubic;   // to renumber index 0,1,2...
                            }
                            for ($j = 0; $j < 5; $j++) {
                                if (empty($rubrics[$j]->description)) {
                                    break; // out of inner for loop
                                }
                                echo "<tr valign=\"top\">\n";
                                if ($selection == $j) {
                                    echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[$i]\" value=\"$j\"
                                checked=\"checked\" alt=\"$j\" /></td>\n";
                                } else {
                                    echo "  <td align=\"center\"><input type=\"radio\" name=\"grade[$i]\" value=\"$j\"
                                alt=\"$j\" /></td>\n";
                                }
                                echo "<td>" . format_text($rubrics[$j]->description) . "</td>\n";
                            }
                            echo "<tr valign=\"top\">\n";
                            echo "  <td align=\"right\"><p><b>" . get_string("feedback") . ":</b></p></td>\n";
                            echo "  <td>\n";
                            if ($allowchanges) {
                                echo "      <textarea name=\"feedback_$i\" rows=\"3\" cols=\"75\" >\n";
                                if (isset($grades[$i]->answer)) {
                                    echo $grades[$i]->answer;
                                }
                                echo "</textarea>\n";
                            } else {
                                echo format_text($grades[$i]->answer);
                            }
                            echo "  </td>\n";
                            echo "</tr>\n";


                            echo "<tr valign=\"top\">\n";
                            echo "  <td colspan=\"2\" class=\"workshopassessmentheading\">&nbsp;</td>\n";
                            echo "</tr>\n";
                        }
                    }
                    break;
            } // end of outer switch
            // now get the general comment (present in all types)
            echo "<tr valign=\"top\">\n";
            switch ($quest->gradingstrategy) {
                case 0:
                case 1:
                case 4 : // no grading, accumulative and rubic
                    echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . ":</b></p></td>\n";
                    break;
                default :
                    echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . "/<br />" .
                    get_string("reasonforadjustment", "quest") . ":</b></p></td>\n";
            }
            echo "  <td>\n";
            if ($ismanager) {
                if ($allowchanges) {
                    echo "      <textarea name=\"generalcomment\" rows=\"5\" cols=\"75\" >\n";
                    if (isset($assessment->commentsteacher)) {
                        echo $assessment->commentsteacher;
                    }
                    echo "</textarea>\n";
                } else {
                    if ($assessment) {
                        if (isset($assessment->commentsteacher)) {
                            echo format_text($assessment->commentsteacher);
                        }
                    } else {
                        print_string("yourfeedbackgoeshere", "quest");
                    }
                }
            } else {
                if ($allowchanges) {
                    echo "      <textarea name=\"generalteachercomment\" rows=\"5\" cols=\"75\" >\n";
                    if (isset($assessment->commentsteacher)) {
                        echo $assessment->commentsteacher;
                    }
                    echo "</textarea>\n";
                } else {
                    if ($assessment) {
                        if (isset($assessment->commentsteacher)) {
                            echo format_text($assessment->commentsteacher);
                        }
                    } else {
                        print_string("yourfeedbackgoeshere", "quest");
                    }
                }
            }

            echo "&nbsp;</td>\n";
            echo "</tr>\n";


            $timenow = time();
            // now show the grading grade if available...
            if (isset($assessment->state)) {
                echo "<tr valign=\"top\">\n";
                echo "<td colspan=\"2\" class=\"workshopassessmentheading\" align=\"center\"><b>" .
                get_string('assessmentglobal', 'quest') . "</b></td>\n";
                echo "</tr>\n";

                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>";
                print_string('grade', 'quest');
                echo ":</b></p></td><td>\n";

                if ($assessment->state == ASSESSMENT_STATE_BY_AUTOR) {
                    echo number_format($assessment->points, 4);
// 			if($submission->nanswerscorrect == 0){
// 				echo ' '.get_string('of','quest').' ('.number_format($submission->initialpoints, 4).')';
// 			}
// 			else{
// 				echo ' '.get_string('of','quest').' ('.number_format($submission->pointsanswercorrect, 4).')';
// 			}
                    echo ' ' . get_string('of', 'quest') . ' ' . get_string('initialpoints', 'quest') . ' ' . number_format($submission->initialpoints,
                            2);
                }
                echo "&nbsp;</td>\n";
                echo "</tr>\n";
            }


            /*
             * Manual Grading Form
             *
             *
             */
            if ($allowchanges == true) {
                echo "<tr valign=\"top\">\n";
                echo "<td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
                echo get_string('changemanualcalification', 'quest') . '</b></center></td></tr>';
                echo "<tr valign=\"top\">";
                echo "<td align=\"right\"><p><b>" . get_string('newcalification', 'quest') . ": </b></p></td>\n";
                echo "<td><input size=\"3\" maxlength=\"3\" name=\"manualcalification\" type=\"text\">%</td></tr>";
            }

            // ...and close the table, show submit button if needed...
            echo "</table>\n";
            if ($assessment) {
                if ($allowchanges) {
                    echo "<input type=\"submit\" value=\"" . get_string("savemyassessment", "quest") . "\" />\n";
                }
            }
            echo "</center>";
            echo "</form>\n";
        }

///////////////////////////////////////////////////////////////

        function quest_sortfunction_calification($a, $b) {
            $sort = 'calification';
            $dir = 'DESC';
            if ($dir == 'ASC') {
                return ($a[$sort] > $b[$sort]);
            } else {
                return ($a[$sort] < $b[$sort]);
            }
        }

        /**
         * INCRUSTA GRÃ�FICO DE EVOLUCION DE PUNTOS
         */
        function quest_print_score_graph($quest, $submission) {
            global $CFG;
            $tinit = $quest->tinitial * 86400;
            echo "<center><img src = '" . $CFG->wwwroot . "/mod/quest/graph_submission.php?sid=$submission->id&amp;tinit=$tinit&amp;dst=$submission->datestart&amp;dend=$submission->dateend&amp;ipoints=$submission->initialpoints&amp;daswcorr=$submission->dateanswercorrect&amp;pointsmax=$submission->pointsmax'></center>";
        }

/////////////////////
        function quest_print_simple_calification($quest, $course, $currentgroup, $actionclasification) {
            global $CFG, $USER, $DB, $OUTPUT;
            $groupmode = $currentgroup = false; //JPC group support desactivation
            $USER->showclasifindividual = $actionclasification;
            $context = context_course::instance($course->id);

            if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                echo $OUTPUT->heading(get_string("nostudentsyet"));
                //echo $OUTPUT->footer($course);
            } else {

                // Now prepare table with student assessments and submissions
                $tablesort = new stdClass();
                $tablesort->data = array();
                $tablesort->sortdata = array();
                $calification_users = array();
                $calification_teams = array();
                $indice = 0;

                if (!$quest->showclasifindividual) {
                    $actionclasification = 'teams';
                }

                if ($actionclasification == 'global') {

                    if ($califications = quest_get_calification($quest)) {
                        foreach ($califications as $calification) {
                            // skip if student not in group
                            if ($currentgroup) {
                                if (!groups_is_member($currentgroup, $calification->userid)) {
                                    continue;
                                }
                            }
                            $calification_users[] = $calification;
                            $indice++;
                        }
                    }
                    foreach ($calification_users as $calification_user) {
                        // check if he is enrolled
                        if (!is_enrolled($context, $calification_user->userid))
                            continue; // skip him
// 			foreach($users as $user){
// 				if($user->id == $calification_user->userid){
// 					break;
// 				}
// 			}

                        $data = array();
                        $sortdata = array();
                        $user = get_complete_user_data('id', $calification_user->userid);
                        //	$data[] = print_user_picture($user->id, $course->id, $user->picture,0,true);
                        $user->imagealt = get_string('pictureof', 'quest') . " " . fullname($user);
                        $data[] = $OUTPUT->user_picture($user, array('courseid' => $course->id, 'link' => true));
                        $sortdata['picture'] = 1;

                        $data[] = "<b>" . fullname($user) . '</b>';
                        $sortdata['user'] = strtolower(fullname($user));

                        $points = $calification_user->points;

                        if ($quest->allowteams) {
                            if ($clasification_team = $DB->get_record("quest_calification_teams",
                                    array("teamid" => $calification_user->teamid, "questid" => $quest->id))) {
                                $points = $points + $clasification_team->points * $quest->teamporcent / 100;
                            }
                        }
                        $pointsprint = number_format($points, 4);
                        $data[] = $pointsprint;
                        $sortdata['calification'] = $points;

                        $tablesort->data[] = $data;
                        $tablesort->sortdata[] = $sortdata;
                    }

                    uasort($tablesort->sortdata, 'quest_sortfunction_calification');

                    $table = new html_table();
                    $table->data = array();
                    $count = 0;
                    foreach ($tablesort->sortdata as $key => $row) {

                        // limit table lenght
                        $count++;
                        if ($count > 5)
                            break;
                        $table->data[] = $tablesort->data[$key];
                    }

                    $table->align = array('left', 'left', 'center');
                    $table->head = array(get_string('user', 'quest'), get_string('calification', 'quest'));
                    $table->headspan = array(2, 1);
                    $columns = array('picture', 'user', 'calification');
                    $table->width = "95%";

                    $sort = '';
                }
                else if ($actionclasification == 'teams') {

                    $teamstemp = array();

                    if ($teams = $DB->get_records_select("quest_teams", "questid = ?", array($quest->id))) {
                        foreach ($teams as $team) {
                            foreach ($users as $user) {
                                // skip if student not in group
                                if ($currentgroup) {
                                    if (!groups_is_member($currentgroup, $user->id)) {
                                        continue;
                                    }
                                }

                                $clasification = $DB->get_record("quest_calification_users",
                                        array("userid" => $user->id, "questid" => $quest->id));
                                if (!empty($clasification) && $clasification->teamid == $team->id) {
                                    $existy = false;
                                    foreach ($teamstemp as $teamtemp) {
                                        if ($teamtemp->id == $team->id) {
                                            $existy = true;
                                        }
                                    }
                                    if (!$existy) {
                                        $teamstemp[] = $team;
                                    }
                                }
                            }
                        }
                    }
                    $teams = $teamstemp;

                    if ($clasification_teams = quest_get_calification_teams($quest)) {
                        foreach ($clasification_teams as $clasification_team) {
                            foreach ($teams as $team) {
                                if ($clasification_team->teamid == $team->id) {
                                    $calification_teams[] = $clasification_team;
                                    $indice++;
                                }
                            }
                        }
                    }

                    for ($i = 0; $i < $indice; $i++) {

                        $data = array();
                        $sortdata = array();

                        foreach ($teams as $team) {
                            if ($calification_teams[$i]->teamid == $team->id) {
                                $data[] = $team->name;
                                $sortdata['team'] = strtolower($team->name);
                            }
                        }

                        $points = $calification_teams[$i]->points;
                        $pointsprint = number_format($points, 4);
                        $data[] = $pointsprint;
                        $sortdata['calification'] = $points;

                        $tablesort->data[] = $data;
                        $tablesort->sortdata[] = $sortdata;
                    }


                    uasort($tablesort->sortdata, 'quest_sortfunction_calification');

                    $table = new html_table();
                    $table->data = array();
                    $count = 0;
                    foreach ($tablesort->sortdata as $key => $row) {
                        $table->data[] = $tablesort->data[$key];
                        // limit table lenght
                        if ($count > 5)
                            break;
                        $count++;
                    }
                    $table->align = array('left', 'center');
                    $table->head = array(get_string('team', 'quest'), get_string('calification', 'quest'));
                    $columns = array('team', 'calification');
                    $table->width = "95%";

                    $cm = get_coursemodule_from_instance('quest', $quest->id);

                    $sort = '';
                }
                echo html_writer::table($table);
            }
        }

//////////////////////////////////////////
        function quest_sortfunction($a, $b) {
            global $sort, $dir;

            if ($dir == 'ASC') {
                return ($a[$sort] > $b[$sort]);
            } else {

                return ($a[$sort] < $b[$sort]);
            }
        }

///////////////////////////////////////////////////
        function quest_actions_submission($course, $submission, $quest, $cm) {

            global $USER, $DB, $OUTPUT;

            $string = '';

            $context = context_module::instance($cm->id);
            $ismanager = has_capability('mod/quest:manage', $context);

            echo "<center><b>";

            $can_approve = has_capability('mod/quest:approvechallenge', $context);
            if (($can_approve) && ($submission->state == SUBMISSION_STATE_APPROVAL_PENDING)) {
                $string = "<a href=\"submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=approve\">" .
                        get_string('approve', 'quest') . "</a>";
            }

            $can_answer = has_capability('mod/quest:attempt', $context);
            if ($ismanager ||
                    ($can_answer && $submission->userid != $USER->id)) {
                $answered = false;
                if ($answers = quest_get_user_answers($submission, $USER)) {
                    foreach ($answers as $answer) {
                        if ($answer->submissionid == $submission->id) {
                            $answered = true;
                            if ($string != '') {
                                $string.='&nbsp;/&nbsp;';
                            }
                            $string.=get_string('answerexisty', 'quest');
                        }
                    }
                }
                if ($submission->state != SUBMISSION_STATE_APROVED) {
                    if ($string != '') {
                        $string.='&nbsp;/&nbsp;';
                    }
                    $string.=get_string('phase1submission', 'quest');
                }
                if (($ismanager && !$answered) ||
                        ($answered == false && $submission->phase == SUBMISSION_PHASE_ACTIVE && $submission->state == SUBMISSION_STATE_APROVED)) {
                    if ($string != '') {
                        $string.='&nbsp;/&nbsp;';
                    }
                    $string .= "<a href=\"answer.php?id=$cm->id&amp;uid=$USER->id&amp;action=answer&amp;sid=$submission->id\">" . get_string("reply",
                                    "quest") . "</a>";
                    $string.= $OUTPUT->help_icon('answersubmission', 'quest');
                }
            } else if (!$can_answer) {
                $string.= get_string('cantRespond_WARN', 'quest');
                $string.= $OUTPUT->help_icon('answersubmission', 'quest');
            } else if ($submission->userid == $USER->id) {
                $string.= get_string('authorofsubmission', 'quest');
                $string.= $OUTPUT->help_icon('answersubmission', 'quest');
            }

            if ($ismanager ||
                    ($submission->userid == $USER->id || $submission->phase != SUBMISSION_PHASE_ACTIVE)) {
                if ($assessment_autor = $DB->get_record("quest_assessments_autors",
                        array("submissionid" => $submission->id, "questid" => $quest->id))) {
                    if ($string != '') {
                        $string.='&nbsp;/&nbsp;';
                    }
                    $string .= "<a href=\"viewassessmentautor.php?aid=$assessment_autor->id\">" .
                            get_string("seeassessmentautor", "quest") . "</a>";
                }
            }

            if (($ismanager)) {
                if ($string != '') {
                    $string.='&nbsp;/&nbsp;';
                }
                if ($assessment_autor = $DB->get_record("quest_assessments_autors",
                        array("submissionid" => $submission->id, "questid" => $quest->id))) {
                    $string .= "<a href=\"assess_autors.php?id=$cm->id&amp;sid=$submission->id&amp;action=evaluate\">" .
                            get_string('reevaluate', 'quest') . "</a>";
                    $string.=$OUTPUT->help_icon('assessthissubmission', 'quest');
                } else {
                    $string .= "<a href=\"assess_autors.php?id=$cm->id&amp;sid=$submission->id&amp;action=evaluate\">" .
                            get_string('evaluate', 'quest') . "</a>";
                }
            }

            if ($ismanager) {
                if ($string != '') {
                    $string.='&nbsp;/&nbsp;';
                }
                $string .= "<a href=\"submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=recalificationall\">" .
                        get_string('recalificationall', 'quest') . "</a>";
            }
            return $string;
        }

        /**
         *
         * @global stdClass $USER
         * @global stdClass $DB
         * @param stdClass $quest
         * @param stdClass $submission
         * @return boolean
         */
        function quest_validate_user_answer(stdClass $quest, stdClass $submission) {

            global $USER, $DB;

            $validate = true;
            if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?",
                    array($quest->id, $submission->id))) {
                foreach ($answers as $answer) {
                    if ($answer->userid == $USER->id) {
                        $validate = false;
                    }
                }
            }
            return $validate;
        }


        /**
         * Update quest points and scores of the answers older than $answer_actual
         * Don't aply marking elements, just update answer->pointsmax
         * Update users and teams scores
         */
        function quest_update_grade_for_answer($answer_actual, $submission, $quest, $course) {
            global $DB;
// all answers to this submission
            $answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id));

            if ($answers) {
                foreach ($answers as $answer) {
// 			if(($answer->id != $answer_actual->id) &&($answer->date >= $answer_actual->date)    )
                    {
                        $points = quest_get_points($submission, $quest, $answer);
                        $answer->pointsmax = number_format($points, 6);
                        $DB->update_record("quest_answers", $answer);
                        //print("<p>Answer $answer->id has $points maximum points");
                        quest_update_user_scores($quest, $answer->userid);
                        if ($teamid = quest_get_user_team($answer->questid, $answer->userid))
                            quest_update_team_scores($answer->questid, $teamid);
                    }
                }
            }
        }

        /**
         * Update a submission details in the database.
         * Trucate numeric values to workaround weird database truncation errors with Moodle 2.5
         * @param unknown $submission
         */
        function quest_update_submission($submission) {
            global $DB;
            $submission->points = number_format($submission->points, 4);
            $DB->update_record('quest_submissions', $submission);
        }
        function quest_update_challenge_calendar($cm,$quest, $challenge){
            global $DB;
            $dates = array(
           'openchallenge' => $challenge->datestart,
           'closechallenge' => $challenge->dateend
               );
            foreach ($dates as $type => $date) {
                if ($type == 'openchallenge') {
                    $stringevent = 'datestartsubmissionevent';
                } else if ($type == 'closechallenge') {
                    $stringevent = 'dateendsubmissionevent';
                }
                $eventdata = new stdClass();
                $eventdata->name = get_string($stringevent, 'quest', $challenge->title);
                $url=new moodle_url('/mod/quest/submissions.php',array('id'=>$cm->id,'sid'=>$challenge->id,'action'=>'showsubmission'));
                $eventdata->description =  "<a href=\"$url\">$challenge->title</a>";
                $eventdata->eventtype = $type;
                $eventdata->timestart = $date;
                $eventdata->modulename='quest';
                $eventdata->instance = $quest->id;
                $eventdata->timeduration = 0;
                $eventdata->visible = $cm->visible;
                $eventdata->groupid = 0;
                $eventdata->userid = 0;
                $eventdata->courseid = $quest->course;
                $eventdata->uuid= $challenge->id;

                $event = $DB->get_record('event', array('modulename' => 'quest', 'instance' => $quest->id, 'eventtype' => $type,'uuid'=>$challenge->id));
                if ($event) { // Update event.
                        $event = calendar_event::load($event->id);
                        $event->update($eventdata);
                    } else { // Create new event.         
                        calendar_event::create($eventdata);
                    }
            }   
        }
        /**
         * Update a assessment details in the database.
         * Trucate numeric values to workaround weird database truncation errors and decimal points with Moodle 2.5
         * @param unknown $submission
         */
        function quest_update_assessment($assessment) {
            global $DB;
            $assessment->pointsautor = number_format($assessment->pointsautor, 4);
            $assessment->pointsteacher = number_format($assessment->pointsteacher, 4);
            $DB->update_record('quest_assessments', $assessment);
        }

        /**
         * Update a assessment_autor details in the database.
         * Trucate numeric values to workaround weird database truncation errors and decimal points with Moodle 2.5
         * @param unknown $submission
         */
        function quest_update_assessment_author($assessment) {
            global $DB;
            $assessment->points = number_format($assessment->points, 4);
            $assessment->pointsmax = number_format($assessment->pointsmax, 4);
            $DB->update_record('quest_assessments_autors', $assessment);
        }

        /**
         * Update a $calification_user details in the database.
         * Trucate numeric values to workaround weird database truncation errors and decimal points with Moodle 2.5
         * @param unknown $submission
         */
        function quest_update_calification_user($calification_user) {
            global $DB;
            $calification_user->points = number_format($calification_user->points, 4, '.', '');
            $DB->update_record('quest_calification_users', $calification_user);
        }

        /**
         * get the team for the user and quest
         * @param questid  id of the quest
         * @param userid	id of the user being queried for
         * @return false if failure or null data, mixed with the id otherway
         */
        function quest_get_user_team($questid, $userid) {
            global $DB;
            $query = $DB->get_field("quest_calification_users", "teamid", array("userid" => $userid, "questid" => $questid));
            return $query;
        }

        /**
         * get the members of a team
         * @param int $questid
         * @param int $teamid
         * @return array of userids:
         */
        function quest_get_team_members($questid, $teamid) {
            global $DB;
            $query = $DB->get_records("quest_calification_users", array("teamid" => $teamid, "questid" => $questid), '', 'userid');
            return $query;
        }

        /**
         * calculate user answer points from records in database
         * do not sum assessments in phase 0 or 1 (approval pending)
         * @param $userid id o array de id
         * @return points
         */
        function quest_calculate_user_score($questid, $userid) {
            global $CFG, $DB;
            list($insql, $inparams) = $DB->get_in_or_equal($userid);
            $params = array_merge(array($questid), $inparams);
            $sql = "select sum(ans.grade*ans.pointsmax/100) as points from {quest_answers} as ans, {quest_assessments} as assess WHERE " .
                    "ans.questid=? AND " .
                    "ans.userid $insql AND " .
                    "ans.id=assess.answerid AND " .
                    "assess.phase=" . ASSESSMENT_PHASE_APPROVED;
            if ($query = $DB->get_record_sql($sql, $params)) {
                if (isset($query->points)) {
                    return $query->points;
                } else {
                    return 0;
                }
            } else {

                return 0;
            }
        }

        /**
         * calculate user submission points from records in database
         * @param $userid id o array de ids
         * @return points
         */
        function quest_calculate_user_submissions_score($questid, $userid) {
            global $DB;
            $points = 0;
            list($insql, $inparams) = $DB->get_in_or_equal($userid);
            $allparams = array_merge(array($questid), $inparams);
            if ($query = $DB->get_records_select("quest_submissions", "questid=? and userid $insql", $allparams)) {

                foreach ($query as $s)
                    $submissions[] = $s->id;
                list($insql2, $inparams2) = $DB->get_in_or_equal($submissions);
                if ($query = $DB->get_record_select("quest_assessments_autors", "submissionid $insql2", $inparams2,
                        "sum(points) as points")) {//evp no estoy segura de que funcione
                    if ($query->points)
                        $points = $query->points;
                }
            }
            return $points;
        }

        /**
         * @param $userid array de identificadores
         */
        function quest_count_user_submissions_assesed($questid, $userid) {
            global $DB;
            list($insql, $inparams) = $DB->get_in_or_equal($userid);
            $allparams = array_merge(array($questid), $inparams);
            if ($query = $DB->get_records_select("quest_submissions", "questid=? and userid $insql", $allparams)) {
                foreach ($query as $s)
                    $submissions[] = $s->id;
                list($insql, $inparams) = $DB->get_in_or_equal($submissions);
                if ($query = $DB->get_record_select("quest_assessments_autors", "submissionid $insql and state>0", $inparams,
                        "count(points) as num"))
                    return $query->num;
                else
                    return 0;
            } else
                return 0;
        }

        /**
         * @param $userid array de identificadores
         */
        function quest_count_user_submissions($questid, $userid) {
            global $DB;
            list($insql, $inparams) = $DB->get_in_or_equal($userid);
            $allparams = array_merge(array($questid), $inparams);
            if ($query = $DB->get_record_select("quest_submissions", "questid=? and userid $insql", $allparams, "count(id) as num")) {
                return $query->num;
            } else
                return 0;
        }

        /**
         * @param $userid array de identificadores
         */
        function quest_count_user_answers($questid, $userid) {
            global $DB;
            list($insql, $inparams) = $DB->get_in_or_equal($userid);
            $allparams = array_merge(array($questid), $inparams);
            if ($query = $DB->get_record_select("quest_answers", "questid=? and userid $insql", $allparams, "count(grade) as num"))
                return $query->num;
            else
                return 0;
        }

        /**
         * @param $userid array de identificadores
         */
        function quest_count_user_answers_assesed($questid, $userid) {
            global $DB;
            list($insql, $inparams) = $DB->get_in_or_equal($userid);
            $allparams = array_merge(array($questid), $inparams);
            $query = $DB->get_record_select("quest_answers", "questid=? and userid $insql and phase>0", $allparams,
                    "count(grade) as num");
            if ($query)
                return $query->num;
            else
                return 0;
        }

        /**
         * Count submissions's assessments
         */
        function quest_count_submission_assessments($sid) {
            global $DB;
            $answersids = $DB->get_records('quest_answers', array("submissionid" => $sid), '', "id");

            if ($answersids != false && count($answersids) > 0) {
                foreach ($answersids as $aid)
                    $aids[] = $aid->id;
                $answersids = implode(",", $aids);
                list($insql, $inparams) = $DB->get_in_or_equal(array($answersids));
                return $DB->count_records_select("quest_assessments", "answerid $insql", $inparams);
            } else
                return 0;
        }

        /**
         * Recalculate scores, stats and report to the gradebook for an user and his team
         * @param record $quest
         * @param int $userid specified user
         */
        function quest_grade_updated($quest, $userid) {
            if (!$userid){
                return;
            }
            // Update current User scores.
            quest_update_user_scores($quest, $userid);
            $userids = array($userid);

            //  Update answer current team totals.
            if ($quest->allowteams) {
                $team = quest_get_user_team($quest->id, $userid);

                if ($team) {
                    quest_update_team_scores($quest->id, $team);
                }
            }


            // Report to gradebook.
            // as score are relative to other's we need to update all grades.
            quest_update_grades($quest, 0);
        }

        /**
         * Updates $calification_user  registry
         * counting totals and pointanswers and points from the records in the database
         */
        function quest_update_user_scores($quest, $userid) {
            global $DB;
            $questid = $quest->id;
            $calification_user = $DB->get_record("quest_calification_users", array("questid" => $questid, "userid" => $userid),
                    '*');

            if (!$calification_user) {
                $calification_user = new stdClass();
                $calification_user->userid = $userid;
                $calification_user->questid = $quest->id;
                $calification_user->id = $DB->insert_record("quest_calification_users", $calification_user);
            }

            $calification_user->pointsanswers = quest_calculate_user_score($questid, $userid);
            $calification_user->nanswers = quest_count_user_answers($questid, $userid);
            $calification_user->nanswersassessment = quest_count_user_answers_assesed($questid, $userid);
            $calification_user->pointssubmission = quest_calculate_user_submissions_score($questid, $userid);
            $calification_user->nsubmissionsassessment = quest_count_user_submissions_assesed($questid, $userid);
            $calification_user->nsubmissions = quest_count_user_submissions($questid, $userid);
            $calification_user->points = $calification_user->pointssubmission + $calification_user->pointsanswers;
            quest_update_calification_user($calification_user);
        }

        /**
         * @param stdClass|int $questid
         * @param int $teamid
         * Updates pointanswers and points from the records in the database
         * $calification_team->nanswers = $nanswers;
          $calification_team->nanswerassessment=$nanswersassessed;
          $calification_team->points=$points;
          $calification_team->pointsanswers=$pointsanswers;
          $calification_team->pointssubmission=$submissionpoints;
          $calification_team->nsubmissionsassessment=$nsubmissionassessed;
          $calification_team->nsubmissions=$nsubmissions;
         * updates team->ncomponents
         *
         */
        function quest_update_team_scores($quest, $teamid) {
            global $DB;
            if ($quest instanceof stdClass)
                $questid = $quest->id;
            else
                $questid = $quest;

            $query = $DB->get_records("quest_calification_users", array("teamid" => $teamid, 'questid' => $questid));
            $members = array();
            foreach ($query as $q)
                $members[] = $q->userid;

            $member_list = implode(',', $members);

            if (empty($members)) {
                echo "Quest $questid Team $teamid  has no members.";
                return;
            }
            $pointsanswers = quest_calculate_user_score($questid, $members);

            $nanswers = quest_count_user_answers($questid, $members);
            $nanswersassessed = quest_count_user_answers_assesed($questid, $members);
            $submissionpoints = quest_calculate_user_submissions_score($questid, $members);
            $nsubmissions = quest_count_user_submissions($questid, $members);
            //print("Team $teamid formed by $member_list has $nsubmissions submissions");
            $nsubmissionassessed = quest_count_user_submissions_assesed($questid, $members);

            $points = $submissionpoints + $pointsanswers;
            //print("<p>Updating team $teamid with $nanswers answers ($nanswersassessed assessed) and $points points");
            $calification_team = $DB->get_record("quest_calification_teams", array("questid" => $questid, "teamid" => $teamid),
                    '*');

            if (!$calification_team) {
                $calification_team = new stdClass();
                $calification_team->teamid = $teamid;
                $calification_team->questid = $questid;
                $calification_team->id = $DB->insert_record("quest_calification_teams", $calification_team);
            }
            $calification_team->nanswers = $nanswers;
            $calification_team->nanswerassessment = $nanswersassessed;
            $calification_team->points = '' . $points;
            $calification_team->pointsanswers = "$pointsanswers";
            $calification_team->pointssubmission = "$submissionpoints";
            $calification_team->nsubmissionsassessment = $nsubmissionassessed;
            $calification_team->nsubmissions = $nsubmissions;
            $DB->update_record('quest_calification_teams', $calification_team);

            $DB->set_field("quest_teams", "ncomponents", count($members), array("questid" => $questid, "id" => $teamid));
        }

        /**
         *
         */
        function quest_recalification($answer, $quest, $assessment, $course) {

            global $USER, $DB;

            global $QUEST_EWEIGHTS_RECALIF;

            $context = context_course::instance($course->id);
            $ismanager = has_capability('mod/quest:manage', $context);


            if (!$submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid))) {
                error("quest submission is misconfigured");
            }
            if (!has_capability('mod/quest:manage', $context, $answer->userid)) {
                if (!$calification_user = $DB->get_record("quest_calification_users",
                        array("userid" => $answer->userid, "questid" => $quest->id))) {
                    error("Calification user is incorrect");
                }
                if ($quest->allowteams) {
                    if (!$calification_team = $DB->get_record("quest_calification_teams",
                            array("teamid" => $calification_user->teamid, "questid" => $quest->id))) {
                        error("Calification team is incorrect");
                    }
                }
            }
            // first get the assignment elements for maxscores and weights...
            $elementsraw = $DB->get_records("quest_elements", array("questid" => $quest->id), "elementno ASC");
// 	if (count($elementsraw) < $quest->nelements) {
// 		print_string("noteonassignmentelements", "quest");
// 	}

            if ($elementsraw) {
                foreach ($elementsraw as $element) {
                    $elements[$element->elementno] = $element;   // to renumber index 0,1,2...
                }
            } else {
                $elements = null;
            }

            $timenow = time();
            // don't fiddle about, delete all the old and add the new!
            $formraw = new stdclass();
            $formraw->grade = $DB->get_records("quest_elements_assessments", array("assessmentid" => $assessment->id));
            if ($formraw->grade) {
                foreach ($formraw->grade as $graderaw) {
                    $form->grade[$graderaw->elementno] = $graderaw->calification;   // to renumber index 0,1,2...
                }
            } else {
                $form->grade = null;
            }

            if ($quest->validateassessment == 1) {
                if (!empty($assessment->teacherid)) {
                    if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {
                        if ($assessment->phase == 0) {
                            $calification_user->nanswersassessment++;
                            if ($quest->allowteams) {
                                $calification_team->nanswerassessment++;
                            }
                        }
                        $assessment->phase = 1;
                    }
                } else {
                    $assessment->phase = 0;
                }
            } else {
                if ($assessment->phase == 0) {
                    $calification_user->nanswersassessment++;
                    if ($quest->allowteams) {
                        $calification_team->nanswerassessment++;
                    }
                }
                $assessment->phase = 1;
            }


            $answer->phase = 1;


            //determine what kind of grading we have
            switch ($quest->gradingstrategy) {
                case 0: // no grading
                    // Insert all the elements that contain something
                    $points = quest_get_points($submission, $quest, $answer);
                    $grade = 0;
                    if ((100.0 * ($rawgrade / $totalweight)) >= 50.0000) {


                        $submission->points = $grade;

                        if (($submission->nanswerscorrect == 0) && ($assessment->phase == 1)) {

                            $submission->dateanswercorrect = $answer->date;
                            $submission->pointsanswercorrect = $points;
                        }
                        if (($answer->phase != 2) && ($assessment->phase == 1)) {
                            $submission->nanswerscorrect++;
                            $answer->phase = 2;
                        }
                    } else {

                        $submission->points = $grade;
                        if ($answer->phase == 2) {
                            $submission->nanswerscorrect--;
                        }
                        $answer->phase = 1;
                    }


                    if ($assessment->phase == 1) {
                        if (empty($assessment->teacherid)) {
                            if (!empty($assessment->userid)) {

                                $calification_user->points -= $assessment->pointsautor;
                                $calification_user->pointsanswers -= $assessment->pointsautor;
                                $calification_user->points += $grade;
                                $calification_user->pointsanswers += $grade;

                                if ($quest->allowteams) {
                                    $calification_team->points -= $assessment->pointsautor;
                                    $calification_team->pointsanswers -= $assessment->pointsautor;
                                    $calification_team->points += $grade;
                                    $calification_team->pointsanswers += $grade;
                                }
                            }
                        } else if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {

                            $calification_user->points -= $assessment->pointsteacher;
                            $calification_user->pointsanswers -= $assessment->pointsteacher;
                            $calification_user->points += $grade;
                            $calification_user->pointsanswers += $grade;

                            if ($quest->allowteams) {
                                $calification_team->points -= $assessment->pointsteacher;
                                $calification_team->pointsanswers -= $assessment->pointsteacher;
                                $calification_team->points += $grade;
                                $calification_team->pointsanswers += $grade;
                            }
                        }
                    }

                    break;

                case 1: // accumulative grading
                    // Insert all the elements that contain something
                    // now work out the grade...
                    $rawgrade = 0;
                    $totalweight = 0;
                    foreach ($form->grade as $key => $grade) {
                        $maxscore = $elements[$key]->maxscore;
                        $weight = $QUEST_EWEIGHTS_RECALIF[$elements[$key]->weight];
                        if ($weight > 0) {
                            $totalweight += $weight;
                        }
                        $rawgrade += ($grade / $maxscore) * $weight;
                    }
                    $points = quest_get_points($submission, $quest, $answer);
                    $grade = $points * ($rawgrade / $totalweight);
                    if ((100.0 * ($rawgrade / $totalweight)) >= 50.0000) {



                        $submission->points = $grade;

                        if (($submission->nanswerscorrect == 0) && ($assessment->phase == 1)) {

                            $submission->dateanswercorrect = $answer->date;
                            $submission->pointsanswercorrect = $points;
                        }
                        if (($answer->phase != 2) && ($assessment->phase == 1)) {
                            $submission->nanswerscorrect++;
                            $answer->phase = 2;
                        }
                    } else {
                        $points = quest_get_points($submission, $quest, $answer);
                        $grade = $points * ($rawgrade / $totalweight);
                        $submission->points = $grade;
                        if ($answer->phase == 2) {
                            $submission->nanswerscorrect--;
                        }
                        $answer->phase = 1;
                    }

                    break;

                case 2: // error banded graded
                    // Insert all the elements that contain something
                    $error = 0.0;
                    for ($i = 0; $i < $quest->nelements; $i++) {

                        if (empty($form->grade[$i])) {
                            $error += $QUEST_EWEIGHTS_RECALIF[$elements[$i]->weight];
                        }
                    }
                    // now save the adjustment

                    $i = $quest->nelements;

                    $rawgrade = ($elements[intval($error + 0.5)]->maxscore + $form->grade[$i]);
                    // do sanity check
                    if ($rawgrade < 0) {
                        $rawgrade = 0;
                    } else if ($rawgrade > $quest->maxcalification) {
                        $rawgrade = $quest->maxcalification;
                    }
                    $points = quest_get_points($submission, $quest, $answer);
                    $grade = $points * ($rawgrade / $quest->maxcalification);
                    if ((100.0 * ($rawgrade / $totalweight)) >= 50.0000) {



                        $submission->points = $grade;

                        if (($submission->nanswerscorrect == 0) && ($assessment->phase == 1)) {

                            $submission->dateanswercorrect = $answer->date;
                            $submission->pointsanswercorrect = $points;
                        }
                        if (($answer->phase != 2) && ($assessment->phase == 1)) {
                            $submission->nanswerscorrect++;
                            $answer->phase = 2;
                        }
                    } else {
                        $points = quest_get_points($submission, $quest, $answer);
                        $grade = $points * ($rawgrade / $quest->maxcalification);
                        $submission->points = $grade;
                        if ($answer->phase == 2) {
                            $submission->nanswerscorrect--;
                        }
                        $answer->phase = 1;
                    }

                    break;

                case 3: // criteria grading
                    // save in the selected criteria value in element zero,

                    $rawgrade = ($elements[$form->grade[0]]->maxscore + $form->grade[1]);
                    $points = quest_get_points($submission, $quest, $answer);
                    $grade = $points * ($rawgrade / $quest->maxcalification);
                    if ((100.0 * ($rawgrade / $totalweight)) >= 50.0000) {



                        $submission->points = $grade;


                        if (($submission->nanswerscorrect == 0) && ($assessment->phase == 1)) {

                            $submission->dateanswercorrect = $answer->date;
                            $submission->pointsanswercorrect = $points;
                        }
                        if (($answer->phase != 2) && ($assessment->phase == 1)) {
                            $submission->nanswerscorrect++;
                            $answer->phase = 2;
                        }
                    } else {
                        $points = quest_get_points($submission, $quest, $answer);
                        $grade = $points * ($rawgrade / $quest->maxcalification);
                        $submission->points = $grade;
                        if ($answer->phase == 2) {
                            $submission->nanswerscorrect--;
                        }
                        $answer->phase = 1;
                    }

                    break;

                case 4: // rubric grading (identical to accumulative grading)
                    // Insert all the elements that contain something
                    // now work out the grade...
                    $rawgrade = 0;
                    $totalweight = 0;
                    foreach ($form->grade as $key => $grade) {
                        $maxscore = $elements[$key]->maxscore;
                        $weight = $QUEST_EWEIGHTS_RECALIF[$elements[$key]->weight];
                        if ($weight > 0) {
                            $totalweight += $weight;
                        }
                        $rawgrade += ($grade / $maxscore) * $weight;
                    }

                    $points = quest_get_points($submission, $quest, $answer);
                    $grade = $points * ($rawgrade / $totalweight);
                    if ((100.0 * ($rawgrade / $totalweight)) >= 50.0000) {

                        $submission->points = $grade;

                        if (($submission->nanswerscorrect == 0) && ($assessment->phase == 1)) {

                            $submission->dateanswercorrect = $answer->date;
                            $submission->pointsanswercorrect = $points;
                        }
                        if (($answer->phase != 2) && ($assessment->phase == 1)) {
                            $submission->nanswerscorrect++;
                            $answer->phase = 2;
                        }
                    } else {

                        $submission->points = $grade;
                        if ($answer->phase == 2) {
                            $submission->nanswerscorrect--;
                        }
                        $answer->phase = 1;
                    }


                    break;
            } // end of switch

            $answer->grade = 100 * ($grade / $points);

            // update the time of the assessment record (may be re-edited)...
            $assessment->dateassessment = $timenow;

            //get first answer correct
            //		$query  = $DB->get_record_select("quest_answers","submissionid=$submission->id and grade>50","min(date) as fecha");
            //        if (!$query)
            //        $first_date_correct=$assessment->dateanswercorrect;
            //        else
            //        $first_date_correct=$query->fecha;
            //$first_date_correct=$assessment->dateanswercorrect; // desactiva
            $first_date_correct = $submission->dateanswercorrect;

            if (!empty($assessment->teacherid)) {
                if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {
                    $assessment->pointsteacher = $grade;
                }
            } else if (!empty($assessment->userid)) {
                $assessment->pointsautor = $grade;
            }

            if (!empty($assessment->teacherid)) {
                if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {
                    $assessment->state = 2;
                }
            } else if (!empty($assessment->userid)) {
                $assessment->state = 1;
            }

            $DB->update_record('quest_answers', $answer);
            quest_update_assessment($assessment);
            quest_update_submission($submission);
            quest_update_submission_counts($submission->id);
            // Recalculate points and report to gradebook.
            quest_grade_updated($quest, $submission->userid);

            $aid = $assessment->id;
            if (!empty($assessment->teacherid)) {
                if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {
                    if ($user = get_complete_user_data('id', $answer->userid)) {

                        quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission,
                                $answer);
                    }
                    if ($user = get_complete_user_data('id', $assessment->userid)) {

                        quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission,
                                $answer);
                    }
                }
            } else {
                if ($user = get_complete_user_data('id', $answer->userid)) {

                    quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission,
                            $answer);
                }
                if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                    print_heading(get_string("nostudentsyet"));
                    print_footer($course);
                    exit;
                }
// 		/** JPC 2013-11-28 disable excesive notifications
// 		foreach($users as $user){
// 			if(!has_capability('mod/quest:manage',$context,$user->id)){
// 				continue;
// 			}
// 			quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
// 		}
            }

            $cm = get_coursemodule_from_instance('quest', $quest->id);
            mod_quest\event\answer_assessed::create_from_parts($submission, $answer, $assessment, $cm);
//            add_to_log($course->id, "quest", "assess_answer", "viewassessment.php?id=$cm->id&amp;asid=$assessment->id",
//                    "$assessment->id", "$cm->id");
        }

        function quest_print_table_teams($quest, $course, $cm, $sortteam, $dirteam) {

            global $CFG, $USER, $DB;

            $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
            $groupmode = groups_get_activity_group($cm);   // Groups are being used?
            $currentgroup = groups_get_course_group($course);
            $groupmode = $currentgroup = false; //JPC group support desactivation

            $context = context_module::instance($cm->id);
            $ismanager = has_capability('mod/quest:manage', $context);

            if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                echo $OUTPUT->heading(get_string("nostudentsyet"));
                echo $OUTPUT->footer();
                exit;
            }


            // Now prepare table with student assessments and submissions
            $tablesort = new stdclass();
            $tablesort->data = array();
            $tablesort->sortdata = array();
            $i = 0;


            foreach ($users as $user) {
                // skip if student not in group
                if ($ismanager) {
                    if (!has_capability('mod/quest:manage', $context, $user->id)) {
                        if ($currentgroup) {
                            if (!groups_is_member($currentgroup, $user->id)) {
                                continue;
                            }
                        }
                    }
                } else if (!has_capability('mod/quest:manage', $context, $user->id) && ($groupmode == 1)) {
                    if ($currentgroup) {
                        if (!groups_is_member($currentgroup, $user->id)) {
                            continue;
                        }
                    }
                }
                if ($calification_user = $DB->get_record("quest_calification_users",
                        array("userid" => $user->id, "questid" => $quest->id))) {

                    if ($team = $DB->get_record("quest_teams", array("id" => $calification_user->teamid))) {

                        $data = array();
                        $sortdata = array();

                        $data[] = "<a name=\"userid$user->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
                                fullname($user) . '</a>';
                        $sortdata['firstname'] = strtolower($user->firstname);
                        $sortdata['lastname'] = strtolower($user->lastname);

                        $data[] = $team->name;
                        $sortdata['teamname'] = strtolower($team->name);

                        $data[] = $team->ncomponents;
                        $sortdata['ncomponents'] = $team->ncomponents;

                        $i++;

                        $tablesort->data[] = $data;
                        $tablesort->sortdata[] = $sortdata;
                    }
                }
            }

            function quest_sortfunction_team($a, $b) {
                global $sortteam, $dirteam;
                if ($dirteam == 'ASC') {
                    return ($a[$sortteam] > $b[$sortteam]);
                } else {
                    return ($a[$sortteam] < $b[$sortteam]);
                }
            }

            uasort($tablesort->sortdata, 'quest_sortfunction_team');
            $table = new html_table();
            $table->data = array();
            foreach ($tablesort->sortdata as $key => $row) {
                $table->data[] = $tablesort->data[$key];
            }


            $table->align = array('left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');

            $columns = array('firstname', 'lastname', 'teamname', 'ncomponents');
            $table->width = "95%";

            foreach ($columns as $column) {
                $string[$column] = get_string("$column", 'quest');
                if ($sortteam != $column) {
                    $columnicon = '';
                    $columndir = 'ASC';
                } else {
                    $columndir = $dirteam == 'ASC' ? 'DESC' : 'ASC';
                    if ($column == 'lastaccess') {
                        $columnicon = $dirteam == 'ASC' ? 'up' : 'down';
                    } else {
                        $columnicon = $dirteam == 'ASC' ? 'down' : 'up';
                    }
                    $columnicon = " <img src=\"" . $CFG->wwwroot . "pix/t/$columnicon.png\" alt=\"$columnicon\" />";
                }
                $$column = "<a href=\"view.php?id=$cm->id&amp;sortteam=$column&amp;dirteam=$columndir\">" . $string[$column] . "</a>$columnicon";
            }

            $table->head = array("$firstname / $lastname", "$teamname", "$ncomponents");

            echo html_writer::table($table);
        }

        function quest_recalification_all($submission, $quest, $course) {
            global $DB;
            if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?",
                    array($quest->id, $submission->id))) {

                $submission->nanswerscorrect = 0;
                $DB->set_field("quest_submissions", "nanswerscorrect", $submission->nanswerscorrect, array("id" => $submission->id));

                foreach ($answers as $answer) {

                    $points = quest_get_points($submission, $quest, $answer);
                    $answer->pointsmax = $points;
                    $DB->set_field("quest_answers", "pointsmax", $answer->pointsmax, array("id" => $answer->id));

                    if ($assessment = $DB->get_record("quest_assessments", array("questid" => $quest->id, "answerid" => $answer->id))) {

                        if ($answer->state != ANSWER_STATE_MODIFIED) {
                            quest_recalification($answer, $quest, $assessment, $course);
                        }
                    }
                }
            }
        }

        /**
         * Disable module instance if user has no permissions and module is hidden (disabled)
         *
         * @param type $course
         * @param type $cm
         */
        function quest_check_visibility($course, $cm) {
            $context = context_course::instance($course->id);
            if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $context)) {
                print_error("ModuleHidden", 'quest');
            }
        }

        function quest_require_password($quest, $course, $userpassword) {

            global $USER, $OUTPUT;
            $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id);
            $context = context_module::instance($cm->id);

            $ismanager = has_capability('mod/quest:manage', $context);

            if (($quest->usepassword) && (!$ismanager)) {
                $correctpass = false;
                if (!empty($userpassword)) {
                    if ($quest->password == md5(trim($userpassword))) {
                        $USER->questloggedin[$quest->id] = true;
                        $correctpass = true;
                    }
                } else if ($USER->questloggedin[$quest->id]) {
                    $correctpass = true;
                }

                if (!$correctpass) {
                    echo "<br><br>";
                    echo $OUTPUT->box_start("center");
                    echo "<form name=\"password\" method=\"post\">\n";

                    echo "<table cellpadding=\"7px\">";
                    if (isset($userpassword)) {
                        echo "<tr align=\"center\" style='color:#DF041E;'><td>" . get_string("wrongpassword", "quest") .
                        "</td></tr>";
                    }
                    echo "<tr align=\"center\"><td>" . get_string("passwordprotectedquest", "quest", format_string($quest->name)) .
                    "</td></tr>";
                    echo "<tr align=\"center\"><td>" . get_string("enterpassword", "quest") .
                    " <input type=\"password\" name=\"userpassword\" /></td></tr>";

                    echo "<tr align=\"center\"><td>";
                    echo "<input type=\"button\" value=\"" . get_string("cancel") .
                    "\" onclick=\"parent.location='../../course/view.php?id=$course->id';\">  ";
                    echo "<input type=\"button\" value=\"" . get_string("continue") .
                    "\" onclick=\"document.password.submit();\" />";
                    echo "</td></tr></table>";
                    echo $OUTPUT->box_end();
                    echo $OUTPUT->footer();
                    exit();
                }
            }
        }

//////////////////////////////////////////////////////////
        function quest_get_user_answers($submission, $user) {
            global $DB;
            return $DB->get_records_select("quest_answers", "submissionid = ? AND userid = ? AND date > 0",
                            array($submission->id, $user->id), "date DESC");
        }

//////////////////////////////////////////////////////////
        function quest_get_submission_answers($submission) {
            global $DB;
            return $DB->get_records_select("quest_answers", "submissionid = ? AND date > 0", array($submission->id), "date DESC");
        }

//////////////////////////////////////////////////////////
        function quest_get_user_answer($quest, $user) {
            global $DB;
            return $DB->get_records_select("quest_answers", "questid = ? AND userid = ? AND date > 0",
                            array($quest->id, $user->id), "date DESC");
        }

//////////////////////////////////////////////////////////
        function quest_answer_grade($quest, $answer, $all) {

            return ($answer->grade * $answer->pointsmax) / 100;
//    $grade = 0;
//    if ($assessments = quest_get_assessments($answer,$all)) {
//
//        foreach ($assessments as $assessment) {
//
//
//                    if($assessment->state == 2){
//                        $grade += $assessment->pointsteacher;
//                    }
//                    else if($assessment->state == 1)
//                    {
//                        $grade+=$assessment ->pointsautor;
//                    }
//
//        }
//    }
//    return $grade;
        }

//////////////////////////////////////////////////////////
        function quest_get_user_assessment($answer) {
            global $DB;
            return $DB->get_records_select("quest_assessments", "answerid = ? AND dateassessment > 0", array($answer->id),
                            "dateassessment DESC");
        }

//////////////////////////////////////////////////////////
        function quest_get_user_assessments($quest, $user) {
            global $DB;
            return $DB->get_records_select("quest_assessments", "questid = ? AND userid = ? AND dateassessment > 0",
                            array($quest->id, $user->id), "dateassessment DESC");
        }

//////////////////////////////////////////////////////////
        function quest_get_user_clasification($quest, $user) {
            global $DB;
            return $DB->get_records_select("quest_calification_users", "questid = ? AND
        userid = ? ", array($quest->id, $user->id));
        }

//////////////////////////////////////////////////////////
        function quest_get_calification($quest) {
            global $DB;
            return $DB->get_records_select("quest_calification_users", "questid = ?", array($quest->id), "points ASC");
        }

//////////////////////////////////////////////////////////////
        function quest_get_calification_teams($quest) {
            global $DB;
            return $DB->get_records('quest_calification_teams', array('questid' => $quest->id), "points ASC");
        }

//////////////////////////////////////////////////////////////
        function quest_get_answers($quest, $user) {
            global $DB;
            return $DB->get_records("quest_answers", array('userid' => $user->id, 'questid' => $quest->id));
        }

        /**
         * Get all users that act as student (i.e. can 'mod/quest:attempt')
         * @param int $courseid
         * @return array of user records
         */
        function get_course_students($courseid) {
            $context = context_course::instance($courseid);
            $members = get_users_by_capability($context, 'mod/quest:attempt');
            return $members;
        }

        /**
         *
         * @param stdClass $quest
         * @return Ambigous <number, unknown>
         */
        function quest_get_maxpoints_teams(stdClass $quest) {
            global $DB;
            $maxpoints = -1;

            $califications_team = $DB->get_records('quest_calification_teams', array("questid" => $quest->id));
            foreach ($califications_team as $calification_team) {
                $grade = $calification_team->points;
                if ($grade > $maxpoints) {
                    $maxpoints = $grade;
                }
            }
// 		if ($students = get_course_students($quest->course)) {
// 			foreach ($students as $student) {
// 				if($calification_student = $DB->get_record("quest_calification_users",array("questid"=>$quest->id,"userid"=>$student->id))){
// 					if($quest->allowteams){
// 						if($calification_team = $DB->get_record("quest_calification_teams", array("questid"=> $quest->id, "teamid"=>$calification_student->teamid))){
// 							$grade = $calification_team->points;
// 						}
// 					}
// 					if($grade > $maxpoints){
// 						$maxpoints = $grade;
// 					}
// 				}
// 			}
// 		}

            return $maxpoints;
        }

        /**
         *
         * @global type $CFG
         * @global type $OUTPUT
         * @param type $course
         * @param type $isteacher
         * @param type $timestart
         * @return type
         */
        function quest_print_recent_activity($course, $isteacher, $timestart) {
            global $CFG, $OUTPUT;

            $context = context_course::instance($course->id);
            $ismanager = has_capability('mod/quest:manage', $context);

            $submitsubmissioncontent = false;
            if ($isteacher) {
                if ($logs = quest_get_submitsubmission_logs($course, $timestart)) {
                    foreach ($logs as $log) {
                        // Create a temp valid module structure (only need courseid, moduleid)
                        $tempmod = new stdClass();
                        $tempmod->course = $course->id;
                        $tempmod->id = $log->questid;
                        //Obtain the visible property from the instance
                        if (instance_is_visible("quest", $tempmod)) {
                            $submitsubmissioncontent = true;
                            break;
                        }
                    }
                    if ($submitsubmissioncontent) {
                        echo $OUTPUT->heading(get_string("questsubmitsubmission", "quest") . ":");
                        foreach ($logs as $log) {
                            //Create a temp valid module structure (only need courseid, moduleid)
                            $tempmod->course = $course->id;
                            $tempmod->id = $log->questid;
                            if (!has_capability('mod/quest:manage', $context, $log->userid)) {
                                //Obtain the visible property from the instance
                                if (instance_is_visible("quest", $tempmod)) {
                                    print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                            $CFG->wwwroot . '/mod/quest/' . $log->url);
                                }
                            }
                        }
                    }
                }
            }

            $submitsubmissionusercontent = false;
            if (!$isteacher) {
                if ($logs = quest_get_submitsubmissionuser_logs($course, $timestart)) {
                    foreach ($logs as $log) {
                        // Create a temp valid module structure (only need courseid, moduleid)
                        $tempmod->course = $course->id;
                        $tempmod->id = $log->questid;
                        //Obtain the visible property from the instance
                        if (instance_is_visible("quest", $tempmod)) {
                            $submitsubmissionusercontent = true;
                            break;
                        }
                    }
                    if ($submitsubmissionusercontent) {
//                 print_headline(get_string("questsubmitsubmission", "quest").":");
                        echo $OUTPUT->heading(get_string("questsubmitsubmission", "quest"));
                        foreach ($logs as $log) {
                            //Create a temp valid module structure (only need courseid, moduleid)
                            $tempmod->course = $course->id;
                            $tempmod->id = $log->questid;

                            //Obtain the visible property from the instance
                            if (instance_is_visible("quest", $tempmod)) {
                                print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                        $CFG->wwwroot . '/mod/quest/' . $log->url);
                            }
                        }
                    }
                }
            }

            $approvesubmissioncontent = false;
            if (!$isteacher) {
                if ($logs = quest_get_approvesubmission_logs($course, $timestart)) {
                    foreach ($logs as $log) {
                        // Create a temp valid module structure (only need courseid, moduleid)
                        $tempmod->course = $course->id;
                        $tempmod->id = $log->questid;
                        //Obtain the visible property from the instance
                        if (instance_is_visible("quest", $tempmod)) {
                            $approvesubmissioncontent = true;
                            break;
                        }
                    }
                    if ($approvesubmissioncontent) {
//                 print_headline(get_string("questapprovesubmission", "quest").":");
                        echo $OUTPUT->heading(get_string("questapprovesubmission", "quest"));
                        foreach ($logs as $log) {
                            //Create a temp valid module structure (only need courseid, moduleid)
                            $tempmod->course = $course->id;
                            $tempmod->id = $log->questid;

                            //Obtain the visible property from the instance
                            if (instance_is_visible("quest", $tempmod)) {
                                print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                        $CFG->wwwroot . '/mod/quest/' . $log->url);
                            }
                        }
                    }
                }
            }


            // have a look for new assessments for this user (assess)
            $assessmentcontent = false;
            if (!$isteacher) { // teachers only need to see submissions
                if ($logs = quest_get_assessments_logs($course, $timestart)) {
                    // got some, see if any belong to a visible module
                    foreach ($logs as $log) {
                        // Create a temp valid module structure (only need courseid, moduleid)
                        $tempmod = new stdClass();
                        $tempmod->course = $course->id;
                        $tempmod->id = $log->questid;
                        //Obtain the visible property from the instance
                        if (instance_is_visible("quest", $tempmod)) {
                            $assessmentcontent = true;
                            break;
                        }
                    }
                    // if we got some "live" ones then output them
                    if ($assessmentcontent) {
//                 print_headline(get_string("questassessments", "quest").":");
                        echo $OUTPUT->heading(get_string("questassessments", "quest") . ":");
                        foreach ($logs as $log) {
                            //Create a temp valid module structure (only need courseid, moduleid)
                            $tempmod->course = $course->id;
                            $tempmod->id = $log->questid;
                            //Obtain the visible property from the instance
                            if (instance_is_visible("quest", $tempmod)) {
                                if (!has_capability('mod/quest:manage', $context)) {  // don't break anonymous rule
                                    $log->firstname = ''; //$course->student;
                                    $log->lastname = '';
                                }
                                print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                        $CFG->wwwroot . '/mod/quest/' . $log->url);
                            }
                        }
                    }
                }
            }

            $assessmentautorcontent = false;
            if (!$isteacher) { // teachers only need to see submissions
                if ($logs = quest_get_assessmentsautor_logs($course, $timestart)) {
                    // got some, see if any belong to a visible module
                    foreach ($logs as $log) {
                        // Create a temp valid module structure (only need courseid, moduleid)
                        $tempmod->course = $course->id;
                        $tempmod->id = $log->questid;
                        //Obtain the visible property from the instance
                        if (instance_is_visible("quest", $tempmod)) {
                            $assessmentautorcontent = true;
                            break;
                        }
                    }
                    // if we got some "live" ones then output them
                    if ($assessmentautorcontent) {
                        echo $OUTPUT->heading(get_string("questassessments", "quest") . ":");
                        foreach ($logs as $log) {
                            //Create a temp valid module structure (only need courseid, moduleid)
                            $tempmod->course = $course->id;
                            $tempmod->id = $log->questid;
                            //Obtain the visible property from the instance
                            if (instance_is_visible("quest", $tempmod)) {

                                print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                        $CFG->wwwroot . '/mod/quest/' . $log->url);
                            }
                        }
                    }
                }
            }

            // have a look for new assessment gradings for this user (grade)
            $answercontent = false;
            if ($logs = quest_get_submitanswer_logs($course, $timestart)) {
                // got some, see if any belong to a visible module
                foreach ($logs as $log) {
                    // Create a temp valid module structure (only need courseid, moduleid)
                    $tempmod->course = $course->id;
                    $tempmod->id = $log->questid;
                    //Obtain the visible property from the instance
                    if (instance_is_visible("quest", $tempmod)) {
                        $answercontent = true;
                        break;
                    }
                }
                // if we got some "live" ones then output them
                if ($answercontent) {
                    echo $OUTPUT->heading(get_string("questsubmitanswer", "quest") . ":");
                    foreach ($logs as $log) {
                        //Create a temp valid module structure (only need courseid, moduleid)
                        $tempmod->course = $course->id;
                        $tempmod->id = $log->questid;
                        //Obtain the visible property from the instance
                        if (instance_is_visible("quest", $tempmod)) {
                            /* $log->firstname = $course->student;    // Keep anonymous
                              $log->lastname = ''; */
                            print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                    $CFG->wwwroot . '/mod/quest/' . $log->url);
                        }
                    }
                }
            }


            return $submitsubmissioncontent or $submitsubmissionusercontent or $approvesubmissioncontent or $assessmentcontent or $assessmentautorcontent or $answercontent;
        }
