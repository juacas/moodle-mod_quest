<?PHP // $Id: version.php

/////////////////////////////////////////////////////////////////////////////////
///  Code fragment to define the version of QUEST
///  This fragment is called by moodle_needs_upgrading() and /admin/index.php
/////////////////////////////////////////////////////////////////////////////////
defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2014011401;
$plugin->requires = 2012011900; // The current module version (Date: YYYYMMDDXX)
$plugin->component = 'mod_quest';
$plugin->cron     = 600;// Period for cron to check this module (secs)
$plugin->matutity = MATURITY_STABLE;
$plugin->release = 'v1.2';
?>
