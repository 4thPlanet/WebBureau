<?php
class themes extends module {
	public static function install() {
		/* Install _THEMES and _USERS_THEMES tables */
		global $db;

		/* Install widgets... */
		require_once(__DIR__ . '/Personal.Themes.Widget.php');
		personal_theme_widget::install();

		return true;
	}

	/* Sets user theme for current user.  In the future, a new parameter, $user = NULL, will be added for setting theme for a user other than the current user... */
	protected static function set_user_theme($theme) {
		global $db,$s_user;
		if (!empty($theme)) {
			$query = "
				INSERT INTO _USERS_THEMES (USER_ID,SESSION_ID,THEME_ID) VALUES (?,?,?) ON DUPLICATE KEY UPDATE
					THEME_ID = VALUES(THEME_ID)";
			$params = array(
				array("type" => "i", "value" => users::current_user_is_guest() ? NULL : $s_user->id()),
				array("type" => "s", "value" => users::current_user_is_guest() ? session_id() : NULL),
				array("type" => "i", "value" => $theme)
			);
			$db->run_query($query,$params);
		} else {
			$query = "DELETE FROM _USERS_THEMES WHERE (USER_ID = ? AND SESSION_ID IS NULL) OR (SESSION_ID = ? AND USER_ID IS NULL)";
			$params = array(
				array("type" => "i", "value" => users::current_user_is_guest() ? NULL : $s_user->id()),
				array("type" => "s", "value" => users::current_user_is_guest() ? session_id() : NULL)
			);
			$db->run_query($query,$params);
		}
		$s_user->reload_theme();
		return true;
	}

	public static function post() {}

	public static function ajax() {}

	public static function view($theme='',$file='') {
		global $db,$s_user;
		if ($theme=='css' && !empty($file)) {
			return static::get_stylesheet($file);
		}
		/* TODO: Only allow if user has Themes/Selection/<ANYTHING> available... */
	}
	
	public static function get($filename) {
	    global $db;
	    if (!preg_match('/^(?<ID>\d+)-(?<NAME>.*)\.css/',$filename,$theme)) return false;
	    $query = "SELECT STYLE FROM _THEMES WHERE ID = ? AND NAME RLIKE ?";
	    $params = array(
	        array("type" => "i", "value" => $theme['ID']),
	        array("type" => "s", "value" => utilities::decode_url_safe($theme['NAME']))
	    );
	    $result = $db->run_query($query,$params);
	    return count($result) ? $result[0]['STYLE']: false;
	}

	/* This file will output a stylesheet stored in the database.  Filename should be of the form <ID>-<THEME_NAME>.css */
	protected static function get_stylesheet($filename) {
		global $db;
		$result = static::get($filename);
		if ($result){
		    header("Content-Type: text/css");
		    echo $result;
		    exit;
		} else {
		    die;
		}		
	}

	public static function required_rights() {
		global $db;

		$rights = array(
			'Themes' => array(
				'Administration' => array(
					'Set Default Theme' => array(
						'description' => 'Allows user to set the site-wide default theme.',
						'default_groups' => array('Admin')
					),
					'Delete Themes' => array(
						'description' => 'Allows user to delete themes.',
						'default_groups' => array('Admin')
					),
					'Edit Themes' => array(
						'description' => 'Allows user to edit existing themes',
						'default_groups' => array('Admin')
					),
					'Add Themes' => array(
						'description' => 'Allows user to add new themes.',
						'default_groups' => array('Admin')
					)
				),
				'Selection' => array(
					'Choose Custom Theme' => array(
						'description' => 'Allows user to pick a theme other than the site default.',
						'default_groups' => array('Admin','Registered User')
					),
					'View All Custom Themes' => array(
						'description' => 'Allows user to view ALL themes installed on the site.',
						'default_groups' => array('Admin')
					)
					/* TODO: Add dynamic section to view each theme individually */
				)
			)
		);

		/* Now get each individual theme and create a right to select it... */
		$query = "SELECT NAME FROM _THEMES";
		$themes = $db->run_query($query);
		if (!empty($themes))
			foreach($themes as $theme)
				$rights['Themes']['Selection']["View {$theme['NAME']} Theme"] = array(
					'description' => "Allows user to select the {$theme['NAME']} theme over the default theme.",
					'default_groups' => array('Admin','Registered User')
				);
		return $rights;
	}

	public static function required_tables() {
		return array(
			'_THEMES' => array(
				'columns' => array(
					'ID' => 'int auto_increment',
					'NAME' => 'varchar(32)',
					'STYLE' => 'text',
					'MODULE_ID' => 'int',
					'IS_DEFAULT' => 'bit(1) DEFAULT 0'
				),
				'keys' => array(
					'PRIMARY' => array('ID'),
					'FOREIGN' => array(
						'MODULE_ID' => array('table' => '_MODULES','column' => 'ID'),
					),
					'UNIQUE' => array(
						array('NAME')
					)
				)
			),
			'_USERS_THEMES' => array(
				'columns' => array(
					'USER_ID' => 'int',
					'SESSION_ID' => 'varchar(32)',
					'THEME_ID' => 'int',
				),
				'keys' => array(
					'PRIMARY' => array('USER_ID','SESSION_ID'),
					'FOREIGN' => array(
						'USER_ID' => array('table' => '_USERS','column' => 'ID'),
						'THEME_ID' => array('table' => '_THEMES','column' => 'ID'),
					),
					'UNIQUE' => array(
						array('USER_ID'),
						array('SESSION_ID')
					)
				)
			),
		);
	}

	/* This function will display the actual page... */
	public static function view_page() {
		global $db,$s_user;
		$theme = $s_user->get_theme();
		if (empty($theme)) {
			/* Needs to be site default theme here!! */
			layout::setup_page();
			return;
		}
		$css = $theme['HAS_STYLESHEET'] ? array(utilities::get_public_location(__DIR__ . "/css/{$theme['ID']}-".utilities::make_url_safe($theme['NAME']).".css")) : array();
		call_user_func_array(
			array($theme['CLASS_NAME'],'setup_page'),
			array(
				array(),array('css' => $css)
			));
	}
}
?>
