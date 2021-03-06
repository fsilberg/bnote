<?php
if(file_exists($GLOBALS["DIR_DATA"] . "applicationdataprovider.php")) {
	require_once $GLOBALS["DIR_DATA"] . "applicationdataprovider.php";
}

/**
 * Data Access Object (DAO) Template for all entities.
 * @author matti
 *
 */
abstract class AbstractData {
	
	/**
	 * Database Object
	 */
	protected $database;

	/**
	 * Regular Expression Object
	 */
	protected $regex;
	
	/**
	 * Content: [db_field] => [label, Type]<br />
	 * With [label] as the displayed name and [type] as the Type constant.
	 * @var Array
	 */
	protected $fields;
	
	/**
	 * References to other entities.
	 * Content: [foreign_key_column] => [foreign table]
	 * @var Array
	 */
	protected $references;
	
	/**
	 * Associated database table.
	 * @var String
	 */
	protected $table;
	
	/**
	 * ADP = Application Data Provider
	 * A collection of data access methods used in multiple modules
	 * accessing database information.
	 * @var Object
	 */
	private $adp;
	
	/**
	 * Initialize data provider.
	 */
	protected function init() {
		global $system_data;
		$this->database = $system_data->dbcon;
		$this->regex = $system_data->regex;
		
		$this->adp = new ApplicationDataProvider($this->database, $this->regex, $system_data);
	}
	
	/**
	 * <strong>init() must have been called first!</strong>
	 * @return ApplicationDataProvider The application data provider reference.
	 */
	public function adp() {
		if($this->adp == null) echo "<strong>Application Data Provider not set! Call init()!</strong>";
		return $this->adp;
	}
	
	/**
	 * Returns the name of the database fields.
	 * @return One dimensional array with db_fields as values.
	 */
	public function getDatabaseFields() {
		return array_keys($this->fields);
	}
	
	/**
	 * Returns the complete field information array.
	 * @return Array with the content: [db_field] => [label, Type]<br />
	 * With [label] as the displayed name and [type] as the Type constant.
	 */
	public function getFields() {
		return $this->fields;
	}
	
	/**
	 * @return The name of the database table.
	 */
	public function getTable() {
		return $this->table;
	}
	
	/**
	 * Returns the label of the given field name.
	 * @param String $field Database field name.
	 * @return String label of field.
	 */
	public function getLabelOfField($field) {
		if(in_array($field, $this->fields)) return "";
		return $this->fields[$field][0];
	}
	
	/**
	 * Returns the type of the given field name.
	 * @param String $field Database field name.
	 * @return Type Type of the field. If field not existent, TEXT is returned.
	 */
	public function getTypeOfField($field) {
		if(!key_exists($field, $this->fields)) {
			return FieldType::TEXT;
		}
		return $this->fields[$field][1];
	}
	
	/**
	 * Returns the table references by the given column.
	 * @param String $column Name of the referencing column.
	 * @return Name of the table the column references to.
	 */
	public function getReferencedTable($column) {
		if(isset($this->references[$column])) {
			return $this->references[$column];
		}
		return $this->getTable();
	}
	
	/**
	 * Returns all entities, without exchanging the foreign key columns with something.<br />
	 * <strong>Don't use this function for entities with possible large contents!</strong>
	 * @return Returns an database getSelection(...) result array.
	 */
	public function findAllNoRef() {
		$query = "SELECT * FROM $this->table";
		return $this->database->getSelection($query);
	}
	
	/**
	 * Returns all entities, without exchanging the foreign key columns with something.<br />
	 * @param String $limit Limit Expression in SQL without the "LIMIT" identifier.
	 * @return Returns an database getSelection(...) result array.
	 */
	public function findAllNoRefLimit($limit) {
		$query = "SELECT * FROM $table LIMIT $limit";
		return $this->database->getSelection($query);
	}
	
	/**
	 * Returns all entities, without exchanging the foreign key columns with something.<br />
	 * @param String $where Where clause in SQL without the "WHERE" identifier.
	 * @return Returns an database getSelection(...) result array.
	 */
	public function findAllNoRefWhere($where) {
		$query = "SELECT * FROM $table WHERE $where";
		return $this->database->getSelection($query);
	}
	
	/**
	 * Returns all entities with the foreign key columns exchanged for the given exchange columns.
	 * @param Array $colExchange The columns that will be exchanged for the foreign key column.<br/>
	 * 		Format: [foreign_key_column] => [col1, col2, ...] with colX in the referred table.
	 * @return Returns an database getSelection(...) result array.
	 */
	public function findAllJoined($colExchange) {
		return $this->database->getSelection($this->createJoinedQuery($colExchange));
	}
	
	/**
	 * Returns all entities with the foreign key columns exchanged for the given exchange columns.
	 * @param Array $colExchange The columns that will be exchanged for the foreign key column.<br/>
	 * 		Format: [foreign_key_column] => [col1, col2, ...] with colX in the referred table.
	 * @param String $limit Limit Expression in SQL without the "LIMIT" identifier.
	 * @return Returns an database getSelection(...) result array.
	 */
	public function findAllJoinedLimit($colExchange, $limit) {
		if(strlen($limit) > 0) {
			$q = $this->createJoinedQuery($colExchange) . " LIMIT $limit";
			return $this->database->getSelection($q);
		}
		else {
			return findAllJoined($colExchange);
		}
	}
	
	/**
	 * 
	 * Returns all entities with the foreign key columns exchanged for the given exchange columns.
	 * @param Array $colExchange The columns that will be exchanged for the foreign key column.<br/>
	 * 		Format: [foreign_key_column] => [col1, col2, ...] with colX in the referred table.
	 * @param String $where Where clause in SQL without the "WHERE" identifier.
	 * @return Returns an database getSelection(...) result array.
	 */
	public function findAllJoinedWhere($colExchange, $where) {
		if(strlen($where) > 0) {
			$q = $this->createJoinedQuery($colExchange) . " AND $where";
			return $this->database->getSelection($q);
		}
		else {
			return findAllJoined($colExchange);
		}
	}
	
	/**
	 * Helper function to build the complex query.
	 * @param Array $colExchange see findAllJoined(...)
	 */
	private function createJoinedQuery($colExchange) {
		// build query
		$query = "SELECT ";
		$join = "";
		$tables = array();
		array_push($tables, $this->table);
		
		// all native fields
		foreach($this->fields as $field => $info) {
			if(isset($colExchange[$field])) continue;
			$query .= $this->table . "." . $field . ", ";
		}
		
		// all exchanged fields
		if(count($colExchange) != 0) {
			foreach($colExchange as $fcol => $tcols) {
				if(!isset($this->references[$fcol])) continue;
				$foreign_table = $this->references[$fcol];
				if(is_array($tcols)) {
					foreach($tcols as $cid => $col) {
						$query .= $foreign_table . "." . $col . " as $foreign_table$col, "; // foreign_table.foreign_col
					}
				}
				// add table to required tables
				if(!in_array($foreign_table, $tables)) array_push($tables, $foreign_table);
				
				// add join clause: fcol_id = foreigntable.id
				$join .= $this->table . "." . $fcol . " = " . $foreign_table . ".id AND ";
			}
		}
		$query = substr($query, 0, strlen($query)-2); // cut last ", "
		
		// From tables
		$query .= " FROM ";
		foreach($tables as $table) {
			$query .= $table . ", ";
		}
		if(count($tables) > 1) $query = substr($query, 0, strlen($query)-2); // cut last ", "
		
		// join statement
		if(strlen($join) > 5) $join = substr($join, 0, strlen($join)-5); // cut last " AND "
		$query .= " WHERE " . $join;

		return $query;
	}
	
	/**
	 * Finds one row result by its id.
	 * @param int $id ID of the row.
	 * @return Returns a database getRow(...) result. 
	 */
	public function findByIdNoRef($id) {
		$query = "SELECT * FROM $this->table WHERE id = $id";
		return $this->database->getRow($query);
	}
	
	/**
	 * Finds a row results by its id and includes exchanged columns.
	 * @param int $id ID of the row.
	 * @param Array $colExchange
	 * @return Returns a database getRow(...) result.
	 */
	public function findByIdJoined($id, $colExchange) {
		$table = $this->table;
		$query = $this->createJoinedQuery($colExchange) . " AND $table.id = $id";
		return $this->database->getRow($query);
	}
	
	/**
	 * Creates a new row with the given values.
	 * @param Array $values Value array in the format [db_field] => [value].
	 * @return ID of the insert statement / new entity.
	 */
	public function create($values) {
		if(count($values) > 0) {
			// build query
			$cols = "";
			$vals = "";
			
			foreach($values as $field => $value) {
				if(!in_array($field, array_keys($this->fields))) continue;
				$cols .= $field . ", ";
				$t = $this->getTypeOfField($field);
				
				if($t == FieldType::DATE || $t == FieldType::DATETIME) {
					$value = Data::convertDateToDb($value);
				}
				else if($t == FieldType::DECIMAL) {
					$value = Data::convertToDb($value);
				}
				
				if($t == FieldType::TEXT || $t == FieldType::CHAR || $t == FieldType::PASSWORD
					|| $t == FieldType::DATETIME || $t == FieldType::TIME || $t == FieldType::ENUM
					|| $t == FieldType::DATE || $t == FieldType::EMAIL || $t == FieldType::LOGIN) $vals .= '"' . $value . '", ';
				else $vals .= $value . ", ";
			}
			$cols = substr($cols, 0, strlen($cols)-2); // cut last ", "
			$vals = substr($vals, 0, strlen($vals)-2); // cut last ", "
			
			$query = "INSERT INTO $this->table (";
			$query .= $cols;
			$query .= ") VALUES (";
			$query .= $vals;
			$query .= ")";
		}

		return $this->database->execute($query);
	}
	
	/**
	 * Updates the row with the given $id with the values.
	 * @param String $id ID of the row to update.
	 * @param Array $values Array in the format [db_field] => [value]
	 */
	public function update($id, $values) {
		$query = "UPDATE " . $this->table . " SET ";
		
		foreach($values as $field => $val) {
			if(!array_key_exists($field, $this->fields)) continue;
			else {
				$query .= $field . " = ";
				$t = $this->getTypeOfField($field);
				
			if($t == FieldType::DATE || $t == FieldType::DATETIME) {
					$val = Data::convertDateToDb($val);
				}
				else if($t == FieldType::DECIMAL) {
					$val = Data::convertToDb($val);
				}
				
				if($t == FieldType::TEXT || $t == FieldType::CHAR || $t == FieldType::PASSWORD
					|| $t == FieldType::DATETIME || $t == FieldType::TIME || $t == FieldType::ENUM
					|| $t == FieldType::DATE || $t == FieldType::EMAIL || $t == FieldType::LOGIN) {
						$query .= '"' . $val . '", ';
					}
				else {
					$query .= $val . ", ";
				}
			}
		}
		$query = substr($query, 0, strlen($query)-2);
		$query .= " WHERE id = $id";
		$this->database->execute($query);
	}
	
	/**
	 * Removes the row with the given id.
	 * @param int $id Id of the row.
	 */
	public function delete($id) {
		$query = "DELETE FROM " . $this->table . " WHERE id = $id";
		$this->database->execute($query);
	}
	
	/**
	 * Validate user input based on fieldtype information. If function passes,
	 * then the values are ok.
	 * @param Array input Input array in the form of [id] => [value].
	 */
	public function validate($input) {
		if(count($input) == 0) {
			new Error("Bitte geben Sie ausreichend Informationen an.");
		}
		foreach($input as $id => $value) {
			switch($this->getTypeOfField($id)) {
				case 1: $this->regex->isPositiveAmount($value); break;
				case 2: $this->regex->isMoney($value); break;
				case 3: $this->regex->isName($value); break;
				case 4: $this->regex->isDate(trim($value)); break;
				case 5: $this->regex->isTime(trim($value)); break;
				case 6: $this->regex->isDateTime(trim($value)); break;
				case 7: if($value != "null") $this->regex->isPositiveAmount($value); break;
				case 8: $this->regex->isEmail($value); break;
				case 9: // only check if password is not empty.
						if(isset($value) && $value != "") $this->regex->isPassword($value);
						break;
				case 13: $this->regex->isLogin($value); break;
				default: $this->regex->isText($value); break;
			}
		}
	}
}