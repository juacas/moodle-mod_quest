<?PHP // $Id: version.php

/////////////////////////////////////////////////////////////////////////////////
///  Code fragment to define the version of QUEST
///  This fragment is called by moodle_needs_upgrading() and /admin/index.php
/////////////////////////////////////////////////////////////////////////////////
defined('MOODLE_INTERNAL') || die();

$module->version  = 2014011401;
$module->requires = 2011011900; // The current module version (Date: YYYYMMDDXX)
$module->component = 'mod_quest';
$module->cron     = 600// Period for cron to check this module (secs)

?>
