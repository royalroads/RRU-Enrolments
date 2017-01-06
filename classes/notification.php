<?php namespace rru_enrol;

class notification {

	private static $to = "";
	private static $subject = "Mismatch between SIS and Moodle Shell";
	private static $from = "moodleadmin@royalroads.ca";

	public static function send($orphans) {

		// Create message headers
		$headers = "From:" . self::$from . "\r\n";
		
		$headers .= "MIME-VERSION: 1.0\r\n";
		$headers .= "Content-type: text/html\r\n";

		// Create body
		$body = "<h1>RRU Enroll Error!</h1>The courses listed below exist in your SIS, but do NOT exist in Moodle.<br>
		As a direct result, some enrollments may have been missed!<p></p>";
		foreach($orphans AS $orphan) {
			$body .= $orphan . "<br>";
		}

		// Send notification
		mail(self::$to,self::$subject,$body,$headers);

	}

}
