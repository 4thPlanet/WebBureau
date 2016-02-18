<?php
	abstract class Database {

	//	protected $db_connection;
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
	}
?>
