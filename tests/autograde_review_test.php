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
 * Check that automatic grading does not disclose the solution during a challenge.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(autograde_service::class)]
final class autograde_review_test extends advanced_testcase {
    /**
     * Choice-level feedback and general feedback must also stay hidden.
     */
    public function test_multichoice_feedback_is_not_released_early(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/mod/quest/answer.php'));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('multichoice', 'one_of_four', [
            'category' => $category->id,
        ]);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quest', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id), 1);
        $quba->start_all_questions();
        $quba->process_all_actions(time(), $quba->prepare_simulated_post_data([$slot => ['answer' => '1']]));
        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);

        $during = autograde_service::render_question($quba, $slot, true, false);
        $this->assertStringContainsString('Which is the oddest number?', $during);
        $this->assertStringNotContainsString('The oddest number is One.', $during);
        $this->assertStringNotContainsString('One is the oddest.', $during);

        $after = autograde_service::render_question($quba, $slot, true, true);
        $this->assertStringContainsString('The oddest number is One.', $after);
    }

    /**
     * The review shows the student's result, but releases the question solution only after closing.
     */
    public function test_solution_is_hidden_until_challenge_end(): void {
        global $DB, $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $PAGE->set_url(new \moodle_url('/mod/quest/answer.php', ['id' => $cm->id]));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', 'frogtoad', [
            'category' => $category->id,
        ]);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quest', \context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id), 100);
        $quba->start_all_questions();
        $quba->process_all_actions(time(), $quba->prepare_simulated_post_data([$slot => ['answer' => 'lizard']]));
        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);

        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Amphibian challenge',
            'description' => 'Name an amphibian',
            'timecreated' => time(),
            'datestart' => time() - HOURSECS,
            'dateend' => time() + DAYSECS,
            'pointsmax' => 100,
            'pointsmin' => 0,
            'initialpoints' => 10,
            'phase' => SUBMISSION_PHASE_ACTIVE,
            'state' => SUBMISSION_STATE_APROVED,
            'evaluated' => 1,
        ]);
        $answer = (object)[
            'questid' => $quest->id,
            'submissionid' => $submissionid,
            'userid' => $USER->id,
            'title' => 'Attempt',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
            'attachment' => '',
            'date' => time(),
            'pointsmax' => 100,
            'grade' => 0,
            'commentforteacher' => '',
            'commentsteacher' => 'Private teacher note',
            'phase' => ANSWER_PHASE_GRADED,
            'state' => ANSWER_STATE_EDITTED,
            'permitsubmit' => 0,
            'perceiveddifficulty' => -1,
            'questionusageid' => $quba->get_id(),
        ];
        $answer->id = $DB->insert_record('quest_answers', $answer);

        ob_start();
        quest_print_answer($quest, $answer);
        $during = ob_get_clean();
        $this->assertStringContainsString('lizard', $during);
        $this->assertStringContainsString('quest-autograde-result', $during);
        $this->assertStringContainsString('0.00%', $during);
        $this->assertStringContainsString('Private teacher note', $during);
        $this->assertStringNotContainsString('Generalfeedback: frog', $during);
        $this->assertStringNotContainsString('The correct answer is', $during);

        $DB->set_field('quest_submissions', 'dateend', time() - HOURSECS, ['id' => $submissionid]);
        ob_start();
        quest_print_answer($quest, $answer);
        $after = ob_get_clean();
        $this->assertStringContainsString('Generalfeedback: frog', $after);
    }
}
