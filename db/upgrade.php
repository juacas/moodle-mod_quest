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
/** Questournament activity for Moodle
 *
 *
 * This file keeps track of upgrades to
 * the questournament module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installtion to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the functions defined in lib/ddllib.php
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/ddllib.php');
/**
 *
 * @global moodle_database $DB
 * @param number $oldversion
 * @return boolean
 */
function xmldb_quest_upgrade($oldversion = 0) {
    global  $DB;
    $dbman = $DB->get_manager();

    // And upgrade begins here. For each one, you'll need one block of code similar to the next one. Please, delete
    // this comment lines once this file start handling proper upgrade code.
    if ($oldversion < 2013100400) {
        $table = new xmldb_table('quest_answers');
        $field = new xmldb_field('attachment', XMLDB_TYPE_CHAR, '100', null, false, false, null, null);
        $dbman->change_field_notnull($table, $field);

        $table = new xmldb_table('quest_submissions');
        $field = new xmldb_field('attachment', XMLDB_TYPE_CHAR, '100', null, false, false, null, null);
        $dbman->change_field_notnull($table, $field);
        upgrade_mod_savepoint(true, 2013100400, 'quest');
    }
    if ($oldversion < 2016060900) {
        $table = new xmldb_table('quest');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, true, true, false, null, null);
        $dbman->rename_field($table, $field, 'intro');

        $table = new xmldb_table('quest');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', true, true, false, 0, null);
        $dbman->add_field($table, $field);
        upgrade_mod_savepoint(true, 2016060900, 'quest');
    }

    if ($oldversion < 2017101800) {
        $table = new xmldb_table('quest_submissions');
        $field = new xmldb_field('pointsmin', XMLDB_TYPE_INTEGER, '4', true, true, false, 0, $table->getField('pointsmax'));
        $dbman->add_field($table, $field);

        $table = new xmldb_table('quest');
        $field = new xmldb_field('mincalification', XMLDB_TYPE_INTEGER, '4', true, true, false, 0,
                $table->getField('maxcalification'));
        $dbman->add_field($table, $field);
        upgrade_mod_savepoint(true, 2017101800, 'quest');
    }
    if ($oldversion < 2018030701) {
        // Define field anonymizeviewsbe added to msocial_.
        $table = new xmldb_table('quest');
        $field = new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0 , 'permitviewautors');

        // Conditionally launch add field rejected.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Msocial savepoint reached.
        upgrade_mod_savepoint(true, 2018030701, 'quest');
    }
    return true;
}