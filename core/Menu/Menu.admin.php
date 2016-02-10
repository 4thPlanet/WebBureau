<?php
class menu_admin extends menu {
	public static function ajax($args,$request) {
		switch($request['ajax']) {
			case 'save-menu':
				foreach($request as $key=>$val) if (!is_array($val)) unset($request[$key]);
				return static::save_menu($request);
		}
	}

	public static function post($args,$request) {}

	public static function view() {
		global $local, $db;
		$output = array('html' => '', 'title' => 'Menu Edit', 'script' => array(), 'css' => array());
		$query = "SELECT ID, NAME, CLASS_NAME FROM _MODULES";
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
			utilities::get_public_location(__DIR__ . '/js/menu-transfer-list.js')
		);

		array_push($output['css'],
			"{$local}style/jquery-ui.css",
			"{$local}style/jquery-sortable.css",
			utilities::get_public_location(__DIR__ . '/style/menu-admin.css')
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
		$output['html'] .= "<h4>Empty Link<span class='toggle'>+</span></h4><ul class='module' module=''><li right='' args=''><span class='menu-text'>Link to Nowhere</span> <span class='to-menu' title='Click here to move this item onto the menu.'>--&gt;</span></li></ul>";

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

	/* Recursive function to convert $obj to a li element*/
	protected static function menu_object_to_li($name,$obj) {
		$li = "<li right='{$obj['right']}' args='".utilities::make_html_safe(json_encode($obj['args']),ENT_QUOTES)."'><span class='menu-text'>$name</span> <span class='to-menu' title='Click here to move this item onto the menu.'>--&gt;</span>";
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

	/* recursive function to output the menu for the admin() method*/
	protected static function menu_simple_display($parent = null) {
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
			$display .= "<li module='{$item['MODULE_ID']}' right='{$item['RIGHT_ID']}' args='".json_encode($argList)."'><span class='menu-text'>{$item['TEXT']}</span><ul>".static::menu_simple_display($item['ID'])."</ul><span class='remove'>x</span></li>";
		}

		return $display;
	}

	protected static function save_menu($menu) {
		global $db;
		/* Will need to implement the rights check in here as well.. */
		$num_rows = static::get_num_menus($menu);
		/* Delete all _MENU records which are not in the top $num_rows (will need to delete args first) */
		/* Because MySQL doesn't support LIMIT in inner queries we'll need to get our max ID separately (who needs performance?!) */
		$query = "SELECT ID FROM _MENU ORDER BY ID ASC LIMIT $num_rows";
		$result = array_pop($db->run_query($query));
		$max_id = $result['ID'];

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

	protected static function save_sub_menu($menu,$IDs, $parent = null) {
		global $db;
		$count = 0;
		foreach($menu as $item) {
			$id = array_shift($IDs);
			if (is_array($id)) {
				$id = array_values($id);
				$id = $id[0];
			}
			$count++;
			if (empty($item['module'])) $item['module'] = null;
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
				array("type" => "s", "value" => $item['text']),
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
			$result = $db->run_query($query,$params);
			$num_args = $result[0]['NUM_ARGS'];
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

	protected static function get_num_menus($menu) {
		/* recursively goes through menu to get the total number of menu records which will be needed in save_menu */
		$count = 0;
		foreach($menu as $item) {
			$count++;
			if (!empty($item['submenu'])) $count+= static::get_num_menus($item['submenu']);
		}

		return $count;
	}
}
?>
