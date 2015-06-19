<?php
define('CLI_SCRIPT', 1);
require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
$fullname="restore test course";
$shortname="restoretest";
$categoryid=3;
$folder='d5441abf95d594bbfff05dd83c7f6b74';
$userid=2;
// Transaction
      //  $transaction = $DB->start_delegated_transaction();
 $courseid=16; // reuse a course
        // Create new course
        if (!isset($courseid))
        {
        	$courseid = restore_dbops::create_new_course($fullname, $shortname, $categoryid);
        }
        else 
        {
        	restore_dbops::delete_course_content($courseid);
        }
        	 
        // Restore backup into course
        $controller = new restore_controller($folder, $courseid, 
                backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $userid,
                backup::TARGET_NEW_COURSE);
        $controller->execute_precheck();
        $controller->execute_plan();
 
        // Commit
     //   $transaction->allow_commit();