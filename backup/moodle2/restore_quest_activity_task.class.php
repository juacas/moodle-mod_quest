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

/** Backup Questournament module
 *
 * quest restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */
class restore_quest_activity_task extends restore_activity_task {

    /** Define (add) particular settings this activity can have */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /** Define (add) particular steps this activity can have */
    protected function define_my_steps() {
        // ...quest only has one structure step.
        $this->add_step(new restore_quest_activity_structure_step('quest_structure', 'quest.xml'));
    }

    /** Define the contents in the activity that must be
     * processed by the link decoder */
    static public function define_decode_contents() {
        $contents = array();
        $contents[] = new restore_decode_content('quest', array('intro'), 'quest');
        $contents[] = new restore_decode_content('quest_answers', array('description', 'title', 'commentforteacher'),
                                                'quest_answer');
        $contents[] = new restore_decode_content('quest_submissions',
                array('description', 'title', 'commentteacherpupil', 'commentteacherauthor'), 'quest_submisssion');

        return $contents;
    }

    /** Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('QUESTVIEWBYID', '/mod/quest/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('QUESTINDEX', '/mod/quest/index.php?id=$1', 'course');

        return $rules;
    }

    /** Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * quest logs.
     * It must return one array
     * of {@link restore_log_rule} objects */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('quest', 'add', 'view.php?id={course_module}', '{quest}');
        $rules[] = new restore_log_rule('quest', 'update', 'view.php?id={course_module}', '{quest}');
        $rules[] = new restore_log_rule('quest', 'view', 'view.php?id={course_module}', '{quest}');
        $rules[] = new restore_log_rule('quest', 'read_submission',
                'submissions.php?id={course_module}&sid={submission}&action=showsubmission', '{quest}');
        $rules[] = new restore_log_rule('quest', 'read_answer', 'answer.php?sid={submission}&aid={answer}&action=showanswer',
                '{quest}');
        $rules[] = new restore_log_rule('quest', 'newattachment',
                'submissions.php?id={course_module}&id={submission}&action=showsubmission', '{quest}');

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
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        // Fix old wrong uses (missing extension).
        $rules[] = new restore_log_rule('quest', 'view all', 'index?id={course}', null, null, null, 'index.php?id={course}');
        $rules[] = new restore_log_rule('quest', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}