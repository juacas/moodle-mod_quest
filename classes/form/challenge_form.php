<?php
// This file is part of Questournament activity for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Challenge form.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quest\form;

use context_module;
use html_writer;
use moodle_url;
use moodleform;
use question_bank;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating or editing a Quest challenge.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class challenge_form extends moodleform {
    /**
     * Define the challenge form fields.
     *
     * @return void
     */
    public function definition() {
        global $PAGE;

        $mform = & $this->_form;
        $challenge = $this->_customdata['challenge'];
        $quest = $this->_customdata['quest'];
        $cm = $this->_customdata['cm'];
        $definitionoptions = $this->_customdata['definitionoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];
        $action = $this->_customdata['action'];
        $composerquestions = $this->_customdata['composerquestions'] ?? [];
        $questionbankcontent = in_array($action, ['approve', 'modif'], true) &&
            !empty($this->_customdata['linkedquestion']);

        $context = context_module::instance($cm->id);
        $ismanager = has_capability('mod/quest:manage', $context);

        $mform->addElement('hidden', 'id', $cm->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'sid', $challenge->id);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'nosubmit', 0); // ...!!!!evp esto tiene sentido si usamos el
                                                     // js.
                                                     // ...definido. hay que ver si es necesario.
        $mform->setType('nosubmit', PARAM_BOOL);

        if ($composerquestions) {
            $mform->addElement('hidden', 'composer_mode', 1);
            $mform->setType('composer_mode', PARAM_BOOL);
            $mform->addElement('hidden', 'composer_questionids', implode(',', array_map(
                static fn($question) => (int)$question->id,
                $composerquestions
            )));
            $mform->setType('composer_questionids', PARAM_RAW_TRIMMED);
            $mform->addElement('html', $this->render_question_composer(
                $composerquestions,
                $cm,
                $action,
                (int)$challenge->id
            ));
        }

        if (!$questionbankcontent || $action === 'modif') {
            $mform->addElement('text', 'title', get_string("title", "quest"), 'size="60" maxlength="100"');
            $mform->setType('title', PARAM_TEXT);
            $mform->addRule('title', null, 'required', null, 'client');
        }

        if (!$questionbankcontent) {
            $mform->addElement(
                'editor',
                'description_editor',
                get_string("introductiontothechallenge", "quest"),
                null,
                $definitionoptions
            );
            $mform->setType('description_editor', PARAM_RAW);
            $mform->addRule('description_editor', null, 'required', null, 'client');
        }

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
            $mform->addElement('html', '</br>' . get_string("challengeend", "quest") . ': ' . userdate($challengeend));
            $mform->addElement('hidden', 'dateend', $challengeend);
        }
        $mform->setType('dateend', PARAM_INT);

        $calendar = \core_calendar\type_factory::get_calendar_instance();
        if ($ismanager && $calendar->get_name() === 'gregorian') {
            $formatdatetime = static function (int $timestamp): string {
                $parts = usergetdate($timestamp);
                return sprintf(
                    '%04d-%02d-%02dT%02d:%02d',
                    $parts['year'],
                    $parts['mon'],
                    $parts['mday'],
                    $parts['hours'],
                    $parts['minutes']
                );
            };
            $PAGE->requires->js_call_amd('mod_quest/challenge_datetime', 'init', [[
                'fields' => [
                    [
                        'name' => 'datestart',
                        'min' => $formatdatetime((int)$quest->datestart),
                        'max' => $formatdatetime((int)$quest->dateend),
                    ],
                    [
                        'name' => 'dateend',
                        'min' => $formatdatetime((int)$quest->datestart),
                        'max' => $formatdatetime((int)$quest->dateend),
                    ],
                ],
            ]]);
        }

        $numbers = [];
        for ($i = $quest->mincalification; $i <= $quest->maxcalification; $i++) {
            $numbers[$i] = $i;
        }

        $mform->addElement('select', 'pointsmax', get_string("pointsmax", "quest"), $numbers);
        $mform->setDefault('pointsmax', $quest->maxcalification);
        $mform->addHelpButton('pointsmax', 'pointsmax', 'quest');
        $mform->addElement('select', 'pointsmin', get_string("pointsmin", "quest"), $numbers);
        $mform->setDefault('pointsmin', $quest->mincalification);
        $mform->addHelpButton('pointsmin', 'pointsmin', 'quest');

        $numbers = [];
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
            $mform->addElement(
                'filemanager',
                'attachment_filemanager',
                get_string("attachments", "quest"),
                null,
                $attachmentoptions
            );
        }
        if ($action == 'approve') {
            $mform->addElement('textarea', 'commentteacherauthor', get_string("commentsforauthor", "quest"), 'rows="6" cols="70"');
            $mform->addHelpButton('commentteacherauthor', 'commentsforauthor', 'quest');
        }
        if ($ismanager) {
            $mform->addElement('textarea', 'commentteacherpupil', get_string("commentsforstudent", "quest"), 'rows="6" cols="70"');
            $mform->addHelpButton('commentteacherpupil', 'commentsforstudent', 'quest');
            $difficultyscale = quest_get_difficulty_levels();
            $radioarray = [];
            foreach ($difficultyscale as $value => $item) {
                $radioarray[] = & $mform->createElement('radio', 'perceiveddifficulty', '', $item, $value);
            }
            $mform->addGroup(
                $radioarray,
                'perceiveddifficultyOps',
                get_string("perceivedTeacherDifficultyLevel", "quest"),
                [' '],
                false
            );
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
            $buttonarray = [];
            $buttonarray[] = & $mform->createElement('submit', 'submitbuttonapprove', get_string('approve', 'quest'));
            $buttonarray[] = & $mform->createElement('submit', 'submitbuttonsave', get_string('savechanges'));
            $buttonarray[] = & $mform->createElement('reset', 'resetbutton', get_string('resetchanges', 'quest'));
            $buttonarray[] = & $mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        } else {
            if ($action === 'modif' && $composerquestions) {
                $submitlabel = get_string('savechallenge', 'quest');
            } else if ($composerquestions) {
                $submitlabel = get_string('createchallenge', 'quest');
            } else {
                $submitlabel = get_string('savechanges');
            }
            $this->add_action_buttons(true, $submitlabel);
        }
        // In the form the id hidden element is used to hold the cmid of the quest.
        $formdata = clone $challenge;
        $formdata->sid = $challenge->id;
        $formdata->id = $cm->id;
        $this->set_data($formdata);
    }

    /**
     * Render the sortable question list included in the challenge creation form.
     *
     * @param stdClass[] $questions Selected ready questions.
     * @param object $cm Activity course module.
     * @return string
     */
    private function render_question_composer(array $questions, object $cm, string $action, int $challengeid): string {
        global $CFG, $DB, $OUTPUT, $USER;

        require_once($CFG->libdir . '/questionlib.php');
        $context = \context_module::instance($cm->id);
        $references = [];
        if ($action === 'modif' && $challengeid > 0) {
            foreach (\mod_quest\question\question_reference_service::get_challenge_questions($challengeid) as $slot) {
                $references[(int)$slot->reference->questionbankentryid] = $slot->reference;
            }
        }
        $questions = array_map(static function ($question) {
            $metadata = \mod_quest\question\bank_provider::get_question((int)$question->id);
            $metadata->maxmark = (float)($question->maxmark ?? 1);
            return $metadata;
        }, $questions);
        // Composer previews are not challenge attempts. Core's preview file handler
        // can serve their question files before the challenge has been saved.
        $usage = \question_engine::make_questions_usage_by_activity('core_question_preview', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $previewslots = [];
        foreach (array_values($questions) as $question) {
            $loadedquestion = \question_bank::load_question((int)$question->id);
            $previewslots[] = $usage->add_question($loadedquestion, (float)($question->maxmark ?? 1));
        }
        $usage->start_all_questions();
        \question_engine::save_questions_usage_by_activity($usage);

        $questionids = array_map(static fn($question) => (int)$question->id, $questions);
        $returnurl = quest_question_composer_url(
            $cm->id,
            $action === 'modif' ? 'modif' : 'addqchallenge',
            $questionids,
            $action === 'modif' ? $challengeid : 0
        )->out_as_local_url(false);

        $html = html_writer::tag('h4', get_string('page') . ' 1', ['class' => 'h5 mb-2']);
        $html .= html_writer::start_tag('ul', [
            'class' => 'list-group mb-3 quest-question-composer-slots',
            'id' => 'quest-question-composer-slots',
            'data-region' => 'question-composer-slots',
        ]);
        foreach (array_values($questions) as $index => $question) {
            $html .= html_writer::start_tag('li', [
                'class' => 'list-group-item quest-question-composer-slot',
                'data-region' => 'question-composer-slot',
                'data-questionid' => $question->id,
            ]);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'question_order[]',
                'value' => $question->id,
            ]);
            $html .= html_writer::start_div('quest-question-composer-row');
            $html .= html_writer::link('#', $OUTPUT->pix_icon('i/dragdrop', get_string('move')), [
                'class' => 'quest-question-composer-drag',
                'data-drag-type' => 'move',
                'aria-label' => get_string('move') . ' ' . ($index + 1),
            ]);
            $html .= html_writer::tag('span', (string)($index + 1), [
                'class' => 'quest-question-composer-number',
                'data-region' => 'question-number',
            ]);
            $html .= html_writer::start_div('quest-question-composer-name');
            $html .= html_writer::tag('span', format_string($question->name), [
                'class' => 'fw-bold',
                'data-region' => 'question-name',
            ]);
            $qtypename = \question_bank::get_qtype($question->qtype)->local_name();
            $html .= html_writer::tag('span', $qtypename, ['class' => 'badge bg-light text-dark ms-2']);
            $categoryname = $DB->get_field('question_categories', 'name', ['id' => $question->categoryid]);
            if ($categoryname) {
                $html .= html_writer::tag('span', format_string($categoryname), [
                    'class' => 'badge bg-primary ms-2 quest-question-composer-bank',
                ]);
            }
            $html .= html_writer::end_div();

            $questioncontext = \context::instance_by_id($question->contextid);
            $canedit = has_capability('moodle/question:editall', $questioncontext) ||
                ((int)$question->createdby === (int)$USER->id &&
                    has_capability('moodle/question:editmine', $questioncontext));
            if ($canedit) {
                $editurl = new moodle_url('/question/bank/editquestion/question.php', [
                    'id' => $question->id,
                    'cmid' => $cm->id,
                    'returnurl' => $returnurl,
                ]);
                $html .= html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')), [
                    'class' => 'btn btn-outline-secondary quest-question-composer-action',
                    'target' => '_blank',
                ]);
            }
            $selectedversion = $references[(int)$question->questionbankentryid]->version ?? null;
            $versions = quest_get_question_versions((int)$question->questionbankentryid);
            $versionoptions = html_writer::tag('option', get_string('alwayslatest', 'quiz'), [
                'value' => 0,
                'selected' => $selectedversion === null ? 'selected' : null,
            ]);
            $latestversion = 0;
            foreach ($versions as $candidate) {
                if ($candidate->status === \core_question\local\bank\question_version_status::QUESTION_STATUS_READY) {
                    $latestversion = (int)$candidate->version;
                    break;
                }
            }
            if ($latestversion === 0 && $versions) {
                $latestversion = (int)reset($versions)->version;
            }
            foreach ($versions as $version) {
                $number = (int)$version->version;
                $label = get_string(
                    $number === $latestversion ? 'questionversionlatest' : 'questionversion',
                    'quiz',
                    $number
                );
                $versionoptions .= html_writer::tag('option', $label, [
                    'value' => $number,
                    'selected' => (int)$selectedversion === $number ? 'selected' : null,
                ]);
            }
            $html .= html_writer::tag('select', $versionoptions, [
                'class' => 'form-select quest-question-composer-version',
                'name' => 'question_version[' . $question->id . ']',
                'aria-label' => get_string('question_version', 'question'),
            ]);
            $previewurl = \qbank_previewquestion\helper::question_preview_url(
                $question->id,
                null,
                null,
                null,
                null,
                $context,
                $cm->id
            );
            $html .= html_writer::link($previewurl, $OUTPUT->pix_icon('i/preview', get_string('preview')), [
                'class' => 'btn btn-outline-primary quest-question-composer-action',
                'target' => '_blank',
            ]);
            $html .= html_writer::tag('button', $OUTPUT->pix_icon('t/delete', get_string('remove')), [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary quest-question-composer-action',
                'data-action' => 'remove-question',
                'aria-label' => get_string('remove'),
            ]);
            $html .= html_writer::start_div('quest-question-composer-mark');
            $html .= html_writer::empty_tag('input', [
                'type' => 'number',
                'min' => 0.01,
                'step' => 'any',
                'required' => 'required',
                'name' => 'question_mark[' . $question->id . ']',
                'value' => $question->maxmark ?? 1,
                'class' => 'form-control',
                'aria-label' => get_string('questionmark', 'quest'),
            ]);
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::div(
                \mod_quest\service\autograde_service::render_question_preview($usage, $previewslots[$index]),
                'quest-question-composer-preview'
            );
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::div(get_string('noquestionsselected', 'quest'), 'alert alert-warning d-none', [
            'data-region' => 'question-composer-empty',
        ]);

        return $html;
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
            $errors['pointsmax'] = get_string('checkthat', 'quest') . ': '
                    . get_string('pointsmin', 'quest') . ' (' . $data['pointsmin'] . ')'
                    . ' < ' . get_string('pointsmax', 'quest') . ' (' . $data['pointsmax'] . ')'
                    . ' < ' . $quest->maxcalification;
        }
        if ($data['pointsmin'] < $quest->mincalification) {
            $errors['pointsmin'] = get_string('checkthat', 'quest') . ': ' .
                   $quest->mincalification .
                   ' < ' . get_string('pointsmin', 'quest') .  ' (' . $data['pointsmin'] . ')' . ' < ' .
                    get_string('pointsmax', 'quest') . ' (' . $data['pointsmax'] . ')';
        }
        if ($data['pointsmax'] < $data['pointsmin']) {
            $errors['pointsmax'] = get_string('checkthat', 'quest') . ': ' .
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
            $errors['initialpoints'] = get_string('checkthat', 'quest') . ': ' .
                    get_string('initialpoints', 'quest') . ' (' . $data['initialpoints'] . ')' .
                    ' > ' . get_string('pointsmin', 'quest') . ' (' . $data['pointsmin'] . ')';
        }
        return $errors;
    }
}
