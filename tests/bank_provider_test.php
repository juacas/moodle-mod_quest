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
use mod_quest\question\bank_provider;

/**
 * Tests for question-bank discovery and validation.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bank_provider_test extends advanced_testcase {

    /**
     * The provider exposes the capabilities used for bank selection.
     */
    public function test_bank_capabilities_are_explicit(): void {
        $this->assertSame([
            'moodle/question:useall',
            'moodle/question:usemine',
        ], bank_provider::CAPS);
        $this->assertIsBool(bank_provider::has_bank_helper());
    }

    /**
     * A generated question can be read and validated through the provider.
     */
    public function test_get_and_require_question(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'name' => 'Provider question',
        ]);

        $metadata = bank_provider::get_question($question->id);
        $usable = bank_provider::require_question($question->id);

        $this->assertSame((int)$question->id, (int)$metadata->id);
        $this->assertSame((int)$question->id, (int)$usable->id);
        $this->assertNotEmpty($metadata->questionbankentryid);
        $this->assertSame('ready', $metadata->status);
        $this->assertTrue($DB->record_exists('question_versions', ['questionid' => $question->id]));
    }
}
