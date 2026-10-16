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

/**
 * Tests submitted question ordering in the challenge composer.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quest_print_upload_form
 */
final class question_composer_submission_test extends advanced_testcase {
    /**
     * The generated form includes a registered copy of its two selected IDs.
     */
    public function test_two_questions_are_in_the_form(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $PAGE->set_url(new \moodle_url('/mod/quest/challenges.php', ['id' => $cm->id, 'action' => 'addqchallenge']));

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $first = $generator->create_question('essay', null, ['category' => $category->id]);
        $second = $generator->create_question('essay', null, ['category' => $category->id]);
        $form = new \quest_print_upload_form(null, [
            'submission' => (object)['id' => 0],
            'quest' => $quest,
            'cm' => $cm,
            'definitionoptions' => ['context' => $context, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes],
            'attachmentoptions' => ['subdirs' => false, 'maxfiles' => 0, 'maxbytes' => $course->maxbytes],
            'action' => 'submitchallenge',
            'composerquestions' => [$first, $second],
        ]);
        ob_start();
        $form->display();
        $html = ob_get_clean();

        $this->assertStringContainsString('name="composer_mode"', $html);
        $this->assertStringContainsString('name="composer_questionids"', $html);
        $this->assertStringContainsString('value="' . $first->id . ',' . $second->id . '"', $html);
        $this->assertSame(2, substr_count($html, 'name="question_order[]"'));
    }

    /**
     * A registered form field retains the selection if sortable inputs are absent.
     */
    public function test_registered_field_fallback(): void {
        require_once(__DIR__ . '/../locallib.php');
        $previouspost = $_POST;
        try {
            $_POST = ['composer_questionids' => '14231,14282'];
            $this->assertSame([14231, 14282], quest_get_submitted_question_order());

            $_POST['question_order'] = ['14282', '14231'];
            $this->assertSame([14282, 14231], quest_get_submitted_question_order());

            $_POST = [];
            $this->assertSame([], quest_get_submitted_question_order());
        } finally {
            $_POST = $previouspost;
        }
    }
}
