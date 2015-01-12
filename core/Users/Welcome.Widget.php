<?php
class welcome_widget extends users implements widget {
	public static function install() {
		global $db;
		$query = "
			INSERT INTO _WIDGETS(MODULE_ID,NAME,FILENAME,CLASS_NAME,RIGHT_ID)
			SELECT M.ID, ?, ?, ?, R.ID
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID
			LEFT JOIN _WIDGETS W ON 
				M.ID = W.MODULE_ID AND
				? = W.FILENAME
			WHERE M.CLASS_NAME = ? AND R.NAME = ? AND W.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Welcome"),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => get_parent_class(__CLASS__)),
			array("type" => "s", "value" => "Welcome"),
		);
		$db->run_query($query,$params);
	}
	
	public static function view() {
		/* Welcome widget, displays the text "Welcome, {User}", along with an option to logout */
		/* Will need to grab $user from the session */
		global $local, $s_user;
		$user = $s_user->user_info;
		if (empty($user['DISPLAY_NAME'])) $user['DISPLAY_NAME'] = $user['USERNAME'];
		return "<p>Welcome, {$user['DISPLAY_NAME']}!</p>
		<p><a href='{$local}Users/logout'>Logout</a></p>";
	}
}
?>
