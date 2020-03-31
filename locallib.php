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
/** Library of extra functions and module quest
 *
 * quest constants and standard Moodle functions plus the quest functions
 * called by the standard functions
 * see also locallib.php for other non-standard quest functions
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License.
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->dirroot/mod/quest/lib.php");
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/lib/formslib.php');
$repeatactionsbelow = false; // Repeat actions at the bottom of pages to easy the access on long.
                             // ...pages..
$questtype = array(0 => get_string('notgraded', 'quest'), 1 => get_string('accumulative', 'quest'));

$questshowgrades = array(0 => get_string('dontshowgrades', 'quest'), 1 => get_string('showgrades', 'quest'));

$questscales = array(
                0 => array('name' => get_string('scaleyes', 'quest'), 'type' => 'radio', 'size' => 2, 'start' => get_string('yes'),
                                'end' => get_string('no')),
                1 => array('name' => get_string('scalepresent', 'quest'), 'type' => 'radio', 'size' => 2,
                                'start' => get_string('present', 'quest'), 'end' => get_string('absent', 'quest')),
                2 => array('name' => get_string('scalecorrect', 'quest'), 'type' => 'radio', 'size' => 2,
                                'start' => get_string('correct', 'quest'), 'end' => get_string('incorrect', 'quest')),
                3 => array('name' => get_string('scalegood3', 'quest'), 'type' => 'radio', 'size' => 3,
                                'start' => get_string('good', 'quest'), 'end' => get_string('poor', 'quest')),
                4 => array('name' => get_string('scaleexcellent4', 'quest'), 'type' => 'radio', 'size' => 4,
                                'start' => get_string('excellent', 'quest'), 'end' => get_string('verypoor', 'quest')),
                5 => array('name' => get_string('scaleexcellent5', 'quest'), 'type' => 'radio', 'size' => 5,
                                'start' => get_string('excellent', 'quest'), 'end' => get_string('verypoor', 'quest')),
                6 => array('name' => get_string('scaleexcellent7', 'quest'), 'type' => 'radio', 'size' => 7,
                                'start' => get_string('excellent', 'quest'), 'end' => get_string('verypoor', 'quest')),
                7 => array('name' => get_string('scale10', 'quest'), 'type' => 'selection', 'size' => 10),
                8 => array('name' => get_string('scale20', 'quest'), 'type' => 'selection', 'size' => 20),
                9 => array('name' => get_string('scale100', 'quest'), 'type' => 'selection', 'size' => 100));
// Constants..
define('QUEST_TYPE_GRADE_INDIVIDUAL', 0);
define('QUEST_TYPE_GRADE_TEAM', 1);
$questeweights = array(0 => -4.0, 1 => -2.0, 2 => -1.5, 3 => -1.0, 4 => -0.75, 5 => -0.5, 6 => -0.25, 7 => 0.0, 8 => 0.25, 9 => 0.5,
                10 => 0.75, 11 => 1.0, 12 => 1.5, 13 => 2.0, 14 => 4.0);
$questfweights = array(0 => 0, 1 => 0.1, 2 => 0.25, 3 => 0.5, 4 => 0.75, 5 => 1.0, 6 => 1.5, 7 => 2.0,
                8 => 3.0, 9 => 5.0, 10 => 7.5, 11 => 10.0, 12 => 50.0);
$questeweightsrecalif = array(0 => -4.0, 1 => -2.0, 2 => -1.5, 3 => -1.0, 4 => -0.75, 5 => -0.5, 6 => -0.25,
                7 => 0.0, 8 => 0.25, 9 => 0.5, 10 => 0.75, 11 => 1.0, 12 => 1.5, 13 => 2.0, 14 => 4.0);
/** assesment->state
 * 0 sin realizar
 * 1 realizada autor
 * 2 realizada profesor
 *
 * assessment->phase
 * 0 sin aprobar
 * 1 aprobada */
define('ASSESSMENT_STATE_UNDONE', 0);
define('ASSESSMENT_STATE_BY_AUTOR', 1);
define('ASSESSMENT_STATE_BY_TEACHER', 2);
define('ASSESSMENT_PHASE_APPROVAL_PENDING', 0);
define('ASSESSMENT_PHASE_APPROVED', 1);
/** answer->state
 * 0 sin editar
 * 1 editada
 * 2 modificada (evaluada manualmente?) //evp this should be clearly defined.
 *
 * answer->phase
 * 0 sin evaluar
 * 1 evaluada
 * 2 aprobada (evaluada >50%)
 *
 * answer->permitsubmit
 * 0 no editable
 * 1 editable */
define('ANSWER_STATE_UNEDITTED', 0);
define('ANSWER_STATE_EDITTED', 1);
define('ANSWER_STATE_MODIFIED', 2);
define('ANSWER_PHASE_UNGRADED', 0);
define('ANSWER_PHASE_GRADED', 1);
define('ANSWER_PHASE_PASSED', 2);
define('ANSWER_PERMITSUBMIT_NO_EDITABLE', 0);
define('ANSWER_PERMITSUBMIT_EDITABLE', 1);

/** submission->state
 * 2 teacher, approved statte
 * 1 approval pending state */
define('SUBMISSION_STATE_APROVED', 2);
define('SUBMISSION_STATE_APPROVAL_PENDING', 1);

define('SUBMISSION_PHASE_ACTIVE', 1);
define('SUBMISSION_PHASE_CLOSED', 0);

/*
 * * Functions for the QUEST module ******
 * *************************************
 */

/**
 * @param array $options
 * @param string $name
 * @param string $selected
 * @param string $nothing
 * @param string $script
 * @param string $nothingvalue
 * @param boolean $returnhtml
 * @return string */
function quest_choose_from_menu($options, $name, $selected = "", $nothing = "choose", $script = "",
                                $nothingvalue = "0", $returnhtml = false) {
    // Given an array of value, creates a popup menu to be part of a form: $options["value"]["label"].
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

    if ($returnhtml) {
        return $output;
    } else {
        echo $output;
    }
}
/**
 *
 * @param \stdClass $quest
 */
function quest_print_quest_heading($quest) {
    global $OUTPUT;
    echo $OUTPUT->pix_icon('icon', 'Quest', 'quest', array('align' => 'left'));
    echo $OUTPUT->heading(format_string($quest->name));
    quest_print_quest_info($quest);
}
/**
 *
 * @param \stdClass $quest
 */
function quest_print_quest_info($quest) {
    global $CFG, $DB, $OUTPUT;

    echo $OUTPUT->box_start();
    // ...print phase and date info.
    $string = '<b>' . get_string('currentphase', 'quest') . '</b>: ' . quest_phase($quest) . '<br />';
    $dates = array('dateofstart' => $quest->datestart, 'dateofend' => $quest->dateend);

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
/**
 *
 * @param \stdClass $cm
 * @param \stdClass $context
 * @param \stdClass $quest
 */
function quest_print_challenge_grading_link($cm, $context, $quest) {
    global $OUTPUT;
    $text = "<a href=\"assessments_autors.php?id=$cm->id&amp;action=displaygradingform\">" .
             get_string("specimenassessmentformsubmission", "quest") . "</a>";
    $text .= $OUTPUT->help_icon('specimensubmission', 'quest');

    if (has_capability('mod/quest:manage', $context) and $quest->nelements) {
        $editicon = $OUTPUT->pix_icon('t/edit', get_string('amendassessmentelements', 'quest'));
        $text .= "<a href=\"assessments_autors.php?id=$cm->id&amp;action=editelements&sesskey=" . sesskey() . "\">" . $editicon .
                 '</a>';
    }
    echo ($text);
}
/**
 *
 * @param \stdClass $cm
 * @param \stdClass $context
 * @param \stdClass $quest
 */
function quest_print_answer_grading_link($cm, $context, $quest) {
    global $OUTPUT;
    $text = "<a href=\"assessments.php?id=$cm->id&amp;viewgeneral=1&amp;action=displaygradingform\">" .
             get_string("specimenassessmentformanswer", "quest") . "</a>";
    $text .= $OUTPUT->help_icon('specimenanswer', 'quest');

    if (has_capability('mod/quest:manage', $context) and $quest->nelements) {
        $editicon = $OUTPUT->pix_icon('t/edit', get_string('amendassessmentelements', 'quest'));
        $url = new moodle_url('assessments.php', ['id' => $cm->id, 'newform' => 0, 'cambio' => 0, 'viewgeneral' => 1,
                        'action' => 'editelements', 'sesskey' => sesskey()]);
        $text .= "&nbsp;<a href=\"" . $url->out() . "\">" . $editicon . '</a>';
    }
    echo ($text);
}
/**
 *
 * @param \stdClass $quest
 * @param string $style
 * @return string
 */
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
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $submission
 * @return string
 */
function quest_print_submission_title($quest, $submission) {
    // Arguments are objects.
    $cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course, null, MUST_EXIST);

    if (!$submission->timecreated) { // ...a "no submission".
        return $submission->title;
    }
    $url = (new moodle_url('submissions.php', ['id' => $cm->id, 'sid' => $submission->id, 'action' => 'showsubmission']))->out();
    return "<a name=\"sid_$submission->id\" href=\"$url\">$submission->title</a>";
}
/**
 * Form for a Challenge
 * @author juacas
 *
 */
class quest_print_upload_form extends moodleform {

    public function definition() {
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
        $mform->addElement('hidden', 'nosubmit', 0); // ...!!!!evp esto tiene sentido si usamos el
                                                     // js.
                                                     // ...definido. hay que ver si es necesario.
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
            // ...$mform->addElement('html', '<div class="fitemtitle"> '.$stringchallengestart.' :.
            // ...'.$date.' </div>');.
            $mform->addElement('html', get_string("challengestart", "quest") . ': ' . userdate($challengestart));
            $mform->addElement('hidden', 'datestart', $challengestart);
        }
        $mform->setType('datestart', PARAM_INT);

        $challengeend = $challengestart + $quest->timemaxquestion * 24 * 3600;
        if ($challengeend > $quest->dateend) {
            $challengeend = $quest->dateend;
        }
        if ($ismanager) {
            $mform->addElement('date_time_selector', 'dateend', get_string('challengeend', "quest"));
            $mform->setDefault('dateend', $challengeend);
            $mform->addHelpButton('dateend', 'challengeend', 'quest');
        } else {
            // ...$mform->addElement('html', '<div class="fitemtitle"> '.$stringchallengestart.' :.
            // ...'.$date.' </div>');.
            $mform->addElement('html', '</br>' . get_string("challengeend", "quest") . ': ' . userdate($challengeend));
            $mform->addElement('hidden', 'dateend', $challengeend);
        }
        $mform->setType('dateend', PARAM_INT);

        for ($i = $quest->mincalification; $i <= $quest->maxcalification; $i++) {
            $numbers[$i] = $i;
        }

        $mform->addElement('select', 'pointsmax', get_string("pointsmax", "quest"), $numbers);
        $mform->setDefault('pointsmax', $quest->maxcalification);
        $mform->addHelpButton('pointsmax', 'pointsmax', 'quest');
        $mform->addElement('select', 'pointsmin', get_string("pointsmin", "quest"), $numbers);
        $mform->setDefault('pointsmin', $quest->mincalification);
        $mform->addHelpButton('pointsmin', 'pointsmin', 'quest');

        unset($numbers);
        if ($ismanager) {
            for ($i = $quest->mincalification; $i <= $quest->maxcalification; $i++) {
                $numbers[$i] = $i;
            }
        } else {
            for ($i = $quest->mincalification; $i <= $quest->initialpoints; $i++) {
                $numbers[$i] = $i;
            }
        }

        $mform->addElement('select', 'initialpoints', get_string("initialpoints", "quest"), $numbers);
        $mform->addHelpButton('initialpoints', 'initialpoints', 'quest');
        $mform->setDefault('initialpoints', $quest->initialpoints);

        if ($quest->nattachments) {
            $mform->addElement('filemanager', 'attachment_filemanager', get_string("attachments", "quest"),
                                null, $attachmentoptions);
        }
        if ($action == 'approve') {
            $mform->addElement('textarea', 'commentteacherauthor', get_string("commentsforauthor", "quest"), 'rows="6" cols="70"');
            $mform->addHelpButton('commentteacherauthor', 'commentsforauthor', 'quest');
        }
        if ($ismanager) {
            $mform->addElement('textarea', 'commentteacherpupil', get_string("commentsforstudent", "quest"), 'rows="6" cols="70"');
            $mform->addHelpButton('commentteacherpupil', 'commentsforstudent', 'quest');
            $difficultyscale = quest_get_difficulty_levels();
            $radioarray = array();
            foreach ($difficultyscale as $value => $item) {
                $radioarray[] = & $mform->createElement('radio', 'perceiveddifficulty', '', $item, $value);
            }
            $mform->addGroup($radioarray, 'perceiveddifficultyOps', get_string("perceivedTeacherDifficultyLevel", "quest"),
                    array(' '), false);
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
        // In the form the id hidden element is used to hold the cmid of the quest.
        $submission->sid = $submission->id;
        $submission->id = $cm->id;
        $this->set_data($submission);
    }
    /**
     *
     * {@inheritDoc}
     * @see moodleform::validation()
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $a = new stdClass();
        $quest = $this->_customdata['quest'];
        $a->questdatestart = userdate($quest->datestart);
        $a->questdateend = userdate($quest->dateend);

        if ($data['datestart'] < $quest->datestart) {
            $errors['datestart'] = get_string('invaliddates', 'quest', $a);
        }
        if ($data['dateend'] > $quest->dateend) {
            $errors['dateend'] = get_string('invaliddates', 'quest', $a);
        }
        if ($data['datestart'] >= $data['dateend']) {
            $errors['datestart'] = get_string('invaliddates', 'quest', $a);
        }
        if ($data['pointsmax'] > $quest->maxcalification) {
            $errors['pointsmax'] = get_string('checkthat', 'quest'). ': ' . get_string('pointsmin', 'quest') .  ' (' . $data['pointsmin'] . ')' .
                    ' < ' .get_string('pointsmax', 'quest') . ' (' . $data['pointsmax'] . ')' . ' < ' . $quest->maxcalification;
        }
        if ($data['pointsmin'] < $quest->mincalification) {
            $errors['pointsmin'] = get_string('checkthat', 'quest'). ': ' .
                   $quest->mincalification .
                   ' < ' . get_string('pointsmin', 'quest') .  ' (' . $data['pointsmin'] . ')' . ' < ' .
                    get_string('pointsmax', 'quest') . ' (' . $data['pointsmax'] . ')';

        }
        if ($data['pointsmax'] < $data['pointsmin']) {
            $errors['pointsmax'] = get_string('checkthat', 'quest'). ': ' .
                    get_string('pointsmin', 'quest') . ' (' . $data['pointsmin'] . ')' .
                    ' < ' . get_string('pointsmax', 'quest') . ' (' . $data['pointsmax'] . ')' . ' < ' . $quest->maxcalification;
            $errors['pointsmin'] = $errors['pointsmax'];
        }
        if ($data['pointsmax'] < $data['initialpoints']) {
            $errors['initialpoints'] = get_string('checkthat', 'quest') . ': ' .
                    get_string('initialpoints', 'quest') . ' (' . $data['initialpoints'] . ')' .
                    ' < ' . get_string('pointsmax', 'quest') . ' (' .  $data['pointsmax'] . ')';
        }
        if ($data['pointsmin'] > $data['initialpoints']) {
            $errors['initialpoints'] = get_string('checkthat', 'quest'). ': ' .
                    get_string('initialpoints', 'quest') . ' (' . $data['initialpoints'] . ')' .
                    ' > ' . get_string('pointsmin', 'quest') . ' (' . $data['pointsmin'] . ')';
        }
        return $errors;
    }
}

/** Receive and store a new challenge for the quest
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
 * @param \stdClass $cm
 * @param array $definitionoptions
 * @param array $attachmentoptions
 * @param \stdClass $context
 * @param string $action
 * @param int $authorid author of the $newsubmission will override $newsubmission->userid */
function quest_upload_challenge(stdClass $quest, stdClass $newsubmission, $ismanager, $cm, $definitionoptions, $attachmentoptions,
        $context, $action, $authorid) {
    global $USER, $DB, $CFG, $OUTPUT, $COURSE, $PAGE;

    // ...get the current set of submissions.
    // ...add new submission record.
    $newsubmission->questid = $quest->id;
    $newsubmission->userid = $authorid;
    $newsubmission->id = $newsubmission->sid; // ...id is overused in the form but must be named id.
                                              // ...for the database..
    $newsubmission->description = ''; // ...updated later.
    $newsubmission->descriptionformat = FORMAT_HTML; // ...updated later.
    $newsubmission->descriptiontrust = 0; // ...updated later.
    $newsubmission->timecreated = time();
    $canapprove = has_capability('mod/quest:approvechallenge', $context);
    if ($ismanager) {
        if (!isset($newsubmission->perceiveddifficulty)) {
            $newsubmission->perceiveddifficulty = -1;
        }
    }
    if ($newsubmission->dateend > $quest->dateend) {
        $newsubmission->dateend = $quest->dateend;
    }
    if ($newsubmission->initialpoints > $newsubmission->pointsmax) {
        $newsubmission->initialpoints = $newsubmission->pointsmax;
    }
    if (empty($newsubmission->id)) { // ...$newsubmission->sid is not defined or empty if this is a
                                     // new submission.
        $isnew = true;
        if ($canapprove) {
            $newsubmission->state = SUBMISSION_STATE_APROVED; // ...if teacher, approved state..
        } else {
            $newsubmission->state = SUBMISSION_STATE_APPROVAL_PENDING; // ...if student approval
                                                                       // pending state..
        }
        if (!$newsubmission->id = $DB->insert_record("quest_submissions", $newsubmission)) {
            print_error('inserterror', 'quest', null, "quest_submissions");
        }
    } else {
        $isnew = false;
        if ($canapprove && $action === 'approve') { // ...the challenge is approved by the
                                                    // teacher..
            $newsubmission->state = SUBMISSION_STATE_APROVED;
        } else { // The challenge is modified, the status does not change.
            $newsubmission->state = $DB->get_field('quest_submissions', 'state', array('id' => $newsubmission->id));
        }
    }

    // ...management of files: save embedded images and attachments..
    $newsubmission = file_postupdate_standard_editor($newsubmission, 'description', $definitionoptions, $context, 'mod_quest',
            'submission', $newsubmission->id);
    $newsubmission = file_postupdate_standard_filemanager($newsubmission, 'attachment', $attachmentoptions, $context, 'mod_quest',
            'attachment', $newsubmission->id);

    // ...store the updated values in table..
    $DB->update_record('quest_submissions', $newsubmission);

    if ($action == 'submitchallenge') {
        /*
         * recalculate points and report to gradebook
         */
        quest_grade_updated($quest, $USER->id);
    }

    quest_update_challenge_calendar($cm, $quest, $newsubmission);
    $redirecturl = new moodle_url('/mod/quest/submissions.php', ['id' => $cm->id, 'sid' => $newsubmission->id,
                    'action' => 'showsubmission']);
    if ($action == 'submitchallenge') {
        require_once('classes/event/challenge_created.php');
        mod_quest\event\challenge_created::create_from_parts($newsubmission, $cm)->trigger();
    } else if ($action == 'modif') {
        if ($CFG->version >= 2014051200) {
            require_once('classes/event/challenge_updated.php');
            \mod_quest\event\challenge_updated::create_from_parts($USER, $newsubmission, $cm)->trigger();
        } else {
            add_to_log($COURSE->id, "quest", "modif_submission",
                    "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id",
                    "$cm->id");
        }

    } else if ($action == 'approve') {
        if ($CFG->version >= 2014051200) {
            require_once('classes/event/challenge_approved.php');
            \mod_quest\event\challenge_approved::create_from_parts($USER, $newsubmission, $cm)->trigger();
        } else {
            add_to_log($COURSE->id, "quest", "approve_submission",
                    "submissions.php?id=$cm->id&amp;sid=$newsubmission->id&amp;action=showsubmission", "$newsubmission->id",
                    "$cm->id");
        }
        // Get next url: assess_autor or approve.
        $redirecturl = quest_next_submission_url($newsubmission, $cm);
    }
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string("submitted", "quest") . " " . get_string("ok"));
    echo $OUTPUT->continue_button($redirecturl);
}
/**
 *
 * @return string[]
 */
function quest_get_durations() {
    $minutes = array(-1 => "", 1 => " 1 " . get_string("minutes", "moodle"), 2 => " 2 " . get_string("minutes", "moodle"),
                    5 => " 5 " . get_string("minutes", "moodle"), 10 => "10 " . get_string("minutes", "moodle"),
                    15 => "15 " . get_string("minutes", "moodle"), 20 => "20 " . get_string("minutes", "moodle"),
                    25 => "25 " . get_string("minutes", "moodle"), 30 => "30 " . get_string("minutes", "moodle"),
                    45 => "45 " . get_string("minutes", "moodle"), 60 => " 1 " . get_string("hour", "moodle"));

    // ...some half hours.
    for ($i = 90; $i < 12 * 60; $i = $i + 30) {
        if ($i % 60 == 0) {
            $minutes[$i] = floor($i / 60) . " " . get_string("hours", "moodle");
        } else {
            $minutes[$i] = floor($i / 60) . " " . get_string("hours", "moodle") . " 30 " . get_string("minutes", "moodle");
        }
    }
    $minutes[24 * 60] = " 1 " . get_string("day");
    // ...some days.
    for ($i = 1; $i <= 15; $i++) {
        $minutes[24 * 60 * $i] = " " . $i . " " . get_string("days", "moodle");
    }
    // ...some weeks.
    for ($i = 3; $i <= 4; $i++) {
        $minutes[24 * 60 * 7 * $i] = " " . $i . " " . get_string("weeks", "moodle");
    }
    // ...some months.
    for ($i = 5; $i <= 12; $i++) {
        $minutes[24 * 60 * 30 * $i] = " " . $i . " " . get_string("months", "moodle");
    }
    return $minutes;
}
/**
 *
 * @return string[]
 */
function quest_get_difficulty_levels() {
    return array(0 => get_string("difficultyEasy", "quest"), 1 => get_string("difficultyAttainable", "quest"),
                    2 => get_string("difficultyHard", "quest"));
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $submission
 */
function quest_print_submission($quest, $submission) {
    // ...prints the submission with optional attachments.
    global $USER, $OUTPUT;

    $cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course, null, MUST_EXIST);
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
    $canpreview = has_capability('mod/quest:preview', $context);

    if (!empty($submission->comentteacherautor)) {
        if (($submission->userid == $USER->id) || ($canpreview)) {
            echo $OUTPUT->heading(get_string('commentsforauthor', 'quest'));
            echo $OUTPUT->box(format_text($submission->comentteacherautor), 'center');
        }
    }
    if (!empty($submission->comentteacherpupil)) {
        echo $OUTPUT->heading_with_help(get_string('commentsforstudent', 'quest'), 'commentsforstudent', 'quest');
        echo $OUTPUT->box(format_text($submission->comentteacherpupil), 'center');
    }

    if ($quest->nattachments) {
        if ($submission->attachment) {
            quest_print_attachments($context, 'attachment', $submission->id, 'timemodified');
        }
    }
    return;
}
/**
 *
 * @param \stdClass $context
 * @param string $filearea
 * @param string $itemid
 * @param string $order
 */
function quest_print_attachments($context, $filearea, $itemid, $order) {
    global $OUTPUT;
    $n = 1;
    echo "<table align=\"center\">\n";
    $fs = get_file_storage();
    if ($files = $fs->get_area_files($context->id, 'mod_quest', $filearea, $itemid, $order, false)) {
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle',
                    array('class' => 'icon'));
            $path = "/$context->id/mod_quest/$filearea/";
            if ($itemid) {
                $path .= $itemid . '/';
            }
            $path .= $filename;
            $filepathurl = moodle_url::make_pluginfile_url($file->get_contextid(),
                                            $file->get_component(),
                                            $file->get_filearea(),
                                            $file->get_itemid(),
                                            $file->get_filepath(),
                                            $file->get_filename());
            $path = $filepathurl->out();
            echo "<tr><td><b>" . get_string("attachment", "quest") . " $n:</b> \n";
            echo $iconimage;
            echo format_text("<a href=\"$path\">" . s($filename) . "</a>", FORMAT_HTML, array('context' => $context));
            $n++;
        }
    }
    echo "</table>\n";
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $submission
 */
function quest_print_submission_info($quest, $submission) {
    global $USER, $DB, $OUTPUT;

    $timenow = time();

    $course = $DB->get_record("course", array("id" => $quest->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
    // ...print standard assignment heading.
    $context = context_module::instance($cm->id);
    $canpreview = has_capability('mod/quest:preview', $context);
    echo $OUTPUT->box_start("center");

    // ...print phase and date info.
    $string = '<b>' . get_string('currentphasesubmission', 'quest') . '</b>: ' .
                quest_submission_phase($submission, $quest, $course) . '<br/>';
    $dates = array('dateofstart' => $submission->datestart, 'dateofend' => $submission->dateend);
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
        $string .= '<b>' . get_string('pointsmaxsubmission', 'quest') . ":&nbsp;&nbsp;$submission->pointsanswercorrect" .
        '</b><br>';
    }
    // Form field for the countdown of score.
    $string .= '<form name="puntos"><b>' . get_string('points', 'quest') .
                ";&nbsp;&nbsp;<input name=\"calificacion\" id=\"formscore\" type=\"text\" " .
                "value=\"0.000\" size=\"10\" readonly=\"1\" " .
                "style=\"background-color:White; border:black; color:Black; font-size:14pt; text-align : center;\"></form></b><br>";
    if (($USER->id == $submission->userid) || ($canpreview) || ($submission->dateend < time())) {
        if ($submission->evaluated == 1 && $assessment = $DB->get_record("quest_assessments_autors",
                array("questid" => $quest->id, "submissionid" => $submission->id))) {
            $string .= '<b>' . get_string('calificationautor', 'quest') . ': ';
            $string .= number_format(100 * $assessment->points / $submission->initialpoints, 1) . '% ';
            $string .= get_string('of', 'quest') . ' ' . get_string('initialpoints', 'quest') . ' ' . number_format(
                    $submission->initialpoints, 2);
            $string .= ' (' . number_format($assessment->points, 1) . ')</b>';
        } else {
            $string .= '<br><b>' . get_string('calificationautor', 'quest') . ': ' . get_string('evaluation_pending', 'quest') .
                     '</b>';
        }
    }

    if (($submission->datestart < $timenow) && ($submission->dateend > $timenow) &&
             ($submission->nanswerscorrect < $quest->nmaxanswers)) {
        $submission->phase = SUBMISSION_PHASE_ACTIVE;
    }
    echo $string;

    $initialpoints[] = (float) $submission->initialpoints;
    $nanswerscorrect[] = (int) $submission->nanswerscorrect;
    $datesstart[] = (int) $submission->datestart;
    $datesend[] = (int) $submission->dateend;
    $dateanswercorrect[] = (int) $submission->dateanswercorrect;
    $pointsmax[] = (float) $submission->pointsmax;
    $pointsmin[] = (float) $submission->pointsmin;
    $pointsanswercorrect[] = (float) $submission->pointsanswercorrect;
    $tinitial[] = $quest->tinitial * 86400;
    $state[] = (int) $submission->state;
    $type = $quest->typecalification;
    $nmaxanswers = (int) $quest->nmaxanswers;
    $pointsnmaxanswers[] = (float) $submission->points;
    // Javascript counter support.
    $forms[] = "#formscore";
    $incline[] = 0;
    $servertime = time();
    $params = [1, $pointsmax, $pointsmin, $initialpoints, $tinitial,
                    $datesstart, $state, $nanswerscorrect, $dateanswercorrect,
                    $pointsanswercorrect, $datesend,
                    $forms, $type, $nmaxanswers, $pointsnmaxanswers,
                    $servertime, null];
    global $PAGE;
    $PAGE->requires->js_call_amd('mod_quest/counter', 'puntuacionarray', $params);

    echo $OUTPUT->box_end();
}
/**
 *
 * @param \stdClass $submission
 * @param \stdClass $quest
 * @param \stdClass $course
 * @param string $style
 * @return string
 */
function quest_submission_phase($submission, $quest, $course, $style = '') {
    global $USER;

    $context = context_course::instance($course->id);
    $ismanager = has_capability('mod/quest:manage', $context);
    $cangrade = has_capability('mod/quest:grade', $context);
    $time = time();

    if ($submission->state == SUBMISSION_STATE_APPROVAL_PENDING) {
        if ($submission->evaluated == false) {
            return get_string('phase1submission' . $style, 'quest');
        } else if ($submission->evaluated == true) {
            if (($cangrade) || ($submission->userid == $USER->id)) {
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
                if (($cangrade) || ($submission->userid == $USER->id)) {
                    return get_string('phase8submission' . $style, 'quest');
                } else {
                    return get_string('phase2submission' . $style, 'quest');
                }
            }
        } else if (($time < $submission->dateend) && ($submission->nanswerscorrect < $quest->nmaxanswers)) {
            if ($submission->evaluated == 0) {
                return get_string('phase3submission' . $style, 'quest');
            } else if ($submission->evaluated == 1) {
                if (($cangrade) || ($submission->userid == $USER->id)) {
                    return get_string('phase6submission' . $style, 'quest');
                } else {
                    return get_string('phase3submission' . $style, 'quest');
                }
            }
        } else {
            if ($submission->evaluated == 0) {
                return get_string('phase4submission' . $style, 'quest');
            } else if ($submission->evaluated == 1) {
                if (($cangrade) || ($submission->userid == $USER->id)) {
                    return get_string('phase7submission' . $style, 'quest');
                } else {
                    return get_string('phase4submission' . $style, 'quest');
                }
            }
        }
    }
}
/**
 * Form for anwers.
 * @author juacas
 */
class quest_print_answer_form extends moodleform {

    public function definition() {
        $mform = & $this->_form;
        $currententry = $this->_customdata['current'];
        $quest = $this->_customdata['quest'];
        $cm = $this->_customdata['cm'];
        $definitionoptions = $this->_customdata['definitionoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];
        $action = $this->_customdata['action'];

        $context = context_module::instance($cm->id);

        $mform->addElement('text', 'title', get_string("title", "quest"), 'size="60" maxlength="100"');
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string("responsetochallenge", "quest"), null, $definitionoptions);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addRule('description_editor', null, 'required', null, 'client');

        if ($quest->nattachments) {
            $mform->addElement('filemanager', 'attachment_filemanager', get_string("attachments", "quest"),
                                null, $attachmentoptions);
        }
        $difficultyscale = quest_get_difficulty_levels();
        $radioarray = array();
        foreach ($difficultyscale as $value => $item) {
            $radioarray[] = & $mform->createElement('radio', 'perceiveddifficulty', '', $item, $value);
        }
        $mform->addGroup($radioarray, 'radioar', get_string("perceiveddifficultyLevelQuestion", "quest"), array(' '), false);
        $mform->addElement('hidden', 'aid', $currententry->id);
        $mform->setType('aid', PARAM_INT);
        $mform->addElement('hidden', 'id', $currententry->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', $currententry->submissionid);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHA);

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
 * @global stdClass $DB
 * @global type $COURSE
 * @global type $OUTPUT
 * @global stdClass $USER
 * @param \stdClass $quest
 * @param \stdClass $answer
 * @param bool $ismanager
 * @param \stdClass $cm
 * @param array $definitionoptions
 * @param array $attachmentoptions
 * @param \stdClass $context */
function quest_uploadanswer($quest, $answer, $ismanager, $cm, $definitionoptions, $attachmentoptions, $context) {
    global $DB, $COURSE, $OUTPUT, $USER;

    $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);
    $timenow = time();
    // ...variable $modif to check if the answer is new of is being modified.
    if (empty($answer->id)) {
        $modif = false;
        if (!$validate = quest_validate_user_answer($quest, $submission)) {
            print_error('answerexisty', 'quest', "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission");
        }
        $answer->questid = $quest->id;
        $answer->userid = $USER->id;
        $answer->submissionid = $answer->sid;
    } else {
        $modif = true;
        $answer->id = $DB->get_field('quest_answers', 'id', array('id' => $answer->id), MUST_EXIST);
        if (!($ismanager or (($USER->id == $answer->userid) and ($timenow < $quest->dateend)))) {
            print_error('answernoauthorizedupdate', 'quest',
                    "submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id");
        }
    }
    $answer->date = $timenow;
    $answer->description = ''; // ...updated later.
    $answer->descriptionformat = FORMAT_HTML; // ...updated later.
    $answer->descriptiontrust = 0; // ...updated later.
    $answer->commentforteacher = ''; // ...field defined in table as no null and no default value is indicated.

    $points = quest_get_points($submission, $quest, $answer);
    $answer->pointsmax = $points;

    if ($modif == false) {
        $answer->phase = 0;
        $answer->state = 1;
    } else {
        $answer->phase = $DB->get_field('quest_answers', 'phase', array('id' => $answer->id));
        if (($answer->phase == ANSWER_PHASE_GRADED) || ($answer->phase == ANSWER_PHASE_PASSED)) {
            $answer->state = ANSWER_STATE_MODIFIED;
        } else {
            $answer->state = ANSWER_STATE_EDITTED;
        }
    }
    if ($modif == false) {
        if (!$answer->id = $DB->insert_record("quest_answers", $answer)) {
            print_error('inserterror', 'quest', null, "quest_answers");
        }
    }
    $answer = file_postupdate_standard_editor($answer, 'description', $definitionoptions, $context, 'mod_quest', 'answer',
            $answer->id);

    // ...do something about the attachments, if there are any.
    if ($quest->nattachments) {
        // ...management of files: save embedded images and attachments.
        $answer = file_postupdate_standard_filemanager($answer, 'attachment', $attachmentoptions, $context, 'mod_quest',
                'answer_attachment', $answer->id);
    }
    $DB->update_record('quest_answers', $answer);

    // TODO: en este punto no hay cambio de calificaciÃ³n.
    // Update scores and statistics.
    // Update current User scores.
    require_once('scores_lib.php');
    $submission->nanswers = quest_count_submission_answers($submission->id);
    $DB->update_record('quest_submissions', $submission);
    quest_update_user_scores($quest, $answer->userid);
    // Update answer current team totals.
    if ($quest->allowteams) {
        quest_update_team_scores($quest->id, quest_get_user_team($quest->id, $answer->userid));
    }
    if (!$users = quest_get_course_members($COURSE->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer($course);
        exit();
    }
    // JPC 2013-11-28 disable excesive notifications.
    if (false) {
        foreach ($users as $user) {
            if ($ismanager) {
                quest_send_message($user,
                                    "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", 'answeradd',
                                    $quest, $submission, $answer, $USER);
            }
        }
    }
    // JPC disabled block.
    $user = get_complete_user_data('id', $submission->userid);
    if ($user) {
        quest_send_message($user, "answer.php?sid=$submission->id&amp;aid=$answer->id&amp;action=showanswer", 'answeradd', $quest,
                $submission, $answer);
    }

    if ($modif == false) {
        \mod_quest\event\answer_created::create_from_parts($submission, $answer, $cm)->trigger();
    } else {
        \mod_quest\event\answer_updated::create_from_parts($submission, $answer, $cm)->trigger();
    }
}

/**
 *
 * Enter description here ...
 * @param \stdClass $quest
 * @param \stdClass $submission
 * @param \stdClass $course
 * @param \stdClass $cm
 * @param string $sort
 * @param string $dir */
function quest_print_table_answers($quest, $submission, $course, $cm, $sort, $dir) {
    global $CFG, $USER, $DB, $OUTPUT;
    $groupmode = false;
    $currentgroup = 0;
    $timenow = time();
    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    // Get all the students.
    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }
    // Now prepare table with student assessments and submissions.
    $tablesort = new stdClass();
    $tablesort->data = array();
    $tablesort->sortdata = array();

    $ismanageruser = has_capability('mod/quest:manage', $context, $USER->id);

    if ($answers = quest_get_submission_answers($submission)) {
        foreach ($answers as $answer) {
            $data = array();
            $sortdata = array();
            // Can show the answer?.
            if (!$ismanager && $groupmode != false && $groupmode != VISIBLEGROUPS && !groups_is_member($currentgroup,
                    $answer->userid)) { // ...not in this group.
                continue;
            }

            if (($ismanager) || // ...admin.
                ($submission->userid == $USER->id) || // ...challenge owner.
                ($answer->userid == $USER->id) || // ...answer owner.
                ($submission->dateend < $timenow) || ($submission->nanswerscorrect >= $quest->nmaxanswers)) {
                // Challenge closed....
                $editicon = $OUTPUT->pix_icon('t/edit', get_string('modif', 'quest'));
                $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete', 'quest'));
                $mineicon = $answer->userid == $USER->id && !$ismanager ? $OUTPUT->user_picture($USER) : '';
                $answertitle = $mineicon . quest_print_answer_title($quest, $answer, $submission);
                $sesskey = sesskey();
                $editlink = " <a href=\"answer.php?action=modif&amp;id=$cm->id&amp;aid=$answer->id&amp;sid=$submission->id\">" .
                         $editicon . '</a>';
                $url = (new moodle_url('answer.php', ['id' => $cm->id, 'sid' => $submission->id, 'action' => 'confirmdelete',
                                         'aid' => $answer->id]))->out();
                $deletelink = " <a href=\"$url\">$deleteicon</a>";
                if ($ismanager) {
                    $data[] = $answertitle . $editlink . $deletelink;
                } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase == 0) &&
                         $submission->nanswerscorrect < $quest->nmaxanswers) {
                    $data[] = $answertitle . $editlink . $deletelink;
                } else if (($answer->userid == $USER->id) && ($submission->dateend > $timenow) && ($answer->phase > 0) &&
                         ($answer->permitsubmit == 1)) {
                    $data[] = $answertitle . $editlink;
                } else {
                    $data[] = $answertitle;
                }
                $sortdata['title'] = strtolower($answer->title);

                $user = get_complete_user_data('id', $answer->userid);
                if (!$user) {
                    continue;
                }
                // User Name Surname.
                if ($ismanager) {
                    $data[] = $user ? $OUTPUT->user_picture($user) : '';
                    $data[] = "<a name=\"userid->id\" href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
                             fullname($user) . '</a>';
                    $sortdata['firstname'] = strtolower($user->firstname);
                    $sortdata['lastname'] = strtolower($user->lastname);
                }
                // Answer Phase.
                $data[] = quest_answer_phase($answer, $course);
                $sortdata['phase'] = quest_answer_phase($answer, $course);

                $data[] = userdate($answer->date, get_string('datestr', 'quest'));
                $sortdata['dateanswer'] = $answer->date;

                if (($answer->phase == 1) || ($answer->phase == 2)) {
                    $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id));
                } else {
                    $assessment = null;
                }

                if (!$ismanageruser && ($groupmode == 2)) {
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
                if ($answer->pointsmax == 0) {
                    $grade = number_format($score, 4) . ' (' . get_string('phase4submission', 'quest') . ')';
                } else {
                    $grade = number_format($score, 4) . ' (' . number_format($answer->grade, 0) . '%) [max:' . number_format(
                            $answer->pointsmax, 4) . ']';
                }
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
            } // ...if user is authorized to view answer.
        } // ...for each answer.
    } // ...if there are answers.
      // Uses globals $sort, $dir.
    uasort($tablesort->sortdata, 'quest_sortfunction');
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
        $url = (new moodle_url('submissions.php', ['id' => $cm->id, 'sid' => $submission->id, 'action' => 'showsubmission',
                                                    'sort' => $column, 'dir' => $columndir]))->out();

        $$column = "<a href=\"$url\">" . $string[$column] . "</a>$columnicon";
    }

    if ($ismanager) {
        $table->head = array("$title", "$firstname / $lastname", "$phase", "$dateanswer", get_string('actions', 'quest'),
                        "$calification", get_string("perceiveddifficultyLevel", 'quest'));
        $table->headspan = array(1, 2, 1, 1, 1);
    } else {
        $table->head = array("$title", "$phase", "$dateanswer", get_string('actions', 'quest'), "$calification");
    }

    echo '<tr><td>';
    echo html_writer::table($table);
    echo '</td></tr>';
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $answer
 * @param \stdClass $submission
 * @return string
 */
function quest_print_answer_title($quest, $answer, $submission) {
    // Arguments are objects..
    $cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course, null, MUST_EXIST);

    if (!$answer->date) { // A "no submission".....
        return $submission->title;
    }
    $url = (new moodle_url('answer.php', ['id' => $cm->id, 'sid' => $submission->id, 'action' => 'showanswer',
                                        'aid' => $answer->id]))->out();
    return "<a name=\"sid_$answer->id\" href=\"$url\">$answer->title</a>";
}
/**
 *
 * @param \stdClass $cm
 * @param \stdClass $answer
 * @param \stdClass $submission
 * @param \stdClass $course
 * @param \stdClass $assessment
 * @return string
 */
function quest_print_actions_answers($cm, $answer, $submission, $course, $assessment) {
    global $USER;
    // Returns the teacher or peer grade and a hyperlinked list of grades for this submission..
    $str = '';

    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if (!$ismanager && ($answer->userid == $USER->id)) {
        if (($answer->phase == 1) || ($answer->phase == 2)) {
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"viewassessment.php?asid=$assessment->id\">" . get_string(
                    'seevaluate', 'quest') . "</a>";
        } else {
            $url = (new moodle_url('answer.php', ['sid' => $submission->id, 'action' => 'showanswer',
                                                  'aid' => $answer->id]))->out();
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$url\">" . get_string(
                    'see', 'quest') . "</a>";
        }
    } else if ($ismanager) {
        $assessurl = new moodle_url("/mod/quest/assess.php",
                array('id' => $cm->id, 'sid' => $submission->id, 'aid' => $answer->id, 'sesskey' => sesskey()));
        if (($answer->phase == 1) || ($answer->phase == 2)) {

            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$assessurl\">" . get_string('reevaluate', 'quest') . "</a>";

            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" " .
                    "href=\"viewassessment.php?sid=$submission->id&amp;asid=$assessment->id&amp;aid=$answer->id\">" . get_string(
                    'seevaluate', 'quest') . "</a>";
        } else if ($answer->phase == 0) {

            $str .= '&nbsp;&nbsp;<a href="' . $assessurl . '">' . get_string('evaluate', 'quest') . '</a>';
        } else {
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" " .
                    "href=\"answer.php?aid=$answer->id&amp;action=showanswer&amp;sid=$submission->id\">" . get_string(
                    'see', 'quest') . "</a>";
        }
    } else if ($submission->userid == $USER->id) {

        if ((($answer->phase == 1) || ($answer->phase == 2)) && ($assessment->state == 1)) {
            $assessurl = new moodle_url("/mod/quest/assess.php",
                    array('id' => $cm->id, 'sid' => $submission->id, 'aid' => $answer->id, 'sesskey' => sesskey()));
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$assessurl\">" . get_string('reevaluate', 'quest') . "</a>";
            $viewurl = new moodle_url("/mod/quest/viewassessment.php",
                    array('sid' => $submission->id, 'asid' => $assessment->id, 'aid' => $answer->id, 'sesskey' => sesskey()));
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$viewurl" . get_string('seevaluate', 'quest') . "</a>";
        } else if ((($answer->phase == 1) || ($answer->phase == 2)) && ($assessment->state == 2)) {
            $viewurl = new moodle_url("/mod/quest/viewassessment.php",
                    array('sid' => $submission->id, 'asid' => $assessment->id, 'aid' => $answer->id, 'sesskey' => sesskey()));
            $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$viewurl\">" . get_string('seevaluate', 'quest') . "</a>";
        } else if ($answer->phase == 0) {
            $assessurl = new moodle_url("/mod/quest/assess.php",
                    array('id' => $cm->id, 'sid' => $submission->id, 'aid' => $answer->id, 'sesskey' => sesskey(),
                                    'action' => 'evaluate'));
            $assessmsg = get_string('evaluate', 'quest');
            $str .= "&nbsp;&nbsp;<a href=\"$assessurl\">$assessmsg</a>";
        } else {
            $answerurl = new moodle_url("/mod/quest/answer.php",
                    array('sid' => $submission->id, 'aid' => $answer->id, 'sesskey' => sesskey()));
            $str .= "&nbsp;&nbsp;<a name=\"$answerurl\">" . get_string('see', 'quest') . "</a>";
        }
    } else {
        $answerurl = new moodle_url("/mod/quest/answer.php",
                array('sid' => $submission->id, 'aid' => $answer->id, 'action' => 'showanswer', 'sesskey' => sesskey()));
        $str .= "&nbsp;&nbsp;<a name=\"sid_$answer->id\" href=\"$answerurl\">" . get_string('see', 'quest') . "</a>";
    }
    if ((($ismanager) || ($submission->userid == $USER->id)) && (($answer->phase == 1) || ($answer->phase == 2)) &&
             ($answer->permitsubmit == 0)) {
        $answerurl = new moodle_url("/mod/quest/answer.php",
                array('sid' => $submission->id, 'aid' => $answer->id, 'action' => 'permitsubmit', 'sesskey' => sesskey()));
        $str .= "&nbsp;&nbsp;<a href=\"$answerurl\">" . get_string('permitsubmit', 'quest') . "</a>";
    }
    return $str;
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $answer
 */
function quest_print_answer_info($quest, $answer) {
    global $CFG, $DB, $OUTPUT;
    if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
        print_error("course_misconfigured", 'quest');
    }
    $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
    // Print standard assignment heading..
    echo $OUTPUT->box_start("center");
    // Print phase and date info..
    $string = '<b>' . get_string('currentphaseanswer', 'quest') . '</b>: ' . quest_answer_phase($answer, $course) . '<br />';
    $dates = array('dateanswer' => $answer->date, 'dateassess' => 0);
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

/** answer->phase
 * 0 1 2
 * assessment->state 1 ungraded graded autor graded >0.5 autor
 * 2 graded teacher graded >0.5 teacher
 *
 * answer->phase
 * 0 1
 * answer->state 1
 * 2 modified */
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
                $string .= " " . get_string('modified', 'quest');
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
 * @global type $CFG
 * @global stdClass $USER
 * @global type $OUTPUT
 * @param \stdClass $quest
 * @param \stdClass $answer
 */
function quest_print_answer($quest, $answer) {
    // ...prints the answer with optional attachments.
    global $CFG, $USER, $OUTPUT;

    $cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course, null, MUST_EXIST);

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
 * @param stdClass $quest record
 * @param int $sid submissionid
 * @param stdClass $assessment
 * @param boolean $allowchanges
 * @param boolean $showcommentlinks
 * @param string $returnto */
function quest_print_assessment($quest, $sid, $assessment, $allowchanges = false, $showcommentlinks = false, $returnto = '') {
    global $CFG, $USER, $questscales, $questeweights, $DB, $OUTPUT;
    $course = $DB->get_record("course", array("id" => $quest->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if ($assessment) {

        $answer = $DB->get_record("quest_answers", array("id" => $assessment->answerid), '*', MUST_EXIST);
        $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);

        $url = (new moodle_url('answer.php', ['id' => $cm->id, 'sid' => $submission->id, 'action' => 'showanswer',
                        'aid' => $answer->id]))->out();
        echo $OUTPUT->heading(
                get_string('assessmentof', 'quest', "<a href=\"$url\" target=\"submission\">$answer->title</a>"));
    }

    $timenow = time();

    // ...reset the internal flags.
    if ($assessment) {
        $showgrades = true;
    } else { // ...if no assessment, i.e. specimen grade form always show grading scales.
        $showgrades = true;
    }

    echo "<center>\n";

    if (!isset($answer)) {
        $answer = new stdClass();
        $answer->id = -1;
    }
    // ...now print the grading form with the grading grade if any.
    // FORM is needed for Mozilla browsers, else radio bttons are not checked..
    $sesskey = sesskey();
    $formfragment = <<<FORM
<form name="assessmentform" method="post" action="assessments.php">
	<input type="hidden" name="id" value="$cm->id" /> <input
		type="hidden" name="aid" value="$answer->id" /> <input
		type="hidden" name="sid" value="$sid" /> <input
		type="hidden" name="sesskey" value="$sesskey" /> <input
		type="hidden" name="action" value="updateassessment" /> <input
		type="hidden" name="returnto" value="$returnto" /> <input
		type="hidden" name="elementno" value="" /> <input type="hidden"
		name="stockcommentid" value="" />
FORM;
    echo $formfragment;
    echo '<center> <table cellpadding="2" border="1">';

    echo "<tr valign=\"top\">\n";
    echo "  <td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
    if ($assessment) {
        if ((isset($assessment->teacherid) && $assessment->teacherid != 0)) {
            $user = $DB->get_record('user', array('id' => $assessment->teacherid));
            print_string("assessmentby", "quest", quest_fullname($user->id, $course->id));
        } else if (isset($assessment->userid) && $assessment->userid != 0 && $ismanager) {
            $user = get_complete_user_data('id', $assessment->userid);
            print_string("assessmentby", "quest", quest_fullname($user->id, $course->id));
        } else if (isset($assessment->userid) && ($assessment->userid != 0) && ($assessment->userid == $USER->id) && !$ismanager) {
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

    // ...get the assignment elements....
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
        if (count($elementsraw) < $nelements && $quest->gradingstrategyautor) {
            echo $OUTPUT->notification("noteonassessmentelements", "quest");
        }
    }
    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            if ($element->questid == $quest->id) {
                $elements[] = $element; // To renumber index.
            }
        }
    } else {
        $elements = null;
    }

    if ($assessment) {
        // ...get any previous grades....
        if ($gradesraw = $DB->get_records_select("quest_elements_assessments", "assessmentid = ?", array($assessment->id),
                "elementno")) {
            foreach ($gradesraw as $grade) {
                $grades[] = $grade; // ...to renumber index.
            }
        }
    } else {
        // ...setup dummy grades array.
        for ($i = 0; $i < count($elementsraw); $i++) { // ...gives a suitable sized loop.
            $grades[$i] = new stdClass();
            $grades[$i]->answer = get_string("yourfeedbackgoeshere", "quest");
            $grades[$i]->calification = 0;
        }
    }
    if ($allowchanges == false) {
        $enabled = "disabled=\"true\"";
    } else {
        $enabled = "";
    }
    // ...determine what sort of grading.
    switch ($quest->gradingstrategy) {
        case 0: // ...no grading.
                // ...now print the form.
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
                    echo "<textarea name=\"feedback[$i]\" rows=\"3\" cols=\"75\" >\n";
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

        case 1: // ...accumulative grading.
                // ...now print the form.
            for ($i = 0; $i < count($elements); $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                echo "  <td>" . format_text($elements[$i]->description);
                echo "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                         number_format($questeweights[$elements[$i]->weight], 2) . "</font></p>\n";
                echo "</td></tr>\n";
                if ($showgrades) {
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><p><b>" . get_string("grade") . ":</b></p></td>\n";
                    echo "  <td valign=\"top\">\n";

                    // ...get the appropriate scale.
                    $scalenumber = $elements[$i]->scale;
                    $scale = (object) $questscales[$scalenumber];
                    switch ($scale->type) {
                        case 'radio':
                            // ...show selections highest first.
                            echo "<center><b>$scale->start</b>&nbsp;&nbsp;&nbsp;";
                            for ($j = $scale->size - 1; $j >= 0; $j--) {
                                $checked = false;
                                if (isset($grades[$i]->calification)) {
                                    if ($j == $grades[$i]->calification) {
                                        $checked = true;
                                    }
                                } else { // ...there's no previous grade so check the lowest option.
                                    if ($j == 0) {
                                        $checked = true;
                                    }
                                }
                                if ($checked) {
                                    echo " <input type=\"radio\" $enabled name=\"grade[$i]\" value=\"$j\" " .
                                        "checked=\"checked\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                } else {
                                    echo " <input type=\"radio\" $enabled name=\"grade[$i]\" value=\"$j\" alt=\"$j\" /> " .
                                    "&nbsp;&nbsp;&nbsp;\n";
                                }
                            }
                            echo "&nbsp;&nbsp;&nbsp;<b>$scale->end</b></center>\n";
                            break;
                        case 'selection':
                            unset($numbers);
                            for ($j = 0; $j <= $scale->size; $j++) {
                                $numbers[$j] = $j;
                            }
                            if (isset($grades[$i]->calification)) {
                                $selected = $grades[$i]->calification;
                            } else {
                                $selected = '';
                            }

                            // Choose_from_menu: $numbers, "grade[$i]".
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

                    echo "<textarea name=\"feedback[$i]\" rows=\"3\" cols=\"75\" >\n";
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
        default:
            throw new InvalidArgumentException('Unknown grading strategy.');

    } // ...end of outer switch.
      // ...now get the general comment (present in all types).
    echo "<tr valign=\"top\">\n";
    switch ($quest->gradingstrategy) {
        case 0:
        case 1:
        case 4: // ...no grading, accumulative and rubic.
            echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . ":</b>" .
            $OUTPUT->help_icon('generalcomment', 'quest') . "</p></td>\n";
            break;
        default:
            echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . "/<br />" .
                    $OUTPUT->help_icon('generalcomment', 'quest') . get_string("reasonforadjustment", "quest") . ":</b></p></td>\n";
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
            echo "  <td align=\"right\"><p><b>" . get_string("commentsauthor", "quest") . ":</b></p></td>\n";
            echo "  <td>\n";
            echo format_text($assessment->commentsforteacher);
            echo "&nbsp;</td>\n";
            echo "</tr>\n";
        }
    }

    $timenow = time();
    // ...now show the grading grade if available....
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
                $perct = $assessment->pointsmax == 0 ? 0 : $assessment->pointsautor / $assessment->pointsmax;
                echo number_format($perct, 1) . '% (';
                echo number_format($assessment->pointsautor, 4);
                echo ' ' . get_string('of', 'quest') . ' ' . number_format($answer->pointsmax, 4) . ') ';
                echo "&nbsp;</td>\n";
                echo "</tr>\n";
            }
            echo "<tr valign=\"top\">\n";
            echo "  <td align=\"right\"><p><b>";
            print_string('grade', 'quest');
            echo ":</b></p></td><td>\n";

            echo number_format($answer->grade, 1) . '% (';
            echo number_format($assessment->pointsteacher, 4);

            echo ' ' . get_string('of', 'quest') . ' ' . number_format($answer->pointsmax, 4) . ') ';
        }
        if ($assessment->state == 1) {
            echo "<tr valign=\"top\">\n";
            echo "  <td align=\"right\"><p><b>";
            print_string('grade', 'quest');
            echo ":</b></p></td><td>\n";

            echo number_format($assessment->pointsautor, 4);
            if ($answer->pointsmax == 0) {
                echo ' ' . get_string('phase4submission', 'quest') . ')';
            } else {
                echo ' ' . get_string('of', 'quest') . ' (' . number_format($answer->pointsmax, 4) . ')';
            }
        }
        echo "&nbsp;</td>\n";
        echo "</tr>\n";
    }

    /*
     * Manual Grading Form
     */
    if ($allowchanges == true) {
        echo "<tr valign=\"top\">\n";
        echo "<td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
        echo get_string('changemanualcalification', 'quest') . '</b></center></td></tr>';
        echo "<tr valign=\"top\">";
        echo "<td align=\"right\"><p><b>" . get_string('newcalification', 'quest') . ": </b></p></td>\n";
        echo "<td><input size=\"3\" maxlength=\"3\" name=\"manualcalification\" type=\"text\">%</td></tr>";
    }

    // ...and close the table, show submit button if needed....
    echo "</table>\n";
    if ($assessment) {
        if ($allowchanges) {
            echo "<input type=\"submit\" value=\"" . get_string("savemyassessment", "quest") . "\" />\n";
        }
    }

    echo "</center>";
    echo "</form>\n";
}

/** */
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

/** Calculate a percentual grade for an answer. */
function quest_get_answer_grade($quest, $answer, $grades, $feedbacks) {
    global $questeweights, $DB;
    $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);
    $assessment = $DB->get_record("quest_assessments", array("answerid" => $answer->id), '*', MUST_EXIST);
    // Has personalized elements?
    if ($DB->count_records("quest_elements", array("submissionsid" => $submission->id, "questid" => $quest->id)) == 0) {
        $customizedsid = 0;
    } else {
        $customizedsid = $submission->id;
    }

    // ...first get the assignment elements for maxscores and weights....
    // Can be $customizedsid==0 (general elements) o any $sid (personalized elements).
    $select = "submissionsid=? AND questid=?";
    $params = array($customizedsid, $quest->id);
    $elementsraw = $DB->get_records_select("quest_elements", $select, $params, "elementno ASC");
    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            $elements[] = $element; // ...to renumber index.
        }
    } else {
        $elements = [];
    }
    $num = count($elements);
    $percent = 0;
    // ...don't fiddle about, delete all the old and add the new!.
    $DB->delete_records("quest_elements_assessments", array("assessmentid" => $assessment->id));

    switch ($quest->gradingstrategy) {
        case 0: // ...no grading.
                // Insert all the elements that contain something.
            for ($i = 0; $i < $num; $i++) {
                if (!isset($feedbacks[$i])) {
                    continue;
                }

                $element = new stdClass();
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $i;
                $element->answer = $feedbacks[$i];
                $element->commentteacher = '';

                if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                    print_error('inserterror', 'quest', null, "quest_elements_assessments");
                }
            }
            $percent = 0;
            break;

        case 1: // ...accumulative grading.
                // Insert all the elements that contain something.
            foreach ($grades as $key => $thegrade) {
                $element = new stdclass();
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $key;
                $element->answer = $feedbacks[$key];
                $element->calification = $thegrade;
                $element->commentteacher = '';

                if (!$element->id = $DB->insert_record("quest_elements_assessments", $element)) {
                    print_error('inserterror', 'quest', null, "quest_elements_assessments");
                }
            }
            // ...now work out the grade....
            $rawgrade = 0;
            $totalweight = 0;
            foreach ($grades as $key => $grade) {
                $elem  = $elements[$key];
                $maxscore = $elem->maxscore;
                $weight = $questeweights[$elem->weight];
                if ($weight > 0) {
                    $totalweight += $weight;
                }

                $rawgrade += ($grade / $maxscore) * $weight;
            }

            // Process grade into quest assesment.
            $percent = ($rawgrade / $totalweight);
            break;
        default:
            throw new InvalidArgumentException('Unknown grading strategy.');
    } // ...end of switch.
    return $percent;
}
/**
 *
 * @param \stdClass $submission
 * @param \stdClass $quest
 * @param string $answer
 * @return number
 */
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
    $pointsmin = $submission->pointsmin;

    $tinitial = $quest->tinitial * 86400;
    $type = $quest->typecalification;
    $nmaxanswers = $quest->nmaxanswers;
    $pointsnmaxanswers = $submission->points;
    $state = $submission->state;

    if ($state < 2) {
        $grade = $initialpoints;
    } else {
        $grade = quest_calculate_points($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect,
                $initialpoints, $pointsmax, $pointsmin, $type);
    }

    return $grade;
}
/**
 *
 * @param integer $timenow
 * @param integer $datestart
 * @param integer $dateend
 * @param integer $tinitial
 * @param integer $dateanswercorrect
 * @param number $initialpoints
 * @param number $pointsmax
 * @param number $pointsmin
 * @param integer $type
 * @return number
 */
function quest_calculate_points($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints,
                                $pointsmax, $pointsmin=0, $type = 0) {
    if (!$dateanswercorrect) {
        $dateanswercorrect = PHP_INT_MAX; // This regularize comparisons.
    }
    if ($dateanswercorrect < $datestart) {
        $dateanswercorrect = $datestart;
    }
    // Determine scoring zone.
    if ($timenow >= $dateend) {
        $zone = 'ended';
    } else if ($timenow > $dateanswercorrect) {
        $zone = 'deflaction';
    } else if ($timenow < ($datestart + $tinitial)) {
        $zone = 'stationary';
    } else if ($timenow >= ($datestart + $tinitial)) {
        $zone = 'inflaction';
    } else {
        print_error('error');
    }

    switch ($zone) {
        case 'stationary': // Stationary score.
            $points = $initialpoints;
            break;
        case 'ended':
            if ($dateanswercorrect <= $dateend) {
                $points = $pointsmin;
            } else {
                $points = $pointsmax;
            }
            break;
        case 'inflaction': // Inflactionary zone.
            $dt = $timenow - ($datestart + $tinitial);
            $points = $dt * ($pointsmax - $initialpoints) / ($dateend - $datestart - $tinitial) + $initialpoints;
            break;
        case 'deflaction': // Deflactionary score.
            $pointscorrect = quest_calculate_points($dateanswercorrect, $datestart, $dateend, $tinitial, $dateanswercorrect,
                                                    $initialpoints, $pointsmax, $pointsmin);
            $incline2 = ($pointscorrect - $pointsmin) / ($dateend - $dateanswercorrect);
            $points = $pointscorrect - $incline2 * ($timenow - $dateanswercorrect);
            break;
    }

    if ($points < $pointsmin) {
        $points = $pointsmin;
    }
    return $points;
}
/**
 *
 * @param \stdClass $quest
 * @param string $assessment
 * @param string $allowchanges
 * @param string $showcommentlinks
 * @param string $returnto
 */
function quest_print_assessment_autor($quest, $assessment = false, $allowchanges = false,
                                      $showcommentlinks = false, $returnto = '') {
    global $CFG, $USER, $questscales, $questeweights, $DB, $OUTPUT;

    $course = $DB->get_record("course", array("id" => $quest->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if ($assessment) {

        $submission = $DB->get_record("quest_submissions", array("id" => $assessment->submissionid), '*', MUST_EXIST);
        echo $OUTPUT->heading(
                get_string('assessmentof', 'quest',
                        "<a href=\"submissions.php?id=$cm->id&amp;action=showsubmission&amp;sid=$submission->id\" " .
                        "target=\"submission\">$submission->title</a>"));
    }

    $timenow = time();

    // ...reset the internal flags.
    if ($assessment) {
        $showgrades = true;
    } else { // ...if no assessment, i.e. specimen grade form always show grading scales.
        $showgrades = true;
    }

    if ($assessment) {
        // ...set the internal flag if necessary.
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
    // ...get the assignment elements....
    $elementsraw = $DB->get_records("quest_elementsautor", array("questid" => $quest->id), "elementno ASC");
    if (count($elementsraw) < $quest->nelementsautor && $quest->gradingstrategyautor) {
        echo $OUTPUT->notification("noteonassessmentelements", "quest");
    }
    $numelements = min(count($elementsraw), $quest->nelementsautor);
    // Now print the grading form with the grading grade if any.
    $formfragment = <<<FORM
        <form name="assessmentform" method="post"
		action="assessments_autors.php">
		<input type="hidden" name="id" value="$cm->id" /> <input
			type="hidden" name="aid" value="$assessment->id" /> <input
			type="hidden" name="action" value="updateassessment" /> <input
			type="hidden" name="returnto" value="$returnto" /> <input
			type="hidden" name="elementno" value="$numelements" /> <input type="hidden"
			name="stockcommentid" value="" />
		<center>
			<table cellpadding="2" border="1">
FORM;
    echo $formfragment;
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

    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            $elements[] = $element; // ...to renumber index.
        }
    } else {
        $elements = null;
    }
    $grades = array();
    if ($assessment) {
        // ...get any previous grades....
        if ($gradesraw = $DB->get_records("quest_items_assesments_autor",
                array("assessmentautorid" => $assessment->id), "elementno")) {
            foreach ($gradesraw as $grade) {
                $grades[] = $grade; // ...to renumber index.
            }
        }
    }

    if (empty($grades)) {
        // ...setup dummy grades array.
        for ($i = 0; $i < $numelements; $i++) { // ...gives a suitable sized loop.
            $grades[$i] = new stdClass();
            $grades[$i]->answer = '';
            get_string("yourfeedbackgoeshere", "quest");
            $grades[$i]->calification = 0;
        }
    }
    // ...determine what sort of grading.
    switch ($quest->gradingstrategyautor) {
        case 0: // ...no grading.
                // ...now print the form.
            for ($i = 0; $i < $numelements; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                echo "  <td>" . format_text($elements[$i]->description);
                echo "</td></tr>\n";
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("feedback") .
                $OUTPUT->help_icon('feedback', 'quest') . ":</b></p></td>\n";
                echo "  <td>\n";
                if ($allowchanges) {
                    echo "      <textarea name=\"feedback[$i]\" rows=\"3\" cols=\"75\" >\n";
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

        case 1: // ...accumulative grading.
                // ...now print the form.
            for ($i = 0; $i < $numelements; $i++) {
                $iplus1 = $i + 1;
                echo "<tr valign=\"top\">\n";
                echo "  <td align=\"right\"><p><b>" . get_string("element", "quest") . " $iplus1:</b></p></td>\n";
                echo "  <td>" . format_text($elements[$i]->description);
                echo "<p align=\"right\"><font size=\"1\">" . get_string("weight", "quest") . ": " .
                         number_format($questeweights[$elements[$i]->weight], 2) . "</font></p>\n";
                echo "</td></tr>\n";
                if ($showgrades) {
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><p><b>" . get_string("grade") .
                    ":</b></p></td>\n";
                    echo "  <td valign=\"top\">\n";

                    // ...get the appropriate scale.
                    $scalenumber = $elements[$i]->scale;
                    $scale = (object) $questscales[$scalenumber];
                    switch ($scale->type) {
                        case 'radio':
                            // ...show selections highest first.
                            echo "<center><b>$scale->start</b>&nbsp;&nbsp;&nbsp;";
                            for ($j = $scale->size - 1; $j >= 0; $j--) {
                                $checked = false;
                                if (isset($grades[$i]->calification)) {
                                    if ($j == $grades[$i]->calification) {
                                        $checked = true;
                                    }
                                } else { // ...there's no previous grade so check the lowest option.
                                    if ($j == 0) {
                                        $checked = true;
                                    }
                                }
                                if ($checked) {
                                    echo "<input type=\"radio\" name=\"grade[$i]\" value=\"$j\" " .
                                        "checked=\"checked\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                } else {
                                    echo "<input type=\"radio\" name=\"grade[$i]\" value=\"$j\" alt=\"$j\" /> &nbsp;&nbsp;&nbsp;\n";
                                }
                            }
                            echo "&nbsp;&nbsp;&nbsp;<b>$scale->end</b></center>\n";
                            break;
                        case 'selection':
                            unset($numbers);
                            for ($j = $scale->size; $j >= 0; $j--) {
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
                echo "  <td align=\"right\"><p><b>" . get_string("feedback") .
                        $OUTPUT->help_icon('feedback', 'quest') .":</b></p></td>\n";
                echo "  <td>\n";
                if ($allowchanges) {
                    echo "      <textarea name=\"feedback[$i]\" rows=\"3\" cols=\"75\" >\n";
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
        default:
            throw new InvalidArgumentException('Unknown grading strategy.');
    } // ...end of outer switch.
      // ...now get the general comment (present in all types).
    echo "<tr valign=\"top\">\n";
    switch ($quest->gradingstrategy) {
        case 0:
        case 1:
        case 4: // ...no grading, accumulative and rubic.
            echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") . ":</b>" .
                    $OUTPUT->help_icon('generalcomment', 'quest') . "</p></td>\n";
            break;
        default:
            echo "  <td align=\"right\"><p><b>" . get_string("generalcomment", "quest") .
                    $OUTPUT->help_icon('generalcomment', 'quest') . "/<br />" .
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
    // ...now show the grading grade if available....
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
            echo ' ' . get_string('of', 'quest') . ' ' . get_string('initialpoints', 'quest') . ' ' . number_format(
                    $submission->initialpoints, 2);
        }
        echo "&nbsp;</td>\n";
        echo "</tr>\n";
    }

    /*
     * Manual Grading Form
     */
    if ($allowchanges == true) {
        echo "<tr valign=\"top\">\n";
        echo "<td colspan=\"2\" class=\"workshopassessmentheading\"><center><b>";
        echo get_string('changemanualcalification', 'quest') . '</b></center></td></tr>';
        echo "<tr valign=\"top\">";
        echo "<td align=\"right\"><p><b>" . get_string('newcalification', 'quest') . ": </b></p></td>\n";
        echo "<td><input size=\"3\" maxlength=\"3\" name=\"manualcalification\" type=\"text\">%</td></tr>";
    }

    // ...and close the table, show submit button if needed....
    echo "</table>\n";
    if ($assessment) {
        if ($allowchanges) {
            echo "<input type=\"submit\" value=\"" . get_string("savemyassessment", "quest") . "\" />\n";
        }
    }
    echo "</center>";
    echo "</form>\n";
}

/** Sort callback
 * @param array $a
 * @param array $b
 * @return boolean */
function quest_sortfunction_calification($a, $b) {
    $sort = 'calification';
    $dir = 'DESC';
    if ($dir == 'ASC') {
        return ($a[$sort] > $b[$sort]);
    } else {
        return ($a[$sort] < $b[$sort]);
    }
}

/** Insert scoring graph */
function quest_print_score_graph($quest, $submission) {
    global $CFG;
    global $DB;
    $datefirstanswer = $DB->get_field("quest_answers", "min(date)", array("submissionid" => $submission->id));
    $tinit = $quest->tinitial * 86400; // Days to seconds.
    $imgurl = new moodle_url('/mod/quest/graph_submission.php',
            ['dfirstansw' => $datefirstanswer, 'tinit' => $tinit, 'dst' => $submission->datestart, 'dend' => $submission->dateend,
             'ipoints' => $submission->initialpoints, 'daswcorr' => $submission->dateanswercorrect,
             'pointsmax' => $submission->pointsmax, 'pointsmin' => $submission->pointsmin
            ]);
    echo "<center><img src = '" . $imgurl->out() . "'></center>";
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $course
 * @param integer $currentgroup
 * @param string $actionclasification
 */
function quest_print_simple_calification($quest, $course, $currentgroup, $actionclasification) {
    global $CFG, $USER, $DB, $OUTPUT;
    $groupmode = $currentgroup = false; // JPC group support desactivation.
    $USER->showclasifindividual = $actionclasification;
    $context = context_course::instance($course->id);

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
    } else {
        // Now prepare table with student assessments and submissions.
        $tablesort = new stdClass();
        $tablesort->data = array();
        $tablesort->sortdata = array();
        $calificationusers = array();
        $calificationteams = array();
        $indice = 0;

        if (!$quest->showclasifindividual) {
            $actionclasification = 'teams';
        }

        if ($actionclasification == 'global') {

            if ($califications = quest_get_calification($quest)) {
                foreach ($califications as $calification) {
                    // ...skip if student not in group.
                    if ($currentgroup) {
                        if (!groups_is_member($currentgroup, $calification->userid)) {
                            continue;
                        }
                    }
                    $calificationusers[] = $calification;
                    $indice++;
                }
            }
            foreach ($calificationusers as $calificationuser) {
                // ...check if he is enrolled.
                if (!is_enrolled($context, $calificationuser->userid)) {
                    continue; // ...skip him.
                }

                $data = array();
                $sortdata = array();
                $user = get_complete_user_data('id', $calificationuser->userid);
                $user->imagealt = get_string('pictureof', 'quest') . " " . fullname($user);
                $data[] = $OUTPUT->user_picture($user, array('courseid' => $course->id, 'link' => true));
                $sortdata['picture'] = 1;

                $data[] = "<b>" . fullname($user) . '</b>';
                $sortdata['user'] = strtolower(fullname($user));

                $points = $calificationuser->points;

                if ($quest->allowteams) {
                    if ($clasificationteam = $DB->get_record("quest_calification_teams",
                            array("teamid" => $calificationuser->teamid, "questid" => $quest->id))) {
                        $points = $points + $clasificationteam->points * $quest->teamporcent / 100;
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

                // ...limit table lenght.
                $count++;
                if ($count > 5) {
                    break;
                }
                $table->data[] = $tablesort->data[$key];
            }

            $table->align = array('left', 'left', 'center');
            $table->head = array(get_string('user', 'quest'), get_string('calification', 'quest'));
            $table->headspan = array(2, 1);
            $columns = array('picture', 'user', 'calification');
            $table->width = "95%";

            $sort = '';
        } else if ($actionclasification == 'teams') {

            $teamstemp = array();

            if ($teams = $DB->get_records_select("quest_teams", "questid = ?", array($quest->id))) {
                foreach ($teams as $team) {
                    foreach ($users as $user) {
                        // ...skip if student not in group..
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

            if ($clasificationteams = quest_get_calification_teams($quest)) {
                foreach ($clasificationteams as $clasificationteam) {
                    foreach ($teams as $team) {
                        if ($clasificationteam->teamid == $team->id) {
                            $calificationteams[] = $clasificationteam;
                            $indice++;
                        }
                    }
                }
            }

            for ($i = 0; $i < $indice; $i++) {

                $data = array();
                $sortdata = array();

                foreach ($teams as $team) {
                    if ($calificationteams[$i]->teamid == $team->id) {
                        $data[] = $team->name;
                        $sortdata['team'] = strtolower($team->name);
                    }
                }

                $points = $calificationteams[$i]->points;
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
                // ...limit table lenght.
                if ($count > 5) {
                    break;
                }
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

/**
 * @param array $a
 * @param array $b
 * @return boolean */
function quest_sortfunction($a, $b) {
    global $sort, $dir;

    if ($dir == 'ASC') {
        return ($a[$sort] > $b[$sort]);
    } else {

        return ($a[$sort] < $b[$sort]);
    }
}

/**
 * @global stdClass $USER
 * @global stdClass $DB
 * @global type $OUTPUT
 * @param \stdClass $course
 * @param \stdClass $submission
 * @param \stdClass $quest
 * @param \stdClass $cm
 * @param array $options array with booleans for recalification, and in the future other options
 * @return string */
function quest_actions_submission($course, $submission, $quest, $cm, $options = null) {
    global $USER, $DB, $OUTPUT;

    $string = '';

    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    $canapprove = has_capability('mod/quest:approvechallenge', $context);
    if (($canapprove) && ($submission->state == SUBMISSION_STATE_APPROVAL_PENDING)) {
        $string = "<a href=\"submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=approve\">" .
                 get_string('approve', 'quest') . "</a>";
    }

    $cananswer = has_capability('mod/quest:attempt', $context);
    if ($ismanager || ($cananswer && $submission->userid != $USER->id)) {
        $answered = false;
        if ($answers = quest_get_user_answers($submission, $USER)) {
            foreach ($answers as $answer) {
                if ($answer->submissionid == $submission->id) {
                    $answered = true;
                    if ($string != '') {
                        $string .= '&nbsp;/&nbsp;';
                    }
                    $string .= get_string('answerexisty', 'quest');
                }
            }
        }
        if ($submission->state != SUBMISSION_STATE_APROVED) {
            if ($string != '') {
                $string .= '&nbsp;/&nbsp;';
            }
            $string .= get_string('phase1submission', 'quest');
        }
        if (($ismanager && !$answered) || ($answered == false && $submission->phase == SUBMISSION_PHASE_ACTIVE &&
                 $submission->state == SUBMISSION_STATE_APROVED)) {
            if ($string != '') {
                $string .= '&nbsp;/&nbsp;';
            }
            $string .= "<a href=\"answer.php?id=$cm->id&amp;uid=$USER->id&amp;action=answer&amp;sid=$submission->id\">" .
                        get_string("reply", "quest") . "</a>";
            $string .= $OUTPUT->help_icon('answersubmission', 'quest');
        }
    } else if (!$cananswer) {
        $string .= get_string('cantRespond_WARN', 'quest');
        $string .= $OUTPUT->help_icon('answersubmission', 'quest');
    } else if ($submission->userid == $USER->id) {
        $string .= get_string('authorofsubmission', 'quest');
        $string .= $OUTPUT->help_icon('answersubmission', 'quest');
    }

    if ($ismanager || ($submission->userid == $USER->id || $submission->phase != SUBMISSION_PHASE_ACTIVE)) {
        $assessmentautor = quest_get_submission_assessment($submission);
        if ($assessmentautor) {
            if ($string != '') {
                $string .= '&nbsp;/&nbsp;';
            }
            $string .= "<a href=\"viewassessmentautor.php?aid=$assessmentautor->id\">" . get_string("seeassessmentautor", "quest") .
                     "</a>";
        }
    }

    if (($ismanager)) {
        if ($string != '') {
            $string .= '&nbsp;/&nbsp;';
        }
        $assessmentautor = quest_get_submission_assessment($submission);
        if ($assessmentautor) {
            $string .= "<a href=\"assess_autors.php?id=$cm->id&amp;sid=$submission->id&amp;action=evaluate\">" .
                     get_string('reevaluate', 'quest') . "</a>";
            $string .= $OUTPUT->help_icon('assessthissubmission', 'quest');
        } else {
            $string .= "<a href=\"assess_autors.php?id=$cm->id&amp;sid=$submission->id&amp;action=evaluate\">" .
                     get_string('evaluate', 'quest') . "</a>";
        }
    }

    if ($ismanager == true && (isset($options['recalification']) && $options['recalification'] == true)) {
        if ($string != '') {
            $string .= '&nbsp;/&nbsp;';
        }
        $string .= "<a href=\"submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=recalificationall\">" .
                 get_string('recalificationall', 'quest') . "</a>";
    }
    return $string;
}

/**
 * @global stdClass $USER
 * @global stdClass $DB
 * @param stdClass $quest
 * @param stdClass $submission
 * @return boolean */
function quest_validate_user_answer(stdClass $quest, stdClass $submission) {
    global $USER, $DB;

    $validate = true;
    if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id))) {
        foreach ($answers as $answer) {
            if ($answer->userid == $USER->id) {
                $validate = false;
            }
        }
    }
    return $validate;
}

/** Update quest points and scores of the answers older than $answer_actual
 * Don't aply marking elements, just update answer->pointsmax
 * Update users and teams scores */
function quest_update_grade_for_answer($answeractual, $submission, $quest, $course) {
    global $DB;
    // ...all answers to this submission.
    $answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id));

    if ($answers) {
        foreach ($answers as $answer) {
            $points = quest_get_points($submission, $quest, $answer);
            $answer->pointsmax = number_format($points, 6);
            $DB->update_record("quest_answers", $answer);
            quest_update_user_scores($quest, $answer->userid);
            if ($teamid = quest_get_user_team($answer->questid, $answer->userid)) {
                quest_update_team_scores($answer->questid, $teamid);
            }
        }
    }
}

/** Update a submission details in the database.
 * Trucate numeric values to workaround weird database truncation errors with Moodle 2.5
 * @param \stdClass $submission */
function quest_update_submission($submission) {
    global $DB;
    $submission->points = number_format($submission->points, 4);
    $DB->update_record('quest_submissions', $submission);
}
/**
 *
 * @param \stdClass $cm
 * @param \stdClass $quest
 * @param \stdClass $challenge
 */
function quest_update_challenge_calendar($cm, $quest, $challenge) {
    global $DB;
    $dates = array('openchallenge' => $challenge->datestart, 'closechallenge' => $challenge->dateend);
    foreach ($dates as $type => $date) {
        if ($type == 'openchallenge') {
            $stringevent = 'datestartsubmissionevent';
        } else if ($type == 'closechallenge') {
            $stringevent = 'dateendsubmissionevent';
        }
        $eventdata = new stdClass();
        $eventdata->name = get_string($stringevent, 'quest', $challenge->title);
        $url = new moodle_url('/mod/quest/submissions.php',
                array('id' => $cm->id, 'sid' => $challenge->id, 'action' => 'showsubmission'));
        $eventdata->description = "<a href=\"$url\">$eventdata->name</a>";
        $eventdata->eventtype = $type;
        $eventdata->timestart = $date;
        $eventdata->modulename = 'quest';
        $eventdata->instance = $cm->instance;
        $eventdata->timeduration = 0;
        $eventdata->visible = $cm->visible;
        $eventdata->groupid = 0;
        $eventdata->userid = 0;
        $eventdata->courseid = $quest->course;
        $eventdata->uuid = 'challenge-' . $challenge->id . '-' . $type;

        $event = $DB->get_record('event',
                array('modulename' => 'quest',
                        'instance' => $eventdata->instance,
                        'eventtype' => $eventdata->eventtype,
                        'uuid' => $eventdata->uuid ));
        if ($event) { // Update event..
            $event = calendar_event::load($event->id);
            $event->update($eventdata, false);
        } else { // Create new event..
            calendar_event::create($eventdata, false);
        }
    }
}
/**
 *
 * @param \stdClass $cm
 * @param \stdClass $quest
 * @param \stdClass $challenge
 */
function quest_update_quest_calendar($quest, $cm = null) {
    global $DB;
    if ($cm === null) {
        $cm = get_coursemodule_from_instance('quest', $quest->id);
    }
    $dates = array('datestart' => $quest->datestart, 'dateend' => $quest->dateend);
    foreach ($dates as $type => $date) {

        $eventdata = new stdClass();
        $eventdata->name = get_string($type . 'event', 'quest', $quest->name);
        $url = new moodle_url('/mod/quest/view.php', array('id' => $cm->id));
        $eventdata->description = strip_pluginfile_content($quest->intro);
        $eventdata->eventtype = $type;
        $eventdata->timestart = $date;
        $eventdata->modulename = 'quest';
        $eventdata->instance = $cm->instance;
        $eventdata->timeduration = 0;
        $eventdata->visible = $cm->visible;
        $eventdata->courseid = $cm->course;
        $eventdata->uuid = 'quest-' . $quest->id . '-' . $type;

        $event = $DB->get_record('event',
                array('modulename' => $eventdata->modulename,
                        'instance' => $eventdata->instance,
                        'eventtype' => $eventdata->eventtype,
                        'uuid' => $eventdata->uuid));
        if ($event) { // Update event..
            $event = calendar_event::load($event->id);
            $event->update($eventdata, false);
        } else { // Create new event..
            calendar_event::create($eventdata, false);
        }
    }
}
/** Update a assessment details in the database.
 * Trucate numeric values to workaround weird database truncation errors and decimal points with
 * Moodle 2.5
 * @param \stdClass $submission */
function quest_update_assessment($assessment) {
    global $DB;
    $assessment->pointsautor = number_format($assessment->pointsautor, 4);
    $assessment->pointsteacher = number_format($assessment->pointsteacher, 4);
    $DB->update_record('quest_assessments', $assessment);
}

/** Update a assessment_autor details in the database.
 * Trucate numeric values to workaround weird database truncation errors and decimal points with
 * Moodle 2.5
 * @param \stdClass $submission */
function quest_update_assessment_author($assessment) {
    global $DB;
    $assessment->points = number_format($assessment->points, 4);
    $assessment->pointsmax = number_format($assessment->pointsmax, 4);
    $DB->update_record('quest_assessments_autors', $assessment);
}

/** Update a $calificationuser details in the database.
 * Trucate numeric values to workaround weird database truncation errors and decimal points with
 * Moodle 2.5
 * @param \stdClass $submission */
function quest_update_calification_user($calificationuser) {
    global $DB;
    $calificationuser->points = number_format($calificationuser->points, 4, '.', '');
    $DB->update_record('quest_calification_users', $calificationuser);
}

/** get the team for the user and quest
 * @param questid id of the quest
 * @param userid id of the user being queried for
 * @return false if failure or null data, mixed with the id otherway */
function quest_get_user_team($questid, $userid) {
    global $DB;
    $query = $DB->get_field("quest_calification_users", "teamid", array("userid" => $userid, "questid" => $questid));
    return $query;
}

/** get the members of a team
 * @param int $questid
 * @param int $teamid
 * @return array of userids: */
function quest_get_team_members($questid, $teamid) {
    global $DB;
    $query = $DB->get_records("quest_calification_users", array("teamid" => $teamid, "questid" => $questid), '', 'userid');
    return $query;
}

/** calculate user answer points from records in database
 * do not sum assessments in phase 0 or 1 (approval pending)
 * @param integer $questid integer id
 * @param integer $userid integer id o array de id
 * @return number */
function quest_calculate_user_score($questid, $userid) {
    global $CFG, $DB;
    list($insql, $inparams) = $DB->get_in_or_equal($userid);
    $params = array_merge(array($questid), $inparams);
    $sql = "select sum(ans.grade*ans.pointsmax/100) points from {quest_answers} ans, " .
            "{quest_assessments} assess WHERE " .
             "ans.questid=? AND ans.userid $insql AND ans.id=assess.answerid AND assess.phase=" .
             ASSESSMENT_PHASE_APPROVED;
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

/** calculate user submission points from records in database
 * @param integer $questid id
 * @param integer|array $userid id o array de ids
 * @return number */
function quest_calculate_user_submissions_score($questid, $userid) {
    global $DB;
    $points = 0;
    list($insql, $inparams) = $DB->get_in_or_equal($userid);
    $allparams = array_merge(array($questid), $inparams);
    if ($query = $DB->get_records_select("quest_submissions", "questid=? and userid $insql", $allparams)) {
        foreach ($query as $s) {
            $submissions[] = $s->id;
        }
        list($insql2, $inparams2) = $DB->get_in_or_equal($submissions);
        if ($query = $DB->get_record_select("quest_assessments_autors", "submissionid $insql2", $inparams2, "sum(points) as points")) { // ...evp.
                                                                                                                                        // ...funcione.
            if ($query->points) {
                $points = $query->points;
            }
        }
    }
    return $points;
}

/**
 * @param $userid array de identificadores */
function quest_count_user_submissions_assesed($questid, $userid) {
    global $DB;
    list($insql, $inparams) = $DB->get_in_or_equal($userid);
    $allparams = array_merge(array($questid), $inparams);
    if ($query = $DB->get_records_select("quest_submissions", "questid=? and userid $insql", $allparams)) {
        foreach ($query as $s) {
            $submissions[] = $s->id;
        }
        list($insql, $inparams) = $DB->get_in_or_equal($submissions);
        if ($query = $DB->get_record_select("quest_assessments_autors", "submissionid $insql and state>0", $inparams,
                "count(points) as num")) {
            return $query->num;
        } else {
            return 0;
        }
    } else {
        return 0;
    }
}

/**
 * @param $userid array de identificadores */
function quest_count_user_submissions($questid, $userid) {
    global $DB;
    list($insql, $inparams) = $DB->get_in_or_equal($userid);
    $allparams = array_merge(array($questid), $inparams);
    if ($query = $DB->get_record_select("quest_submissions", "questid=? and userid $insql", $allparams, "count(id) as num")) {
        return $query->num;
    } else {
        return 0;
    }
}

/**
 * @param $userid array de identificadores */
function quest_count_user_answers($questid, $userid) {
    global $DB;
    list($insql, $inparams) = $DB->get_in_or_equal($userid);
    $allparams = array_merge(array($questid), $inparams);
    if ($query = $DB->get_record_select("quest_answers", "questid=? and userid $insql", $allparams, "count(grade) as num")) {
        return $query->num;
    } else {
        return 0;
    }
}

/**
 * @param $userid array de identificadores */
function quest_count_user_answers_assesed($questid, $userid) {
    global $DB;
    list($insql, $inparams) = $DB->get_in_or_equal($userid);
    $allparams = array_merge(array($questid), $inparams);
    $query = $DB->get_record_select("quest_answers", "questid=? and userid $insql and phase>0", $allparams, "count(grade) as num");
    if ($query) {
        return $query->num;
    } else {
        return 0;
    }
}

/** Count submissions's assessments */
function quest_count_submission_assessments($sid) {
    global $DB;
    $answersids = $DB->get_records('quest_answers', array("submissionid" => $sid), '', "id");

    if ($answersids != false && count($answersids) > 0) {
        foreach ($answersids as $aid) {
            $aids[] = $aid->id;
        }
        $answersids = implode(",", $aids);
        list($insql, $inparams) = $DB->get_in_or_equal(array($answersids));
        return $DB->count_records_select("quest_assessments", "answerid $insql", $inparams);
    } else {
        return 0;
    }
}

/** Recalculate scores, stats and report to the gradebook for an user and his team
 * @param \stdClass $quest record
 * @param int $userid specified user */
function quest_grade_updated($quest, $userid) {
    if (!$userid) {
        return;
    }
    // Update current User scores..
    quest_update_user_scores($quest, $userid);
    $userids = array($userid);

    // Update answer current team totals..
    if ($quest->allowteams) {
        $team = quest_get_user_team($quest->id, $userid);

        if ($team) {
            quest_update_team_scores($quest->id, $team);
        }
    }

    // Report to gradebook..
    // ...as score are relative to other's we need to update all grades..
    quest_update_grades($quest, 0);
}

/** Updates $calificationuser registry
 * counting totals and pointanswers and points from the records in the database */
function quest_update_user_scores($quest, $userid) {
    global $DB;
    $questid = $quest->id;
    $calificationuser = $DB->get_record("quest_calification_users", array("questid" => $questid, "userid" => $userid), '*');

    if (!$calificationuser) {
        $calificationuser = new stdClass();
        $calificationuser->userid = $userid;
        $calificationuser->questid = $quest->id;
        $calificationuser->id = $DB->insert_record("quest_calification_users", $calificationuser);
    }

    $calificationuser->pointsanswers = quest_calculate_user_score($questid, $userid);
    $calificationuser->nanswers = quest_count_user_answers($questid, $userid);
    $calificationuser->nanswersassessment = quest_count_user_answers_assesed($questid, $userid);
    $calificationuser->pointssubmission = quest_calculate_user_submissions_score($questid, $userid);
    $calificationuser->nsubmissionsassessment = quest_count_user_submissions_assesed($questid, $userid);
    $calificationuser->nsubmissions = quest_count_user_submissions($questid, $userid);
    $calificationuser->points = $calificationuser->pointssubmission + $calificationuser->pointsanswers;
    quest_update_calification_user($calificationuser);
}

/**
 * @param stdClass|int $questid
 * @param int $teamid Updates pointanswers and points from the records in the database
 *        $calificationteam->nanswers = $nanswers;
 *        $calificationteam->nanswerassessment=$nanswersassessed;
 *        $calificationteam->points=$points;
 *        $calificationteam->pointsanswers=$pointsanswers;
 *        $calificationteam->pointssubmission=$submissionpoints;
 *        $calificationteam->nsubmissionsassessment=$nsubmissionassessed;
 *        $calificationteam->nsubmissions=$nsubmissions;
 *        updates team->ncomponents */
function quest_update_team_scores($quest, $teamid) {
    global $DB;
    if ($quest instanceof stdClass) {
        $questid = $quest->id;
    } else {
        $questid = $quest;
    }

    $query = $DB->get_records("quest_calification_users", array("teamid" => $teamid, 'questid' => $questid));
    $members = array();
    foreach ($query as $q) {
        $members[] = $q->userid;
    }
    $memberlist = implode(',', $members);
    if (empty($members)) {
        echo "Quest $questid Team $teamid  has no members.";
        return;
    }
    $pointsanswers = quest_calculate_user_score($questid, $members);

    $nanswers = quest_count_user_answers($questid, $members);
    $nanswersassessed = quest_count_user_answers_assesed($questid, $members);
    $submissionpoints = quest_calculate_user_submissions_score($questid, $members);
    $nsubmissions = quest_count_user_submissions($questid, $members);
    $nsubmissionassessed = quest_count_user_submissions_assesed($questid, $members);

    $points = $submissionpoints + $pointsanswers;
    $calificationteam = $DB->get_record("quest_calification_teams", array("questid" => $questid, "teamid" => $teamid), '*');

    if (!$calificationteam) {
        $calificationteam = new stdClass();
        $calificationteam->teamid = $teamid;
        $calificationteam->questid = $questid;
        $calificationteam->id = $DB->insert_record("quest_calification_teams", $calificationteam);
    }
    $calificationteam->nanswers = $nanswers;
    $calificationteam->nanswerassessment = $nanswersassessed;
    $calificationteam->points = '' . $points;
    $calificationteam->pointsanswers = "$pointsanswers";
    $calificationteam->pointssubmission = "$submissionpoints";
    $calificationteam->nsubmissionsassessment = $nsubmissionassessed;
    $calificationteam->nsubmissions = $nsubmissions;
    $DB->update_record('quest_calification_teams', $calificationteam);

    $DB->set_field("quest_teams", "ncomponents", count($members), array("questid" => $questid, "id" => $teamid));
}
function quest_print_error_banded_form($quest, $num, $elements, $questeweights) {
    for ($i = 0; $i < $num; $i++) {
        $iplus1 = $i + 1;
        echo "<tr valign=\"top\">\n";
        echo "  <td align=\"right\"><b>" . get_string("element", "quest") . " $iplus1:</b></td>\n";
        echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">" .
        $elements[$i]->description . "</textarea>\n";
        echo "  </td></tr>\n";
        if ($elements[$i]->weight == '') { // ...not set..
            $elements[$i]->weight = 11; // ...unity..
        }
        echo "</tr>\n";
        echo "<tr valign=\"top\"><td align=\"right\"><b>" . get_string("elementweight", "quest") . ":</b></td><td>\n";
        quest_choose_from_menu($questeweights, "weight[]", $elements[$i]->weight, "");
        echo "      </td>\n";
        echo "</tr>\n";
        echo "<tr valign=\"top\">\n";
        echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
        echo "</tr>\n";
    }
    echo "</center></table><br />\n";
    echo "<center><b>" . get_string("gradetable", "quest") . "</b></center>\n";
    echo "<center><table cellpadding=\"5\" border=\"1\"><tr><td align=\"CENTER\">" .
            get_string("numberofnegativeresponses", "quest");
    echo "</td><td>" . get_string("suggestedgrade", "quest") . "</td></tr>\n";
    for ($j = $quest->maxcalification; $j >= 0; $j--) {
        $numbers[$j] = $j;
    }
    for ($i = 0; $i <= $num; $i++) {
        echo "<tr><td align=\"CENTER\">$i</td><td align=\"CENTER\">";
        if (!isset($elements[$i])) { // ...the "last one" will be!.
            $elements[$i]->description = "";
            $elements[$i]->maxscore = 0;
        }
        echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
        echo "</td></tr>\n";
    }
    echo "</table></center>\n";
}
/**
 *
 * @param \stdClass $answer
 * @param \stdClass $quest
 * @param \stdClass $assessment
 * @param \stdClass $course
 */
function quest_recalification($answer, $quest, $assessment, $course) {
    global $USER, $DB;

    global $questeweightsrecalif;

    $context = context_course::instance($course->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    $submission = $DB->get_record("quest_submissions", array("id" => $answer->submissionid), '*', MUST_EXIST);
    if (!has_capability('mod/quest:manage', $context, $answer->userid)) {
        $calificationuser = $DB->get_record("quest_calification_users",
                array("userid" => $answer->userid, "questid" => $quest->id), '*', MUST_EXIST);
        if ($quest->allowteams) {
            $calificationteam = $DB->get_record("quest_calification_teams",
                    array("teamid" => $calificationuser->teamid, "questid" => $quest->id), '*', MUST_EXIST);
        }
    }
    // ...first get the assignment elements for maxscores and weights....
    $elementsraw = $DB->get_records("quest_elements", array("questid" => $quest->id), "elementno ASC");
    if ($elementsraw) {
        foreach ($elementsraw as $element) {
            $elements[$element->elementno] = $element; // ...to renumber index.
        }
    } else {
        $elements = null;
    }

    $timenow = time();
    // ...don't fiddle about, delete all the old and add the new!.
    $grades = [];
    $gradesraw = new stdclass();
    $gradesraw->grade = $DB->get_records("quest_elements_assessments", array("assessmentid" => $assessment->id));
    if ($gradesraw->grade) {
        foreach ($gradesraw->grade as $graderaw) {
            $grades[$graderaw->elementno] = $graderaw->calification; // ...to renumber index.
        }
    } else {
        $grades = null;
    }

    if ($quest->validateassessment == 1) {
        if (!empty($assessment->teacherid)) {
            if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {
                if ($assessment->phase == 0) {
                    $calificationuser->nanswersassessment++;
                    if ($quest->allowteams) {
                        $calificationteam->nanswerassessment++;
                    }
                }
                $assessment->phase = 1;
            }
        } else {
            $assessment->phase = 0;
        }
    } else {
        if ($assessment->phase == 0) {
            $calificationuser->nanswersassessment++;
            if ($quest->allowteams) {
                $calificationteam->nanswerassessment++;
            }
        }
        $assessment->phase = 1;
    }

    $answer->phase = 1;

    // ...determine what kind of grading we have.
    switch ($quest->gradingstrategy) {
        case 0: // ...no grading.
                // Insert all the elements that contain something.
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

                        $calificationuser->points -= $assessment->pointsautor;
                        $calificationuser->pointsanswers -= $assessment->pointsautor;
                        $calificationuser->points += $grade;
                        $calificationuser->pointsanswers += $grade;

                        if ($quest->allowteams) {
                            $calificationteam->points -= $assessment->pointsautor;
                            $calificationteam->pointsanswers -= $assessment->pointsautor;
                            $calificationteam->points += $grade;
                            $calificationteam->pointsanswers += $grade;
                        }
                    }
                } else if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {

                    $calificationuser->points -= $assessment->pointsteacher;
                    $calificationuser->pointsanswers -= $assessment->pointsteacher;
                    $calificationuser->points += $grade;
                    $calificationuser->pointsanswers += $grade;

                    if ($quest->allowteams) {
                        $calificationteam->points -= $assessment->pointsteacher;
                        $calificationteam->pointsanswers -= $assessment->pointsteacher;
                        $calificationteam->points += $grade;
                        $calificationteam->pointsanswers += $grade;
                    }
                }
            }

            break;

        case 1: // ...accumulative grading.
                // Insert all the elements that contain something.
                // ...now work out the grade....
            $rawgrade = 0;
            $totalweight = 0;
            foreach ($grades as $key => $grade) {
                $maxscore = $elements[$key]->maxscore;
                $weight = $questeweightsrecalif[$elements[$key]->weight];
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
        default:
            throw new InvalidArgumentException('Unknown grading strategy.');
    } // ...end of switch.

    $answer->grade = 100 * ($grade / $points);

    // ...update the time of the assessment record (may be re-edited)....
    $assessment->dateassessment = $timenow;
    $firstdatecorrect = $submission->dateanswercorrect;

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
    // Recalculate points and report to gradebook..
    quest_grade_updated($quest, $submission->userid);

    $aid = $assessment->id;
    if (!empty($assessment->teacherid)) {
        if (has_capability('mod/quest:manage', $context, $assessment->teacherid)) {
            if ($user = get_complete_user_data('id', $answer->userid)) {

                quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
            }
            if ($user = get_complete_user_data('id', $assessment->userid)) {

                quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
            }
        }
    } else {
        if ($user = get_complete_user_data('id', $answer->userid)) {

            quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
        }
        if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
            global $OUTPUT;
            echo $OUTPUT->heading(get_string('nostudentsyet'));
            echo $OUTPUT->footer();
            exit();
        }
        // JPC 2013-11-28 disable excesive notifications.
        if (false) {
            foreach ($users as $user) {
                if (!has_capability('mod/quest:manage', $context, $user->id)) {
                    continue;
                }
                quest_send_message($user, "viewassessment.php?asid=$assessment->id", 'assessment', $quest, $submission, $answer);
            }
        }
    }

    $cm = get_coursemodule_from_instance('quest', $quest->id);
    mod_quest\event\answer_assessed::create_from_parts($submission, $answer, $assessment, $cm);
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $course
 * @param \stdClass $cm
 * @param string $sortteam
 * @param string $dirteam
 * @return boolean
 */
function quest_print_table_teams($quest, $course, $cm, $sortteam, $dirteam) {
    global $CFG, $USER, $DB, $OUTPUT;
    $changegroup = optional_param('group', -1, PARAM_INT);// Group change requested?.
    $groupmode = groups_get_activity_group($cm); // Groups are being used?.
    $currentgroup = groups_get_course_group($course);
    $groupmode = $currentgroup = false; // JPC group support desactivation.

    $context = context_module::instance($cm->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
        echo $OUTPUT->heading(get_string("nostudentsyet"));
        echo $OUTPUT->footer();
        exit();
    }

    // Now prepare table with student assessments and submissions.
    $tablesort = new stdclass();
    $tablesort->data = array();
    $tablesort->sortdata = array();
    $i = 0;

    foreach ($users as $user) {
        // ...skip if student not in group.
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
        if ($calificationuser = $DB->get_record("quest_calification_users",
                                array("userid" => $user->id, "questid" => $quest->id))) {

            if ($team = $DB->get_record("quest_teams", array("id" => $calificationuser->teamid))) {

                $data = array();
                $sortdata = array();

                $data[] = "<a name=\"userid$user->id\" " .
                        "href=\"{$CFG->wwwroot}/user/view.php?id=$user->id&amp;course=$course->id\">" .
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
    $firstname = '';
    $lastname = '';
    $teamname = '';
    $ncomponents = 0;
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
        $$column = "<a href=\"view.php?id=$cm->id&amp;sortteam=$column&amp;dirteam=$columndir\">" . $string[$column] .
                 "</a>$columnicon";
    }
    // Variables $firstname, $lastname, $teamname, $ncomponents are defined by $$column assignment above.
    $table->head = array("$firstname / $lastname", "$teamname", "$ncomponents");

    echo html_writer::table($table);
}
/**
 *
 * @param \stdClass $submission
 * @param \stdClass $quest
 * @param \stdClass $course
 */
function quest_recalification_all($submission, $quest, $course) {
    global $DB;
    if ($answers = $DB->get_records_select("quest_answers", "questid=? AND submissionid=?", array($quest->id, $submission->id))) {

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

/** Disable module instance if user has no permissions and module is hidden (disabled)
 *
 * @param \stdClass $course
 * @param \stdClass $cm */
function quest_check_visibility($course, $cm) {
    $context = context_course::instance($course->id);
    if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $context)) {
        print_error("modulehiddenerror", 'quest');
    }
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $course
 * @param string $userpassword
 */
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
                echo "<tr align=\"center\" style='color:#DF041E;'><td>" . get_string("wrongpassword", "quest") . "</td></tr>";
            }
            echo "<tr align=\"center\"><td>" . get_string("passwordprotectedquest", "quest", format_string($quest->name)) .
                     "</td></tr>";
            echo "<tr align=\"center\"><td>" . get_string("enterpassword", "quest") .
                     " <input type=\"password\" name=\"userpassword\" /></td></tr>";

            echo "<tr align=\"center\"><td>";
            echo "<input type=\"button\" value=\"" . get_string("cancel") .
                     "\" onclick=\"parent.location='../../course/view.php?id=$course->id';\">  ";
            echo "<input type=\"button\" value=\"" . get_string("continue") . "\" onclick=\"document.password.submit();\" />";
            echo "</td></tr></table>";
            echo $OUTPUT->box_end();
            echo $OUTPUT->footer();
            exit();
        }
    }
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $answer
 * @param string $all flag 'ALL'
 * @return number
 */
function quest_answer_grade($quest, $answer, $all) {
    return ($answer->grade * $answer->pointsmax) / 100;
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $user
 * @return array
 */
function quest_get_user_clasification($quest, $user) {
    global $DB;
    return $DB->get_records_select("quest_calification_users", "questid = ? AND userid = ? ", array($quest->id, $user->id));
}
/**
 *
 * @param \stdClass $quest
 * @return array
 */
function quest_get_calification($quest) {
    global $DB;
    return $DB->get_records_select("quest_calification_users", "questid = ?", array($quest->id), "points ASC");
}
/**
 *
 * @param \stdClass $quest
 * @return array
 */
function quest_get_calification_teams($quest) {
    global $DB;
    return $DB->get_records('quest_calification_teams', array('questid' => $quest->id), "points ASC");
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $user
 * @return array
 */
function quest_get_answers($quest, $user) {
    global $DB;
    return $DB->get_records("quest_answers", array('userid' => $user->id, 'questid' => $quest->id));
}
/**
 * Gets an unassessed answer ordered by time.
 * @param stdClass $answer
 * @return NULL | stdClass $answer next in the queue
 */
function quest_next_unassesed_answer($answer) {
    global $DB;
    $sql = <<<SQL
SELECT answer.id, assess.id assid
FROM {quest_answers} answer
LEFT JOIN {quest_assessments} assess ON (answer.id = assess.answerid)
WHERE answer.submissionid = ? and (assess.id IS NULL OR assess.phase = 0) and answer.id <> ? order by answer.date
SQL;
    $answers = $DB->get_records_sql($sql, [$answer->submissionid, $answer->id]);
    if (count($answers) == 0 ) {
        return null;
    } else {
        return reset($answers);
    }
}
/**
 * Gets an unassessed submission ordered by time.
 * @param stdClass $submission
 * @return NULL | stdClass $submission next in the queue
 */
function quest_next_unassesed_submission($submission) {
    global $DB;
    $sql = <<<SQL
SELECT submission.id, assess.id assid
FROM {quest_submissions} submission
LEFT JOIN {quest_assessments_autors} assess ON (submission.id = assess.submissionid)
WHERE submission.questid = ? and  (assess.id IS NULL OR assess.state = 0) and submission.id <> ? order by dateassessment
SQL;
    $submissions = $DB->get_records_sql($sql, [$submission->questid, $submission->id]);
    if (count($submissions) == 0 ) {
        return null;
    } else {
        return reset($submissions);
    }
}
/**
 * Gets an unapproved submission ordered by time.
 * @param stdClass $submission
 * @return NULL | stdClass $submission next in the queue
 */
function quest_next_unapproved_submission($submission) {
    global $DB;
    // APPROVED_PENDING is 0.
    $submissions = $DB->get_records('quest_submissions',
            ['questid' => $submission->questid, 'state' => SUBMISSION_STATE_APPROVAL_PENDING],
            'dateend ASC');
    if (count($submissions) == 0 ) {
        return null;
    } else {
        return reset($submissions);
    }
}
/**
 * Define the aproval/assessment workflow.
 * @param \stdClass $submission
 * @param context_module $context
 * @return moodle_url
 */
function quest_next_submission_url($submission, $cm) {
    $context = context_module::instance($cm->id);
    $nextunapproved = null;
    $nextunassessed = null;
    $submissionassessment = quest_get_submission_assessment($submission);
    // First own assesment. Then other approvals.
    if (!$submissionassessment) {
        $nextunassessed = $submission;
    } else {
        if (has_capability('mod/quest:approvechallenge', $context)) {
            $nextunapproved = quest_next_unapproved_submission($submission);
        } else {
            $nextunapproved = null;
        }
        if (has_capability('mod/quest:grade', $context) && $nextunapproved === null) {
            $nextunassessed = quest_next_unassesed_submission($submission);
        } else {
            // ... else redirect to answers list.
            $nextunassessed = null;
        }
    }
    // Apply priority of approval above assessment.
    if ($nextunapproved !== null) {
        $nexturl = new moodle_url('submissions.php',
                ['id' => $cm->id, 'sid' => $nextunapproved->id, 'action' => 'approve', 'sesskey' => sesskey()]);
    } else if ($nextunassessed !== null ) {
        $nexturl = new moodle_url('assess_autors.php',
                ['id' => $cm->id, 'sid' => $nextunassessed->id, 'action' => 'evaluate', 'sesskey' => sesskey()]);
    } else {
        $nexturl = new moodle_url('view.php', ['id' => $cm->id ]);
    }
    return $nexturl;
}
/** Get all users that act as student (i.e.
 * can 'mod/quest:attempt')
 * @param int $courseid
 * @return stdClass[] array of user records */
function quest_get_course_students($courseid) {
    $context = context_course::instance($courseid);
    $members = get_users_by_capability($context, 'mod/quest:attempt');
    return $members;
}
/**
 *
 * @param integer $id cmid of activity.
 * @return stdClass[] course, cm
 */
function quest_get_course_and_cm($id) {
    if (function_exists('get_course_and_cm_from_cmid')) {
        return get_course_and_cm_from_cmid($id, 'quest');
    } else {
        $cm = get_coursemodule_from_id('quest', $id, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        return array($course, $cm);
    }
}
/**
 *
 * @param \stdClass $quest
 * @return stdClass[]
 */
function quest_get_course_and_cm_from_quest($quest) {
    $course = get_course($quest->course);
    $cm = get_fast_modinfo($course->id)->instances["quest"][$quest->id];
    return array($course, $cm);
}

/**
 * @deprecated
 *
 * @global type $CFG
 * @global type $OUTPUT
 * @param \stdClass $course
 * @param bool $isteacher
 * @param integer $timestart
 * @return bool */
function quest_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG, $OUTPUT;

    $context = context_course::instance($course->id);
    $ismanager = has_capability('mod/quest:manage', $context);

    $submitsubmissioncontent = false;
    if ($isteacher) {
        if ($logs = quest_get_submitsubmission_logs($course, $timestart)) {
            foreach ($logs as $log) {
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod = new stdClass();
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    $submitsubmissioncontent = true;
                    break;
                }
            }
            if ($submitsubmissioncontent) {
                echo $OUTPUT->heading(get_string("questsubmitsubmission", "quest") . ":");
                foreach ($logs as $log) {
                    // Create a temp valid module structure (only need courseid, moduleid).
                    $tempmod->course = $course->id;
                    $tempmod->id = $log->questid;
                    if (!has_capability('mod/quest:manage', $context, $log->userid)) {
                        // Obtain the visible property from the instance.
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
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    $submitsubmissionusercontent = true;
                    break;
                }
            }
            if ($submitsubmissionusercontent) {
                echo $OUTPUT->heading(get_string("questsubmitsubmission", "quest"));
                foreach ($logs as $log) {
                    // Create a temp valid module structure (only need courseid, moduleid).
                    $tempmod->course = $course->id;
                    $tempmod->id = $log->questid;

                    // Obtain the visible property from the instance.
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
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    $approvesubmissioncontent = true;
                    break;
                }
            }
            if ($approvesubmissioncontent) {
                echo $OUTPUT->heading(get_string("questapprovesubmission", "quest"));
                foreach ($logs as $log) {
                    // Create a temp valid module structure (only need courseid, moduleid).
                    $tempmod->course = $course->id;
                    $tempmod->id = $log->questid;

                    // Obtain the visible property from the instance.
                    if (instance_is_visible("quest", $tempmod)) {
                        print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                $CFG->wwwroot . '/mod/quest/' . $log->url);
                    }
                }
            }
        }
    }

    // ...have a look for new assessments for this user (assess).
    $assessmentcontent = false;
    if (!$isteacher) { // ...teachers only need to see submissions.
        if ($logs = quest_get_assessments_logs($course, $timestart)) {
            // ...got some, see if any belong to a visible module.
            foreach ($logs as $log) {
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod = new stdClass();
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    $assessmentcontent = true;
                    break;
                }
            }
            // ...if we got some "live" ones then output them.
            if ($assessmentcontent) {
                echo $OUTPUT->heading(get_string("questassessments", "quest") . ":");
                foreach ($logs as $log) {
                    // Create a temp valid module structure (only need courseid, moduleid).
                    $tempmod->course = $course->id;
                    $tempmod->id = $log->questid;
                    // Obtain the visible property from the instance.
                    if (instance_is_visible("quest", $tempmod)) {
                        if (!has_capability('mod/quest:manage', $context)) { // ...don't break.
                                                                             // ...anonymous rule.
                            $log->firstname = '';
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
    if (!$isteacher) { // ...teachers only need to see submissions.
        if ($logs = quest_get_assessmentsautor_logs($course, $timestart)) {
            // ...got some, see if any belong to a visible module.
            foreach ($logs as $log) {
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    $assessmentautorcontent = true;
                    break;
                }
            }
            // ...if we got some "live" ones then output them.
            if ($assessmentautorcontent) {
                echo $OUTPUT->heading(get_string("questassessments", "quest") . ":");
                foreach ($logs as $log) {
                    // Create a temp valid module structure (only need courseid, moduleid).
                    $tempmod->course = $course->id;
                    $tempmod->id = $log->questid;
                    // Obtain the visible property from the instance.
                    if (instance_is_visible("quest", $tempmod)) {

                        print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                $CFG->wwwroot . '/mod/quest/' . $log->url);
                    }
                }
            }
        }
    }

    // ...have a look for new assessment gradings for this user (grade).
    $answercontent = false;
    if ($logs = quest_get_submitanswer_logs($course, $timestart)) {
        // ...got some, see if any belong to a visible module.
        foreach ($logs as $log) {
            // Create a temp valid module structure (only need courseid, moduleid).
            $tempmod->course = $course->id;
            $tempmod->id = $log->questid;
            // Obtain the visible property from the instance.
            if (instance_is_visible("quest", $tempmod)) {
                $answercontent = true;
                break;
            }
        }
        // ...if we got some "live" ones then output them.
        if ($answercontent) {
            echo $OUTPUT->heading(get_string("questsubmitanswer", "quest") . ":");
            foreach ($logs as $log) {
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    print_recent_activity_note($log->time, $log, $isteacher, $log->title,
                                                $CFG->wwwroot . '/mod/quest/' . $log->url);
                }
            }
        }
    }

    return $submitsubmissioncontent or $submitsubmissionusercontent or $approvesubmissioncontent or $assessmentcontent or
             $assessmentautorcontent or $answercontent;
}
/**
 *
 * @param \stdClass $user
 * @param string $file
 * @param string $msgtype challenge_start, evaluatecomment, addsubmission, save,
 *                          deletesubmission, answeradd, answerdelete, modifsubmission
 * @param \stdClass $quest
 * @param stdClass $field1 the object that caused the message (i.e. challenge record)
 * @param string $field2
 * @param string $from
 * @return mixed the integer ID of the new message or false if there was a problem with submitted data
 */
function quest_send_message($user, $file, $msgtype, $quest, $object, $field2 = '', $from = '') {
    $data = quest_compose_message_data($user, $file, $msgtype, $quest, $object, $field2, $from);
    return quest_send_message_data($data);
}

function quest_send_message_data($data) {
    // Actually send the message.
    global $CFG;
    if (version_compare($CFG->release, "3.2", '>=')) { // ...messaging for Moodle 3.2+.
        $messagedata = new \core\message\message();
    } else if (version_compare($CFG->release, "2.4", '>=')) { // Messaging for Moodle 2.4+.
        $messagedata = new stdClass();
    }
    $messagedata->component = 'mod_quest';
    $messagedata->name = $data->msgtype;
    $messagedata->userfrom = $data->userfrom;
    $messagedata->userto = $data->userto;
    $messagedata->subject = $data->subject;
    $messagedata->fullmessage = $data->messagehtml;
    $messagedata->fullmessagehtml = $data->messagehtml;
    $messagedata->smallmessage = '';
    $messagedata->fullmessageformat = FORMAT_HTML;
    $messagedata->notification = true;
    $messagedata->courseid = $data->courseid;
    $msgid = message_send($messagedata);
    return $msgid;
}
/**
 *
 * @param stdClass $user user record of the user
 * @param string $file file part of the url that represents the subject
 * @param string $msgtype
 * @param \stdClass $quest
 * @param \stdClass $object
 * @param string $field2
 * @param string $from
 * @return null|stdClass
 */
function quest_compose_message_data($user, $file, $msgtype, $quest, $object, $field2 = '', $from = '') {
    global $CFG, $SITE, $COURSE;
    if (!$user) {
        return null;
    }
    force_current_language($user->lang);
    $site = get_site();
    if (empty($from) || $from == null) {
        // If there are a "no_reply" user use him. otherwise submit from any teacher..
        $userfrom = class_exists('core_user') ? core_user::get_noreply_user() : quest_get_teacher($quest->course);
    } else {
        $userfrom = $from;
    }
    // Translate quest msgtype to Moodle messages producer type messages.php.
    $messagetype = 'challenge_update';
    switch ($msgtype) {
        case 'challenge_start':
            $messagetype = 'challenge_start';
            break;
        case 'evaluatecomment':
            $messagetype = 'evaluation_update';
            break;
        case 'addsubmission':
        case 'save':
        case 'deletesubmission':
        case 'answeradd':
            break;
        case 'answerdelete':
        case 'modifsubmission':
            $messagetype = 'challenge_update';
            break;
    }
    $data = new stdClass();
    $data->firstname = fullname($user);
    $data->sitename = $site->fullname;
    $data->admin = $CFG->supportname . ' (' . $CFG->supportemail . ')';
    $data->title = $object->title;
    $data->name = $quest->name;
    if (!empty($field2)) {
        $data->secondname = $field2->title;
    }

    $data->subject = get_string('email' . $msgtype . 'subject', 'quest');

    // Make the text version a normal link for normal people.
    $data->link = $CFG->wwwroot . "/mod/quest/$file";
    $message = get_string('email' . $msgtype, 'quest', $data);
    $data->msgtype = $messagetype; // Producer msg type.
    // Make the HTML version more XHTML happy (&amp;).
    $data->link = $CFG->wwwroot . "/mod/quest/$file";
    $data->messagehtml = text_to_html($message, false, false, true);
    $user->mailformat = 1; // Always send HTML version as well.
    $data->userto = $user;
    $data->courseid = $quest->course;
    $data->userfrom = $userfrom;
    return $data;
}
/**
 *
 * @param string $messagehtml
 * @param integer $courseid
 * @param \stdClass $userfrom
 * @param string $subject
 * @return string
 */
function quest_message_html($messagehtml, $courseid, $userfrom, $subject) {
    global $CFG;

    $outputhtml = '<head>';
    foreach ($CFG->stylesheets as $stylesheet) {
        $outputhtml .= '<link rel="stylesheet" type="text/css" href="' . $stylesheet . '" />' . "\n";
    }
    $outputhtml .= '</head>';
    $outputhtml .= "\n<body id=\"email\">\n\n";
    $strquests = get_string('quests', 'quest');
    $outputhtml .= '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

    $outputhtml .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $outputhtml .= print_user_picture($userfrom->id, $courseid, $userfrom->picture, false, true);
    $outputhtml .= '</td>';

    $outputhtml .= '<td class="topic starter">';

    $outputhtml .= '<div class="subject">' . $subject . '</div>';

    $fullname = fullname($userfrom, isteacher($courseid));
    $by = new stdClass();
    $by->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $userfrom->id . '&amp;course=' . $courseid . '">' . $fullname .
             '</a>';
    $by->date = userdate(time(), '', $userfrom->timezone);
    $outputhtml .= '<div class="author">' . get_string('bynameondate', 'forum', $by) . '</div>';

    $outputhtml .= '</td></tr>';

    $outputhtml .= '<tr><td class="left side"> </td><td class="content">';

    $messagehtml = text_to_html($messagehtml, false, false, true);
    $outputhtml .= $messagehtml;

    $outputhtml .= '</td></tr></table>';

    $outputhtml .= '</body>';

    return $outputhtml;
}

/** TODO reuse grade calculation with quest_get_maxpoints
 * @param int $groupid
 * @param \stdClass $quest
 * @return number */
function quest_get_maxpoints_group($groupid, $quest) {
    global $DB;
    $maxpoints = 0;
    if ($students = quest_get_course_students($quest->course)) {
        foreach ($students as $student) {
            if ($groupmember = $DB->get_record("groups_members", array("userid" => $student->id))) {
                if ($groupid == $groupmember->groupid) {
                    if ($calificationstudent = $DB->get_record("quest_calification_users",
                            array("questid" => $quest->id, "userid" => $student->id))) {

                        $grade = $calificationstudent->points;

                        if ($quest->allowteams) {
                            if ($calificationteam = $DB->get_record("quest_calification_teams",
                                    array("questid" => $quest->id, "teamid" => $calificationstudent->teamid))) {

                                $grade += $calificationteam->points * $quest->teamporcent / 100;
                            }
                        }

                        if ($grade > $maxpoints) {
                            $maxpoints = $grade;
                        }
                    }
                }
            }
        }
    }

    return $maxpoints;
}

/** get max score achieved by participants
 * @param quest record $quest
 * @return number */
function quest_get_maxpoints($quest) {
    global $DB;
    $maxpoints = 0;
    if ($students = quest_get_course_students($quest->course)) {
        foreach ($students as $student) {

            if ($calificationstudent = $DB->get_record("quest_calification_users",
                    array("questid" => $quest->id, "userid" => $student->id))) {

                $grade = $calificationstudent->points;

                if ($quest->allowteams) {
                    if ($calificationteam = $DB->get_record("quest_calification_teams",
                            array("questid" => $quest->id, "teamid" => $calificationstudent->teamid))) {

                        $grade += $calificationteam->points * $quest->teamporcent / 100;
                    }
                }

                if ($grade > $maxpoints) {
                    $maxpoints = $grade;
                }
            }
        }
    }

    return $maxpoints;
}
/**
 *
 * @param int $groupid
 * @param \stdClass $quest
 * @return number
 */
function quest_get_maxpoints_group_teams($groupid, $quest) {
    global $DB;
    $maxpoints = 0;
    if ($students = quest_get_course_students($quest->course)) {
        foreach ($students as $student) {
            if ($groupmember = $DB->get_record("groups_members", array("userid" => $student->id))) {
                if ($groupid == $groupmember->groupid) {
                    if ($calificationstudent = $DB->get_record("quest_calification_users",
                            array("questid" => $quest->id, "userid" => $student->id))) {

                        if ($quest->allowteams) {
                            if ($calificationteam = $DB->get_record("quest_calification_teams",
                                    array("questid" => $quest->id, "teamid" => $calificationstudent->teamid))) {

                                $grade = $calificationteam->points;
                            }
                        }

                        if ($grade > $maxpoints) {
                            $maxpoints = $grade;
                        }
                    }
                }
            }
        }
    }

    return $maxpoints;
}

// Any other quest functions go here. Each of them must have a name that.
// ...starts with quest.
function quest_get_submissions($quest) {
    global $DB;
    return $DB->get_records_select("quest_submissions", "questid = ?  AND timecreated > 0", array($quest->id), "timecreated DESC");
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $user
 * @return array
 */
function quest_get_user_submissions($quest, $user) {
    global $DB;
    // ...return real submissions of user newest first, oldest last. Ignores the dummy submissions.
    // ...which get created to hold the final grades for users that make no submissions.
    return $DB->get_records_select("quest_submissions", "questid = ? AND
        userid = ? AND timecreated > 0", array($quest->id, $user->id), "timecreated DESC");
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $user
 * @return mixed|stdClass|false|NULL
 */
function quest_get_student_submission($quest, $user) {
    // Return a submission for a particular user.
    global $CFG, $DB;

    $submission = $DB->get_record("quest_submissions", array("questid" => $quest->id, "userid" => $user->id));
    if (!empty($submission->timecreated)) {
        return $submission;
    }
    return null;
}
/**
 *
 * @param \stdClass $quest
 * @param string $order
 * @return array
 */
function quest_get_student_submissions($quest, $order = "title") {
    // Return all ENROLLED student submissions.
    global $CFG, $DB;

    if ($order == "title") {
        $order = "s.title";
    }
    if ($order == "name") {
        $order = "a.lastname, a.firstname";
    }
    if ($order == "time") {
        $order = "s.timecreated ASC";
    }
    // ...make sure it works on the site course.
    $site = get_site();
    if ($quest->course == $site->id) {
        $select = '';
        $params = array('quest' => $quest->id);
    } else { // ...evp quiz�s haya que probar que esto funciona como se quiere.
        $select = "u.course = :course AND";
        $params = array('course' => $quest->course, 'quest' => $quest->id);
    }

    return $DB->get_records_sql(
            "SELECT s.* FROM {quest_submissions} s,
            {user_students} u, {user} a
            WHERE $select s.userid = u.userid
            AND a.id = u.userid
            AND s.questid = :quest
            AND s.timecreated > 0
            ORDER BY $order", $params);
}
/**
 *
 * @param \stdClass $answer
 * @param string $all
 * @param string $order
 * @return array
 */
function quest_get_assessments($answer, $all = '', $order = '') {
    // Return assessments for this submission ordered oldest first, newest last.
    // ...new assessments made within the editing time are NOT returned unless they.
    // ...belong to the user or the second argument is set to ALL.
    global $CFG, $USER, $DB;

    $timenow = time();
    if (!$order) {
        $order = "dateassessment DESC";
    }
    if ($all != 'ALL') {
        return $DB->get_records_select("quest_assessments", "answerid = ? AND dateassessment < ? AND userid = ?",
                array($answer->id, $timenow, $USER->id), $order);
    } else {
        return $DB->get_records_select("quest_assessments", "answerid = ? AND dateassessment < ?", array($answer->id, $timenow),
                $order);
    }
}
/**
 *
 * @param \stdClass $submission
 * @return mixed|stdClass|false
 */
function quest_get_submission_assessment($submission) {
    global $DB;
    return $DB->get_record("quest_assessments_autors",
            array("submissionid" => $submission->id, "questid" => $submission->questid));
}
/**
 *
 * @param array $records
 * @param integer $queryid
 * @param \stdClass $cm
 */
function quest_export_csv($records, $queryid, $cm) {
    global $CFG;
    // Generate CSV report with $records.
    $localelang = current_language();
    // Moodle's bug Spanish RFC code is ES not ESP.
    $localelang = str_replace("esp", "es", $localelang);
    $localelang = str_replace("ESP", "ES", $localelang);
    setlocale(LC_ALL, $localelang);
    $localeconfig = localeconv();
    header("Content-Type: text/csv");
    header('Content-Disposition: attachment; filename="' .
            date('Y-m-d', time()) . '_' .
            $queryid . '_questournament_' .
            $cm->id .
            '.csv"');
    $firstrow = true;
    foreach ($records as $log) {
        $els = array();
        $elsk = array();
        foreach ($log as $key => $value) {
            // Detect other fields not numeric like IPs.
            if (is_numeric($value) && round($value) == $value) {
                $els[] = $value;
            } else if (is_numeric($value) && abs($value - round($value)) < 1) {
                $val = number_format($value, 10, $localeconfig['decimal_point'], '');
                $els[] = $val;
            } else {
                $els[] = $value;
            }

            $elsk[] = $key;
        }
        if ($firstrow) {
            echo implode(";", $elsk) . "\n";
            $firstrow = false;
        }
        echo implode(";", $els) . "\n";
    }
    die;
}
/**
 *
 * @param integer $userid
 * @param integer $courseid
 * @return string
 */
function quest_fullname($userid, $courseid) {
    global $CFG, $DB;
    if (!$user = $DB->get_record('user', array('id' => $userid))) {
        return get_string('unknownauthor', 'quest');
    }
    return '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $courseid . '">' . fullname($user) .
             '</a>';
}
/** Get challenges submitted from timestart in a course
 *
 * @global type $CFG
 * @global type $USER
 * @global type $DB
 * @param \stdClass $course
 * @param integer $timestart
 * @return boolean */
function quest_get_submitsubmission_logs($course, $timestart) {
    global $CFG, $USER, $DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    $submissions = $DB->get_records_sql(
            "SELECT s.*
              FROM {quest} q,{quest_submissions} s
              WHERE s.timecreated > :timestart AND s.timecreated < :timethen
                   AND q.course = :course
                   AND s.questid = q.id", array('timestart' => $timestart, 'timethen' => $timethen, 'course' => $course->id));
    return $submissions;
}

/**
 * @global type $CFG
 * @global type $USER
 * @global type $DB
 * @param \stdClass $course
 * @param integer $timestart
 * @return boolean */
function quest_get_submitsubmissionuser_logs($course, $timestart) {
    global $CFG, $USER, $DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    $submissions = $DB->get_records_sql(
            "SELECT s.*
              FROM {quest} q,{quest_submissions} s
              WHERE s.timecreated > :timestart AND s.timecreated < :timethen
                   AND q.course = :course
                   AND s.questid = q.id
                   AND s.userid = :userid",
            array('timestart' => $timestart, 'timethen' => $timethen, 'course' => $course->id, 'userid' => $USER->id));
    return $submissions;
}
/**
 *
 * @param \stdClass $course
 * @param integer $timestart
 * @return boolean|array
 */
function quest_get_approvesubmission_logs($course, $timestart) {
    global $CFG, $USER, $DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql(
            "SELECT l.time, l.url, u.firstname, u.lastname, s.questid, s.userid, s.title
             FROM {log} l,
                {quest} e,
                {quest_submissions} s,
                {user} u
            WHERE l.time > :timestart AND l.time < :timethen
                AND l.course = :course AND l.module = 'quest' AND l.action = 'approve_submiss'
                AND l.info = s.id AND s.userid = :user AND u.id = s.userid AND e.id = s.questid",
            array('timestart' => $timestart, 'timethen' => $timethen, 'course' => $course->id, 'user' => $USER->id));
}
/**
 *
 * @param \stdClass $course
 * @param integer $timestart
 * @return boolean|array
 */
function quest_get_submitanswer_logs($course, $timestart) {
    global $CFG, $USER, $DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql(
            "SELECT l.time, l.url, u.firstname, u.lastname, a.questid, a.userid, a.title
             FROM {log} l,
                {quest} e,
                {quest_answers} a,
                {user} u
            WHERE l.time > :timestart AND l.time < :timethen
                AND l.course = :course AND l.module = 'quest' AND l.action = 'submit_answer'
                AND l.info = a.id AND a.userid = :user AND u.id = a.userid AND e.id = a.questid",
            array('timestart' => $timestart, 'timethen' => $timethen, 'course' => $course->id, 'user' => $USER->id));
}
/**
 *
 * @param \stdClass $course
 * @param integer $timestart
 * @return boolean|array
 */
function quest_get_assessments_logs($course, $timestart) {
    global $CFG, $USER, $DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql(
            "SELECT l.time, l.url, u.firstname, u.lastname, a.questid, a.userid, s.title
                             FROM {log} l,
                                {quest} e,
                                {quest_answers} s,
                                {quest_assessments} a,
                                {user} u
                            WHERE l.time > :timestart AND l.time < :timethen
                                AND l.course = :course AND l.module = 'quest' AND l.action = 'assess_answer'
                                AND a.id = l.info AND s.id = a.answerid AND s.userid = :user
                                AND u.id = a.userid AND e.id = a.questid",
            array('timestart' => $timestart, 'timethen' => $timethen, 'course' => $course->id, 'user' => $USER->id));
}
/**
 *
 * @param \stdClass $course
 * @param integer $timestart
 * @return boolean|array
 */
function quest_get_assessmentsautor_logs($course, $timestart) {
    global $CFG, $USER, $DB;
    if (empty($USER->id)) {
        return false;
    }

    $timethen = time() - $CFG->maxeditingtime;
    return $DB->get_records_sql(
            "SELECT l.time, l.url, u.firstname, u.lastname, a.questid, a.userid, s.title
             FROM {log} l,
                {quest} e,
                {quest_submissions} s,
                {quest_assessments_autors} a,
                {user} u
            WHERE l.time > :timestart AND l.time < :timethen
                AND l.course = :course AND l.module = 'quest' AND l.action = 'assess_submissi'
                AND a.id = l.info AND s.id = a.submissionid AND s.userid = :user
                AND u.id = a.userid AND e.id = a.questid",
            array('timestart' => $timestart, 'timethen' => $timethen, 'course' => $course->id, 'user' => $USER->id));
}
/**
 *
 * @param integer $courseid
 * @param string $sort
 * @param string $dir
 * @param number $page
 * @param number $recordsperpage
 * @param string $firstinitial
 * @param string $lastinitial
 * @param mixed $group
 * @param string $search
 * @param string $fields
 * @param string $exceptions
 * @return array
 */
function quest_get_course_members($courseid, $sort = 's.timeaccess', $dir = '', $page = 0,
        $recordsperpage = 99999, $firstinitial = '', $lastinitial = '',
        $group = null, $search = '', $fields = '', $exceptions = '') {
    global $CFG;

    if (!$fields) {
        $fields = 'u.*';
    }

    $context = context_course::instance($courseid);
    $students = get_enrolled_users($context, '', 0, $fields, $sort);
    return $students;
}
