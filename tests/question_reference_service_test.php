<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_quest;

use advanced_testcase;
use mod_quest\question\question_reference_service;

/**
 * Tests for Quest question-reference lifecycle and version resolution.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_reference_service_test extends advanced_testcase {

    /**
     * Replacing a challenge link updates the existing row instead of creating
     * duplicate references.
     */
    public function test_set_challenge_question_updates_existing_reference(): void {
        global $DB;

        $this->resetAfterTest(true);
        $contextid = \context_system::instance()->id;
        $first = question_reference_service::set_challenge_question($contextid, 44, 100, 1);
        $second = question_reference_service::set_challenge_question($contextid, 44, 200, null);

        $this->assertSame((int)$first->id, (int)$second->id);
        $this->assertSame(1, $DB->count_records('question_references', [
            'component' => question_reference_service::COMPONENT,
            'questionarea' => question_reference_service::QUESTIONAREA,
            'itemid' => 44,
        ]));
        $this->assertSame(200, (int)$second->questionbankentryid);
        $this->assertNull($second->version);
    }

    /**
     * A challenge without a reference has no question to render.
     */
    public function test_missing_reference_returns_null(): void {
        $this->resetAfterTest(true);

        $this->assertNull(question_reference_service::get_challenge_question_reference(9999));
        $this->assertNull(question_reference_service::get_question_for_challenge(9999));
    }

    /**
     * Deleting a link is idempotent and does not affect unrelated references.
     */
    public function test_delete_challenge_reference_is_scoped(): void {
        $this->resetAfterTest(true);
        $contextid = \context_system::instance()->id;
        question_reference_service::set_challenge_question($contextid, 50, 500, null);
        question_reference_service::set_challenge_question($contextid, 51, 501, null);

        question_reference_service::delete_challenge_reference(50);
        question_reference_service::delete_challenge_reference(50);

        $this->assertNull(question_reference_service::get_challenge_question_reference(50));
        $this->assertNotNull(question_reference_service::get_challenge_question_reference(51));
    }
}
