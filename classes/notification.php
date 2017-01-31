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



/**
* Primary notification object for RRU Enrolment plugin
*/
class notification {

	private static $from = "moodleadmin@royalroads.ca";
	private static $subject = "RRU Enrolment Notification";


	/**
	* Get list of address from enrol settings page
	* Extract a list of valid email address
	* @return $recipients - array of valid email addresses
	*/
	private static function getRecipients() {
		
		$recipients = array();

		// We need the lib that allows us a quick api to grab config
		require_once(dirname(__FILE__) . '/../../../lib/moodlelib.php');
		// Grab the sync_notification setting, contain a list of emails
		$settings = get_config('enrol_rru');
		$raw_list =$settings->sync_notification;

		// Sanitize the email list, confirm valid address
		$emails = explode(';',$raw_list);
		foreach($emails AS $address) {
			filter_var($address,FILTER_VALIDATE_EMAIL) ? $recipients[] = $address : '';
		}

		return $recipients;
	} 




	/**
	* Accept a list of orphaned courses
	* Send email to set addresses
	* @param $orphans - array containing all relevant courses
	* @param $subject - string to be used as subject line in email
	* @return null
	*/
	public static function send($orphans,$subject = null) {

		/**
		* First, let's create the email itself
		* Let's start off with some basic headers
		*/
		$headers = "From:" . self::$from . "\r\n";		
		$headers .= "MIME-VERSION: 1.0\r\n";
		$headers .= "Content-type: text/html\r\n";

		// Set some content ...
		$content = array(
			'heading' => 	"<h1>RRU Enrolment Error</h1>",
			'body' => 		"The following courses exist in your SIS, but not in Moodle.<br>
							Consequently, no enrollments in these courses occurred!<p></p>"
		);

		// Add content to email
		$body = $content['heading'] . $content['body'];
		foreach($orphans AS $orphan) {
			$body .= $orphan . "<br>";
		}

		// Update our email subject line if set
		$subject !== null ? self::$subject = $subject : '';

		/**
		* OK we have an email, a subject and from
		* All we need is a list to "to's"...
		*/
		$recipients = self::getRecipients();

		// And now we send to each...
		foreach($recipients AS $to) {
			mail($to,self::$subject,$body,$headers);
		}
	
	}

}


