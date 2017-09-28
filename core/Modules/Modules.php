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
			if (!empty($request['modules_to_install'])) {
				foreach($request['modules_to_install'] as $module_info_file) {
					// read the file..
					$module_info = json_decode(file_get_contents($module_info_file),true);
					$module_info['Directory'] = dirname($module_info_file) . "/" ;
					if (!empty($module_info['Extends'])) {
						// Get Parent Module ID...
						$module_info['ModuleParentID'] = module::get_module_id($module_info['Extends']);
						if ($module_info['ModuleParentID'] === false) {
							Layout::set_message("Unable to install module [{$module_info['Module']}] - install module [{$module_info['Extends']}] first.","error");
							continue;
						}
					}
					if (!self::install_module($module_info)) {
						Layout::set_message("Unable to install module [{$module_info['Module']}].","error");
					} else {
						Layout::set_message("Successfully installed module [{$module_info['Module']}].","success");
					}
				}
			} else {
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
			}
		} else {
			$module = array_shift($args);
			if ($module == 'checkRights' || $module == 'checkTables') {
				$action = $module;
				$module = array_shift($args);

				if ($action == 'checkRights') {



					if (isset($request['rightGroups']['new'])) {
						$newRights = $request['rightGroups']['new'];
						unset($request['rightGroups']['new']);
					} else {
						$newRights = array();
					}

					if ($user->check_right('Users', 'Rights', 'Assign Rights')) {
						// first handle removed rights...
						foreach($request['rightGroups'] as $right_id => $groups) {
							// add non-existant group id in case no one will have that permission...
							unset($_SESSION[__CLASS__][$module]['moduleRightsList'][$right_id]);
							$query = "
							DELETE
							FROM _GROUPS_RIGHTS
							WHERE RIGHT_ID = ? AND GROUP_ID NOT IN (".substr(str_repeat("?,",count($groups)),0,-1).")";
							$params = array(
								array("type" => "i", "value" => $right_id)
							);
							foreach($groups as $group_id)
								array_push($params,array("type" => "i", "value" => $group_id));
								$db->run_query($query,$params);
						}
						if ($_SESSION[__CLASS__][$module]['moduleRightsList']) {
							// whatever rights remain, no groups have them
							$query = "DELETE FROM _GROUPS_RIGHTS WHERE RIGHT_ID IN (".substr(str_repeat("?,",count($_SESSION[__CLASS__][$module]['moduleRightsList'])),0,-1).")";
							$params = array();
							foreach($_SESSION[__CLASS__][$module]['moduleRightsList'] as $right_id => $_ignore) {
								array_push($params,array("type" => "i", "value" => $right_id));
							}
							$db->run_query($query,$params);
						}
						unset($_SESSION[__CLASS__][$module]['moduleRightsList']);

						layout::set_message("Successfully assigned module rights.","success");

						// and now assign rights..
						users::assign_rights($request['rightGroups']);
					}


					if ($newRights && $user->check_right('Users','Rights','Create Rights')) {
						// ..and now create and assign new rights
						$newRightAssignments = array();
						foreach($newRights as $rightDefinition => $groups) {
							$rightDefinition = unserialize($rightDefinition);
							list($right_type,$right_name,$right_description) = $rightDefinition;
							$right_id = users::create_right($module, $right_type, $right_name, $right_description);
							$newRightAssignments[$right_id] = $groups;
						}
						users::assign_rights($newRightAssignments);

						layout::set_message("Successfully created and assigned new module rights.","success");
					}

					return;

				} elseif ($action == 'checkTables') {
					if (!$user->check_right('Tables','Table Actions','Add Table')) return false;

					switch($request['checkTableAction']) {
						case 'CreateMissingTables':
							foreach($_SESSION[__CLASS__][$module]['MissingTables'] as $table_name => $table_definition) {
								$db->create_table($table_name, $table_definition['columns'], $table_definition['keys']);
							}
							unset($_SESSION[__CLASS__][$module]['MissingTables']);
							$cache = caching::get_site_cacher('Tables')->deleteAllModuleCache();
							layout::set_message("Successfully added missing tables.","success");
							return;
						case 'CreateMissingColumns':
							foreach($_SESSION[__CLASS__][$module]['MissingColumns'] as $table_name => $columns) {
								foreach($columns as $column_name => $column_definition) {
									$db->add_table_column($table_name, $column_name, $column_definition);
								}
							}
							unset($_SESSION[__CLASS__][$module]['MissingColumns']);
							$cache = caching::get_site_cacher('Tables')->deleteAllModuleCache();
							layout::set_message("Successfully added missing columns.","success");
							return;
						case 'CreateMissingIndexes':
							foreach($_SESSION[__CLASS__][$module]['MissingIndexes'] as $table_name => $missing_indexes) {
								foreach($missing_indexes as $key_type => $keys) {
									if ($key_type == 'PRIMARY') {
										$db->add_table_key($table_name,$key_type,$keys);
									} elseif ($key_type == 'FOREIGN') {
										foreach($keys as $column_name => $fk_definition) {
											$db->add_table_key($table_name,$key_type,array($column_name),$fk_definition);
										}
									} else {
										foreach($keys as $key_columns) {
											$db->add_table_key($table_name,$key_type,$key_columns);
										}
									}
								}
							}
							unset($_SESSION[__CLASS__][$module]['MissingIndexes']);
							$cache = caching::get_site_cacher('Tables')->deleteAllModuleCache();
							layout::set_message("Successfully added missing indexes.","success");
							return;
					}
				}

				layout::set_message("$action POST functionality has not been implemented yet.",'error');


				return;
			} else {
				if (!$user->check_right('Modules','Administer', "Administer $module")) return false;
				$helper = module::get_module_helpers(module::get_module_id($module),'admin');

				$class_name = module::get_module_class($module);
				if ($class_name===false) return false;
				return call_user_func_array(array($helper['admin']['CLASS_NAME'],'post'),array($args,$request));
			}
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

	public static function display_slug($slug,$row) {
		return is_null($slug) ? $row['NAME'] : $slug;
	}

	public static function get_admin_link($module) {
		return '<a href="'.static::get_module_url().$module.'">'.$module.'</a>';
	}

	private static function view_main() {
		global $db,$local;
		$output = array(
			'html' => '<h1>Module Administration</h1>',
			'script' => array(),
			'css' => array()
		);
		$user = users::get_session_user();

		/* List Modules, with links to Check Tables, Keys, Required Rights... */
		$query = "
			SELECT NAME,SLUG
			FROM _MODULES
		";
		$params = "";
		$modules_table = new pagingtable_widget($query);
		$modules_table->set_columns(array(
			array("column" => "NAME", "display_name" => "Module Name", "display_function" => array(__CLASS__,"get_admin_link")),
			array("column" => "SLUG", "display_name" => "Slug", "display_function" => array(__CLASS__,"display_slug")),
			array("column" => "NAME", "display_name" => "", "display_format" => '<a href="'.static::get_module_url().'checkRights/%s">Check Rights</a>'),
			array("column" => "NAME", "display_name" => "", "display_format" => '<a href="'.static::get_module_url().'checkTables/%s">Check Tables</a>'),
		));

		$paging_output = $modules_table->build_table();
		foreach($paging_output as $type => $out) {
			if ($type == 'html') {
				$output[$type] .= $out;
			} else {
				$output[$type] = array_merge($output[$type],$out);
			}
		}

		/* Install Module */
		$output['html'] .= "
			<h2>Install New Module</h2>
				<form action='' method='post' enctype='multipart/form-data'>
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

		// search for uninstalled modules...
		$module_files = utilities::recursive_glob('*.module');
		/* For each .module file, convert to object from JSON */
		$every_module = array();
		$idx=0;
		foreach($module_files as $module_info_file) {
			$every_module[$idx] = json_decode(file_get_contents($module_info_file),true);
			if (is_null($every_module[$idx])) die("Invalid .module file.  Please see $module_info_file and fix.");

			// check if installed..
			if (modules::get_module_id($every_module[$idx]['Module']) !== false) {
				unset($every_module[$idx]);
			} else {
				$every_module[$idx]['.module'] = $module_info_file;
				// check if any dependencies...
				$idx++;
			}

		}

		if ($every_module) {
			$output['html'] .= "<h2>Install Loaded Module</h2><form method='post'><ul style='list-style-type: none;'>";
			foreach($every_module as $module_info)
			{
				$output['html'] .= <<<MODULE
	<li><label><input type="checkbox" name="modules_to_install[]" value="{$module_info['.module']}" /> {$module_info['Module']}</label></li>
MODULE;
			}
			$output['html'] .= "</ul><input type='submit' value='Install Module(s)' /></form>";
		}

		return $output;
	}

	private static function view_check_rights($module) {
		global $db;

		$user = users::get_session_user();

		/* Confirm User has rights for this... */
		if (!$user->check_right('Users','Rights','Create Rights') && !$user->check_right('Users', 'Rights', 'Assign Rights')) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}

		$output = array(
			'html' => "<h2>{$module} Module Rights</h2>",
			'css' => ".table-body-header {font-weight: bold; text-align: center; background: grey;}",
			'script' => array()
		);

		$the_module = self::get_module_by_name($module);
		$required_rights = call_user_func_array(array($the_module->CLASS_NAME,'required_rights'),array());

		if (empty($required_rights)) {
			$output['html'] .= "<p>This module has no rights.</p>";
			$output['html'] .= "<p><a href='".modules::get_module_url()."'>&lt;&lt;Back</a></p>";
			return $output;
		}
		$missing_rights = array();
		$current_rights = array();
		foreach($required_rights as $module => $right_types) {
			foreach($right_types as $right_type => $rights) {
				foreach($rights as $right_name => $right) {
					$right_id = users::get_right_id($module,$right_type,$right_name);
					if ($right_id !== false) {
						$current_rights[$module][$right_type][$right_name] = $right_id;
						$_SESSION[__CLASS__][$module]['moduleRightsList'][$right_id] = true;
					} else {
						$missing_rights[$module][$right_type][$right_name] = $right;
					}
				}
			}
		}

		$all_groups = users::get_groups();
		foreach($all_groups as &$group) {
			$group['rights'] = users::get_group_rights($group['ID']);
		}
		unset($group);

		$output['html'] .= "<form action='' method='post'>";
		if ($user->check_right('Users', 'Rights', 'Assign Rights')) {
			$output['html'] .= "<h3>Existing Rights</h3>";
			$output['html'] .= "<table><thead><tr>";
			$output['html'] .= "<th></th><th>Right</th>";
			foreach($all_groups as $group) {
				$output['html'] .= "<th>{$group['NAME']}</th>";
			}
			$output['html'] .= "</tr></thead><tbody>";

			foreach($current_rights as $module => $right_types) {
				$output['html'] .= "<tr class='table-body-header'><td colspan='100%'>{$module}</td></tr>";
				foreach($right_types as $right_type => $rights) {
					$output['html'] .= "<tr><td class='table-body-header' rowspan='".count($rights)."'>{$right_type}</td>";
					$open_row = true;
					foreach($rights as $right_name => $right_id) {
						if (!$open_row) {
							$output['html'] .= "<tr>";
							$open_row = false;
						}
						$output['html'] .= "<td>{$right_name}</td>";
						foreach($all_groups as $group) {
							$checked = in_array($right_id,$group['rights']) ? 'checked="checked"' : '';
							$output['html'] .= "<td><input type='checkbox' name='rightGroups[$right_id][]' value='{$group['ID']}' $checked /></td>";
						}
						$output['html'] .= "</tr>";
					}
				}
			}
			$output['html'] .= "</tbody></table>";
		}

		if ($missing_rights && $user->check_right('Users', 'Rights', 'Create Rights')) {
			$output['html'] .= "<h3>Missing Rights</h3>";
			$output['html'] .= "<p>These permissions are required by the {$module} module, but don't exist in the system yet.  Assign them here.</p>";
			$output['html'] .= "<table><thead><tr>";
			$output['html'] .= "<th></th><th>Right</th>";
			foreach($all_groups as $group) {
				$output['html'] .= "<th>{$group['NAME']}</th>";
			}
			$output['html'] .= "</tr></thead><tbody>";

			foreach($missing_rights as $module => $right_types) {
				$output['html'] .= "<tr class='table-body-header'><td colspan='100%'>{$module}</td></tr>";
				foreach($right_types as $right_type => $rights) {
					$output['html'] .= "<tr><td class='table-body-header' rowspan='".count($rights)."'>{$right_type}</td>";
					$open_row = true;
					foreach($rights as $right_name => $right_info) {
						if (!$open_row) {
							$output['html'] .= "<tr>";
							$open_row = false;
						}
						$output['html'] .= "<td><strong>{$right_name}</strong><br /><em>{$right_info['description']}</em></td>";
						foreach($all_groups as $group) {
							$checked = in_array($group['NAME'],$right_info['default_groups']) ? 'checked="checked"' : '';
							$name_description_string = serialize(array($right_type,$right_name,$right_info['description']));
							$output['html'] .= "<td><input type='checkbox' name='rightGroups[new][{$name_description_string}][]' value='{$group['ID']}' $checked /></td>";
						}
						$output['html'] .= "</tr>";
					}
				}
			}
			$output['html'] .= "</table>";
		}

		$output['html'] .= "<p><input type='submit' value='Save Rights' /></p>";

		$output['html'] .= "</form>";
		$output['html'] .= "<p><a href='".modules::get_module_url()."'>&lt;&lt;Back</a></p>";
		return $output;
	}

	private static function view_check_tables($module) {
		$user = users::get_session_user();
		/* Confirm User has rights for this... */
		if (!$user->check_right('Tables','Table Actions','Add Table')) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}

		$output = array(
		'html' => "<h2>{$module} Module Tables</h2>",
		'script' => array()
		);

		$the_module = self::get_module_by_name($module);
		$required_tables = call_user_func_array(array($the_module->CLASS_NAME,'required_tables'),array());

		if (empty($required_tables)) {
			$output['html'] .= "<p>This module has no required tables.</p>";
			return $output;
		}

		$missing_tables = array();
		$missing_columns = array();
		$missing_indexes = array();
		foreach($required_tables as $table_name => $table_definition) {
			// does table exist?
			if (tables::is_table($table_name)) {
				$table = new tables($table_name);
				$current_columns = utilities::group_numeric_by_key($table->get_columns(),'COLUMN_NAME');
				$current_keys = tables::get_table_keys($table_name);

				// do columns exist?
				foreach($table_definition['columns'] as $column_name => $column_definition) {
					if (!isset($current_columns[$column_name])) {
						// column missing
						$missing_columns[$table_name][$column_name] = $column_definition;
					}
				}

				// do keys exist?
				foreach($table_definition['keys'] as $key_type => $keys) {
					if ($key_type == 'PRIMARY') {
						if (!isset($current_keys[$key_type]) || serialize($current_keys[$key_type]) != serialize($keys)) {
							$missing_indexes[$table_name][$key_type] = $keys;
						}
					} elseif ($key_type == 'FOREIGN') {
						foreach($keys as $column => $fk_definition) {
							ksort($fk_definition);
							ksort($current_keys[$key_type][$column]);
							if (!isset($current_keys[$key_type][$column]) || serialize($current_keys[$key_type][$column]) != serialize($fk_definition)) {
								$missing_indexes[$table_name][$key_type][$column] = $fk_definition;
							}
						}
					} else {
						// may or may not be unique
						foreach($keys as $key_columns) {
							$serializedDefinition = serialize($key_columns);
							$key_found = false;

							if (isset($current_keys[$key_type])) {
								foreach($current_keys[$key_type] as $key_definition) {
									if (serialize($key_definition) == $serializedDefinition) {
										$key_found = true;
										break;
									}
								}
							}

							if (!$key_found) {
								$missing_indexes[$table_name][$key_type][] = $key_columns;
							}
						}
					}
				}
			} else {
				// table doesn't exist - output table information...
				$missing_tables[$table_name] = $table_definition;
			}
		}

		$output['html'] .= "<form method='post' action=''>";

		if ($missing_tables) {
			$_SESSION[__CLASS__][$the_module->NAME]['MissingTables'] = $missing_tables;
			$output['html'] .= "<h3>Missing Tables:</h3><ul>";
			foreach($missing_tables as $table_name => $table_definition) {
				$output['html'] .= "<li>{$table_name}</li>";
			}
			$output['html'] .= "</ul>";

			$output['html'] .= "<button type='submit' name='checkTableAction' value='CreateMissingTables'>Create Missing Tables</button>";
		}

		if ($missing_columns) {
			$_SESSION[__CLASS__][$the_module->NAME]['MissingColumns'] = $missing_columns;
			$output['html'] .= "<h3>Missing Columns:</h3><ul>";
			foreach($missing_columns as $table_name => $table_columns) {
				$output['html'] .= "<li>{$table_name}<ul>";
				foreach($table_columns as $column_name => $column_definition) {
					$output['html'] .= "<li>{$column_name}</li>";
				}
				$output['html'] .= "</ul></li>";
			}
			$output['html'] .= "</ul>";

			$output['html'] .= "<button type='submit' name='checkTableAction' value='CreateMissingColumns'>Create Missing Columns</button>";
		}

		if ($missing_indexes) {
			$_SESSION[__CLASS__][$the_module->NAME]['MissingIndexes'] = $missing_indexes;
			$output['html'] .= "<h3>Missing Indexes:</h3><ul>";
			foreach($missing_indexes as $table_name => $key_types) {
				$output['html'] .= "<li>{$table_name}<ul>";
				foreach($key_types as $key_type => $keys) {
					$output['html'] .= "<li>{$key_type} KEY(s) ON:<ul>";
					if ($key_type == 'PRIMARY') {
						$output['html'] .= "<li>".implode(",",$keys)."</li>";
					} elseif ($key_type == 'FOREIGN') {
						foreach($keys as $key_column => $fk_definition) {
							$output['html'] .= "<li>$key_column (REFERENCES {$fk_definition['table']})</li>";
						}
					} else {
						foreach($keys as $key_definition) {
							$output['html'] .= "<li>".implode(",",$key_definition)."</li>";
						}
					}
					$output['html'] .= "</ul></li>";
				}
				$output['html'] .= "</ul></li>";
			}
			$output['html'] .= "</ul>";

			$output['html'] .= "<button type='submit' name='checkTableAction' value='CreateMissingIndexes'>Create Missing Indexes</button>";
		}

		$output['html'] .= "</form>";
		if (empty($missing_tables) && empty($missing_columns) && empty($missing_indexes)) {
			$output['html'] .= "<p>Table definitions are up to date.</p>";
		}
		$output['html'] .= "<p><a href='".modules::get_module_url()."'>&lt;&lt;Back</a></p>";

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
			if ($module == 'checkRights') {
				return self::view_check_rights(array_shift($args));
			} elseif ($module == 'checkTables') {
				return self::view_check_tables(array_shift($args));
			}

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
	public static function required_tables() { return array(); }

	public static function get_all_modules()
	{
		global $db;
		$query = "SELECT ID,NAME FROM _MODULES";
		return utilities::group_numeric_by_key($db->run_query($query), 'ID');
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
