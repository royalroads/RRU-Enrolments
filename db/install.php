<?php

/**
 * install.php, make necessary DB changes
 *
 * Make changes to the database for customizations that
 * are required by the rru plugin for student enrolments
 *
 * 2011-06-01
 * @package      enrol
 * @subpackage   rrue
 * @copyright    2012 Emma Irwin, Royal Roads University
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Hook into upgradelib.php to install any Enrol RRU db requirements
 */
function xmldb_enrol_rru_install() {
    global $DB, $CFG;

    // Initialize the Enrol RRU last update run timestamp.
    set_config('autoupdate_last_run', time(), 'enrol_rru');

}
