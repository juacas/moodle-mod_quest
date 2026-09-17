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

    protected stdClass $quest;
    protected stdClass $course;
    protected object $cm;
    protected array $challenges;
    protected bool $canaddchallenge;
    protected array $summarydata;
    protected string $detailtablehtml;
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

            $phaseclass = 'bg-primary';
            $phasename = get_string('phase_stationary', 'quest');
            if ($phase === scoring_calculator::PHASE_INFLATION) {
                $phaseclass = 'bg-warning text-dark';
                $phasename = get_string('phase_inflation', 'quest');
            } elseif ($phase === scoring_calculator::PHASE_DEFLATION) {
                $phaseclass = 'bg-purple text-white';
                $phasename = get_string('phase_deflation', 'quest');
            } elseif ($phase === scoring_calculator::PHASE_ENDED) {
                $phaseclass = 'bg-secondary text-white';
                $phasename = get_string('closed', 'quest');
            }

            $timeleft = '';
            if ($timenow < $c->dateend) {
                $diff = $c->dateend - $timenow;
                $days = (int)($diff / 86400);
                $hours = (int)(($diff % 86400) / 3600);
                $timeleft = $days > 0 ? "{$days}d {$hours}h left" : "{$hours}h left";
            } else {
                $timeleft = get_string('closed', 'quest');
            }

            $challengesdata[] = [
                'id' => $c->id,
                'title' => format_string($c->title),
                'description' => file_rewrite_pluginfile_urls(format_text($c->description, FORMAT_HTML), 'pluginfile.php', $context->id, 'mod_quest', 'submission', $c->id),
                'descriptionexcerpt' => shorten_text(strip_tags($c->description), 120),
                'currentpoints' => round($curpoints, 1),
                'phaseclass' => $phaseclass,
                'phasename' => $phasename,
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
            'questname' => format_string($this->quest->name),
            'intro' => format_module_intro('quest', $this->quest, $this->cm->id),
            'introattachments' => $this->summarydata['introattachments'] ?? '',
            'hasintroattachments' => !empty($this->summarydata['introattachments']),
            'datestartstr' => userdate($this->quest->datestart, get_string('strftimedatetime', 'langconfig')),
            'dateendstr' => userdate($this->quest->dateend, get_string('strftimedatetime', 'langconfig')),
            'allowteams' => !empty($this->quest->allowteams),
            'canaddchallenge' => $this->canaddchallenge,
            'addchallengeurl' => (new moodle_url('/mod/quest/challenges.php', [
                'id' => $this->cm->id,
                'action' => 'submitchallenge',
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
        $padLeft = 10;
        $padRight = 10;
        $padTop = 18;   // Space for phase labels.
        $padBottom = 18; // Space for X-axis dates.
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;

        $ymax = max($pmax * 1.12, $pinit * 1.15, 10);
        $ymin = 0;

        $getx = function($t) use ($datestart, $dateend, $padLeft, $plotWidth) {
            $clamped = max($datestart, min($dateend, $t));
            return round($padLeft + (($clamped - $datestart) / ($dateend - $datestart)) * $plotWidth, 1);
        };

        $gety = function($p) use ($ymin, $ymax, $padTop, $plotHeight) {
            $clamped = max($ymin, min($ymax, $p));
            return round($padTop + $plotHeight - (($clamped - $ymin) / ($ymax - $ymin)) * $plotHeight, 1);
        };

        $statEnd = min($dateend, $datestart + $tinitial);
        $statX = $getx($statEnd);
        $hasCorrect = !empty($dateanswercorrect) && $dateanswercorrect > $datestart && $dateanswercorrect < $dateend;
        $inflEnd = $hasCorrect ? $dateanswercorrect : $dateend;
        $inflX = $getx($inflEnd);
        $endX = $getx($dateend);
        $startX = $getx($datestart);

        $statY = $gety($pinit);

        $inflScore = scoring_calculator::calculate_points(
            $inflEnd, $datestart, $dateend, $tinitial, $dateanswercorrect, $pinit, $pmax, $pmin
        );
        $inflEndY = $gety($inflScore);

        // SVG: width/height="100%" so CSS container controls the size. No fixed height.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"'
             . ' viewBox="0 0 ' . $width . ' ' . $height . '"'
             . ' class="quest-card-sparkline" preserveAspectRatio="xMidYMid meet"'
             . ' style="display:block; border-radius: 6px; background: rgba(0,0,0,0.02);">';

        // Phase background zones.
        $svg .= '<rect x="' . $startX . '" y="' . $padTop . '" width="' . max(0, $statX - $startX)
              . '" height="' . $plotHeight . '" fill="rgba(13, 110, 253, 0.08)" />';
        $svg .= '<rect x="' . $statX . '" y="' . $padTop . '" width="' . max(0, $inflX - $statX)
              . '" height="' . $plotHeight . '" fill="rgba(245, 158, 11, 0.09)" />';
        if ($hasCorrect) {
            $svg .= '<rect x="' . $inflX . '" y="' . $padTop . '" width="' . max(0, $endX - $inflX)
                  . '" height="' . $plotHeight . '" fill="rgba(139, 92, 246, 0.09)" />';
        }

        // Phase divider lines.
        $svg .= '<line x1="' . $statX . '" y1="' . $padTop . '" x2="' . $statX . '" y2="' . ($padTop + $plotHeight)
              . '" stroke="rgba(13,110,253,0.25)" stroke-dasharray="2,2" />';
        if ($hasCorrect) {
            $svg .= '<line x1="' . $inflX . '" y1="' . $padTop . '" x2="' . $inflX . '" y2="' . ($padTop + $plotHeight)
                  . '" stroke="rgba(139,92,246,0.25)" stroke-dasharray="2,2" />';
        }

        // Tramo 1: Estacionario (blue solid).
        $svg .= '<line x1="' . $startX . '" y1="' . $statY . '" x2="' . $statX . '" y2="' . $statY
              . '" stroke="#0d6efd" stroke-width="3" stroke-linecap="round" />';

        // Tramo 2: Inflacionario (amber solid).
        $svg .= '<line x1="' . $statX . '" y1="' . $statY . '" x2="' . $inflX . '" y2="' . $inflEndY
              . '" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" />';

        // Tramo 3: Deflacionario real (purple solid, if correct answer).
        if ($hasCorrect) {
            $deflEndY = $gety($pmin);
            $svg .= '<line x1="' . $inflX . '" y1="' . $inflEndY . '" x2="' . $endX . '" y2="' . $deflEndY
                  . '" stroke="#8b5cf6" stroke-width="3" stroke-linecap="round" />';
        }

        // Hypothetical dotted deflation projection (always shown).
        $projInflEnd = $hasCorrect ? $dateanswercorrect : $inflEnd;
        $projInflX   = $getx($projInflEnd);
        $projInflScore = scoring_calculator::calculate_points(
            $projInflEnd, $datestart, $dateend, $tinitial, null, $pinit, $pmax, $pmin
        );
        $projInflY = $gety($projInflScore);
        $projEndY  = $gety($pmin);
        $projOpacity = $hasCorrect ? '0.35' : '0.6';
        $projWidth   = $hasCorrect ? '1.5' : '2';
        $svg .= '<line x1="' . $projInflX . '" y1="' . $projInflY . '" x2="' . $endX . '" y2="' . $projEndY
              . '" stroke="#8b5cf6" stroke-width="' . $projWidth . '" stroke-dasharray="4,3"'
              . ' opacity="' . $projOpacity . '" />';

        // Transition dots.
        $svg .= '<circle cx="' . $statX . '" cy="' . $statY . '" r="3.5" fill="#0d6efd" stroke="#fff" stroke-width="1.5" />';
        if ($hasCorrect) {
            $svg .= '<circle cx="' . $inflX . '" cy="' . $inflEndY . '" r="4" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />';
        }

        // "Now" vertical marker.
        if ($timenow >= $datestart && $timenow <= $dateend) {
            $nowX = $getx($timenow);
            $nowScore = scoring_calculator::calculate_points(
                $timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $pinit, $pmax, $pmin
            );
            $nowY = $gety($nowScore);

            $svg .= '<line x1="' . $nowX . '" y1="' . $padTop . '" x2="' . $nowX . '" y2="' . ($padTop + $plotHeight)
                  . '" stroke="#198754" stroke-width="1.5" stroke-dasharray="3,2" />';
            $svg .= '<circle cx="' . $nowX . '" cy="' . $nowY . '" r="4" fill="#198754" stroke="#fff" stroke-width="1.5" />';
        }

        $svg .= '</svg>';
        return $svg;
    }
}
