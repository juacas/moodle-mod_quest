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
use stdClass;
use mod_quest\service\tournament_manager;

/**
 * Unit tests for the tournament_manager service.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_quest\service\tournament_manager
 */
final class tournament_manager_test extends advanced_testcase {

    /**
     * Test validation of user answer submissions.
     */
    public function test_can_submit_answer(): void {
        $timenow = time();

        $quest = new stdClass();
        $quest->id = 1;
        $quest->nmaxanswers = 2;

        $submission = new stdClass();
        $submission->id = 10;
        $submission->userid = 100; // Author is user 100.
        $submission->datestart = $timenow - 3600;
        $submission->dateend = $timenow + 3600;

        // Author cannot answer own challenge.
        [$allowedAuthor, $reasonAuthor] = tournament_manager::can_submit_answer($quest, $submission, 100);
        $this->assertFalse($allowedAuthor);

        // Another student can answer active challenge.
        [$allowedStudent, $reasonStudent] = tournament_manager::can_submit_answer($quest, $submission, 200);
        $this->assertTrue($allowedStudent);

        // Challenge not yet started.
        $futureSubmission = clone $submission;
        $futureSubmission->datestart = $timenow + 1000;
        $futureSubmission->dateend = $timenow + 5000;
        [$allowedFuture, ] = tournament_manager::can_submit_answer($quest, $futureSubmission, 200);
        $this->assertFalse($allowedFuture);

        // Challenge closed.
        $closedSubmission = clone $submission;
        $closedSubmission->datestart = $timenow - 5000;
        $closedSubmission->dateend = $timenow - 1000;
        [$allowedClosed, ] = tournament_manager::can_submit_answer($quest, $closedSubmission, 200);
        $this->assertFalse($allowedClosed);
    }
}
