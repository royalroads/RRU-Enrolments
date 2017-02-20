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
 * 2017-02-14
 * @package    enrol
 * @subpackage rru
 * @author     Gerald Albion, Andy Zoltay
 * @copyright  © 2017 Royal Roads University
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
     * Fetches all roles the user has in all contexts
     *
     * @param int $userid
     * @return mixed array The roleids or bool false if error
     * global $DB the Moodle DML API object
     */
    private function getuserroles($userid) {
        // We need a distinct list of roles held by the user in all contexts.
        $result = $DB->get_records_sql('SELECT DISTINCT roleid FROM {role_assignments} WHERE userid = ?', array($userid));

        // If something went wrong that was not an exception, return false.
        if (!$result) {
            return false;
        }

        // We want to return a simple array of integers, not the array of objects the query returned.
        $return = array();
        foreach ($result as $id->data) {
            $return[] = $id;
        }
        return $return;
    }

    /**
     * Resolves a category name into a short school code used in the group's idnumber field.
     * @param str $catname the category name
     * @return str the school code or bool false if not found;
     */
    private function getschoolcode($catname) {
        switch ($cat) {
            case 'CCWI'         : return 'CCWI';
            case 'INTRDISCPL'   : return 'CIS';
            case 'ISC'          : return 'ISC';
            case 'COMM-CULTURE' : return 'SCC';
            case 'ENVIRONMENT'  : return 'SES';
            case 'EDUTECH'      : return 'SET';
            case 'CONFLICT'     : return 'SHS';
            case 'MGMT-GRAD'    :
            case 'MGMT-UGRAD'   : return 'SOB';
            case 'LEADERSHIP'   : return 'SOLS';
            case 'TOURHOSPMGMT' : return 'STHM';
            default             : return false;
        }
    }

    /**
     * Fetches all the schools in which the user has some kind of instructor role
     *
     * @param int $userid ID of the user in question
     * @return mixed array the schools (array of strings) or bool false if error
     * @global $DB the Moodle DML API object
     */
    private function getuserschools($userid) {
        // Get all of the course categories and parent categories in which the user has a non-student role.
        $sql = "
SELECT DISTINCT cc.name AS 'category', cp.name AS 'parentcat'
     FROM mdl_user u
LEFT JOIN mdl_role_assignments ra ON u.id = ra.userid
LEFT JOIN mdl_role r ON r.id = ra.roleid
LEFT JOIN mdl_context cx ON cx.id = ra.contextid
LEFT JOIN mdl_course co ON co.id = cx.instanceid
LEFT JOIN mdl_course_categories cc ON cc.id = co.category
LEFT JOIN mdl_course_categories cp ON cp.id = cc.parent
    WHERE u.idnumber = ?
      AND cx.contextlevel = 50
      AND ra.roleid <> 5";
        $resultset = $DB->get_records_sql($sql, array($userid));

        $result = array();

        // Iterate the found categories for this user.
        foreach($resultset as $row) {

            // Try to resolve the school from the course category.
            $schoolcode = getschoolcode($row->category);

            // Did this fail?  Try again with the course's parent category.
            if (!$schoolcode) {
                $schoolcode = getschoolcode($row-parentcat);
            }

            // Still no luck?  We don't recognize this category.  It might be a test category or
            // something else not associated with a school.  Skip it.
            if (!$schoolcode) { // No recognized categories or parent categories.
                continue;
            }

            // We have a school code (as used in the course groups' idnumber column).  Add it to the list.
            $result[]=$schoolcode;
        }

        // Return false if there are no schools for this user.
        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    /**
     * Assign users to groups within Orientation to Teaching & Design
     * Based on the user's school (school of the course they teach) or role in the university.
     * @author Gerald Albion
     * date       2017-02-17
     *
     * @param  array $users
     * @return boolean false on failure
     * @global object $DB The Moodle DML API object
     */
    private function assigngroups($users) {
        // Init group memberships.
        $group                    = array();
        $group['DEANORDESIGNATE'] = array(); // Deans - not currently used here.  Populate this group manually.
        $group['PROGRAMHEADS']    = array(); // Program heads (Program leads)
        $group['PROGRAMSTAFF']    = array(); // Program staff (PAs)
        $group['SCHOOLDIRECTORS'] = array(); // Directors - not currently used here.  Populate this group manually.
        $group['OTHERRRUSTAFF']   = array(); // Other RRU staff.  Library, Help Desk, CTET Admin and anyone without any other role or school.
        $group['CCWI']            = array(); // CCWI
        $group['CIS']             = array(); // Interdiciplinary Studies
        $group['ISC']             = array(); // ISC
        $group['SCC']             = array(); // Comm & Culture
        $group['SES']             = array(); // Environment
        $group['SET']             = array(); // EduTech
        $group['SHS']             = array(); // Humanitarian studies / conflict
        $group['SOB']             = array(); // School of Business
        $group['SOLS']            = array(); // Leadership
        $group['STHM']            = array(); // Tourism Hospitality Management

        // Build a list of users for each group.
        foreach($users as $id => $user) { // id is user id, always same as $user->teacher

            // Init flag.
            $foundgroup=false;

            // Role groups
            $roles = $this->getuserroles($id);
            foreach ($roles as $roleid) {
                switch ($roleid) {
                    case 12 : // Program Associate = "Program Staff" group
                        $group['PROGRAMSTAFF'][]=$id;
                        $foundgroup = true;
                        break;
                    case 15:
                    case 17: // Programlead-director or programlead_directorisc = "Program Heads" group
                        $group['PROGRAMHEADS'][]=$id;
                        $foundgroup = true;
                        break;
                    case 9:
                    case 11:
                    case 13: // CTETAdmin, Library, Help Desk = "Other RRU Staff" group
                        $group['OTHERRRUSTAFF'][]=$id;
                        $foundgroup = true;
                        break;
                    default: // No default here.
                }
            }

            // School groups
            $schools = $this->getuserschools($id);
            foreach($schools as $school) {
                switch ($school) {
                    case 'CCWI':
                    case 'CIS':
                    case 'ISC':
                    case 'SCC':
                        case 'SES':
                    case 'SET':
                    case 'SHS':
                    case 'SOB':
                    case 'SOLS':
                    case 'STHM':
                        $group[$school][]=$id;
                        $foundgroup = true;
                        break;
                }
            }

            // If no roles or schools, add "Other RRU Staff"
            if (!$foundgroup) {
                $group_other[]=$id;
            }

        }
        // Get group ids for the course.

        // Assign groups.
    }

    /**
     * Get the current enrolments and course identifiers from the SIS
     *
     * @author Gerald Albion, Andrew Zoltay
     * updated 2017-02-14
     * @global object $DB - Moodle database object
     * @return array of db records or false if orror occurs
    */
    private function fetch_instructor_mm_enrolments(){
        global $DB;

        try {
            //Get a list of all instructors (of all kinds), program staff, approvers, and other RRU staff who should be enrolled.
            $sql = "
SELECT DISTINCT ra.userid AS teacherid
           FROM mdl_role_assignments ra
     INNER JOIN mdl_context cx ON cx.id = ra.contextid
     INNER JOIN mdl_course co ON co.id = cx.instanceid
     INNER JOIN mdl_user u ON u.id = ra.userid
          WHERE roleid IN (3, 4, 9, 10, 12, 13, 15, 17, 18, 20)
            AND cx.contextlevel in (40, 50)
            AND co.idnumber <> ''
            AND u.idnumber <> ''
       ORDER BY co.fullname, u.lastname;
             ";

            $result = $DB->get_records_sql($sql);
        }
        catch(dml_exception $e) {
            $this->write_log("Failed to get course participants from database - DB error: [$e->debuginfo]", true);
            return false;
        }

        /*
         * It may seem a bit like putting the cart before the horse to assign users to groups in the course now, before the users are
         * actually enrolled, but this is how we can keep this special case for this enrolment source in the instructors_mm submodule.
         */
        $this->assigngroups($result);

        /*
         * Prepare the enrolments output.
         */
        $coursename = 'Orientation to Teaching & Course Design';
        try {
            $courseid = $DB->get_field('course', 'idnumber', array('fullname'=>$coursename), MUST_EXIST);
        } catch (Exception $e) {
            $this->write_log("Unable to get idnumber for '{$coursename}'.\nException message: " . $e->getMessage() . "\n");
            $this->write_log("Instructor and staff enrolments in {$coursename} will NOT be updated.\n");
        }

        if (!$courseid) {
            $this->write_log("'{$coursename}' exists but unable to get idnumber.  Make sure the course has a non-blank idnumber.\n");
            $this->write_log("Instructor and staff enrolments in {$coursename} will NOT be updated.\n");
        }

        $studentroleid = $DB->get_field('role', 'id', array('archetype'=>'student'), MUST_EXIST);
        // Get the data in the correct format for enrol_rru plugin to deal with it.
        $enrolments = array();

        foreach($result as $id => $record) {
            // Format enrolments.
            $enrolment = array();
            $enrolment['chrCourseCode'] = $courseid;
            $enrolment['intUserCode'] = $record->teacherid;
            $enrolment['intRoleID'] = $studentroleid;

            $enrolments[] = $enrolment;
        }
        return $enrolments;
    }


}
