<?php

class menu extends module {
	
	public function __construct() {
		parent::__construct();
	}
	
	public static function install() {
		/* There are 2 necessary tables - _MENU and _MENU_ARGS
		 * Along with 3 widgets - menu, centered menu, and vertical menu
		 */
		global $local, $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _MENU (
				ID int AUTO_INCREMENT,
				PARENT_ID int,
				MODULE_ID int,
				TEXT varchar(100),
				RIGHT_ID int,
				DISPLAY_ORDER int,
				PRIMARY KEY (ID),
				FOREIGN KEY (PARENT_ID) REFERENCES _MENU(ID),
				FOREIGN KEY (MODULE_ID) REFERENCES _MODULES(ID),
				FOREIGN KEY (RIGHT_ID) REFERENCES _RIGHTS(ID)
			)";
		$db->run_query($query);
		$query = "
			CREATE TABLE IF NOT EXISTS _MENU_ARGS (
				ID int AUTO_INCREMENT,
				MENU_ID int,
				ARG_NUMBER int,
				ARG varchar(50),
				PRIMARY KEY (ID),
				FOREIGN KEY (MENU_ID) REFERENCES _MENU(ID)
			 )";
		$db->run_query($query);
		$query = "
			INSERT INTO _MODULES (NAME, DESCRIPTION, IS_CORE, FILENAME, CLASS_NAME)
			SELECT tmp.NAME, tmp.DESCRIPTION, tmp.IS_CORE, ?, ?
			FROM (SELECT 'Menu' as NAME,'Handles the site menu' as DESCRIPTION,1 as IS_CORE) tmp
			LEFT JOIN _MODULES M ON	M.NAME = tmp.NAME
			WHERE M.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__)
		);
		$db->run_query($query,$params);
		
		/* Install Widgets*/
		require_once(__DIR__ . '/Menu.Widget.php');
		require_once(__DIR__ . '/VerticalMenu.Widget.php');
		require_once(__DIR__ . '/CenteredMenu.Widget.php');
		
		menu_widget::install();
		vertical_menu_widget::install();
		centered_menu_widget::install();

		return true;
	}
	
	private static function get_num_menus($menu) {
			/* recursively goes through menu to get the total number of menu records which will be needed in save_menu */
			$count = 0;
			foreach($menu as $item) {
					$count++;
					if (!empty($item['submenu'])) $count+= static::get_num_menus($item['submenu']);
			}
			
			return $count;
	}
	
	private static function save_sub_menu($menu,$IDs, $parent = null) {
			global $db;
			$count = 0;
			foreach($menu as $idx=>$item) {
				$id = array_shift($IDs);
				if (is_array($id)) $id = array_values($id)[0];
				$count++;
				if (empty($item['right'])) $item['right'] = null;
				if (empty($item['args'])) $item['args'] = array();
				$query = "UPDATE _MENU SET
					PARENT_ID = ?,
					MODULE_ID = ?,
					TEXT = ?,
					RIGHT_ID = ?,
					DISPLAY_ORDER = ?
					WHERE ID = ?";
				$params = array(
					array("type" => "i", "value" => $parent),
					array("type" => "i", "value" => $item['module']),
					array("type" => "s", "value" => $idx),
					array("type" => "i", "value" => $item['right']),
					array("type" => "i", "value" => $count),
					array("type" => "i", "value" => $id)
				);
				$db->run_query($query,$params);
				/* Delete any excess args... */
				$query = "DELETE FROM _MENU_ARGS WHERE MENU_ID = ? AND ARG_NUMBER > ?";
				$params = array(
					array("type" => "i", "value" => $id),
					array("type" => "i", "value" => count($item['args']))
				);
				$db->run_query($query,$params);
				/* Insert any new args... */
				$query = "SELECT COUNT(*) as NUM_ARGS FROM _MENU_ARGS WHERE MENU_ID = ?";
				$params = array(
					array("type" => "i", "value" => $id)
				);
				$num_args = $db->run_query($query,$params)[0]['NUM_ARGS'];
				while ($num_args < count($item['args'])) {
						$query = "INSERT INTO _MENU_ARGS (MENU_ID, ARG_NUMBER) VALUES (?,?)";
						$params = array(
							array("type" => "i", "value" => $id),
							array("type" => "i", "value" => ++$num_args)
						);
						$db->run_query($query,$params);
				}
				/* Set up the args update query... */
				if (!empty($item['args'])) {
					$query = "UPDATE _MENU_ARGS SET ARG = CASE ARG_NUMBER";
					$params = array();
					foreach($item['args'] as $idx2=>$arg)  {
						$query .= " WHEN ".($idx2+1) ." THEN ?";
						array_push($params,array("type" => "s", "value" => $arg));
					}
					$query .= " END WHERE MENU_ID = ?";
					array_push($params,array("type" => "i", "value" => $id));
					$db->run_query($query,$params);
					$error = $db->GetErrors();
					if (!empty($error))
						die($error);
				}
				/* Still TODO: Submenus!! */
				if (!empty($item['submenu'])) {
					$subIDs = array();
					$num_subs = static::get_num_menus($item['submenu']);
					for ($i=0; $i<$num_subs; $i++) {
						$subIDs[] = array_shift($IDs);
					}
					static::save_sub_menu($item['submenu'],$subIDs,$id);
				}
			}
			return;
	}
	
	protected static function save_menu($menu) {
		global $db;
		/* Will need to implement the rights check in here as well.. */
		$num_rows = static::get_num_menus($menu);
		/* Delete all _MENU records which are not in the top $num_rows (will need to delete args first) */
		/* Because MySQL doesn't support LIMIT in inner queries we'll need to get our max ID separately (who needs performance?!) */
		$query = "SELECT ID FROM _MENU ORDER BY ID ASC LIMIT $num_rows";
		$max_id = array_pop($db->run_query($query))['ID'];

		$query = "DELETE FROM _MENU_ARGS WHERE MENU_ID > ?";
		$params = array(array("type" => "i", "value" => $max_id));
		$db->run_query($query,$params);
		
		/* clear out any FK issues for children nodes here... */
		$query = "UPDATE _MENU SET PARENT_ID = NULL WHERE ID > ? OR PARENT_ID > ?";
		array_push($params,array("type" => "i", "value" => $max_id));
		$db->run_query($query,$params);
		array_pop($params);
		
		$query = "DELETE FROM _MENU WHERE ID > ?";
		$db->run_query($query,$params);
		/* Create any new _MENU records which may be required... */
		$query = "SELECT ID FROM _MENU";
		$current_menus = $db->run_query($query);
		while (count($current_menus) < $num_rows) {
			$query = "INSERT INTO _MENU() VALUES()";
			$db->run_query($query);
			$current_menus[] = $db->get_inserted_id();
		}
		static::save_sub_menu($menu,$current_menus);
		return array('success' => 1);
	}
	
	public static function ajax($args,$request) {
		switch($request['ajax']) {
			case 'save-menu': 
				foreach($request as $key=>$val) if (!is_array($val)) unset($request[$key]);
				return static::save_menu($request);
		}
	}
	
	public static function menu() {return false;}
	
	public static function admin() {
		global $local, $db;
		$output = array('html' => '', 'title' => 'Menu Edit', 'script' => array(), 'css' => array());
		$query = "SELECT ID, NAME, CLASS_NAME
		FROM _MODULES";
		$modules = $db->run_query($query);
		foreach($modules as $idx=>&$module) {
			$module['menu'] = call_user_func_array(array($module['CLASS_NAME'],'menu'),array());
			if (empty($module['menu'])) unset($modules[$idx]);
		}
		unset($module);

		array_push($output['script'],
			"{$local}script/jquery.min.js",
			"{$local}script/jquery-ui.min.js",
			"{$local}script/jquery-sortable.js",
			get_public_location(__DIR__ . '/js/menu-transfer-list.js')
		);
		
		array_push($output['css'],
			"{$local}style/jquery-ui.css",
			"{$local}style/jquery-sortable.css",
			get_public_location(__DIR__ . '/style/menu-admin.css')
		);
		
		$output['html'] .= "<h2>Menu Edit</h2>
		<div id='modules' class='half'>
		<h3>Available Modules:</h3>";
		/* Run through $modules */
		foreach($modules as $module) {
			$output['html'] .= "
				<h4>{$module['NAME']}<span class='toggle'>+</span></h4>
				
				<ul class='module' module='{$module['ID']}'>";
				
				foreach($module['menu'] as $text=>$menu_item) {
						$output['html'] .= static::menu_object_to_li($text,$menu_item);
				}
				
				$output['html'] .= "</ul>";
		}
		
		$output['html'] .= "
		</div>
		<div id='menu' class='half'>
			<h3>Menu</h3>
			<ul>" . static::menu_simple_display() . "
			</ul>
		</div>
		<p><button id='save'>Save Menu</button></p>
		";
		return $output;
	}
	
	public static function view() {
		global $local;
		header("Location: $local");
		exit();
	}
	
	/* recursive function to output the menu for the a() method*/
	private static function menu_simple_display($parent = null) {
		global $db;
		$query = "SELECT * 
		FROM _MENU 
		WHERE PARENT_ID = ? OR (PARENT_ID IS NULL AND ? IS NULL) ORDER BY DISPLAY_ORDER";
		$params = array(
			array("type" => "i", "value" => $parent),
			array("type" => "i", "value" => $parent)
		);
		$menu = $db->run_query($query,$params);
		$display = "";
		foreach($menu as $item) {
			$query = "SELECT ARG FROM _MENU_ARGS WHERE MENU_ID = ? ORDER BY ARG_NUMBER ASC";
			$params = array(
				array("type" => "i", "value" => $item['ID'])
			);
			$args = $db->run_query($query,$params);
			$argList = array();
			foreach($args as $arg) {
				$argList[] = $arg['ARG'];
				}
			$display .= "<li module='{$item['MODULE_ID']}' right='{$item['RIGHT_ID']}' args='".json_encode($argList)."'>{$item['TEXT']}<ul>".static::menu_simple_display($item['ID'])."</ul><span class='remove'>x</span></li>";
		}
		
		return $display;
	}
	
	/* Recursive function to convert $obj to a li element*/
	private static function menu_object_to_li($name,$obj) {
		$li = "<li right='{$obj['right']}' args='".make_html_safe(json_encode($obj['args']),ENT_QUOTES)."'>$name <span class='to-menu' title='Click here to move this item onto the menu.'>--&gt;</span>";
		if (!empty($obj['submenu']))  {
			$li .= "<ul>";
			foreach($obj['submenu'] as $text=>$submenu) {
				$li.=static::menu_object_to_li($text,$submenu);
			}
			$li .= "</ul>";
		}
		
		$li .= "</li>";
		return $li;
	}
	
	/* Recursively creates a menu based off of $parent (menu ID)*/
	protected static function create_menu($parent = null) {
		global $local,$db;
		$user = users::get_session_user();
		$query = "SELECT MN.ID, MN.TEXT, M.CLASS_NAME, M2.NAME as RIGHT_MODULE_NAME, T.NAME AS RIGHT_TYPE_NAME, R.NAME AS RIGHT_RIGHT_NAME
		FROM _MENU MN
		JOIN _MODULES M ON MN.MODULE_ID = M.ID
		LEFT JOIN _RIGHTS R ON MN.RIGHT_ID = R.ID
		LEFT JOIN _RIGHT_TYPES T ON R.RIGHT_TYPE_ID = T.ID
		LEFT JOIN _MODULES M2 ON T.MODULE_ID = M2.ID
		WHERE PARENT_ID = ? OR (PARENT_ID IS NULL AND ? IS NULL)
		ORDER BY DISPLAY_ORDER";
		$params = array(
			array("type" => "i", "value" => $parent),
			array("type" => "i", "value" => $parent)
		);
		$menus = $db->run_query($query,$params);
		$menu = "";
		if (empty($menus)) return;
		foreach($menus as $q=>$item) {
			if (!empty($item['RIGHT_RIGHT_NAME']) && !$user->check_right($item['RIGHT_MODULE_NAME'],$item['RIGHT_TYPE_NAME'],$item['RIGHT_RIGHT_NAME'])) {
				unset($menus[$q]);
				continue;
			}
			$query = "SELECT ARG FROM _MENU_ARGS WHERE MENU_ID = ? ORDER BY ARG_NUMBER";
			$params = array(
				array("type" => "i", "value" => $item['ID'])
			);
			$args = $db->run_query($query,$params);
			$argList = array();
			if (!empty($args))
				foreach($args as $arg)
					$argList[] = $arg['ARG'];
			$href = call_user_func_array(array($item['CLASS_NAME'],'decode_menu'),array($argList));
			$menu .= "<li><a href='$href'>{$item['TEXT']}</a>";
			$submenu = static::create_menu($item['ID']);
			if (!empty($submenu))
				$menu.= "<ul>$submenu</ul>";
			$menu .= "</li>";
			
		}
		return $menu;
	}
	
}

?>
