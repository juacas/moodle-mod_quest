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

require_once($CFG->dirroot . '/mod/quest/backup/moodle2/backup_quest_stepslib.php');
require_once($CFG->dirroot . '/mod/quest/backup/moodle2/backup_quest_settingslib.php');

/**
 * Backup Questournament module
 * backup task that provides all the settings and steps to perform one complete backup of the activity.
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
class backup_quest_activity_task extends backup_activity_task {

    /** Define (add) particular settings this activity can have */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /** Define (add) particular steps this activity can have */
    protected function define_my_steps() {
        // Quest only has one structure step.
        $this->add_step(new backup_quest_activity_structure_step('quest_structure', 'quest.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     * @param unknown $content
     * @return mixed
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of choices.
        $search = "/(" . $base . "\/mod\/quest\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@QUESTINDEX*$2@$', $content);

        // Link to choice view by moduleid.
        $search = "/(" . $base . "\/mod\/quest\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@QUESTVIEWBYID*$2@$', $content);
        return $content;
    }
}