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
use mod_quest\service\autograde_service;

/**
 * Unit tests for question bank integration and references.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_quest\question\question_reference_service::class)]
final class question_bank_test extends advanced_testcase {

    /**
     * Test constant definitions and question reference area identifiers.
     */
    public function test_reference_constants(): void {
        $this->assertEquals('mod_quest', question_reference_service::COMPONENT);
        $this->assertEquals('challenge_question', question_reference_service::QUESTIONAREA);
    }

    /**
     * References created by older versions must follow the latest question version.
     */
    public function test_reference_switches_from_fixed_version_to_latest(): void {
        global $DB;

        $this->resetAfterTest(true);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, [
            'category' => $category->id,
            'name' => 'Original version',
        ]);
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', [
            'questionid' => $question->id,
        ]);

        $challengeid = 987654;
        question_reference_service::set_challenge_question(
            \context_system::instance()->id,
            $challengeid,
            (int)$entryid,
            1
        );

        $questiongenerator->update_question($question, null, ['name' => 'Latest version']);
        question_reference_service::use_latest_version_for_challenge($challengeid);

        $reference = question_reference_service::get_challenge_question_reference($challengeid);
        $this->assertNotNull($reference);
        $this->assertNull($reference->version);

        $resolved = question_reference_service::get_question_for_challenge($challengeid);
        $this->assertNotNull($resolved);
        $this->assertSame('Latest version', $resolved->name);
    }

    /**
     * Question previews keep configured attachment controls visible but disabled.
     */
    public function test_question_preview_disables_attachment_controls(): void {
        global $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $PAGE->set_url('/mod/quest/challenges.php');

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('essay', 'editorfilepicker', [
            'category' => $category->id,
            'attachments' => 2,
            'attachmentsrequired' => 1,
        ]);

        $quba = \question_engine::make_questions_usage_by_activity(
            'mod_quest',
            \context_system::instance()
        );
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id), 1);
        $quba->start_question($slot, 1);
        \question_engine::save_questions_usage_by_activity($quba);

        $html = autograde_service::render_question_preview($quba, $slot);

        $this->assertStringContainsString('This question requires at least 1 attachment(s)', $html);
        $this->assertStringContainsString('fp-btn-add', $html);
        $this->assertStringContainsString('disabled="disabled"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }
}
