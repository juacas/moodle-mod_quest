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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Steps definitions related to mod_quest.
 *
 * @package    mod_quest
 * @category   test
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Quest-specific Behat steps.
 */
class behat_mod_quest extends behat_base {

    /**
     * Resolve a challenge title to its detail page.
     *
     * @param string $type Page type.
     * @param string $identifier Challenge title.
     * @return moodle_url Page URL.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        if (strtolower($type) !== 'quest challenge') {
            throw new coding_exception('Unrecognised Quest page type "' . $type . '".');
        }

        $submission = $DB->get_record('quest_submissions', ['title' => $identifier], '*', MUST_EXIST);
        $quest = $DB->get_record('quest', ['id' => $submission->questid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quest', $quest->id, $quest->course, null, MUST_EXIST);

        return new moodle_url('/mod/quest/challenges.php', [
            'id' => $cm->id,
            'cid' => $submission->id,
            'action' => 'showchallenge',
        ]);
    }

    /**
     * Create a pending open essay challenge for a phase/visibility scenario.
     *
     * @Given /^a Quest essay challenge "([^"]*)" created by "([^"]*)" is awaiting approval in "([^"]*)"$/
     * @param string $title Challenge title.
     * @param string $username Author username.
     * @param string $activity Quest activity name.
     */
    public function create_pending_essay_challenge(string $title, string $username, string $activity): void {
        global $DB;

        require_once(__DIR__ . '/../../locallib.php');
        $cm = $this->get_cm_by_activity_name('quest', $activity);
        $quest = $DB->get_record('quest', ['id' => $cm->instance], '*', MUST_EXIST);
        $user = $this->get_user_by_identifier($username);
        if (!$user) {
            throw new coding_exception('Unknown Behat user "' . $username . '".');
        }

        $now = time();
        $submission = (object) [
            'questid' => $quest->id,
            'userid' => $user->id,
            'numelements' => 0,
            'title' => $title,
            'timecreated' => $now,
            'description' => 'Explain the main idea of this essay.',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
            'attachment' => 0,
            'points' => 0,
            'phase' => SUBMISSION_PHASE_ACTIVE,
            'commentteacherpupil' => '',
            'commentteacherauthor' => '',
            'dateend' => $now + DAYSECS,
            'nanswers' => 0,
            'nanswerscorrect' => 0,
            'state' => SUBMISSION_STATE_APPROVAL_PENDING,
            'datestart' => $now - HOURSECS,
            'pointsmax' => $quest->maxcalification,
            'pointsmin' => $quest->mincalification,
            'dateanswercorrect' => 0,
            'initialpoints' => $quest->initialpoints,
            'pointsanswercorrect' => 0,
            'mailed' => 0,
            'maileduser' => 0,
            'predictedduration' => 0,
            'perceiveddifficulty' => -1,
            'evaluated' => 0,
            'questionusageid' => 0,
        ];

        $DB->insert_record('quest_submissions', $submission);
    }

    /**
     * Mark the seeded challenge as approved and assessed by the teacher.
     *
     * @Given /^the Quest essay challenge "([^"]*)" has been approved and assessed by "([^"]*)"$/
     * @param string $title Challenge title.
     * @param string $username Teacher username.
     */
    public function approve_and_assess_essay_challenge(string $title, string $username): void {
        global $DB;

        require_once(__DIR__ . '/../../locallib.php');
        $submission = $DB->get_record('quest_submissions', ['title' => $title], '*', MUST_EXIST);
        $teacher = $this->get_user_by_identifier($username);
        if (!$teacher) {
            throw new coding_exception('Unknown Behat user "' . $username . '".');
        }

        $DB->set_field('quest_submissions', 'state', SUBMISSION_STATE_APROVED, ['id' => $submission->id]);
        $DB->set_field('quest_submissions', 'evaluated', 1, ['id' => $submission->id]);
        $assessment = (object) [
            'questid' => $submission->questid,
            'submissionid' => $submission->id,
            'userid' => $teacher->id,
            'points' => 80,
            'dateassessment' => time(),
            'pointsmax' => $submission->pointsmax,
            'commentsforteacher' => '',
            'commentsteacher' => 'The challenge is clear and ready for participation.',
            'phase' => ASSESSMENT_PHASE_APPROVED,
            'state' => ASSESSMENT_STATE_BY_TEACHER,
        ];
        $DB->insert_record('quest_assessments_autors', $assessment);
    }

    /**
     * Add an answer from another student and its author assessment.
     *
     * @Given /^student "([^"]*)" has answered Quest essay challenge "([^"]*)" and its author has assessed it$/
     * @param string $username Answer author.
     * @param string $title Challenge title.
     */
    public function add_assessed_essay_answer(string $username, string $title): void {
        global $DB;

        require_once(__DIR__ . '/../../locallib.php');
        $submission = $DB->get_record('quest_submissions', ['title' => $title], '*', MUST_EXIST);
        $answeruser = $this->get_user_by_identifier($username);
        $author = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);

        $answer = (object) [
            'questid' => $submission->questid,
            'submissionid' => $submission->id,
            'userid' => $answeruser->id,
            'title' => 'Essay answer',
            'description' => 'This is the student answer to the essay challenge.',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
            'attachment' => '',
            'date' => time(),
            'pointsmax' => $submission->pointsmax,
            'grade' => 80,
            'commentforteacher' => '',
            'phase' => ANSWER_PHASE_GRADED,
            'state' => ANSWER_STATE_EDITTED,
            'permitsubmit' => ANSWER_PERMITSUBMIT_NO_EDITABLE,
            'perceiveddifficulty' => -1,
            'questionusageid' => 0,
        ];
        $answerid = $DB->insert_record('quest_answers', $answer);
        $DB->set_field('quest_submissions', 'nanswers', 1, ['id' => $submission->id]);

        $assessment = (object) [
            'questid' => $submission->questid,
            'answerid' => $answerid,
            'userid' => $author->id,
            'teacherid' => 0,
            'pointsautor' => 80,
            'pointsteacher' => 0,
            'dateassessment' => time(),
            'pointsmax' => $submission->pointsmax,
            'commentsforteacher' => '',
            'commentsteacher' => '',
            'phase' => ANSWER_PHASE_GRADED,
            'state' => ASSESSMENT_STATE_BY_AUTOR,
        ];
        $DB->insert_record('quest_assessments', $assessment);
    }
}
