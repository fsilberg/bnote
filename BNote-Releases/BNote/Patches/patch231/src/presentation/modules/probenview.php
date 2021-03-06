<?php

/**
 * View for rehearsal module.
 * @author matti
 *
 */
class ProbenView extends CrudRefView {
	
	/**
	 * Create the contact view.
	 */
	function __construct($ctrl) {
		$this->setController($ctrl);
		$this->setEntityName("Probe");
		$this->setJoinedAttributes(array(
			"location" => array("name")
		));
	}
	
	function start() {
		Writing::h1("Proben");
		Writing::p("Bitte auf eine Probe klicken um diese zu bearbeiten.");
		
		$add = new Link($this->modePrefix() . "addEntity", "Probe hinzufügen");
		$add->addIcon("add");
		$add->write();
		
		Writing::h2("N&auml;chste Probe");
		$nextRehearsal = $this->getData()->getNextRehearsal();
		if($nextRehearsal != null && $nextRehearsal != "" && count($nextRehearsal) > 0) {
			$this->writeRehearsal($nextRehearsal);
		}
		else {
			Writing::p("Keine Probe angesagt.");
		}

		Writing::h2("Weitere Proben");
		$this->writeRehearsalList($this->getData()->getAllRehearsals());
	}
	
	function addEntity() {
		// check whether a location exists
		if(!$this->getData()->locationsPresent()) {
			$msg = new Message("Keine Location vorhanden", "Bevor du eine Probe anlegen kannst, erstelle bitte eine Location.");
			$this->backToStart();
			return;
		}
		
		// New entity form
		$form = new Form("Neue Probe", $this->modePrefix() . "add");
		$form->autoAddElementsNew($this->getData()->getFields());
		$form->removeElement("id");
		$form->setForeign("location", "location", "id", "name", -1);
		$form->write();
		
		$this->verticalSpace();
		$this->backToStart(); 
	}
	
	function add() {
		// validate
		$this->getData()->validate($_POST);
		
		// process
		$this->getData()->create($_POST);
		
		// write success
		new Message($this->getEntityName() . " gespeichert",
				"Die Probe wurde erfolgreich gespeichert.");
		
		// Show link to create a rehearsal information
		$lnk = new Link("?mod=7&mode=rehearsalMail", "Probenbenachrichtigung an Mitspieler senden");
		$lnk->write();
		$this->verticalSpace();
		
		// write back button
		$this->backToStart();
	}
	
	/**
	 * Writes out a list with rehearsals.
	 * @param Array $data Data selection array.
	 */
	private function writeRehearsalList($data) {
		// omit the header and the first row, cause its the next rehearsal
		$count = 0;
		for($i = 2; $i < count($data); $i++) {
			$this->writeRehearsal($data[$i]);
			$count++;
		}
		if($count == 0) {
			Writing::p("Keine weiteren Proben angesagt.");
		}
	}
	
	/**
	 * Writes out a single rehearsal as text.
	 * @param Array $row Row with field ids of rehearsal and location as keys (id column of rehearsal).
	 */
	private function writeRehearsal($row) {
		/* PHP > 5.2!!!
		 *
		// calculate day of the week
		$date_begin = new DateTime($row["begin"]);
		$date_end = new DateTime($row["end"]);
		$weekday = Data::convertEnglishWeekday($date_begin->format('D'));

		// check whether they are on the same day -> if so, only write hour as end
		$finish = $date_end->format('H:i');
		*/
		// PHP 5.0 - 5.1
		$date_begin = strtotime($row["begin"]);
		$date_end = strtotime($row["end"]);
		$weekday = Data::convertEnglishWeekday(date("D", $date_begin));
		$finish = date('H:i', $date_end);

		$when = Data::convertDateFromDb($row["begin"]) . " bis " . $finish . " Uhr";

		// put the output together
		$out = "<p class=\"rehearsal_title\">$weekday, $when</p>";
		$out .= "<p class=\"rehearsal_details\">" . $row["name"];
		$out .= " (" . $row["street"] . ", " . $row["zip"] . " " . $row["city"] .  ")</p>";
		$out .= "<pre class=\"rehearsal\">" . $row["notes"] . "</pre>\n";
		
		echo "<a class=\"rehearsal\" href=\"" . $this->modePrefix() . "view&id=" . $row["id"] . "\">";
		echo "<div class=\"rehearsal\">$out</div>";
		echo "</a>\n";
	}
	
	protected function editEntityForm() {
		$r = $this->getData()->findByIdNoRef($_GET["id"]);
		
		$form = new Form($this->getEntityName() . " bearbeiten",
							$this->modePrefix() . "edit_process&id=" . $_GET["id"]);
		$form->autoAddElements($this->getData()->getFields(),
									$this->getData()->getTable(), $_GET["id"]);
		$form->removeElement("id");
		$form->setForeign("location", "location", "id", "name", $r["location"]);
		
		$form->write();
	}
	
	protected function viewDetailTable() {
		$entity = $this->getData()->findByIdJoined($_GET["id"], $this->getJoinedAttributes());
		$details = new Dataview();
		$details->autoAddElements($entity);
		$details->autoRename($this->getData()->getFields());
		$details->renameElement("name", "Ort");
		$details->renameElement("street", "Stra&szlig;e");
		$details->renameElement("zip", "Postleitzahl");
		$details->renameElement("city", "Stadt");
		$details->write();
	}
	
	protected function additionalViewButtons() {
		$participants = new Link($this->modePrefix() . "participants&id=" . $_GET["id"], "Teilnehmer anzeigen");
		$participants->addIcon("user");
		$participants->write();
		$this->buttonSpace();
		
		$songs = new Link($this->modePrefix() . "practise&id=" . $_GET["id"], "St&uuml;cke zum &uuml;ben");
		$songs->addIcon("music_file");
		$songs->write();
		$this->buttonSpace();
		
		// show a button to send a reminder to all about this rehearsal
		$remHref = "?mod=" . $this->getData()->getCommunicationModuleId() . "&mode=rehearsalMail&preselect=" . $_GET["id"]; 
		
		$reminder = new Link($remHref, "Benachrichtigung senden");
		$reminder->addIcon("email");
		$reminder->write();
	}
	
	function participants() {
		$this->checkID();
		
		// participants table
		$pdate = $this->getData()->getRehearsalBegin($_GET["id"]);
		Writing::h2("Probe am $pdate Uhr");
		$table = new Table($this->getData()->getParticipants($_GET["id"]));
		$table->renameHeader("participate", "Nimmt teil");
		$table->renameHeader("reason", "Grund");
		$table->write();
		$this->verticalSpace();
		
		// statistics
		Writing::h3("Zusammenfassung");
		$dv = new Dataview();
		$dv->autoAddElements($this->getData()->getParticipantStats($_GET["id"]));
		$dv->write();
		$this->verticalSpace();
		
		// back button
		$this->backToViewButton($_GET["id"]);
		$this->verticalSpace();
	}
	
	function practise() {
		$this->checkID();
		$pdate = $this->getData()->getRehearsalBegin($_GET["id"]);
		Writing::h2("St&uuml;cke zum &uuml;ben f&uuml;r Probe am $pdate Uhr");
		
		// check if a new song was added
		if(isset($_POST["song"])) {
			$this->getData()->saveSongForRehearsal($_POST["song"], $_GET["id"], $_POST["notes"]);
		}
		
		// show songs
		$songs = $this->getData()->getSongsForRehearsal($_GET["id"]);
		echo "<ul>\n";
		for($i = 1; $i < count($songs); $i++) {
			$s = $songs[$i];
			$href = $this->modePrefix() . "practise&id=" . $_GET["id"];
			$href .= "&song=" . $s["id"];
			$caption = $s["title"];
			echo "<li class=\"practise\"><a href=\"$href\">$caption</a><br />";
			// show options if required
			if(isset($_GET["song"]) && $_GET["song"] == $s["id"]) {
				echo '<form method="POST" action="' . $this->modePrefix();
				echo 'practiseUpdate&id=' . $_GET["id"] . '&song=' . $s["id"] . '">';
				echo ' <input type="text" name="notes" size="30" value="' . $s["notes"] . '" />';
				echo ' <input type="submit" value="speichern" />&nbsp;&nbsp;';
				$del = new Link($this->modePrefix() .
					"practiseDelete&id=" . $_GET["id"] . "&song=" . $s["id"], "l&ouml;schen");
				$del->write();
				echo '</form>';
			}
			else {
				echo $s["notes"] . "<br />";
			}
			echo "</li>\n";
		}
		if(count($songs) == 1) {
			echo "<li>Keine St&uuml;cke ausgew&auml;hlt.</li>\n";
		}
		echo "</ul>\n";
		
		// add a song
		$form = new Form("St&uuml;ck hinzuf&uuml;gen", $this->modePrefix() . "practise&id=" . $_GET["id"]);
		$form->addElement("song", new Field("song", "", FieldType::REFERENCE));
		$form->setForeign("song", "song", "id", "title", -1);
		$form->renameElement("song", "St&uuml;ck");
		$form->addElement("Anmerkungen", new Field("notes", "", FieldType::CHAR));
		$form->write();
		
		$this->verticalSpace();
		$this->backToViewButton($_GET["id"]);
	}
	
	function practiseUpdate() {
		$this->checkID();
		$this->getData()->updateSongForRehearsal($_GET["song"], $_GET["id"], $_POST["notes"]);
		unset($_GET["song"]);
		$this->practise();
	}
	
	function practiseDelete() {
		$this->checkID();
		$this->getData()->removeSongForRehearsal($_GET["song"], $_GET["id"]);
		unset($_GET["song"]);
		$this->practise();
	}
}

?>
