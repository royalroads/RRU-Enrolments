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
 * RRU enrolment , enrol instructors as students in Mastering Moodle Course
 *
 * 2012-09-12
 * @package    enrol
 * @subpackage rru
 * @copyright  2012 Andrew Zoltay, Royal Roads University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
/**
 * enrol class for instructors in Mastering Moodle  source, extends rru_source
 *
 * @package  enrol
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instructors_mm_enrol_rru_source extends enrol_rru_source {

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

        // Get fresh list of courses and students to manage enrolments.
        $enrolments = $this->fetch_instructor_mm_enrolments();

        return $enrolments;
    }


    /**
     * Get the current enrolments and course identifiers from the SIS
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @global object $DB - Moodle database object
     * @return array of db records or false if orror occurs
    */
    private function fetch_instructor_mm_enrolments(){
        global $DB;

        try {
            //Get a list of all instructors.
            $sql = "
              SELECT
              DISTINCT usr.idnumber AS teacherid
              FROM mdl_course c
              INNER JOIN mdl_context cx
              ON c.id = cx.instanceid
              AND cx.contextlevel = '50'
              INNER JOIN mdl_role_assignments ra
              ON cx.id = ra.contextid
              INNER JOIN mdl_role r ON ra.roleid = r.id
              INNER JOIN mdl_user usr ON ra.userid = usr.id
              WHERE r.archetype = 'editingteacher'
              AND c.idnumber  !=''
              AND usr.idnumber !=''
              ORDER BY c.fullname, usr.lastname;
             ";

            $result = $DB->get_records_sql($sql);
        }
        catch(dml_exception $e) {
            $this->write_log("Failed to get approvers from database - DB error: [$e->debuginfo]", true);
            return false;
        }
        $masteringmoodleid = $DB->get_field('course', 'idnumber', array('fullname'=>'Mastering Moodle'), MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', array('archetype'=>'student'), MUST_EXIST);
        // Get the data in the correct format for enrol_rru plugin to deal with it.
        $enrolments = array();
        foreach($result as $id => $record) {
            // Format enrolments.
            $enrolment = array();
            $enrolment['chrCourseCode'] = $masteringmoodleid;
            $enrolment['intUserCode'] = $record->teacherid;
            $enrolment['intRoleID'] = $studentroleid;

            $enrolments[] = $enrolment;
        }
        return $enrolments;
    }


}
