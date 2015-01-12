<?php
class login_widget extends users implements widget {
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
			array("type" => "s", "value" => "Login"),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => get_parent_class(__CLASS__)),
			array("type" => "s", "value" => "Login"),
		);
		$db->run_query($query,$params);
	}
	
	public static function view() {
		/* Login widget, displays a login form... */
		global $local;
		$user = static::get_session_user();
		if (!$user->check_right('Users','Widget','Login')) {
			header("Location: " . static::get_module_url());
			exit();
		}
		$output = array('html' => '');
		$output['html'] = "
		<p>
			<form action='{$local}Users/login' method='post'>
				<label for='username'>Username:</label>
				<input id='username' name='username' />
				<label for='password'>Password:</label>
				<input type='password' id='password' name='password' />
				<input type='submit' value='Login' />
			</form>
			or <a href='{$local}Users/register/' title='Click here to register' />Register</a>
		</p>
		";
		return $output;
	}
}
?>
