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
use mod_quest\service\scoring_calculator;

/**
 * Templatable view page model for the Quest tournament dashboard.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements renderable, templatable {

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
     * Challenge records shown in cards.
     *
     * @var array
     */
    protected array $challenges;
    /**
     * Whether the current user can add a challenge.
     *
     * @var bool
     */
    protected bool $canaddchallenge;
    /**
     * Summary data prepared by the page controller.
     *
     * @var array
     */
    protected array $summarydata;
    /**
     * Rendered detail table HTML.
     *
     * @var string
     */
    protected string $detailtablehtml;
    /**
     * Rendered table legend HTML.
     *
     * @var string
     */
    protected string $legendhtml;

    /**
     * Constructor.
     *
     * @param stdClass $quest
     * @param stdClass $course
     * @param object $cm
     * @param array $challenges
     * @param bool $canaddchallenge
     * @param array $summarydata
     * @param string $detailtablehtml
     * @param string $legendhtml
     */
    public function __construct(
        stdClass $quest,
        stdClass $course,
        object $cm,
        array $challenges,
        bool $canaddchallenge,
        array $summarydata = [],
        string $detailtablehtml = '',
        string $legendhtml = ''
    ) {
        $this->quest = $quest;
        $this->course = $course;
        $this->cm = $cm;
        $this->challenges = $challenges;
        $this->canaddchallenge = $canaddchallenge;
        $this->summarydata = $summarydata;
        $this->detailtablehtml = $detailtablehtml;
        $this->legendhtml = $legendhtml;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $timenow = time();
        $tinitial = (int)($this->quest->tinitial * 86400);

        $context = \context_module::instance($this->cm->id);
        $challengesdata = [];
        foreach ($this->challenges as $c) {
            $curpoints = scoring_calculator::calculate_points(
                $timenow,
                (int)$c->datestart,
                (int)$c->dateend,
                $tinitial,
                !empty($c->dateanswercorrect) ? (int)$c->dateanswercorrect : null,
                (float)$c->initialpoints,
                (float)$c->pointsmax,
                (float)$c->pointsmin
            );

            $phase = scoring_calculator::get_phase(
                $timenow,
                (int)$c->datestart,
                (int)$c->dateend,
                $tinitial,
                !empty($c->dateanswercorrect) ? (int)$c->dateanswercorrect : null
            );

            $phaseclass = 'quest-phase-badge';
            $phasename = get_string('phase_stationary', 'quest');
            $cardstyle = 'background-color: #f0f7ff; border: 1px solid #cce5ff; border-top: 4px solid #0d6efd; border-radius: 8px;';

            if ($phase === scoring_calculator::PHASE_INFLATION) {
                $phaseclass = 'quest-phase-badge';
                $phasename = get_string('phase_inflation', 'quest');
                $cardstyle = 'background-color: #fffbf0; border: 1px solid #ffeeba; '
                    . 'border-top: 4px solid #ffc107; border-radius: 8px;';
            } else if ($phase === scoring_calculator::PHASE_DEFLATION) {
                $phaseclass = 'quest-phase-badge';
                $phasename = get_string('phase_deflation', 'quest');
                $cardstyle = 'background-color: #f9f5ff; border: 1px solid #e2d9f3; '
                    . 'border-top: 4px solid #6f42c1; border-radius: 8px;';
            } else if ($phase === scoring_calculator::PHASE_ENDED) {
                $phaseclass = 'quest-phase-badge quest-phase-badge-closed';
                $phasename = get_string('closed', 'quest');
                $cardstyle = 'background-color: #f8f9fa; border: 1px solid #e9ecef; '
                    . 'border-top: 4px solid #6c757d; border-radius: 8px;';
            }

            $attentionstatuses = \quest_get_challenge_attention_status($c, $this->cm, $context);

            $timeleft = '';
            if ($timenow < $c->dateend) {
                $diff = $c->dateend - $timenow;
                $days = (int)($diff / 86400);
                $hours = (int)(($diff % 86400) / 3600);
                $timeleft = $days > 0 ? "{$days}d {$hours}h left" : "{$hours}h left";
            } else {
                $timeleft = get_string('closed', 'quest');
            }

            $desc = file_rewrite_pluginfile_urls(
                $c->description,
                'pluginfile.php',
                $context->id,
                'mod_quest',
                'submission',
                $c->id
            );
            $challengesdata[] = [
                'id' => $c->id,
                'title' => format_string($c->title),
                'description' => format_text($desc, $c->descriptionformat ?: FORMAT_HTML, ['context' => $context]),
                'descriptionexcerpt' => shorten_text(strip_tags($c->description), 120),
                'currentpoints' => number_format($curpoints, 4),
                'datestart' => (int)$c->datestart,
                'dateend' => (int)$c->dateend,
                'tinitial' => $tinitial,
                'dateanswercorrect' => !empty($c->dateanswercorrect) ? (int)$c->dateanswercorrect : 0,
                'initialpoints' => (float)$c->initialpoints,
                'pointsmax' => (float)$c->pointsmax,
                'pointsmin' => (float)$c->pointsmin,
                'type' => (int)$this->quest->typecalification,
                'phaseclass' => $phaseclass,
                'phasename' => $phasename,
                'attentionstatuses' => $attentionstatuses,
                'cardstyle' => $cardstyle,
                'nanswers' => $c->nanswers,
                'timeleft' => $timeleft,
                'minichartsvg' => $this->render_card_sparkline($c, $tinitial, $timenow),
                'viewurl' => (new moodle_url('/mod/quest/challenges.php', [
                    'id' => $this->cm->id,
                    'cid' => $c->id,
                    'action' => 'showchallenge',
                ]))->out(false),
            ];
        }

        $hasgradinglinks = !empty($this->summarydata['challengegradinghtml']) || !empty($this->summarydata['answergradinghtml']);
        $hasdetailtable = !empty($this->detailtablehtml);

        return [
            'servertime' => $timenow,
            'questname' => format_string($this->quest->name),
            'intro' => format_module_intro('quest', $this->quest, $this->cm->id),
            'datestartstr' => userdate($this->quest->datestart, get_string('strftimedatetime', 'langconfig')),
            'dateendstr' => userdate($this->quest->dateend, get_string('strftimedatetime', 'langconfig')),
            'allowteams' => !empty($this->quest->allowteams),
            'canaddchallenge' => $this->canaddchallenge,
            'canaddqchallenge' => $this->canaddchallenge &&
                (!empty($this->quest->allowqbankquestions) || !empty($this->summarydata['ismanager'])),
            'addchallengeurl' => (new moodle_url('/mod/quest/challenges.php', [
                'id' => $this->cm->id,
                'action' => 'submitchallenge',
            ]))->out(false),
            'addqchallengeurl' => (new moodle_url('/mod/quest/challenges.php', [
                'id' => $this->cm->id,
                'action' => 'addqchallenge',
            ]))->out(false),
            'leaderboardurl' => (new moodle_url('/mod/quest/viewclasification.php', [
                'id' => $this->cm->id,
            ]))->out(false),
            'myplaceurl' => (new moodle_url('/mod/quest/myplace.php', [
                'id' => $this->cm->id,
            ]))->out(false),
            'teamurl' => (!empty($this->summarydata['ismanager']) && !empty($this->quest->allowteams))
                ? (new moodle_url('/mod/quest/team.php', ['id' => $this->cm->id]))->out(false)
                : '',
            'scheduleurl' => !empty($this->summarydata['ismanager'])
                ? (new moodle_url('/mod/quest/schedule.php', ['id' => $this->cm->id]))->out(false)
                : '',
            'hasgradinglinks' => $hasgradinglinks,
            'challengegradinghtml' => $this->summarydata['challengegradinghtml'] ?? '',
            'answergradinghtml' => $this->summarydata['answergradinghtml'] ?? '',
            'simplecalificationhtml' => $this->summarydata['simplecalificationhtml'] ?? '',
            'clasificationswitchurl' => $this->summarydata['clasificationswitchurl'] ?? '',
            'clasificationswitchlabel' => $this->summarydata['clasificationswitchlabel'] ?? '',
            'challengecount' => count($challengesdata),
            'haschallenges' => !empty($challengesdata),
            'challenges' => $challengesdata,
            'hasdetailtable' => $hasdetailtable,
            'detailtablehtml' => $this->detailtablehtml,
            'legendhtml' => $this->legendhtml,
            'caneditdates' => !empty($this->summarydata['ismanager']),
        ];
    }

    /**
     * Generate a lightweight vector sparkline SVG for a challenge card.
     *
     * @param \stdClass $c Challenge submission record.
     * @param int $tinitial Stationary duration in seconds.
     * @param int $timenow Current timestamp.
     * @return string SVG markup.
     */
    protected function render_card_sparkline($c, int $tinitial, int $timenow): string {
        $datestart = (int)$c->datestart;
        $dateend = (int)$c->dateend;
        if ($dateend <= $datestart) {
            return '';
        }

        $pinit = (float)$c->initialpoints;
        $pmax = (float)$c->pointsmax;
        $pmin = (float)($c->pointsmin ?? 0);
        $dateanswercorrect = !empty($c->dateanswercorrect) ? (int)$c->dateanswercorrect : null;

        // Square viewBox for 1:1 rendering.
        $width = 240;
        $height = 240;
        $padleft = 10;
        $padright = 10;
        $padtop = 18;   // Space for phase labels.
        $padbottom = 18; // Space for X-axis dates.
        $plotwidth = $width - $padleft - $padright;
        $plotheight = $height - $padtop - $padbottom;

        $ymax = max($pmax * 1.12, $pinit * 1.15, 10);
        $ymin = 0;

        $getx = function($t) use ($datestart, $dateend, $padleft, $plotwidth) {
            $clamped = max($datestart, min($dateend, $t));
            return round($padleft + (($clamped - $datestart) / ($dateend - $datestart)) * $plotwidth, 1);
        };

        $gety = function($p) use ($ymin, $ymax, $padtop, $plotheight) {
            $clamped = max($ymin, min($ymax, $p));
            return round($padtop + $plotheight - (($clamped - $ymin) / ($ymax - $ymin)) * $plotheight, 1);
        };

        $statend = min($dateend, $datestart + $tinitial);
        $statx = $getx($statend);
        $hascorrect = !empty($dateanswercorrect) && $dateanswercorrect > $datestart && $dateanswercorrect < $dateend;
        $inflend = $hascorrect ? $dateanswercorrect : $dateend;
        $inflx = $getx($inflend);
        $endx = $getx($dateend);
        $startx = $getx($datestart);

        $staty = $gety($pinit);

        $inflscore = scoring_calculator::calculate_points(
            $inflend, $datestart, $dateend, $tinitial, $dateanswercorrect, $pinit, $pmax, $pmin
        );
        $inflendy = $gety($inflscore);

        // SVG: width/height="100%" so CSS container controls the size. No fixed height.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"'
             . ' viewBox="0 0 ' . $width . ' ' . $height . '"'
             . ' class="quest-card-sparkline" preserveAspectRatio="xMidYMid meet"'
             . ' style="display:block; border-radius: 6px; background: rgba(0,0,0,0.02);">';

        // Phase background zones.
        $svg .= '<rect x="' . $startx . '" y="' . $padtop . '" width="' . max(0, $statx - $startx)
              . '" height="' . $plotheight . '" fill="rgba(13, 110, 253, 0.08)" />';
        $svg .= '<rect x="' . $statx . '" y="' . $padtop . '" width="' . max(0, $inflx - $statx)
              . '" height="' . $plotheight . '" fill="rgba(245, 158, 11, 0.09)" />';
        if ($hascorrect) {
            $svg .= '<rect x="' . $inflx . '" y="' . $padtop . '" width="' . max(0, $endx - $inflx)
                  . '" height="' . $plotheight . '" fill="rgba(139, 92, 246, 0.09)" />';
        }

        // Phase divider lines.
        $svg .= '<line x1="' . $statx . '" y1="' . $padtop . '" x2="' . $statx . '" y2="' . ($padtop + $plotheight)
              . '" stroke="rgba(13,110,253,0.25)" stroke-dasharray="2,2" />';
        if ($hascorrect) {
            $svg .= '<line x1="' . $inflx . '" y1="' . $padtop . '" x2="' . $inflx . '" y2="' . ($padtop + $plotheight)
                  . '" stroke="rgba(139,92,246,0.25)" stroke-dasharray="2,2" />';
        }

        // Tramo 1: Estacionario (blue solid).
        $svg .= '<line x1="' . $startx . '" y1="' . $staty . '" x2="' . $statx . '" y2="' . $staty
              . '" stroke="#0d6efd" stroke-width="3" stroke-linecap="round" />';

        // Tramo 2: Inflacionario (amber solid).
        $svg .= '<line x1="' . $statx . '" y1="' . $staty . '" x2="' . $inflx . '" y2="' . $inflendy
              . '" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" />';

        // Tramo 3: Deflacionario real (purple solid, if correct answer).
        if ($hascorrect) {
            $deflendy = $gety($pmin);
            $svg .= '<line x1="' . $inflx . '" y1="' . $inflendy . '" x2="' . $endx . '" y2="' . $deflendy
                  . '" stroke="#8b5cf6" stroke-width="3" stroke-linecap="round" />';
        }

        // Hypothetical dotted deflation projection (always shown).
        $projinflend = $hascorrect ? $dateanswercorrect : $inflend;
        $projinflx   = $getx($projinflend);
        $projinflscore = scoring_calculator::calculate_points(
            $projinflend, $datestart, $dateend, $tinitial, null, $pinit, $pmax, $pmin
        );
        $projinfly = $gety($projinflscore);
        $projendy  = $gety($pmin);
        $projopacity = $hascorrect ? '0.35' : '0.6';
        $projwidth   = $hascorrect ? '1.5' : '2';
        $svg .= '<line x1="' . $projinflx . '" y1="' . $projinfly . '" x2="' . $endx . '" y2="' . $projendy
              . '" stroke="#8b5cf6" stroke-width="' . $projwidth . '" stroke-dasharray="4,3"'
              . ' opacity="' . $projopacity . '" />';

        // Transition dots.
        $svg .= '<circle cx="' . $statx . '" cy="' . $staty . '" r="3.5" fill="#0d6efd" stroke="#fff" stroke-width="1.5" />';
        if ($hascorrect) {
            $svg .= '<circle cx="' . $inflx . '" cy="' . $inflendy . '" r="4" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />';
        }

        // Current-time vertical marker.
        if ($timenow >= $datestart && $timenow <= $dateend) {
            $nowx = $getx($timenow);
            $nowscore = scoring_calculator::calculate_points(
                $timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $pinit, $pmax, $pmin
            );
            $nowy = $gety($nowscore);

            $svg .= '<line x1="' . $nowx . '" y1="' . $padtop . '" x2="' . $nowx . '" y2="' . ($padtop + $plotheight)
                  . '" stroke="#198754" stroke-width="1.5" stroke-dasharray="3,2" />';
            $svg .= '<circle cx="' . $nowx . '" cy="' . $nowy . '" r="4" fill="#198754" stroke="#fff" stroke-width="1.5" />';
        }

        $svg .= '</svg>';
        return $svg;
    }
}
