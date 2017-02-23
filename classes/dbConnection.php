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

/**
* @author        Jacek Pawel Polus
* @copyright     2017 Royal Roads University
* @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/


class dbConnection {

	/**
	* Get a database connection
	* @param return - database connection
	*/
	public static function get() {
		
		// Get connection info.
		$port = '1433'; // MS SQL Server port

		// AZRevisit - where to move config settings?
		$dbserver = get_config('enrol_rru', 'dbserver');
		$dbname = get_config('enrol_rru', 'db');
		$dbuser = get_config('enrol_rru', 'dbuser');
		$dbpwd = get_config('enrol_rru', 'dbpwd');

		// Confirm connection params are set.
		if (!$dbserver or !$dbname or !$dbuser) {
		    return false;
		}

		if (!$mssqllink = mssql_connect($dbserver . ':' . $port, $dbuser, $dbpwd)) {
		    $mserror = mssql_get_last_message();
		    return false;
		}

		if (mssql_select_db ($dbname, $mssqllink)) {
		    return $mssqllink;
		} else {
		    return false;
		}
	}
}