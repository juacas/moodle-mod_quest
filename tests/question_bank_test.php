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
use mod_quest\question\question_reference_service;

/**
 * Unit tests for question bank integration and references.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_quest\question\question_reference_service
 */
final class question_bank_test extends advanced_testcase {

    /**
     * Test constant definitions and question reference area identifiers.
     */
    public function test_reference_constants(): void {
        $this->assertEquals('mod_quest', question_reference_service::COMPONENT);
        $this->assertEquals('challenge_question', question_reference_service::QUESTIONAREA);
    }
}
