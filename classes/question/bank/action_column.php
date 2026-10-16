<?php
// This file is part of Moodle - https://moodle.org/
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

namespace mod_quest\question\bank;

/**
 * Action column for the Quest question picker.
 *
 * @package    mod_quest
 * @copyright 2026 onwards EDUVALab, University of Valladolid
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_column extends \core_question\local\bank\column_base {
    /**
     * Return the internal column name.
     *
     * @return string Column name.
     */
    public function get_name(): string {
        return 'actions';
    }

    /**
     * Return the translated column title.
     *
     * @return string Column title.
     */
    public function get_title(): string {
        return get_string('actions');
    }

    /**
     * Return the fields required by the column.
     *
     * @return array Required fields.
     */
    public function get_required_fields(): array {
        return ['q.id', 'q.qtype'];
    }

    /**
     * Return the CSS classes used by the column.
     *
     * @return array CSS classes.
     */
    public function get_extra_classes(): array {
        return ['text-nowrap', 'text-right', 'text-end'];
    }

    /**
     * Render the available question actions.
     *
     * @param object $question Question row.
     * @param string $rowclasses Row CSS classes.
     * @return void
     */
    protected function display_content($question, $rowclasses): void {
        global $OUTPUT;

        if (custom_view::selectable($question)) {
            echo \html_writer::tag('button', get_string('useexistingquestion', 'quest'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm me-1',
                'data-action' => 'quest-use-question',
                'data-questionid' => $question->id,
            ]);
        }

        if (question_has_capability_on($question, 'edit') && \question_bank::is_qtype_installed($question->qtype)) {
            $title = get_string('editbankquestion', 'quest');
            echo \html_writer::link($this->qbank->question_edit_url((int)$question->id),
                $OUTPUT->pix_icon('t/edit', $title, 'moodle', ['class' => 'icon']), [
                    'class' => 'btn btn-icon btn-sm btn-outline-secondary me-1',
                    'title' => $title,
                    'aria-label' => $title,
                    'target' => '_blank',
                ]);
        }

        $usable = method_exists('\question_bank', 'is_qtype_usable')
            ? \question_bank::is_qtype_usable($question->qtype)
            : (\question_bank::is_qtype_installed($question->qtype) && $question->qtype !== 'missingtype');

        if (question_has_capability_on($question, 'use') && $usable) {
            $title = get_string('preview');
            echo \html_writer::link(
                new \moodle_url('/question/bank/previewquestion/preview.php', ['id' => $question->id]),
                $OUTPUT->pix_icon('t/preview', $title, 'moodle', ['class' => 'icon']), [
                    'class' => 'btn btn-icon btn-sm btn-outline-secondary',
                    'title' => $title,
                    'aria-label' => $title,
                    'target' => '_blank',
                    'rel' => 'noopener',
                ]
            );
        }
    }
}
