<?php
/*
 * The tables class is a catch-all for handling any requests to view table data.
 * It uses the _TABLE_INFO table (which can be accessed through this class!) to determine how to display each tables' data.
 */

 define('SHORT_DISPLAY',1);
 define('PREVIEW_DISPLAY',2);
 define('FULL_DISPLAY',3);
class tables extends module {

	/* The columns and primary key of a table object... */
	protected $table_name;
	protected $columns;
	protected $PK;
	protected $id;

	/* constructor */
	public function __construct($table,$id = null) {
		/* Confirm table is real, then load table columns and primary key... */
		if (static::is_table($table)) {
			$this->table_name = $table;
			$this->columns = static::get_table_columns($table);
			$this->PK = static::get_primary_key($table);
			$this->id = $id;
		}
	}

	public function set_id($id) {
		/* Sets table id to $id*/
		$this->id = $id;
	}

	/*
	 * Returns all records in table.
	 * If $id is set, only returns the one record...
	 * If returnAll = true, will return all records (will return only one page of records, using paging class, otherwise)
	 * */
	public function get_records($returnAll = false) {
		global $db;
		if (empty($this->table_name)) return false;
		extract($this->get_records_sql());

		$paging = new paging($query,$params);
		if ($returnAll)
			$paging->set_per_page($paging->get_record_count());

		return $paging->get_current_page();
	}

	protected function get_records_sql() {
		$query = "SELECT * FROM {$this->table_name}";
		$params = array();
		if (!empty($this->id)) {
			$query .= " WHERE ";
			$key_cond = array();
			if (count($this->PK)==1) {
				$key_cond[] = "{$this->PK[0]['COLUMN_NAME']} = ?";
				array_push($params,array("type" => "s", "value" => $this->id));
			} else {
				foreach($this->PK as $idx=>$key) {
					$key_cond[] = "{$key['COLUMN_NAME']} = ?";
					array_push($params,array("type" => "s", "value" => $this->id[$idx]));
				}
			}
			$query .= implode(" AND ",$key_cond);
		}
		return array(
			'query' => $query,
			'params' => $params
		);
	}

	public function save($data) {
		global $db;
		if (empty($this->id)) {
			$query = "INSERT INTO {$this->table_name} ( ";
			$params = array();
			$cols = array();
			foreach($this->columns as $column) {
				if ($column['IS_AUTO_INCREMENT']) continue;
				$cols[] = $column['COLUMN_NAME'];
				array_push(
					$params,
					array("type" => "s", "value" => $data[$column['COLUMN_NAME']])
				);
				Database::param_type_check($column['DATA_TYPE'],$params[count($params)-1]['type']);
			}
			$query .= implode(",",$cols) . ") VALUES ( ".substr(str_repeat("?,",count($params)),0,-1) ." )";
		} else {
			$query = "UPDATE {$this->table_name} SET ";
			$sets = array();
			$params = array();
			foreach($this->columns as $column) {
				if ($column['IS_AUTO_INCREMENT']) continue;
				$sets[] = "{$column['COLUMN_NAME']} = ?";
				array_push(
					$params,
					array("type" => "s", "value" => $data[$column['COLUMN_NAME']])
				);
				Database::param_type_check($column['DATA_TYPE'],$params[count($params)-1]['type']);
			}
			$query .= implode(", ",$sets) . " WHERE ";

			if (count($this->PK)==1) {
				$query .= "{$this->PK[0]['COLUMN_NAME']} = ?";
				array_push(
					$params,
					array("type" => "s", "value" => $this->id)
				);
			} elseif (count($this->PK)==count($id)) {
				$key_cond = array();
				foreach($PK as $idx=>$key) {
					$key_cond[] = "{$key['COLUMN_NAME']} = ?";
						array_push(
						$params,
						array("type" => "s", "value" => $this->id[$idx])
					);
				}
				$query .= implode(" AND ",$key_cond);
			} else {
				return false;
			}
		}
		$db->run_query($query,$params);
		if (is_null($this->id)) {
			return $db->get_inserted_id();
		} else {
			return true;
		}
	}

	/*
	 *
	 * */
	public function delete_record() {
		global $db;
		$condition = array();
		$params = array();
		$id = is_array($this->id) ? $this->id : array($this->id);
		foreach($this->PK as $idx=>$column) {
			$condition[] = "{$column['COLUMN_NAME']} = ?";
			array_push(
				$params,
				array("type" => "s", "value" => $id[$idx])
			);
		}
		$query = "
			DELETE FROM {$this->table_name}
			WHERE " . implode(",",$condition);
		$db->run_query($query,$params);
		return true;
	}
	/*
	 * Updates the _TABLE_INFO and _TABLE_META records associated with this table...
	 *
	 * */
	public function update_table_info($data) {
		global $db;
		$table_info = new Tables('_TABLE_INFO', $this->table_name);
		if (empty($table_info->get_records()))
			$table_info->set_id(null);
		$data['TABLE_NAME'] = $this->table_name;
		$table_info->save($data);
		$meta_key_params = array(
			array("type" => "s", "value" => $this->table_name)
		);

		if (!empty($data['meta'])) {
			/* Save each meta record... */
			$query = "
				INSERT INTO _TABLE_METAS (TABLE_NAME, META_NAME, META_CONTENT)
				VALUES (?,?,?)
				ON DUPLICATE KEY UPDATE META_CONTENT = VALUES(META_CONTENT);
			";
			foreach($data['meta'] as $key=>$value) {
				if ($value === "") continue;

				$params = array (
					array("type" => "s", "value" => $this->table_name),
					array("type" => "s", "value" => $key),
					array("type" => "s", "value" => $value)
				);
				$db->run_query($query,$params);
				array_push(
					$meta_key_params,
						array("type" => "s", "value" => $key)
				);
			}
		}
		/* Finally, remove any metas no longer in use... */
		$query = "
			DELETE FROM _TABLE_METAS
			WHERE
				TABLE_NAME = ? AND
				META_NAME NOT IN (".substr(str_repeat("?,",count($meta_key_params)-1),0,-1).")
		";
		$db->run_query($query,$meta_key_params);
		return true;
	}
	public function get_columns() {
		return $this->columns;
	}

	public function get_key() {
		return $this->PK;
	}

	public static function is_table($table) {
		global $db;
		/* Returns true if table is a table... */
		$query = "
			SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS is_table
			FROM INFORMATION_SCHEMA.TABLES T
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name()),
			array("type" => "s", "value" => $table)
		);
		$result = $db->run_query($query,$params);
		return $result[0]['is_table']==1;
	}

	public static function table_has_column($table,$column)
	{
		global $db;
		$query = "
			SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS has_column
			FROM INFORMATION_SCHEMA.COLUMNS C
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
		";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name()),
			array("type" => "s", "value" => $table),
			array("type" => "s", "value" => $column)
		);
		$result = $db->run_query($query,$params);
		return $result[0]['has_column']==1;
	}

	/* Returns all tables Session User has rights to view... */
	public static function get_users_viewable_tables() {
		global $db,$s_user;
		$query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name())
		);
		$tables = group_numeric_by_key($db->run_query($query,$params),'TABLE_NAME');
		foreach($tables as $idx=>$table) {
			if (!$s_user->check_right('Tables',$table,'View')) unset($tables[$idx]);
		}
		return $tables;
	}



	/* Returns a multi-dimensional array of menu options for this module (including sub-menus) */
	public static function menu() {
		global $db;
		/* Start with basic menu - only one option (Table) */
		$menu = array(
			'Tables' => array(
				'args' => array(),
				'submenu' => array(),
				'right' => null
			)
		);
		/* Build submenu by looping through each table... */
		$query = "SELECT TABLE_NAME
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME NOT RLIKE '^\_'";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name())
		);
		$tables = $db->run_query($query,$params);
		foreach($tables as $table) {
			$right = users::get_right_id('Tables',$table['TABLE_NAME'],'view');
			$menu['Tables']['submenu'][$table['TABLE_NAME']] = array(
				'args' => array($table['TABLE_NAME']),
				'submenu' => array(),
				'right' => $right
			);
			$submenu = &$menu['Tables']['submenu'][$table['TABLE_NAME']]['submenu'];

			$query = "SELECT IFNULL(DETAILED_MENU_OPTIONS,0) as DETAILED_MENU
			FROM _TABLE_INFO
			WHERE TABLE_NAME = ?";
			$params = array(
				array("type" => "s", "value" => $table['TABLE_NAME'])
			);
			$table_info = $db->run_query($query,$params);
			if (empty($table_info) || !$table_info[0]['DETAILED_MENU']) continue;
			$PK = implode(",",group_numeric_by_key(static::get_primary_key($table['TABLE_NAME']),'COLUMN_NAME'));
			$decode = static::sql_decode_display($table['TABLE_NAME'],SHORT_DISPLAY);
			$joins = "";
			if (!empty($decode['joins'])) {
				foreach($decode['joins'] as $col => $table_join) {
					$joins .= "LEFT JOIN {$table_join['table']} ON {$table['TABLE_NAME']}.{$col} = {$table_join['table']}.{$table_join['column']} ";
				}
			}
			$query = "SELECT {$table['TABLE_NAME']}.$PK, {$decode['concat']} AS SHORT_DISPLAY
			FROM {$table['TABLE_NAME']} $joins";
			$params = $decode['params'];
			$data = $db->run_query($query,$params);
			foreach ($data as $row) {
					$text = array_pop($row);
					$submenu[$text] = array(
						'args' => array_merge(array($table['TABLE_NAME']),array_values($row)),
						'submenu' => array(),
						'right' => $right
					);
			}
		}

		return $menu;
	}

	public static function decode_menu($args) {
		/* Decodes the menu args and returns the appropriate HREF */
		global $db;
		$url = static::get_module_url();
		if (empty($args)) return $url;
		/* Get the Table */
		$query = "
			SELECT T.TABLE_NAME, IFNULL(I.SLUG,T.TABLE_NAME) as 'table'
			FROM INFORMATION_SCHEMA.TABLES T
			LEFT JOIN _TABLE_INFO I ON T.TABLE_NAME = I.TABLE_NAME
			WHERE T.TABLE_SCHEMA = ? AND T.TABLE_NAME = ?";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name()),
			array("type" => "s", "value" => array_shift($args))
		);
		$result = $db->run_query($query,$params);
		extract($result[0]);


		$url.= "$table";
		if (empty($args)) return $url;
		/* Get the ID */
		$PK = static::get_primary_key($TABLE_NAME);
		$display = static::sql_decode_display($TABLE_NAME,SHORT_DISPLAY);
		$joins = "";
		if (!empty($display['joins'])) {
			foreach($display['joins'] as $col => $table_join) {
				$joins .= "LEFT JOIN {$table_join['table']} ON {$TABLE_NAME}.{$col} = {$table_join['table']}.{$table_join['column']} ";
			}
		}
		$query = "
			SELECT {$display['concat']} AS DISPLAY
			FROM $TABLE_NAME
			$joins
			WHERE ";
		$params = $display['params'];
		$clause = array();
		foreach($PK as $key) {
			$clause[] = "{$TABLE_NAME}.{$key['COLUMN_NAME']} = ?";
			array_push($params,array("type" => "s", "value" => array_shift($args)));
		}
		$query .= implode(" AND ", $clause);
		$display = $db->run_query($query,$params);
		if (empty($display)) return $url;

		if (!empty($table)) $url .= "/";
		return $url . make_url_safe($display[0]['DISPLAY'],ENT_QUOTES) ;
	}

	public static function install() {
		global $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _TABLE_INFO (
				TABLE_NAME varchar(100),
				SLUG varchar(100) UNIQUE,
				SHORT_DISPLAY varchar(50),
				PREVIEW_DISPLAY varchar(500),
				PREVIEW_DISPLAY_BEFORE varchar(500),
				PREVIEW_DISPLAY_AFTER varchar(500),
				FULL_DISPLAY text,
				LINK_BACK_TO_TABLE bit,
				ROW_DISPLAY_MAX int,
				DETAILED_MENU_OPTIONS bit,
				PRIMARY KEY (TABLE_NAME)
			)";
		$db->run_query($query);
		$query = "
			CREATE TABLE IF NOT EXISTS _TABLE_METAS (
				TABLE_NAME varchar(100),
				META_NAME varchar(100),
				META_CONTENT text,
				PRIMARY KEY (TABLE_NAME,META_NAME)
			)";
		$db->run_query($query);
		return true;
	}

	public static function required_rights() {
		global $db;
		$required = array(
			'Tables' => array(
				'Table Actions' => array(
					'Add Table' => array(
						'description' => 'Allows user to create a new table.',
						'default_groups' => array('Admin')
					)
				)
			)
		);
		$query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES T WHERE T.TABLE_SCHEMA = ?";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name())
		);
		$tables = group_numeric_by_key($db->run_query($query,$params),'TABLE_NAME');
		foreach($tables as $table) {
			$required['Tables'][$table] = array(
				'Add' => array(
					'description' => "Allows user to add to the $table table.",
					'default_groups' => array('Admin')
				),
				'Edit' => array(
					'description' => "Allows user to edit the $table table.",
					'default_groups' => array('Admin')
				),
				'Delete' => array(
					'description' => "Allows user to delete from the $table table.",
					'default_groups' => array('Admin')
				),
				'View' => array(
					'description' => "Allows user to view the $table table.",
					'default_groups' => array('Admin')
				)
			);
			if (!preg_match('/^\_/',$table))
				array_push(
					$required['Tables'][$table]['View']['default_groups'],
					'Guest',
					'Registered User'
				);
		}
		return $required;
	}

	public static function get_primary_key($table_name) {
		global $db;
		/* Returns the primary key of the table... */
		$query = "SELECT COLUMN_NAME
		FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		WHERE
			CONSTRAINT_SCHEMA = ? AND
			TABLE_NAME = ? AND
			CONSTRAINT_NAME = 'PRIMARY'";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name()),
			array("type" => "s", "value" => $table_name)
		);
		return $db->run_query($query,$params);
	}

	public static function get_table_columns($table_name) {
		/* Returns the columns of a given table, and which tables they reference (if applicable) */
		global $db;
		$query = "SELECT C.COLUMN_NAME, C.DATA_TYPE, C.CHARACTER_MAXIMUM_LENGTH,
			CASE WHEN C.EXTRA RLIKE 'auto_increment' THEN 1 ELSE 0 END as IS_AUTO_INCREMENT,
			CASE C.IS_NULLABLE WHEN 'YES' THEN 1 ELSE 0 END AS IS_NULLABLE,
			K.REFERENCED_TABLE_NAME, K.REFERENCED_COLUMN_NAME, K.CONSTRAINT_NAME
			FROM INFORMATION_SCHEMA.COLUMNS C
			LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE K ON
				C.TABLE_SCHEMA = K.TABLE_SCHEMA AND
				C.TABLE_NAME = K.TABLE_NAME AND
				C.COLUMN_NAME = K.COLUMN_NAME
			WHERE C.TABLE_NAME = ? AND C.TABLE_SCHEMA = ?
			ORDER BY C.ORDINAL_POSITION";
		$params = array(
			array("type" => "s", "value" => $table_name),
			array("type" => "s", "value" => $db->get_db_name())
		);
		return $db->run_query($query,$params);
	}

	public static function sql_decode_display($table_name,$display=SHORT_DISPLAY) {
		/* This function is used to get the SQL to display a given table. */
		/* Allowed values for $display: SHORT_DISPLAY, PREVIEW_DISPLAY, FULL_DISPLAY constants (valued at 1,2, and 3) */
		global $db;
		$query = "SELECT SHORT_DISPLAY FROM _TABLE_INFO WHERE TABLE_NAME = ?";
		$params = array(
			array("type" => "s", "value" => $table_name)
		);
		$display = $db->run_query($query,$params);
		if (!empty($display)) $display = $display[0]['SHORT_DISPLAY'];
		else {
			$keys = static::get_primary_key($table_name);
			$display = "";
			foreach($keys as $key) {
				$display .= "{{$key['COLUMN_NAME']}}";
			}
		}

		$table_info = static::get_table_columns($table_name);
		$str_parts = array(array("string"=>$display));
		$joins = array();
		foreach($table_info as $column) {
			$idx = -1;
			while ($idx < count($str_parts)-1) {
				$part = $str_parts[++$idx];
				if (empty($part['string'])) continue;
				if (preg_match("/^(?<before>.*){{$column['COLUMN_NAME']}}(?<after>.*)$/",$part['string'],$matches)) {
					$beforeArr = array_slice($str_parts,0,$idx);
					$afterArr = array_slice($str_parts,$idx+1);

					$new_parts = $beforeArr;
					if (!empty($matches['before']))
						$beforeArr = array_merge($beforeArr,array(array("string" => $matches['before'])));
					if (!empty($matches['after']))
						$afterArr = array_merge($afterArr,array(array("string" => $matches['after'])));
					$str_parts = array_merge($beforeArr,array(array("field" => "{$table_name}.{$column['COLUMN_NAME']}")),$afterArr);
				}
				if (!empty($column['REFERENCED_TABLE_NAME']) && preg_match("/^(?<before>.*){{$column['COLUMN_NAME']}_(?<fk_col>[\w]+)}(?<after>.*)$/",$part['string'],$matches)) {
					// possible foreign key column match...
					if (static::table_has_column($column['REFERENCED_TABLE_NAME'],$matches['fk_col']))
					{
						$beforeArr = array_slice($str_parts,0,$idx);
						$afterArr = array_slice($str_parts,$idx+1);
						$new_parts = $beforeArr;
						if (!empty($matches['before']))
							$beforeArr = array_merge($beforeArr,array(array("string" => $matches['before'])));
						if (!empty($matches['after']))
							$afterArr = array_merge($afterArr,array(array("string" => $matches['after'])));
						$str_parts = array_merge($beforeArr,array(array("field" => "{$column['REFERENCED_TABLE_NAME']}.{$matches['fk_col']}")),$afterArr);
						$joins[$column['COLUMN_NAME']] = array('table' => $column['REFERENCED_TABLE_NAME'], 'column' => $column['REFERENCED_COLUMN_NAME']);
					}

				}
			}
		}
		$condition = "CONCAT(";
		$params = array();
		foreach($str_parts as $part) {
			if (array_key_exists('string',$part)) {
				$condition .= "?,";
				array_push($params,array(
					"type" => "s", "value" => $part['string']
				));
			}
			else {
				$condition .= "{$part['field']},";
			}
		}
		$condition = substr($condition,0,-1);
		$condition .= ")";
		return array('concat' => $condition, 'params' => $params, 'joins' => $joins);
	}

	/* Outputs available tables for viewing... */
	protected static function view_tables() {
		global $db,$local;
		$output = array('html' => '', 'script' => array(), 'css' => array());
		$user = users::get_session_user();
		$db_name = $db->get_db_name();
		$query = "
			SELECT T.TABLE_NAME, IFNULL(I.SLUG,T.TABLE_NAME) as SLUG
			FROM INFORMATION_SCHEMA.TABLES T
			LEFT JOIN _TABLE_INFO I ON T.TABLE_NAME = I.TABLE_NAME
			WHERE TABLE_SCHEMA = ?";
			$params = array(
				array("type" => "s", "value" => $db_name)
			);
			$tables = $db->run_query($query,$params);
			$tables_no_rights = array();
			$output['html'] = "<p>Available Tables:</p>
			<ol>";
			foreach($tables as $t) {
				/* Only display table if they have the right to view it... */
				if ($user->check_right('Tables',$t['TABLE_NAME'],'View'))
					$output['html'] .= "<li><a href='".static::get_module_url()."{$t['SLUG']}'>{$t['TABLE_NAME']}</a></li>";
				elseif (users::get_right_id('Tables',$t['TABLE_NAME'],'View')===false)
					$tables_no_rights[] = $t['TABLE_NAME'];

			}
			$output['html'] .= "</ol>";
			if (!empty($tables_no_rights)) {
				$query = "SELECT ID, NAME FROM _GROUPS";
				$groups = group_numeric_by_key($db->run_query($query),'ID');

				array_push(
					$output['script'],
					"{$local}script/jquery.min.js",
					"{$local}script/jquery-ui.min.js",
					get_public_location(__DIR__ . '/js/create-table-rights.js'),
					"var groups = " . json_encode($groups) . ";"
					);
				$output['css'][] = "{$local}style/jquery-ui.css";
				$output['html'] .= "<p>The following tables do not have any rights associated with them.  Please assign them accordingly:</p><ul class='assign-rights'>";
				foreach ($tables_no_rights as $t) {
					$output['html'] .= "<li><button value='$t'>$t</button></li>";
				}
				$output['html'] .= "</ul>";
			}

			return $output;
	}

	public static function build_foreign_key_link($val,$row,$column)
	{
		global $db, $s_user;

		$id = $val;

		/* Get Foreign Key SHORT Display */
		$sql = static::sql_decode_display($column['REFERENCED_TABLE_NAME']);
		$joins = "";
		if (!empty($sql['joins'])) {
			foreach($sql['joins'] as $col => $table_join) {
				$joins .= "LEFT JOIN {$table_join['table']} ON {$column['REFERENCED_TABLE_NAME']}.{$col} = {$table_join['table']}.{$table_join['column']} ";
			}
		}
		$query = "SELECT {$sql['concat']} as display FROM {$column['REFERENCED_TABLE_NAME']} $joins WHERE {$column['REFERENCED_TABLE_NAME']}.{$column['REFERENCED_COLUMN_NAME']} = ?";
		$params = $sql['params'];
		array_push($params,array("type" => "s", "value" => $val));
		$result = $db->run_query($query,$params);
		if (!empty($result)) $val = $result[0]['display'];

		if ($s_user->check_right('Tables',$column['REFERENCED_TABLE_NAME'],'Edit'))
		{
			$val = "<a href='".static::get_module_url()."{$column['REFERENCED_TABLE_NAME']}/$id'>$val</a>";
		}

		return $val;
	}

	public static function shorten_text_fields($val) {
		$val = preg_split('/\s/',strip_tags($val), null, PREG_SPLIT_NO_EMPTY);
		return implode(' ',array_slice($val,0,50));
	}


	protected static function view_table($table) {
		global $local, $db;
		$output = array('html' => '', 'script' => array(), 'css' => array());
		$user = users::get_session_user();
		if (!$user->check_right('Tables',$table,'View')) {
			header("Location: ".static::get_module_url()."");
			exit();
			return;
		}

		$query = "SELECT *
			FROM _TABLE_INFO
			WHERE TABLE_NAME = ?";
		$params = array(
			array("type" => "s", "value" => $table)
		);
		$table_info = $db->run_query($query,$params);

		$query = "SELECT *
			FROM $table";

		if (!empty($table_info)) {
			$table_info = $table_info[0];
			$table_info['SLUG'] = is_null($table_info['SLUG']) ? $table : $table_info['SLUG'];
			if ($table_info['SLUG'] > "") $table_info['SLUG'] .= "/";
		}
		else {
			$table_info['SLUG'] = "$table/";
		}
		if (empty($table_info['PREVIEW_DISPLAY'])) {
			$paging_table = pagingtable_widget::load_paging($query,array());
			if ($paging_table === false)
				$paging_table = new pagingtable_widget($query,array());
			$paging_table->set_per_page(empty($table_info['ROW_DISPLAY_MAX']) ? 10 : $table_info['ROW_DISPLAY_MAX']);
			$columns = array();
			$table_columns = static::get_table_columns($table);
			foreach ($table_columns as $column) {
				$column_info = array(
					'column' => $column['COLUMN_NAME'],
					'display_name' => $column['COLUMN_NAME']
				);
				if (!empty($column['REFERENCED_TABLE_NAME']) && !empty($column['REFERENCED_COLUMN_NAME'])) {
					$column_info['display_function'] = array(__CLASS__,'build_foreign_key_link');
					$column_info['display_function_add_params'] = array($column);
				} elseif ($column['DATA_TYPE'] == 'text') {
					$column_info['display_function'] = array(__CLASS__,'shorten_text_fields');
					$column_info['nowrap'] = false;
				}

				$columns[] = $column_info;

			}

			$paging_table->set_columns($columns);
			$paging_table_output = $paging_table->build_table();
			foreach($paging_table_output as $type => $paging_output) {
				if ($type == 'html')
					$output['html'] .= $paging_output;
				else {
					if (!isset($output[$type])) $output[$type] = array();
					$output[$type] = array_merge($output[$type],$paging_output);
				}
			}


		} else {
			$paging = new paging($query);
			$paging->set_per_page(empty($table_info['ROW_DISPLAY_MAX']) ? 10 : $table_info['ROW_DISPLAY_MAX']);
			if (isset($_REQUEST['page'])) {
				$paging->goto_page($_REQUEST['page']);
			}
			$data = $paging->get_current_page();

			$output['html'] .= $table_info['PREVIEW_DISPLAY_BEFORE'];
			foreach($data as $row) {
				$common = array();
				$short = replace_formatted_string($table_info['SHORT_DISPLAY'],"{","}",$row);
				$link = make_url_safe($short);
				$common['link'] = "<a href='".static::get_module_url()."{$table_info['SLUG']}$link'>$short</a>";
				$display = $table_info['PREVIEW_DISPLAY'];
				$display = replace_formatted_string($display,"{","}",$row);
				$display = replace_formatted_string($display,"%","%",$common);

				/* Look for any {FKID_FIELD} or % FKID_HREF %*/
				$columns = static::get_table_columns($table);
				foreach($columns as $column) {
					if (empty($column['REFERENCED_TABLE_NAME'])) continue;
					$regex = "/\{({$column['COLUMN_NAME']})(\_)(\w+)\}/";
					preg_match_all($regex,$display,$matches);
					if (!empty($matches)) {
						$FK_fields = static::get_table_columns($column['REFERENCED_TABLE_NAME']);
						$fields = $matches[3];
						foreach($fields as $idx=>&$field) {
							/* confirm $field is legitimate field... */
							$legit = false;
							foreach($FK_fields as $idx2=>$fkf) {
								if ($fkf['COLUMN_NAME'] == $field) {
									$legit = true;
									unset($FK_fields[$idx2]);
									break;
								}
							}
							if (!$legit) unset($fields[$idx]);
							$field = "{$column['REFERENCED_TABLE_NAME']}.$field as {$column['COLUMN_NAME']}_$field";
						}
						$params = array();
						if (preg_match("/%{$column['COLUMN_NAME']}_HREF%/", $display)) {
							$concat = static::sql_decode_display($column['REFERENCED_TABLE_NAME'],SHORT_DISPLAY);
							$joins = "";
							if (!empty($concat['joins'])) {
								foreach($concat['joins'] as $col => $table_join) {
									$joins .= "LEFT JOIN {$table_join['table']} ON {$column['REFERENCED_TABLE_NAME']}.{$col} = {$table_join['table']}.{$table_join['column']} ";
								}
							}
							$fields[] = "{$concat['concat']} as HREF";
							$params = $concat['params'];
						} else $joins = "";

						$query = "SELECT " . implode(",",$fields) . "
						FROM {$column['REFERENCED_TABLE_NAME']}
						$joins
						WHERE {$column['REFERENCED_TABLE_NAME']}.{$column['REFERENCED_COLUMN_NAME']} = ?";
						array_push($params,
							array("type" => "s", "value" => $row[$column['COLUMN_NAME']])
						);

						Database::param_type_check($column['DATA_TYPE'],$params[0]['type']);
						$FK_data = $db->run_query($query,$params);
						if (!empty($FK_data)) {
							$FK_data = $FK_data[0];
							$fklink = "";
							if (!empty($FK_data['HREF'])) {
								$fklink = "".static::get_module_url()."{$column['REFERENCED_TABLE_NAME']}/" . make_url_safe($FK_data['HREF'],ENT_QUOTES) ;
								unset($FK_data['HREF']);
							}
							$display = replace_formatted_string($display,"{","}",$FK_data);
							$display = str_replace("%{$column['COLUMN_NAME']}_HREF%",$fklink,$display);
						}

					}
				}
				$output['html'] .= $display;
			}
			$output['html'] .= $table_info['PREVIEW_DISPLAY_AFTER'];

			if ($paging->get_page_count() > 1)
			{
				$output['html'] .= "<p id='page-navigation'>";
				$current_page = $paging->get_page_number();
				if ($current_page > 1) {
					$page = $current_page - 1;
					$output['html'] .= "<a href='".static::get_module_url()."{$table_info['SLUG']}?page={$page}'>Previous</a>";
				}
				if ($current_page < $paging->get_page_count()) {
					$page = $current_page + 1;
					$output['html'] .= "<a href='".static::get_module_url()."{$table_info['SLUG']}?page={$page}'>Next</a>";
				}
				$output['html'] .= "</p>";
				array_push($output['css'],get_public_location(__DIR__."/style/paging-navigation.css"));
			}
		}
		$output['html'] .= "<p><a href='".static::get_module_url()."'>Return to Table Listing...</a></p>";
		return $output;
	}

	protected static function view_table_record($table,$id) {
		global $local,$db;
		$output = array('html' => '', 'script' => array());
		$user = users::get_session_user();
		/* First we need to figure out the WHERE clause... */
		$condition = static::sql_decode_display($table,SHORT_DISPLAY);
		$joins = "";
		if (!empty($condition['joins'])) {
			foreach($condition['joins'] as $col => $table_join) {
				$joins .= "LEFT JOIN {$table_join['table']} ON {$table}.{$col} = {$table_join['table']}.{$table_join['column']} ";
			}
		}
		$params = array_merge($condition['params'],array(array("type" => "s", "value" => decode_url_safe($id))));

		$query = "SELECT {$table}.* FROM $table $joins WHERE {$condition['concat']} RLIKE ?";
		$data = $db->run_query($query,$params);
		if (!empty($data)) $data = $data[0];
		elseif (empty($data) && empty($id)) return static::view_table($table);

		/* Pick up rights checks here!!!!! */
		if (!empty($action) && !$user->check_right('Tables',$table,ucfirst($action)))  {
			header("Location: ".static::get_module_url()."$table/$id");
			exit();
			return;
		} elseif (empty($action) && !$user->check_right('Tables',$table,'View')) {
			header("Location: ".static::get_module_url());
			exit();
			return;
		}

		$query = "
			SELECT SLUG,FULL_DISPLAY, IFNULL(LINK_BACK_TO_TABLE,1) AS LINK_BACK_TO_TABLE
			FROM _TABLE_INFO
			WHERE TABLE_NAME = ?";
		$params = array(array("type" => "s", "value" => $table));
		$display = $db->run_query($query,$params);
		if (empty($display) || empty($display[0]['FULL_DISPLAY'])) {
			$display['LINK_BACK_TO_TABLE'] = empty($display) ? false : $display[0]['LINK_BACK_TO_TABLE'];
			$output['html'] .= "
			<table>
				<tbody>";
			foreach($data as $column=>$value) {
				$output['html'] .= "
					<tr>
						<td>$column</td>
						<td>$value</td>
					</tr>";
			}
			$output['html'] .= "
				</tbody>
			</table>";
		} else {
			$display = $display[0];
			$display['FULL_DISPLAY'] = replace_formatted_string($display['FULL_DISPLAY'],"{","}",$data);
			$columns = static::get_table_columns($table);
			foreach($columns as $column) {
				if ($column['REFERENCED_TABLE_NAME']) {
					if (preg_match_all("/{{$column['COLUMN_NAME']}_(?<REFERENCED_COLUMN>(.*))}/",$display['FULL_DISPLAY'],$matches)) {
						//confirm it IS a field...
						foreach($matches['REFERENCED_COLUMN'] as $referenced_column) {
							if (static::table_has_column($column['REFERENCED_TABLE_NAME'],$referenced_column)) {
								$query = "
									SELECT {$referenced_column}
									FROM {$column['REFERENCED_TABLE_NAME']}
									WHERE {$column['REFERENCED_COLUMN_NAME']} = ?
								";
								$params = array(
									array('type' => 'i', 'value' => $data[$column['COLUMN_NAME']])
								);
								$result = $db->run_query($query,$params);
								$display['FULL_DISPLAY'] = preg_replace("/{{$column['COLUMN_NAME']}_{$referenced_column}}/",$result[0][$referenced_column],$display['FULL_DISPLAY']);
							}
						}
					}

					if (preg_match("/%{$column['COLUMN_NAME']}_HREF%/",$display['FULL_DISPLAY'])) {
						//replace with hyperlink to that record...
						$href = static::get_module_url() ;
						$query = "
							SELECT SLUG as table_slug, SHORT_DISPLAY as record_slug
							FROM _TABLE_INFO
							WHERE TABLE_NAME = ?
						";
						$params = array(
							array("type" => "s", "value" => $column['REFERENCED_TABLE_NAME'])
						);
						$result = $db->run_query($query,$params);
						if (empty($result)) {
							// no slug, short display - use table name and primary key
							$href .= "{$column['REFERENCED_TABLE_NAME']}/{$data[$column['COLUMN_NAME']]}";
						} else {
							extract($result[0]);
							if (is_null($table_slug))
								$table_slug = $column['REFERENCED_TABLE_NAME'];
							$href .= $table_slug;
							if (!empty($record_slug))
							{
								$reference_columns = static::get_table_columns($column['REFERENCED_TABLE_NAME']);
								$colsToGrab = array();
								foreach($reference_columns as $ref_column)
								{
									if (preg_match("/{{$ref_column['COLUMN_NAME']}}/",$record_slug))
										$colsToGrab[] = $ref_column['COLUMN_NAME'];
								}
								if (!empty($colsToGrab)) {
									$query = "
										SELECT ".implode(",",$colsToGrab)."
										FROM {$column['REFERENCED_TABLE_NAME']}
										WHERE {$column['REFERENCED_COLUMN_NAME']} = ?
									";
									$params = array(
										array("type" => "i", "value" => $data[$column['COLUMN_NAME']])
									);
									$ref_data = $db->run_query($query,$params);
									$record_slug = make_url_safe(replace_formatted_string($record_slug,"{","}",$ref_data[0]));
								}
							} else {
								$record_slug = make_html_safe($data[$column['COLUMN_NAME']]);
							}
							$href .= "/$record_slug";
							$display['FULL_DISPLAY'] = preg_replace("/%{$column['COLUMN_NAME']}_HREF%/",$href,$display['FULL_DISPLAY']);
						}

					}
				}
			}
			$output['html'] .= $display['FULL_DISPLAY'];
		}
		if ($display['LINK_BACK_TO_TABLE']) {
			if (array_key_exists('SLUG',$display) && !is_null($display['SLUG'])) {
				$slug = empty($display['SLUG']) ? '' : $display['SLUG'];
			} else {
				$slug = $table;
			}
			$output['html'] .= "<p><a href='".static::get_module_url().make_url_safe($slug)."'>Return to $table listing...</a></p>";
		}
		/* Now check for any Table METAs */
		$query = "
			SELECT *
			FROM _TABLE_METAS
			WHERE TABLE_NAME = ?";
		$params =array(
			array("type" => "s", "value" => $table)
		);
		$metas = $db->run_query($query,$params);
		if (!empty($metas)) {
			$output['meta'] = array();
			foreach($metas as $meta)
				$output['meta'][$meta['META_NAME']] = replace_formatted_string($meta['META_CONTENT'],"{","}",$data);
		}
		return $output;
	}

	protected static function decode_display($display,$columns,$data) {
		global $db;
		$display = replace_formatted_string($display,"{","}",$data);
		foreach($columns as $column) {
			if ($column['REFERENCED_TABLE_NAME']) {
				/* Check for {FKID_<FIELD>}*/
				if (preg_match_all("/{{$column['COLUMN_NAME']}_(?<REFERENCED_COLUMN>([\w\d]+))}/",$display,$matches)) {
					//confirm it IS a field...
					foreach($matches['REFERENCED_COLUMN'] as $referenced_column) {
						if (static::table_has_column($column['REFERENCED_TABLE_NAME'],$referenced_column)) {
							$query = "
								SELECT {$referenced_column}
								FROM {$column['REFERENCED_TABLE_NAME']}
								WHERE {$column['REFERENCED_COLUMN_NAME']} = ?
							";
							$params = array(
								array('type' => 'i', 'value' => $data[$column['COLUMN_NAME']])
							);
							$result = $db->run_query($query,$params);
							$display = preg_replace("/{{$column['COLUMN_NAME']}_{$referenced_column}}/",$result[0][$referenced_column],$display);
						}
					}
				}
				/* Check for %FKID_HREF% */
				if (preg_match("/%{$column['COLUMN_NAME']}_HREF%/",$display)) {
					//replace with hyperlink to that record...
					$href = static::get_module_url() ;
					$query = "
						SELECT SLUG as table_slug, SHORT_DISPLAY as record_slug
						FROM _TABLE_INFO
						WHERE TABLE_NAME = ?
					";
					$params = array(
						array("type" => "s", "value" => $column['REFERENCED_TABLE_NAME'])
					);
					$result = $db->run_query($query,$params);
					if (empty($result)) {
						// no slug, short display - use table name and primary key
						$href .= "{$column['REFERENCED_TABLE_NAME']}/{$data[$column['COLUMN_NAME']]}";
					} else {
						extract($result[0]);
						if (is_null($table_slug))
							$table_slug = $column['REFERENCED_TABLE_NAME'];
						$href .= $table_slug;
						if (!empty($record_slug))
						{
							$reference_columns = static::get_table_columns($column['REFERENCED_TABLE_NAME']);
							$colsToGrab = array();
							foreach($reference_columns as $ref_column)
							{
								if (preg_match("/{{$ref_column['COLUMN_NAME']}}/",$record_slug))
									$colsToGrab[] = $ref_column['COLUMN_NAME'];
							}
							if (!empty($colsToGrab)) {
								$query = "
									SELECT ".implode(",",$colsToGrab)."
									FROM {$column['REFERENCED_TABLE_NAME']}
									WHERE {$column['REFERENCED_COLUMN_NAME']} = ?
								";
								$params = array(
									array("type" => "i", "value" => $data[$column['COLUMN_NAME']])
								);
								$ref_data = $db->run_query($query,$params);
								$record_slug = make_url_safe(replace_formatted_string($record_slug,"{","}",$ref_data[0]));
							}
						} else {
							$record_slug = make_html_safe($data[$column['COLUMN_NAME']]);
						}
						$href .= "/$record_slug";
						$display = preg_replace("/%{$column['COLUMN_NAME']}_HREF%/",$href,$display);
					}

				}
			}
		}
		return $display;
	}

	public static function ajax($args,$request) {
		switch($request['ajax']) {
			case 'Assign Rights':
				if (empty($request['table'])) return array('success' => 0);
				foreach(array('Add','Edit','Delete','View') as $right) {
					/* Create the given right for the table...*/
					$r = users::create_right('Tables',$request['table'],$right, "Allows user to $right the the {$request['table']} table.");
					/* Assign the created right to the appropriate groups... */
					users::assign_rights(array($r => $request["{$right}_groups"]));
				}
				return array('success' => 1);
		}
	}

	public static function post($args,$post) {
		/* Just a placeholder, nothing can be posted from the public side...*/
	}

	public static function view($table='', $id='') {
		global $db,$local;
		$db_name = $db->get_db_name();
		$output = array(
			'html' => '',
			'title' => 'Table Management',
			'script' => array(),
		);
		$user = users::get_session_user();
		/* First confirm $table is in fact a table... */
		if (!empty($table)) {
			$query = "
				SELECT T.TABLE_NAME
				FROM _TABLE_INFO I
				RIGHT JOIN INFORMATION_SCHEMA.TABLES T ON I.TABLE_NAME = T.TABLE_NAME
				WHERE IFNULL(I.SLUG,T.TABLE_NAME) = ? AND T.TABLE_SCHEMA = ?";
			$params = array(
				array("type" => "s", "value" => $table),
				array("type" => "s", "value" => $db_name)

			);
			$result = $db->run_query($query,$params);
			if (empty($result)) {
				list($table,$id) = array('',$table);
			} else {
				$table = $result[0]['TABLE_NAME'];
			}
		}
		if (empty($table)) {
			$query = "
				SELECT T.TABLE_NAME
				FROM _TABLE_INFO I
				JOIN INFORMATION_SCHEMA.TABLES T ON I.TABLE_NAME = T.TABLE_NAME
				WHERE I.SLUG = '' AND T.TABLE_SCHEMA = ?";
			$params = array(
				array("type" => "s", "value" => $db_name)
			);
			$result = $db->run_query($query,$params);
			if (empty($result)) {
				/* No table fits this... */
				if (!empty($id)) {
					header("Location: " . tables::get_module_url());
					exit();
					return;
				}
			} else {
				$table = $result[0]['TABLE_NAME'];
			}
		}

		if (!empty($table)) {
			return static::view_table_record($table,$id);
		} else {
			return static::view_tables();
		}
	}
}
?>
