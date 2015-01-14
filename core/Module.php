<?php
class module {
	/* Basic Contructor */
	public function __construct() {
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
		$query = "INSERT INTO _MODULES (NAME,DESCRIPTION,IS_CORE,FILENAME,CLASS_NAME) VALUES (?,?,?,?,?)";
		$params = array(
			array("type" => "s", "value" => $info['Module']),
			array("type" => "s", "value" => $info['Description']),
			array("type" => "s", "value" => $info['Core']),
			array("type" => "s", "value" => $info['Filename']),
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
		
		require_once($info['Filename']);
		
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
							$groups = group_numeric_by_key($db->run_query($query,$params),'ID');
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
	
	/* What needs to be done in order to install the module*/
	public static function install() {
		global $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _MODULES (
				ID int AUTO_INCREMENT,
				NAME varchar(30),
				DESCRIPTION varchar(255),
				IS_CORE bit,
				FILENAME varchar(200),
				CLASS_NAME varchar(100),
				SLUG varchar(50) UNIQUE,
				PRIMARY KEY (ID)
			);";
		$db->run_query($query);
		$query = "
			CREATE TABLE IF NOT EXISTS _MODULES_DEPENDENCIES (
				ID int AUTO_INCREMENT,
				MODULE_ID int,
				REQUIRED_MODULE_ID int,
				PRIMARY KEY (ID),
				FOREIGN KEY (MODULE_ID) REFERENCES _MODULES(ID) ON DELETE CASCADE,
				FOREIGN KEY (REQUIRED_MODULE_ID) REFERENCES _MODULES(ID)
			)";
		$db->run_query($query);
		
		$query = "CREATE TABLE IF NOT EXISTS _WIDGETS (
			ID int AUTO_INCREMENT,
			MODULE_ID int,
			NAME varchar(200),
			FILENAME varchar(200),
			CLASS_NAME varchar(100),
			PRIMARY KEY (ID),
			FOREIGN KEY (MODULE_ID) REFERENCES _MODULES(ID)
		);";
		$db->run_query($query);
		return true;
		
	}
	/* What needs to be done in order to uninstall the module */
	public static function uninstall() {return false;}
	
	/* Returns list of Rights required by Module */
	public static function required_rights() { return array();}
	
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
		if (!empty($module)) return $module[0];
		if (!empty($slug)) array_unshift($args,$slug);
		$params = array(
			array("type" => "s", "value" => ""),
			array("type" => "s", "value" => "")
		);
		$module = $db->run_query($query,$params);
		return (empty($module) ? false : $module[0] );
	}
	
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
	
	public static function widget($id,$params=array()) {
		/* Determines what exactly is widget $id, then attempts to run it. */
		global $db;
		$query = "SELECT W.CLASS_NAME
		FROM _WIDGETS W
		WHERE W.ID = ?";
		$param = array(
			array("type" => "i", "value" => $id)
		);
		$widget = $db->run_query($query,$param);
		$widget = $widget[0];
		return call_user_func_array(array($widget['CLASS_NAME'],'view'),array($params));
	}
}
?>
