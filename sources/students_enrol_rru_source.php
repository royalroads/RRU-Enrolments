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
 * RRU enrolment student enrolment source definition.
 *
 * 2012-09-12
 * @package    enrol
 * @subpackage rru
 * @copyright  2012 Andrew Zoltay, Royal Roads University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
* Require our notification/email class
**/
require_once(dirname(dirname(__FILE__)) . '/classes/notification.php');


/**
 * enrol class for students source, extends rru_source
 *
 * @package  enrol
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class students_enrol_rru_source extends enrol_rru_source {

    /**
     * return specific settings relevant to this source
     *
     * @author Emma Irwin
     * date    2012-09-12
     * @return array of setting value with structure
     * admin_setting name => array(description, help_text, and default)
     */
    public function get_enrolment_settings() {
        $enrol_settings = array(
        "enrol_rru/monthsahead" => array("description" => get_string('monthsahead', 'enrol_rru'), "help_text" => get_string('monthsahead_help', 'enrol_rru'), "default" => 9),
        "enrol_rru/dbserver" => array("description" => get_string('dbserver', 'enrol_rru'), "help_text" => get_string('dbserver_help', 'enrol_rru'), "default" => ''),
        "enrol_rru/db" => array("description" => get_string('db','enrol_rru'), "help_text" => get_string('db_help', 'enrol_rru'), "default" => ''),
        "enrol_rru/dbuser" => array("description" => get_string('dbuser', 'enrol_rru'), "help_text" => get_string('dbuser_help', 'enrol_rru'), "default" => '')
        );
        return($enrol_settings);
    }
    /**
     * Get the current enrolments and course identifiers from the SIS
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @return boolean
     */
    public function get_enrolment_data() {
        // Connect to External student enrolment source database.
        if (!$conn = $this->rru_mssql_connect()) {
            return false;
        }

        $months = get_config('enrol_rru', 'monthsahead');

        // Get fresh list of courses and students to manage enrolments.
        $enrolments = $this->fetch_student_enrolments($conn, $months);

        // Close connection to External student enrolment source.
        $this->rru_mssql_disconnect($conn);

        return $enrolments;
    }


    /**
     * Get the current enrolments and course identifiers from the SIS
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @global object $DB - Moodle database object
     * @param link_identifier $sourceconn - connection to enrolment source db
     * @param integer $monthsahead - number of months into the future to get enrolments
     * @return array of db records or false if error occurs
     */
    private function fetch_student_enrolments($sourceconn, $monthsahead){
        global $DB;

        if ($sourceconn) {
            $query = "EXEC Learn.usp_GetStudentEnrolments @intMonthsBeforeStart = $monthsahead;";
            $result = mssql_query($query, $sourceconn);

            if (!$result) {
                $this->write_log("Error calling Learn.usp_GetStudentEnrolments: " . mssql_get_last_message(), true);
                return false;
            }



            // Get student role id.
            $studentroleid = $DB->get_field('role', 'id', array('archetype' => 'student'), MUST_EXIST);

            // Get the data in the correct format for enrol_rru plugin to deal with it.
            $enrolments = array();

            /* 
            * Keep track of SIS offerings that
            * do not have a corresponding Moodle shell
            **/
            $orphans = [];

            while($row = mssql_fetch_assoc($result)) {
            
                // Check if chrCourse_Code is an orphaned offering (only if not already identified as such)
                if(!in_array($row['chrCourse_Code'], $orphans)) {

                    $shell = $DB->record_exists('course',array('idnumber' => $row['chrCourse_Code']));

                    if(!$shell) {
                        $orphans[] = $row['chrCourse_Code'];
                    }

                }


                // Format enrolments.
                $enrolment = array();
                $enrolment['chrCourseCode'] = $row['chrCourse_Code'];
                $enrolment['intUserCode'] = $row['intStudent_PK'];
                $enrolment['intRoleID'] = $studentroleid;

                $enrolments[] = $enrolment;
            }

            // Notify "the authorities" if orphans exist
            count($orphans) > 0 ? rru_enrol\notification::send($orphans) : null;

            return $enrolments;

        }else {
            $this->write_log("Connection not established", true);
            return false;
        }
    }


    /**
     * Connect to MS SQL Server
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @global $RRU_ENROL_CFG - configuration settings for RRU enrolments
     * @return MS SQL Link identifier or false
    */
    private function rru_mssql_connect() {

        // Get connection info.
        $port = '1433'; // MS SQL Server port

        // AZRevisit - where to move config settings?
        $dbserver = get_config('enrol_rru', 'dbserver');
        $dbname = get_config('enrol_rru', 'db');
        $dbuser = get_config('enrol_rru', 'dbuser');
        $dbpwd = get_config('enrol_rru', 'dbpwd');

        // Confirm connection params are set.
        if (!$dbserver or !$dbname or !$dbuser) {
            $this->write_log("Missing connection settings", true);
            die('Missing RRU enrolments database settings');
        }

        $this->write_log('Connecting to ' . $dbserver . '...');

        if (!$mssqllink = mssql_connect($dbserver . ':' . $port, $dbuser, $dbpwd)) {
            $mserror = mssql_get_last_message();
            $this->write_log('Unable to connect to: '. $dbserver . ' - MS SQL Server error message: [' . $mserror . ']', true);
            return false;
        }

        if (mssql_select_db ($dbname, $mssqllink)) {
            $this->write_log('Connected to ' . $dbname . ' database');
            return $mssqllink;
        } else {
            $this->write_log('failed to select ' . $dbname . ' database', true);
            return false;
        }
    }

    /**
     * Close connection to MS SQL Server
     *
     * @author Andrew Zoltay
     * date    2012-08-26
     * @param MS SQL Link identifier $conn to be closed
     * @return none
    */
    private function rru_mssql_disconnect(&$connection) {
        if (!mssql_close($connection)) {
            $this->write_log("Failed to close MS SQL Server connection", true);
        }
    }


}
