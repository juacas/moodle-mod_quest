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
     * Saving a legacy challenge must not revalidate its existing activity-context question as a bank.
     */
    public function test_unchanged_legacy_composition_is_not_revalidated_or_rewritten(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $context = \context_module::instance($quest->cmid);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('shortanswer', null, ['category' => $category->id]);
        $entryid = (int)$DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $question->id]);

        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Legacy question challenge',
            'description' => '',
            'timecreated' => time(),
            'datestart' => time(),
            'dateend' => time() + DAYSECS,
            'pointsmax' => 1,
        ]);
        question_reference_service::set_challenge_question($context->id, $submissionid, $entryid, null);
        $existingquestions = question_reference_service::get_challenge_questions($submissionid);

        $questionitems = \quest_get_submitted_question_composition(
            $existingquestions,
            [(int)$question->id],
            [(int)$question->id => 1]
        );

        $this->assertSame([], $questionitems);
        $this->assertSame(1, $DB->count_records('question_references', [
            'component' => question_reference_service::COMPONENT,
            'questionarea' => question_reference_service::QUESTIONAREA,
            'itemid' => $submissionid,
        ]));
        $this->assertSame(0, $DB->count_records('quest_challenge_questions', ['submissionid' => $submissionid]));
    }

    /**
     * The composer accepts questions which need the challenge rubric for grading.
     */
    public function test_composer_accepts_an_essay_question(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $essay = $generator->create_question('essay', null, ['category' => $category->id]);
        $entryid = (int)$DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $essay->id]);

        $items = \quest_get_submitted_question_composition([], [(int)$essay->id], [(int)$essay->id => 3]);

        $this->assertCount(1, $items);
        $this->assertSame($entryid, $items[0]['questionbankentryid']);
        $this->assertSame(3.0, $items[0]['maxmark']);
    }

    /**
     * A composed challenge stores its editor text after the mandatory insert.
     */
    public function test_composed_challenge_saves_description_and_question(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('essay', null, ['category' => $category->id]);
        $questionitems = \quest_get_submitted_question_composition(
            [], [(int)$question->id], [(int)$question->id => 1], $context
        );

        $submitted = (object)[
            'sid' => 0,
            'title' => 'Composed challenge',
            'description_editor' => [
                'text' => '<p>Answer all questions</p>',
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
            'datestart' => time(),
            'dateend' => time() + DAYSECS,
            'pointsmax' => 80,
            'pointsmin' => 0,
            'initialpoints' => 10,
            'commentteacherpupil' => '',
            'perceiveddifficulty' => 0,
            'predictedduration' => -1,
        ];
        $descriptionoptions = ['context' => $context, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes];
        $attachmentoptions = ['context' => $context, 'maxfiles' => 0, 'maxbytes' => $course->maxbytes];

        try {
            \quest_upload_challenge($quest, $submitted, true, $cm, $descriptionoptions,
                $attachmentoptions, $context, 'submitchallenge', $USER->id, false, $questionitems);
            $this->fail('Expected the CLI redirect after saving the challenge.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
        }

        $saved = $DB->get_record('quest_submissions', ['questid' => $quest->id, 'title' => 'Composed challenge'],
            '*', MUST_EXIST);
        $this->assertSame('<p>Answer all questions</p>', $saved->description);
        $this->assertEquals(FORMAT_HTML, $saved->descriptionformat);
        $this->assertCount(1, question_reference_service::get_challenge_questions((int)$saved->id));
    }

    /**
     * The rubric grades only manual slots, weighted against the automatic slots.
     */
    public function test_mixed_question_grades_are_weighted_by_composer_marks(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $automatic = $generator->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $essay = $generator->create_question('essay', null, ['category' => $category->id]);

        $quba = \question_engine::make_questions_usage_by_activity('mod_quest', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $automaticslot = $quba->add_question(\question_bank::load_question($automatic->id), 2);
        $quba->add_question(\question_bank::load_question($essay->id), 3);
        $quba->start_all_questions();
        $quba->process_all_actions(time(), $quba->prepare_simulated_post_data([
            $automaticslot => ['answer' => 'frog'],
        ]));
        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);

        $answer = (object)['questionusageid' => $quba->get_id()];
        $this->assertEquals(1.0, $quba->get_question_fraction($automaticslot));
        $this->assertNull(autograde_service::get_automatic_grade($answer));
        $this->assertEqualsWithDelta(0.7, autograde_service::combine_with_rubric_grade($answer, 0.5), 0.0001);
        $this->assertEqualsWithDelta(0.4, autograde_service::combine_with_rubric_grade($answer, 0), 0.0001);

        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $this->getDataGenerator()->create_course()->id,
        ]);
        $challengeid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'title' => 'Mixed challenge',
        ]);
        $automaticentry = (int)$DB->get_field('question_versions', 'questionbankentryid', [
            'questionid' => $automatic->id,
        ]);
        $essayentry = (int)$DB->get_field('question_versions', 'questionbankentryid', [
            'questionid' => $essay->id,
        ]);
        $context = \context_module::instance($quest->cmid);
        question_reference_service::replace_challenge_questions($context->id, $quest->id, $challengeid, [
            ['questionbankentryid' => $automaticentry, 'maxmark' => 2],
            ['questionbankentryid' => $essayentry, 'maxmark' => 3],
        ]);
        $this->assertFalse(autograde_service::is_fully_automatic_challenge($challengeid));
        question_reference_service::replace_challenge_questions($context->id, $quest->id, $challengeid, [
            ['questionbankentryid' => $automaticentry, 'maxmark' => 2],
        ]);
        $this->assertTrue(autograde_service::is_fully_automatic_challenge($challengeid));
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

    /**
     * Composer images must use a preview usage that can serve question files.
     */
    public function test_composer_embedded_image_uses_question_context_and_preview_usage(): void {
        global $DB, $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $PAGE->set_url(new \moodle_url('/mod/quest/challenges.php', ['id' => $cm->id, 'action' => 'addqchallenge']));

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $question = $generator->create_question('essay', 'files', ['category' => $category->id]);
        $file = get_file_storage()->get_file($context->id, 'question', 'questiontext', $question->id, '/', '1.png');
        $this->assertNotFalse($file);

        $form = new \quest_print_upload_form(null, [
            'submission' => (object)['id' => 0],
            'quest' => $quest,
            'cm' => $cm,
            'definitionoptions' => ['context' => $context, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes],
            'attachmentoptions' => ['subdirs' => false, 'maxfiles' => 0, 'maxbytes' => $course->maxbytes],
            'action' => 'addqchallenge',
            'composerquestions' => [(object)['id' => $question->id, 'maxmark' => 1]],
        ]);
        ob_start();
        $form->display();
        $html = ob_get_clean();

        $this->assertMatchesRegularExpression(
            '~/' . $context->id . '/question/questiontext/(\d+)/(\d+)/' . $question->id . '/1\.png~',
            $html
        );
        preg_match('~/' . $context->id . '/question/questiontext/(\d+)/(\d+)/' . $question->id . '/1\.png~',
            $html, $matches);
        $this->assertSame('core_question_preview', $DB->get_field('question_usages', 'component', [
            'id' => (int)$matches[1],
        ]));
    }

    /**
     * The challenge and author-assessment screens share one multi-question panel.
     */
    public function test_challenge_question_panel_renders_all_slots_without_edit_links(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $PAGE->set_url(new \moodle_url('/mod/quest/assess_autors.php', ['id' => $cm->id]));

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $first = $generator->create_question('shortanswer', 'frogtoad', [
            'category' => $category->id,
            'name' => 'First panel question',
        ]);
        $second = $generator->create_question('essay', null, [
            'category' => $category->id,
            'name' => 'Second panel question',
        ]);
        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Two question challenge',
            'description' => '',
        ]);
        $entryids = $DB->get_records_list('question_versions', 'questionid', [$first->id, $second->id],
            '', 'questionid,questionbankentryid');
        question_reference_service::replace_challenge_questions($context->id, $quest->id, $submissionid, [
            ['questionbankentryid' => (int)$entryids[$first->id]->questionbankentryid, 'maxmark' => 2],
            ['questionbankentryid' => (int)$entryids[$second->id]->questionbankentryid, 'maxmark' => 3],
        ]);
        $submission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);

        $html = autograde_service::render_challenge_questions_preview($quest, $submission, $context, $cm->id, true);

        $this->assertStringContainsString('id="quest-qpreview-panel"', $html);
        $this->assertStringContainsString('First panel question', $html);
        $this->assertStringContainsString('Second panel question', $html);
        $this->assertSame(2, substr_count($html, 'quest-question-preview-item'));
        $this->assertStringNotContainsString('editquestion/question.php', $html);
    }

    /**
     * Allowing a resubmission creates a new attempt and keeps the old one.
     */
    public function test_resubmission_starts_new_attempt_without_deleting_history(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $context = \context_system::instance();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, [
            'category' => $category->id,
            'name' => 'Resubmission question',
        ]);
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', [
            'questionid' => $question->id,
        ]);

        $quest = (object)[
            'id' => 1,
            'tinitial' => 0,
            'maxcalification' => 100,
            'mincalification' => 0,
        ];
        $submission = (object)[
            'id' => $DB->insert_record('quest_submissions', (object)[
                'questid' => $quest->id,
                'userid' => $USER->id,
                'title' => 'Resubmission challenge',
                'description' => 'Challenge description',
                'timecreated' => time(),
                'datestart' => time() - HOURSECS,
                'dateend' => time() + DAYSECS,
                'pointsmax' => 100,
                'pointsmin' => 0,
                'initialpoints' => 10,
                'phase' => SUBMISSION_PHASE_ACTIVE,
                'state' => SUBMISSION_STATE_APROVED,
                'evaluated' => 1,
            ]),
            'pointsmax' => 100,
            'pointsmin' => 0,
            'initialpoints' => 10,
            'datestart' => time() - HOURSECS,
            'dateend' => time() + DAYSECS,
        ];
        question_reference_service::set_challenge_question(
            $context->id,
            $submission->id,
            (int)$entryid,
            null
        );

        $oldquba = \question_engine::make_questions_usage_by_activity('mod_quest', $context);
        $oldquba->set_preferred_behaviour('deferredfeedback');
        $oldslot = $oldquba->add_question(\question_bank::load_question($question->id), 100);
        $oldquba->start_question($oldslot, 1);
        \question_engine::save_questions_usage_by_activity($oldquba);
        $oldusageid = $oldquba->get_id();

        $DB->insert_record('quest_answers', (object)[
            'questid' => $quest->id,
            'submissionid' => $submission->id,
            'userid' => $USER->id,
            'title' => 'Previous attempt',
            'description' => 'Previous response',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
            'attachment' => '',
            'date' => time(),
            'pointsmax' => 100,
            'grade' => 50,
            'commentforteacher' => '',
            'phase' => ANSWER_PHASE_GRADED,
            'state' => ANSWER_STATE_EDITTED,
            'permitsubmit' => ANSWER_PERMITSUBMIT_EDITABLE,
            'perceiveddifficulty' => -1,
            'questionusageid' => $oldusageid,
        ]);

        [$newquba, $newslot] = autograde_service::get_or_create_attempt(
            $quest,
            $submission,
            $USER->id,
            $context
        );

        $this->assertNotSame((int)$oldusageid, (int)$newquba->get_id());
        $this->assertTrue($DB->record_exists('question_usages', ['id' => $oldusageid]));
        $this->assertSame(1, $newslot);
        $this->assertSame(2, $DB->count_records('quest_answers', ['submissionid' => $submission->id]));
    }
}
