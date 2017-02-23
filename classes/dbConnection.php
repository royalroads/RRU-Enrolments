<?php namespace enrol_rru;


class dbConnection {

	protected static function get() {
		// Get connection info.
		$port = '1433'; // MS SQL Server port

		// AZRevisit - where to move config settings?
		$dbserver = get_config('enrol_rru', 'dbserver');
		$dbname = get_config('enrol_rru', 'db');
		$dbuser = get_config('enrol_rru', 'dbuser');
		$dbpwd = get_config('enrol_rru', 'dbpwd');

		

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