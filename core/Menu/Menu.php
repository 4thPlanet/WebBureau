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

		/* Install Widgets*/
		require_once(__DIR__ . '/Menu.Widget.php');
		require_once(__DIR__ . '/VerticalMenu.Widget.php');
		require_once(__DIR__ . '/CenteredMenu.Widget.php');

		menu_widget::install();
		vertical_menu_widget::install();
		centered_menu_widget::install();

		return true;
	}

	public static function ajax($args,$request) {}

	public static function menu() {return false;}

	public static function view() {
		global $local;
		header("Location: $local");
		exit();
	}

	/* Recursively creates a menu based off of $parent (menu ID)*/
	protected static function create_menu($parent = null) {
		global $local,$db;
		$user = users::get_session_user();
		$query = "SELECT MN.ID, MN.TEXT, M.CLASS_NAME, M2.NAME as RIGHT_MODULE_NAME, T.NAME AS RIGHT_TYPE_NAME, R.NAME AS RIGHT_RIGHT_NAME
		FROM _MENU MN
		LEFT JOIN _MODULES M ON MN.MODULE_ID = M.ID
		LEFT JOIN _RIGHTS R ON MN.RIGHT_ID = R.ID
		LEFT JOIN _RIGHT_TYPES T ON R.RIGHT_TYPE_ID = T.ID
		LEFT JOIN _MODULES M2 ON T.MODULE_ID = M2.ID
		WHERE
			( PARENT_ID = ? OR (PARENT_ID IS NULL AND ? IS NULL) ) AND
			( M.ID IS NOT NULL OR MN.MODULE_ID IS NULL )
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
			$href = $item['CLASS_NAME'] ? call_user_func_array(array($item['CLASS_NAME'],'decode_menu'),array($argList)) : 'javascript:void(0)';
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
