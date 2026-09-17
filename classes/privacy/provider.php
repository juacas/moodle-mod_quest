<?php
// This file is part of QUESTOURnament activity for Moodle - http://moodle.org/
//
// QUESTOURnament for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// QUESTOURnament for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for mod_quest.
 *
 * @package    mod_quest
 * @copyright  2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quest\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_quest.
 *
 * @package    mod_quest
 * @copyright  2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quest_submissions', [
            'questid' => 'privacy:metadata:quest_submissions:questid',
            'userid' => 'privacy:metadata:quest_submissions:userid',
            'title' => 'privacy:metadata:quest_submissions:title',
            'description' => 'privacy:metadata:quest_submissions:description',
            'attachment' => 'privacy:metadata:quest_submissions:attachment',
            'timecreated' => 'privacy:metadata:quest_submissions:timecreated',
            'points' => 'privacy:metadata:quest_submissions:points',
            'commentteacherpupil' => 'privacy:metadata:quest_submissions:commentteacherpupil',
            'commentteacherauthor' => 'privacy:metadata:quest_submissions:commentteacherauthor',
        ], 'privacy:metadata:quest_submissions');

        $collection->add_database_table('quest_answers', [
            'questid' => 'privacy:metadata:quest_answers:questid',
            'submissionid' => 'privacy:metadata:quest_answers:submissionid',
            'userid' => 'privacy:metadata:quest_answers:userid',
            'title' => 'privacy:metadata:quest_answers:title',
            'description' => 'privacy:metadata:quest_answers:description',
            'attachment' => 'privacy:metadata:quest_answers:attachment',
            'date' => 'privacy:metadata:quest_answers:date',
            'pointsmax' => 'privacy:metadata:quest_answers:pointsmax',
            'grade' => 'privacy:metadata:quest_answers:grade',
            'commentforteacher' => 'privacy:metadata:quest_answers:commentforteacher',
            'perceiveddifficulty' => 'privacy:metadata:quest_answers:perceiveddifficulty',
            'questionusageid' => 'privacy:metadata:quest_answers:questionusageid',
        ], 'privacy:metadata:quest_answers');

        $collection->add_database_table('quest_assessments', [
            'questid' => 'privacy:metadata:quest_assessments:questid',
            'answerid' => 'privacy:metadata:quest_assessments:answerid',
            'userid' => 'privacy:metadata:quest_assessments:userid',
            'teacherid' => 'privacy:metadata:quest_assessments:teacherid',
            'pointsautor' => 'privacy:metadata:quest_assessments:pointsautor',
            'pointsteacher' => 'privacy:metadata:quest_assessments:pointsteacher',
            'dateassessment' => 'privacy:metadata:quest_assessments:dateassessment',
            'commentsforteacher' => 'privacy:metadata:quest_assessments:commentsforteacher',
            'commentsteacher' => 'privacy:metadata:quest_assessments:commentsteacher',
        ], 'privacy:metadata:quest_assessments');

        $collection->add_database_table('quest_assessments_autors', [
            'questid' => 'privacy:metadata:quest_assessments_autors:questid',
            'submissionid' => 'privacy:metadata:quest_assessments_autors:submissionid',
            'userid' => 'privacy:metadata:quest_assessments_autors:userid',
            'points' => 'privacy:metadata:quest_assessments_autors:points',
            'dateassessment' => 'privacy:metadata:quest_assessments_autors:dateassessment',
            'commentsforteacher' => 'privacy:metadata:quest_assessments_autors:commentsforteacher',
            'commentsteacher' => 'privacy:metadata:quest_assessments_autors:commentsteacher',
        ], 'privacy:metadata:quest_assessments_autors');

        $collection->add_database_table('quest_elements_assessments', [
            'questid' => 'privacy:metadata:quest_elements_assessments:questid',
            'assessmentid' => 'privacy:metadata:quest_elements_assessments:assessmentid',
            'userid' => 'privacy:metadata:quest_elements_assessments:userid',
            'answer' => 'privacy:metadata:quest_elements_assessments:answer',
            'commentteacher' => 'privacy:metadata:quest_elements_assessments:commentteacher',
            'calification' => 'privacy:metadata:quest_elements_assessments:calification',
        ], 'privacy:metadata:quest_elements_assessments');

        $collection->add_database_table('quest_items_assesments_autor', [
            'questid' => 'privacy:metadata:quest_items_assesments_autor:questid',
            'assessmentautorid' => 'privacy:metadata:quest_items_assesments_autor:assessmentautorid',
            'userid' => 'privacy:metadata:quest_items_assesments_autor:userid',
            'answer' => 'privacy:metadata:quest_items_assesments_autor:answer',
            'commentteacher' => 'privacy:metadata:quest_items_assesments_autor:commentteacher',
            'calification' => 'privacy:metadata:quest_items_assesments_autor:calification',
        ], 'privacy:metadata:quest_items_assesments_autor');

        $collection->add_database_table('quest_calification_users', [
            'questid' => 'privacy:metadata:quest_calification_users:questid',
            'userid' => 'privacy:metadata:quest_calification_users:userid',
            'teamid' => 'privacy:metadata:quest_calification_users:teamid',
            'points' => 'privacy:metadata:quest_calification_users:points',
            'nanswers' => 'privacy:metadata:quest_calification_users:nanswers',
            'nsubmissions' => 'privacy:metadata:quest_calification_users:nsubmissions',
            'pointssubmission' => 'privacy:metadata:quest_calification_users:pointssubmission',
            'pointsanswers' => 'privacy:metadata:quest_calification_users:pointsanswers',
        ], 'privacy:metadata:quest_calification_users');

        $collection->link_subsystem('core_files', 'privacy:metadata:core_files');
        $collection->link_subsystem('core_question', 'privacy:metadata:core_question');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest} q ON q.id = cm.instance
             LEFT JOIN {quest_submissions} qs ON qs.questid = q.id AND qs.userid = :userid1
             LEFT JOIN {quest_answers} qa ON qa.questid = q.id AND qa.userid = :userid2
             LEFT JOIN {quest_assessments} qas ON qas.questid = q.id AND (qas.userid = :userid3 OR qas.teacherid = :userid4)
             LEFT JOIN {quest_assessments_autors} qaa ON qaa.questid = q.id AND qaa.userid = :userid5
             LEFT JOIN {quest_calification_users} qcu ON qcu.questid = q.id AND qcu.userid = :userid6
                 WHERE qs.id IS NOT NULL
                    OR qa.id IS NOT NULL
                    OR qas.id IS NOT NULL
                    OR qaa.id IS NOT NULL
                    OR qcu.id IS NOT NULL";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'quest',
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'cmid' => $context->instanceid,
            'modname' => 'quest',
        ];

        // Users who submitted challenges.
        $sql = "SELECT qs.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest_submissions} qs ON qs.questid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users who answered challenges.
        $sql = "SELECT qa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest_answers} qa ON qa.questid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users who assessed answers.
        $sql = "SELECT qas.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest_assessments} qas ON qas.questid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Teachers who assessed answers.
        $sql = "SELECT qas.teacherid AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest_assessments} qas ON qas.questid = cm.instance
                 WHERE cm.id = :cmid AND qas.teacherid > 0";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users who assessed authoring.
        $sql = "SELECT qaa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest_assessments_autors} qaa ON qaa.questid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users with qualifications.
        $sql = "SELECT qcu.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quest_calification_users} qcu ON qcu.questid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('quest', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $quest = $DB->get_record('quest', ['id' => $cm->instance]);
            if (!$quest) {
                continue;
            }

            $writer = writer::with_context($context);

            // 1. Export user submissions (challenges).
            $submissions = $DB->get_records('quest_submissions', [
                'questid' => $quest->id,
                'userid' => $user->id,
            ]);

            foreach ($submissions as $sub) {
                $subcontext = [
                    get_string('challenges', 'mod_quest'),
                    $sub->id,
                ];

                $description = $writer->rewrite_pluginfile_urls(
                    $subcontext,
                    'mod_quest',
                    'attachment',
                    $sub->id,
                    $sub->description
                );

                $data = (object) [
                    'title' => $sub->title,
                    'description' => $description,
                    'timecreated' => transform::datetime($sub->timecreated),
                    'points' => $sub->points,
                    'pointsmax' => $sub->pointsmax,
                    'phase' => $sub->phase,
                    'state' => $sub->state,
                ];

                $writer->export_data($subcontext, $data);
                $writer->export_area_files($subcontext, 'mod_quest', 'attachment', $sub->id);
                $writer->export_area_files($subcontext, 'mod_quest', 'submission', $sub->id);
            }

            // 2. Export user answers.
            $answers = $DB->get_records('quest_answers', [
                'questid' => $quest->id,
                'userid' => $user->id,
            ]);

            foreach ($answers as $ans) {
                $subcontext = [
                    get_string('answers', 'mod_quest'),
                    $ans->id,
                ];

                $ansdesc = $writer->rewrite_pluginfile_urls(
                    $subcontext,
                    'mod_quest',
                    'answer_attachment',
                    $ans->id,
                    $ans->description
                );

                $data = (object) [
                    'title' => $ans->title,
                    'description' => $ansdesc,
                    'date' => transform::datetime($ans->date),
                    'pointsmax' => $ans->pointsmax,
                    'grade' => $ans->grade,
                    'commentforteacher' => $ans->commentforteacher,
                    'perceiveddifficulty' => $ans->perceiveddifficulty,
                ];

                $writer->export_data($subcontext, $data);
                $writer->export_area_files($subcontext, 'mod_quest', 'answer_attachment', $ans->id);
                $writer->export_area_files($subcontext, 'mod_quest', 'answer', $ans->id);
            }

            // 3. Export assessments performed by the user on answers.
            $assessments = $DB->get_records('quest_assessments', [
                'questid' => $quest->id,
                'userid' => $user->id,
            ]);

            foreach ($assessments as $assessment) {
                $subcontext = [
                    get_string('assessments', 'mod_quest'),
                    $assessment->id,
                ];

                $data = (object) [
                    'pointsautor' => $assessment->pointsautor,
                    'pointsteacher' => $assessment->pointsteacher,
                    'dateassessment' => transform::datetime($assessment->dateassessment),
                    'commentsforteacher' => $assessment->commentsforteacher,
                ];

                $writer->export_data($subcontext, $data);
            }

            // 4. Export overall qualifications in the contest.
            $calif = $DB->get_record('quest_calification_users', [
                'questid' => $quest->id,
                'userid' => $user->id,
            ]);

            if ($calif) {
                $subcontext = [get_string('calification', 'mod_quest')];
                $data = (object) [
                    'points' => $calif->points,
                    'nanswers' => $calif->nanswers,
                    'nsubmissions' => $calif->nsubmissions,
                    'pointssubmission' => $calif->pointssubmission,
                    'pointsanswers' => $calif->pointsanswers,
                ];
                $writer->export_data($subcontext, $data);
            }
        }
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param \context $context A context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('quest', $context->instanceid);
        if (!$cm) {
            return;
        }

        $questid = $cm->instance;

        // Delete all child tables.
        $DB->delete_records('quest_elements_assessments', ['questid' => $questid]);
        $DB->delete_records('quest_items_assesments_autor', ['questid' => $questid]);
        $DB->delete_records('quest_assessments', ['questid' => $questid]);
        $DB->delete_records('quest_assessments_autors', ['questid' => $questid]);
        $DB->delete_records('quest_answers', ['questid' => $questid]);
        $DB->delete_records('quest_submissions', ['questid' => $questid]);
        $DB->delete_records('quest_calification_users', ['questid' => $questid]);
        $DB->delete_records('quest_calification_teams', ['questid' => $questid]);
        $DB->delete_records('quest_teams', ['questid' => $questid]);
        $DB->delete_records('quest_elements', ['questid' => $questid]);
        $DB->delete_records('quest_elementsautor', ['questid' => $questid]);

        // Delete all files associated with this module context.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('quest', $context->instanceid);
        if (!$cm) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            static::delete_user_data_in_quest($cm->instance, (int) $context->id, (int) $userid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('quest', $context->instanceid);
            if (!$cm) {
                continue;
            }

            static::delete_user_data_in_quest($cm->instance, (int) $context->id, (int) $userid);
        }
    }

    /**
     * Delete personal data of a user within a specific quest instance.
     *
     * @param int $questid The quest instance ID.
     * @param int $contextid The context ID of the course module.
     * @param int $userid The user ID.
     */
    protected static function delete_user_data_in_quest(int $questid, int $contextid, int $userid): void {
        global $DB;

        $fs = get_file_storage();

        // 1. Delete user calification.
        $DB->delete_records('quest_calification_users', [
            'questid' => $questid,
            'userid' => $userid,
        ]);

        // 2. Delete assessments performed by the user.
        $DB->delete_records('quest_elements_assessments', [
            'questid' => $questid,
            'userid' => $userid,
        ]);
        $DB->delete_records('quest_items_assesments_autor', [
            'questid' => $questid,
            'userid' => $userid,
        ]);
        $DB->delete_records('quest_assessments', [
            'questid' => $questid,
            'userid' => $userid,
        ]);
        $DB->delete_records('quest_assessments_autors', [
            'questid' => $questid,
            'userid' => $userid,
        ]);

        // 3. Delete answers submitted by the user.
        $answers = $DB->get_records('quest_answers', [
            'questid' => $questid,
            'userid' => $userid,
        ]);

        foreach ($answers as $ans) {
            $fs->delete_area_files($contextid, 'mod_quest', 'answer_attachment', $ans->id);
            $fs->delete_area_files($contextid, 'mod_quest', 'answer', $ans->id);

            if (!empty($ans->questionusageid)) {
                \question_engine::delete_questions_usage_by_activity($ans->questionusageid);
            }

            $DB->delete_records('quest_assessments', ['answerid' => $ans->id]);
            $DB->delete_records('quest_answers', ['id' => $ans->id]);
        }

        // 4. Handle submissions (challenges) created by the user.
        $submissions = $DB->get_records('quest_submissions', [
            'questid' => $questid,
            'userid' => $userid,
        ]);

        foreach ($submissions as $sub) {
            $fs->delete_area_files($contextid, 'mod_quest', 'attachment', $sub->id);
            $fs->delete_area_files($contextid, 'mod_quest', 'submission', $sub->id);

            // Check if others have submitted answers to this challenge.
            $hasanswers = $DB->record_exists('quest_answers', ['submissionid' => $sub->id]);

            if ($hasanswers) {
                // Anonymize challenge content to preserve attempt history and grades for other participants.
                $DB->update_record('quest_submissions', (object) [
                    'id' => $sub->id,
                    'title' => get_string('privacy:request:deleted:title', 'mod_quest'),
                    'description' => get_string('privacy:request:deleted:content', 'mod_quest'),
                    'commentteacherpupil' => null,
                    'commentteacherauthor' => null,
                ]);
            } else {
                // If nobody answered it, remove completely.
                $DB->delete_records('quest_assessments_autors', ['submissionid' => $sub->id]);
                $DB->delete_records('quest_submissions', ['id' => $sub->id]);
            }
        }
    }
}
