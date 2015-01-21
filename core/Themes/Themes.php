<?php
class themes extends module {
	public static function install() {
		/* Install _THEMES and _USERS_THEMES tables */
		global $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _THEMES (
				ID int auto_increment,
				NAME varchar(32),
				STYLE text,
				MODULE_ID int,
				IS_DEFAULT bit(1) DEFAULT 0,
				PRIMARY KEY (ID),
				UNIQUE KEY (NAME),
				FOREIGN KEY (MODULE_ID) REFERENCES _MODULES(ID)
			);";
		$db->run_query($query);
		
		$query = "
			CREATE TABLE IF NOT EXISTS _USERS_THEMES (
				USER_ID int,
				SESSION_ID varchar(32),
				THEME_ID int,
				INDEX(USER_ID),
				INDEX(SESSION_ID),
				FOREIGN KEY (USER_ID) REFERENCES _USERS(ID),
				FOREIGN KEY (THEME_ID) REFERENCES _THEMES(ID)
			);";
		$db->run_query($query);
		
		/* Install widgets... */
		require_once(__DIR__ . '/Personal.Themes.Widget.php');
		personal_theme_widget::install();
		
		return true;
	}
	
	protected static function admin_theme($theme) {
		global $db,$s_user;
		$output = array(
			'html' => '<h3>Theme Administration</h3>'
		);
		
		$query = "SELECT * FROM _THEMES WHERE ID = ?";
		$params = array(
			array("type" => "i", "value" => $theme)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			header("Location: ". modules::get_module_url() . "/Themes");
			exit();
			return;
		}
		
		$theme = $result[0];
		
		if ($s_user->check_right('Themes','Administration','Edit Themes')) {
			$output['html'] .= "
			<form method='post' action=''>
				<div class='field'>
					<label for='theme-name'>Name:</label>
					<input id='theme-name' name='name' value='{$theme['NAME']}' />
				</div>
				<div class='field'>
					<label for='theme-style'>CSS:</label>
					<textarea id='theme-style' name='style'>{$theme['STYLE']}</textarea>
				</div>";
			if ($s_user->check_right('Themes','Administration','Set Default Theme')) {
				$checked = $theme['IS_DEFAULT'] ? 'checked="checked"' : '';
				$output['html'] .= "
				<div class='field'>
					<input type='checkbox' id='is-default-theme' name='default' value='1' $checked />
					<label for='is-default-theme'>Site Default Theme?</label>
				</div>";
			}
			$output['html'] .= "
				<div class='field'>
					<label for='theme-layout-module'>Layout Module:</label>
					<select id='theme-layout-module' name='module'>";
			$query = "SELECT ID, NAME FROM _MODULES ORDER BY NAME";
			$modules = $db->run_query($query);
			foreach($modules as $module) {
				$selected = ($module['ID'] == $theme['MODULE_ID']) || (is_null($theme['MODULE_ID']) && $module['NAME'] == 'Layout' ) ? 'selected="selected"' : '';
				$output['html'] .= "<option value='{$module['ID']}' $selected>{$module['NAME']}</option>";
			}
			$output['html'] .= "						
					</select>
				</div>
				<div class='field'>
					<input type='submit' value='Save Theme' />
				</div>
			</form>";	
		} else {
			$output['html'] .= "<h4>{$theme['NAME']} Theme</h4>";
			$output['html'] .= "<p><a href='".static::get_module_url()."css/{$theme['ID']}-".make_url_safe($theme['NAME']).".css'>Stylesheet link should go here...</a></p>";
			if ($s_user->check_right('Themes','Administration','Set Default Theme') && !$theme['IS_DEFAULT']) {
				$output['html'] .= "
				<form method='post' action=''>
					<p>
						<button type='submit' name='default' value='1'>Save as Site Default</button>
					</p>
				</form>";
			} elseif ($theme['IS_DEFAULT']) {
				$output['html'] .= "<p>Currently site default theme.</p>";
			}
		}
		
		$output['html'] .= "<p><a href='".modules::get_module_url()."Themes'>Return to theme administration</a></p>";
		
		return $output;
	}
	
	protected static function admin_themes() {
		global $db;
		$output = array(
			'html' => '<h3>Theme Administration</h3>'
		);
		$query = "SELECT ID,NAME,CASE IS_DEFAULT WHEN 1 THEN 'Yes' ELSE 'No' END as IS_DEFAULT_THEME FROM _THEMES";
		$themes = $db->run_query($query);
		if (!empty($themes)) {
			$output['html'] .= "
			<h4>Current Themes</h4>
			<table>
				<thead>
					<tr>
						<th>Theme</th>
						<th>Is Default?</th>
					</tr>
				</thead>
				<tbody>";
			foreach($themes as $theme) {
				$output['html'] .= "
					<tr>
						<td><a href='".modules::get_module_url()."Themes/{$theme['ID']}'>{$theme['NAME']}</a></td>
						<td>{$theme['IS_DEFAULT_THEME']}</td>
					</tr>";
			}
			$output['html'] .= "			
				</tbody>
			</table>";
		}
		$output['html'] .= "
			<h4>Add New Theme</h4>
			<form method='post' action=''>
				<label for='theme-name'>Name:</label>
				<input id='theme-name' name='name' />
				<input type='submit' value='Create New Theme!' />
			</form>";
		return $output;
	}
	
	public static function admin_post($args,$request) {
		global $db,$s_user;
		if (empty($args)) {
			/* Add new theme... */
			/* Only allow if Themes/Administration/Add Themes */
			if (!$s_user->check_right('Themes','Administration','Add Themes')) return false;
			/* Create new theme with given name, redirect to that new theme's Admin URL... */
			$query = "INSERT INTO _THEMES (NAME) VALUES (?)";
			$params = array(
				array("type" => "s", "value" => $request['name'])
			);
			$db->run_query($query,$params);
			$theme_id = $db->get_inserted_id();
			header("Location: " . modules::get_module_url() . "Themes/$theme_id");
			exit();
			return;
		}else {
			/* Edit existing theme... */
			/* Only allow if Themes/Administration/Edit Themes */
			$theme_id = array_shift($args);
			if ($s_user->check_right('Themes','Administration','Edit Themes')) {
				$query = "
					UPDATE _THEMES SET
						NAME = ?,
						STYLE = ?,
						MODULE_ID = ?
					WHERE ID = ?";
				$params = array(
					array("type" => "s", "value" => $request['name']),
					array("type" => "s", "value" => $request['style']),
					array("type" => "i", "value" => $request['module']),
					array("type" => "i", "value" => $theme_id)
				);
				$db->run_query($query,$params);
			}
			/* Check if right to set default theme... */
			if ($s_user->check_right('Themes','Administration','Set Default Theme')) {
				if (!empty($request['default'])) {
					$query = "UPDATE _THEMES SET IS_DEFAULT = CASE ID WHEN ? THEN 1 ELSE 0 END";
					$params = array(
						array("type" => "i", "value" => $theme_id)
					);
					$db->run_query($query,$params);
				} else {
					$query = "UPDATE _THEMES SET IS_DEFAULT = 0 WHERE ID = ?";
					$params = array(
						array("type" => "i", "value" => $theme_id)
					);
					$db->run_query($query,$params);
				}
			}
		}
	}
	
	public static function admin($theme='') {
		if (empty($theme)) 
			return static::admin_themes();
		else return static::admin_theme($theme);
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
	
	/* This file will output a stylesheet stored in the database.  Filename should be of the form <ID>-<THEME_NAME>.css */
	protected static function get_stylesheet($filename) {
		global $db;
		if (!preg_match('/^(?<ID>\d+)-(?<NAME>.*)\.css/',$filename,$theme)) die(1);
		$query = "SELECT STYLE FROM _THEMES WHERE ID = ? AND NAME RLIKE ?";
		$params = array(
			array("type" => "i", "value" => $theme['ID']),
			array("type" => "s", "value" => decode_url_safe($theme['NAME']))
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) die(2);
		header("Content-Type: text/css");
		echo $result[0]['STYLE'];
		exit;
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
	
	/* This function will display the actual page... */
	public static function view_page() {
		global $db,$s_user;
		$theme = $s_user->get_theme();
		if (empty($theme)) {
			layout::setup_page();
			return;
		}
		$css = $theme['HAS_STYLESHEET'] ? array(static::get_module_url() . "css/{$theme['ID']}-".make_url_safe($theme['NAME']).".css") : array();
		call_user_func_array(
			array($theme['CLASS_NAME'],'setup_page'),
			array(
				array('css' => $css)
			));
	}
}
?>
