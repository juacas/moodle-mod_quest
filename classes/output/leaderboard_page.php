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

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use user_picture;

/**
 * Templatable leaderboard page model for Quest standings.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class leaderboard_page implements renderable, templatable {

    protected stdClass $quest;
    protected stdClass $course;
    protected object $cm;
    protected array $standings;

    /**
     * Constructor.
     *
     * @param stdClass $quest
     * @param stdClass $course
     * @param object $cm
     * @param array $standings
     */
    public function __construct(
        stdClass $quest,
        stdClass $course,
        object $cm,
        array $standings
    ) {
        $this->quest = $quest;
        $this->course = $course;
        $this->cm = $cm;
        $this->standings = $standings;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $picturefields = \core_user\fields::get_picture_fields();
        $standingsdata = [];
        foreach ($this->standings as $s) {
            $userobj = new stdClass();
            foreach ($picturefields as $field) {
                if ($field === 'id') {
                    $userobj->id = $s->userid ?? ($s->id ?? 0);
                } else {
                    $userobj->$field = $s->$field ?? '';
                }
            }

            $pic = $output->user_picture($userobj, ['size' => 35]);

            $standingsdata[] = [
                'rank' => $s->rank,
                'istop1' => ($s->rank === 1),
                'istop2' => ($s->rank === 2),
                'istop3' => ($s->rank === 3),
                'istop' => ($s->rank <= 3),
                'iscurrentuser' => ($s->userid == $USER->id),
                'userpicture' => $pic,
                'fullname' => fullname($userobj),
                'email' => $s->email,
                'teamname' => !empty($s->teamname) ? $s->teamname : '-',
                'nanswers' => (int)$s->nanswers,
                'pointssubmission' => round((float)$s->pointssubmission, 1),
                'pointsanswers' => round((float)$s->pointsanswers, 1),
                'points' => round((float)$s->points, 1),
            ];
        }

        return [
            'questname' => format_string($this->quest->name),
            'allowteams' => !empty($this->quest->allowteams),
            'backurl' => (new moodle_url('/mod/quest/view.php', ['id' => $this->cm->id]))->out(false),
            'standings' => $standingsdata,
        ];
    }
}
