<?php

/**
 * Data Access Class for concert data.
 * @author matti
 *
 */
class KonzerteData extends AbstractData {
	
	/**
	 * Build data provider.
	 */
	function __construct() {
		$this->fields = array(
			"id" => array("Konzert ID", FieldType::INTEGER),
			"begin" => array("Beginn", FieldType::DATETIME),
			"end" => array("Ende", FieldType::DATETIME),
			"location" => array("Ort", FieldType::REFERENCE),
			"program" => array("Programm", FieldType::REFERENCE),
			"contact" => array("Kontakt", FieldType::REFERENCE),
			"notes" => array("Anmerkungen", FieldType::TEXT)
		);
		
		$this->references = array(
			"location" => "location",
			"program" => "program",
			"contact" => "contact"
		);
		
		$this->table = "concert";
		
		$this->init();
	}
	
	function getFutureConcerts() {
		return $this->adp()->getFutureConcerts();
	}
	
	function getPastConcerts() {
		/* 
		 * For complexity reasons is this data filtering
		 * done in PHP instead of SQL. Since there are only
		 * very few concerts usually, this shouldn't be a
		 * problem!
		 */
		$result = array();
		
		// add header
		array_push($result, array(
			"id", "begin", "end", "notes", 
			"location_name", "location_city",
			"contact_name", "program_name"
		));
		
		// get all future concerts
		$query = "SELECT * FROM concert WHERE end < NOW() ORDER BY begin ASC";
		$concerts = $this->database->getSelection($query);
		
		// iterate over concerts and replace foreign keys with data
		for($i = 1; $i < count($concerts); $i++) {
			// resolve location -> mandatory!
			$loc_id = $concerts[$i]["location"];
			if($loc_id > 0) {
				$location = $this->getLocation($loc_id);
			}
			else {
				$location = array(
					"name" => "-",
					"address" => "0"
				);
			}
			
			// resolve address -> address id present, because location is mandatory
			$address = $this->getAddress($location["address"]);
			if($address == null || $address == "") {
				$address = array(
					"city" => "-"
				);
			}
			
			// resolve contact
			if($concerts[$i]["contact"] != "") {
				$contact = $this->getContact($concerts[$i]["contact"]);
			}
			else {
				$contact = array(
					"name" => "", "phone" => "", "email" => "", "web" => ""
				);
			}
			
			// resolve program
			if($concerts[$i]["program"] != "") {
				$program = $this->getProgram($concerts[$i]["program"]);
			}
			else {
				$program = array(
					"name" => "", "notes" => ""
				);
			}
			
			// build result for by row
			array_push($result, array(
				"id" => $concerts[$i]["id"],
				"begin" => $concerts[$i]["begin"],
				"end" => $concerts[$i]["end"], 
				"notes" => $concerts[$i]["notes"],
				"location_name" => $location["name"],
				"location_city" => $address["city"],
				"contact_name" => $contact["name"],
				"program_name" => $program["name"],
			));
		}
		return $result;
	}
	
	function getLocation($id) {
		$q1 = "SELECT name, notes, address FROM location ";
		$q1 .= "WHERE id = $id";
		return $this->database->getRow($q1);
	}
	
	function getAddress($id) {
		if($id < 1) return null;
		$q2 = "SELECT street, city, zip FROM address ";
		$q2 .= "WHERE id = $id";
		return $this->database->getRow($q2);
	}
	
	function getContact($id) {
		$q3 = "SELECT CONCAT_WS(' ', name, surname) as name, phone, email, web ";
		$q3 .= "FROM contact WHERE id = " . $id;
		return $this->database->getRow($q3);
	}
	
	function getProgram($id) {
		$q4 = "SELECT name, notes FROM program ";
		$q4 .= "WHERE id = " . $id;
		return $this->database->getRow($q4);
	}
	
	function getLocations() {
		return $this->adp()->getLocations();
	}
	
	function getContacts() {
		return $this->adp()->getContacts();
	}
	
	function getTemplates() {
		return $this->adp()->getTemplatePrograms();
	}
	
	// NOT TRANSACTION SECURE!
	function saveConcert() {
		$values = $_POST;
		$this->validate($values);
		
		// check for location
		if(!isset($values["location"]) || $values["location"] == "") {
			// Create Location
			// 1) create address
			$addy = array(
				"street" => $values["street"],
				"city" => $values["city"],
				"zip" => $values["zip"]
			);
			$aid = $this->adp()->manageAddress(-1, $addy);
			
			// 2) create location
			$notes = "Konzert am " . substr($values["begin"], 0, strlen($values["begin"])-5);
			$query = "INSERT INTO location (name, notes, address) VALUES (";
			$query .= "\"" . $values["location_name"] . "\", \"" . $notes . "\", $aid";
			$query .= ")";
			$lid = $this->database->execute($query);
			
			// 3) save location id in values
			unset($values["location_name"]);
			unset($values["street"]);
			unset($values["city"]);
			unset($values["zip"]);
			$values["location"] = $lid;
		}
		
		// check for contact
		if(!isset($values["contact"]) || $values["contact"] == "") {
			// create contact, but don't set a few options
			$query = "INSERT INTO contact (surname, name, phone, email, web, status)";
			$query .= " VALUES (";
			$query .= '"' . $values["contact_surname"] . '", ';
			$query .= '"' . $values["contact_name"] . '", ';
			$query .= '"' . $values["contact_phone"] . '", ';
			$query .= '"' . $values["contact_email"] . '", ';
			$query .= '"' . $values["contact_web"] . '", ';
			$query .= '"OTHER"'; //automatically group "OTHER"
			$query .= ")";
			$cid = $this->database->execute($query);
			
			// save a contact in values
			unset($values["contact_name"]);
			unset($values["contact_surname"]);
			unset($values["contact_phone"]);
			unset($values["contact_email"]);
			unset($values["contact_web"]);
			$values["contact"] = $cid; 
		}
		
		// check for program
		if(isset($values["program"]) && $values["program"] < 1) {
			// remove if none was chosen
			unset($values["program"]);
		}
		
		// create concert
		parent::create($values);
	}
	
	function getParticipants($cid) {
		$query = 'SELECT CONCAT_WS(" ", c.name, c.surname) as name, ';
		$query .= ' CASE cu.participate WHEN 1 THEN "ja" WHEN 2 THEN "vielleicht" ELSE "nein" END as participate, cu.reason';
		$query .= ' FROM concert_user cu, user u, contact c';
		$query .= ' WHERE cu.concert = ' . $cid . ' AND cu.user = u.id AND u.contact = c.id';
		$query .= ' ORDER BY participate, name';
		return $this->database->getSelection($query);
	}
}

?>