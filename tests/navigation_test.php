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

/**
 * Tests for activity, challenge, and answer breadcrumbs.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_test extends advanced_testcase {
    /**
     * An answer page links back through the challenge and Quest activity.
     */
    public function test_answer_breadcrumb_links_to_challenge_and_quest(): void {
        global $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quest = $this->getDataGenerator()->create_module('quest', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($quest->cmid);
        $challenge = (object)['id' => 321, 'title' => 'Example challenge'];
        $answer = (object)['id' => 456, 'title' => 'Example answer'];
        $PAGE->set_url(new \moodle_url('/mod/quest/answer.php', ['sid' => $challenge->id]));
        $PAGE->set_cm($cm, $course, $quest);

        \quest_add_breadcrumbs($cm, $challenge, $answer);

        $activitynode = $PAGE->navigation->find($cm->id, \navigation_node::TYPE_ACTIVITY);
        $questurl = new \moodle_url('/mod/quest/view.php', ['id' => $cm->id]);
        if ($activitynode && !$activitynode->mainnavonly) {
            $this->assertEquals($questurl, $activitynode->action);
        } else {
            $this->assertEquals($questurl, $PAGE->navbar->children[0]->action);
        }
        $challengeurl = new \moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id,
            'cid' => $challenge->id,
            'action' => 'showchallenge',
        ]);
        $challengenode = $PAGE->navbar->children[count($PAGE->navbar->children) - 2];
        $answernode = $PAGE->navbar->children[count($PAGE->navbar->children) - 1];
        $this->assertEquals($challengeurl, $challengenode->action);
        $this->assertStringContainsString('Example challenge', $challengenode->text);
        $this->assertStringContainsString('Example answer', $answernode->text);
        $this->assertFalse($answernode->has_action());
    }
}
