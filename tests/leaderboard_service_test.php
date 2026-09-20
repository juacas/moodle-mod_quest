<?php
// This file is part of Questournament activity for Moodle - http://moodle.org/
//
// Questournament for Moodle is free software: you can redistribute it and/or modify
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
use mod_quest\service\leaderboard_service;

/**
 * Unit tests for leaderboard calculations and official ranks.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_quest\service\leaderboard_service
 */
final class leaderboard_service_test extends advanced_testcase {

    /**
     * Test answer points are calculated for both a single user and a list.
     */
    public function test_calculate_user_answer_score(): void {
        global $DB;

        $this->resetAfterTest(true);

        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();

        $this->insert_answer(42, $userone->id, 2, 80, 50);
        $this->insert_answer(42, $userone->id, 1, 100, 100);
        $this->insert_answer(42, $usertwo->id, 3, 50, 20);

        $this->assertEquals(40.0, leaderboard_service::calculate_user_answer_score(42, $userone->id));
        $this->assertEquals(50.0, leaderboard_service::calculate_user_answer_score(42, [
            $userone->id,
            $usertwo->id,
        ]));
        $this->assertEquals(0.0, leaderboard_service::calculate_user_answer_score(42, []));
        $this->assertSame(0, $DB->count_records('quest_assessments'));
    }

    /**
     * Test author points only include assessments for the user's submissions.
     */
    public function test_calculate_user_author_score(): void {
        global $DB;

        $this->resetAfterTest(true);

        $author = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $submissionid = $this->insert_submission(42, $author->id);
        $otherid = $this->insert_submission(42, $otheruser->id);

        $DB->insert_record('quest_assessments_autors', (object)[
            'questid' => 42,
            'submissionid' => $submissionid,
            'userid' => $otheruser->id,
            'points' => 12.5,
            'dateassessment' => time(),
            'pointsmax' => 100,
            'commentsforteacher' => '',
            'commentsteacher' => '',
        ]);
        $DB->insert_record('quest_assessments_autors', (object)[
            'questid' => 42,
            'submissionid' => $otherid,
            'userid' => $author->id,
            'points' => 99.0,
            'dateassessment' => time(),
            'pointsmax' => 100,
            'commentsforteacher' => '',
            'commentsteacher' => '',
        ]);

        $this->assertEquals(12.5, leaderboard_service::calculate_user_author_score(42, $author->id));
        $this->assertEquals(99.0, leaderboard_service::calculate_user_author_score(42, $otheruser->id));
    }

    /**
     * Test official rank stays attached to a user when display sorting changes.
     */
    public function test_individual_standings_preserve_official_rank(): void {
        global $DB;

        $this->resetAfterTest(true);

        $winner = $this->getDataGenerator()->create_user([
            'firstname' => 'Zoe',
            'lastname' => 'Winner',
        ]);
        $second = $this->getDataGenerator()->create_user([
            'firstname' => 'Amy',
            'lastname' => 'Second',
        ]);

        $DB->insert_record('quest_calification_users', (object)[
            'questid' => 42,
            'userid' => $winner->id,
            'points' => 100,
            'nanswers' => 1,
        ]);
        $DB->insert_record('quest_calification_users', (object)[
            'questid' => 42,
            'userid' => $second->id,
            'points' => 50,
            'nanswers' => 1,
        ]);

        $standings = leaderboard_service::get_individual_standings(42, 'lastname', 'ASC');

        $this->assertCount(2, $standings);
        $this->assertSame((int)$second->id, (int)$standings[0]->userid);
        $this->assertSame(2, (int)$standings[0]->rank);
        $this->assertSame((int)$winner->id, (int)$standings[1]->userid);
        $this->assertSame(1, (int)$standings[1]->rank);
    }

    /**
     * Insert the minimal answer record required by leaderboard queries.
     *
     * @param int $questid
     * @param int $userid
     * @param int $phase
     * @param float $grade
     * @param float $pointsmax
     * @return int
     */
    private function insert_answer(
        int $questid,
        int $userid,
        int $phase,
        float $grade,
        float $pointsmax
    ): int {
        global $DB;

        return $DB->insert_record('quest_answers', (object)[
            'questid' => $questid,
            'submissionid' => 1,
            'userid' => $userid,
            'title' => 'Answer',
            'description' => 'Description',
            'date' => time(),
            'pointsmax' => $pointsmax,
            'grade' => $grade,
            'phase' => $phase,
            'commentforteacher' => '',
        ]);
    }

    /**
     * Insert the minimal submission record required by author score queries.
     *
     * @param int $questid
     * @param int $userid
     * @return int
     */
    private function insert_submission(int $questid, int $userid): int {
        global $DB;

        return $DB->insert_record('quest_submissions', (object)[
            'questid' => $questid,
            'userid' => $userid,
            'title' => 'Challenge',
            'description' => 'Description',
        ]);
    }
}
