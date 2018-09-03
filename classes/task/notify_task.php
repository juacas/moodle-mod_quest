<?php
// This file is part of QUESTournament for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
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
/** For debugging:
 * SET XDEBUG_CONFIG=netbeans-xdebug=xdebug
 * php.exe admin\tool\task\cli\schedule_task.php --execute=\mod_msocial\task\notify_task */
namespace mod_quest\task;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

global $CFG;
require_once($CFG->dirroot . '/mod/quest/lib.php');
require_once($CFG->dirroot . '/mod/quest/locallib.php');

class notify_task extends \core\task\scheduled_task {

    public function get_name() {
        // Shown in admin screens.
        return "QUESTournament notify tasks.";
    }

    public function execute() {
        global $COURSE;
        global $CFG, $USER, $SITE, $DB;
        /* @var $DB \moodle_database */
        global $DB;
        $courseid = $COURSE->id;
        mtrace("\n============================");
        mtrace(" QUESTournament notify tasks.");
        mtrace("==============================");
        // Function to be run periodically according to the moodle cron
        // This function searches for things that need to be done, such
        // as sending out mail, toggling flags etc ...
        $timestart = time();
        $timeref = time() - 24 * 3600;
        $userfrom = null;

        if ($quests = $DB->get_records("quest")) {

            $urlinfo = parse_url($CFG->wwwroot);
            $hostname = $urlinfo['host'];
            // Daily actions Day brief of QUESTs activities.
            if (!isset($CFG->digestmailtimelast)) { // To catch the first time.
                set_config('questdigestmailtimelast', 0, 'quest');
            }
            // Daily digest for teachers.
            $timenow = time();
            $sitetimezone = $CFG->timezone;
            $digesttime = usergetmidnight($timenow, $sitetimezone);
            $questdigestmailtimelast = get_config('quest', 'questdigestmailtimelast');
            if ($questdigestmailtimelast < $digesttime and $timenow > $digesttime) {
                set_config('questdigestmailtimelast', $timenow, 'quest');
                mtrace('Sending QUEST digests: ' . userdate($timenow, '', $sitetimezone));

                foreach ($quests as $quest) {

                    if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
                        mtrace("Course is misconfigured");
                        continue;
                    }
                    if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
                        mtrace("Coursemodule is misconfigured");
                        continue;
                    }

                    if ($cm->visible == 0) {
                        mtrace("Coursemodule for quest no: $quest->id is disabled");
                        continue;
                    }
                    $context = \context_course::instance($course->id);
                    $userfrom = class_exists('core_user') ? \core_user::get_noreply_user() : quest_get_teacher($course->id);
                    $mailcount = 0;
                    mtrace("DAILY TASKs for quest no: $quest->id");
                    mtrace(get_string('processingquest', 'quest', $quest->id));

                    if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                        mtrace("ERROR!: There is no users");
                        continue;
                    }

                    foreach ($users as $userto) {

                        if (has_capability('mod/quest:manage', $context, $userto->id)) {

                            $indice = 0;

                            $postsubject = get_string('resumequest', 'quest', $quest);

                            $posttext = get_string('resume24hours', 'quest', $quest);
                            $posttext .= "\n\r------------------------------------------------------------\n\r";

                            $posthtml = '<head>';

                            $posthtml .= '</head>';
                            $posthtml .= "\n<body id=\"email\">\n\n";
                            $posthtml .= get_string('resume24hours', 'quest', $quest);
                            $posthtml .= "<br>-------------------------------------------------------------<br>";

                            if ($submissions = $DB->get_records("quest_submissions", array("questid" => $quest->id))) {

                                // Imprimir cabecera del m�dulo QUEST en mensaje.
                                foreach ($submissions as $submission) {
                                    // Challenge unnotified and recently created.
                                    if (($submission->timecreated > $timeref) && ($submission->mailed == 0)) {

                                        $indice++;
                                        $user = get_complete_user_data('id', $submission->userid);

                                        $cleanquestname = str_replace('"', "'", strip_tags($quest->name));
                                        $userfrom->customheaders = array( // Headers to make emails
                                                                          // easier to track.
                                        'Precedence: Bulk',
                                        'List-Id: "' . $cleanquestname . '" <moodlequest' . $quest->id . '@' .
                                         $hostname . '>',
                                        'List-Help: ' . $CFG->wwwroot . '/mod/quest/view.php?f=' . $quest->id,
                                        'X-Course-Id: ' . $course->id,
                                        'X-Course-Name: ' . strip_tags($course->fullname));
                                        if (!empty($course->lang)) {
                                            $CFG->courselang = $course->lang;
                                        } else {
                                            unset($CFG->courselang);
                                        }
                                        $USER->lang = $userto->lang;
                                        $USER->timezone = $userto->timezone;

                                        $posttext .= quest_make_mail_text($course, $quest, $submission, $userfrom, $userto, $user,
                                                $cm);

                                        $posthtml .= quest_make_mail_html($course, $quest, $submission, $userfrom, $userto, $user,
                                                $cm);
                                    } // ...if teacher.
                                } // ...for submissions.
                            }

                            // ...count of messages to send.
                            if ($indice > 0) {

                                $posthtml .= "</body>";
                                $posttext = format_text($posttext, 1);
                                mtrace("Mailing Daily briefing to user $userto->id .");

                                if (!$mailresult = email_to_user($userto, $userfrom, $postsubject, $posttext, $posthtml)) {
                                    mtrace(
                                            "Error: notify_task.php: Could not send out mail to user $userto->id" .
                                                     " ($userto->email) .. not trying again.");
                                } else if ($mailresult === 'emailstop') {
                                    mtrace("Error: notify_task.php: Error 'emailstop' when mailing to user $userto->id" .
                                            " ($userto->email) .. not trying again.");
                                } else {
                                    $mailcount++;
                                }
                            }
                        } // ...if teacher.
                    } // ...foreach user.

                    // Mark submissions as mailed...
                    if ($submissions = $DB->get_records("quest_submissions", array("questid" => $quest->id))) {
                        // Imprimir cabecera del m�dulo QUEST en mensaje.
                        foreach ($submissions as $submission) {
                            if (($submission->timecreated > $timeref) && ($submission->mailed == 0)) {
                                $submission->mailed = 1;
                                $DB->set_field("quest_submissions", "mailed", $submission->mailed, array("id" => $submission->id));
                            }
                        }
                    }
                    mtrace(".... mailed to $mailcount users.");
                } // ...foreach quests.
            } else {
                mtrace("==============================");
                mtrace("Posponing Daily tasks.");
                mtrace("==============================");
            }
            /*
             * Process all quests
             * Notify challenges recently started and unmailed
             * to studens
             * submissions already notified are marked with maileduser=1
             * maileduser marks the instant notification (actually cron tick time)
             * mailed marks the submissions mailed in a daily digest
             */
            mtrace("Searching recent events to notify to all users...");
            foreach ($quests as $quest) {
                if (!$course = $DB->get_record("course", array("id" => $quest->course))) {
                    mtrace("Course for Quest no: $quest->id is misconfigured");
                    continue;
                }
                if (!$cm = get_coursemodule_from_instance("quest", $quest->id, $course->id)) {
                    mtrace("Coursemodule for Quest no: $quest->id is misconfigured");
                    continue;
                }
                if ($cm->visible == 0) {
                    mtrace("Coursemodule is disabled");
                    continue;
                }
                if (!$users = quest_get_course_members($course->id, "u.lastname, u.firstname")) {
                    mtrace("There is no users");
                    continue;
                }
                $context = \context_course::instance($course->id);
                $submissionscount = 0;
                $userscount = 0;
                $userfrom = class_exists('core_user') ? \core_user::get_noreply_user() : quest_get_teacher($course->id);
                // For each quest group messages to avoid avalanches.
                if ($submissions = $DB->get_records("quest_submissions", array("questid" => $quest->id))) {
                    mtrace("Processing " . count($submissions) . " challenges for quest: $quest->id.");
                    $submissionsmailed = [];
                    $usermessages = []; // Users and their messages to send.
                    // Imprimir cabecera del modulo QUEST en mensaje.
                    foreach ($submissions as $submission) {
                        // The challenge has started and is approved and is not emailed yet to
                        // users.
                        if (($submission->datestart <= time())
                                && ($submission->dateend >= time())
                                && ($submission->maileduser == 0)
                                && ($submission->state != SUBMISSION_STATE_APPROVAL_PENDING)) {
                            $submissionscount++;
                            $submissionsmailed[] = $submission;
                            $userscount = 0;
                            mtrace("Challenge $submission->id has started. Messaging advice" . " to " . count($users) . " users.");
                            foreach ($users as $user) {
                                if (!has_capability('mod/quest:manage', $context, $user)) { // Only to non-teachers.
                                    $userscount++;

                                    $msgdata = quest_compose_message_data($user,
                                            "submissions.php?id=$cm->id&amp;sid=$submission->id&amp;action=showsubmission",
                                            'challenge_start', $quest, $submission, '', $userfrom);
                                    $usermessages[$user->id][] = $msgdata;
                                }
                            }

                        }
                    } // ...for submissions.
                    // Collect and send messages to users.
                    foreach ($usermessages as $userid => $usermsglist) {
                        $messagehtml = "<ul>\n";
                        foreach ($usermsglist as $usermsg) {
                            $messagehtml .= "<li>" . $usermsg->messagehtml . "</li>\n";
                        }
                        $template = reset($usermsglist);
                        $template->messagehtml = $messagehtml;
                        mtrace("Sending message (" . count($usermsglist) . " challenges: " . implode(',', array_keys($submissions)) .") to user " .
                                $template->userto->username . " in name of " . $template->userfrom->username . "\n");
                        quest_send_message_data($template);
                    }
                    // Mark challenges as mailed.
                    foreach ($submissionsmailed as $submission) {
                        $DB->set_field("quest_submissions", "maileduser", 1, array('id' => $submission->id));
                        // Update Calendar Events.
                        quest_update_challenge_calendar($cm, $quest, $submission);
                    }
                } else {
                    mtrace("Quest id: $quest->id has no challenges.");
                }
                mtrace("Quest id: $quest->id : $submissionscount submissions mailed to $userscount users.\n");
            }
        }
        mtrace("QUESTOURnament processed (" . (time() - $timestart) . " s)");
        return true;
    }
}
