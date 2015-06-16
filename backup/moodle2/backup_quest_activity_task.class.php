<?php
 
require_once($CFG->dirroot . '/mod/quest/backup/moodle2/backup_quest_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/quest/backup/moodle2/backup_quest_settingslib.php'); // Because it exists (optional)
 
/**
 * choice backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_quest_activity_task extends backup_activity_task {
 
    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }
 
    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // quest only has one structure step
        $this->add_step(new backup_quest_activity_structure_step('quest_structure', 'quest.xml'));
    }
 
    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content)
    {
    	  global $CFG;
 
        $base = preg_quote($CFG->wwwroot,"/");
 
        // Link to the list of choices
        $search="/(".$base."\/mod\/quest\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@QUESTINDEX*$2@$', $content);
 
        // Link to choice view by moduleid
        $search="/(".$base."\/mod\/quest\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@QUESTVIEWBYID*$2@$', $content);
        return $content;
    }
}