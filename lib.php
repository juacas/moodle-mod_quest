<?php
// This file is part of Questournament activity for Moodle http://moodle.org/
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

/**
 * Library of functions and constants for module quest
 * quest constants and standard Moodle functions plus the quest functions
 * called by the standard functions
 * see also locallib.php for other non-standard quest functions
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/mod/quest/locallib.php');

/**
 * Hash a Quest access password using the current PHP password algorithm.
 *
 * @param string $password Plain-text password.
 * @return string Password hash.
 */
function quest_hash_password(string $password): string {
    return password_hash(trim($password), PASSWORD_DEFAULT);
}

/**
 *
 * @param \stdClass $quest
 * @return boolean|number
 */
function quest_add_instance($quest) {
    // Given an object containing all the necessary data,
    // (defined by the form in mod.html) this function
    // will create a new instance and return the id number
    // of the new instance.
    global $DB;

    $quest->timemodified = time();

    if ($quest->initialpoints > $quest->maxcalification) {
        $quest->initialpoints = $quest->maxcalification;
    }
    if (($quest->showclasifindividual == 0) && ($quest->allowteams == 0)) {
        $quest->showclasifindividual = 1;
    }
    if (($quest->typegrade == QUEST_TYPE_GRADE_TEAM) && ($quest->allowteams == 0)) {
        $quest->typegrade = 0;
    }

    // Store new access passwords using a slow, salted password hash.
    if (!empty($quest->password)) {
        $quest->password = quest_hash_password($quest->password);
    } else {
        unset($quest->password);
    }

    if ($returnid = $DB->insert_record("quest", $quest)) {
        $quest->id = $quest->instance = $returnid;
        quest_update_quest_calendar($quest, $quest); // At this point $quest is a mix of quest record and cminfo.
        $ctx = context_module::instance($quest->coursemodule);
        quest_save_intro_draft_files($quest, $ctx);
        quest_grade_item_update($quest);
    }
    return $returnid;
}

/**
 * Report whether the Quest module supports a Moodle feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 *
 *         Features are explained in moodlelib.php */
function quest_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPMEMBERSONLY:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_ADVANCED_GRADING:
            return false;
        case FEATURE_RATE:
            return false;
        case FEATURE_NO_VIEW_LINK:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        default:
            return null;
    }
}
/**
 * Check validity of challenge dates.
 *
 * @param \stdClass $challenge
 * @return boolean
 */
function quest_check_challenge_dates($challenge) {
    return ($challenge->datestart >= $challenge->questdatestart &&
            $challenge->dateend <= $challenge->questdateend &&
             $challenge->questdateend > $challenge->questdatestart);
}

/**
 * Legacy wrapper for quest_check_challenge_dates.
 *
 * @param \stdClass $newsubmission
 * @return boolean
 */
function quest_check_submission_dates($newsubmission) {
    return quest_check_challenge_dates($newsubmission);
}

/**
 * Check validity of challenge title and description text.
 *
 * @param \stdClass $challenge
 * @return boolean
 */
function quest_check_challenge_text($challenge) {
    $validate = true;

    if (empty($challenge->title)) {
        $validate = false;
    }
    if (empty($challenge->description)) {
        $validate = false;
    }
    return $validate;
}

/**
 * Legacy wrapper for quest_check_challenge_text.
 *
 * @param \stdClass $newsubmission
 * @return boolean
 */
function quest_check_submission_text($newsubmission) {
    return quest_check_challenge_text($newsubmission);
}
/** Update the configuration of the Quest
 *
 * @param stdClass $quest cminfo
 * @return bool */
function quest_update_instance($quest, $form) {
    // Given an object containing all the necessary data,
    // (defined by the form in mod_.ht_form.php) this function
    // will update an existing instance with new data.
    global $CFG, $DB;

    if ($quest->initialpoints > $quest->maxcalification) {
        $quest->initialpoints = $quest->maxcalification;
    }
    if (($quest->showclasifindividual == 0) && ($quest->allowteams == 0)) {
        $quest->showclasifindividual = 1;
    }
    if (($quest->typegrade == 1) && ($quest->allowteams == 0)) {
        $quest->typegrade = 0;
    }
    // Keep the stored hash when the edit form leaves the password unchanged.
    if (!empty($quest->password)) {
        $storedpassword = $DB->get_field('quest', 'password', ['id' => $quest->instance]);
        if ((string)$quest->password === (string)$storedpassword) {
            unset($quest->password);
        } else {
            $quest->password = quest_hash_password($quest->password);
        }
    } else {
        unset($quest->password);
    }
    $quest->id = $quest->instance;
    if ($DB->update_record("quest", $quest)) {
        quest_update_quest_calendar($quest, $quest);
        $ctx = context_module::instance($quest->coursemodule);
        quest_save_intro_draft_files($quest, $ctx);
    }

    quest_update_grades($quest);
    return true;
}
/**
 *
 * @param int $id
 * @return boolean
 */
function quest_delete_instance($id) {
    global $CFG, $DB;
    require_once('locallib.php');
    // Given an ID of an instance of this module,
    // ...this function will permanently delete the instance.
    // ...and any data that depends on it..
    $quest = $DB->get_record("quest", ["id" => $id], "*", MUST_EXIST);

    if (!$cm = get_coursemodule_from_instance('quest', $quest->id)) {
        return false;
    }
    // ...delete all the associated records in the quest tables, start positive....
    $result = true;
    if (!$DB->delete_records("quest_elements", ["questid" => $quest->id])) {
        $result = false;
    }
    if (!$DB->delete_records("quest_elements_assessments", ["questid" => $quest->id])) {
        $result = false;
    }

    if (!$DB->delete_records("quest_items_assesments_autor", ["questid" => $quest->id])) {
        $result = false;
    }

    if (!$DB->delete_records("quest_elementsautor", ["questid" => $quest->id])) {
        $result = false;
    }

    if (!$DB->delete_records("quest_assessments", ["questid" => $quest->id])) {
        $result = false;
    }

    if (!$DB->delete_records("quest_assessments_autors", ["questid" => $quest->id])) {
        $result = false;
    }
    if (!$DB->delete_records("quest_submissions", ["questid" => $quest->id])) {
        $result = false;
    }
    if (!$DB->delete_records("quest_answers", ["questid" => $quest->id])) {
        $result = false;
    }
    if (!$DB->delete_records("quest_calification_users", ["questid" => $quest->id])) {
        $result = false;
    }
    if ($quest->allowteams) {
        if (!$DB->delete_records("quest_teams", ["questid" => $quest->id])) {
            $result = false;
        }
        if (!$DB->delete_records("quest_calification_teams", ["questid" => $quest->id])) {
            $result = false;
        }
    }
    if (!$DB->delete_records("quest", ["id" => $quest->id])) {
        $result = false;
    }
    if (!$DB->delete_records('event', ['modulename' => 'quest', 'instance' => $quest->id])) {
        $result = false;
    }
    $context = context_module::instance($cm->id);
    // ...now get rid of all files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);
    return $result;
}
/**
 *
 * @param \stdClass $course
 * @param \stdClass $cm
 * @param \stdClass $context
 * @param string $filearea
 * @param string[] $args
 * @param bool $forcedownload
 * @param array $options
 * @return boolean
 */
function quest_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);
    if (!has_capability('mod/quest:view', $context)) {
        return false;
    }
    $filename = array_pop($args);
    if ($filename === null || $filename === '') {
        return false;
    }
    $entryid = (int) array_shift($args);

    if ($filearea === 'introattachment') {
        $entryid = 0;
    } else {
        if ($filearea === 'attachment' || $filearea === 'submission') {
            if (!$entry = $DB->get_record('quest_submissions', ['id' => $entryid])) {
                return false;
            }
        } else if ($filearea === 'answer_attachment' || $filearea === 'answer') {
            if (!$entry = $DB->get_record('quest_answers', ['id' => $entryid])) {
                return false;
            }
        } else {
            return false; // Unknown filearea.
        }

        // File IDs are global. Do not allow an ID from a different Quest activity
        // to be used with the current module context.
        if ((int) $entry->questid !== (int) $cm->instance) {
            return false;
        }
    }

    $filepath = '/';
    if (!empty($args)) {
        $filepath .= implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $hash = $fs->get_pathname_hash($context->id, 'mod_quest', $filearea, $entryid, $filepath, $filename);
    $file = $fs->get_file_by_hash($hash);
    if (!$file || $file->is_directory()) {
        // Fallback for submissions linked to a question bank question.
        if (($filearea === 'attachment' || $filearea === 'submission') && !empty($entryid)) {
            require_once($CFG->dirroot . '/mod/quest/classes/question/question_reference_service.php');
            require_once($CFG->libdir . '/questionlib.php');
            $linkedq = \mod_quest\question\question_reference_service::get_question_for_challenge($entryid);
            if ($linkedq) {
                $loadedq = \question_bank::load_question((int)$linkedq->id);
                $qhash = $fs->get_pathname_hash(
                    $loadedq->contextid,
                    'question',
                    'questiontext',
                    $loadedq->id,
                    $filepath,
                    $filename
                );
                $file = $fs->get_file_by_hash($qhash);
            }
        }
        if (!$file || $file->is_directory()) {
            return false;
        }
    }

    // Finally send the file.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Serve question files belonging to quest question usages.
 *
 * Called by question_pluginfile() in lib/questionlib.php when component is 'mod_quest'.
 *
 * @param stdClass $course course settings object
 * @param context $context context object (question category/course context)
 * @param string $component the name of the component we are serving files for ('question')
 * @param string $filearea the name of the file area ('questiontext', etc.)
 * @param int $qubaid the attempt usage id
 * @param int $slot the id of a question in this attempt
 * @param array $args the remaining bits of the file path
 * @param bool $forcedownload whether the user must be forced to download the file
 * @param array $options additional options affecting the file serving
 * @return void Sends file or calls send_file_not_found()
 */
function quest_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options = []) {
    global $CFG;

    require_once($CFG->libdir . '/questionlib.php');

    try {
        $quba = question_engine::load_questions_usage_by_activity($qubaid);
    } catch (\Exception $e) {
        send_file_not_found();
    }

    $owningcontext = $quba->get_owning_context();
    if ($owningcontext && $owningcontext->contextlevel == CONTEXT_MODULE) {
        list($modcontext, $course, $cm) = get_context_info_array($owningcontext->id);
        require_course_login($course, true, $cm);
        if (!has_capability('mod/quest:view', $owningcontext)) {
            send_file_not_found();
        }
    } else {
        require_login();
    }

    $displayoptions = new question_display_options();
    $displayoptions->feedback = question_display_options::VISIBLE;
    $displayoptions->numpartscorrect = question_display_options::VISIBLE;
    $displayoptions->generalfeedback = question_display_options::VISIBLE;
    $displayoptions->rightanswer = question_display_options::VISIBLE;
    $displayoptions->manualcomment = question_display_options::VISIBLE;
    $displayoptions->history = question_display_options::VISIBLE;

    if (!$quba->check_file_access($slot, $displayoptions, $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $fullpath = quest_question_file_path($context, $component, $filearea, $args);
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Build the storage path for a file owned by a question attempt.
 *
 * Question files live in the question category context, not in the module
 * context that owns the question usage. The context received by the callback
 * is the one encoded in the question pluginfile URL.
 *
 * @param context $context Question file context.
 * @param string $component File component.
 * @param string $filearea File area.
 * @param string[] $args Remaining pluginfile path components.
 * @return string File storage path.
 */
function quest_question_file_path(context $context, string $component, string $filearea, array $args): string {
    return "/{$context->id}/{$component}/{$filearea}/" . implode('/', $args);
}

/**
 * Alternative callback alias for quest_question_pluginfile.
 */
function mod_quest_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options = []) {
    quest_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, $options);
}

/**
 * Save the attachments in the draft areas.
 *
 * @param stdClass $formdata
 */
function quest_save_intro_draft_files($formdata, $ctx) {
    if (isset($formdata->introattachments)) {
        file_save_draft_area_files($formdata->introattachments, $ctx->id, 'mod_quest', 'introattachment', 0);
    }
}
/**
 *
 * @param \stdClass $course
 * @param \stdClass $user
 * @param \stdClass $mod
 * @param \stdClass $quest
 * @return NULL
 */
function quest_user_outline($course, $user, $mod, $quest) {
    // Return a small object with summary information about what a
    // user has done with a given particular instance of this module
    // Used for user activity reports.
    // $return->time = the time they did it
    // $return->info = a short text description.
    $result = null;
    if ($submissions = quest_get_user_submissions($quest, $user)) {
        $result->info = count($submissions) . " " . get_string("submissions", "quest") . "<br>";
        foreach ($submissions as $submission) {
            $result->time = $submission->timecreated;
            break;
        }
    }
    if ($answers = quest_get_user_answer($quest, $user)) {
        $result->info .= count($answers) . " " . get_string("answers", "quest") . "<br>";
        foreach ($answers as $answer) {
            $result->time = $answer->date;
            break;
        }
    }
    if ($assessments = quest_get_user_assessments($quest, $user)) {
        $result->info .= count($assessments) . " " . get_string("assessments", "quest") . "<br>";
        foreach ($assessments as $assessment) {
            $result->time = $assessment->dateassessment;
            break;
        }
    }
    return $result;
}
/**
 *
 * @param \stdClass $course
 * @param \stdClass $user
 * @param \stdClass $mod
 * @param \stdClass $quest
 */
function quest_user_complete($course, $user, $mod, $quest) {
    // Print a detailed representation of what a user has done with
    // a given particular instance of this module, for user activity reports.
    global $DB, $OUTPUT;
    if ($submissions = $DB->get_records_select("quest_submissions", "questid=? AND userid=?", [$quest->id, $user->id])) {
        foreach ($submissions as $submission) {
            echo get_string('submission', 'quest') . ': ' . $submission->title . '<br />';
            quest_print_feedback($course, $submission, $user);
        }
    } else {
        print_string('notsubmittedyet', 'quest');
    }

    $nanswers = 0;
    if ($submissions = $DB->get_records_select("quest_submissions", "questid = ?", [$quest->id])) {

        foreach ($submissions as $submission) {
            if ($answers = $DB->get_records_select("quest_answers", "questid=? and submissionid=? and userid=?",
                    [$quest->id, $submission->id, $user->id])) {
                foreach ($answers as $answer) {
                    $nanswers++;
                    echo $OUTPUT->box_start('block');
                    echo '<table cellspacing="0" class="workshop_feedbackbox">';

                    echo '<tr>';

                    echo get_string('submission', 'quest') . ': ' . $submission->title . '<br />' . '</td>';

                    echo '</tr>';

                    echo '<tr>';
                    echo '<td>';
                    echo get_string('answername', 'quest') . ' ' . $answer->title . ': </td>';
                    echo '</tr>';

                    echo "<tr>";
                    echo '<td>' . get_string('dateanswer', 'quest') . ': ';
                    echo userdate($answer->date, get_string('datestrmodel', 'quest')) . '</td>';

                    echo '</tr>';

                    echo '</table>';
                    echo $OUTPUT->box_end();
                }
            }
        }
    }
    if ($nanswers == 0) {
        echo ' ';
        print_string('notsubmittedanswers', 'quest');
    }
}
/**
 *
 * @param \stdClass $course
 * @param \stdClass $submission
 * @param \stdClass $user
 */
function quest_print_feedback($course, $submission, $user) {
    global $CFG, $rating, $DB, $OUTPUT;

    $strgrade = get_string('grade', 'quest');
    $strnograde = get_string('nograde', 'quest');
    $strnoanswers = get_string('noanswers', 'quest');
    $strnoassessments = get_string('noassessments', 'quest');

    if (!$answers = $DB->get_records('quest_answers', ['submissionid' => $submission->id])) {

        echo '<table cellspacing="0" class="workshop_feedbackbox">';
        echo '<tr>';
        echo '<td>';
        echo $OUTPUT->user_picture($user, ['courseid' => $course->id]);
        echo '</td>';
        echo '<td>' . fullname($user) . '</td>';

        echo '</tr>';
        echo '<tr>';
        echo '<td></td><td>';
        echo $strnoanswers . '</td>';
        echo '</tr>';
        echo '</table>';

        return;
    }
    foreach ($answers as $answer) {

        if (!$feedbacks = $DB->get_records('quest_assessments', ['answerid' => $answer->id])) {
            echo '<table cellspacing="0" class="workshop_feedbackbox">';
            echo '<tr>';
            echo '<td>';
            echo $OUTPUT->user_picture($user, ['courseid' => $course->id]);
            echo '</td>';
            echo '<td>' . fullname($user) . '</td>';

            echo '</tr>';
            echo '<tr>';
            echo '<td></td><td>';
            echo $strnoassessments . '</td>';
            echo '</tr>';
            echo '</table>';
            return;
        }

        foreach ($feedbacks as $feedback) {

            echo '<table cellspacing="0" class="workshop_feedbackbox">';

            echo '<tr>';
            echo '<td>';
            echo $OUTPUT->user_picture($user, ['courseid' => $course->id]);
            echo '</td>';
            echo '<td align="left">' . fullname($user) . '</td>';

            echo '</tr>';

            echo '<tr>';
            echo '<td></td><td>';
            echo get_string('answername', 'quest') . ' ' . $answer->title . ': </td>';
            echo '</tr>';

            echo "<tr><td></td>";

            echo '<td>' . get_string('timeassessment', 'quest') . ': ';
            echo format_time($feedback->dateassessment - $answer->date) . '</td>';

            echo '</tr><tr>';

            echo '<td></td><td>';

            $context = context_course::instance($course->id);
            $ismanager = has_capability('mod/quest:manage', $context);
            $canpreview = has_capability('mod/quest:preview', $context);

            if ($ismanager) {
                if ($feedback->teacherid == $user->id) {
                    if ($feedback->pointsteacher) {
                        echo $strgrade . ': ' . $feedback->pointsteacher;
                    } else {
                        echo $strnograde;
                    }
                } else {
                    echo $strnograde;
                }
            } else {
                if ($feedback->pointsautor) {
                    echo $strgrade . ': ' . $feedback->pointsautor;
                } else {
                    echo $strnograde;
                }
            }

            echo '</td></tr>';

            echo '</table>';
        }
    }
}
/**
 *
 * @param \stdClass $course
 * @param bool $isteacher
 * @param int $timestart
 * @return boolean
 */
function quest_is_recent_activity($course, $isteacher, $timestart) {
    // Given a course and a time, this module should find recent activity
    // that has occurred in QUEST activities and print it out.
    // Return true if there was output, or false is there was none.
    global $CFG;
    require_once('locallib.php');
    $assessmentcontent = false;
    if (!$isteacher) { // ...teachers only need to see submissions.
        if ($logs = quest_get_assessments_logs($course, $timestart)) {
            // ...got some, see if any belong to a visible module.
            foreach ($logs as $log) {
                // Create a temp valid module structure (only need courseid, moduleid).
                $tempmod->course = $course->id;
                $tempmod->id = $log->questid;
                // Obtain the visible property from the instance.
                if (instance_is_visible("quest", $tempmod)) {
                    $assessmentcontent = true;
                    break;
                }
            }
        }
    }
    return false; // True if anything was printed, otherwise false.
}

/**
 * Get one user who acts as a teacher.
 *
 * @param \stdClass $courseid */
function quest_get_teacher($courseid) {
    $context = context_course::instance($courseid);
    $members = get_users_by_capability($context, 'mod/quest:manage');
    return reset($members);
}

/**
 *
 * @param \stdClass $course
 * @param \stdClass $quest
 * @param \stdClass $submission
 * @param \stdClass $userfrom
 * @param \stdClass $userto
 * @param \stdClass $user
 * @param \stdClass $cm
 * @return string
 */
function quest_make_mail_text($course, $quest, $submission, $userfrom, $userto, $user, $cm) {
    global $CFG;

    $userto = get_complete_user_data('id', $userto->id);
    $by = new stdClass();
    $by->name = fullname($user);
    $by->date = userdate($submission->timecreated, "", $userto->timezone);

    $strbynameondate = get_string('bynameondate', 'quest', $by);

    $strquests = get_string('quests', 'quest');

    $posttext = '';

    $posttext = "$course->shortname -> $strquests -> " . format_string($quest->name, true);

    $subject = get_string('emailaddsubmissionsubject', 'quest');

    $posttext .= " -> " . format_string($submission->title, true);

    $posttext .= "\n\r---------------------------------------------------------------------\n\r";
    $posttext .= format_string($subject, true);

    $posttext .= "\n\r" . $strbynameondate . "\n\r";
    $posttext .= "\n\r---------------------------------------------------------------------\n\r";
    $site = get_site();
    $data = new stdClass();
    $data->firstname = fullname($userto);
    $data->sitename = $site->fullname;
    $data->admin = $CFG->supportname . ' (' . $CFG->supportemail . ')';
    $data->title = $submission->title;
    $data->name = $quest->name;
    $data->link = $CFG->wwwroot . "/mod/quest/challenges.php?id=$cm->id&amp;cid=$submission->id&amp;action=showchallenge";
    $message = get_string('emailaddsubmission', 'quest', $data);

    $posttext .= format_text_email($message, 1);
    $posttext .= "\n\r";

    return $posttext;
}
/**
 *
 * @param \stdClass $course
 * @param \stdClass $quest
 * @param \stdClass $submission
 * @param \stdClass $userfrom
 * @param \stdClass $userto
 * @param \stdClass $user
 * @param \stdClass $cm
 * @return string
 */
function quest_make_mail_html($course, $quest, $submission, $userfrom, $userto, $user, $cm) {
    global $CFG;

    $userto = get_complete_user_data('id', $userto->id);

    if ($userto->mailformat != 1) { // Needs to be HTML.
        return '';
    }

    $strquests = get_string('quests', 'quest');

    $posthtml = '<div class="navbar">' . '<a target="_blank" href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">' .
             $course->shortname . '</a> &raquo; ' . '<a target="_blank" href="' . $CFG->wwwroot . '/mod/quest/index.php?id=' .
             $course->id . '">' . $strquests . '</a> &raquo; ' . '<a target="_blank" href="' . $CFG->wwwroot .
             '/mod/quest/view.php?id=' . $cm->id . '">' . format_string($quest->name, true) . '</a>';

    $posthtml .= ' &raquo; <a target="_blank" href="' . $CFG->wwwroot . '/mod/quest/challenges.php?id=' . $cm->id .
             '&amp;action=showchallenge&amp;cid=' . $submission->id . '">' . format_string($submission->title, true) . '</a></div>';

    $posthtml .= quest_make_mail_post($quest, $userfrom, $userto, $course, $user, $submission, $cm);

    return $posthtml;
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $userfrom
 * @param \stdClass $userto
 * @param \stdClass $course
 * @param \stdClass $user
 * @param \stdClass $submission
 * @param \stdClass $cm
 * @return string
 */
function quest_make_mail_post($quest, $userfrom, $userto, $course, $user, $submission, $cm) {
    // Given the data about a posting, builds up the HTML to display it and
    // returns the HTML in a string. This is designed for sending via HTML email.
    global $CFG, $OUTPUT;

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT->user_picture($user, ['popup' => false]);
    $output .= '</td>';

    $output .= '<td class="topic starter">';

    $subject = get_string('emailaddsubmissionsubject', 'quest');

    $output .= '<div class="subject">' . $subject . '</div>';

    $context = context_module::instance($cm->id);
    $ismanagerto = has_capability('mod/quest:manage', $context, $userto->id);

    $fullname = fullname($user, $ismanagerto);
    $by = new stdClass();
    $by->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $course->id . '">' . $fullname .
             '</a>';
    $by->date = userdate($submission->timecreated, '', $user->timezone);
    $output .= '<div class="author">' . get_string('bynameondate', 'forum', $by) . '</div>';
    $output .= '</td></tr>';
    $output .= '<tr><td class="left side"> </td><td class="content">';

    $site = get_site();
    $data = new stdClass();
    $data->admin = $CFG->supportname . ' (' . $CFG->supportemail . ')';
    $data->firstname = fullname($userto);
    $data->sitename = $site->fullname;
    $data->title = $submission->title;
    $data->name = $quest->name;
    $data->link = $CFG->wwwroot . "/mod/quest/challenges.php?id=$cm->id&amp;cid=$submission->id&amp;action=showchallenge" .
             '&amp;p=' . $userto->secret . '&amp;s=' . $userto->username;
    $message = get_string('emailaddsubmission', 'quest', $data);
    $messagehtml = text_to_html($message, false, false, true);
    $output .= $messagehtml;
    $output .= '</td></tr></table>' . "\n\n";
    return $output;
}

/** Lists all gradable areas for the advanced grading methods framework
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values */
function quest_grading_areas_list() {
    return ['individual' => get_string('individualcalification', 'quest'), 'team' => get_string('pointsteam', 'quest')];
}

/** Create grade item for given quest.
 *
 * @param stdClass $assign record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise */
function quest_grade_item_update($quest, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!isset($quest->courseid)) {
        $quest->courseid = $quest->course;
    }

    $params = ['itemname' => $quest->name, 'idnumber' => $quest->id];

    // Questournament grades as a % of the maxscore in the ranking table...
    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax'] = floatval(100); // Grade is always normalized to other users
                                         // maxcalification...
    $params['gradepass'] = floatval(50);
    $params['grademin'] = 0;

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    setlocale(LC_NUMERIC, 'C'); // JPC Moodle applies numeric locale to cast strings cheating mysql.
    return grade_update('mod/quest', $quest->courseid, 'mod', 'quest', $quest->id, 0, $grades, $params);
}

/** Return grade for given user or all users.
 *
 * @param stdClass $assign record of assign with an additional cmidnumber
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none */
function quest_get_user_grades($quest, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quest/locallib.php');
    if ($quest = $DB->get_record("quest", ["id" => $quest->id], '*', MUST_EXIST)) {
        $course = get_course($quest->course);
        $cm = get_coursemodule_from_instance("quest", $quest->id, $course->id, null, null, MUST_EXIST);
        $groupmode = groups_get_activity_group($cm);
        $maxpoints = -1;
        $maxpointsgroup = null;

        if ($userid != 0) {
            $students = [$userid => get_complete_user_data('id', $userid)];
        } else {
            $students = quest_get_course_students($quest->course);
        }
        if ($students) {
            $return = [];

            $maxpoints = -1; // ...uncalculated start value.
            $maxpointsgroup = []; // ...group points cache.
            $textinfo = "";
            foreach ($students as $student) {
                // Get maximum scores...
                if ($groupmode != false) {
                    if ($groupmember = $DB->get_record("groups_members", ["userid" => $student->id])) {
                        // Cache maxpoints for this group...
                        if ($maxpointsgroup[$groupmember->groupid]) {
                            $maxpoints = $maxpointsgroup[$groupmember->groupid];
                        } else {
                            if ($quest->typegrade == QUEST_TYPE_GRADE_INDIVIDUAL) { // Grading by
                                                                                    // individuals...
                                $maxpoints = quest_get_maxpoints_group($groupmember->groupid, $quest);
                            } else if ($quest->typegrade == QUEST_TYPE_GRADE_TEAM) { // Grading by
                                                                                     // teams...
                                $maxpoints = quest_get_maxpoints_group_teams($groupmember->groupid, $quest);
                            }

                            $maxpointsgroup[$groupmember->groupid] = $maxpoints;
                        }
                    }
                } else if ($maxpoints == -1) {
                    // ...no se usan grupos.
                    // ...avoid to query more than once.
                    if ($quest->typegrade == QUEST_TYPE_GRADE_INDIVIDUAL) { // Grading by
                                                                            // individuals...
                        $maxpoints = quest_get_maxpoints($quest);
                    } else if ($quest->typegrade == QUEST_TYPE_GRADE_TEAM) { // Grading by teams ...
                        $maxpoints = quest_get_maxpoints_teams($quest);
                    }
                }
                // Calculate proportionally...
                if ($calificationstudent = $DB->get_record("quest_calification_users",
                        ["questid" => $quest->id, "userid" => $student->id])) {
                    $points = 0;
                    if ($quest->typegrade == QUEST_TYPE_GRADE_INDIVIDUAL) { // Grading by
                                                                            // individuals...
                        $points = $calificationstudent->points;
                    }
                    $textinfo = number_format($calificationstudent->points, 1) . " points";

                    if ($quest->allowteams) { // Add team score...
                        if ($calificationteam = $DB->get_record("quest_calification_teams",
                                ["questid" => $quest->id, "teamid" => $calificationstudent->teamid])) {
                            $points += $calificationteam->points * $quest->teamporcent / 100;
                            $textinfo .= "+ $quest->teamporcent% of " .
                                        number_format($calificationteam->points, 1) . " team points";
                        }
                    }
                    $textinfo = number_format($points, 1) . " points = " . $textinfo;
                    $textinfo .= "/Max. " . number_format($maxpoints, 1);
                    $rawgrade = $maxpoints == 0 ? 0 : $points / $maxpoints * $quest->maxcalification;
                    $textinfo .= " (" . number_format($points, 1) . "/" . number_format($maxpoints, 1) . ") (" .
                             number_format($rawgrade, 1) . "% of $quest->maxcalification)";
                    // Grade API needs userid, rawgrade, feedback, feedbackformat, usermodified,
                    // ...dategraded, datesubmitted.
                    $grade = new stdClass();
                    $grade->userid = $student->id;
                    $grade->maxgrade = "100";
                    $grade->rawgrade = floatval($rawgrade); // Preserve decimal grades for legacy Moodle data.
                                                            // moodle 2.5??
                    $grade->feedback = $textinfo;
                    $grade->feedbackformat = FORMAT_PLAIN;
                    $return[$student->id] = $grade;
                } // ...student has calification.
            } // ...foreach student in list.
        } else { // No students.
            $return = false;
        }
    } else {
        $return = false;
    }
    return $return;
}

/** Update activity grades.
 *
 * @param stdClass $quest database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used */
function quest_update_grades($quest, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = quest_get_user_grades($quest, $userid);

    if ($grades) {
        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        quest_grade_item_update($quest, $grades);
    } else {
        quest_grade_item_update($quest);
    }
}
/**
 *
 * @param int $questid
 * @return array
 */
function quest_get_participants($questid) {
    // Must return an array of user records (all data) who are participants
    // for a given instance of QUEST. Must include every user involved
    // in the instance, independient of his role (student, teacher, admin...)
    // See other modules as example.
    global $CFG, $DB;

    // Get students from quest_submissions.
    $stsubmissions = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.id FROM {user} u, {quest_submissions} s " .
            "WHERE s.questid = ? and u.id = s.userid", [$questid]);
    // Get students from quest_assessments.
    $stassessments = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.id FROM {user} u, {quest_assessments} a " .
            "WHERE a.questid = ? and ( u.id = a.userid or u.id = a.teacherid )", [$questid]);

    // Get students from quest_comments.
    $stanswers = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.id FROM {user} u, {quest_answers} c " .
            "WHERE c.questid = ? and u.id = c.userid", [$questid]);

    // Add st_answers to st_submissions.
    if ($stanswers) {
        foreach ($stanswers as $stanswer) {
            $stsubmissions[$stanswer->id] = $stanswer;
        }
    }
    // Add st_assessments to st_submissions.
    if ($stassessments) {
        foreach ($stassessments as $stassessment) {
            $stsubmissions[$stassessment->id] = $stassessment;
        }
    }

    // Return st_submissions array (it contains an array of unique users).
    return ($stsubmissions);
}

/**
 * Return whether a scale is used by one Quest instance.
 *
 * it it has support for grading and scales. */
function quest_scale_used($questid, $scaleid) {
    $return = false;
    return $return;
}

/**
 * Return whether a scale is used by any Quest instance.
 *
 * it it has support for grading and scales. */
function quest_scale_used_anywhere($scaleid) {
    $return = false;
    return $return;
}
/**
 *
 * @param number $courseid
 * @return boolean
 */
function quest_refresh_events($courseid = 0) {
    // This standard function will check all instances of this module
    // and make sure there are up-to-date events created for each of them.
    // If courseid = 0, then every quest event in the site is checked, else
    // only quest events belonging to the course specified are checked.
    // This function is used, in its new format, by restore_refresh_events().
    global $DB;
    if ($courseid == 0) {
        if (!$quests = $DB->get_records("quest")) {
            return true;
        }
    } else {
        if (!$quests = $DB->get_records("quest", ["course" => $courseid])) {
            return true;
        }
    }
    foreach ($quests as $quest) {
        quest_update_quest_calendar($quest);
    }
    return true;
}
/**
 *
 * @param \stdClass[] $activities
 * @param int $index
 * @param int $sincetime
 * @param \stdClass $courseid
 * @param string $questcmid
 * @param string $user
 * @param string $groupid
 */
function quest_get_recent_mod_activity(&$activities, &$index, $sincetime, $courseid, $questcmid = "0", $user = "", $groupid = "") {
    // Returns all quest posts since a given time. If quest is specified then
    // this restricts the results.
    global $CFG, $USER, $DB;

    if ($questcmid) {
        $questselect = " AND cm.id = :quest";
        $params = ['quest' => $questcmid];
    } else {
        $questselect = "";
        $params = [];
    }

    if ($user) {
        $userselect = " AND u.id = :user";
        $params = array_merge($params, ['user' => $user]);
    } else {
        $userselect = "";
    }
    $context = context_module::instance($questcmid);

    if (!has_capability('mod/quest:manage', $context)) {
        $selectuser = " AND s.userid = :userid";
        $params = array_merge($params, ['userid' => $USER->id]);
    } else {
        $selectuser = "";
    }
    // ... get challenges submitted.
    $params = array_merge($params, ['sincetime' => $sincetime], ['course' => $courseid]);
    $posts = $DB->get_records_sql(
            "SELECT s.id, s.userid, s.title, s.timecreated, u.firstname, u.lastname,
            u.picture, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, cm.instance, q.name, cm.section
            FROM {quest_submissions} s
            JOIN {user} u ON s.userid = u.id
            JOIN {quest} q ON s.questid = q.id
            JOIN {course_modules} cm ON cm.instance = q.id
            WHERE s.timecreated  > :sincetime
            $questselect
            $userselect $selectuser
            AND q.course = :course
            AND cm.course = q.course
            ORDER BY s.id", $params);

    if (!empty($posts)) {
        foreach ($posts as $post) {

            if (empty($groupid) || groups_is_member($groupid, $post->userid)) {

                $tmpactivity = new stdClass();
                $tmpactivity->cmid = $questcmid;
                $tmpactivity->type = "quest";
                $tmpactivity->defaultindex = $index;
                $tmpactivity->instance = $post->instance;
                $tmpactivity->name = $post->name;
                $tmpactivity->section = $post->section;

                $tmpactivity->content = new stdClass();
                $tmpactivity->content->id = 'challenges.php?action=showchallenge&amp;id=' . $questcmid . '&amp;cid=' . $post->id;
                $tmpactivity->content->title = $post->title;

                $tmpactivity->user = new stdClass();
                $additionalfields = ['id' => 'userid', 'picture', 'imagealt', 'email'];
                $additionalfields = explode(',', user_picture::fields());
                $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
                $tmpactivity->user->userid = $post->userid;
                $tmpactivity->timestamp = $post->timecreated;
                $activities[$index++] = $tmpactivity;
            }
        }
    }
    // ... get the answers submitted.
    $posts = $DB->get_records_sql(
            "SELECT a.*, u.firstname, u.lastname,
            u.picture, u.imagealt, u.email, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
            cm.instance, q.name, cm.section
            FROM {quest_answers} a
            JOIN {user} u ON a.userid = u.id
            JOIN {quest} q ON a.questid = q.id
            JOIN {course_modules} cm ON cm.instance = q.id
            WHERE a.date  > :sincetime $questselect
            $userselect $selectuser
            AND q.course = :course
            AND cm.course = q.course
            ORDER BY a.id", $params);

    if (!empty($posts)) {
        foreach ($posts as $post) {

            if (empty($groupid) || groups_is_member($groupid, $post->userid)) {

                $tmpactivity = new \stdClass();

                $tmpactivity->type = "quest";
                $tmpactivity->defaultindex = $index;
                $tmpactivity->instance = $post->instance;
                $tmpactivity->name = $post->name;
                $tmpactivity->section = $post->section;

                $tmpactivity->content->id = 'answer.php?action=showanswer&amp;sid=' . $post->submissionid . '&amp;aid=' . $post->id;
                $tmpactivity->content->title = $post->title;

                $tmpactivity->user = new stdClass();
                $additionalfields = ['id' => 'userid', 'picture', 'imagealt', 'email'];
                $additionalfields = explode(',', user_picture::fields());
                $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
                $tmpactivity->user->userid = $post->userid;

                $tmpactivity->timestamp = $post->date;
                $activities[] = $tmpactivity;
                $index++;
            }
        }
    }
    return;
}

/** API funtion for reporting recent aactivity
 *
 * @param \stdClass $activity
 * @param \stdClass $course
 * @param bool $detail
 * @return */
function quest_print_recent_mod_activity($activity, $course, $detail = false) {
    global $CFG, $USER, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0">';

    if (!empty($activity->content->parent)) {
        $openformat = "<font size=\"2\"><i>";
        $closeformat = "</i></font>";
    } else {
        $openformat = "<b>";
        $closeformat = "</b>";
    }

    echo "<tr>";

    echo "<td class=\"workshoppostpicture\" width=\"35\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user);
    echo "</td>";

    echo "<td>$openformat";

    if ($detail) {
        echo "<img src=\"$CFG->modpixpath/$activity->type/icon.gif\" " . "height=\"16\" width=\"16\" alt=\"" .
                 strip_tags(format_string($activity->name, true)) . "\" />  ";
    }

    echo "<a href=\"$CFG->wwwroot/mod/quest/" . $activity->content->id . "\">" . $activity->content->title;

    echo "</a>$closeformat";

    echo "<br /><font size=\"2\">";
    echo "<a href=\"$CFG->wwwroot/user/view.php?id=" . $activity->user->userid . "&amp;course=" . "$course\">" .
             fullname($activity->user) . "</a>";
    echo " - " . userdate($activity->timestamp) . "</font></td></tr>";

    echo "</table>";

    return;
}

/*
 * ******
 * RESET course
 * *********
 */

/**
 * Add Quest options to the course reset form.
 *
 * @param moodleform $mform form passed by reference */
function quest_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'questournamentheader', get_string('modulenameplural', 'quest'));

    $mform->addElement('checkbox', 'reset_quest_all_answers', get_string('resetquestallanswers', 'quest'));
}
/**
 * Course reset form defaults.
 * @param \stdClass $course
 * @return number[]
 */
function quest_reset_course_form_defaults($course) {
    return ['reset_quest_all_answers' => 0];
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all answers, teams, and evaluations from the specified questournament
 * and clean up any related data.
 * @param \stdClass $data the data submitted from the reset course.
 * @return array status array */
function quest_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/filelib.php');

    $componentstr = get_string('modulenameplural', 'quest');
    $status = [];

    $removeanswers = false;

    if (!empty($data->reset_quest_all_answers)) {
        $removeanswers = true;
        $typesql = "";
        $typesstr = get_string('resetquestallanswers', 'quest');
        $types = [];
    }
    $questidssql = $DB->get_records('quest', ['course' => $data->courseid], '', 'id');
    $questids = [];
    foreach ($questidssql as $quid) {
        $questids[] = $quid->id;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($questids);
    $answerssql = "SELECT a.id as id FROM mdl_quest_answers a, mdl_quest q WHERE q.course=? and a.questid=q.id";
    $answerparams = [$data->courseid];

    if ($removeanswers) {

        $DB->delete_records_select('quest_elements_assessments', "questid $insql", $inparams);
        $DB->delete_records_select('quest_items_assesments_autor', "questid $insql", $inparams);
        $DB->delete_records_select('quest_assessments', "questid $insql", $inparams);
        $DB->delete_records_select('quest_assessments_autors', "questid $insql", $inparams);
        // ...remove califications.
        $DB->delete_records_select('quest_calification_users', "questid $insql", $inparams);
        $DB->delete_records_select('quest_calification_teams', "questid $insql", $inparams);
        // ...delete all teams.
        $DB->delete_records_select('quest_teams', "questid $insql", $inparams);

        // ...now get rid of all attachments.
        if ($answers = $DB->get_records_sql($answerssql, $answerparams)) {
            foreach ($answers as $answerid => $unused) {
                fulldelete($CFG->dataroot . '/' . $data->courseid . '/moddata/quest/answers/' . $answerid);
            }
        }

        // ...delete all answers.
        $DB->delete_records_select('quest_answers', "questid $insql", $inparams);

        // ...reset counters.
        // evp hay que pensar si sustituir la siguiente consulta ya que no recomienda untilizar
        // execute.
        $resetsubmissions = "UPDATE {quest_submissions} SET
			nanswers = 0,
			nanswerscorrect = 0,
			dateanswercorrect = 0,
			pointsanswercorrect = 0,
			mailed = 0,
			maileduser = 0
            WHERE questid $insql";

        $DB->execute($resetsubmissions, $inparams);

        $status[] = ['component' => $componentstr, 'item' => $typesstr, 'error' => false];
    }

    // ...updating dates - shift may be negative too.
    if ($data->timeshift) {

        shift_course_mod_dates('quest', ['datestart', 'dateend'], $data->timeshift, $data->courseid);
        $shifttimesql = "UPDATE {quest_submissions} " .
                        "SET datestart = datestart + (?), dateend = dateend + (?) " .
                        "WHERE questid  $insql and datestart<>0";
        $shiftparams = array_merge([$data->timeshift, $data->timeshift], $inparams);
        $DB->execute($shifttimesql, $shiftparams);

        $status[] = ['component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false];
    }

    return $status;
}

/**
 * Obtains the automatic completion state for this module based on any conditions in game settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 *
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function quest_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    if (($cm->completion == 0) || ($cm->completion == 1)) {
        // Completion option is not enabled so just return $type.
        return $type;
    }
    $quest = $DB->get_record('quest', ['id' => $cm->instance], '*', MUST_EXIST);
    // Check for passing grade.
    if ($quest->completionpass) {
        require_once($CFG->libdir . '/gradelib.php');
        $item = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod',
                        'itemmodule' => 'quest', 'iteminstance' => $cm->instance, 'outcomeid' => null]);
        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, [$userid], false);
            if (!empty($grades[$userid])) {
                $passed = $grades[$userid]->is_passed($item);
                return $passed;
            }
        }
    }

    return $type;
}
/** Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $questnode The node to add module settings to */
function quest_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $questnode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $questobject = $DB->get_record("quest", ["id" => $PAGE->cm->instance]);
    $context = $PAGE->cm->context;

    $questnode->add('Questournaments', new moodle_url('/mod/quest/index.php', ['id' => $PAGE->course->id]),
            navigation_node::TYPE_SETTING);

    // ...manage Teams.
    if (has_capability('mod/quest:manage', $context)) {
        if ($questobject && $questobject->allowteams) {
            $questnode->add(get_string('changeteamteacher', 'quest'),
                    new moodle_url('/mod/quest/team.php', ['id' => $PAGE->cm->id, 'action' => 'change']),
                    navigation_node::TYPE_SETTING);
        }
    }
    if (has_capability('mod/quest:downloadlogs', $context)) {
        $catnode = $questnode->add(get_string('adminlogs', 'quest'), null, navigation_node::TYPE_CONTAINER);
        $catnode->add(get_string('gettechnicallogs', 'quest'),
                new moodle_url('/mod/quest/getLogs.php', ['id' => $PAGE->cm->id]),
                navigation_node::TYPE_SETTING);
        $catnode->add(get_string('fullactivitylisting', 'quest'),
                new moodle_url('/mod/quest/report.php', ['id' => $PAGE->cm->id]),
                navigation_node::TYPE_SETTING);
    }

    // Question bank integration.
    if (has_capability('moodle/question:managecategory', $context) ||
            has_capability('moodle/question:add', $context) ||
            has_capability('moodle/question:viewall', $context)) {
        require_once($CFG->libdir . '/questionlib.php');
        $qnode = question_extend_settings_navigation($questnode, $context);
        if ($qnode) {
            $qnode->trim_if_empty();
        }
    }
}
/**
 *
 * @param \stdClass $submission
 * @param \stdClass $user
 * @return array
 */
function quest_get_user_answers($submission, $user) {
    global $DB;
    return $DB->get_records_select("quest_answers", "submissionid = ? AND userid = ? AND date > 0",
            [$submission->id, $user->id], "date DESC");
}
/**
 * Gets all answers for a challenge.
 *
 * @param \stdClass $challenge
 * @return array
 */
function quest_get_challenge_answers($challenge) {
    global $DB;
    return $DB->get_records_select("quest_answers", "submissionid = ? AND date > 0", [$challenge->id], "date DESC");
}

/**
 * Legacy wrapper for quest_get_challenge_answers.
 *
 * @param \stdClass $submission
 * @return array
 */
function quest_get_submission_answers($submission) {
    return quest_get_challenge_answers($submission);
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $user
 * @return array
 */
function quest_get_user_answer($quest, $user) {
    global $DB;
    return $DB->get_records_select("quest_answers", "questid = ? AND userid = ? AND date > 0", [$quest->id, $user->id],
            "date DESC");
}
/**
 *
 * @param \stdClass $quest
 * @param \stdClass $user
 * @return array
 */
function quest_get_user_assessments($quest, $user) {
    global $DB;
    return $DB->get_records_select("quest_assessments", "questid = ? AND userid = ? AND dateassessment > 0",
            [$quest->id, $user->id], "dateassessment DESC");
}
/**
 *
 * @param \stdClass $answer
 * @return array
 */
function quest_get_user_assessment($answer) {
    global $DB;
    return $DB->get_records_select("quest_assessments", "answerid = ? AND dateassessment > 0", [$answer->id],
            "dateassessment DESC");
}
/**
 * Return the maximum team score for a Quest.
 *
 * @param stdClass $quest Quest record.
 * @return number Maximum score.
 */
function quest_get_maxpoints_teams(stdClass $quest) {
    global $DB;
    $maxpoints = -1;

    $calificationsteam = $DB->get_records('quest_calification_teams', ["questid" => $quest->id]);
    foreach ($calificationsteam as $calificationteam) {
        $grade = $calificationteam->points;
        if ($grade > $maxpoints) {
            $maxpoints = $grade;
        }
    }
    return $maxpoints;
}

/**
 * Build the standard question-bank parameters for a Quest fragment.
 *
 * @param array $params Query parameters.
 * @param array $extraparams Additional view parameters.
 * @return array Question-bank resources and parameters.
 */
function mod_quest_build_required_parameters_for_custom_view(array $params, array $extraparams): array {
    global $CFG;
    require_once($CFG->dirroot . "/question/editlib.php");
    $viewclass = $extraparams['view'] ?? null;
    $defaultpagesize = $viewclass ? $viewclass::DEFAULT_PAGE_SIZE : 20;
    [$thispageurl, $contexts, $cmid, $cm, , $pagevars] = question_build_edit_resources(
            'editq',
            '/mod/quest/challenges.php',
            array_merge($params, $extraparams),
            $defaultpagesize);
    $extraparams['cmid'] = $cmid;
    if (\mod_quest\question\bank_provider::has_bank_helper()) {
        $extraparams['requirebankswitch'] = true;
    }
    return [$contexts, $thispageurl, $cm, $pagevars, $extraparams];
}

/**
 * Render the complete official question bank view in the Quest picker.
 *
 * @param array $args Fragment arguments.
 * @return string Rendered question-bank view.
 */
function mod_quest_output_fragment_quest_question_bank(array $args): string {
    $params = [];
    $extraparams = [];
    $querystring = parse_url($args['querystring'] ?? '', PHP_URL_QUERY) ?? '';
    parse_str($querystring, $params);

    $params['cmid'] = clean_param($args['bankcmid'] ?? 0, PARAM_INT);
    $viewclass = \mod_quest\question\bank\custom_view::class;
    $extraparams['view'] = $viewclass;
    $extraparams['questcmid'] = clean_param($args['questcmid'] ?? 0, PARAM_INT);

    if (empty($params['cmid'])) {
        $params['cmid'] = $extraparams['questcmid'];
    }

    $destination = \context_module::instance($extraparams['questcmid']);
    require_capability("mod/quest:manage", $destination);
    \mod_quest\question\bank_provider::require_bank($params['cmid']);

    [$contexts, $thispageurl, $cm, $pagevars, $extraparams] =
            mod_quest_build_required_parameters_for_custom_view($params, $extraparams);

    $course = get_course($cm->course);
    $questionbank = new $viewclass($contexts, $thispageurl, $course, $cm, $pagevars, $extraparams);

    ob_start();
    try {
        $questionbank->display();
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

/**
 * Render filtered and paginated question data for the official view.
 *
 * @param array $args Fragment arguments.
 * @return string Rendered question-bank data.
 */
function mod_quest_output_fragment_quest_question_data(array $args): string {
    if (empty($args)) {
        return '';
    }

    [$params, $extraparams] = \core_question\local\bank\filter_condition_manager::extract_parameters_from_fragment_args($args);

    $cmid = clean_param($args['cmid'] ?? $args['bankcmid'] ?? 0, PARAM_INT);
    $questcmid = clean_param($extraparams['questcmid'] ?? $args['questcmid'] ?? 0, PARAM_INT);
    if (empty($cmid)) {
        $cmid = $questcmid;
    }
    $params['cmid'] = $cmid;

    $extraparams['questcmid'] = $questcmid;
    $extraparams['view'] = clean_param($args['view'] ?? \mod_quest\question\bank\custom_view::class, PARAM_NOTAGS);

    $destination = \context_module::instance($questcmid);
    require_capability("mod/quest:manage", $destination);
    \mod_quest\question\bank_provider::require_bank($params['cmid']);

    [$contexts, $thispageurl, $cm, $pagevars, $extraparams] =
            mod_quest_build_required_parameters_for_custom_view($params, $extraparams);

    $course = get_course($cm->course);
    $viewclass = $extraparams['view'];
    $questionbank = new $viewclass($contexts, $thispageurl, $course, $cm, $pagevars, $extraparams);

    $questionbank->add_standard_search_conditions();
    ob_start();
    $questionbank->display_question_list();
    return ob_get_clean();
}
