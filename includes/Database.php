<?php
	abstract class Database {

		/* These are set by the child class */
		protected static $db_pass;
		protected static $db_server;
		protected static $db_name;
		protected static $db_login;
		protected $db_connection;
		/* END These are set by the child class */

		public $query_count = 0;

		function __construct() {
			$this->db_connection = new mysqli(static::$db_server, static::$db_login, static::$db_pass, static::$db_name);
		}

		public function run_query($query,$params = false) {
			if ($prepared = $this->db_connection->prepare($query)) {
				if ($params) {
					$paramTypes = '';
					$paramVals = array();
					foreach ($params as $index=>$param) {
							$paramTypes .= $params[$index]['type'];
							$paramVals[] = &$params[$index]['value'];
					}
					array_unshift($paramVals,$paramTypes);
					call_user_func_array(array($prepared,'bind_param'),$paramVals);
				}
				$prepared->execute();
				if ($prepared->error) {
					logging::log("error", "Error executing query: {$prepared->error}".PHP_EOL."Query: $query\nParams: " . print_r($params,true));
					return false;
				}
				$this->query_count++;
				if (method_exists($prepared,'get_result')) {
					$result = $prepared->get_result();
					if (!is_bool($result)) $result = $result->fetch_all(MYSQLI_ASSOC);
				} else {
					/* get_result(), the long way (still need way of returning error)... */
					$meta = $prepared->result_metadata();	//result metadata (information about each column)
					$result = array();		//what we will return
					$row = array();			//each result row will go in here
					$cols = array();		//array of references to $row based on $meta

					if ($meta->field_count) {
						$fields = $meta->fetch_fields();
						foreach($fields as $field)
						{
							$var = $field->name;
							$$var = null;
							$cols[$var] = &$$var;
						}

						call_user_func_array(array($prepared,'bind_result'),$cols);
						$i = 0;
						while ($prepared->fetch()) {
							$result[$i] = array();
							foreach($cols as $k=>$v) {
								$result[$i][$k] = $v;
							}
							$i++;
						}
					}
				}
/* 				End crappy way of doing things for 5.2 */
				$prepared->close();
				return $result;
			} else {
				// Error in prepared statement...
				logging::log("error", "Error preparing query: {$this->db_connection->error}".PHP_EOL."$query");
				return false;
			}
		}

		public function trigger($name,$when,$table,$action) {
			/* Creates trigger $name on $table for $when events.  $action is the trigger action. */
			/* $when = {BEFORE|AFTER} {INSERT|UPDATE|DELETE}
			 *
			 * */
			$trigger = "CREATE TRIGGER $name $when ON $table FOR EACH ROW $action";
			$this->db_connection->query($trigger);

		}
		public function drop_trigger($name) {
			/* Drops trigger $name */
			$drop = "DROP TRIGGER IF EXISTS $name";
			$this->db_connection->query($trigger);
		}

		/* Returns the last inserted ID */
		public function get_inserted_id() {
			return $this->db_connection->insert_id;
		}

		/* Returns a list of errors from the last statement run*/
		public function GetErrors() {
			return $this->db_connection->error;
		}

		/* Closes the connection */
		public function close() {
			return $this->db_connection->close();
        }
        /* Confirms the param type is properly set, given data type.*/
        public static function param_type_check($data_type,&$param_type) {
	        if (preg_match('/(int|bit)/',$data_type)) {
	            $param_type = 'i';
	        } elseif (preg_match('(decimal|double|float)',$data_type)) {
	            $param_type = 'd';
	        }
    	}

    	public function table_exists($table) {
    		$query = "
			SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS is_table
			FROM INFORMATION_SCHEMA.TABLES T
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
    		$params = array(
    			array("type" => "s", "value" => $this->get_db_name()),
    			array("type" => "s", "value" => $table)
    		);
    		$result = $this->run_query($query,$params);
    		return $result[0]['is_table']==1;
    	}

    	public function table_keys($table) {
    		$query = "
				SELECT IFNULL(S.INDEX_NAME,U.CONSTRAINT_NAME) as INDEXNAME,IFNULL(S.NON_UNIQUE,1) as NON_UNIQUE,
				GROUP_CONCAT(IFNULL(S.COLUMN_NAME,U.COLUMN_NAME) ORDER BY IFNULL(S.SEQ_IN_INDEX,U.ORDINAL_POSITION) SEPARATOR ',' ) COLS,
				U.REFERENCED_TABLE_NAME, U.REFERENCED_COLUMN_NAME,
                C.UPDATE_RULE, C.DELETE_RULE
				FROM INFORMATION_SCHEMA.STATISTICS S
				LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE U ON S.INDEX_NAME = U.CONSTRAINT_NAME AND S.TABLE_NAME = U.TABLE_NAME AND S.TABLE_SCHEMA = U.TABLE_SCHEMA AND S.COLUMN_NAME = U.COLUMN_NAME
				LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS C ON U.CONSTRAINT_NAME = C.CONSTRAINT_NAME AND U.TABLE_NAME = C.TABLE_NAME AND U.TABLE_SCHEMA = C.CONSTRAINT_SCHEMA
    			WHERE S.TABLE_SCHEMA = ? AND S.TABLE_NAME = ?
				GROUP BY IFNULL(S.INDEX_NAME,U.CONSTRAINT_NAME)
				UNION
				SELECT IFNULL(S.INDEX_NAME,U.CONSTRAINT_NAME) as INDEXNAME,IFNULL(S.NON_UNIQUE,1) as NON_UNIQUE,
				GROUP_CONCAT(IFNULL(S.COLUMN_NAME,U.COLUMN_NAME) ORDER BY IFNULL(S.SEQ_IN_INDEX,U.ORDINAL_POSITION) SEPARATOR ',' ) COLS,
				U.REFERENCED_TABLE_NAME, U.REFERENCED_COLUMN_NAME,
                C.UPDATE_RULE, C.DELETE_RULE
				FROM INFORMATION_SCHEMA.STATISTICS S
				RIGHT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE U ON S.INDEX_NAME = U.CONSTRAINT_NAME AND S.TABLE_NAME = U.TABLE_NAME AND S.TABLE_SCHEMA = U.TABLE_SCHEMA AND S.COLUMN_NAME = U.COLUMN_NAME
				LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS C ON U.CONSTRAINT_NAME = C.CONSTRAINT_NAME AND U.TABLE_NAME = C.TABLE_NAME AND U.TABLE_SCHEMA = C.CONSTRAINT_SCHEMA
    			WHERE U.TABLE_SCHEMA = ? AND U.TABLE_NAME = ?
				GROUP BY IFNULL(S.INDEX_NAME,U.CONSTRAINT_NAME)
    		";
    		$params = array(
    			array("type" => "s", "value" => static::$db_name),
    			array("type" => "s", "value" => $table),
    			array("type" => "s", "value" => static::$db_name),
    			array("type" => "s", "value" => $table),
    		);
    		return $this->run_query($query,$params);
    	}

    	public function table_column_exists($table,$column) {
    		$query = "
				SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS has_column
				FROM INFORMATION_SCHEMA.COLUMNS C
				WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
			";
    		$params = array(
    			array("type" => "s", "value" => $this->get_db_name()),
    			array("type" => "s", "value" => $table),
    			array("type" => "s", "value" => $column)
    		);
    		$result = $this->run_query($query,$params);
    		return $result[0]['has_column']==1;
    	}

    	public function create_table($table_name,$columns,$keys,$engine='INNODB') {
    		if (!preg_match('/^\d*[_a-zA-Z]\w*/',$table_name)) {
    			Layout::set_message('Invalid Table Name','error');
    			return false;
    		}
    		if (empty($columns)) {
    			Layout::set_message('At least one field must be present.','error');
    			return false;
    		}

    		$query = "CREATE TABLE $table_name (";
    		foreach($columns as $column_name => $column_definition) {
    			if (!preg_match('/^\d*[_a-zA-Z]\w*/',$column_name)) {
    				Layout::set_message("$column_name is an invalid field name",'error');
    				return false;
    			}
    			$query .= " $column_name $column_definition".PHP_EOL.",";
    		}
    		foreach($keys as $key_type => $indexes) {
    			/* key types:
    			 * PRIMARY (just list columns)
    			 * FOREIGN (array of id => array(TABLE,COLUMN))
    			 * UNIQUE (just list columns)
    			 * '' (basic key - just list columns)
    			 */
    			if ($key_type == 'PRIMARY') {
    				// only one primary key
    				$query .= "$key_type KEY (".implode(",",$indexes).")" . PHP_EOL . ",";
    			} else {
    				if ($key_type == 'FOREIGN') {
    					// column => table(ID)
    					foreach($indexes as $column => $references) {
    						$query .= "$key_type KEY ($column) REFERENCES {$references['table']}({$references['column']})";
    						if (isset($references['delete']))
    							$query .= " ON DELETE {$references['delete']}";
    						if (isset($references['update']))
    							$query .= " ON UPDATE {$references['update']}";
    						$query .= PHP_EOL . ",";
    					}
    				} else {
    					// just a list of each index
    					foreach($indexes as $columns) {
    						$query .= "$key_type KEY (".implode(",",$columns).")" . PHP_EOL . ",";
    					}
    				}
    			}
    		}
    		$query = substr($query,0,-1);
    		$query .= ") ENGINE=$engine";

    		$result = $this->run_query($query);
    	}

    	public function add_table_column($table,$column_name,$column_definition) {
    		$query = "ALTER TABLE {$table} ADD COLUMN {$column_name} {$column_definition}";
    		$result = $this->run_query($query);
    	}


    	public function add_table_key($table,$key_type,$columns,$reference_data=array()) {
    		$query = "ALTER TABLE $table ADD $key_type KEY (".implode(",",$columns).")";

    		if ($reference_data) {
    			$query .= " REFERENCES ".$reference_data['table'] . "(".$reference_data['column'].")";
    			if (isset($reference_data['update'])) {
    				$query .= " ON UPDATE " . $reference_data['update'];
    			}
    			if (isset($reference_data['delete'])) {
    				$query .= " ON DELETE " . $reference_data['delete'];
    			}
    		}
    		$result = $this->run_query($query);
    	}
	}
?>
