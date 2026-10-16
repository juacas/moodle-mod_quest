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
 * Checkbox column for selecting a question from the official bank view.
 *
 * @package    mod_quest
 * @copyright 2026 onwards EDUVALab, University of Valladolid
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_column extends \core_question\local\bank\checkbox_column {
    /**
     * Render a checkbox only for selectable questions.
     *
     * @param object $question Question row.
     * @param string $rowclasses Row CSS classes.
     * @return void
     */
    protected function display_content($question, $rowclasses): void {
        if (custom_view::selectable($question)) {
            parent::display_content($question, $rowclasses);
        }
    }
}
