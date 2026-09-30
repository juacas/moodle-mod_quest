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

namespace mod_quest\backup;

use advanced_testcase;
use backup;
use backup_controller;
use backup_setting;
use context_module;
use question_bank;
use question_engine;
use restore_controller;
use mod_quest\question\question_reference_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Checks that Quest backups contain linked bank questions and their answer attempts.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_quest_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_quest_activity_structure_step::class)]
final class question_bank_restore_test extends advanced_testcase {
    /**
     * Same-course duplication keeps every composed question in order.
     */
    public function test_duplicate_keeps_two_question_slots_and_marks(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $context = context_module::instance($quest->cmid);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $first = $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'name' => 'First duplicate question',
        ]);
        $second = $generator->create_question('essay', null, [
            'category' => $category->id,
            'name' => 'Second duplicate question',
        ]);
        $entryids = $DB->get_records_list('question_versions', 'questionid', [$first->id, $second->id],
            '', 'questionid,questionbankentryid');
        $challengeid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Composed challenge to duplicate',
            'description' => 'Answer both questions',
            'timecreated' => time(),
            'datestart' => time() - HOURSECS,
            'dateend' => time() + DAYSECS,
            'perceiveddifficulty' => 2,
        ]);
        question_reference_service::replace_challenge_questions($context->id, $quest->id, $challengeid, [
            ['questionbankentryid' => (int)$entryids[$first->id]->questionbankentryid, 'maxmark' => 2],
            ['questionbankentryid' => (int)$entryids[$second->id]->questionbankentryid, 'maxmark' => 3],
        ]);

        $backup = new backup_controller(backup::TYPE_1ACTIVITY, $quest->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $restore = new restore_controller($backupid, $course->id, backup::INTERACTIVE_NO,
            backup::MODE_IMPORT, $USER->id, backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $restoredquest = $DB->get_record_select('quest', 'course = :course AND id <> :original', [
            'course' => $course->id,
            'original' => $quest->id,
        ], '*', MUST_EXIST);
        $restoredchallenge = $DB->get_record('quest_submissions', ['questid' => $restoredquest->id], '*', MUST_EXIST);
        $this->assertEquals(2, $restoredchallenge->perceiveddifficulty);
        $slots = question_reference_service::get_challenge_questions((int)$restoredchallenge->id);
        $this->assertCount(2, $slots);
        $this->assertEquals([1, 2], array_map(static fn($slot) => (int)$slot->slotnumber, $slots));
        $this->assertEquals([2.0, 3.0], array_map(static fn($slot) => (float)$slot->maxmark, $slots));
        $this->assertSame('First duplicate question', $slots[0]->question->name);
        $this->assertSame('Second duplicate question', $slots[1]->question->name);
        $restoredcm = get_coursemodule_from_instance('quest', $restoredquest->id, $course->id);
        foreach ($slots as $slot) {
            $this->assertSame(question_reference_service::SLOTQUESTIONAREA, $slot->reference->questionarea);
            $this->assertEquals(context_module::instance($restoredcm->id)->id, $slot->reference->usingcontextid);
        }
    }

    /**
     * Challenge links and their bank questions are kept even without user data.
     */
    public function test_linked_question_survives_restore_without_user_data(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $course->id,
            'name' => 'Quest without user data',
        ]);
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => context_module::instance($bank->cmid)->id]);
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('essay', null, ['category' => $category->id, 'name' => 'Essay for Quest']);
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $question->id]);
        $challengeid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Essay challenge',
            'description' => 'Describe the result',
            'timecreated' => time(),
            'datestart' => time() - HOURSECS,
            'dateend' => time() + DAYSECS,
            'initialpoints' => 10,
        ]);
        question_reference_service::set_challenge_question(
            context_module::instance($quest->cmid)->id, $challengeid, $entryid, 1);

        $backup = new backup_controller(backup::TYPE_1ACTIVITY, $quest->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $restore = new restore_controller($backupid, $targetcourse->id, backup::INTERACTIVE_NO,
            backup::MODE_IMPORT, $USER->id, backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($restore->execute_precheck());
        $this->assertTrue($DB->record_exists('backup_ids_temp', [
            'itemname' => 'question',
            'itemid' => $question->id,
        ]));
        $restore->execute_plan();
        $restore->destroy();

        $restoredquest = $DB->get_record('quest', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $restoredchallenge = $DB->get_record('quest_submissions', ['questid' => $restoredquest->id], '*', MUST_EXIST);
        $reference = question_reference_service::get_challenge_question_reference($restoredchallenge->id);
        $this->assertNotNull($reference);
        $slot = $DB->get_record('quest_challenge_questions', ['submissionid' => $restoredchallenge->id], '*', MUST_EXIST);
        $this->assertEquals($slot->id, $reference->itemid);
        $this->assertSame(question_reference_service::SLOTQUESTIONAREA, $reference->questionarea);
        $this->assertEquals(1, $reference->version);
        $this->assertSame('Essay for Quest',
            question_reference_service::get_question_for_challenge($restoredchallenge->id)->name);
        $this->assertEquals(0, $DB->count_records('quest_answers', ['questid' => $restoredquest->id]));
    }

    /**
     * Back up a challenge linked to an external bank and restore it in another course.
     */
    public function test_linked_question_and_answer_usage_survive_restore(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $course->id,
            'name' => 'Question bank backup',
        ]);
        $context = context_module::instance($quest->cmid);
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => context_module::instance($bank->cmid)->id]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $question = $questiongenerator->create_question('shortanswer', null, [
                'category' => $category->id,
                'name' => 'Linked Quest question',
            ]);
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $question->id]);
        $latestquestion = $questiongenerator->update_question($question, null, [
            'name' => 'Updated Quest question',
        ]);

        $challengeid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Question bank challenge',
            'description' => 'Answer the linked question',
            'timecreated' => time(),
            'datestart' => time() - HOURSECS,
            'dateend' => time() + DAYSECS,
            'initialpoints' => 10,
        ]);
        question_reference_service::set_challenge_question($context->id, $challengeid, $entryid);

        $usage = question_engine::make_questions_usage_by_activity('mod_quest', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $slot = $usage->add_question(question_bank::load_question($question->id), 1);
        $usage->start_question($slot, 1);
        question_engine::save_questions_usage_by_activity($usage);
        $oldusageid = $usage->get_id();

        $DB->insert_record('quest_answers', (object)[
            'questid' => $quest->id,
            'submissionid' => $challengeid,
            'userid' => $USER->id,
            'title' => 'Bank answer',
            'description' => '',
            'commentforteacher' => '',
            'date' => time(),
            'questionusageid' => $oldusageid,
        ]);

        $backup = new backup_controller(backup::TYPE_1ACTIVITY, $quest->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
        $backup->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $backup->get_plan()->get_setting('users')->set_value(true);
        $backup->get_plan()->get_setting('quest_' . $quest->cmid . '_userinfo')
            ->set_status(backup_setting::NOT_LOCKED);
        $backup->get_plan()->get_setting('quest_' . $quest->cmid . '_userinfo')->set_value(true);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $restore = new restore_controller($backupid, $targetcourse->id, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $USER->id, backup::TARGET_CURRENT_ADDING);
        $restore->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('users')->set_value(true);
        $restore->get_plan()->get_setting('quest_' . $quest->cmid . '_userinfo')
            ->set_status(backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('quest_' . $quest->cmid . '_userinfo')->set_value(true);
        $this->assertTrue($restore->execute_precheck());
        $this->assertTrue($DB->record_exists('backup_ids_temp', [
            'itemname' => 'question',
            'itemid' => $question->id,
        ]));
        $this->assertTrue($DB->record_exists('backup_ids_temp', [
            'itemname' => 'question',
            'itemid' => $latestquestion->id,
        ]));
        $restore->execute_plan();
        $restore->destroy();

        $restoredquest = $DB->get_record('quest', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $restoredcm = get_coursemodule_from_instance('quest', $restoredquest->id, $targetcourse->id);
        $restoredcontext = context_module::instance($restoredcm->id);
        $restoredchallenge = $DB->get_record('quest_submissions', ['questid' => $restoredquest->id], '*', MUST_EXIST);
        $reference = question_reference_service::get_challenge_question_reference($restoredchallenge->id);

        $this->assertNotNull($reference);
        $this->assertEquals($restoredcontext->id, $reference->usingcontextid);
        $slot = $DB->get_record('quest_challenge_questions', ['submissionid' => $restoredchallenge->id], '*', MUST_EXIST);
        $this->assertEquals($slot->id, $reference->itemid);
        $this->assertSame(question_reference_service::SLOTQUESTIONAREA, $reference->questionarea);
        $this->assertGreaterThan(0, $reference->questionbankentryid);
        $restoredquestion = question_reference_service::get_question_for_challenge($restoredchallenge->id);
        $this->assertNotNull($restoredquestion);
        $this->assertSame('Updated Quest question', $restoredquestion->name);

        $answer = $DB->get_record('quest_answers', ['submissionid' => $restoredchallenge->id], '*', MUST_EXIST);
        $this->assertGreaterThan(0, $answer->questionusageid);
        $this->assertNotEquals($oldusageid, $answer->questionusageid);
        $attempt = $DB->get_record('question_attempts', ['questionusageid' => $answer->questionusageid], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('question', ['id' => $attempt->questionid]),
            'Restored attempt question ID: ' . $attempt->questionid);
        $restoredusage = question_engine::load_questions_usage_by_activity($answer->questionusageid);
        $this->assertSame('Linked Quest question', $restoredusage->get_question(1)->name);
        $this->assertEquals($restoredcontext->id, $restoredusage->get_owning_context()->id);
    }
}
