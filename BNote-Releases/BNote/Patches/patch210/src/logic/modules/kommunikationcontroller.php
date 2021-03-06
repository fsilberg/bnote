<?php

/**
 * Controller for emails.
 * @author matti
 *
 */
class KommunikationController extends DefaultController {
	
	function start() {
		if(isset($_GET['mode'])) {
			if(isset($_GET['sub']) && $_GET['sub'] = "send") {
				$this->prepareMail();
				$this->sendMail();
			}
			$this->getView()->$_GET['mode']();
		}
		else {
			$this->getView()->start();
		}
	}
	
	/**
	 * Prepares the data for the mail.
	 */
	private function prepareMail() {
		// adjust subject & body
		if(isset($_POST["rehearsal"])) {
			// adjust subject
			$reh = $this->getData()->getRehearsal($_POST["rehearsal"]);
			$text = "Probe am " . Data::getWeekdayFromDbDate($reh["begin"]);
			$text .= ", " . Data::convertDateFromDb($reh["begin"]) . " Uhr";
			$_POST["subject"] = $text;
			
			// adjust body: append songs to practise
			$songs = $this->getData()->getSongsForRehearsal($_POST["rehearsal"]);
			if(count($songs) > 1) {
				$ext = "<p>Bitte probt folgende St&uuml;cke:</p><ul>\n";
				for($i = 1; $i < count($songs); $i++) {
					$ext .= "<li>" . $songs[$i]["title"] . " (" . $songs[$i]["notes"] . ")</li>\n";
				}
				$ext .= "</ul>\n";
				$_POST["message"] .= "\n$ext";
			}
		}
		else if($_POST["subject"] == "") {
			global $system_data;
			$_POST["subject"] = $system_data->getCompany(); // band name
		}
	}
	
	/**
	 * Please make sure that the $_POST array has
	 * either the group or the contact key set and
	 * it must have a subject and message attribute.
	 */
	private function sendMail() {
		$addresses = array();
		$subject = $_POST["subject"];
		$body = $_POST["message"];
		
		// determine whether to send it to a group or to a single person
		if(isset($_POST["group"]) && $_POST["group"] != "") {
			// get all mail addresses
			$addies = $this->getData()->getMailaddressesFromGroup($_POST["group"]);
			for($i = 1; $i < count($addies); $i++) {
				array_push($addresses, $addies[$i]["email"]);
			}
		}
		else {
			// get contact mail address
			array_push($addresses, $this->getData()->getContactmail($_POST["contact"]));
		}		
		
		// Receipient Setup
		global $system_data;
		$ci = $system_data->getCompanyInformation();		
		$headers  = 'From: ' . $this->getData()->getUsermail() . "\r\n";
		$receipient = $ci["Mail"];
		
		// To send HTML mail, the Content-type header must be set
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		
		// place sender addresses into the bcc field
		$bcc_addresses = "";
		foreach($addresses as $i => $to) {
			if($i > 0) $bcc_addresses .= ",";
			$bcc_addresses .= $to;
		}
		$headers .= 'Bcc: ' . $bcc_addresses . "\r\n";
		
		/*
		 * MAIL FUNCTION
		 * -------------
		 * Some hosting providers require specific mail() settings
		 * therefore this comment should show where the function is!
		 */
		if(!$GLOBALS["system_data"]->inDemoMode()) {
			if(!mail($receipient, $subject, $body, $headers)) {
				$this->getView()->reportMailError($bcc_addresses);
			}
		}
	}
}