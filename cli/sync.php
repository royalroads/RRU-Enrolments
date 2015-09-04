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

/**
 * CLI sync for RRU enrolments synchronization.
 *
 * Sample cron entry:
 * # 15 minutes past 10am
 * 15 10 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/enrol/rru/cli/sync.php
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *
 * 2012-08-23
 * @package    enrol
 * @subpackage rru
 * @copyright  2012 Andrew Zoltay, Royal Roads University - based on Petr Skoda's code
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

if (!enrol_is_enabled('rru')) {
     die('enrol_rru plugin is disabled, sync is disabled');
}

$enrol = enrol_get_plugin('rru');

// Do the work here.
if ($enrol->populate_source()) {
    $enrol->sync_enrolments();
}

// Report any errors that have occurred.
$enrol->report_errors();
