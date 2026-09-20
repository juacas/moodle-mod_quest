<?php
// This file is part of Questournament activity for Moodle - http://moodle.org/
//
// Questournament for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Questournament activity for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quest;

use advanced_testcase;

/**
 * Tests for Quest security helpers.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class security_test extends advanced_testcase {

    /**
     * New Quest passwords use a verifiable salted hash instead of MD5.
     */
    public function test_password_hash_is_secure_and_verifiable(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quest/lib.php');
        $password = 'a-quest-password';
        $hash = \quest_hash_password($password);

        $this->assertNotSame(md5($password), $hash);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrong-password', $hash));
    }
}
