<?php

class modules extends module {
	/* Constructor (nothing is really needed) */
	public function __construct() {}

	/* Admin function (as this is the admin class, there's no way to do this except for through the view method) */
	public static function admin() {}

	/* What to do on post requests... */
	public static function post($args,$request) {
		global $db,$local_dir;
		$user = users::get_session_user();
		if (!$user->check_right('Modules','Administer','Administer Modules')) return false;
		if (empty($args)) {
			$zip = new ZipArchive;
			$success = true;
			if ($zip->open($_FILES['module_upload']['tmp_name']) === TRUE) {
				$folder_name = substr($_FILES['module_upload']['name'],0,strrpos($_FILES['module_upload']['name'],"."));
				$location = "{$local_dir}custom/$folder_name";
				$zip->extractTo($location);
				$zip->close();
				/* Search for .module file... */
				$info_files = utilities::recursive_glob('*.module',$location);
				foreach($info_files as $info_file) {
					$module_info = json_decode(file_get_contents($info_file),true);
					$module_info['Directory'] = dirname($info_file) . "/";
					/* Confirm no dependencies... */
					if (!empty($module_info['Requires'])) {
						$query = "
							SELECT COUNT(*) as num_modules
							FROM _MODULES
							WHERE NAME IN (".substr(str_repeat("?,",count($module_info['Requires'])),0,-1).")";
						$params = array();
						foreach($module_info['Requires'] as $required)
							array_push($params, array("type" => "s", "value" => $required));
						$result = $db->run_query($query,$params);
						if ($result[0]['num_modules'] != count($module_info['Requires'])) {
							layout::set_message("Unable to install module {$module_info['Module']}.  Please insure all required modules are installed first.");
							continue;
						}
						if ($module_info['Extends']) {
							$module_info['ModuleParentID'] = module::get_module_id($module_info['Extends']);
						}
					}
					$success = module::install_module($module_info);
					if ($success) layout::set_message('New module installed.','info');
					else layout::set_message('Unable to install module.','error');
				}
			} else {
				layout::set_message('Unable to install read .zip file to install class.','error');
			}
		} else {
			$module = array_shift($args);
			if (!$user->check_right('Modules','Administer', "Administer $module")) return false;
			$helper = module::get_module_helpers(module::get_module_id($module),'admin');

			$class_name = module::get_module_class($module);
			if ($class_name===false) return false;
			return call_user_func_array(array($helper['admin']['CLASS_NAME'],'post'),array($args,$request));
		}
	}

	/* What to do on AJAX requests... */
	public static function ajax($args,$request) {
		global $db;
		$user = users::get_session_user();
		if (!$user->check_right('Modules','Administer','Administer Modules')) return false;
		if (empty($args)) {
			switch($request['ajax']) {
				case 'Assign Right':
					$rid = users::create_right($request['module'],$request['type'],$request['right'],$request['description']);
					users::assign_rights(array($rid => $request['groups']));
					return array('success' => 1);
			}
		} else {
			$module = array_shift($args);
			if (!$user->check_right('Modules','Administer',"Administer $module")) return false;
			$helpers = module::get_module_helpers(module::get_module_id($module),'admin');
			/* Return the ajax() function of that module... */
			return call_user_func_array(array($helpers['admin']['CLASS_NAME'],'ajax'),array($args,$request));
		}
	}

	private static function view_main() {
		global $db,$local;
		$output = array(
			'html' => '<h3>Module Administration</h3>',
			'script' => array(),
			'css' => array()
		);
		$user = users::get_session_user();
		/* Install Module */
		$output['html'] .= "
			<form action='' method='post' enctype='multipart/form-data'>
				<h4>Install New Module</h4>
				<p>
					Select a file to upload containing the module to be uploaded (only .zip files supported presently):<br />
					<input type='file' name='module_upload' /><br /><br />
					<input type='submit' value='Upload Module' />
				</p>
			</form>";
		/* Search for rights which are not properly installed (only if have Create Right right...) */
		if (!$user->check_right('Users','Rights','Create Rights')) return $output;
		$query = "SELECT CLASS_NAME FROM _MODULES";
		$modules = utilities::group_numeric_by_key($db->run_query($query),'CLASS_NAME');
		$required_rights = array();

		/* This will need to be rewritten to allow for <RIGHT> = array('description' => '<description>', 'default_groups' => array(<DEFAULT GROUPS>))*/
		foreach($modules as $class) {
			$required_rights = array_merge_recursive($required_rights,call_user_func(array($class,'required_rights')));
		}
		foreach($required_rights as $module=>$types) {
			foreach($types as $type=>$rights) {
				foreach($rights as $right=>$right_info) {
					if (users::get_right_id($module,$type,$right)!==false) unset($required_rights[$module][$type][$right]);
				}
				if (empty($required_rights[$module][$type])) unset($required_rights[$module][$type]);
			}
			if (empty($required_rights[$module])) unset($required_rights[$module]);
		}
		if (!empty($required_rights)) {
			$query = "SELECT ID,NAME FROM _GROUPS";
			$groups = utilities::group_numeric_by_key($db->run_query($query),'ID');

			array_push($output['script'],
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				utilities::get_public_location(__DIR__ . '/js/create-module-rights.js'),
				"var groups = ".json_encode($groups,true).";");
			$output['css'][] = "{$local}style/jquery-ui.css";
			$output['html'] .= "
			<h4>Install Missing Rights</h4>
			<p>The following right(s) are not defined in the system, but should be.  Please assign the following rights:</p>
			<form class='assign-right'>
			";
			$required_rights = utilities::make_html_safe($required_rights,ENT_QUOTES);
			foreach($required_rights as $module=>$types) {
				$output['html'] .= "<h5>$module</h5><ul>";
				foreach($types as $type=>$rights) {
					foreach($rights as $right=>$right_info) {
						$output['html'] .= "<li><button module='$module' type='$type' right='".utilities::make_html_safe($right,ENT_QUOTES)."' title='{$right_info['description']}'>$type / $right</button></li>";
					}
				}
				$output['html'] .= "</ul>";
			}
			$output['html'] .= "</form>";
		}
		return $output;
	}

	/* What to do for standard views... */
	public static function view() {
		global $db,$local;
		$user = users::get_session_user();
		/* Confirm User has rights for this... */
		if (!$user->check_right('Modules','Administer','Administer Modules')) {
			header("Location: $local");
			exit();
			return;
		}

		$args = func_get_args();
		if (empty($args)) {
			return static::view_main();
		}

		else {
			$module = array_shift($args);
			/* Confirm User has rights for this... */
			if (!$user->check_right('Modules','Administer',"Administer $module")) {
				header("Location: " . static::get_module_url());
				exit();
				return;
			}

			$the_module = self::get_module_by_name($module);
			$module_menu = array();


			$helper = module::get_module_helpers($the_module->ID,'admin');
			if (empty($helper)) {
				header("Location: " . static::get_module_url());
				exit();
				return;
			}

			// Create a menu...
			if ($the_module->PARENT_MODULE_ID) {
				$parent_module = new module($the_module->PARENT_MODULE_ID);
				$module_menu = array_merge(
					array(
						$parent_module->NAME => static::get_module_url() . $parent_module->NAME
					),
					$parent_module->get_module_extensions()
				);
			}
			else {
				$module_menu = array_merge(
					array(
						$the_module->NAME => static::get_module_url() . $the_module->NAME

					),
					$the_module->get_module_extensions()
				);
			}

			// If the module_menu is more than 1 element deep, display it...
			$html = "";
			$css = array();
			if (count($module_menu) > 1) {
				$css[] = utilities::get_public_location(__DIR__ . '/style/admin.css');
				$html = "<ul id='admin_menu'>";
				foreach($module_menu as $text => $url) {

					if (is_array($url)) {
						$text = $url['NAME'];
						$url = static::get_module_url() . $url['NAME'];
					}
					$html .= "<li ".($text==$the_module->NAME ? "class='active'" : "")."><a href='$url'>$text</a></li>";
				}
				$html .= "</ul>";
			}

			$admin = call_user_func_array(array($helper['admin']['CLASS_NAME'],$helper['admin']['METHOD_NAME']),$args);
			$admin['html'] = $html . $admin['html'];
			if ($css) {
				if (isset($admin['css'])) {
					if (is_array($admin['css'])) $admin['css'] = array_merge($css,$admin['css']);
					else $admin['css'] = array_merge($css['css'],array($admin['css']));
				}
				else $admin['css'] = $css;
			}



			/* Return the admin() function of that module... */
			return $admin;
		}

	}

	/* Install the module... */
	public static function install() {
		/* Nothing to do...*/
	}

	/* Uninstall the module... */
	public static function uninstall() {}

	/* List of required rights... */
	public static function required_rights() {
		global $db;
		$required = array(
			'Modules' => array(
				'Administer' => array()
			)
		);
		$query = "SELECT NAME FROM _MODULES";
		$modules = utilities::group_numeric_by_key($db->run_query($query),'NAME');
		foreach($modules as $module)
			$required['Modules']['Administer']["Administer $module"] = array(
				'description' => "Allows a user to administer the $module module.",
				'default_groups' => array('Admin')
			);
		return $required;
	}

	/* Returns a multi-dimensional array of menu options for this module (including sub-menus) */
	public static function menu() {
		global $db;
		$menu = array(
			'Modules' => array(
				'args' => array(),
				'submenu' => array(),
				'right' => users::get_right_id('Modules','Administer','Administer Modules')
			)
		);
		$query = "SELECT NAME FROM _MODULES WHERE NAME <> ? ORDER BY NAME";
		$params = array(
			array("type" => "s", "value" => "Modules")
		);
		$modules = utilities::group_numeric_by_key($db->run_query($query,$params),'NAME');
		foreach($modules as $module) {
			$menu['Modules']['submenu'][$module] =
			array(
				'args' => array($module),
				'submenu' => array(),
				'right' => users::get_right_id('Modules','Administer',"Administer $module")
			);
		}
		return $menu;
	}

	/* given args[], returns a HREF to this menu item */
	public static function decode_menu($args) {
		$url = static::get_module_url();
		if (empty($args)) return $url;
		else $url .= $args[0];
		return $url;
	}




}
?>
