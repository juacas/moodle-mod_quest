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
 * Tests return URLs used by the question composer.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_composer_url_test extends advanced_testcase {
    /**
     * An empty composer URL can be built before any question is selected.
     */
    public function test_empty_creation_url(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $url = quest_question_composer_url(55, 'addqchallenge', [], 0, true);
        parse_str((string)parse_url($url->out(false), PHP_URL_QUERY), $params);

        $this->assertSame('55', $params['id']);
        $this->assertSame('addqchallenge', $params['action']);
        $this->assertSame(sesskey(), $params['sesskey']);
        $this->assertArrayNotHasKey('questionids', $params);
    }

    /**
     * Several question IDs survive a URL round trip as an ordered array.
     */
    public function test_creation_url_with_multiple_questions(): void {
        require_once(__DIR__ . '/../locallib.php');

        $url = quest_question_composer_url(55, 'addqchallenge', [14155, 4409]);
        parse_str((string)parse_url($url->out(false), PHP_URL_QUERY), $params);

        $this->assertSame(['14155', '4409'], $params['questionids']);
        $this->assertSame('addqchallenge', $params['action']);
    }

    /**
     * Editing retains both the challenge ID and its selected questions.
     */
    public function test_edit_url_with_question_ids(): void {
        require_once(__DIR__ . '/../locallib.php');

        $url = quest_question_composer_url(55, 'modif', [14686, 12229], 366);
        parse_str((string)parse_url($url->out(false), PHP_URL_QUERY), $params);

        $this->assertSame('366', $params['sid']);
        $this->assertSame('modif', $params['action']);
        $this->assertSame(['14686', '12229'], $params['questionids']);
    }
}
