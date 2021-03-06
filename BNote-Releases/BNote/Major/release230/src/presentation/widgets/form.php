<?php

/**
 * Prints a form in a formbox
**/
class Form implements iWriteable {

 protected $formname;
 protected $method;
 protected $action;
 protected $multipart;
 protected $elements = array();
 protected $foreign = array();
 protected $hidden = array();
 private $rename = array();
 protected $submitValue; 
 protected $removeSubmitButton = false;

 /**
  * Constructor
  * @param String $name    The form's header description
  * @param String $action  link to call when form is submitted
  */
 function Form($name, $action) {
  $this->formname = $name;
  $this->method = 'POST';
  $this->action = $action;
  $this->multipart = ""; // none by default
  $this->submitValue = "OK";
 }

 /**
  * Sets either POST or GET as method.
  * @param String $method Method attribute of form-tag.
  */
 function setMethod($method) {
 	$this->method = $method;
 }
 
 /**
  * adds an Element to the form
  * @param String $name Label of the element
  * @param Field  $element Objectlink to an iWriteable implementing class-object
  */ 
 public function addElement($name, $element) {
  $this->elements[$name] = $element;
 }

 /**
  *  Automatically adds elements from an array
  *  @param $array Array with format field => fieldtype
  *  @param $table table associated with the array
  *  @param $id id to fill the form with the data of the row with this id
  */
 public function autoAddElements($array, $table, $id) {
  global $system_data;
  $entity = $system_data->dbcon->getRow("SELECT * FROM $table WHERE id = $id");
  foreach($array as $field => $info) {
   $value = $entity[$field];
   if(($info[1] == FieldType::DATE || $info[1] == FieldType::DATETIME) && !empty($value)) {
   		$value = Data::convertDateFromDb($value);
   }
   else if($info[1] == FieldType::DECIMAL) {
   		$value = Data::convertFromDb($value);
   }
   else if($info[1] == FieldType::PASSWORD) {
   		$value = "";
   }
   $this->addElement($field, new Field($field, $value, $info[1]));
   $this->renameElement($field, $info[0]);
  }
 }

 /**
  * automatically adds elements from an array, but without values
  * @param $array Array with format field => fieldtype
  */
 public function autoAddElementsNew($array) {
  foreach($array as $field => $info) {
   $this->addElement($field, new Field($field, "", $info[1]));
   $this->renameElement($field, $info[0]);
   }
 }
 
 /**
  * Sets a certain column as a foreign key and creates a dropbox for it
  * @param string $field The column which is the foreign key 
  * @param string $table The table the foreign key references to
  * @param string $idcolumn The column in the foreign table which is referenced
  * @param string $namecolumn The column in the foreign table which contains the names for the refereced keys
  * @param string $selectedid The id which is currently set, set -1 if none
  */
 public function setForeign($field, $table, $idcolumn, $namecolumn, $selectedid) {
 	// check whether key even exists
 	if(!array_key_exists($field, $this->elements)) new Error("Der Fremdschl&uuml;ssel konnte nicht gefunden werden.");
 	
 	// create new dropdown list
 	$dropdown = new Dropdown($field);
 	
 	global $system_data;
 	$choices = $system_data->dbcon->getForeign($table, $idcolumn, $namecolumn);
 	foreach($choices as $id => $name) {
 		$dropdown->addOption($name, $id);
 	}
 	if($selectedid >= 0) $dropdown->setSelected($selectedid);
 	
 	$this->foreign[$field] = $dropdown;
 }
 
 /**
  * Add an option to a foreign key dropboxbox
  * @param Identifier $field Name of the field to add the option for
  * @param String $optionname Name of the option 
  * @param String $optionvalue Value of the option
  */
 public function addForeignOption($field, $optionname, $optionvalue) {
 	$dp = $this->foreign[$field];
 	$dp->addOption($optionname, $optionvalue);
 }
 
 /**
  * Change what's selected on a foreign field
  * @param Identifier $field Name of the field where to change the option
  * @param integer $id Selected option
  */
 public function setForeignOptionSelected($field, $id) {
 	$dp = $this->foreign[$field];
 	$dp->setSelected($id);
 }
 
 /**
  * Prepare dropdownlists to be written
  */
 private function createForeign() {
 	foreach($this->foreign as $field => $dropdown) {
	 	// write dropdown list to elements array
	 	$this->elements[$field] = $dropdown; 
 	}
 }
 
 /**
  * Removes the element from the form
  * @param Identifier $name The name of the element to remove
  */
 public function removeElement($name) { 	
 	// determine position of element to remove
 	$i = 0;
 	foreach($this->elements as $key => $value) {
 		if($key == $name) break;
 		$i++;
 	}

 	// remove element
 	array_splice($this->elements, $i, 1);
 }
 
 /**
  * Adds a hidden field to the form
  * @param Identifier $name The identifier in the $_POST array
  * @param String $value Value of the identifier
  */
 public function addHidden($name, $value) {
 	$this->hidden[$name] = $value;
 }
 
 /**
  * Changes the caption for the submit button
  * @param String $name Caption of the submit button
  */
 public function changeSubmitButton($name) {
 	$this->submitValue = $name;
 }
 
 /**
  * Changes the label for the Element
  * @param String $name Name of the Element
  * @param String $label New label for the Element
  */
 public function renameElement($name, $label) {
 	$this->rename[$name] = $label;
 }
 
 /**
  * Returns the given elements currently saved value
  * @param Identifier $name Identifying name of the element
  */
 public function getValueForElement($name) {
 	$el = $this->elements[$name];
 	return $el->getValue();
 }
 
 /**
  * Sets whether the form contains multipart fields, e.g. file fields.
  * @param boolean $bool True if it contains multipart data (default), otherwise false.
  */
 public function setMultipart($bool = true) {
 	if($bool) $this->multipart = ' enctype="multipart/form-data"';
 	else $this->multipart = "";
 }
 
 /**
  * Sets whether the submit button should be shown or not.
  * @param boolean $bool True or nothing when the button should be removed, otherwise false.
  */
 public function removeSubmitButton($bool = true) {
 	$this->removeSubmitButton = $bool;
 }

 /**
  *  print html output
  */
 public function write() {
  $this->createForeign();
  
  //echo '<div class="FormBox">' . $this->formname . "\n";

  echo '<form method="' . $this->method . '" action="' . $this->action . '"';  
  echo $this->multipart . '>' . "\n";
  
  echo '<fieldset>';
  echo "<legend class=\"FormBox\">" . $this->formname . "</legend>\n";
  
  echo '<table>' . "\n";

  foreach($this->elements as $label => $element) {
   echo " <tr>\n";
   if(isset($this->rename[$label])) $label = $this->rename[$label];
   echo "  <td>" . $label . "</td>\n";
   echo "  <td>" . $element->write() . "</td>\n";
   echo " </tr>\n";
  }
  echo '</table>' . "\n";
  
   // add hidden values
  foreach($this->hidden as $name => $value) {
  	echo '<input type="hidden" value="' . $value . '" name="' . $name . '">' . "\n";
  }
  
  // Submit Button
  if(!$this->removeSubmitButton) {
  	echo '<input type="submit" value="' . $this->submitValue . '">' . "\n";
  }
  echo '</fieldset>' . "\n";
  echo '</form>' . "\n";
  //echo '</div>' . "\n";
 }

}

?>