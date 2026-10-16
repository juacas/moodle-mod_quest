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
 * Tests the version selector used by the challenge question composer.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quest_get_question_versions
 */
final class question_composer_versions_test extends advanced_testcase {
    /**
     * Duplicate version numbers must not overwrite records or create duplicate options.
     */
    public function test_duplicate_version_numbers(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('essay', null, ['category' => $category->id]);
        $original = $DB->get_record('question_versions', ['questionid' => $question->id], '*', MUST_EXIST);

        $ready = clone $original;
        unset($ready->id);
        $ready->version = 3;
        $ready->status = 'ready';
        $DB->insert_record('question_versions', $ready);

        // Moodle 5.x prevents duplicate versions at the database level; Moodle 4.5 does not.
        $uniqueindex = new \xmldb_index(
            'questionbankentryid-version',
            XMLDB_INDEX_UNIQUE,
            ['questionbankentryid', 'version']
        );
        if (!$DB->get_manager()->index_exists(new \xmldb_table('question_versions'), $uniqueindex)) {
            $hidden = clone $ready;
            $hidden->status = 'hidden';
            $DB->insert_record('question_versions', $hidden);
        }

        $versions = quest_get_question_versions((int)$original->questionbankentryid);

        $this->assertSame([3, 1], array_keys($versions));
        $this->assertSame('ready', $versions[3]->status);
        $this->assertCount(2, $versions);
    }
}
