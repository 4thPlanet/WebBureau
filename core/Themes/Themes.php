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
				PRIMARY KEY (USER_ID,SESSION_ID),
				INDEX(SESSION_ID),
				FOREIGN KEY (USER_ID) REFERENCES _USERS(ID),
				FOREIGN KEY (THEME_ID) REFERENCES _THEMES(ID)
			);";
		$db->run_query($query);
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
				$output['html'] .= "
				<div class='field'>
					<input type='checkbox' id='is-default-theme' name='default' value='1' />
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
			$output['html'] .= "<p><a href='#'>Stylesheet link should go here...</a></p>";
			if ($s_user->check_right('Themes','Administration','Set Default Theme')) $output .= "
			<form method='post' action=''>
				<p>
					<button type='submit' name='set-default' value='1'>Save as Site Default</button>
				</p>
			</form>";
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
	
	public static function view() {
		global $db,$s_user;
		/* TODO: Only allow if user has Themes/Selection/<ANYTHING> available... */
		
		
		
	}
	
	public static function required_rights() {
		return array(
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
					'ALL Custom Themes' => array(
						'description' => 'Allows user to view ALL themes, and select one for their personal use.',
						'default_groups' => array('Admin')
					)
					/* TODO: Add dynamic section to view each theme individually */
				)
			)
		);
	}
}
?>
