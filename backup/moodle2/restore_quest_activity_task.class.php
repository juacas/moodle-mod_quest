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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quest/backup/moodle2/restore_quest_stepslib.php');

/**
 * Restore task for the Quest activity.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quest_activity_task extends restore_activity_task {

    /**
     * Define particular settings for this activity.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define the restore steps for this activity.
     *
     * @return void
     */
    protected function define_my_steps() {
        // ...quest only has one structure step.
        $this->add_step(new restore_quest_activity_structure_step('quest_structure', 'quest.xml'));
    }

    /**
     * Relink attempts when Moodle keeps an existing question from an external bank.
     *
     * During a same-site activity restore, the temporary question copy can be removed
     * after the activity step. Its attempts must then use the original question ID.
     */
    public function after_restore() {
        global $DB;

        if (!$this->is_samesite() || !$this->get_setting_value('userinfo')) {
            return;
        }

        $sql = "SELECT qa.id, bi.itemid AS originalquestionid
                  FROM {quest_answers} a
                  JOIN {question_attempts} qa ON qa.questionusageid = a.questionusageid
                  JOIN {backup_ids_temp} bi ON bi.newitemid = qa.questionid
                  LEFT JOIN {question} q ON q.id = qa.questionid
                 WHERE a.questid = :questid
                   AND bi.backupid = :restoreid
                   AND bi.itemname = 'question_created'
                   AND q.id IS NULL";
        $attempts = $DB->get_records_sql($sql, [
            'questid' => $this->get_activityid(),
            'restoreid' => $this->get_restoreid(),
        ]);
        foreach ($attempts as $attempt) {
            if ($DB->record_exists('question', ['id' => $attempt->originalquestionid])) {
                $DB->set_field('question_attempts', 'questionid', $attempt->originalquestionid, ['id' => $attempt->id]);
            }
        }
    }

    /**
     * Define the activity contents processed by the link decoder.
     *
     * @return array Restore content rules.
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('quest', ['intro'], 'quest');
        $contents[] = new restore_decode_content('quest_answers', ['description', 'title', 'commentforteacher'],
                                                'quest_answer');
        $contents[] = new restore_decode_content('quest_submissions',
                ['description', 'title', 'commentteacherpupil', 'commentteacherauthor'], 'quest_submisssion');

        return $contents;
    }

    /**
     * Define decoding rules for links belonging to the activity.
     *
     * @return array Restore link rules.
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('QUESTVIEWBYID', '/mod/quest/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('QUESTINDEX', '/mod/quest/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules for Quest activity events.
     *
     * @return array Restore log rules.
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('quest', 'add', 'view.php?id={course_module}', '{quest}');
        $rules[] = new restore_log_rule('quest', 'update', 'view.php?id={course_module}', '{quest}');
        $rules[] = new restore_log_rule('quest', 'view', 'view.php?id={course_module}', '{quest}');
        $rules[] = new restore_log_rule('quest', 'read_submission',
                'challenges.php?id={course_module}&cid={submission}&action=showchallenge', '{quest}');
        $rules[] = new restore_log_rule('quest', 'read_challenge',
                'challenges.php?id={course_module}&cid={submission}&action=showchallenge', '{quest}');
        $rules[] = new restore_log_rule('quest', 'read_answer', 'answer.php?sid={submission}&aid={answer}&action=showanswer',
                '{quest}');
        $rules[] = new restore_log_rule('quest', 'newattachment',
                'challenges.php?id={course_module}&cid={submission}&action=showchallenge', '{quest}');

        return $rules;
    }

    /** Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs.
     * It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0) */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        // Fix old wrong uses (missing extension).
        $rules[] = new restore_log_rule('quest', 'view all', 'index?id={course}', null, null, null, 'index.php?id={course}');
        $rules[] = new restore_log_rule('quest', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
