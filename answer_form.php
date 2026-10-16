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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Form for creating or editing a Quest answer.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating or editing an answer.
 */
class quest_print_answer_form extends moodleform {
    /**
     * Define the answer form fields.
     *
     * @return void
     */
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
            $mform->addElement(
                'filemanager',
                'attachment_filemanager',
                get_string("attachments", "quest"),
                null,
                $attachmentoptions
            );
        }
        $difficultyscale = quest_get_difficulty_levels();
        $radioarray = [];
        foreach ($difficultyscale as $value => $item) {
            $radioarray[] = & $mform->createElement('radio', 'perceiveddifficulty', '', $item, $value);
        }
        $mform->addGroup($radioarray, 'radioar', get_string("perceiveddifficultyLevelQuestion", "quest"), [' '], false);
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
