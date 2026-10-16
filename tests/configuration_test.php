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
 * Basic activity configuration and course-module visibility tests.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class configuration_test extends advanced_testcase {
    /**
     * Creating and editing an activity persists its basic settings.
     */
    public function test_create_and_update_activity_settings(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $course->id,
            'name' => 'Initial Quest',
            'intro' => 'Initial introduction',
            'allowqbankquestions' => 1,
        ]);
        $stored = $DB->get_record('quest', ['id' => $quest->id], '*', MUST_EXIST);
        $this->assertSame('Initial Quest', $stored->name);
        $this->assertSame('Initial introduction', $stored->intro);
        $this->assertSame(1, (int)$stored->allowqbankquestions);
        $this->assertGreaterThan(0, (int)$stored->timemodified);

        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);
        $settings = clone $stored;
        $settings->instance = $stored->id;
        $settings->coursemodule = $cm->id;
        $settings->name = 'Updated Quest';
        $settings->allowqbankquestions = 0;
        $this->assertTrue(quest_update_instance($settings, null));

        $updated = $DB->get_record('quest', ['id' => $quest->id], '*', MUST_EXIST);
        $this->assertSame('Updated Quest', $updated->name);
        $this->assertSame(0, (int)$updated->allowqbankquestions);
        $this->assertGreaterThanOrEqual((int)$stored->timemodified, (int)$updated->timemodified);
    }

    /**
     * Core updates the activity timestamp when its visibility changes.
     */
    public function test_activity_visibility_can_be_changed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $course->id, false, MUST_EXIST);

        $this->assertTrue(set_coursemodule_visible($cm->id, 0));
        $this->assertEquals(0, $DB->get_field('course_modules', 'visible', ['id' => $cm->id]));
        $this->assertGreaterThan(0, (int)$DB->get_field('quest', 'timemodified', ['id' => $quest->id]));

        $this->assertTrue(set_coursemodule_visible($cm->id, 1));
        $this->assertEquals(1, $DB->get_field('course_modules', 'visible', ['id' => $cm->id]));
    }
}
