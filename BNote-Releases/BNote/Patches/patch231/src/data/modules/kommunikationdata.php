<?php
require_once $GLOBALS["DIR_DATA_MODULES"] . "kontaktedata.php";

/**
 * Data Access Class for communication data.
 * @author matti
 *
 */
class KommunikationData extends KontakteData {
	
	function getRehearsals() {
		return $this->adp()->getAllRehearsals();
	}
	
	function getRehearsal($id) {
		$query = "SELECT begin, name as location, street, city, zip ";
		$query .= "FROM rehearsal r, location l, address a ";
		$query .= "WHERE r.location = l.id AND l.address = a.id AND r.id = " . $id;
		return $this->database->getRow($query);
	}
	
	function getUsermail() {
		$cid = $this->database->getCell("user", "contact", "id = " . $_SESSION["user"]);
		return $this->getContactmail($cid);
	}
	
	function getContactmail($id) {
		return $this->database->getCell("contact", "email", "id = $id");
	}
	
	function getMailaddressesFromGroup($group) {
		// bug #5: exclude configured administrators from mail-traffic
		global $system_data;
		$adminContacts = $system_data->getSuperUserContactIDs();
		$exclAdminSqlWhere = "";
		if(count($adminContacts) > 0) {
			for($adminId = 0; $adminId < count($adminContacts); $adminId++) {
				if($adminId > 0) $exclAdminSqlWhere .= " AND ";
				$exclAdminSqlWhere .= "id <> " . $adminContacts[$adminId];
			}
		}
		
		$query = "SELECT email FROM contact WHERE ";
		if(count($adminContacts) > 0) $query .= "($exclAdminSqlWhere) AND (";
		$query .= "status = '";
		$stat = $group;
		
		// 100 = Admins, Members
		if($stat == 100) {
			$stat = KontakteData::$STATUS_ADMIN . "' OR status = '";
			$stat .= KontakteData::$STATUS_MEMBER;
		}
		// 101 = Admins, Members, Externals
		else if($stat == 101) {
			$stat = KontakteData::$STATUS_ADMIN . "' OR status = '";
			$stat .= KontakteData::$STATUS_MEMBER . "' OR status = '";
			$stat .= KontakteData::$STATUS_EXTERNAL;
		}
		
		$query .= $stat . "'";
		if(count($adminContacts) > 0) $query .= ")";

		return $this->database->getSelection($query);
	}
	
	function getSongsForRehearsal($rid) {
		$query = "SELECT s.title, rs.notes ";
		$query .= "FROM rehearsal_song rs, song s ";
		$query .= "WHERE rs.song = s.id AND rs.rehearsal = $rid";
		return $this->database->getSelection($query);
	}
}

?>