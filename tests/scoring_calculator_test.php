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

namespace mod_quest;

use advanced_testcase;
use mod_quest\service\scoring_calculator;

/**
 * Unit tests for the scoring_calculator service.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_quest\service\scoring_calculator::class)]
final class scoring_calculator_test extends advanced_testcase {

    /**
     * Test stationary scoring phase (before inflation begins).
     */
    public function test_stationary_phase(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 500; // Stationary until 1500.
        $initial = 50.0;
        $max = 100.0;
        $min = 10.0;

        // At start time.
        $pts1 = scoring_calculator::calculate_points($start, $start, $end, $tinit, null, $initial, $max, $min);
        $this->assertEquals(50.0, $pts1);

        // Midway through stationary phase.
        $pts2 = scoring_calculator::calculate_points(1250, $start, $end, $tinit, null, $initial, $max, $min);
        $this->assertEquals(50.0, $pts2);

        // Right at the end of stationary phase boundary.
        $phase = scoring_calculator::get_phase(1250, $start, $end, $tinit, null);
        $this->assertEquals(scoring_calculator::PHASE_STATIONARY, $phase);
    }

    /**
     * Test phase transitions at their exact temporal boundaries.
     */
    public function test_phase_boundaries(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 500;

        $this->assertEquals(scoring_calculator::PHASE_PENDING, scoring_calculator::get_phase(
            $start - 1, $start, $end, $tinit, null
        ));
        $this->assertEquals(scoring_calculator::PHASE_STATIONARY, scoring_calculator::get_phase(
            $start, $start, $end, $tinit, null
        ));
        $this->assertEquals(scoring_calculator::PHASE_STATIONARY, scoring_calculator::get_phase(
            $start + $tinit - 1, $start, $end, $tinit, null
        ));
        $this->assertEquals(scoring_calculator::PHASE_INFLATION, scoring_calculator::get_phase(
            $start + $tinit, $start, $end, $tinit, null
        ));
        $this->assertEquals(scoring_calculator::PHASE_ENDED, scoring_calculator::get_phase(
            $end, $start, $end, $tinit, null
        ));
    }

    /**
     * Test inflationary phase (points increase over time until answered).
     */
    public function test_inflationary_phase(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 1000; // Inflation runs from 2000 to 5000 (duration 3000).
        $initial = 40.0;
        $max = 100.0;
        $min = 10.0;

        // Halfway through inflation (time 3500, dt = 1500 / 3000 = 0.5).
        // Points = 0.5 * (100 - 40) + 40 = 70.0.
        $pts = scoring_calculator::calculate_points(3500, $start, $end, $tinit, null, $initial, $max, $min);
        $this->assertEqualsWithDelta(70.0, $pts, 0.001);

        $phase = scoring_calculator::get_phase(3500, $start, $end, $tinit, null);
        $this->assertEquals(scoring_calculator::PHASE_INFLATION, $phase);
    }

    /**
     * Test deflationary phase (points decrease after first correct answer).
     */
    public function test_deflationary_phase(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 1000; // Inflation starts at 2000.
        $initial = 40.0;
        $max = 100.0;
        $min = 10.0;

        // First correct answer arrives at 3500 (score at that moment was 70.0).
        $correcttime = 3500;

        // Deflation period is from 3500 to 5000 (duration 1500).
        // At time 4250 (halfway through deflation, dt = 750 / 1500 = 0.5).
        // Incline = (70 - 10) / 1500 = 60 / 1500 = 0.04 pts/sec.
        // Points = 70 - (0.04 * 750) = 40.0.
        $pts = scoring_calculator::calculate_points(4250, $start, $end, $tinit, $correcttime, $initial, $max, $min);
        $this->assertEqualsWithDelta(40.0, $pts, 0.001);

        $phase = scoring_calculator::get_phase(4250, $start, $end, $tinit, $correcttime);
        $this->assertEquals(scoring_calculator::PHASE_DEFLATION, $phase);
    }

    /**
     * Test answers recorded before the challenge start are clamped safely.
     */
    public function test_answer_before_start_is_clamped(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 1000;
        $initial = 40.0;
        $max = 100.0;
        $min = 10.0;

        $points = scoring_calculator::calculate_points(
            2000, $start, $end, $tinit, 500, $initial, $max, $min
        );

        // The answer timestamp is normalised to the challenge start.
        $this->assertEqualsWithDelta(32.5, $points, 0.001);
        $this->assertEquals(
            scoring_calculator::PHASE_DEFLATION,
            scoring_calculator::get_phase(2000, $start, $end, $tinit, 500)
        );
    }

    /**
     * Test challenge conclusion at or after end date.
     */
    public function test_ended_phase(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 500;
        $initial = 30.0;
        $max = 100.0;
        $min = 5.0;

        // Ended with a correct answer within duration -> reaches min points.
        $ptswithanswer = scoring_calculator::calculate_points(5500, $start, $end, $tinit, 3000, $initial, $max, $min);
        $this->assertEquals(5.0, $ptswithanswer);

        // Ended without any correct answer -> reaches max points.
        $ptswithoutanswer = scoring_calculator::calculate_points(5500, $start, $end, $tinit, null, $initial, $max, $min);
        $this->assertEquals(100.0, $ptswithoutanswer);

        $phase = scoring_calculator::get_phase(5500, $start, $end, $tinit, null);
        $this->assertEquals(scoring_calculator::PHASE_ENDED, $phase);
    }

    /**
     * Test edge case where min and max points are equal.
     */
    public function test_equal_min_max_points(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 500;
        $points = 50.0;

        $pts = scoring_calculator::calculate_points(2500, $start, $end, $tinit, null, $points, $points, $points);
        $this->assertEquals(50.0, $pts);
    }

    /**
     * Test get_chart_data returns valid trajectory structure.
     */
    public function test_chart_data_generation(): void {
        $start = 1000;
        $end = 5000;
        $tinit = 500;
        $initial = 20.0;
        $max = 100.0;
        $min = 5.0;
        $timenow = 2500;

        $data = scoring_calculator::get_chart_data(
            $start, $end, $tinit, 2000, 1800, $initial, $max, $min, $timenow
        );

        $this->assertIsArray($data);
        $this->assertEquals($start, $data['datestart']);
        $this->assertEquals($end, $data['dateend']);
        $this->assertGreaterThan(0, count($data['actualcurve']));
        $this->assertNotEmpty($data['currentpoints']);
        $this->assertEquals(scoring_calculator::PHASE_DEFLATION, $data['currentphase']);
    }

    /**
     * Test chart data without an answer or inflection point.
     */
    public function test_chart_data_without_inflection(): void {
        $data = scoring_calculator::get_chart_data(
            1000,
            5000,
            500,
            null,
            null,
            20.0,
            100.0,
            5.0,
            1000
        );

        $timestamps = array_column($data['actualcurve'], 'x');

        $this->assertSame([], $data['worstcasecurve']);
        $this->assertContains(1000, $timestamps);
        $this->assertContains(1500, $timestamps);
        $this->assertContains(5000, $timestamps);
        $this->assertSame($timestamps, array_values(array_unique($timestamps)));
        $this->assertEquals(scoring_calculator::PHASE_STATIONARY, $data['currentphase']);
        $this->assertEquals(20.0, $data['currentpoints']);
    }
}
