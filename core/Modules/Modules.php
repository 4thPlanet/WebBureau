<?php

class modules extends module {
	/* Constructor (nothing is really needed) */
	public function __construct() {}
	
	/* Admin function (as this is the admin class, there's no way to do this except for through the view method) */
	public static function admin() {}
	
	/* What to do on post requests... */
	public static function post($args,$request) {
		$user = users::get_session_user();
		if (!$user->check_right('Modules','Administer','Administer Modules')) return false;
		if (empty($args)) {
			$zip = new ZipArchive;
			$success = true;
			if ($zip->open($_FILES['module_upload']['tmp_name']) === TRUE) {
				$folder_name = substr($_FILES['module_upload']['name'],0,strrpos($_FILES['module_upload']['name'],"."));
				$zip->extractTo("{$_SERVER['DOCUMENT_ROOT']}/custom/$folder_name");
				$zip->close();
				/* Get list of all currently defined classes... */
				$current_classes = get_declared_classes();
				/* Now load every file in /custom/$folder_name... */
				$files = scandir("{$_SERVER['DOCUMENT_ROOT']}/custom/$folder_name");
				foreach($files as $file) {
					/* If PHP file, include... */
					if (!preg_match('/\.php?/',$file)) continue;
					include("{$_SERVER['DOCUMENT_ROOT']}/custom/$folder_name/$file");
				}
				$new_classes = array_diff(get_declared_classes(),$current_classes);
				foreach($new_classes as $class) {
					/* Discard if widget... */
					if (is_subclass_of($class,'widget')) continue;
					$success = $success && call_user_func_array(array($class,'install'),array());
				}
				if ($success) layout::set_message('New module installed.','info');
				else layout::set_message('Unable to install module.','error');
			} else {
				layout::set_message('Unable to install read .zip file to install class.','error');
			}
		} else {
			$module = array_shift($args);
			if (!$user->check_right('Modules','Administer', "Administer $module")) return false;
			$class_name = module::get_module_class($module);
			if ($class_name===false) return false;
			return call_user_func_array(array($class_name,'admin_post'),array($args,$request));
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
			$class_name = module::get_module_class($module);
			if ($class_name===false) return false;
			/* Return the ajax() function of that module... */
			return call_user_func_array(array($class_name,'ajax'),array($args,$request));
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
		$modules = group_numeric_by_key($db->run_query($query),'CLASS_NAME');
		$required_rights = array();
		foreach($modules as $class) {
			$required_rights = array_merge_recursive($required_rights,call_user_func(array($class,'required_rights')));
		}
		foreach($required_rights as $module=>$types) {
			foreach($types as $type=>$rights) {
				foreach($rights as $right=>$description) {
					if (users::get_right_id($module,$type,$right)!==false) unset($required_rights[$module][$type][$right]);
				}
				if (empty($required_rights[$module][$type])) unset($required_rights[$module][$type]);
			}
			if (empty($required_rights[$module])) unset($required_rights[$module]);
		}
		if (!empty($required_rights)) {
			$query = "SELECT ID,NAME FROM _GROUPS";
			$groups = group_numeric_by_key($db->run_query($query),'ID');
			
			array_push($output['script'],
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				get_public_location(__DIR__ . '/js/create-module-rights.js'),
				"var groups = ".json_encode($groups,true).";");
			$output['css'][] = "{$local}style/jquery-ui.css";
			$output['html'] .= "
			<h4>Install Missing Rights</h4>
			<p>The following right(s) are not defined in the system, but should be.  Please assign the following rights:</p>
			<form class='assign-right'>
			";
			$required_rights = make_html_safe($required_rights,ENT_QUOTES);
			foreach($required_rights as $module=>$types) {
				$output['html'] .= "<h5>$module</h5><ul>";
				foreach($types as $type=>$rights) {
					foreach($rights as $right=>$description) {
						$output['html'] .= "<li><button module='$module' type='$type' right='".make_html_safe($right,ENT_QUOTES)."' title='$description'>$type / $right</button></li>";
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

			$query = "SELECT CLASS_NAME FROM _MODULES WHERE NAME = ?";
			$params = array(
				array("type" => "s", "value" => $module)
			);
			$result = $db->run_query($query,$params);
			if (empty($result)) {
				header("Location: " . static::get_module_url());
				exit();
				return;
			}
			$class_name = $result[0]['CLASS_NAME'];
			/* Return the admin() function of that module... */
			return call_user_func_array(array($class_name,'admin'),$args);
		}
			
	}
	
	/* Install the module... */
	public static function install() {
		global $db;
		/* Create the Modules Module... */
		$query = "
			INSERT INTO _MODULES (NAME,DESCRIPTION,IS_CORE,FILENAME,CLASS_NAME)
			SELECT tmp.NAME, tmp.DESCRIPTION, tmp.IS_CORE, ?,?
			FROM (SELECT 'Modules' as Name, 'Administers Other Modules' as DESCRIPTION, 1 as IS_CORE) tmp
			LEFT JOIN _MODULES M ON tmp.NAME = M.NAME
			WHERE M.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__)
		);
		$db->run_query($query,$params);
		/* Set up rights... */
		
		/* Create RIGHT TYPE */
		$query = "
			INSERT INTO _RIGHT_TYPES (MODULE_ID,NAME)
			SELECT M.ID, ?
			FROM _MODULES M
			LEFT JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			WHERE M.NAME = ? AND T.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Administer"),
			array("type" => "s", "value" => "Administer"),
			array("type" => "s", "value" => "Modules")
		);
		$db->run_query($query,$params);
		
		/* Create rights for each module */
		$query = "
			INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
			SELECT T.ID, CONCAT('Administer ',A.NAME), CONCAT('Allows a user to administer the ',A.NAME,' module.')
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			JOIN _MODULES A ON 1=1
			LEFT JOIN _RIGHTS R ON 
				T.ID = R.RIGHT_TYPE_ID AND
				R.NAME = CONCAT('Administer ',A.NAME)
			WHERE M.NAME = ? AND T.NAME = ? AND R.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Modules"),
			array("type" => "s", "value" => "Administer"),
		);
		$db->run_query($query,$params);
		
		/* Give rights to Admin group... */
		$query = "
			INSERT INTO _GROUPS_RIGHTS (GROUP_ID,RIGHT_ID)
			SELECT G.ID, R.ID
			FROM _GROUPS G
			JOIN _MODULES M ON M.NAME = ?
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID
			LEFT JOIN _GROUPS_RIGHTS GR ON
				G.ID = GR.GROUP_ID AND
				R.ID = GR.RIGHT_ID
			WHERE G.NAME = ? AND GR.GROUP_ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Modules"),
			array("type" => "s", "value" => "Administer"),
			array("type" => "s", "value" => "Admin")
		);
		$db->run_query($query,$params);
		return true;
	}
	
	/* Uninstall the module... */
	public static function uninstall() {}
	
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
		$modules = group_numeric_by_key($db->run_query($query,$params),'NAME');
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
