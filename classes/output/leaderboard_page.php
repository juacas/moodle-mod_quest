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

    /**
     * Quest activity record.
     *
     * @var stdClass
     */
    protected stdClass $quest;
    /**
     * Course record.
     *
     * @var stdClass
     */
    protected stdClass $course;
    /**
     * Course module record.
     *
     * @var object
     */
    protected object $cm;
    /**
     * Ranking rows.
     *
     * @var array
     */
    protected array $standings;
    /**
     * Whether the rows represent teams.
     *
     * @var bool
     */
    protected bool $isteams;
    /**
     * Current sort field.
     *
     * @var string
     */
    protected string $sort;
    /**
     * Current sort direction.
     *
     * @var string
     */
    protected string $dir;

    /**
     * Constructor.
     *
     * @param stdClass $quest
     * @param stdClass $course
     * @param object $cm
     * @param array $standings
     * @param bool $isteams
     * @param string $sort
     * @param string $dir
     */
    public function __construct(
        stdClass $quest,
        stdClass $course,
        object $cm,
        array $standings,
        bool $isteams = false,
        string $sort = 'rank',
        string $dir = 'ASC'
    ) {
        $this->quest = $quest;
        $this->course = $course;
        $this->cm = $cm;
        $this->standings = $standings;
        $this->isteams = $isteams;
        $this->sort = $sort;
        $this->dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $context = \context_module::instance($this->cm->id);
        $showauthoringdetails = !empty($this->quest->showauthoringdetails) || has_capability('mod/quest:manage', $context);

        $standingsdata = [];
        if ($this->isteams) {
            foreach ($this->standings as $s) {
                $rank = (int)($s->rank ?? 0);
                $standingsdata[] = [
                    'rank' => $rank,
                    'istop1' => ($rank === 1),
                    'istop2' => ($rank === 2),
                    'istop3' => ($rank === 3),
                    'istop' => ($rank >= 1 && $rank <= 3),
                    'teamname' => $s->name ?? ($s->teamname ?? '-'),
                    'nanswers' => (int)($s->nanswers ?? 0),
                    'nanswerassessment' => (int)($s->nanswerassessment ?? 0),
                    'nsubmissions' => (int)($s->nsubmissions ?? 0),
                    'nsubmissionsassessment' => (int)($s->nsubmissionsassessment ?? 0),
                    'pointssubmission' => round((float)($s->pointssubmission ?? 0), 1),
                    'pointsanswers' => round((float)($s->pointsanswers ?? 0), 1),
                    'points' => round((float)($s->points ?? 0), 1),
                ];
            }
        } else {
            $picturefields = \core_user\fields::get_picture_fields();
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
                $rank = (int)($s->rank ?? 0);

                $standingsdata[] = [
                    'rank' => $rank,
                    'istop1' => ($rank === 1),
                    'istop2' => ($rank === 2),
                    'istop3' => ($rank === 3),
                    'istop' => ($rank >= 1 && $rank <= 3),
                    'iscurrentuser' => (($s->userid ?? 0) == $USER->id),
                    'userpicture' => $pic,
                    'fullname' => fullname($userobj),
                    'email' => $s->email ?? '',
                    'teamname' => !empty($s->teamname) ? $s->teamname : '-',
                    'nanswers' => (int)($s->nanswers ?? 0),
                    'pointssubmission' => round((float)($s->pointssubmission ?? 0), 1),
                    'pointsanswers' => round((float)($s->pointsanswers ?? 0), 1),
                    'points' => round((float)($s->points ?? 0), 1),
                ];
            }
        }

        $sm = get_string_manager();
        $gethelp = function(string $identifier) use ($output, $sm): string {
            if ($sm->string_exists($identifier . '_help', 'quest')) {
                return $output->help_icon($identifier, 'quest');
            }
            return '';
        };

        $buildsort = function(string $column) use ($output): array {
            $isactive = ($this->sort === $column);
            if ($isactive) {
                $nextdir = ($this->dir === 'ASC') ? 'DESC' : 'ASC';
            } else {
                $nextdir = in_array($column, ['rank', 'lastname', 'user', 'team'], true) ? 'ASC' : 'DESC';
            }

            $url = new moodle_url('/mod/quest/viewclasification.php', [
                'id' => $this->cm->id,
                'action' => $this->isteams ? 'teams' : 'global',
                'sort' => $column,
                'dir' => $nextdir,
            ]);

            $icon = '';
            if ($isactive) {
                $iconname = ($this->dir === 'ASC') ? 't/up' : 't/down';
                $icon = ' ' . $output->pix_icon($iconname, $this->dir);
            }

            return [
                'url' => $url->out(false),
                'icon' => $icon,
                'isactive' => $isactive,
            ];
        };

        return [
            'questname' => format_string($this->quest->name),
            'isteams' => $this->isteams,
            'showauthoringdetails' => $showauthoringdetails,
            'allowteams' => !empty($this->quest->allowteams),
            'showclasifindividual' => !empty($this->quest->showclasifindividual),
            'backurl' => (new moodle_url('/mod/quest/view.php', ['id' => $this->cm->id]))->out(false),
            'teamsurl' => (new moodle_url('/mod/quest/viewclasification.php', [
                'action' => 'teams',
                'id' => $this->cm->id,
            ]))->out(false),
            'globalurl' => (new moodle_url('/mod/quest/viewclasification.php', [
                'action' => 'global',
                'id' => $this->cm->id,
            ]))->out(false),
            'hasstandings' => !empty($standingsdata),
            'standings' => $standingsdata,
            'help_rank' => $gethelp('rank'),
            'help_user' => $gethelp('user'),
            'help_team' => $gethelp('teams'),
            'help_nanswers' => $gethelp('nanswers'),
            'help_nanswersassessment' => $gethelp('nanswersassessment'),
            'help_nsubmissions' => $gethelp('nsubmissions'),
            'help_nsubmissionsassessment' => $gethelp('nsubmissionsassessment'),
            'help_pointssubmission' => $gethelp('pointssubmission'),
            'help_pointsanswers' => $gethelp('pointsanswers'),
            'help_points' => $gethelp('points'),
            'sort_rank' => $buildsort('rank'),
            'sort_user' => $buildsort('lastname'),
            'sort_team' => $buildsort('team'),
            'sort_nanswers' => $buildsort('nanswers'),
            'sort_nanswersassessment' => $buildsort('nanswerassessment'),
            'sort_nsubmissions' => $buildsort('nsubmissions'),
            'sort_nsubmissionsassessment' => $buildsort('nsubmissionsassessment'),
            'sort_pointssubmission' => $buildsort('pointssubmission'),
            'sort_pointsanswers' => $buildsort('pointsanswers'),
            'sort_points' => $buildsort('points'),
        ];
    }
}
