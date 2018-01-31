<?php
// This file is part of Questournament activity for Moodle http://moodle.org/.
//
// Questournament for Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version..
//
// Questournament for Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the.
// GNU General Public License for more details..
//
// You should have received a copy of the GNU General Public License.
// along with Questournament for Moodle. If not, see <http://www.gnu.org/licenses/>..

/** Questournament activity for Moodle
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License.
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
 *          ********************************************************* */
// This php script contains all the stuff to backup/restore.
// quest mods.

// This function executes all the restore procedure about this mod.
function quest_restore_mods($mod, $restore) {
    global $CFG;

    $status = true;

    // Get record from backup_ids.
    $data = backup_getid($restore->backup_unique_code, $mod->modtype, $mod->id);

    if ($data) {
        // Now get completed xmlized object.
        $info = $data->info;

        // Now, build the QUEST record structure.
        $quest->course = $restore->course_id;
        $quest->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
        if (isset($info['MOD']['#']['DESCRIPTION']['0']['#'])) {
            $quest->intro = backup_todb($info['MOD']['#']['DESCRIPTION']['0']['#']);
            $quest->introformat = 0;
        } else {
            $quest->intro = backup_todb($info['MOD']['#']['INTRO']['0']['#']);
            $quest->introformat = backup_todb($info['MOD']['#']['INTROFORMAT']['0']['#']);
        }

        $quest->nelements = backup_todb($info['MOD']['#']['NELEMENTS']['0']['#']);
        $quest->nelementsautor = backup_todb($info['MOD']['#']['NELEMENTSAUTOR']['0']['#']);
        $quest->nattachments = backup_todb($info['MOD']['#']['NATTACHMENTS']['0']['#']);
        $quest->phase = backup_todb($info['MOD']['#']['PHASE']['0']['#']);
        $quest->format = backup_todb($info['MOD']['#']['FORMAT']['0']['#']);
        $quest->visible = backup_todb($info['MOD']['#']['VISIBLE']['0']['#']);
        $quest->gradingstrategy = backup_todb($info['MOD']['#']['GRADINGSTRATEGY']['0']['#']);
        $quest->gradingstrategyautor = backup_todb($info['MOD']['#']['GRADINGSTRATEGYAUTOR']['0']['#']);

        $quest->maxbytes = backup_todb($info['MOD']['#']['MAXBYTES']['0']['#']);
        $quest->datestart = backup_todb($info['MOD']['#']['DATESTART']['0']['#']);
        $quest->dateend = backup_todb($info['MOD']['#']['DATEEND']['0']['#']);

        $quest->usepassword = backup_todb($info['MOD']['#']['USEPASSWORD']['0']['#']);
        $quest->password = backup_todb($info['MOD']['#']['PASSWORD']['0']['#']);
        $quest->validateassessment = backup_todb($info['MOD']['#']['VALIDATEASSESSMEN']['0']['#']);
        $quest->timemaxquestion = backup_todb($info['MOD']['#']['TIMEMAXQUESTION']['0']['#']);
        $quest->nmaxanswers = backup_todb($info['MOD']['#']['NMAXANSWERS']['0']['#']);
        $quest->maxcalification = backup_todb($info['MOD']['#']['MAXCALIFICATION']['0']['#']);
        $quest->typecalification = backup_todb($info['MOD']['#']['TYPECALIFICATION']['0']['#']);
        $quest->allowteams = backup_todb($info['MOD']['#']['ALLOWTEAMS']['0']['#']);
        $quest->ncomponents = backup_todb($info['MOD']['#']['NCOMPONENTS']['0']['#']);
        $quest->tinitial = backup_todb($info['MOD']['#']['TINITIAL']['0']['#']);
        $quest->initialpoints = backup_todb($info['MOD']['#']['INITIALPOINTS']['0']['#']);
        $quest->teamporcent = backup_todb($info['MOD']['#']['TEAMPORCENT']['0']['#']);
        $quest->showclasifindividual = backup_todb($info['MOD']['#']['SHOWCLASIFINDIVIDUAL']['0']['#']);
        $quest->typegrade = backup_todb($info['MOD']['#']['TYPEGRADE']['0']['#']);

        // The structure is equal to the db, so insert the quest.
        $newid = $DB->insert_record("quest", $quest);

        // Do some output.
        echo "<li>" . get_string("modulename", "quest") . " \"" . format_string(stripslashes($quest->name), true) . "\"</li>";
        backup_flush(300);

        if ($newid) {
            // We have the newid, update backup_ids.
            backup_putid($restore->backup_unique_code, $mod->modtype, $mod->id, $newid);
            // We have to restore the quest_elements table now.
            $status = quest_elements_restore_mods($newid, $info, $restore);
            $status = quest_elementsautor_restore_mods($newid, $info, $restore);

            // Now check if want to restore user data and do it..
            if ($restore->mods['quest']->userinfo) {
                // Restore quest_submissions, quest_teams and quest_calification_users.
                $status = quest_teams_restore_mods($newid, $info, $restore);
                $status = quest_submissions_restore_mods($newid, $newid, $info, $restore);
                $status = quest_calification_users_restore_mods($newid, $newid, $info, $restore);
            }
        } else {
            $status = false;
        }
    } else {
        $status = false;
    }

    return $status;
}

// This function restores the quest_teams.
function quest_teams_restore_mods($questid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_teams array.
    $teams = $info['MOD']['#']['TEAMS']['0']['#']['TEAM'];

    // Iterate over quest_teams.
    for ($i = 0; $i < count($teams); $i++) {
        $teainfo = $teams[$i];

        // Now, build the QUEST_TEAMS record structure.
        $oldid = backup_todb($teainfo['#']['ID']['0']['#']);
        $team->questid = $questid;
        $team->name = backup_todb($teainfo['#']['NAME']['0']['#']);
        $team->ncomponents = backup_todb($teainfo['#']['NCOMPONENTS']['0']['#']);
        $team->currentgroup = backup_todb($teainfo['#']['CURRENTGROUP']['0']['#']);
        // The structure is equal to the db, so insert the quest_teams.
        $newid = $DB->insert_record("quest_teams", $team);

        // Do some output.
        if (($i + 1) % 10 == 0) {
            echo ".";
            if (($i + 1) % 200 == 0) {
                echo "<br />";
            }
            backup_flush(300);
        }

        if ($newid) {

            backup_putid($restore->backup_unique_code, "quest_teams", $oldid, $newid);
            // We have to restore the quest_calification_teams table now.
            $status = quest_calification_teams_restore_mods($questid, $newid, $teainfo, $restore);
        } else {
            $status = false;
        }
    }

    return $status;
}

// This function restores the quest_calification_teams.
function quest_calification_teams_restore_mods($questid, $teamid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_calification_teams array.
    if (isset($info['#']['CALIFICATIONS_TEAMS']['0']['#']['CALIFICATION_TEAM'])) {
        $calificationteams = $info['#']['CALIFICATIONS_TEAMS']['0']['#']['CALIFICATION_TEAM'];

        // Iterate over quest_calification_teams.
        for ($i = 0; $i < count($calificationteams); $i++) {
            $calinfo = $calificationteams[$i];

            // Now, build the quest_calification_teams record structure.
            $calificationteam->questid = $questid;
            $calificationteam->teamid = $teamid;
            $calificationteam->points = backup_todb($calinfo['#']['POINTS']['0']['#']);
            $calificationteam->nanswers = backup_todb($calinfo['#']['NANSWERS']['0']['#']);
            $calificationteam->nanswerassessment = backup_todb($calinfo['#']['NANSWERASSESSMENT']['0']['#']);
            $calificationteam->nsubmissions = backup_todb($calinfo['#']['NSUBMISSIONS']['0']['#']);
            $calificationteam->nsubmissionsassessment = backup_todb($calinfo['#']['NSUBMISSIONSASSESSMENT']['0']['#']);
            $calificationteam->pointssubmission = backup_todb($calinfo['#']['POINTSSUBMISSION']['0']['#']);
            $calificationteam->pointsanswers = backup_todb($calinfo['#']['POINTSANSWERS']['0']['#']);

            // The structure is equal to the db, so insert the quest_calification_teams.
            $newid = $DB->insert_record("quest_calification_teams", $calificationteam);

            // Do some output.
            if (($i + 1) % 10 == 0) {
                echo ".";
                if (($i + 1) % 200 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
    }
    return $status;
}

// This function restores the quest_calification_users.
function quest_calification_users_restore_mods($questid, $userid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_calification_users array.
    if (isset($info['MOD']['#']['CALIFICATIONS_USERS']['0']['#']['CALIFICATION_USER'])) {

        $calificationusers = $info['MOD']['#']['CALIFICATIONS_USERS']['0']['#']['CALIFICATION_USER'];

        // Iterate over quest_calification_users.
        for ($i = 0; $i < count($calificationusers); $i++) {
            $calinfo = $calificationusers[$i];

            $olduserid = backup_todb($calinfo['#']['USERID']['0']['#']);
            // Now, build the quest_calification_users record structure.
            $calificationuser->questid = $questid;
            $oldteamid = backup_todb($calinfo['#']['TEAMID']['0']['#']);
            $calificationuser->userid = backup_todb($calinfo['#']['USERID']['0']['#']);
            $calificationuser->points = backup_todb($calinfo['#']['POINTS']['0']['#']);
            $calificationuser->nanswers = backup_todb($calinfo['#']['NANSWERS']['0']['#']);
            $calificationuser->nanswerassessment = backup_todb($calinfo['#']['NANSWERASSESSMENT']['0']['#']);
            $calificationuser->nsubmissions = backup_todb($calinfo['#']['NSUBMISSIONS']['0']['#']);
            $calificationuser->nsubmissionsassessment = backup_todb($calinfo['#']['NSUBMISSIONSASSESSMENT']['0']['#']);
            $calificationuser->pointssubmission = backup_todb($calinfo['#']['POINTSSUBMISSION']['0']['#']);
            $calificationuser->pointsanswers = backup_todb($calinfo['#']['POINTSANSWERS']['0']['#']);

            $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
            if ($user) {
                $calificationuser->userid = $user->new_id;
            }
            $team = backup_getid($restore->backup_unique_code, "quest_teams", $oldteamid);
            if ($team) {
                $calificationuser->teamid = $team->new_id;
            }
            // The structure is equal to the db, so insert the quest_calification_users.
            $newid = $DB->insert_record("quest_calification_users", $calificationuser);

            // Do some output.
            if (($i + 1) % 10 == 0) {
                echo ".";
                if (($i + 1) % 200 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
    }
    return $status;
}

// This function restores the quest_elements.
function quest_elements_restore_mods($questid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_elements array.
    $elements = $info['MOD']['#']['ELEMENTS']['0']['#']['ELEMENT'];

    // Iterate over quest_elements.
    for ($i = 0; $i < count($elements); $i++) {
        $eleinfo = $elements[$i];

        // Now, build the QUEST_ELEMENTS record structure.
        $element->questid = $questid;
        $element->submissionsid = 0;
        $element->elementno = backup_todb($eleinfo['#']['ELEMENTNO']['0']['#']);
        $element->description = backup_todb($eleinfo['#']['DESCRIPTION']['0']['#']);
        $element->scale = backup_todb($eleinfo['#']['SCALE']['0']['#']);
        $element->maxscore = backup_todb($eleinfo['#']['MAXSCORE']['0']['#']);
        $element->weight = backup_todb($eleinfo['#']['WEIGHT']['0']['#']);

        // The structure is equal to the db, so insert the quest_elements.
        $newid = $DB->insert_record("quest_elements", $element);

        // Do some output.
        if (($i + 1) % 10 == 0) {
            echo ".";
            if (($i + 1) % 200 == 0) {
                echo "<br />";
            }
            backup_flush(300);
        }

        if ($newid) {
            // We have to restore the quest_rubrics table now.
            $status = quest_rubrics_restore_mods($questid, $element->elementno, $eleinfo, $restore);
        } else {
            $status = false;
        }
    }

    return $status;
}

// This function restores the quest_rubrics.
function quest_rubrics_restore_mods($questid, $elementno, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_rubrics array (optional).
    if (isset($info['#']['RUBRICS']['0']['#']['RUBRIC'])) {
        $rubrics = $info['#']['RUBRICS']['0']['#']['RUBRIC'];

        // Iterate over quest_rubrics.
        for ($i = 0; $i < count($rubrics); $i++) {
            $rubinfo = $rubrics[$i];

            // Now, build the QUEST_RUBRICS record structure.
            $rubric->questid = $questid;
            $rubric->elementno = $elementno;
            $rubric->submissionsid = backup_todb($rubinfo['#']['SUBMISSIONSID']['0']['#']);
            $rubric->rubricno = backup_todb($rubinfo['#']['RUBRICNO']['0']['#']);
            $rubric->description = backup_todb($rubinfo['#']['DESCRIPTION']['0']['#']);

            // The structure is equal to the db, so insert the quest_rubrics.
            $newid = $DB->insert_record("quest_rubrics", $rubric);

            // Do some output.
            if (($i + 1) % 10 == 0) {
                echo ".";
                if (($i + 1) % 200 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
    }
    return $status;
}

// This function restores the quest_elementsautor.
function quest_elementsautor_restore_mods($questid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_elementsautor array.
    $elements = $info['MOD']['#']['ELEMENTS_AUTOR']['0']['#']['ELEMENT_AUTOR'];

    // Iterate over quest_elementsautor.
    for ($i = 0; $i < count($elements); $i++) {
        $eleinfo = $elements[$i];

        // Now, build the QUEST_ELEMENTSAUTOR record structure.
        $element->questid = $questid;
        $element->elementno = backup_todb($eleinfo['#']['ELEMENTNO']['0']['#']);
        $element->description = backup_todb($eleinfo['#']['DESCRIPTION']['0']['#']);
        $element->scale = backup_todb($eleinfo['#']['SCALE']['0']['#']);
        $element->maxscore = backup_todb($eleinfo['#']['MAXSCORE']['0']['#']);
        $element->weight = backup_todb($eleinfo['#']['WEIGHT']['0']['#']);

        // The structure is equal to the db, so insert the quest_elementsautor.
        $newid = $DB->insert_record("quest_elementsautor", $element);

        // Do some output.
        if (($i + 1) % 10 == 0) {
            echo ".";
            if (($i + 1) % 200 == 0) {
                echo "<br />";
            }
            backup_flush(300);
        }

        if ($newid) {
            // We have to restore the quest_rubrics_autor table now.
            $status = quest_rubrics_autor_restore_mods($questid, $element->elementno, $eleinfo, $restore);
        } else {
            $status = false;
        }
    }

    return $status;
}

// This function restores the quest_rubrics_autor.
function quest_rubrics_autor_restore_mods($questid, $elementno, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the quest_rubrics_autor array.
    if (isset($info['#']['RUBRICS_AUTOR']['0']['#']['RUBRIC_AUTOR'])) {
        $rubrics = $info['#']['RUBRICS_AUTOR']['0']['#']['RUBRIC_AUTOR'];

        // Iterate over quest_rubrics_autor.
        for ($i = 0; $i < count($rubrics); $i++) {
            $rubinfo = $rubrics[$i];

            // Now, build the QUEST_RUBRICS_AUTOR record structure.
            $rubric->questid = $questid;
            $rubric->elementno = $elementno;
            $rubric->rubricno = backup_todb($rubinfo['#']['RUBRICNO']['0']['#']);
            $rubric->description = backup_todb($rubinfo['#']['DESCRIPTION']['0']['#']);

            // The structure is equal to the db, so insert the quest_rubrics_autor.
            $newid = $DB->insert_record("quest_rubrics_autor", $rubric);

            // Do some output.
            if (($i + 1) % 10 == 0) {
                echo ".";
                if (($i + 1) % 200 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
    }
    return $status;
}

// This function restores the quest_submissions.
function quest_submissions_restore_mods($oldquestid, $newquestid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the submissions array.
    $submissions = $info['MOD']['#']['SUBMISSIONS']['0']['#']['SUBMISSION'];

    // Iterate over submissions.
    for ($i = 0; $i < count($submissions); $i++) {
        $subinfo = $submissions[$i];

        // We'll need this later!!.
        $oldid = backup_todb($subinfo['#']['ID']['0']['#']);
        $olduserid = backup_todb($subinfo['#']['USERID']['0']['#']);

        // Now, build the QUEST_SUBMISSIONS record structure.
        $submission->questid = $newquestid;
        $submission->userid = backup_todb($subinfo['#']['USERID']['0']['#']);
        $submission->numelements = backup_todb($subinfo['#']['NUMELEMENTS']['0']['#']);
        $submission->title = backup_todb($subinfo['#']['TITLE']['0']['#']);
        $submission->timecreated = backup_todb($subinfo['#']['TIMECREATED']['0']['#']);
        $submission->description = backup_todb($subinfo['#']['DESCRIPTION']['0']['#']);
        $submission->points = backup_todb($subinfo['#']['POINTS']['0']['#']);
        $submission->phase = backup_todb($subinfo['#']['PHASE']['0']['#']);
        $submission->comentteacherpupil = backup_todb($subinfo['#']['COMENTTEACHERPUPIL']['0']['#']);
        $submission->comentteacherautor = backup_todb($subinfo['#']['COMENTTEACHERAUTOR']['0']['#']);
        $submission->dateend = backup_todb($subinfo['#']['DATEEND']['0']['#']);
        $submission->nanswers = backup_todb($subinfo['#']['NANSWERS']['0']['#']);
        $submission->nanswerscorrect = backup_todb($subinfo['#']['NANSWERSCORRECT']['0']['#']);
        $submission->state = backup_todb($subinfo['#']['STATE']['0']['#']);
        $submission->datestart = backup_todb($subinfo['#']['DATESTART']['0']['#']);
        $submission->pointsmax = backup_todb($subinfo['#']['POINTSMAX']['0']['#']);
        $submission->pointsmin = backup_todb($subinfo['#']['POINTSMIN']['0']['#']);
        $submission->dateanswercorrect = backup_todb($subinfo['#']['DATEANSWERCORRECT']['0']['#']);
        $submission->initialpoints = backup_todb($subinfo['#']['INITIALPOINTS']['0']['#']);
        $submission->pointsanswercorrect = backup_todb($subinfo['#']['POINTSANSWERCORRECT']['0']['#']);
        $submission->mailed = backup_todb($subinfo['#']['MAILED']['0']['#']);

        // We have to recode the userid field.
        $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
        if ($user) {
            $submission->userid = $user->new_id;
        }

        // The structure is equal to the db, so insert the quest_submission.
        $newid = $DB->insert_record("quest_submissions", $submission);

        // Elementos particulares de cada submission.
        $status = quest_particular_elements_restore_mods($newquestid, $subinfo, $restore, $newid);

        // Do some output.
        if (($i + 1) % 50 == 0) {
            echo ".";
            if (($i + 1) % 1000 == 0) {
                echo "<br />";
            }
            backup_flush(300);
        }

        if ($newid) {
            // We have the newid, update backup_ids.
            backup_putid($restore->backup_unique_code, "quest_submissions", $oldid, $newid);

            // Now copy moddata associated files.
            $status = quest_restore_submission_files($oldid, $newid, $restore);
            // Now we need to restore quest_assessments_autors and quest_answers.

            $status = quest_assessments_autors_restore_mods($newquestid, $newid, $subinfo, $restore);
            $status = quest_answers_restore_mods($newquestid, $newid, $subinfo, $restore);
        } else {
            $status = false;
        }
    }

    return $status;
}

// This function restores the quest_assessments_autors.
function quest_assessments_autors_restore_mods($newquestid, $newsubmissionid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the assessments_autors array (if any).
    if (isset($info['#']['ASSESSMENTS_AUTOR']['0']['#']['ASSESSMENT_AUTOR'])) {
        $assessments = $info['#']['ASSESSMENTS_AUTOR']['0']['#']['ASSESSMENT_AUTOR'];

        // Iterate over assessments_autors.
        for ($i = 0; $i < count($assessments); $i++) {
            $assinfo = $assessments[$i];

            // We'll need this later!!.
            $oldid = backup_todb($assinfo['#']['ID']['0']['#']);
            $olduserid = backup_todb($assinfo['#']['USERID']['0']['#']);

            // Now, build the QUEST_ASSESSMENTS_AUTORS record structure.
            $assessment->questid = $newquestid;
            $assessment->submissionid = $newsubmissionid;
            $assessment->userid = backup_todb($assinfo['#']['USERID']['0']['#']);
            $assessment->points = backup_todb($assinfo['#']['POINTS']['0']['#']);
            $assessment->dateassessment = backup_todb($assinfo['#']['DATEASSESSMENT']['0']['#']);
            $assessment->commentsforteacher = backup_todb($assinfo['#']['COMMENTFORTEACHER']['0']['#']);
            $assessment->commentsteacher = backup_todb($assinfo['#']['COMMENTSTEACHER']['0']['#']);
            $assessment->state = backup_todb($assinfo['#']['STATE']['0']['#']);
            // We have to recode the userid field.
            $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
            if ($user) {
                $assessment->userid = $user->new_id;
            }

            // The structure is equal to the db, so insert the quest_assessment_autor.
            $newid = $DB->insert_record("quest_assessments_autors", $assessment);

            // Do some output.
            if (($i + 1) % 50 == 0) {
                echo ".";
                if (($i + 1) % 1000 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if ($newid) {
                // We have the newid, update backup_ids.
                backup_putid($restore->backup_unique_code, "quest_assessments_autors", $oldid, $newid);

                // Now we need to restore quest_elements_assessments_autor.

                $status = quest_elements_assessments_autor_restore_mods($newquestid, $newid, $assinfo, $restore);
            } else {
                $status = false;
            }
        }
    }

    return $status;
}

// This function restores the quest_elements_assessments_autor.
function quest_elements_assessments_autor_restore_mods($newquestid, $newassessmentid, $info, $restore) {
    global $CFG, $DB;

    $status = true;

    // Get the quest_elements_assessments_autor array.
    if (isset($info['#']['ELEMENTS_ASSESS_AUTOR']['0']['#']['ELEMENT_ASSESS_AUTOR'])) {
        $grades = $info['#']['ELEMENTS_ASSESS_AUTOR']['0']['#']['ELEMENT_ASSESS_AUTOR'];

        // Iterate over quest_elements_assessments_autor.
        for ($i = 0; $i < count($grades); $i++) {
            $grainfo = $grades[$i];

            $olduserid = backup_todb($grainfo['#']['USERID']['0']['#']);
            // Now, build the quest_elements_assessments_autor record structure.
            $grade->questid = $newquestid;
            $grade->assessmentautorid = $newassessmentid;
            $grade->userid = backup_todb($grainfo['#']['USERID']['0']['#']);
            $grade->elementno = backup_todb($grainfo['#']['ELEMENTNO']['0']['#']);
            $grade->answer = backup_todb($grainfo['#']['ANSWER']['0']['#']);
            $grade->commentteacher = backup_todb($grainfo['#']['COMMENTTEACHER']['0']['#']);
            $grade->calification = backup_todb($grainfo['#']['CALIFICATION']['0']['#']);
            $grade->phase = backup_todb($grainfo['#']['PHASE']['0']['#']);

            $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
            if ($user) {
                $grade->userid = $user->new_id;
            }
            // The structure is equal to the db, so insert the quest_elements_assessments_autor.
            $newid = $DB->insert_record("quest_items_assessments_autor", $grade);

            // Do some output.
            if (($i + 1) % 50 == 0) {
                echo ".";
                if (($i + 1) % 1000 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
    }

    return $status;
}

// This function restores the quest_answers.
function quest_answers_restore_mods($newquestid, $newsubmissionid, $info, $restore) {
    global $CFG, $DB;

    $status = true;

    // Get the answers array.
    if (isset($info['#']['ANSWERS']['0']['#']['ANSWER'])) {
        $answers = $info['#']['ANSWERS']['0']['#']['ANSWER'];

        // Iterate over answers.
        for ($i = 0; $i < count($answers); $i++) {
            $ansinfo = $answers[$i];

            // We'll need this later!!.
            $oldid = backup_todb($ansinfo['#']['ID']['0']['#']);
            $olduserid = backup_todb($ansinfo['#']['USERID']['0']['#']);

            // Now, build the QUEST_ANSWERS record structure.
            $answer->questid = $newquestid;
            $answer->submissionid = $newsubmissionid;
            $answer->userid = backup_todb($ansinfo['#']['USERID']['0']['#']);
            $answer->title = backup_todb($ansinfo['#']['TITLE']['0']['#']);
            $answer->description = backup_todb($ansinfo['#']['DESCRIPTION']['0']['#']);
            $answer->date = backup_todb($ansinfo['#']['DATE']['0']['#']);
            $answer->grade = backup_todb($ansinfo['#']['GRADE']['0']['#']);
            $answer->pointsmax = backup_todb($ansinfo['#']['POINTSMAX']['0']['#']);
            $answer->commentsforteacher = backup_todb($ansinfo['#']['COMMENTFORTEACHER']['0']['#']);
            $answer->phase = backup_todb($ansinfo['#']['PHASE']['0']['#']);
            $answer->state = backup_todb($ansinfo['#']['STATE']['0']['#']);
            // We have to recode the userid field.
            $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
            if ($user) {
                $answer->userid = $user->new_id;
            }

            // The structure is equal to the db, so insert the quest_answers.
            $newid = $DB->insert_record("quest_answers", $answer);

            // Do some output.
            if (($i + 1) % 50 == 0) {
                echo ".";
                if (($i + 1) % 1000 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if ($newid) {

                $status = quest_restore_answer_files($oldid, $newid, $restore);
                // We have the newid, update backup_ids.
                backup_putid($restore->backup_unique_code, "quest_answers", $oldid, $newid);

                // Now we need to restore quest_assessments.

                $status = quest_assessments_restore_mods($newquestid, $newid, $ansinfo, $restore);
            } else {
                $status = false;
            }
        }
    }

    return $status;
}

// This function restores the quest_assessments.
function quest_assessments_restore_mods($newquestid, $newid, $info, $restore) {
    global $CFG;

    $status = true;

    // Get the assessments array (if any).
    if (isset($info['#']['ASSESSMENTS']['0']['#']['ASSESSMENT'])) {
        $assessments = $info['#']['ASSESSMENTS']['0']['#']['ASSESSMENT'];

        // Iterate over assessments.
        for ($i = 0; $i < count($assessments); $i++) {
            $assinfo = $assessments[$i];

            // We'll need this later!!.
            $oldid = backup_todb($assinfo['#']['ID']['0']['#']);
            $olduserid = backup_todb($assinfo['#']['USERID']['0']['#']);
            $oldteacherid = backup_todb($assinfo['#']['TEACHERID']['0']['#']);

            // Now, build the QUEST_ASSESSMENTS record structure.
            $assessment->questid = $newquestid;
            $assessment->answerid = $newid;
            $assessment->userid = backup_todb($assinfo['#']['USERID']['0']['#']);
            $assessment->teacherid = backup_todb($assinfo['#']['TEACHERID']['0']['#']);
            $assessment->pointsautor = backup_todb($assinfo['#']['POINTSAUTOR']['0']['#']);
            $assessment->pointsteacher = backup_todb($assinfo['#']['POINTSTEACHER']['0']['#']);
            $assessment->dateassessment = backup_todb($assinfo['#']['DATEASSESSMENT']['0']['#']);
            $assessment->commentsforteacher = backup_todb($assinfo['#']['COMMENTFORTEACHER']['0']['#']);
            $assessment->commentsteacher = backup_todb($assinfo['#']['COMMENTSTEACHER']['0']['#']);
            $assessment->phase = backup_todb($assinfo['#']['PHASE']['0']['#']);
            $assessment->state = backup_todb($assinfo['#']['STATE']['0']['#']);

            // We have to recode the userid field.
            $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
            if ($user) {
                $assessment->userid = $user->new_id;
            }
            $teacher = backup_getid($restore->backup_unique_code, "user", $oldteacherid);
            if ($teacher) {
                $assessment->teacherid = $teacher->new_id;
            }

            // The structure is equal to the db, so insert the quest_assessment.
            $newid = $DB->insert_record("quest_assessments", $assessment);

            // Do some output.
            if (($i + 1) % 50 == 0) {
                echo ".";
                if (($i + 1) % 1000 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if ($newid) {

                // We have the newid, update backup_ids..
                backup_putid($restore->backup_unique_code, "quest_assessments", $oldid, $newid);

                // Now we need to restore quest_elements_assessments.

                $status = quest_elements_assessments_restore_mods($newquestid, $newid, $assinfo, $restore);
            } else {
                $status = false;
            }
        }
    }

    return $status;
}

// This function restores the quest_elements_assessments.
function quest_elements_assessments_restore_mods($newquestid, $newassessmentid, $info, $restore) {
    global $CFG, $DB;

    $status = true;

    // Get the elements_assessments array.
    if (isset($info['#']['ELEMENTS_ASSESS']['0']['#']['ELEMENT_ASSESS'])) {
        $grades = $info['#']['ELEMENTS_ASSESS']['0']['#']['ELEMENT_ASSESS'];

        // Iterate over grades..
        for ($i = 0; $i < count($grades); $i++) {
            $grainfo = $grades[$i];

            $olduserid = backup_todb($grainfo['#']['USERID']['0']['#']);
            // Now, build the QUEST_ELEMENTS_ASSESSMENTS record structure..
            $grade->questid = $newquestid;
            $grade->assessmentid = $newassessmentid;
            $grade->userid = backup_todb($grainfo['#']['USERID']['0']['#']);
            $grade->elementno = backup_todb($grainfo['#']['ELEMENTNO']['0']['#']);
            $grade->answer = backup_todb($grainfo['#']['ANSWER']['0']['#']);
            $grade->commentteacher = backup_todb($grainfo['#']['COMMENTTEACHER']['0']['#']);
            $grade->calification = backup_todb($grainfo['#']['CALIFICATION']['0']['#']);
            $grade->phase = backup_todb($grainfo['#']['PHASE']['0']['#']);

            $user = backup_getid($restore->backup_unique_code, "user", $olduserid);
            if ($user) {
                $grade->userid = $user->new_id;
            }
            // The structure is equal to the db, so insert the quest_elements_assessments.
            $newid = $DB->insert_record("quest_elements_assessments", $grade);

            // Do some output.
            if (($i + 1) % 50 == 0) {
                echo ".";
                if (($i + 1) % 1000 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
    }

    return $status;
}

// This function copies the workshop related info from backup temp dir to course moddata folder,.
// creating it if needed and recoding everything.
function quest_restore_submission_files($oldsubmiss, $newsubmiss, $restore) {
    global $CFG;

    $status = true;
    $todo = false;
    $moddatapath = "";
    $questpath = "";
    $temppath = "";

    // First, we check to "course_id" exists and create is as necessary.
    // in CFG->dataroot.
    $destdir = $CFG->dataroot . "/" . $restore->course_id;
    $status = check_dir_exists($destdir, true);

    // Now, locate course's moddata directory.
    $moddatapath = $CFG->dataroot . "/" . $restore->course_id . "/" . $CFG->moddata;

    // Check it exists and create it.
    $status = check_dir_exists($moddatapath, true);

    // Now, locate quest directory.
    if ($status) {
        $questpath = $moddatapath . "/quest";
        // Check it exists and create it.
        $status = check_dir_exists($questpath, true);
    }

    // Now locate the temp dir we are gong to restore.
    if ($status) {
        $temppath = $CFG->dataroot . "/temp/backup/" . $restore->backup_unique_code . "/moddata/quest/submissions/" . $oldsubmiss;
        // Check it exists.
        if (is_dir($temppath)) {
            $todo = true;
        }
    }

    // If todo, we create the neccesary dirs in course moddata/quest.
    if ($status and $todo) {
        // First this quest id.
        $thisquestpath = $questpath . "/submissions";
        $status = check_dir_exists($thisquestpath, true);
        $thisquestpath = $thisquestpath . "/" . $newsubmiss;
        $status = check_dir_exists($thisquestpath, true);
        // And now, copy temp_path to this_quest_path.
        $status = backup_copy_file($temppath, $thisquestpath);
    }

    return $status;
}

// This function copies the quest related info from backup temp dir to course moddata folder,.
// creating it if needed and recoding everything.
function quest_restore_answer_files($oldans, $newans, $restore) {
    global $CFG;

    $status = true;
    $todo = false;
    $moddatapath = "";
    $questpath = "";
    $temppath = "";

    // First, we check to "course_id" exists and create is as necessary.
    // in CFG->dataroot....
    $destdir = $CFG->dataroot . "/" . $restore->course_id;
    $status = check_dir_exists($destdir, true);

    // Now, locate course's moddata directory....
    $moddatapath = $CFG->dataroot . "/" . $restore->course_id . "/" . $CFG->moddata;

    // Check it exists and create it....
    $status = check_dir_exists($moddatapath, true);

    // Now, locate quest directory....
    if ($status) {
        $questpath = $moddatapath . "/quest";
        // Check it exists and create it.
        $status = check_dir_exists($questpath, true);
    }

    // Now locate the temp dir we are gong to restore.
    if ($status) {
        $temppath = $CFG->dataroot . "/temp/backup/" . $restore->backup_unique_code . "/moddata/quest/answers/" . $oldans;

        // Check it exists.
        if (is_dir($temppath)) {

            $todo = true;
        }
    }

    // If todo, we create the neccesary dirs in course moddata/quest.
    if ($status and $todo) {
        // First this quest id.
        $thisquestpath = $questpath . "/answers";
        $status = check_dir_exists($thisquestpath, true);
        $thisquestpath = $thisquestpath . "/" . $newans;
        $status = check_dir_exists($thisquestpath, true);

        // And now, copy temp_path to this_quest_path.
        $status = backup_copy_file($temppath, $thisquestpath);
    }

    return $status;
}

// This function converts texts in FORMAT_WIKI to FORMAT_MARKDOWN for.
// some texts in the module.
function quest_restore_wiki2markdown($restore) {
    global $CFG, $DB;

    $status = true;

    // Convert quest->description.
    if ($records = $DB->get_records_sql(
            "SELECT q.id, q.description, q.format FROM {quest} q, {backup_ids} b " .
            "WHERE q.course = ? AND format = " . FORMAT_WIKI . " AND b.backup_code = ? " .
            "AND b.table_name = 'quest' AND b.new_id = q.id",
            array($restore->course_id, $restore->backup_unique_code))) {
        foreach ($records as $record) {
            // Rebuild wiki links.
            $record->description = restore_decode_wiki_content($record->description, $restore);
            // Convert to Markdown.
            $wtm = new WikiToMarkdown();
            $record->description = $wtm->convert($record->description, $restore->course_id);
            $record->format = FORMAT_MARKDOWN;
            $status = $DB->update_record('quest', addslashes_object($record));
            // Do some output.
            $i++;
            if (($i + 1) % 1 == 0) {
                echo ".";
                if (($i + 1) % 20 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }
        }
    }
    return $status;
}

// Return a content decoded to support interactivities linking. Every module.
// should have its own. They are called automatically from.
// quest_decode_content_links_caller() function in each module.
// in the restore process.
function quest_decode_content_links($content, $restore) {
    global $CFG;

    $result = $content;

    // Link to the list of quests.

    $searchstring = '/\$@(QUESTINDEX)\*([0-9]+)@\$/';
    // We look for it.
    preg_match_all($searchstring, $content, $foundset);
    // If found, then we are going to look for its new id (in backup tables).
    if ($foundset[0]) {
        // Iterate over foundset[2]. They are the old_ids.
        foreach ($foundset[2] as $oldid) {
            // We get the needed variables here (course id).
            $rec = backup_getid($restore->backup_unique_code, "course", $oldid);
            // Personalize the searchstring.
            $searchstring = '/\$@(QUESTINDEX)\*(' . $oldid . ')@\$/';
            // If it is a link to this course, update the link to its new location.
            if ($rec->new_id) {
                // Now replace it.
                $result = preg_replace($searchstring, $CFG->wwwroot . '/mod/quest/index.php?id=' . $rec->new_id, $result);
            } else {
                // It's a foreign link so leave it as original.
                $result = preg_replace($searchstring, $restore->original_wwwroot . '/mod/quest/index.php?id=' . $oldid, $result);
            }
        }
    }

    // Link to quest view by moduleid.

    $searchstring = '/\$@(QUESTVIEWBYID)\*([0-9]+)@\$/';
    // We look for it.
    preg_match_all($searchstring, $result, $foundset);
    // If found, then we are going to look for its new id (in backup tables).
    if ($foundset[0]) {
        // print_object($foundset); //Debug.
        // Iterate over foundset[2]. They are the old_ids.
        foreach ($foundset[2] as $oldid) {
            // We get the needed variables here (course_modules id).
            $rec = backup_getid($restore->backup_unique_code, "course_modules", $oldid);
            // Personalize the searchstring.
            $searchstring = '/\$@(QUESTVIEWBYID)\*(' . $oldid . ')@\$/';
            // If it is a link to this course, update the link to its new location.
            if ($rec->new_id) {
                // Now replace it.
                $result = preg_replace($searchstring, $CFG->wwwroot . '/mod/quest/view.php?id=' . $rec->new_id, $result);
            } else {
                // It's a foreign link so leave it as original.
                $result = preg_replace($searchstring, $restore->original_wwwroot . '/mod/quest/view.php?id=' . $oldid, $result);
            }
        }
    }

    return $result;
}

// This function makes all the necessary calls to xxxx_decode_content_links().
// function in each module, passing them the desired contents to be decoded.
// from backup format to destination site/course in order to mantain inter-activities.
// working in the backup/restore process. It's called from restore_decode_content_links().
// function in restore process.
function quest_decode_content_links_caller($restore) {
    global $CFG;
    $status = true;

    // Process every QUEST (description) in the course.
    $quests = $DB->get_records_sql("SELECT q.id, q.description FROM {quest} q WHERE q.course = ?", array($restore->course_id));
    if ($quests) {
        // Iterate over each quest->description.
        $i = 0; // Counter to send some output to the browser to avoid timeouts.
        foreach ($quests as $quest) {
            // Increment counter.
            $i++;
            $content = $quest->description;
            $result = restore_decode_content_links_worker($content, $restore);
            if ($result != $content) {
                // Update record.
                $quest->description = $result;
                $status = $DB->update_record("quest", $quest);
                if ($CFG->debug > 7) {
                    echo '<br /><hr />' . htmlentities($content) . '<br />changed to<br />' .
                    htmlentities($result) . '<hr /><br />';
                }
            }
            // Do some output.
            if (($i + 1) % 5 == 0) {
                echo ".";
                if (($i + 1) % 100 == 0) {
                    echo "<br />";
                }
                backup_flush(300);
            }
        }
    }

    return $status;
}

// This function returns a log record with all the necessay transformations.
// done. It's used by restore_log_module() to restore modules log..
function quest_restore_logs($restore, $log) {
    $status = false;

    // Depending of the action, we recode different things.
    switch ($log->action) {
        case "view_calificati":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "viewclasification.php?id=" . $log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=" . $log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "delete_submissi":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=" . $log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view_teams":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "team.php?id=" . $log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view all":
            $log->url = "index.php?id=" . $log->course;
            $status = true;
            break;
    }
    if ($status) {
        $status = $log;
    }
    return $status;
}

function quest_particular_elements_restore_mods($newquestid, $info, $restore, $newid) {
    global $CFG, $DB;

    $status = true;
    $newsubmissionid = $newid;

    // Get the quest_elements array.
    $elements = $info['#']['PARTICULAR_ELEMENTS']['0']['#']['PARTICULAR_ELEMENT'];

    $m = count($elements);
    // Iterate over quest_elements.
    print("<p>Restaurando $m elementos.");
    for ($i = 0; $i < $m; $i++) {
        $eleinfo = $elements[$i];

        // Now, build the QUEST_ELEMENTS record structure.
        $element->questid = $newquestid;
        $element->submissionsid = $newsubmissionid;
        $element->elementno = backup_todb($eleinfo['#']['ELEMENTNO']['0']['#']);
        $element->description = backup_todb($eleinfo['#']['DESCRIPTION']['0']['#']);
        $element->scale = backup_todb($eleinfo['#']['SCALE']['0']['#']);
        $element->maxscore = backup_todb($eleinfo['#']['MAXSCORE']['0']['#']);
        $element->weight = backup_todb($eleinfo['#']['WEIGHT']['0']['#']);

        // The structure is equal to the db, so insert the quest_elements.
        $newid = $DB->insert_record("quest_elements", $element);
    }
    return $status;
}
