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
 * RRU enrolment plug-in.
 *
 * 2012-08-23
 * @package    enrol
 * @subpackage rru
 * @copyright  2012 Andrew Zoltay, Royal Roads University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * RRU enrolment plugin implementation.
 *
 * 2012-08-23
 * @copyright   2012 Andrew Zoltay, Royal Roads University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_rru_plugin extends enrol_plugin {

    private $errors;
    protected $enroltype = 'enrol_rru';

    /**
     * Constructor for the plugin.
     */
    function __construct() {
        $this->errors = false;
    }

    /**
     * Populate the enrolment source table in Moodle
     * with data from external system
     *
     * @author Andrew Zoltay
     * date    2012-08-26
     * @global object $CFG - Moodle configuration object
     * @return boolean success/failure
    */
    public function populate_source() {
        global $CFG;

        $totalenrolmentcount = 0;
        $this->write_log("-------------------------------------------------------------------");
        $this->write_log("Beginning RRU enrolment sync process.");
        $this->write_log("-------------------------------------------------------------------");

        $this->prep_enrolments_table();

        // Load source based on whatever custom sources are defined.

        // Get enrolment sources (if any).
        $location = $CFG->dirroot . '/enrol/rru/sources';

        $sources = array_diff(scandir($location), array('..', '.', '.svn')); // Skip . and .. entries.

        foreach ($sources as $source) {
            $log_prefix = $source . ' ~ ';

            $this->write_log("*******************************************************************");
            $this->write_log('*****' . $source . '*****');
            $this->write_log("-------------------------------------------------------------------");


            include_once($location . '/' . $source);
            // Strip last 4 chars from file name to create the source class name
            // E.g. 'students_enrol_rru_source.php' becomes 'students_enrol_rru_source'.
            $sourceclass = substr($source, 0, -4);

            if (!class_exists($sourceclass)) {
                $this->write_log($log_prefix . "Source class not found for $source", true);
                continue; // Skip to next source.
            }

            $this->write_log($log_prefix . 'Preparing to load enrolments from ' . $sourceclass . '...');
            $enrolsource = new $sourceclass();
            $enrolments = $enrolsource->get_enrolment_data();

            // Check for errors first.
            if ($enrolsource->has_error()) {
                $this->write_log($log_prefix . "An error occurred while populating mdl_rru_enrolments", true);
            } else if (!$enrolments) {
                $this->write_log($log_prefix . "No enrolments were pulled from $sourceclass source");
                continue; // Skip to next source.
            }

            // Load the table.
            $this->write_log($log_prefix . 'Loading rru_enrolments table with data from ' . $sourceclass . ' source...');
            $totalenrolmentcount .= $this->load_enrolments_table($enrolments, $sourceclass);

        }

        if ($totalenrolmentcount == 0) {
            $this->write_log($log_prefix . "No enrolments were pulled from sources", true);
            return false;
        }

        return true;
    }
    /**
    * Synchronize enrolments in mdl_rru_enrolments with Moodle
    *
    * Dependencies:
    *          - mdl_rru_enrolments table
    *          - mdl_uvw_rru_enrolments view
    *
    * @author Andrew Zoltay
    * date    2012-09-12
    * @global object $DB - Moodle database object
    */
    function source_settings() {


    }
    /**
     * Synchronize enrolments in mdl_rru_enrolments with Moodle
     *
     * Dependencies:
     *          - mdl_rru_enrolments table
     *          - mdl_uvw_rru_enrolments view
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @global object $DB - Moodle database object
     */
    public function sync_enrolments() {
        global $DB;
        // Enrolment should start the day of creation.
        $today = time();
        $timestart = make_timestamp(date('Y',$today), date('m', $today), date('d',$today),0,0,0);
        $timeend = 0;  // Enrolment should be unlimited.

        // Handle enrolment methods.
        // Create new RRU enrolment methods if they don't exist for the courses we're interested in.
        // We need these to enrol our users.
        $this->create_enrol_instances();

        // Handle any enrolments that exist in mdl_rru_enrolments.
        $enrolments = $this->get_enrolments();
        if (!$enrolments) {
            $this->write_log("No new enrolments found");
        } else {
            $this->write_log("Enroling users in courses...");
            $currentcourseid = 0;
            foreach ($enrolments as $enrolment) {
                // Need course/manager object for enrolment management for each course.
                if ($currentcourseid != $enrolment->courseid) {
                    $course = $DB->get_record('course', array('id' => $enrolment->courseid), '*', MUST_EXIST);
                    $context = context_course::instance($course->id);

                    $enrolinstance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'rru'));
                    $currentcourseid = $course->id;
                }
                // Enrol the student.
                $this->enrol_user($enrolinstance, $enrolment->userid, $enrolment->roleid, $timestart, $timeend);
                $this->write_log( $enrolment->source . " - Successfully enroled user: '$enrolment->username' in $course->fullname");
            }
        }

        // Handle unenrolments.
        $unenrolments = $this->get_unenrolments();

        // 2014-07-10 gta
        // Sanity-check number of unenrolments.
        define ('UNENROL_SANITY', 700);
        $proposed_unenrolments = count($unenrolments);
        if ($proposed_unenrolments > UNENROL_SANITY) {
            $unenrolments = FALSE; // Set to same condition as error getting enrolments.
            // Log: sanity check failed.  TRUE passed in error parameter to trigger email notification.
            $this->write_log("Sanity check failed: {$proposed_unenrolments} enrolments proposed for removal; sanity threshold is ".UNENROL_SANITY.".  No unenrolments were performed.  If unenroling {$proposed_unenrolments} enrolments was intended, please contact Computer Services.", TRUE);
        }

        if (!$unenrolments){
            $this->write_log("No new unenrolment records found");
        } else {
            $this->write_log("Unenroling users...");
            $currentcourseid = 0;

            foreach ($unenrolments as $enrolment) {
                // Need course/manager object for enrolment management for each course.
                if($currentcourseid != $enrolment->courseid) {
                    $course = $DB->get_record('course', array('id'=>$enrolment->courseid), '*', MUST_EXIST);
                    $context = context_course::instance($course->id, MUST_EXIST);

                    $enrolinstance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'rru'));
                    $currentcourseid = $course->id;
                }
                // if this source setting for 'do not unenrol' is enabled, skip over.
                // Ideally we should *know* this on a per source, not per enrolment situation.
                if(!get_config('enrol_rru', $enrolment->source . '_disable_unenrol')){
                    // If the role is approver.
                    if ($enrolment->roleid == 10 ) {
                        // If the user has more than 1 role
                        // remove the approver role
                        // otherwise unenrol the user.
                        $sqlcount = "SELECT COUNT(*) as count FROM {role_assignments}
                        WHERE contextid = ". $context->id . " AND userid = ". $enrolment->userid;
                        $count = $DB->get_record_sql($sqlcount);

                        if ($count->count >= 2) {
                            role_unassign($enrolment->roleid, $enrolment->userid, $context->id, 'enrol_rru', $enrolment->enrolid);
                        } else {
                            $this->unenrol_user($enrolinstance, $enrolment->userid);
                        }
                    } else {
                        $this->unenrol_user($enrolinstance, $enrolment->userid);
                    }
                    $this->write_log( $enrolment->source . " - Successfully unenroled user: '$enrolment->username' from $course->fullname");
                }
            }
        }

        $this->write_log("RRU enrolments complete");
    }


    /**
     * Create necessary RRU enrol instances for courses
     * that do not have them
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @global object $DB - Moodle database object
     */
    private function create_enrol_instances() {
        global $DB;

        // Get a list of all the courses in which we're going to enrol peeps
        // that do not have existing RRU enrolment instances.
        try {
            $sql = "SELECT
                        DISTINCT c.id,
                        re.mdlcourseidnumber,
                        re.source
                        FROM {course} c
                        INNER JOIN {rru_enrolments} re ON (c.idnumber = re.mdlcourseidnumber)
                        WHERE NOT EXISTS (SELECT 1 FROM {enrol}
                                    WHERE courseid = c.id
                                    AND enrol = 'rru');";

            $results = $DB->get_recordset_sql($sql);
            foreach ($results as $course) {
                // Create a new one if one doesn't exist.
                if ($this->add_instance($course)) {
                    $this->write_log("Added RRU enrol instance for $course->mdlcourseidnumber");
                } else {
                    $this->write_log("Failed to add RRU enrol instance for $course->mdlcourseidnumber", true);
                }
            }
            $results->close();

        } catch(dml_exception $e) {
            $this->write_log("Error while adding RRU enrol instances - DB error: [$e->debuginfo]", true);
        }

    }


    /**
     * Return a list of new SIS enrolments to populate
     *
     * @author Andrew Zoltay
     * date    2011-08-26
     * @global object $DB - Moodle database object
     * @return mixed_array of enrolment info required to create Moodle enrolments
     *         or false if db error occurs
    */
    private function get_enrolments() {
        global $DB;

        try {
             $sql = "SELECT re.id, re.roleid, c.id AS courseid, c.idnumber, c.fullname, u.id AS userid, u.username, re.mdluseridnumber,
                     e.id AS enrolid,re.source
                     FROM {rru_enrolments} re
                     INNER JOIN {course} c ON (c.idnumber = re.mdlcourseidnumber)
                     INNER JOIN {user} u ON (re.mdluseridnumber = u.idnumber)
                     INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'rru')
                     WHERE NOT EXISTS (SELECT 1 FROM {role_assignments} vr
                              INNER JOIN {user} vu ON vu.`id` = vr.`userid`
                              INNER JOIN {context} vct ON vct.`id` = vr.`contextid`  and vct.`contextlevel` = 50
                              INNER JOIN {user_enrolments} vue ON vue.`userid` = vu.`id`
                              INNER JOIN {enrol} ve ON ve.`id` = vue.`enrolid` and ve.`courseid` = vct.`instanceid`
                              INNER JOIN {course} vc ON vc.`id` = vct.`instanceid`
                              WHERE vu.`id` = u.id AND vc.`id` = c.id
                              AND ve.`enrol` = 'rru' AND vr.roleid = re.roleid)
                     AND re.mdlcourseidnumber <> ''
                     ORDER BY c.id, re.roleid, u.id;";

            $enrolments =$DB->get_records_sql($sql);
            return $enrolments;
        }
        catch(dml_exception $e) {
            $this->write_log("Failed to get enrolments from mdl_rru_enrolments table - DB error: [$e->debuginfo]", true);
            return false;
        }
    }


    /**
     * Return a list of new SIS unenrolments to remove from Moodle enrolments
     * IMPORTANT: depends on uvw_rru_enrolments
     *
     * @author Andrew Zoltay
     * date    2011-08-26
     * @global object $DB - Moodle database object
     * @return mixed_array of unenrolment info required to unenrol users from courses
     *         or false if db error occurs
    */
    private function get_unenrolments() {
        global $DB;

        try {

             $sql = "SELECT DISTINCT
                        ume.userenrolid,
                        re.source,
                        ume.courseid, ume.fullname,
                        ume.userid, ume.username, re.roleid, ume.enrolid
                        FROM mdl_uvw_rru_enrolments ume
                        INNER JOIN mdl_rru_enrolments re ON (re.mdlcourseidnumber = ume.idnumber)
                        LEFT OUTER JOIN (SELECT c1.id , re1.mdluseridnumber FROM mdl_rru_enrolments re1
                            INNER JOIN mdl_user u1 ON (re1.mdluseridnumber = u1.idnumber)
                            INNER JOIN mdl_course c1 ON (re1.mdlcourseidnumber = c1.idnumber)
                        ) a
                            ON (a.id = ume.courseid
                            AND a.mdluseridnumber = ume.student_pk)
                        WHERE ume.student_pk > 0
                        AND ume.roleid = re.roleid
                        AND ume.enroltype = 'rru'
                        AND a.id IS NULL
                        ORDER BY ume.courseid, ume.userid;";

            $unenrolments = $DB->get_records_sql($sql);
            return $unenrolments;
        }
        catch(dml_exception $e) {
            $this->write_log("Failed to get unenrolments from database - DB error: [$e->debuginfo]", true);
            return false;
        }
    }


    /**
     * Empty the mdl_rru_enrolments table in preparation for new load
     *
     * @author Andrew Zoltay
     * date    2012-08-26
     * @global object $DB Moodle database object
     * @return boolean success if all rows are deleted from table else failure
    */
    private function prep_enrolments_table() {
        global $DB;
        // Check for errors
        try {
            // Delete all the rows from the mdl_rru_enrolments table.
            $DB->delete_records('rru_enrolments');
            return true;
        } catch  (moodle_exception $e) {
            $this->write_log("Failed to clear mdl_rru_enrolments table prior to load" . $e->getMessage(), true);
            return false;
        }
    }


    /**
     * Get enrolments from sources and load base table in Moodle
     *
     * @author Andrew Zoltay
     * date    2012-08-26
     * @global object $DB - Moodle database object
     * @enrolments associative array - Enrolment data
     * @return int number of enrolments added to mdl_rru_enrolments table or false if error
    */
    private function load_enrolments_table($enrolments, $source) {
        global $DB;

        $addedcount = 0;

        if ($enrolments) {

            foreach ($enrolments as $enrolrow) {
                $newenrol = new stdClass();
                $newenrol->mdlcourseidnumber = $enrolrow['chrCourseCode'];
                $newenrol->mdluseridnumber = $enrolrow['intUserCode'];
                $newenrol->roleid = $enrolrow['intRoleID'];
                $newenrol->source = $source;

                // Add enrolment to base table.
                if (($DB->insert_record('rru_enrolments', $newenrol))) {
                    $addedcount++;
                } else {
                    $this->write_log("Could not insert mdluseridnumber:" . $enrolrow['intUserCode']
                            . " for mdlcourseidnumber: " . $enrolrow['chrCourseCode'] . " into mdl_rru_enrolments table", true);
                }
            }

            // Compare courses fetched with those added and store result in log.
            $this->write_log("$addedcount enrolments were added to mdl_rru_enrolments");
        }

        return $addedcount;
    }


    /**
     * Write a text to a log file
     * Note: path defined in RRU Enrolments settings
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @param string $message - message to write to the log file
     * @param boolean $iserror - flag that indicates if the message is an error
     * return true if success else return false
    */
    private function write_log($message, $iserror = false) {

        $destination = $this->get_config('logpath') . '/rru_enrol.log';
        if ($iserror) {
            $this->errors = true;
            $entry = date('Y-m-d H:i:s') . ' - ERROR - ' . $message . "\r\n";
        } else {
            $entry = date('Y-m-d H:i:s') . ' - ' . $message . "\r\n";
        }

        if (file_put_contents($destination, $entry, FILE_APPEND)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Send an email to address defined in plugin settings
     * that indicates an error has occurred
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     */
    public function report_errors() {

        // First check to see if any errors occurred.
        if ($this->errors) {
            // Get recipient user object.
            $recipient = get_config('enrol_rru', 'email');

            // Report recipient error.
            if (empty($recipient)) {
                $this->write_log("ERROR - notification email address not set. Please enter an email address on the RRU enrolments settings page.");
            }

            $subject = 'Moodle RRU enrolments failure - ' . date('Y-m-d');
            $message = 'The Moodle RRU enrolment process failed to update enrolments on ' .
                    ' on ' . gethostname() . ' at ' . date('Y-m-d H:i:s') . '. <br>' .
                    'Please check ' . $this->get_config('logpath') . '/rru_enrol.log on server';

            $headers   = array();
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-type: text/html; charset=UTF-8";
            $headers[] = "Subject: {$subject}";
            $headers[] = "X-Mailer: PHP/".phpversion();

            // Send the message.
            if (mail($recipient, $subject, $message, implode("\r\n", $headers))) {
                $this->write_log("Sent email to $recipient");
            } else {
                $this->write_log("failed to send email to $recipient.", true);
            }
        }
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/rru:config', $context);
    }
}


/**
 * RRU enrolment source abstract class implementation.
 * Allows for multiple enrolment sources
 *
 * 2012-09-12
 * @copyright   2012 Andrew Zoltay, Royal Roads University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class enrol_rru_source {
    private $haserrors;
    private $logpath;
    private $errors = array();

    function __construct() {
        $this->haserrors = false;
        $this->logpath = get_config('enrol_rru', 'logpath') . '/rru_enrol.log';
    }

    /**
     * Force implementation to return enrolment data
     * @return multidimensional array
     */
    abstract protected function get_enrolment_data();

    /**
    * Write a text to a log file
    * Note: log path defined in RRU Enrolments settings
    *
    * @author Andrew Zoltay
    * date    2012-08-26
    * @param string $message - message to write to the log file
    * @param boolean $iserror - flag that indicates if the message is an error
    * @return true if success else false
    */
    protected function write_log($message, $iserror = false) {

        if ($iserror) {
            $this->haserrors = true;
            $entry = date('Y-m-d H:i:s') . ' - ERROR - ' . $message . "\r\n";
            $errors[] = $message;
        } else {
            $entry = date('Y-m-d H:i:s') . ' - ' . $message . "\r\n";
        }

        if (file_put_contents($this->logpath, $entry, FILE_APPEND)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Identifies if an error has occurred in the class
     *
     * @author Andrew Zoltay
     * date    2012-09-12
     * @return boolean
     */
    public function has_error() {
        return $this->haserrors;
    }
}
