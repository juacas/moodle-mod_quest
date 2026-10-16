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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quest\output;

use plugin_renderer_base;

/**
 * Standard renderer for the Quest activity module.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the tournament dashboard view.
     *
     * @param view_page $page
     * @return string HTML
     */
    public function render_view_page(view_page $page): string {
        return $this->render_from_template('mod_quest/view', $page->export_for_template($this));
    }

    /**
     * Render the tournament leaderboard page.
     *
     * @param leaderboard_page $page
     * @return string HTML
     */
    public function render_leaderboard_page(leaderboard_page $page): string {
        return $this->render_from_template('mod_quest/leaderboard', $page->export_for_template($this));
    }
}
