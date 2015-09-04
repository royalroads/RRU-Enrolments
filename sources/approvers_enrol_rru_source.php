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
 * RRU enrolment approver enrolment source definition.
 *
 * 2012-09-12
 * @package    enrol
 * @subpackage rru
 * @copyright  2012 Andrew Zoltay, Royal Roads University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
/**
 * enrol class for approvers source, extends rru_source
 *
 * @package  enrol
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approvers_enrol_rru_source extends enrol_rru_source {

    /**
     * return specific settings relevant to this source
     *
     * @author Emma Irwin
     * date    2012-09-12
     * @return array
     */
    public function get_enrolment_settings(){
        return(array());
    }
    /**
     * Get the current enrolments and course identifiers from the SIS
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @return boolean
     */
    public function get_enrolment_data() {

        // Get fresh list of courses and their aprpovers  to manage enrolments.
        $enrolments = $this->fetch_approver_enrolments();

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
    private function fetch_approver_enrolments(){

        global $DB;

        try {
            $sql = "SELECT ga.id, c.idnumber AS coursecode, u.idnumber AS approverid
                    FROM {ga_status} ga
                    INNER JOIN {user} u
                    ON ga.approverid = u.idnumber
                    INNER JOIN {course} c
                    ON c.id = ga.courseid
                    WHERE ga.id IN (SELECT MAX(ga1.id) FROM {ga_status} ga1 GROUP BY ga1.courseid)
                    ORDER BY ga.courseid";
            $result = $DB->get_records_sql($sql);
        }
        catch(dml_exception $e) {
            $this->write_log("Failed to get approvers from database - DB error: [$e->debuginfo]", true);
            return false;
        }
        // Get approver role id.
        $approverroleid = $DB->get_field('role', 'id', array('shortname' => 'approver'), MUST_EXIST);

        // Get the data in the correct format for enrol_rru plugin to deal with it.
        $enrolments = array();

        foreach($result as $id => $record) {
            // Format enrolments.
            $enrolment = array();
            $enrolment['chrCourseCode'] = $record->coursecode;
            $enrolment['intUserCode'] = $record->approverid;
            $enrolment['intRoleID'] = $approverroleid;

            $enrolments[] = $enrolment;
        }
           return $enrolments;
    }


}
