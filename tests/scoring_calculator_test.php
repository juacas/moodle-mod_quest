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
 * @covers     \mod_quest\service\scoring_calculator
 */
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
        $ptsWithAnswer = scoring_calculator::calculate_points(5500, $start, $end, $tinit, 3000, $initial, $max, $min);
        $this->assertEquals(5.0, $ptsWithAnswer);

        // Ended without any correct answer -> reaches max points.
        $ptsWithoutAnswer = scoring_calculator::calculate_points(5500, $start, $end, $tinit, null, $initial, $max, $min);
        $this->assertEquals(100.0, $ptsWithoutAnswer);

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
}
