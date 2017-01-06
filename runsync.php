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
 * GUI sync for RRU enrolments synchronization.
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
 * @author     Gerald Albion, Andy Zoltay
 * @copyright  2016 Royal Roads University - based on Petr Skoda's code
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Prints the Return to Settings button.
 * @param  none
 * @return none
 * @global object $CFG The Moodle config object
 */
function printreturnbutton() {
    global $CFG;
    $url = $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsrru';
    print "<a href=\"{$url}\">";
    print "<button type=\"button\">".get_string('returntosettings', 'enrol_rru')."</button>";
    print "</a>";
}

require(dirname(dirname(dirname(__FILE__))).'/config.php');

// Set up page.
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('pluginname', 'enrol_rru'));
$PAGE->set_heading(get_string('syncresultsheader', 'enrol_rru'));
$PAGE->set_pagelayout('base');
$PAGE->set_url($CFG->wwwroot . '/enrol/rru/runsync.php');

// Print the page header.
print $OUTPUT->header();

// Do not try to sync enrolment if the plugin is disabled.
if (!enrol_is_enabled('rru')) {
    print get_string('plugindisabled', 'enrol_rru');
    printreturnbutton();
    print $OUTPUT->footer();
    die;
}

// Start a scrolling div which will contain the sync output.
print '<div style="border:1px lightgray solid; width:90%; height:520px; padding:6px; margin-bottom:10px; overflow:auto;">';

// Do the enrolment sync.
$enrol = enrol_get_plugin('rru');
$enrol->displaylog = true; // Display output to console (web browser).
if ($enrol->populate_source()) {
    $enrol->sync_enrolments();
}

// Report any errors.
$enrol->report_errors();

// Close the scrolling div.
print "</div>";

// Display a button to return to RRU Enrolment settings page.
printreturnbutton();

// Display the page footer.
print $OUTPUT->footer();
