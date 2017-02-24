<?php namespace enrol_rru;

/**
* This file is part of Moodle - http://moodle.org/
*
* Moodle is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Moodle is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Moodle.  If not, see http://www.gnu.org/licenses/
*/

use enrol_rru\datamart as dbConnection;
use enrol_rru\notification;

/**
* @author        Jacek Pawel Polus
* @copyright     2017 Royal Roads University
* @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

class orphans {


	/**
	* Get a list of possible orphan courses
	* (Courses that have enrollments in SIS but do not have a shell in Moodle)
	* Validate against a list verified RRU courses, notify if matches found
	* @param array $orphans - array of orphaned courses as determined by students_enrol_rru_source.php
	* @param return - null (We will send email instead)
	*/
	public static function notify($orphans) {

		// Set Body
		$body = "<h1>RRU Enrolment Sync Anomolies</h1>";
		$body .= "The following courses exist in the SIS, but do not have a corresponding Moodle Shell.";
		$body .= "Consequently, enrollments in these courses have not been completed!";
		$body .= "<p><hr></p>";

		$courses = self::rru_courses();
		$send_email = false;


		/**
		* Loop through each of the provided orphaned courses
		* If they can be matched to a Moodle shell,
		* Then they are orphans - Courses that should but do not have a Moodle shell
		*/
		foreach($orphans AS $orphan) {

			if(in_array($orphan,$courses)) {
				$body .= $orphan . "<br>";
				$send_email = true;
			}

		}

		if($send_email == true) {
			notification::send("Missing course shells in Moodle",$body);
		}


	}

	/**
	* Get a list of RRU courses that should have
	* shells in Moodle.  We will compare orphans against this
	* @param return - array of course id's (shortname)
	*/
	private static function rru_courses() {

		$conn = dbConnection::get();
		// The stored procudure name in the dataMart
		$stored_procedure = "Learn.usp_GetCurrentCourses";
		$result = mssql_query("EXEC $stored_procedure",$conn);


		if(!$result) {
			// Todo - notify of failed data retrieval
		}


		$rru_courses = array();

		while($row = mssql_fetch_assoc($result)) {
			$rru_courses[] = $row['strIDNumber'];
		}

		return $rru_courses;

	} 

}

