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
 * Tests the combined site and activity restrictions on challenge creation.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(challenge_type_policy::class)]
final class challenge_type_policy_test extends advanced_testcase {
    /**
     * A site without the new setting keeps both creation routes available.
     */
    public function test_missing_site_setting_defaults_to_both_types(): void {
        $this->resetAfterTest();
        unset_config('allowedchallengetypes', 'quest');

        $this->assertSame(challenge_type_policy::BOTH, challenge_type_policy::global_mode());
        $this->assertSame([
            challenge_type_policy::SIMPLE_ONLY,
            challenge_type_policy::QUIZ_ONLY,
            challenge_type_policy::BOTH,
        ], array_keys(challenge_type_policy::mode_options(true)));
    }

    /**
     * Existing activities retain their historical simple-only and both-type values.
     */
    public function test_legacy_activity_values(): void {
        $this->resetAfterTest();
        set_config('allowedchallengetypes', challenge_type_policy::BOTH, 'quest');

        $simple = (object)['allowqbankquestions' => 0];
        $both = (object)['allowqbankquestions' => 1];
        $quiz = (object)['allowqbankquestions' => 2];

        $this->assertTrue(challenge_type_policy::allows_simple($simple, false));
        $this->assertFalse(challenge_type_policy::allows_question_bank($simple, false));
        $this->assertTrue(challenge_type_policy::allows_simple($both, false));
        $this->assertTrue(challenge_type_policy::allows_question_bank($both, false));
        $this->assertFalse(challenge_type_policy::allows_simple($quiz, false));
        $this->assertTrue(challenge_type_policy::allows_question_bank($quiz, false));
    }

    /**
     * The site setting also restricts teachers, independently of activity settings.
     */
    public function test_site_setting_restricts_all_users(): void {
        $this->resetAfterTest();
        $both = (object)['allowqbankquestions' => 1];

        set_config('allowedchallengetypes', challenge_type_policy::SIMPLE_ONLY, 'quest');
        $this->assertTrue(challenge_type_policy::allows_simple($both, true));
        $this->assertFalse(challenge_type_policy::allows_question_bank($both, true));
        $this->assertFalse(challenge_type_policy::allows_question_bank($both, false));
        $this->assertSame([challenge_type_policy::SIMPLE_ONLY],
            array_keys(challenge_type_policy::mode_options(true)));

        set_config('allowedchallengetypes', challenge_type_policy::QUIZ_ONLY, 'quest');
        $this->assertFalse(challenge_type_policy::allows_simple($both, true));
        $this->assertTrue(challenge_type_policy::allows_question_bank($both, true));
        $this->assertFalse(challenge_type_policy::allows_simple($both, false));
        $this->assertSame([challenge_type_policy::QUIZ_ONLY],
            array_keys(challenge_type_policy::mode_options(true)));
    }

    /**
     * A more restrictive activity value does not grant access denied by the site.
     */
    public function test_conflicting_restrictions_do_not_grant_access(): void {
        $this->resetAfterTest();
        set_config('allowedchallengetypes', challenge_type_policy::QUIZ_ONLY, 'quest');
        $simple = (object)['allowqbankquestions' => 0];

        $this->assertFalse(challenge_type_policy::allows_simple($simple, false));
        $this->assertFalse(challenge_type_policy::allows_question_bank($simple, false));
    }
}
