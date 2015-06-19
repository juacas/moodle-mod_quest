<?php  // $Id: upgrade.php,v 1.8.2.3 2008/05/01 20:55:32 skodak Exp $
global $CFG;
require_once $CFG->libdir.'/ddllib.php';
// This file keeps track of upgrades to
// the questournament module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_quest_upgrade($oldversion=0) {

    global $CFG, $USER, $THEME, $DB;
    $dbman = $DB->get_manager(); // loads DDL libs

/// And upgrade begins here. For each one, you'll need one
/// block of code similar to the next one. Please, delete
/// this comment lines once this file start handling proper
/// upgrade code.


    if ($oldversion < 2013100400)
    {
        $table = new xmldb_table('quest_answers');
        $field = new xmldb_field('attachment', XMLDB_TYPE_CHAR, '100', null, false, false, null, null);
        $dbman->change_field_notnull($table, $field);
    
        $table = new xmldb_table('quest_submissions');
        //		<FIELD NAME="attachment" TYPE="char" LENGTH="100" NOTNULL="false" SEQUENCE="false" PREVIOUS="descriptiontrust" NEXT="date"/>
        $field = new xmldb_field('attachment', XMLDB_TYPE_CHAR, '100', null, false, false, null, null);
        $dbman->change_field_notnull($table, $field);
    }
    return true;
}
?>