<?php

/**
 * View for contact module.
 * @author matti
 *
 */
class KontakteView extends CrudRefView {
	
	/**
	 * Create the contact view.
	 */
	function __construct($ctrl) {
		$this->setController($ctrl);
		$this->setEntityName("Kontakt");
		$this->setJoinedAttributes(array(
			"address" => array("street", "city", "zip"),
			"instrument" => array("name")
		));
	}
	
	function start() {
		Writing::h1("Kontakte");
		
		// Options		
		$add = new Link($this->modePrefix() . "addForm", "Kontakt hinzuf&uuml;gen");
		$add->addIcon("add");
		$add->write();
		$this->buttonSpace();
		
		$print = new Link($this->modePrefix() . "selectPrintGroups", "Mitspielerliste drucken");
		$print->addIcon("printer");
		$print->write();
		$this->buttonSpace();
		
		$groups = new Link($this->modePrefix() . "groups&func=start", "Gruppen verwalten");
		$groups->addIcon("group");
		$groups->write();
		$this->buttonSpace();
		
		$vc = new Link($GLOBALS["DIR_EXPORT"] . "kontakte.vcd", "Kontakte Export");
		$vc->addIcon("arrow_down");
		$vc->setTarget("_blank");
		$vc->write();
		$this->verticalSpace();
		
		// show band members
		$this->showContacts();
	}
	
	function showContacts() {		
		// show correct group
		if(isset($_GET["group"]) && $_GET["group"] == "all") {
			$data = $this->getData()->getAllContacts();
		}
		else if(isset($_GET["group"])) {
			$data = $this->getData()->getGroupContacts($_GET["group"]);
		}
		else {
			// default: MEMBERS
			$data = $this->getData()->getMembers();
		}
		
		// write
		$this->showContactTable($data);
	}
	
	private function showContactTable($data) {
		$groups = $this->getData()->getGroups();
		
		// show groups as tabs
		echo "<div class=\"contact_view\">\n";
		echo " <div class=\"contact_view_tabs\">";
		foreach($groups as $cmd => $info) {
			if($cmd == 0) {
				// instead of skipping the first header-row,
				// insert a tab with all contacts
				$cmd = "all";
				$info = array("name" => "Alle Kontakte", "id" => "all");
			}
			$label = $info["name"];
			$groupId = $info["id"];
			
			$active = "";
			if($_GET["group"] == $cmd) $active = "_active";
			else if(!isset($_GET["group"]) && $groupId == 2) $active = "_active";
			
			echo "<a href=\"" . $this->modePrefix() . "start&group=$groupId\"><span class=\"contact_view_tab$active\">$label</span></a>";
		}
		
		// show data
		echo " <table class=\"contact_view\">\n";
		foreach($data as $i => $row) {
			echo "  <tr>\n";
			
			if($i == 0) {
				// header
				echo "   <td class=\"DataTable_Header\">Name, Vorname</td>";
				echo "   <td class=\"DataTable_Header\">Instrument</td>";
				echo "   <td class=\"DataTable_Header\">Adresse</td>";
				echo "   <td class=\"DataTable_Header\">Telefone</td>";
				echo "   <td class=\"DataTable_Header\">Online</td>";
				//echo "   <td class=\"DataTable_Header\">Notizen</td>";
			}
			else {
				// body
				echo "   <td class=\"DataTable\"><a href=\"" . $this->modePrefix() . "view&id=" . $row["id"] . "\">" . $row["surname"] . ", " . $row["name"] . "</a></td>";
				echo "   <td class=\"DataTable\">" . $row["instrumentname"] . "</td>";
				echo "   <td class=\"DataTable\" style=\"width: 150px;\">" . $row["street"] . "<br/>" . $row["zip"] . " " . $row["city"] . "</td>";
				
				// phones
				$phones = "";
				if($row["phone"] != "") {
					$phones .= "Tel: " . $row["phone"];
				}
				if($row["mobile"] != "") {
					if($phones != "") $phones .= "<br/>";
					$phones .= "Mobil: " . $row["mobile"]; 
				}
				if($row["business"] != "") {
					if($phones != "") $phones .= "<br/>";
					$phones .= "Arbeit: " . $row["business"];
				}
				echo "   <td class=\"DataTable\" style=\"width: 150px;\">$phones</td>";
				
				// online
				echo "   <td class=\"DataTable\"><a href=\"mailto:" . $row["email"] . "\">" . $row["email"] . "</a>";
				if($row["web"] != "") {
					echo "<br/><a href=\"http://" . $row["web"] . "\">" . $row["web"] . "</a>";
				} 
				echo "</td>";
				
				// notizen
				//echo "   <td class=\"DataTable\">" . $row["notes"] . "</td>";
			}
			
			echo "  </tr>";
		}
		// show "no entries" row when this is the case
		if(count($data) == 1) {
			echo "<tr><td colspan=\"5\">Keine Kontaktdaten vorhanden.</td></tr>\n";
		}
		
		echo "</table>\n";
		echo " </div>";
		echo "</div>";
	}
	
	function addForm() {
		$form = new Form("Kontakt hinzuf&uuml;gen", $this->modePrefix() . "add");
		
		$form->autoAddElementsNew($this->getData()->getFields());
		$form->removeElement("id");
		$form->setForeign("instrument", "instrument", "id", "name", -1);
		$form->addForeignOption("instrument", "[keine Angabe]", 0);
		
		$form->removeElement("address");
		$form->addElement("Stra&szlig;e", new Field("street", "", FieldType::CHAR));
		$form->addElement("Stadt", new Field("city", "", FieldType::CHAR));
		$form->addElement("PLZ", new Field("zip", "", FieldType::CHAR));
		
		$form->removeElement("status");
		
		// group selection
		$groups = $this->getData()->getGroups();
		$gs = new GroupSelector($groups, array(), "group");
		$form->addElement("Gruppen", $gs);
		
		$form->write();
		
		$this->verticalSpace();
		$this->backToStart();
	}
	
	function add() {
		$this->groupSelectionCheck();
		
		// do as usual
		parent::add();
	}
	
	function viewDetailTable() {
		// user details
		$entity = $this->getData()->getContact($_GET["id"]);
		$details = new Dataview();
		$details->autoAddElements($entity);
		$details->autoRename($this->getData()->getFields());
		$details->removeElement("Status");
		$details->removeElement("Instrument");
		$details->renameElement("instrumentname", "Instrument");
		$details->removeElement("Adresse");
		$details->renameElement("street", "Stra&szlig;e");
		$details->renameElement("zip", "PLZ");
		$details->renameElement("city", "Stadt");
		
		// the contact is a member of these groups
		$groups = $this->getData()->getContactGroups($_GET["id"]);
		$details->addElement("Gruppen", $groups);
		
		$details->write();
	}
	
	function additionalViewButtons() {
		// only show when it doesn't already exist
		if(!$this->getData()->hasContactUserAccount($_GET["id"])) {
			// show button
			$btn = new Link($this->modePrefix() . "createUserAccount&id=" . $_GET["id"],
						"Benutzerkonto erstellen");
			$btn->addIcon("user");
			$btn->write();
			$this->buttonSpace();
		}
	}
	
	function editEntityForm() {
		$contact = $this->getData()->findByIdNoRef($_GET["id"]);
		$form = new Form("Kontakt bearbeiten", $this->modePrefix() . "edit_process&id=" . $_GET["id"]);
		$form->autoAddElements($this->getData()->getFields(), $this->getData()->getTable(), $_GET["id"]);
		$form->removeElement("id");
		$form->setForeign("instrument", "instrument", "id", "name", $contact["instrument"]);
		
		$address = $this->getData()->getAddress($contact["address"]);
		$form->removeElement("address");
		$form->addElement("Stra&szlig;e", new Field("street", $address["street"], FieldType::CHAR));
		$form->addElement("Stadt", new Field("city", $address["city"], FieldType::CHAR));
		$form->addElement("PLZ", new Field("zip", $address["zip"], FieldType::CHAR));
		
		$form->removeElement("status");
		// group selection
		$groups = $this->getData()->getGroups();
		$userGroups = $this->getData()->getContactGroupsArray($_GET["id"]);
		$gs = new GroupSelector($groups, $userGroups, "group");
		$form->addElement("Gruppen", $gs);
		
		$form->write();
	}
	
	function edit_process() {
		$this->groupSelectionCheck();
		
		// do as usual
		parent::edit_process();
	}
	
	private function groupSelectionCheck() {
		// make sure at least one group is selected
		$groups = $this->getData()->getGroups();
		$isAGroupSelected = false;
		
		for($i = 1; $i < count($groups); $i++) {
			$fieldId = "group_" . $groups[$i]["id"];
			if(isset($_POST[$fieldId])) {
				$isAGroupSelected = true;
				break;
			}
		}
		
		if(!$isAGroupSelected) {
			new Error("Bitte weise dem Kontakt mindestens eine Gruppe zu.");
		}
	}
	
	function selectPrintGroups() {
		Writing::h2("Mitspielerliste drucken");
		Writing::p("Alle Mitspieler sind in Gruppen sortiert. Bitte wähle die Gruppen deren Mitglieder du drucken möchtest.");
		
		$form = new Form("Gruppenauswahl", $this->modePrefix() . "printMembers");
		
		// group selection
		$groups = $this->getData()->getGroups();
		$gs = new GroupSelector($groups, array(), "group");
		$form->addElement("Gruppen", $gs);
		
		$form->changeSubmitButton("Druckvorschau anzeigen");
		$form->write();
	}
	
	function printMembers() {
		Writing::h2("Mitspielerliste");
		
		// convert $_POST groups into a flat groups array
		$allGroups = $this->getData()->getGroups();
		$groups = array();
		for($i = 1; $i < count($allGroups); $i++) {
			$gid = $allGroups[$i]["id"];
			if(isset($_POST["group_" . $gid])) {
				array_push($groups, $gid);
			}
		}
		if(count($groups) == 0) {
			new Message("Fehler bei Gruppenauswahl", "Wähle mindestens eine Gruppe zum drucken aus.");
			$this->backToStart();
			return;
		}
		
		// determine filename
		$filename = $GLOBALS["DATA_PATHS"]["members"];
		$filename .= "Mitspielerliste-" . date('Y-m-d') . ".pdf";
		
		// create report
		require_once $GLOBALS["DIR_PRINT"] . "memberlist.php";
		new MembersPDF($filename, $this->getData(), $groups);
		
		// show report
		echo "<embed src=\"src/data/filehandler.php?mode=module&file=$filename\" width=\"90%\" height=\"700px\" />\n";
		echo "<br /><br />\n";
		
		// back button
		$this->backToStart();
		$this->verticalSpace();
	}
	
	function userCreatedAndMailed($username, $email) {
		$m = "Die Zugangsdaten wurden an $email geschickt.";
		new Message("Benutzer $username erstellt", $m);
		$this->backToViewButton($_GET["id"]);
	}
	
	function userCredentials($username, $password) {
		$m = "<br />Die Zugangsdaten konnten dem Benutzer nicht zugestellt werden ";
		$m .= "da keine E-Mail-Adresse hinterlegt ist oder die E-Mail nicht ";
		$m .= "versandt werden konnte. Bitte teile dem Benutzer folgende ";
		$m .= "Zugangsdaten mit:<br /><br />";
		$m .= "Benutzername <strong>$username</strong><br />";
		$m .= "Passwort <strong>$password</strong>";
		new Message("Benutzer $username erstellt", $m);
		$this->backToViewButton($_GET["id"]);
	}
	
}

?>