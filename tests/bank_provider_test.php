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
#[\PHPUnit\Framework\Attributes\CoversClass(bank_provider::class)]
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

    /**
     * A question in this Quest activity's private category can be added to its challenge composer.
     */
    public function test_require_question_accepts_the_current_quest_local_category(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $context = \context_module::instance($quest->cmid);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('shortanswer', null, ['category' => $category->id]);

        $usable = bank_provider::require_question((int)$question->id, $context);

        $this->assertSame((int)$question->id, (int)$usable->id);
    }

    /**
     * Temporary bank permissions stay local and do not rewrite existing role overrides.
     */
    public function test_student_question_permissions_are_local_and_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $context = \context_module::instance($quest->cmid);
        $studentroles = get_archetype_roles('student');
        $studentrole = reset($studentroles);
        $capability = 'moodle/question:add';

        // Add a local deny and verify the helper leaves deliberate role settings intact.
        assign_capability('moodle/question:editmine', CAP_PREVENT, $studentrole->id, $context->id);
        bank_provider::ensure_student_question_capabilities($context);

        $this->assertSame(CAP_PREVENT, (int)$DB->get_field('role_capabilities', 'permission', [
            'contextid' => $context->id,
            'roleid' => $studentrole->id,
            'capability' => 'moodle/question:editmine',
        ]));

        $localallow = $DB->get_record('role_capabilities', [
            'contextid' => $context->id,
            'roleid' => $studentrole->id,
            'capability' => $capability,
        ], '*', MUST_EXIST);
        $this->assertSame(CAP_ALLOW, (int)$localallow->permission);

        // A second call must not rewrite an already granted permission (or produce another log event).
        $localallow->timemodified = 1;
        $DB->update_record('role_capabilities', $localallow);
        bank_provider::ensure_student_question_capabilities($context);
        $this->assertSame(1, (int)$DB->get_field('role_capabilities', 'timemodified', [
            'contextid' => $context->id,
            'roleid' => $studentrole->id,
            'capability' => $capability,
        ]));

        // Challenge authoring is a separate capability and is never granted by this helper.
        $this->assertFalse($DB->record_exists('role_capabilities', [
            'contextid' => $context->id,
            'roleid' => $studentrole->id,
            'capability' => 'mod/quest:addchallenge',
        ]));
    }
}
