<?php
class vertical_menu_widget extends menu implements widget {
	public static function install() {
		global $db;
		$query = "
			INSERT INTO _WIDGETS(MODULE_ID,NAME,FILENAME,CLASS_NAME)
			SELECT M.ID, ?, ?, ?
			FROM _MODULES M
			LEFT JOIN _WIDGETS W ON 
				M.ID = W.MODULE_ID AND
				? = W.FILENAME
			WHERE M.CLASS_NAME = ? AND W.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Vertical Menu"),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => get_parent_class(__CLASS__))
		);
		$db->run_query($query,$params);
	}
	
	public static function view() {
		/* Similar to view_menu, this displays vertically instead of horizontally... */
		global $local;
		$output = array('html' => '', 'css' => get_public_location(__DIR__ . '/style/menu.css'));
		$output['html'] .= "<nav class='menu vertical'><ul>".static::create_menu()."</ul></nav>";
		return $output;
	}
}
?>
