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
use mod_quest\service\autograde_service;

/**
 * Regression tests for restoring a question-engine grade after manual assessment.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autograde_restore_test extends advanced_testcase {

    /**
     * A manual override can be removed without deleting the submitted attempt.
     *
     * @param string $response Submitted answer.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('answer_cases')]
    public function test_restore_automatic_grade_removes_manual_assessment(string $response): void {
        global $DB;

        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $author = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quest', \context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id), 1);
        $quba->start_all_questions();
        $quba->process_all_actions(time(), $quba->prepare_simulated_post_data([$slot => ['answer' => $response]]));
        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
        $this->assertNotNull($quba->get_question_fraction($slot));
        $automaticgrade = round((float)$quba->get_question_fraction($slot) * 100, 2);
        $this->assertNotEquals(75.0, $automaticgrade);

        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $author->id,
            'title' => 'Automatic question',
            'description' => 'Choose an answer',
            'timecreated' => time(),
            'datestart' => time() - HOURSECS,
            'dateend' => time() + DAYSECS,
            'initialpoints' => 10,
            'pointsmax' => 10,
            'pointsmin' => 0,
            'state' => SUBMISSION_STATE_APROVED,
        ]);
        $answerid = $DB->insert_record('quest_answers', (object)[
            'questid' => $quest->id,
            'submissionid' => $submissionid,
            'userid' => $student->id,
            'title' => 'Submitted answer',
            'description' => '',
            'date' => time(),
            'pointsmax' => 10,
            'grade' => 75,
            'commentforteacher' => '',
            'phase' => ANSWER_PHASE_PASSED,
            'state' => ANSWER_STATE_EDITTED,
            'questionusageid' => $quba->get_id(),
        ]);
        $assessmentid = $DB->insert_record('quest_assessments', (object)[
            'questid' => $quest->id,
            'answerid' => $answerid,
            'teacherid' => 2,
            'pointsteacher' => 7.5,
            'commentsforteacher' => '',
            'commentsteacher' => 'Manual feedback',
            'phase' => ASSESSMENT_PHASE_APPROVED,
            'state' => ASSESSMENT_STATE_BY_TEACHER,
        ]);
        $DB->insert_record('quest_elements_assessments', (object)[
            'questid' => $quest->id,
            'assessmentid' => $assessmentid,
            'userid' => 2,
            'elementno' => 0,
            'answer' => 'Criterion feedback',
            'commentteacher' => '',
            'calification' => 75,
        ]);

        $answer = $DB->get_record('quest_answers', ['id' => $answerid], '*', MUST_EXIST);
        $assessment = $DB->get_record('quest_assessments', ['id' => $assessmentid], '*', MUST_EXIST);
        $this->assertEquals($automaticgrade, autograde_service::get_automatic_grade($answer));
        $restoredgrade = quest_restore_automatic_assessment($quest, $answer, $assessment);

        $restored = $DB->get_record('quest_answers', ['id' => $answerid], '*', MUST_EXIST);
        $this->assertEquals($automaticgrade, $restoredgrade);
        $this->assertEquals($automaticgrade, (float)$restored->grade);
        $this->assertEquals($automaticgrade >= 50 ? ANSWER_PHASE_PASSED : ANSWER_PHASE_GRADED, (int)$restored->phase);
        $this->assertFalse($DB->record_exists('quest_assessments', ['id' => $assessmentid]));
        $this->assertFalse($DB->record_exists('quest_elements_assessments', ['assessmentid' => $assessmentid]));
        $this->assertTrue($DB->record_exists('question_usages', ['id' => $quba->get_id()]));
        $submission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $this->assertEquals($automaticgrade >= 50 ? 1 : 0, (int)$submission->nanswerscorrect);
        $this->assertEquals($automaticgrade * (float)$restored->pointsmax / 100,
            (float)quest_calculate_user_score($quest->id, $student->id));
    }

    /**
     * Include passing and failing attempts so both answer phases are covered.
     *
     * @return array Test responses.
     */
    public static function answer_cases(): array {
        return [
            'correct' => ['frog'],
            'incorrect' => ['lizard'],
        ];
    }
}
