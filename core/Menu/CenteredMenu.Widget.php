<?php
class centered_menu_widget extends menu implements widget {
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
			array("type" => "s", "value" => "Centered Menu"),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => get_parent_class(__CLASS__))
		);
		$db->run_query($query,$params);
	}

	public static function view() {
		/* Similar to view_menu, this displays centered menus... */
		global $local;
		$output = array(
			'html' => "<nav class='menu centered'><ul>".static::create_menu()."</ul></nav>",
			'css' => array(
				utilities::get_public_location(__DIR__ . '/style/centered-menu.css')
			)
		);
		return $output;
	}
}
?>
