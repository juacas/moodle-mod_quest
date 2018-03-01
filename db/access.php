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
 * Questournament activity for Moodle
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * Capability definitions for the questournament module.
 * For naming conventions, see lib/db/access.php.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest */
defined('MOODLE_INTERNAL') || die();
/*
 * This permissions list in taken from Questournament 2.0
 * Most of the permissions are unused in this version.
 */
$capabilities = array(
                // Ability to see that the questournament exists, and the basic information
                // about it, for example the start date and time limit.
                'mod/quest:view' => array('captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('guest' => CAP_ALLOW, 'student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW)),
                // Ability to do the questournament as a 'student'.
                'mod/quest:attempt' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('student' => CAP_ALLOW)),
                // Edit questournament attempt.
                'mod/quest:editattempt' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW)),
                // Edit questournament attempt mine.
                'mod/quest:editattemptmine' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Add questournament challenge.
                'mod/quest:addchallenge' => array('riskbitmask' => RISK_SPAM,
                                'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW,
                                                'teacher' => CAP_ALLOW)),
                // Edit questournament challenge mine.
                'mod/quest:editchallengemine' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'student' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Delete questournament challenge mine.
                'mod/quest:deletechallengemine' => array('captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'student' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Edit questournament all challenges.
                'mod/quest:editchallengeall' => array('captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW)),
                // Approve students' challenge.
                'mod/quest:approvechallenge' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Delete questournament all challenges.
                'mod/quest:deletechallengeall' => array('captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('manager' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW)),
                // Add a new questournament instance.
                'mod/quest:addinstance' => array('riskbitmask' => RISK_XSS,
                                'captype' => 'write', 'contextlevel' => CONTEXT_COURSE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW),
                                'clonepermissionsfrom' => 'moodle/course:manageactivities'),
                // Edit the questournament settings, add and remove challenges.
                'mod/quest:manage' => array('captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Preview advanced information and links of the questournament.
                'mod/quest:preview' => array('captype' => 'read',
                                'riskbitmask' => RISK_PERSONAL,
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Manually grade and comment on student attempts at a question, and regrade
                // questournaments.
                'mod/quest:grade' => array('riskbitmask' => RISK_SPAM,
                                'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Approve grade of students assessments.
                'mod/quest:approvegrade' => array('riskbitmask' => RISK_SPAM,
                                'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // View the questournament reports.
                'mod/quest:viewreports' => array('riskbitmask' => RISK_PERSONAL, 'captype' => 'read',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Delete attempts using the overview report.
                'mod/quest:deleteattempts' => array('riskbitmask' => RISK_DATALOSS, 'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW,
                                                'teacher' => CAP_ALLOW)),
                // Manage own challenge.
                'mod/quest:manageownchallenge' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW,
                                                'teacher' => CAP_ALLOW)),
                // Grade own challenge.
                'mod/quest:gradeownchallenge' => array('riskbitmask' => RISK_SPAM, 'captype' => 'write',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('editingteacher' => CAP_ALLOW,
                                                'teacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                'mod/quest:ignoretimelimits' => array('captype' => 'read',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array()),
                // Receive email confirmation from own questournament challenge.
                'mod/quest:emailconfirmchallenge' => array('captype' => 'read',
                                'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('student' => CAP_ALLOW,
                                                'teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Receive email notification from other people's questournament challenges.
                'mod/quest:emailnotifychallenge' => array('captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // View owners of other attempts.
                'mod/quest:viewotherattemptsowners' => array('captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)),
                // Download current questournament's raw reports.
                'mod/quest:downloadlogs' => array('captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
                                'archetypes' => array('teacher' => CAP_ALLOW,
                                                'editingteacher' => CAP_ALLOW,
                                                'manager' => CAP_ALLOW)));