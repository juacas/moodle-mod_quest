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

namespace mod_quest\service;

/**
 * Service to calculate dynamic challenge scores throughout tournament phases.
 *
 * Implements the stationary, inflationary, and deflationary scoring curves.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scoring_calculator {

    /** Challenge has not started yet. */
    public const PHASE_PENDING = 'pending';
    /** Challenge is in its stationary phase. */
    public const PHASE_STATIONARY = 'stationary';
    /** Challenge is gaining value. */
    public const PHASE_INFLATION = 'inflation';
    /** Challenge is losing value after a correct answer. */
    public const PHASE_DEFLATION = 'deflation';
    /** Challenge has ended. */
    public const PHASE_ENDED = 'ended';

    /**
     * Calculate score for a challenge at a given timestamp.
     *
     * @param int $timenow Current timestamp or evaluation time.
     * @param int $datestart Challenge start time.
     * @param int $dateend Challenge end time.
     * @param int $tinitial Duration of stationary phase in seconds.
     * @param int|null $dateanswercorrect Timestamp of first correct answer, or null/0 if none.
     * @param float $initialpoints Initial points during stationary phase.
     * @param float $pointsmax Maximum points during inflation.
     * @param float $pointsmin Minimum points at deflation end.
     * @return float Calculated score.
     */
    public static function calculate_points(
        int $timenow,
        int $datestart,
        int $dateend,
        int $tinitial,
        ?int $dateanswercorrect,
        float $initialpoints,
        float $pointsmax,
        float $pointsmin = 0.0
    ): float {
        if (empty($dateanswercorrect) || $dateanswercorrect <= 0) {
            $dateanswercorrect = PHP_INT_MAX;
        } else if ($dateanswercorrect < $datestart) {
            $dateanswercorrect = $datestart;
        }

        // Check if tournament or challenge ended.
        if ($timenow >= $dateend) {
            if ($dateanswercorrect <= $dateend) {
                return (float)$pointsmin;
            }
            return (float)$pointsmax;
        }

        // Deflation phase begins after the first correct answer.
        if ($timenow > $dateanswercorrect) {
            $pointscorrect = self::calculate_points(
                $dateanswercorrect,
                $datestart,
                $dateend,
                $tinitial,
                $dateanswercorrect,
                $initialpoints,
                $pointsmax,
                $pointsmin
            );
            $denom = $dateend - $dateanswercorrect;
            if ($denom <= 0) {
                return (float)$pointsmin;
            }
            $incline = ($pointscorrect - $pointsmin) / $denom;
            $points = $pointscorrect - ($incline * ($timenow - $dateanswercorrect));
            return max((float)$pointsmin, (float)$points);
        }

        // Before start or in stationary phase.
        if ($timenow < ($datestart + $tinitial)) {
            return (float)$initialpoints;
        }

        // Inflationary phase.
        $inflstart = $datestart + $tinitial;
        $inflduration = $dateend - $inflstart;
        if ($inflduration <= 0) {
            return (float)$pointsmax;
        }

        $dt = $timenow - $inflstart;
        $points = ($dt * ($pointsmax - $initialpoints) / $inflduration) + $initialpoints;

        return max((float)$pointsmin, min((float)$pointsmax, (float)$points));
    }

    /**
     * Determine the current phase of a challenge at a given timestamp.
     *
     * @param int $timenow
     * @param int $datestart
     * @param int $dateend
     * @param int $tinitial
     * @param int|null $dateanswercorrect
     * @return string One of PHASE_* constants.
     */
    public static function get_phase(
        int $timenow,
        int $datestart,
        int $dateend,
        int $tinitial,
        ?int $dateanswercorrect
    ): string {
        if ($timenow < $datestart) {
            return self::PHASE_PENDING;
        }
        if ($timenow >= $dateend) {
            return self::PHASE_ENDED;
        }
        if (!empty($dateanswercorrect) && $dateanswercorrect > 0 && $timenow > $dateanswercorrect) {
            return self::PHASE_DEFLATION;
        }
        if ($timenow < ($datestart + $tinitial)) {
            return self::PHASE_STATIONARY;
        }
        return self::PHASE_INFLATION;
    }

    /**
     * Generate curve data for chart rendering.
     *
     * Returns an array of timestamps, curve points, and phase transitions.
     *
     * @param int $datestart
     * @param int $dateend
     * @param int $tinitial
     * @param int|null $dateanswercorrect
     * @param int|null $datefirstanswer
     * @param float $initialpoints
     * @param float $pointsmax
     * @param float $pointsmin
     * @param int $timenow Current time.
     * @return array Data structured for JSON / JS charting.
     */
    public static function get_chart_data(
        int $datestart,
        int $dateend,
        int $tinitial,
        ?int $dateanswercorrect,
        ?int $datefirstanswer,
        float $initialpoints,
        float $pointsmax,
        float $pointsmin = 0.0,
        ?int $timenow = null
    ): array {
        if ($timenow === null) {
            $timenow = time();
        }

        $dates = [$datestart, $datestart + $tinitial, $dateend];
        $inflectiondates = [];

        if (!empty($datefirstanswer) && $datefirstanswer > $datestart && $datefirstanswer < $dateend) {
            $dates[] = $datefirstanswer;
            $inflectiondates[] = $datefirstanswer;
        }

        if (!empty($dateanswercorrect) && $dateanswercorrect > $datestart && $dateanswercorrect < $dateend) {
            $dates[] = $dateanswercorrect;
            $inflectiondates[] = $dateanswercorrect;
        }

        // Add today/now marker date if within range.
        if ($timenow > $datestart && $timenow < $dateend) {
            $dates[] = $timenow;
        }

        // Add intermediate sampling points for smooth curve.
        $totalduration = max(1, $dateend - $datestart);
        $steps = 40;
        $stepsize = (int)($totalduration / $steps);
        for ($i = 1; $i < $steps; $i++) {
            $dates[] = $datestart + ($i * $stepsize);
        }

        $dates = array_unique($dates);
        sort($dates);

        $actualcurve = [];
        $worstcasecurve = [];
        $hasinflection = count($inflectiondates) > 0;
        $firstinflection = $hasinflection ? min($inflectiondates) : 0;

        foreach ($dates as $d) {
            $pt = self::calculate_points(
                $d, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax, $pointsmin
            );
            $actualcurve[] = [
                'x' => $d,
                'y' => round($pt, 2),
            ];

            if ($hasinflection) {
                $worstpt = self::calculate_points(
                    $d, $datestart, $dateend, $tinitial, $firstinflection, $initialpoints, $pointsmax, $pointsmin
                );
                $worstcasecurve[] = [
                    'x' => $d,
                    'y' => round($worstpt, 2),
                ];
            }
        }

        $currentpoints = self::calculate_points(
            $timenow, $datestart, $dateend, $tinitial, $dateanswercorrect, $initialpoints, $pointsmax, $pointsmin
        );
        $currentphase = self::get_phase($timenow, $datestart, $dateend, $tinitial, $dateanswercorrect);

        return [
            'datestart' => $datestart,
            'dateend' => $dateend,
            'tinitial' => $tinitial,
            'stationary_end' => $datestart + $tinitial,
            'dateanswercorrect' => $dateanswercorrect,
            'datefirstanswer' => $datefirstanswer,
            'timenow' => $timenow,
            'initialpoints' => $initialpoints,
            'pointsmax' => $pointsmax,
            'pointsmin' => $pointsmin,
            'currentpoints' => round($currentpoints, 2),
            'currentphase' => $currentphase,
            'actualcurve' => $actualcurve,
            'worstcasecurve' => $worstcasecurve,
        ];
    }
}
