<?php
class personal_theme_widget extends themes implements widget {
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
			array("type" => "s", "value" => "Personalized Theme"),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => get_parent_class(__CLASS__)),
			array("type" => "s", "value" => "Choose Custom Theme"),
		);
		$db->run_query($query,$params);
		return true;
	}



	public static function ajax($request) {
		switch($request['ajax']) {
			case 'set-theme':
				return array('success' => static::set_user_theme($request['theme']));
		}
		return array('success' => 0);
	}

	public static function view() {
		global $db,$local,$s_user;
		$query = "SELECT ID,NAME FROM _THEMES ORDER BY NAME";
		$themes = $db->run_query($query);
		if (empty($themes)) return array();
		/* Get rid of any themes user does not have rights to use... */
		if (!$s_user->check_right('Themes','Selection','View All Custom Themes')) {
			foreach($themes as $idx=>$theme)
				if (!$s_user->check_right('Themes','Selection',"View {$theme['NAME']} Theme"))
					unset($themes[$idx]);
			if (empty($themes)) return array();
		}

		$output = array(
			'html' => '<h5>Change Site Theme</h5>',
			'script' => array(
				"{$local}script/jquery.min.js",
				utilities::get_public_location(__DIR__ . '/js/personal-theme.js')
			)
		);

		$user_theme = $s_user->get_theme();
		if (empty($user_theme)) $user_theme['ID'] = null;

		$output['html'] .= "
		<p>Select a new theme for a different look:</p>
		<form id='personal-theme' method='post' action=''>
			<select name='theme'>
				<option value=''>Site Default</option>";
		foreach($themes as $theme) {
			$selected = $theme['ID'] == $user_theme['ID'] ? 'selected="selected"' : '';
			$output['html'] .= "<option value='{$theme['ID']}' $selected>{$theme['NAME']}</option>";
		}
		$output['html'] .= "
			</select>
			<input type='submit' value='Use This Theme!' />
		</form>";


		return $output;
	}
}
?>
