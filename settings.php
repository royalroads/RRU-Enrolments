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
 * RRU enrolments plug-in version specification.
 *
 * 2012-08-23
 * @package    enrol
 * @subpackage rru
 * @copyright  2012 Andrew Zoltay, Royal Roads University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/enrol/rru/lib.php');
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('enrol_rru_info', '', get_string('pluginname_desc', 'enrol_rru')));

    $settings->add(new admin_setting_heading('enrol_rru_misc', get_string('mischeader', 'enrol_rru'), ''));
    $settings->add(new admin_setting_configtext('enrol_rru/email', get_string('email', 'enrol_rru'), get_string('email_help', 'enrol_rru'), ''));
    $settings->add(new admin_setting_configdirectory('enrol_rru/logpath', get_string('logpath', 'enrol_rru'), get_string('logpath_help', 'enrol_rru'), '/var/web/moodledata/logs/'));

    // Get enrolment sources (if any).
    $location = $CFG->dirroot . '/enrol/rru/sources';

    // Grab all data sources in the sources folder (but skip over .. . and .svn dirs.
    $sources = array_diff(scandir($location), array('..', '.', '.svn')); // Skip . and .. entries

    // Add the option to disable 'unenrolment' on a per source basis.
    // TODO: right now this only supports printing out configtext admin types.
    // TODO: move this piece  into the enrol/lib.php and have it return all settings (incuding headings) for printout.
    // TODO: basically all logic around this should be in lib.php, only printout should be done here.
    foreach ($sources as $source) {

        $sourceclass = substr($source, 0, -4);

        // Add header for this source.
        $settingheader =  ucfirst(str_replace ('_',' ',rtrim($sourceclass, "_rru_source")));
        $settings->add(new admin_setting_heading($sourceclass, $settingheader, ''));

        // Add option to unenrol for this source (a default for rru sources.
        $settings->add(new admin_setting_configcheckbox('enrol_rru/' . $sourceclass . '_disable_unenrol','Disable unenrol',get_string('unenrol_help','enrol_rru'), 0));

        // Needed for class functions
        require_once($location . '/' . $source);
        $sourceclass = substr($source, 0, -4);
      	if (!class_exists($sourceclass)) {
            continue; // Skip to next source.
        }
        $settingsource = new $sourceclass();

        // Get custom settings for this source.
        $source_settings = $settingsource->get_enrolment_settings();

        // Create new admin settings.
        // TODO: (as mentioned above, add support for oather admin setting types).
        foreach ($source_settings as $key => $source_setting) {
          $settings->add(new admin_setting_configtext($key, $source_setting['description'], $source_setting['help_text'], $source_setting['default']));
        }
    }




    /**
    * @author        Jacek Pawel Polus
    * @copyright     2017 Royal Roads University
    * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
    */

    /**
    * Add a new header to organize
    * Current and Future notification settings
    */
    $name = "enrol_rru/notifications";
    $label = get_string('ntf_header','enrol_rru');
    $desc = "";

    $heading = new admin_setting_heading($name,$label,$desc);
    $settings->add($heading);


            /**
            * Setting:
            * In case SIS tries to enrol students in a shell that doesn't exist
            * notify this email
            */
            $name = "enrol_rru/sync_notification";
            $label = get_string('ntf_sync_error_label','enrol_rru');
            $desc = get_string('ntf_sync_error_desc','enrol_rru');
            $default = '';

            $setting = new admin_setting_configtext($name,$label,$desc,$default);
            $settings->add($setting);






    /**
    * @author        Gerald Albion
    * @copyright     2017 Royal Roads University
    * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
    */
    // Add enrolment sync button
    $name = 'enrol_rru/sync';
    $title = get_string('manageenrolmentsync', 'enrol_rru'); // Manage Enrolment sync
    $url = $CFG->wwwroot . '/enrol/rru/runsync.php';
    $description = "<div class='form-item'>
                      <div class='form-label'><label>".get_string('syncsettingcaption', 'enrol_rru')."</label></div>
                          <div class='form-setting'>
                          <a href=\"{$url}\">
                          <button type=\"button\">".get_string('runenrolmentsync', 'enrol_rru')."</button>
                        </a><br/>".get_string('runenrolmentsynchelp', 'enrol_rru')."
                                    </div>
                    </div>";
    $setting = new admin_setting_heading($name, $title, $description);
    $settings->add($setting);
}


