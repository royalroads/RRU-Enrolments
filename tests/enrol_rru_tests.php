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
 * Unit test for RRU Enrolment method
 *
 * @package    enrol
 * @subpackage rru
 * @category   phpunit
 * @author     Gerald Albion
 * @copyright  2015 Royal Roads University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/enrol/rru/lib.php');

// Need to test:
// lib.php - class enrol_rru_plugin and methods
// sources/approvers_enrol_rru_source.php - class approvers_enrol_rru_source and methods.
// sources/instructors_mm_enrol_rru_source.php - class instructors_mm_enrol_rru_source and methods.
// sources/students_enrol_rru_source.php - class students_enrol_rru_source and methods.


class enrol_rru_test extends advanced_testcase {

    protected function setUp() {

        // Reset the database state after the test is done.
        $this->resetAfterTest(true);

        // Generate a dummy category for the dummy course.
        $this->dummycategory = $this->getDataGenerator()->create_category(array('name' => 'Dummy category',
                        'parent' => null));

        // Generate two dummy courses.
        $this->dummycourse = $this->getDataGenerator()->create_course(array('name' => 'Dummy Course',
                        'idnumber' => 'POTATO101__Y7576F-01',
                        'category' => $this->dummycategory->id));
        $this->dummycourse2 = $this->getDataGenerator()->create_course(array('name' => 'Dummy Course',
                        'idnumber' => 'POTATO476__Y7576F-01',
                        'category' => $this->dummycategory->id,
                        'fullname' => 'Mastering Moodle')); // test_populate_source() depends on this course fullname existing

        // Create three dummy users.
        $this->user  = $this->getDataGenerator()->create_user(array('email' => 'test@example.com',
                        'username' => 'rruenroltestuser'));
        $this->user2 = $this->getDataGenerator()->create_user(array('email' => 'test@example.com',
                        'username' => 'rruenroltestuser2'));
        $this->user3 = $this->getDataGenerator()->create_user(array('email' => 'test@example.com',
                        'username' => 'rruenroltestuser3'));

        $this->test_enrol = new enrol_rru_plugin;
    }

    /**
     * Test enrol_rru_plugin::populate_source()
     *
     * At this time, we cannot test this because we do not have access to
     * the MSSQL connection settings in phpUnit.  One of the enrolment source classes
     * called by populate_course() has this connection as a dependency, and we cannot
     * skip this source without modifying the code under test.
     */
    public function test_populate_source() {

    }


    /**
     * Test enrol_rru_plugin::sync_enrolments()
     *
     * At this time, we cannot test this because we do not have access to
     * the view `uvw_rru_enrolments` in phpUnit.  sync_enrolments() calls
     * get_enrolments() which has uwv_rru_enrolments as a dependency.
     */
    public function test_sync_enrolments() {

    }

    /**
     * Test enrol_rru_plugin::create_enrol_instances()
     *
     * To perform this test, we start with a count of rows in {enrol}.
     * We then add some rows to {rru_enrolments} and invoke the method.
     * The new {enrol} rows (distinct by course) should be represented as
     * new rows in {rru_enrolments}.
     *
     * global object $DB the Moodle database object
     */
    public function test_create_enrol_instances() {
        global $DB;

        // Get count before test
        $countbefore = $DB->count_records('enrol');

        // Add three rows to the rru_enrolments table - two in one course
        // and one in the other.
        $data1 = array('mdlcourseidnumber'=>$this->dummycourse->idnumber,
                        'mdluseridnumber'=>$this->user->id,
                        'roleid'=>10,
                        'source'=>"students_enrol_rru_source");
        $data2 = array('mdlcourseidnumber'=>$this->dummycourse->idnumber,
                        'mdluseridnumber'=>$this->user2->id,
                        'roleid'=>10,
                        'source'=>"students_enrol_rru_source");
        $data3 = array('mdlcourseidnumber'=>$this->dummycourse2->idnumber,
                        'mdluseridnumber'=>$this->user3->id,
                        'roleid'=>10,
                        'source'=>"students_enrol_rru_source");
        $DB->insert_record('rru_enrolments', $data1);
        $DB->insert_record('rru_enrolments', $data2);
        $DB->insert_record('rru_enrolments', $data3);

        // Set up reflector.
        $reflector = new ReflectionClass('enrol_rru_plugin');
        $instance = $reflector->newInstance();

        // Set up and invoke method under test.
        $method = $reflector->getMethod('create_enrol_instances');
        $method->setAccessible(true);
        $method->invoke($instance);

        $countafter = $DB->count_records('enrol');
        $newrows = $countafter - $countbefore;

        // Assert that there are two more rows in the enrol table.
        $this->assertEquals($newrows, 2);
    }

    /**
     * Test enrol_rru_plugin::get_enrolments()
     *
     * At this time, we cannot test this because we do not have access to
     * the view `uvw_rru_enrolments` in phpUnit.
     */
    public function test_get_enrolments() {

    }

    /**
     * Test enrol_rru_plugin::get_unenrolments()
     *
     * At this time, we cannot test this because we do not have access to
     * the view `uvw_rru_enrolments` in phpUnit.
     */
    public function test_get_unenrolments() {

    }

    /**
     * Test enrol_rru_plugin::prep_enrolments_table()
     * prep_enrolments_table() just clears out the enrolments table, so all we
     * can test for is that the table is empty.
     * global object $DB the Moodle database object
     */
    public function test_prep_enrolments_table() {
        global $DB;

        // In the test environment, the rru_enrolments table will start out empty,
        // so calling the method alone doesn't test much.  Insert a dummy record.
        $record = new stdClass();
        $record->mdlcourseidnumber = 'TEST601__Y6768-01';
        $record->mdluseridnumber = '1600';
        $record->roleid = 5;
        $record->source = 'students_enrol_rru_source';
        $DB->insert_record('rru_enrolments', $record);

        // prep_enrolments_table() is private in enrol_rru_plugin
        // so we will access it via reflection.

        // Set up reflector.
        $reflector = new ReflectionClass('enrol_rru_plugin');
        $instance = $reflector->newInstance();

        // Set up and invoke method under test.
        $method = $reflector->getMethod('prep_enrolments_table');
        $method->setAccessible(true);
        $method->invoke($instance);

        $count = $DB->count_records('rru_enrolments');
        $this->assertEquals($count, 0);
    }

    /**
     * Test enrol_rru_plugin::load_enrolments_table()
     */
    public function test_load_enrolments_table() {
        global $DB;

        // Set up three dummy enrolment records as they would come in from Agresso.
        $data1 = array('chrCourseCode' => 'COURSE1',
                       'intUserCode' => 1,
                       'intRoleID' => 5);
        $data2 = array('chrCourseCode' => 'COURSE1',
                       'intUserCode' => 2,
                       'intRoleID' => 5);
        $data3 = array('chrCourseCode' => 'COURSE1',
                       'intUserCode' => 3,
                       'intRoleID' => 5);

        // load_enrolments_table() is private in enrol_rru_plugin
        // so we will access it via reflection.

        // Set up reflector.
        $reflector = new ReflectionClass('enrol_rru_plugin');
        $instance = $reflector->newInstance();

        // Set up and invoke prep method to guarantee the table starts empty.
        $prepmethod = $reflector->getMethod('prep_enrolments_table');
        $prepmethod->setAccessible(true);

        // Set up and invoke method under test.
        $method = $reflector->getMethod('load_enrolments_table');
        $arguments = array(
        	array($data1, $data2, $data3), // An array of records.  We will add 3.
            'SOURCE1',
        );

        $method->setAccessible(true);
        $added = $method->invokeArgs(new enrol_rru_plugin, $arguments);

        // Assert that the added records count and total records agree,
        // since we started from zero.
        $count = $DB->count_records('rru_enrolments');
        $this->assertEquals($added, $count);

        // Assert that three records were added.
        $this->assertEquals(3, $count);

    }



}
