<?php
define('CLI_SCRIPT', 1);
require(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/backup/util/helper/convert_helper.class.php');
$test_course_backup='quest_answers_source';
convert_helper::to_moodle2_format($test_course_backup, 'moodle1');