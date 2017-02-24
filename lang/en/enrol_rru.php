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
 * collection of language strings specific to the RRU enrolment plugin
 *
 * 2012-08-23
 * @package     enrol
 * @subpackage  rru
 * @copyright   2012 Andrew Zoltay, Royal Roads University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'RRU enrolments';
$string['pluginname_desc'] = 'A custom enrolment plugin built by Royal Roads University to manage enrolments from Agresso Student World';

// Setting strings
$string['mischeader'] = "Miscellaneous Settings";
$string['email'] = "Error notification email (Non-Moodle account)";
$string['email_help'] = "Send error notifications to this email address";
$string['logpath'] = "Log file location";

//AZRevisit - find out what the correct web account is that writes to the file system
$string['logpath_help'] = "Path on the web server where RRU enrolments plugin will write the log file. Make sure the folder is writable by the account your web server is running under";
$string['manageenrolmentsync'] = 'Manage enrolment sync';
$string['syncsettingcaption'] = 'Enrolment sync management';
$string['runenrolmentsync'] = 'Run enrolment sync';
$string['runenrolmentsynchelp'] = 'Click to manually run the RRU enrolment sync now.';
$string['returntosettings'] = 'Return to RRU Enrolment settings page';
$string['syncresultsheader'] = 'Manual enrolment sync results';
$string['plugindisabled'] = 'RRU Enrolment plugin is disabled, sync is disabled';

// Student Enrolment
$string['source_student_enrolment'] = "Source - Student Enrolment (Agresso)";
$string['monthsahead'] = "Month enrolment range";
$string['monthsahead_help'] = "The number of months into the future that we want to enrol students";
$string['dbserver'] = "Database server";
$string['dbserver_help'] = "Database host server where the student enrolment data is stored";
$string['db'] = "Database name";
$string['db_help'] = "The name of the database in which the student enrolment data is stored";
$string['dbuser'] = "Database user name";
$string['dbuser_help'] = "The account name of the database user that has permission to the database objects in the student enrolment source";
$string['dbpwd'] = "Source DB user password";
$string['dbpwd_help'] = "Enter the password for the database user account that will access the external data";
$string['unenrol_help'] = "Checking this box, will disable unenrolment for this source";
$string['source_approver_enrolment'] = "Source - Approver Enrolment";
$string['source_mm_enrolment'] = "Source - Mastering Moodle Enrolment";
$string['rru:config'] = 'Configure RRU enrol instances';
$string['rru:enrol'] = 'Enrol users';
$string['rru:manage'] = 'Manage enrolled users';
$string['rru:unenrol'] = 'Unenrol users from course';
$string['rru:unenrolself'] = 'Unenrol self from the course';

// Notifications
$string['ntf_header'] = 'Notifications';
$string['ntf_sync_error_label'] = "SIS/Moodle Sync Error";
$string['ntf_sync_error_desc'] = "Email addresses (seperate with ';') that will be notified of sync error between SIS and Moodle";
$string['ntf_sync_error_subject'] = "Mismatch between SIS and Moodle Shell";
