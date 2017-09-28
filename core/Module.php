<?php
class module {
	/* Basic Contructor */
	public function __construct($id=false) {
		if ($id) {
			$this->init($id);
		}
	}

	private function init($id) {
		global $db;
		$query = "
			SELECT *
			FROM _MODULES M
			WHERE M.ID = ?
		";
		$params = array(
			array("type" => "i", "value" => $id)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) return false;
		else {
			$module_data = $result[0];
			foreach($module_data as $var=>$val)
			{
				$this->$var = $val;
			}
			return true;
		}
	}

	public function get_module_extensions() {
		global $db;
		$query = "
			SELECT *
			FROM _MODULES M
			WHERE PARENT_MODULE_ID = ?
		";
		$params = array(
				array("type" => "i", "value" => $this->ID)
		);
		return $db->run_query($query,$params);
	}

	public static function get_module_by_name($module) {
		$id = static::get_module_id($module);
		if ($id) return new module($id);
		else return false;
	}


	/* Returns a help string */
	public static function help() {
		return "<p>This help text is useless right now.  It should be properly set by writing a 'help' function within ".get_called_class()."'s class.</p>";
	}

	/* To handle what happens behind closed doors... */
	public static function admin_post() {
		return false;
	}

	/* Admin function for...Admin-ing*/
	public static function admin() {
		return array(
			'html' => "An admin function hasn't been set up yet for this module."
		);
	}
	/* Post function for any non-ajax POST requests */
	public static function post() {
		return false;
	}
	/* ajax function for any AJAX requests */
	public static function ajax() {
		return false;
	}

	/* First step to install a module... */
	public static function install_module($info) {
		global $db;
		/* First confirm module hasn't already been installed... */
		$query = "SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS is_installed FROM _MODULES WHERE NAME = ?";
		$params = array(
			array("type" => "s", "value" => $info['Module'])
		);
		$result = $db->run_query($query,$params);
		if ($result[0]['is_installed']) return false;
		/* Save the Module record... */
		$query = "INSERT INTO _MODULES (NAME,PARENT_MODULE_ID,DESCRIPTION,IS_CORE,FILENAME,CLASS_NAME) VALUES (?,?,?,?,?,?)";
		$params = array(
			array("type" => "s", "value" => $info['Module']),
			array("type" => "s", "value" => isset($info['ModuleParentID']) ? $info['ModuleParentID'] : NULL),
			array("type" => "s", "value" => $info['Description']),
			array("type" => "s", "value" => $info['Core']),
			array("type" => "s", "value" => $info['Directory'] . $info['Filename']),
			array("type" => "s", "value" => $info['Class'])
		);
		$db->run_query($query,$params);
		$mod_id = $db->get_inserted_id();
		/* Save any dependencies... */
		if (!empty($info['Requires'])) {
			$query = "
				INSERT INTO _MODULES_DEPENDENCIES (MODULE_ID,REQUIRED_MODULE_ID)
				SELECT ?, M.ID
				FROM _MODULES M
				WHERE NAME IN (".substr(str_repeat("?,",count($info['Requires'])),0,-1).")";
			$params = array(
				array("type" => "i", "value" => $mod_id)
			);
			foreach($info['Requires'] as $required)
				array_push($params,array("type" => "s", "value" => $required));
			$db->run_query($query,$params);
		}
		if (!empty($info['Helpers'])) {
			$query = "
				INSERT INTO _MODULES_HELPERS (MODULE_ID,HELPER_ID,FILENAME,CLASS_NAME)
				SELECT ?,H.ID,?,?
				FROM _HELPERS H
				WHERE TYPE = ?";
			foreach($info['Helpers'] as $type=>$helper) {
				/* Save Helper... */
				$params = array(
					array("type" => "i", "value" => $mod_id),
					array("type" => "s", "value" => $info['Directory'] . $helper['File']),
					array("type" => "s", "value" => $helper['Class']),
					array("type" => "s", "value" => $type)
				);
				$db->run_query($query,$params);
			}
		}

		require_once($info['Directory'].$info['Filename']);

		/* Get all necessary tables... */
		call_user_func_array(array($info['Class'],'install_required_tables'),array());

		/* Run any commands specific for this module's installation... */
		call_user_func_array(array($info['Class'],'install'),array());

		/* Install any rights and auto-assign them as needed... */
		$required_rights = call_user_func_array(array($info['Class'],'required_rights'),array());
		if (!empty($required_rights)) {
			foreach($required_rights as $module=>$types) {
				foreach($types as $type=>$rights) {
					foreach($rights as $right=>$right_info) {
						/* If right exists, do nothing... */
						if (users::get_right_id($module,$type,$right)!==false) continue;
						/* Create right and assign to $info['default_groups'] */
						$new_right = users::create_right($module,$type,$right,$right_info['description'],true);
						if (!empty($right_info['default_groups'])) {
							$query = "SELECT ID FROM _GROUPS WHERE NAME IN (".substr(str_repeat("?,",count($right_info['default_groups'])),0,-1).")";
							$params = array();
							foreach($right_info['default_groups'] as $group)
								array_push($params,array("type" => "s", "value" => $group));
							$groups = utilities::group_numeric_by_key($db->run_query($query,$params),'ID');
							users::assign_rights(array($new_right => $groups),true);
						}
					}
				}
			}
			/* Install again (Yes, its very slow doing it this way, unfortunately Users and base module have some conflicts and this is the best solution I can come up with for now)*/
			call_user_func_array(array($info['Class'],'install'),array());
		}

		return true;
	}

	protected static function install_required_tables() {
		global $db;
		$required_tables = static::required_tables();
		foreach($required_tables as $table => $table_definition) {
			// check if $table exists...
			if ($db->table_exists($table)) {
				// confirm table columns
				foreach($table_definition['columns'] as $column_name => $column_definition) {
					if (!$db->table_column_exists($table, $column_name)) {
						// add column
						$db->add_table_column($table, $column_name, $column_definition);
					}
				}

				// confirm table keys
				$all_keys = utilities::group_numeric_by_keys($db->table_keys($table), array('COLS','REFERENCED_TABLE_NAME','INDEXNAME'));

				foreach($table_definition['keys'] as $key_type => $keys) {
					if ($key_type == 'PRIMARY') {
						// confirm primary key via $all_keys[$keys]['']['PRIMARY']
						$pk = implode(",",$keys);
						if (!isset($all_keys[$pk]) || !isset($all_keys[$pk]['']) || !isset($all_keys[$pk]['']['PRIMARY'])) {
							$db->add_table_key($table,'PRIMARY',$keys);
						}
					} elseif ($key_type == 'FOREIGN') {
						// confirm each FK is in place...
						foreach($keys as $key_column => $table_reference) {
							if (!isset($all_keys[$key_column]) || !isset($all_keys[$key_column][$table_reference['table']])) {
								$db->add_table_key($table, $key_type, array($key_column),$table_reference);
							}

						}
					} else {
						// confirm every other key (unique or otherwise) is in place...
						$non_unique = $key_type !== 'UNIQUE';
						foreach($keys as $columns) {
							$key_found = false;
							$key_string = implode(",",$columns);
							if (!isset($all_keys[$key_string]) || !isset($all_keys[$key_string][''])) {
								$db->add_table_key($table, $key_type, $columns);
							} else {
								// need to go through each existing key with this column, make sure it matches up (e.g., is UNIQUE constraint present)
								foreach($all_keys[$key_string][''] as $key_data) {
									if ($key_data['NON_UNIQUE'] == $non_unique) {
										$key_found = true;
										break;
									}
								}
							}
							if (!$key_found) {
								$db->add_table_key($table, $key_type, $columns);
							}
						}
					}
				}
			} else {
				// install table...
				$db->create_table($table,$table_definition['columns'],$table_definition['keys']);
			}
		}
	}

	/* What needs to be done in order to install the module*/
	public static function install() {
		global $db;

		self::install_required_tables();
		// after $this->required_tables() installed...
		$query = "INSERT INTO _HELPERS (TYPE,METHOD_NAME,FALLBACK_METHOD) VALUES ('admin','view','admin'),('post','post','post'),('ajax','view','ajax')";
		$db->run_query($query);
		return true;

	}
	/* What needs to be done in order to uninstall the module */
	public static function uninstall() {return false;}

	/* Returns list of Rights required by Module */
	public static function required_rights() { return array();}

	/* Returns a list of Tables required by Module, along with their column definitions */
	public static function required_tables() {
		return array(
			'_MODULES' => array(
				'columns' => array(
					'ID' => 'int AUTO_INCREMENT',
					'NAME' => 'varchar(30)',
					'PARENT_MODULE_ID' => 'int',
					'DESCRIPTION' => 'varchar(255)',
					'IS_CORE' => 'bit',
					'FILENAME' => 'varchar(200)',
					'CLASS_NAME' => 'varchar(100)',
					'SLUG' => 'varchar(50)',
				),
				'keys' => array(
					'PRIMARY' => array('ID'),
					'FOREIGN' => array(
						'PARENT_MODULE_ID' => array('table' => '_MODULES','column' => 'ID'),
					),
					'UNIQUE' => array(
						array('SLUG'),
						array('NAME')
					),
				),

			),
			'_MODULE_SETTINGS' => array(
				'columns' => array(
					'MODULE_ID' => 'int',
					'SETTING' => 'varchar(64)',
					'VALUE' => 'varchar(256)',
				),
				'keys' => array(
					'PRIMARY' => array('MODULE_ID','SETTING'),
					'FOREIGN' => array(
						'MODULE_ID' => array('table' => '_MODULES','column' => 'ID'),
					)
				)
			),
			'_MODULES_DEPENDENCIES' => array(
				'columns' => array(
					'ID' => 'int AUTO_INCREMENT',
					'MODULE_ID' => 'int',
					'REQUIRED_MODULE_ID' => 'int'
				),
				'keys' => array(
					'PRIMARY' => array('ID'),
					'FOREIGN' => array(
						'MODULE_ID' => array('table' => '_MODULES', 'column' => 'ID', 'delete' => 'CASCADE'),
						'REQUIRED_MODULE_ID' => array('table' => '_MODULES', 'column' => 'ID'),
					)
				)
			),
			'_HELPERS' => array(
				'columns' => array(
					'ID' => 'int AUTO_INCREMENT',
					'TYPE' => 'varchar(32)',
					'METHOD_NAME' => 'varchar(32)',
					'FALLBACK_METHOD' => 'varchar(32)',
				),
				'keys' => array(
					'PRIMARY' => array('ID'),
				)

			),
			'_MODULES_HELPERS' => array(
				'columns' => array(
					'MODULE_ID' => 'int',
					'HELPER_ID' => 'int',
					'FILENAME' => 'varchar(200)',
					'CLASS_NAME' => 'varchar(100)',
				),
				'keys' => array(
					'PRIMARY' => array('MODULE_ID','HELPER_ID'),
					'FOREIGN' => array(
						'MODULE_ID' => array('table' => '_MODULES','column' => 'ID'),
						'HELPER_ID' => array('table' => '_HELPERS','column' => 'ID')
					),
					'' => array(
						array('CLASS_NAME','FILENAME')
					)
				)
			),
			'_WIDGETS' => array(
				'columns' => array(
					'ID' => 'int AUTO_INCREMENT',
					'MODULE_ID' => 'int',
					'NAME' => 'varchar(200)',
					'FILENAME' => 'varchar(200)',
					'CLASS_NAME' => 'varchar(100)',
				),
				'keys' => array(
					'PRIMARY' => array('ID'),
					'FOREIGN' => array(
						'MODULE_ID' => array('table' => '_MODULES','column' => 'ID'),
					),
					'' => array(
						array('CLASS_NAME','FILENAME')
					)
				)
			)
		);
	}

	/* Returns a multi-dimensional array of menu options for this module (including sub-menus) */
	public static function menu() {
		/* Default is to return a single option - the empty array, representing the module's view method */
		return array(
			ucfirst(get_called_class()) => array(
				'args' => array(),
				'submenu' => array(),
				'right' => ''
			)
		);
	}

	/* Installs the menu automatically... */
	public static function install_menu($menu = null,$order=0,$parent=null) {
		global $db;
		if (is_null($menu)) $menu = call_user_func_array(array(get_called_class(),'menu'),array());
		foreach($menu as $text=>$item) {
			$query = "
				INSERT INTO _MENU (PARENT_ID,MODULE_ID,TEXT,RIGHT_ID,DISPLAY_ORDER)
				SELECT ?,M.ID, ?,?,?
				FROM _MODULES M
				WHERE M.CLASS_NAME = ?";
			$params = array(
				array("type" => "i", "value" => $parent),
				array("type" => "s", "value" => $text),
				array("type" => "i", "value" => $item['right']),
				array("type" => "i", "value" => $order++),
				array("type" => "s", "value" => get_called_class())
			);
			$db->run_query($query,$params);
			$menu_id = $db->get_inserted_id();

			/* If args, save those too */
			if (!empty($item['args'])) {
				$query = "INSERT INTO _MENU_ARGS (MENU_ID,ARG_NUMBER,ARG) VALUES (?,?,?)";
				$x = 0;
				foreach($item['args'] as $arg) {
					$params = array(
						array("type" => "i", "value" => $menu_id),
						array("type" => "i", "value" => ++$x),
						array("type" => "s", "value" => $arg)
					);
					$db->run_query($query,$params);
				}
			}
			if (!empty($item['submenu']))
				foreach($item['submenu'] as $t => $submenu)
					call_user_func_array(array(get_called_class(),'install_menu'),array(array($t => $submenu),0,$menu_id));

		}
	}

	/* given args[], returns a HREF to this menu item */
	public static function decode_menu($args = array()) {
		/* Default is unable to decode anything - so always return the HREF to the module's view method */
		return static::get_module_url();
	}

	public static function get_module_url() {
		global $local,$db;
		$query = "SELECT CONCAT(IFNULL(SLUG,NAME),
		CASE IFNULL(SLUG,NAME) WHEN '' THEN '' ELSE '/' END) as NAME
		FROM _MODULES M
		WHERE M.CLASS_NAME = ?";
		$params = array(
			array("type" => "s", "value" => get_called_class())
		);
		$module = $db->run_query($query,$params);
		return "{$local}{$module[0]['NAME']}";
	}

	/* returns helper classes used by a given module ID... If type specified, will only return helper information for that type */
	public static function get_module_helpers($module,$type = '') {
		global $db;

		if (empty($type)) {
			$helper_join = '1=1';
			$params = array();
		} else {
			$helper_join = 'TYPE = ?';
			$params = array(
				array("type" => "s", "value" => $type)
			);
		}

		$query = "
			SELECT H.TYPE,IFNULL(MH.CLASS_NAME,M.CLASS_NAME) as CLASS_NAME,
				CASE WHEN MH.MODULE_ID IS NULL THEN H.FALLBACK_METHOD ELSE H.METHOD_NAME END AS METHOD_NAME
			FROM _MODULES M
			JOIN _HELPERS H ON $helper_join
			LEFT JOIN _MODULES_HELPERS MH ON H.ID = MH.HELPER_ID AND M.ID = MH.MODULE_ID
			WHERE M.ID = ?";
		array_push($params,array("type" => "i", "value" => $module));
		return utilities::group_numeric_by_key($db->run_query($query,$params),'TYPE');
	}

	/* returns module id, given the name of the module */
	public static function get_module_id($module) {
		global $db;
		$query = "SELECT ID FROM _MODULES WHERE NAME = ?";
		$params = array(
			array("type" => "s", "value" => $module)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) return false;
		else return $result[0]['ID'];
	}
	/* returns information about the module called, using the args array as a reference*/
	public static function get_module(&$args) {
		global $db;
		$slug = empty($args) ? "" : array_shift($args);
		$query = "SELECT *, 1 as pref
			FROM _MODULES
			WHERE SLUG = ?
			UNION ALL
			SELECT *, 0 as pref
			FROM _MODULES
			WHERE SLUG IS NULL AND NAME = ?
			ORDER BY pref DESC LIMIT 1";
		$params = array(
			array("type" => "s", "value" => $slug),
			array("type" => "s", "value" => $slug)
		);
		$module = $db->run_query($query,$params);
		if (!empty($module)) {
			$module = $module[0];
			/* Get Helper Classes */
			unset($module['pref']);
			$module['helpers'] = static::get_module_helpers($module['ID']);

			return $module;
		}
		if (!empty($slug)) array_unshift($args,$slug);
		$params = array(
			array("type" => "s", "value" => ""),
			array("type" => "s", "value" => "")
		);
		$module = $db->run_query($query,$params);
		if (empty($module)) return false;
		$module = $module[0];
		unset($module['pref']);
		$module['helpers'] = static::get_module_helpers($module['ID']);
		return $module;
	}

	/* Returns the class used for a given module... */
	public static function get_module_class($module) {
		global $db;
		$query = "SELECT CLASS_NAME FROM _MODULES WHERE NAME = ?";
		$params = array(
			array("type" => "s", "value" => $module)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) return false;
		return $result[0]['CLASS_NAME'];
	}

	public static function get_all_module_settings($module) {
		global $db;
		$query = "SELECT SETTING,VALUE FROM _MODULE_SETTINGS WHERE MODULE_ID = ?";
		$params = array (
			array("type" => "i", "value" => static::get_module_id($module))
		);
		$settings = utilities::group_numeric_by_key($db->run_query($query,$params), 'SETTING');
		if (!empty($settings)) {
			foreach ($settings as &$setting)
				$setting = $setting['VALUE'];
			return $settings;
		}
		else return array();
	}

	public static function get_module_setting($module,$setting) {
		global $db;
		$query = "SELECT VALUE FROM _MODULE_SETTINGS WHERE MODULE_ID = ? AND SETTING = ?";
		$params = array(
			array("type" => "i", "value" => static::get_module_id($module)),
			array("type" => "s", "value" => $setting)
		);
		$result = $db->run_query($query,$params);
		if (!empty($result)) return $result[0]['VALUE'];
		else return null;
	}

	public static function set_module_setting($module,$setting,$value) {
		global $db;
		$module_id = static::get_module_id($module);
		$query = "
			INSERT INTO _MODULE_SETTINGS (MODULE_ID,SETTING,VALUE)
			VALUES (?,?,?)
			ON DUPLICATE KEY UPDATE VALUE=VALUES(VALUE)
		";
		$params = array(
			array("type" => "i", "value" => $module_id),
			array("type" => "s", "value" => $setting),
			array("type" => "s", "value" => $value)
		);
		$db->run_query($query,$params);
	}

	public static function widget($id,$ajax=false,$params=array()) {
		/* Determines what exactly is widget $id, then attempts to run it. If $ajax is true, run ajax method instead */
		global $db;
		$widget = static::get_widget($id);

		if (is_array($ajax)) {
			$params = $ajax;
			$ajax = false;
		}
		if (!$ajax)
			return call_user_func_array(array($widget['CLASS_NAME'],'view'),$params);
		else return call_user_func_array(array($widget['CLASS_NAME'],'ajax'),array($params));
	}

	public static function setup_widget($id)
	{
		/* Calls the setup() method for the given widget $id */
		global $db;
		$widget = static::get_widget($id);
		return call_user_func_array(array($widget['CLASS_NAME'],'setup'),array());
	}

	public static function get_widget($id)
	{
		global $db;
		$query = "
			SELECT *
			FROM _WIDGETS W
			WHERE W.ID = ?
		";
		$param = array(
			array("type" => "i", "value" => $id)
		);
		$widget = $db->run_query($query,$param);
		return ($widget) ? $widget[0] : false;
	}
}
?>
