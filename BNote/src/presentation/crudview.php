<?php

/**
 * Class which provides a full set of crud methods for a simple module.
 * @author matti
 */
abstract class CrudView extends AbstractView {
	
	private $entityName;
	
	/**
	 * Views all entities in a table.<br />
	 * <strong>Make sure to set the entity name!</storng>
	 * 
	 * (non-PHPdoc)
	 * @see AbstractView::start()
	 */
	public function start() {
		$this->writeTitle();
		
		$add = new Link($this->modePrefix() . "addEntity", $this->getEntityName() . " hinzufügen");
		$add->addIcon("add");
		$add->write();
		
		$this->showAdditionStartButtons();
		
		$this->showAllTable();
	}
	
	protected function showAdditionStartButtons() {
		// by default empty;
	}
	
	public function addEntity() {
		$this->addEntityForm();
		
		$this->verticalSpace();
		$this->backToStart();
	}
	
	protected function addEntityForm() {
		// add entry form
		$form = new Form($this->entityName ." hinzuf&uuml;gen", $this->modePrefix() . "add");
		$form->autoAddElementsNew($this->getData()->getFields());
		$form->removeElement("id");
		$form->write();
	}
	
	protected function showAllTable() {
		// show table rows
		$table = new Table($this->getData()->findAllNoRef());
		$table->setEdit("id");
		$table->write();
	}
	
	protected function writeTitle() {
		global $system_data;
		Writing::h2($system_data->getModuleTitle());
		Writing::p("Bitte w&auml;hlen Sie einen Eintrag um diesen anzuzeigen oder zu bearbeiten.");
	}
	
	public function add() {
		// validate
		$this->getData()->validate($_POST);
		
		// process
		$this->getData()->create($_POST);
		
		// write success
		new Message($this->entityName . " gespeichert",
						"Der Eintrag wurde erfolgreich gespeichert.");
		
		// write back button
		$this->backToStart();
	}
	
	public function view() {
		$this->checkID();
		
		// heading
		Writing::h2($this->entityName . " Details");
		
		// show buttons to edit and delete
		$edit = new Link($this->modePrefix() . "edit&id=" . $_GET["id"],
							$this->entityName . " bearbeiten");
		$edit->addIcon("edit");
		$edit->write();
		$this->buttonSpace();
		$del = new Link($this->modePrefix() . "delete_confirm&id=" . $_GET["id"],
							$this->entityName . " l&ouml;schen");
		$del->addIcon("remove");
		$del->write();
		$this->buttonSpace();
		
		// additional buttons
		$this->additionalViewButtons();
		
		// show the details
		$this->viewDetailTable();
		
		// back button
		$this->backToStart();
	}
	
	protected function viewDetailTable() {
		$entity = $this->getData()->findByIdNoRef($_GET["id"]);
		$details = new Dataview();
		foreach($this->getData()->getFields() as $dbf => $info) {
			$details->addElement($info[0], $entity[$dbf]);
		}
		$details->write();
	}
	
	protected function additionalViewButtons() {
		// by default empty
	}
	
	public function edit() {
		$this->checkID();
		
		// show form
		$this->editEntityForm();
		
		// back button
		$this->verticalSpace();
		$this->backToViewButton($_GET["id"]);
		$this->verticalSpace();
	}
	
	protected function editEntityForm() {
		$form = new Form($this->entityName . " bearbeiten",
							$this->modePrefix() . "edit_process&id=" . $_GET["id"]);
		$form->autoAddElements($this->getData()->getFields(),
									$this->getData()->getTable(), $_GET["id"]);
		$form->removeElement("id");
		$form->write();
	}
	
	public function edit_process() {
		$this->checkID();
		
		// validate
		$this->getData()->validate($_POST);
		
		// update
		$this->getData()->update($_GET["id"], $_POST);
		
		// show success
		new Message($this->entityName . " ge&auml;ndert",
						"Der Eintrag wurde erfolgreich ge&auml;ndert.");
		
		// back button
		$this->backToViewButton($_GET["id"]);
	}
	
	public function delete_confirm() {
		$this->checkID();
		$this->deleteConfirmationMessage($this->getEntityName(),
					$this->modePrefix() . "delete&id=" . $_GET["id"],
					$this->modePrefix() . "view&id=" . $_GET["id"]);
	}
	
	public function delete() {
		$this->checkID();
		// remove
		$this->getData()->delete($_GET["id"]);
		
		// show success
		new Message($this->entityName . " gel&ouml;cht",
						"Der Eintrag wurde erfolgreich gel&ouml;scht.");
		
		// back button
		$this->backToStart();
	}
	
	/**
	 * Writes a button which brings the user back to
	 * mode=view&id=<id>.
	 * @param int $id Usually $_GET["id"], but can be any id for the view mode.
	 */
	public function backToViewButton($id) {
		global $system_data;
		$btv = new Link($this->modePrefix() . "view&id=$id", "Zur&uuml;ck");
		$btv->addIcon("arrow_left");
		$btv->write();
	}
	
	public function setEntityName($name) {
		$this->entityName = $name;
	}
	
	public function getEntityName() {
		return $this->entityName;
	}
}