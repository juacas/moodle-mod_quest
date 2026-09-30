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

/**
 * Regression tests for challenge approval, comments, and manual grading.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class challenge_approval_display_test extends advanced_testcase {
    /**
     * Load legacy helpers used by the approval and assessment screens.
     */
    protected function setUp(): void {
        parent::setUp();
        require_once(__DIR__ . '/../locallib.php');
    }

    /**
     * Linked-question forms preserve the bank render and allow editing the challenge title.
     */
    public function test_linked_question_forms_omit_open_question_inputs(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $PAGE->set_url(new \moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id, 'action' => 'approve',
        ]));
        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Stored title',
            'description' => 'Stored question description',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
        ]);
        $submission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', 'frogtoad', [
            'category' => $category->id,
        ]);
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $question->id]);
        \mod_quest\question\question_reference_service::set_challenge_question(
            $context->id, $submissionid, (int)$entryid
        );
        $linkedquestion = \mod_quest\question\question_reference_service::get_question_for_challenge($submissionid);
        $descriptionoptions = ['context' => $context, 'maxfiles' => -1, 'maxbytes' => $course->maxbytes];
        $attachmentoptions = ['subdirs' => false, 'maxfiles' => 0, 'maxbytes' => $course->maxbytes];
        $form = new \quest_print_upload_form(null, [
            'submission' => $submission,
            'quest' => $quest,
            'cm' => $cm,
            'definitionoptions' => $descriptionoptions,
            'attachmentoptions' => $attachmentoptions,
            'action' => 'approve',
            'linkedquestion' => $linkedquestion,
        ]);
        $this->assertEquals($submissionid, $submission->id);

        ob_start();
        $form->display();
        $html = ob_get_clean();
        $this->assertStringNotContainsString('name="title"', $html);
        $this->assertStringNotContainsString('name="description_editor[text]"', $html);
        $this->assertStringContainsString('name="datestart', $html);

        $modifsubmission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $modifform = new \quest_print_upload_form(null, [
            'submission' => $modifsubmission,
            'quest' => $quest,
            'cm' => $cm,
            'definitionoptions' => $descriptionoptions,
            'attachmentoptions' => $attachmentoptions,
            'action' => 'modif',
            'linkedquestion' => $linkedquestion,
            'composerquestions' => array_map(static function($slot) {
                $slot->question->maxmark = (float)$slot->maxmark;
                return $slot->question;
            }, \mod_quest\question\question_reference_service::get_challenge_questions($submissionid)),
        ]);
        $this->assertEquals($submissionid, $modifsubmission->id);
        ob_start();
        $modifform->display();
        $modifhtml = ob_get_clean();
        $this->assertStringContainsString('name="title"', $modifhtml);
        $this->assertStringNotContainsString('name="description_editor[text]"', $modifhtml);
        $this->assertStringContainsString('name="datestart', $modifhtml);
        $this->assertStringContainsString('quest-question-composer-preview', $modifhtml);
        $this->assertStringContainsString('quest-question-composer-bank', $modifhtml);

        [$quba, $slot] = \mod_quest\service\autograde_service::get_or_create_challenge_preview_usage(
            $quest, $submission, $linkedquestion, $context
        );
        $preview = \mod_quest\service\autograde_service::render_question_preview($quba, $slot);
        $this->assertStringContainsString('Name an amphibian', $preview);

        $submitted = (object)[
            'id' => $submissionid,
            'title' => 'Forged title',
            'description' => 'Forged description',
            'descriptionformat' => FORMAT_PLAIN,
            'descriptiontrust' => 1,
        ];
        $restored = quest_preserve_linked_challenge_content($submitted, (int)$quest->id);
        $this->assertSame('Stored title', $restored->title);
        $this->assertSame('Stored question description', $restored->description);
        $this->assertEquals(FORMAT_HTML, $restored->descriptionformat);
        $this->assertEquals(0, $restored->descriptiontrust);
    }

    /**
     * Authors see private guidance, teachers see both comments, and other students see the tip.
     */
    public function test_challenge_comment_depends_on_viewer(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $author = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $challenge = (object)[
            'userid' => $author->id,
            'commentteacherauthor' => 'Private author guidance',
            'commentteacherpupil' => 'Public student tip',
        ];

        $authorhtml = quest_challenge_comment_html($challenge, $context, (int)$author->id);
        $this->assertStringContainsString('Private author guidance', $authorhtml);
        $this->assertStringNotContainsString('Public student tip', $authorhtml);

        $teacherhtml = quest_challenge_comment_html($challenge, $context, (int)$teacher->id);
        $this->assertStringContainsString('Private author guidance', $teacherhtml);
        $this->assertStringContainsString('Public student tip', $teacherhtml);

        $studenthtml = quest_challenge_comment_html($challenge, $context, (int)$student->id);
        $this->assertStringContainsString('Public student tip', $studenthtml);
        $this->assertStringContainsString('alert-info', $studenthtml);
        $this->assertStringNotContainsString('Private author guidance', $studenthtml);
    }

    /**
     * Author grading feedback is stored in the assessment, not the challenge record.
     */
    public function test_challenge_comment_includes_author_assessment_feedback(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $author = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $challengeid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $author->id,
            'title' => 'Assessed challenge',
            'description' => 'Challenge description',
            'commentteacherauthor' => '',
            'commentteacherpupil' => 'Tip for participants',
        ]);
        $DB->insert_record('quest_assessments_autors', (object)[
            'questid' => $quest->id,
            'submissionid' => $challengeid,
            'userid' => $teacher->id,
            'commentsforteacher' => '',
            'commentsteacher' => 'Feedback on author score',
        ]);
        $challenge = $DB->get_record('quest_submissions', ['id' => $challengeid], '*', MUST_EXIST);

        $authorhtml = quest_challenge_comment_html($challenge, $context, (int)$author->id);
        $this->assertStringContainsString('Feedback on author score', $authorhtml);
        $this->assertStringNotContainsString('Tip for participants', $authorhtml);

        $teacherhtml = quest_challenge_comment_html($challenge, $context, (int)$teacher->id);
        $this->assertStringContainsString('Feedback on author score', $teacherhtml);
        $this->assertStringContainsString('Tip for participants', $teacherhtml);

        $studenthtml = quest_challenge_comment_html($challenge, $context, (int)$student->id);
        $this->assertStringContainsString('Tip for participants', $studenthtml);
        $this->assertStringNotContainsString('Feedback on author score', $studenthtml);
    }

    /**
     * Only the actionable approval notice is shown when approval is pending.
     */
    public function test_phase_badge_is_suppressed_when_approval_notice_exists(): void {
        $pending = (object)['state' => SUBMISSION_STATE_APPROVAL_PENDING];
        $notice = [['label' => get_string('approvalpending', 'quest')]];
        $this->assertFalse(quest_should_show_challenge_phase_badge($pending, $notice, 'Different phase text'));
        $this->assertTrue(quest_should_show_challenge_phase_badge($pending, [], 'Different phase text'));
        $approved = (object)['state' => SUBMISSION_STATE_APROVED];
        $this->assertTrue(quest_should_show_challenge_phase_badge($approved, $notice, 'Different phase text'));
    }

    /**
     * Saving an essay assessment can call the restored grade calculator.
     */
    public function test_criterion_grade_calculator_is_available_and_saves_feedback(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Essay challenge',
            'description' => 'Explain',
        ]);
        $answer = (object)[
            'questid' => $quest->id,
            'submissionid' => $submissionid,
            'userid' => $USER->id,
            'title' => 'Essay answer',
            'description' => 'Response',
            'commentforteacher' => '',
        ];
        $answer->id = $DB->insert_record('quest_answers', $answer);
        $assessmentid = $DB->insert_record('quest_assessments', (object)[
            'questid' => $quest->id,
            'answerid' => $answer->id,
            'userid' => $USER->id,
            'commentsforteacher' => '',
            'commentsteacher' => '',
        ]);
        $DB->insert_record('quest_elements', (object)[
            'questid' => $quest->id,
            'submissionsid' => 0,
            'elementno' => 0,
            'description' => 'Quality',
            'maxscore' => 2,
            'weight' => 11,
        ]);

        $this->assertTrue(function_exists('quest_get_answer_grade'));
        $percent = quest_get_answer_grade($quest, $answer, [0 => 1], [0 => 'Useful feedback']);
        $this->assertEquals(0.5, $percent);
        $this->assertEquals('Useful feedback', $DB->get_field('quest_elements_assessments', 'answer', [
            'assessmentid' => $assessmentid,
            'elementno' => 0,
        ]));
    }
}
