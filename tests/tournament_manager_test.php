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
use mod_quest\service\tournament_manager;

/**
 * Unit tests for the tournament_manager service.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_quest\service\tournament_manager
 */
final class tournament_manager_test extends advanced_testcase {

    /**
     * Test validation of user answer submissions.
     */
    public function test_can_submit_answer(): void {
        $timenow = time();

        $quest = new stdClass();
        $quest->id = 1;
        $quest->nmaxanswers = 2;

        $submission = new stdClass();
        $submission->id = 10;
        $submission->userid = 100; // Author is user 100.
        $submission->datestart = $timenow - 3600;
        $submission->dateend = $timenow + 3600;

        // Author cannot answer own challenge.
        [$allowedauthor, $reasonauthor] = tournament_manager::can_submit_answer($quest, $submission, 100);
        $this->assertFalse($allowedauthor);

        // Another student can answer active challenge.
        [$allowedstudent, $reasonstudent] = tournament_manager::can_submit_answer($quest, $submission, 200);
        $this->assertTrue($allowedstudent);

        // Challenge not yet started.
        $futuresubmission = clone $submission;
        $futuresubmission->datestart = $timenow + 1000;
        $futuresubmission->dateend = $timenow + 5000;
        [$allowedfuture, ] = tournament_manager::can_submit_answer($quest, $futuresubmission, 200);
        $this->assertFalse($allowedfuture);

        // Challenge closed.
        $closedsubmission = clone $submission;
        $closedsubmission->datestart = $timenow - 5000;
        $closedsubmission->dateend = $timenow - 1000;
        [$allowedclosed, ] = tournament_manager::can_submit_answer($quest, $closedsubmission, 200);
        $this->assertFalse($allowedclosed);
    }

    /**
     * Test that the per-user answer limit is enforced.
     */
    public function test_can_submit_answer_respects_maximum(): void {
        global $DB;

        $this->resetAfterTest(true);

        $quest = new stdClass();
        $quest->nmaxanswers = 1;

        $submission = new stdClass();
        $submission->id = 10;
        $submission->userid = 100;
        $submission->datestart = time() - 3600;
        $submission->dateend = time() + 3600;

        $DB->insert_record('quest_answers', (object)[
            'questid' => 1,
            'submissionid' => $submission->id,
            'userid' => 200,
            'title' => 'Previous answer',
            'description' => 'Already submitted',
            'date' => time() - 60,
            'pointsmax' => 50,
            'grade' => 0,
            'commentforteacher' => '',
        ]);

        [$allowed, $reason] = tournament_manager::can_submit_answer($quest, $submission, 200);

        $this->assertFalse($allowed);
        $this->assertNotEmpty($reason);
    }

    /**
     * Test answer aggregation and selection of the earliest correct answer.
     */
    public function test_update_submission_counts(): void {
        global $DB;

        $this->resetAfterTest(true);

        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => 1,
            'userid' => 100,
            'title' => 'Challenge',
            'description' => 'Description',
            'dateend' => 5000,
            'datestart' => 1000,
        ]);

        $answerdata = [
            ['userid' => 201, 'date' => 150, 'grade' => 0, 'phase' => 0, 'pointsmax' => 20],
            ['userid' => 202, 'date' => 300, 'grade' => 49, 'phase' => 1, 'pointsmax' => 30],
            ['userid' => 203, 'date' => 200, 'grade' => 50, 'phase' => 2, 'pointsmax' => 40],
            ['userid' => 204, 'date' => 100, 'grade' => 80, 'phase' => 3, 'pointsmax' => 60],
        ];

        foreach ($answerdata as $data) {
            $DB->insert_record('quest_answers', (object)[
                'questid' => 1,
                'submissionid' => $submissionid,
                'userid' => $data['userid'],
                'title' => 'Answer',
                'description' => 'Description',
                'date' => $data['date'],
                'pointsmax' => $data['pointsmax'],
                'grade' => $data['grade'],
                'phase' => $data['phase'],
                'commentforteacher' => '',
            ]);
        }

        $updated = tournament_manager::update_submission_counts($submissionid);

        $this->assertSame(4, (int)$updated->nanswers);
        $this->assertSame(2, (int)$updated->nanswerscorrect);
        $this->assertSame(100, (int)$updated->dateanswercorrect);
        $this->assertEquals(60.0, (float)$updated->pointsanswercorrect);

        $stored = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $this->assertSame(2, (int)$stored->nanswerscorrect);
        $this->assertSame(100, (int)$stored->dateanswercorrect);
    }

    /**
     * Test recording an answer also creates the user's initial score record.
     */
    public function test_record_answer_updates_submission_and_user_scores(): void {
        global $DB;

        $this->resetAfterTest(true);

        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => 1,
            'userid' => 100,
            'title' => 'Challenge',
            'description' => 'Description',
            'dateend' => time() + 3600,
            'datestart' => time() - 3600,
            'initialpoints' => 25,
            'pointsmax' => 100,
            'pointsmin' => 0,
        ]);

        $quest = (object)[
            'id' => 1,
            'tinitial' => 0,
            'allowteams' => 0,
        ];
        $submission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);

        $answer = tournament_manager::record_answer(
            $quest,
            $submission,
            200,
            'Answer title',
            'Answer body',
            FORMAT_PLAIN,
            0.0,
            0
        );

        $this->assertGreaterThan(0, $answer->id);
        $this->assertSame($submissionid, (int)$answer->submissionid);
        $this->assertGreaterThanOrEqual(25.0, (float)$answer->pointsmax);

        $updatedsubmission = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $this->assertSame(1, (int)$updatedsubmission->nanswers);
        $this->assertSame(0, (int)$updatedsubmission->nanswerscorrect);

        $calification = $DB->get_record('quest_calification_users', [
            'questid' => 1,
            'userid' => 200,
        ], '*', MUST_EXIST);
        $this->assertSame(1, (int)$calification->nanswers);
        $this->assertEquals(0.0, (float)$calification->points);
    }
}
