<?php

/**
 * Data Access Class for Start data.
 * @author matti
 *
 */
class StartData extends AbstractData {
	
	/**
	 * Build data provider.
	 */
	function __construct() {
		$this->fields = array(
			"id" => array("User ID", FieldType::INTEGER),
			"login" => array("Login", FieldType::CHAR),
			"password" => array("Password", FieldType::PASSWORD),
			"realname" => array("Name", FieldType::CHAR),
			"lastlogin" => array("Last Logged in", FieldType::DATETIME)
		);
		
		$this->references = array();
		$this->table = "user";
		
		$this->init();
	}
	
	/**
	 * Checks whether the current user participates in a rehearsal.
	 * @param int $rid ID of the rehearsal.
	 * @return 1 if the user participates, 0 if not, -1 if not chosen yet.
	 */
	function doesParticipateInRehearsal($rid) {
		$part = $this->database->getCell("rehearsal_user", "participate",
					"user = " . $_SESSION["user"] . " AND rehearsal = $rid");
		if($part == "0" || $part == "1" || $part == "2") {
			return $part;
		}
		else return -1;
	}
	
	/**
	 * Checks whether the current user participates in a concert.
	 * @param int $cid ID of the concert.
	 * @return 1 if the user participates, 0 if not, -1 if not chosen yet.
	 */
	function doesParticipateInConcert($cid) {
		$part = $this->database->getCell("concert_user", "participate",
					"user = " . $_SESSION["user"] . " AND concert = $cid");
		if($part == "1") return 1;
		else if($part == "0") return 0;
		else return -1;
	}
	
	/**
	 * Takes the $_GET and $_POST array and extracts the information.
	 */
	function saveParticipation() {
		// remove old decision
		if(isset($_GET["rid"]) || isset($_POST["rehearsal"])) {
			if(isset($_GET["rid"])) {
				$rid = $_GET["rid"];
			}
			else {
				$rid = $_POST["rehearsal"];
			}
			$query = "DELETE FROM rehearsal_user WHERE rehearsal = " . $rid;
			$query .= " AND user = " . $_SESSION["user"];
			$this->database->execute($query);
		}
			
		// save new decision
		if(isset($_GET["rid"]) && isset($_GET["status"]) && $_GET["status"] == "yes") {
			// save rehearsal participation
			$query = "INSERT INTO rehearsal_user (rehearsal, user, participate)";
			$query .= " VALUES (" . $_GET["rid"] . ", " . $_SESSION["user"] . ", 1)";
			$this->database->execute($query);
		}
		else if(isset($_POST["rehearsal"]) && isset($_GET["status"]) && $_GET["status"] == "maybe") {
			// save maybe participation in rehearsal with reason
			$this->regex->isText($_POST["explanation"]);
			$query = "INSERT INTO rehearsal_user (rehearsal, user, participate, reason)";
			$query .= " VALUES (" . $_POST["rehearsal"] . ", " . $_SESSION["user"] . ", 2, \"";
			$query .= $_POST["explanation"] . "\")";
			$this->database->execute($query);
		}
		else if(isset($_POST["rehearsal"])) {
			// save not participating in rehearsal with reason
			$this->regex->isText($_POST["explanation"]);
			$query = "INSERT INTO rehearsal_user (rehearsal, user, participate, reason)";
			$query .= " VALUES (" . $_POST["rehearsal"] . ", " . $_SESSION["user"] . ", 0, \"";
			$query .= $_POST["explanation"] . "\")";
			$this->database->execute($query);
		}
		else if(isset($_GET["cid"]) && isset($_GET["status"]) && $_GET["status"] == "yes") {
			// save concert participation
			$query = "INSERT INTO concert_user (concert, user, participate)";
			$query .= " VALUES (" . $_GET["cid"] . ", " . $_SESSION["user"] . ", 1)";
			$this->database->execute($query);
		}
		else if(isset($_POST["concert"])) {
			// save not participating in concert with reason 
			$this->regex->isText($_POST["explanation"]);
			$query = "INSERT INTO concert_user (concert, user, participate, reason)";
			$query .= " VALUES (" . $_POST["concert"] . ", " . $_SESSION["user"] . ", 0, \"";
			$query .= $_POST["explanation"] . "\")";
			$this->database->execute($query);
		}
	}
	
	function getSongsForRehearsal($rid) {
		$query = "SELECT s.id, s.title, rs.notes ";
		$query .= "FROM rehearsal_song rs, song s ";
		$query .= "WHERE rs.song = s.id AND rs.rehearsal = $rid";
		return $this->database->getSelection($query);
	}
	
	function getRehearsalParticipants($rid) {
		$query = "SELECT c.surname, c.name, i.name as instrument ";
		$query .= "FROM rehearsal_user r, user u, contact c, instrument i ";
		$query .= "WHERE r.participate = 1 AND ";
		$query .= "r.rehearsal = $rid AND r.user = u.id AND u.contact = c.id AND c.instrument = i.id";
		return $this->database->getSelection($query);
	}
	
	function getVotesForUser() {
		$query = "SELECT v.id, v.name, v.end, v.is_date, v.is_multi ";
		$query .= "FROM vote_group vg JOIN vote v ON vg.vote = v.id ";
		$query .= "WHERE vg.user = " . $_SESSION["user"] . " AND v.is_finished = 0 AND end > now() ";
		$query .= "ORDER BY v.end ASC";
		return $this->database->getSelection($query);
	}
	
	function getVote($vid) {
		$query = "SELECT * FROM vote WHERE id = $vid";
		return $this->database->getRow($query);
	}
	
	function getOptionsForVote($vid) {
		$query = "SELECT * FROM vote_option WHERE vote = $vid ORDER BY name, odate";
		return $this->database->getSelection($query);
	}
	
	function canUserVote($vid) {
		// security function
		$c = $this->database->getCell("vote_group", "count(vote)", "vote = $vid AND user = " . $_SESSION["user"]);
		return ($c == 1);
	}
	
	function saveVote($vid, $values) {
		$vote = $this->getVote($vid);
		$user = $_SESSION["user"];
		
		// remove eventual old votes first
		$options = $this->getOptionsForVote($vid);
		$query = "DELETE FROM vote_option_user WHERE (";
		for($i = 1; $i < count($options); $i++) {
			if($i > 1) $query .= " OR ";
			$query .= " vote_option = " . $options[$i]["id"];
		}
		$query .= ") AND user = $user";
		$this->database->execute($query);
		
		if($vote["is_multi"] == 1) {
			// mutiple options choosable
			$query = "INSERT INTO vote_option_user (vote_option, user) VALUES ";
			$c = 0;
			foreach($values as $optionId => $isOn) {
				if($c > 0) $query .= ",";
				$query .= "($optionId, $user)";
				$c++;
			}
 			$this->database->execute($query);
		}
		else {
			// single option only
			$query = "INSERT INTO vote_option_user (vote_option, user) VALUES ";
			$query .= "(" . $values["uservote"] . ", $user)";
			$this->database->execute($query);
		}
	}
	
	function hasUserVoted($vid) {
		$options = $this->getOptionsForVote($vid);
		if(count($options) == 1) {
			return false;
		}
		$where = "(";
		for($i = 1; $i < count($options); $i++) {
			if($i > 1) $where .= " OR ";
			$where .= " vote_option = " . $options[$i]["id"];
		}
		$where .= ") AND user = " . $_SESSION["user"];
		$c = $this->database->getCell("vote_option_user", "count(*)", $where);
		return ($c > 0);
	}
}