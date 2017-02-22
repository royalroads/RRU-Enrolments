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
 * 2017-02-22
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
        global $DB;

        // We need a distinct list of roles held by the user in all contexts.
        $result = $DB->get_records_sql('SELECT DISTINCT roleid FROM {role_assignments} WHERE userid = ?', array($userid));

        // If something went wrong that was not an exception, return false.
        if (!$result) {
            return false;
        }

        // We want to return a simple array of integers, not the array of objects the query returned.
        $return = array();
        foreach ($result as $id) {
            $return[] = $id->roleid;
        }
        return $return;
    }

    /**
     * Resolves a category name into a short school code used in the group's idnumber field.
     * @param str $catname the category name
     * @return str the school code or bool false if not found;
     */
    private function getschoolcode($catname) {
        switch ($catname) {
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
        global $DB;

        // Get all of the course categories and parent categories in which the user has a non-student role.
        $sql = "
SELECT DISTINCT cc.id, cc.name AS 'category', cp.name AS 'parentcat'
     FROM {user} u
LEFT JOIN {role_assignments} ra ON u.id = ra.userid
LEFT JOIN {role} r ON r.id = ra.roleid
LEFT JOIN {context} cx ON cx.id = ra.contextid
LEFT JOIN {course} co ON co.id = cx.instanceid
LEFT JOIN {course_categories} cc ON cc.id = co.category
LEFT JOIN {course_categories} cp ON cp.id = cc.parent
    WHERE u.id = ?
      AND cx.contextlevel = 50
      AND ra.roleid <> 5";
        $resultset = $DB->get_records_sql($sql, array($userid));

        $result = array();

        // Iterate the found categories for this user.
        foreach($resultset as $row) {

            // Try to resolve the school from the course category.
            $schoolcode = $this->getschoolcode($row->category);

            // Did this fail?  Try again with the course's parent category.
            if (!$schoolcode) {
                $schoolcode = $this->getschoolcode($row->parentcat);
            }

            // Still no luck?  We don't recognize this category.  It might be a test category or
            // something else not associated with a school.  Skip it.
            if (!$schoolcode) { // No recognized categories or parent categories.
                continue;
            }

            // We have a school code (as used in the course groups' idnumber column).  Add it to the list if not already there.
            if (!in_array($schoolcode, $result)) {
                $result[]=$schoolcode;
            }
        }

        // Return false if there are no schools for this user.
        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    /**
     * Helper: Fetches the group ids in the course.
     *
     * @param int $courseid
     * @return array the idnumbers and ids or false on error
     * @global object $DB The Moodle DML API object
     */
    function get_groupids($courseid) {
         global $DB;
         $sql = 'SELECT idnumber, id FROM {groups} WHERE courseid = ?';
         try {
             $result = $DB->get_records_sql($sql, array($courseid));
         } catch (Exception $e) {
             return false;
         }
         return $result;
    }

    /**
     * Helper: Adds a user and group to the groups array
     *
     * @param int $userid The user to add
     * @param str $groupidnumber The group id number to add
     * @param array &$groups   The array to which we are adding a user-group association.
     * @param array &$groupids A reference array associating group idnumber with group id
     * @return false on error (group idnumber not in $groupids)
     */
    function add_to_groupsarray($userid, $groupidnumber, &$groups, &$groupids) {
        if (array_key_exists($groupidnumber, $groupids)) {
            $groups[] = array('uid'=>$userid, 'gid'=>$groupids[$groupidnumber]->id);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Helper: Adds a user to a group, unless the user is already a member.
     * Unlike groups_add_members, the user does not have to be enrolled in the course.
     *
     * @param int  $gid The group ID to add the user to
     * @param int  $uid The user ID to add to the group
     * @return none
     * @global $DB the Moodle DML API object
     */
    private function addusertogroup($gid, $uid) {
        global $DB;

        // Is the user already a member of the group?

        $result = $DB->record_exists('groups_members', array('groupid'=>$gid, 'userid'=>$uid));
        if ($result) { // We already have this user in this group.
            return;
        }

        // We don't have this user in this group yet, so add it.
        $data = new stdClass();
        $data->userid = $uid;
        $data->groupid = $gid;
        $data->timeadded = time();
        $data->component = '';
        $data->itemid = 0;
        $DB->insert_record('groups_members', $data);

    }

    /**
     * Assign users to groups within Orientation to Teaching & Design
     * Based on the user's school (school of the course they teach) or role in the university.
     * @author Gerald Albion
     * datex   2017-02-20
     *
     * @param  array $users
     * @param  int $courseid the course having the groups
     * @return boolean false on failure
     */
    private function assigngroups($users, $courseid) {

        // Validation
        if (!$courseid) { // Invalid course id
            return false;
        }

        // Fetch group ids for the course.
        $groupids = $this->get_groupids($courseid);

        // One array handles all group memberships.  array([userid, group id])
        $groupassignments = array();

        // Build a list of users for each group.
        foreach($users as $id => $user) { // id is userid NOT idnumber

            // Init flag.
            $foundgroup=false;

            // Role groups
            $roles = $this->getuserroles($id);
            if ($roles) {
                foreach ($roles as $roleid) {
                    switch ($roleid) {
                        case 12 : // Program Associate = "Program Staff" group
                            if ($this->add_to_groupsarray($id, 'PROGRAMSTAFF', $groupassignments, $groupids)) {
                                $foundgroup = true;
                            }
                            break;
                        case 15:
                        case 17: // Programlead-director or programlead_directorisc = "Program Heads" group
                            if ($this->add_to_groupsarray($id, 'PROGRAMHEADS', $groupassignments, $groupids)) {
                                $foundgroup = true;
                            }
                            break;
                        case 9:
                        case 11:
                        case 13: // CTETAdmin, Library, Help Desk = "Other RRU Staff" group
                            if ($this->add_to_groupsarray($id, 'OTHERRRUSTAFF', $groupassignments, $groupids)) {
                                $foundgroup = true;
                            }
                            break;
                        default: // No default here.
                    }
                }
            }
            // School groups
            $schools = $this->getuserschools($id);

            if ($schools) { // There might not be any schools for this user
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
                            if ($this->add_to_groupsarray($id, $school, $groupassignments, $groupids)) {
                                $foundgroup = true;
                            }
                            break;
                    }
                }
            }

            // If no roles or schools, add "Other RRU Staff"
            if (!$foundgroup) {
                $this->add_to_groupsarray($id, 'OTHERRRUSTAFF', $groupassignments, $groupids);
            }
        }

        // Assign users to course groups.
        foreach ($groupassignments as $groupassignment) {
            $this->addusertogroup($groupassignment['gid'], $groupassignment['uid']);
        }
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
SELECT DISTINCT u.id, u.idnumber AS teacherid
           FROM {role_assignments} ra
     INNER JOIN {context} cx ON cx.id = ra.contextid
     INNER JOIN {course} co ON co.id = cx.instanceid
     INNER JOIN {user} u ON u.id = ra.userid
          WHERE roleid IN (3, 4, 9, 10, 12, 13, 15, 17, 18, 20) -- editing, non-editing, live course instructors; CTET Admins; Approvers; Help Desk; Program Directors; Program Leads; PAs
            AND cx.contextlevel in (40, 50) -- course or category
            AND co.idnumber <> '' -- course must have an idnumber value
            AND u.idnumber <> '' -- user idnumber must not be blank
       ORDER BY co.fullname, u.lastname;
             ";

            $result = $DB->get_records_sql($sql);
        }
        catch(dml_exception $e) {
            $this->write_log("Failed to get course participants from database - DB error: [$e->debuginfo]", true);
            return false;
        }

		// Get the course ID for enrolment.
		$courseidnum = 'TEACHING_COURSE_DESIGN';
        $courseid = $DB->get_field('course', 'id', array('idnumber'=>$courseidnum), MUST_EXIST);

        // Assign the groups.
        $this->assigngroups($result, $courseid);

        // Get the role id for students
        $studentroleid = $DB->get_field('role', 'id', array('archetype'=>'student'), MUST_EXIST);

        // Prepare the return array.
        $enrolments = array();
        foreach($result as $id => $record) {
            // Format enrolments.
            $enrolment = array();
            $enrolment['chrCourseCode'] = $courseidnum;
            $enrolment['intUserCode'] = $record->teacherid;
            $enrolment['intRoleID'] = $studentroleid;
            $enrolments[] = $enrolment;
        }

        // Return the students to enrol.
        return $enrolments;
    }
}
