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
use stdClass;

/**
 * Tests for the public procedural API of mod_quest.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends advanced_testcase {

    /**
     * Load the module callbacks before exercising global functions.
     */
    protected function setUp(): void {
        parent::setUp();
        require_once(__DIR__ . '/../lib.php');
    }

    /**
     * The module advertises the features it actually implements.
     */
    public function test_supports_declares_public_features(): void {
        $this->assertTrue(quest_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(quest_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(quest_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(quest_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(quest_supports(FEATURE_USES_QUESTIONS));
        $this->assertFalse(quest_supports(FEATURE_GROUPS));
        $this->assertNull(quest_supports('unknown_feature'));
    }

    /**
     * Challenge dates must remain inside a valid Quest window.
     */
    public function test_challenge_date_validation_and_legacy_wrapper(): void {
        $valid = (object)[
            'questdatestart' => 100,
            'questdateend' => 500,
            'datestart' => 150,
            'dateend' => 450,
        ];
        $this->assertTrue(quest_check_challenge_dates($valid));
        $this->assertTrue(quest_check_submission_dates($valid));

        $invalid = clone $valid;
        $invalid->datestart = 50;
        $this->assertFalse(quest_check_challenge_dates($invalid));

        $invalid = clone $valid;
        $invalid->dateend = 600;
        $this->assertFalse(quest_check_submission_dates($invalid));

        $invalid = clone $valid;
        $invalid->questdateend = 100;
        $this->assertFalse(quest_check_challenge_dates($invalid));
    }

    /**
     * Titles and descriptions are both required for open challenges.
     */
    public function test_challenge_text_validation_and_legacy_wrapper(): void {
        $valid = (object)['title' => 'A challenge', 'description' => 'Some work'];
        $this->assertTrue(quest_check_challenge_text($valid));
        $this->assertTrue(quest_check_submission_text($valid));

        foreach (['title', 'description'] as $missing) {
            $invalid = clone $valid;
            $invalid->{$missing} = '';
            $this->assertFalse(quest_check_challenge_text($invalid));
            $this->assertFalse(quest_check_submission_text($invalid));
        }
    }

    /**
     * Duration options are ordered and include the half-hour granularity used
     * by the activity editor.
     */
    public function test_duration_options_are_ordered(): void {
        $durations = quest_get_durations();
        $keys = array_map('intval', array_keys($durations));

        $this->assertSame($keys, array_values($keys));
        $this->assertSame($keys, array_values(array_unique($keys)));
        $this->assertSame($keys, $this->sorted_copy($keys));
        $this->assertArrayHasKey(30, $durations);
        $this->assertArrayHasKey(90, $durations);
        $this->assertArrayHasKey(1440, $durations);
    }

    /**
     * Status output is a Bootstrap badge with a readable semantic class.
     */
    public function test_answer_phase_is_rendered_as_badge(): void {
        $this->resetAfterTest(true);

        $html = quest_answer_phase((object)[
            'id' => 1,
            'phase' => ANSWER_PHASE_UNGRADED,
            'state' => 0,
        ], (object)[]);

        $this->assertStringContainsString('badge', $html);
        $this->assertStringContainsString('quest-answer-phase-badge', $html);
        $this->assertStringContainsString('bg-danger', $html);
    }

    /**
     * Stored Quest files are rendered with a safe pluginfile URL and filename.
     */
    public function test_attachment_renderer_lists_stored_files(): void {
        $this->resetAfterTest(true);
        $context = \context_system::instance();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_quest',
            'filearea' => 'introattachment',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'intro-notes.txt',
        ], 'Attachment contents');

        ob_start();
        quest_print_attachments($context, 'introattachment', false, 'timemodified');
        $html = ob_get_clean();

        $this->assertStringContainsString('intro-notes.txt', $html);
        $this->assertStringContainsString('/pluginfile.php/', $html);
        $this->assertStringContainsString('mod_quest/introattachment/0', $html);
    }

    /**
     * A user cannot submit the same challenge twice.
     */
    public function test_validate_user_answer_is_per_user(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => 10,
            'userid' => $otheruser->id,
            'title' => 'Challenge',
            'description' => 'Description',
        ]);
        $quest = (object)['id' => 10];
        $submission = (object)['id' => $submissionid];

        $this->assertTrue(quest_validate_user_answer($quest, $submission));
        $DB->insert_record('quest_answers', (object)[
            'questid' => 10,
            'submissionid' => $submissionid,
            'userid' => $user->id,
            'title' => 'Answer',
            'description' => 'Answer text',
            'date' => time(),
            'pointsmax' => 10,
            'grade' => 0,
            'commentforteacher' => '',
        ]);

        $this->assertFalse(quest_validate_user_answer($quest, $submission));
        $this->setUser($otheruser);
        $this->assertTrue(quest_validate_user_answer($quest, $submission));
    }

    /**
     * Answer ownership checks protect against cross-Quest identifiers.
     */
    public function test_require_answer_ownership_rejects_cross_quest_records(): void {
        global $DB;

        $this->resetAfterTest(true);
        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => 20,
            'userid' => 2,
            'title' => 'Challenge',
            'description' => 'Description',
        ]);
        $answer = (object)[
            'questid' => 20,
            'submissionid' => $submissionid,
        ];

        quest_require_answer_ownership($answer, 20);
        $this->expectException(\moodle_exception::class);
        quest_require_answer_ownership($answer, 21);
    }

    /**
     * Return a sorted copy without changing the tested array.
     *
     * @param int[] $values Values to sort.
     * @return int[]
     */
    private function sorted_copy(array $values): array {
        sort($values, SORT_NUMERIC);
        return $values;
    }
}
