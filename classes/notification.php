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


class notification {

	private static $from = "moodleadmin@royalroads.ca";
	private static $subject = "RRU Enrolment Notification";
	private static $body = "Error - notification body not set! Please contact a Programmer!";

	/**
	* Get list of address from enrol settings page
	* Extract a list of valid email address
	* @return array $recipients - valid email addresses
	*/
	private static function getRecipients() {
		
		$recipients = array();

		// // We need the lib that allows us a quick api to grab config
		// require_once(dirname(__FILE__) . '/../../../lib/moodlelib.php');
		// Grab the sync_notification setting, contain a list of email addresses
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
	* Send a notification to set addresses
	* Send email to set addresses
	* @param $subject - string to be used as subject line in email
	* @param $body - string to be used as the main content
	* @return null
	*/
	public static function send($subject,$body) {


		/**
		* First, let's set some headers
		*/
		$headers = "From:" . self::$from . "\r\n";		
		$headers .= "MIME-VERSION: 1.0\r\n";
		$headers .= "Content-type: text/html\r\n";


		// Update our email subject and body if set
		$subject !== null ? self::$subject = $subject : '';
		$body !== null ? self::$body = $body : '';

		/**
		* OK we have a subject, a body, and a "from"
		* All we need is a list to "to's"...
		*/
		$recipients = self::getRecipients();

		// And now we notify!
		foreach($recipients as $to) {
			mail($to,self::$subject,self::$body,$headers);
		}
	
	}

}


