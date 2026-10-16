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
 * Question name column for the Quest bank picker.
 *
 * @package    mod_quest
 * @copyright 2026 onwards EDUVALab, University of Valladolid
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class name_column extends \core_question\local\bank\column_base {
    /**
     * Return the internal column name.
     *
     * @return string Column name.
     */
    public function get_name(): string {
        return 'questquestionname';
    }

    /**
     * Return the translated column title.
     *
     * @return string Column title.
     */
    public function get_title(): string {
        return get_string('questionname', 'question');
    }

    /**
     * Return the SQL sort expression.
     *
     * @return string SQL expression.
     */
    public function is_sortable() {
        return 'q.name';
    }

    /**
     * Return fields required by the column.
     *
     * @return array Required fields.
     */
    public function get_required_fields(): array {
        return ['q.name', 'q.qtype'];
    }

    /**
     * Return additional CSS classes for the column.
     *
     * @return array CSS classes.
     */
    public function get_extra_classes(): array {
        return [];
    }

    /**
     * Render the question name.
     *
     * @param object $question Question row.
     * @param string $rowclasses Row CSS classes.
     * @return void
     */
    protected function display_content($question, $rowclasses): void {
        echo \html_writer::tag('label', format_string($question->name), [
            'for' => 'checkq' . $question->id,
            'class' => 'mb-0 me-2 cursor-pointer',
        ]);
    }
}
