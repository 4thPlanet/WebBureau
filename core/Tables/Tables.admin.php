<?php
/* The primary difference between Tables and Tables Admin is the lack of slugs (since there's no need to make slugs pretty in the admin side)...*/

class tables_admin extends tables {
	public static function ajax($args,$request) {
		switch($request['ajax']) {
			case 'does-table-exist':
				return static::is_table($request['table_name']);
				break;
		}
	}

	protected static function create_table($table_data) {
		global $db, $s_user;
		if (!$s_user->check_right('Tables','Table Actions','Add Table')) return;

		$table_name = $table_data['table-name'];
		if (!preg_match('/^\d*[a-zA-Z]\w*/',$table_name)) {
			Layout::set_message('Invalid Table Name','error');
			return;
		}
		if (empty($table_data['fields'])) {
			Layout::set_message('At least one field must be present.','error');
			return;
		}
		$primary_keys = array();
		$foreign_keys = array();
		$query = "CREATE TABLE $table_name (";

		foreach($table_data['fields'] as $field=>$properties) {
			if (!preg_match('/^\d*[a-zA-Z]\w*/',$field)) {
				Layout::set_message("$field is an invalid field name",'error');
				return;
			}

			$properties = array_merge(array('is-primary-key' => '', 'not-null' => '', 'auto-increment' => '', 'is_foreign_key' => 0, 'max-length' => ''), $properties);
			if (!empty($properties['max-length'])) {
				$properties['max-length'] = "({$properties['max-length']})";
			}

			$query .= "$field {$properties['field-type']}{$properties['max-length']} {$properties['not-null']} {$properties['auto-increment']},";
			if ($properties['is-primary-key'])
				$primary_keys[] = $field;
			if ($properties['is_foreign_key']) {
				$PK = static::get_primary_key($properties['reference-table']);
				$foreign_keys[$field] = array('TABLE_NAME' => $properties['reference-table'], 'COLUMN_NAME' => $PK[0]['COLUMN_NAME']);
			}
		}
		$query .= "PRIMARY KEY (".implode(",",$primary_keys).")";
		if (!empty($foreign_keys)) {
			foreach($foreign_keys as $fkey=>$reference) {
				$query .= ", FOREIGN KEY ($fkey) REFERENCES {$reference['TABLE_NAME']}({$reference['COLUMN_NAME']})";
			}
		}
		$query .= ")  ENGINE=INNODB;";
		$result = $db->run_query($query);

		if (!static::is_table($table_name)) {
			Layout::set_message(print_r($db->GetErrors(),true),"error");
			return;
		}

		if ($s_user->check_right('Users','Rights','Create Rights') && $s_user->check_right('Users','Rights','Assign Rights')) {
			$new_rights = array();
			foreach($table_data['rights'] as $right_name=>$assign_to) {
				switch($right_name) {
					case 'Add':
						$description = "Allows user to add to the $table_name table.";
						break;
					case 'Edit':
						$description = "Allows user to edit the $table_name table.";
						break;
					case 'Delete':
						$description = "Allows user to delete from the $table_name table.";
						break;
					case 'View':
						$description = "Allows user to view the $table_name table.";
						break;
				}
				$right_id = users::create_right('Tables',$table_name,$right_name,$description);
				$new_rights[$right_id] = $assign_to;
			}
			if (!empty($new_rights)) {
				users::assign_rights($new_rights);
				$s_user->reload_rights();
			}
		}

		Layout::set_message("$table_name created.","success");
		header("Location: " . static::get_module_url() . "$table_name");
		exit;
		return;
	}

	public static function post($args,$request) {
		$table_name = array_shift($args);

		if ($table_name=='add-new-table') return static::create_table($request);

		$table = new Tables($table_name);
		if (!empty($args)) {
			$id = array_shift($args);
		} else {
			$id = null;
		}

		switch($id) {
			case 'meta':
				$meta_table = new Tables('_TABLE_INFO');
				$meta_columns = $meta_table->get_columns();
				$meta_columns_data = array(
					'meta' => $request['meta']
				);
				foreach($meta_columns as $column) {
					if (!empty($request["{$column['COLUMN_NAME']}_null"]))
						$meta_columns_data[$column['COLUMN_NAME']] = null;
					elseif (isset($request[$column['COLUMN_NAME']]))
						$meta_columns_data[$column['COLUMN_NAME']] = $request[$column['COLUMN_NAME']];
					elseif (in_array($column['DATA_TYPE'],array('bit','boolean')))
						$meta_columns_data[$column['COLUMN_NAME']] = 0;
					else
						$meta_columns_data[$column['COLUMN_NAME']] = null;
				}

				$table->update_table_info($meta_columns_data);
				Layout::set_message('Table meta data saved','success');
				return;
			case 'new':
				break;
			default:
				$table->set_id($id);
		}

		/* Before we save the data, we need to map the request data to actual columns... */
		$columns = $table->get_columns();
		$data = array();
		foreach($columns as $idx=>$column) {
			if ($column['IS_AUTO_INCREMENT']) continue;
			$data[$column['COLUMN_NAME']] = empty($request["col{$idx}_null"]) ? $request["col$idx"] : null;

		}
		$result = $table->save($data);
		if ($result===false) {
			Layout::set_message('Unable to save table data.  Please try again.','error');
		} else {
			Layout::set_message('Table data successfully saved.','success');
			if ($result === true) {
				return;
			} elseif (!empty($result)) {
				header("Location: " . static::get_module_url() . "$table_name/$result");
				exit();
				return;
			} elseif ($id=='new') {
				// String PK...
				$PK = $table->get_key();
				$PK = $PK[0]['COLUMN_NAME'];
				$key = $data[$PK];
				header("Location: " . static::get_module_url() . "$table_name/$key");
				exit();
				return;
			}
		}

	}

	public static function view($table='',$id='',$action='') {
		if (empty($table)) {
			return static::list_tables();
		} elseif ($table=='add-new-table') {
			return static::add_new_table();
		} elseif (empty($id)) {
			return static::list_table_records($table);
		} elseif ($id=='meta') {
			return static::edit_table_meta($table);
		} else {
			return static::edit_record($table,$id,$action);
		}

	}

	public static function get_module_url() {
		return modules::get_module_url() . "Tables/";
	}

	protected static function add_new_table() {
		global $local,$db, $s_user;

		if (!$s_user->check_right('Tables','Table Actions','Add Table')) {
			header("Location: " . static::get_module_url() );
			exit;
			return;
		}

		$output = array('html' => '<h3>Add New Table</h3>');

		$output['script'] = array(
			"{$local}script/jquery.min.js",
			utilities::get_public_location(__DIR__ . "/js/create-table.js")
		);

		$existing_tables = static::get_users_viewable_tables();
		$output['script'][] = "var tables = ".json_encode($existing_tables).";";

		$output['css'] = array(utilities::get_public_location(__DIR__ . '/style/new-table.css'));

		$types = array(
			'int',
			'varchar',
			'text',
			'float',
			'money',
			'date',
			'datetime'
		);


		$output['html'] .= "
			<form id='new-table-form' method='post' action=''>
				<p>
					<label for='table-name'>Table Name:</label>
					<input id='table-name' name='table-name' placeholder='Table Name' />
				</p>

				<table>
					<thead>
						<tr>
							<th></th>
							<th>Field</th>
							<th>Type</th>
							<th>Extra</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td colspan='100%'>
								<button type='button' id='add-new-field'>Add Field</button>
							</td>
						</tr>
					</tbody>
				</table>

				<h4>Table Rights:</h4>
				<table>
					<thead>
						<tr>
							<th>Group</th>
							<th>Add</th>
							<th>Edit</th>
							<th>Delete</th>
							<th>View</th>
						</tr>
					</thead>
					<tbody>";
		if ($s_user->check_right('Users','Rights','Create Rights') && $s_user->check_right('Users','Rights','Assign Rights')) {
			$groups = users_admin::get_groups();
			foreach($groups as $group)
				$output['html'] .= "
						<tr>
							<td>{$group['NAME']}</td>
							<td><input type='checkbox' value='{$group['ID']}' name='rights[Add][]' /></td>
							<td><input type='checkbox' value='{$group['ID']}' name='rights[Edit][]' /></td>
							<td><input type='checkbox' value='{$group['ID']}' name='rights[Delete][]' /></td>
							<td><input type='checkbox' value='{$group['ID']}' name='rights[View][]' /></td>
						</tr>";
		}

		$output['html'] .= "
					</tbody>
				</table>
				<p style='text-align: right;'>
					<input type='submit' value='Create Table' />
				</p>
			</form>
		";

		return $output;
	}

	protected static function list_tables() {
		global $db;
		$output = array('html' => '<h3>Table Administration</h3>');

		$output['html'] .= "<p><a href='".static::get_module_url()."add-new-table'>Add New Table</a></p>";
		$output['html'] .= "<ol>";
		$all_tables = static::get_users_viewable_tables();
		foreach($all_tables as $table) {
			$output['html'] .= "<li><a href='".static::get_module_url()."$table'>$table</a></li>";
		}
		$output['html'] .= "</ol>";

		return $output;

	}

	public static function build_edit_delete_links($id,$row,$table)
	{
		global $local,$s_user;
		$updateLink = "";
		$deleteLink = "";

		$PK = static::get_primary_key($table);
		$id = $row[$PK[0]['COLUMN_NAME']];

		if ($s_user->check_right('Tables',$table,'Delete'))
			$deleteLink = "<a href='".static::get_module_url()."$table/$id/delete' title='Click here to delete this row.'><img src='{$local}images/icon-delete.png' alt='Delete Row' /></a>";
		if ($s_user->check_right('Tables',$table,'Edit'))
			$updateLink = "<a href='".static::get_module_url()."$table/$id' title='Click here to edit this row.'><img src='{$local}images/icon-edit.png' alt='Edit Row' /></a>";
		return "$updateLink $deleteLink";
	}

	protected static function list_table_records($table_name) {
		global $local,$db,$s_user;
		if (!$s_user->check_right('Tables',$table_name,'View')) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		$output = array('html' => "<h3>$table_name Administration</h3>");
		$table = new Tables($table_name);
		$output['html'] .= "<p><a href='".static::get_module_url() . "$table_name/meta'>Click here to edit Table Info (Display, metas, etc.).</a></p>";
		if ($s_user->check_right('Tables',$table_name,'Add'))
			$output['html'] .= "<p><a href='".static::get_module_url() . "$table_name/new'>Add new record.</a></p>";

		extract($table->get_records_sql());

		$paging_table = pagingtable_widget::load_paging($query,$params);
		if ($paging_table === false)
			$paging_table = new pagingtable_widget($query,$params);

		$columns = array(
			array('column' => null, 'display_name' => '', 'display_function' => array(__CLASS__,'build_edit_delete_links'), 'display_function_add_params' => array($table_name))
		);

		foreach ($table->columns as $column) {
			$column_info = array(
				'column' => $column['COLUMN_NAME'],
				'display_name' => $column['COLUMN_NAME']
			);
			if (!empty($column['REFERENCED_TABLE_NAME']) && !empty($column['REFERENCED_COLUMN_NAME'])) {
				$column_info['display_function'] = array(__CLASS__,'build_foreign_key_link');
				$column_info['display_function_add_params'] = array($column);
			} elseif ($column['DATA_TYPE'] == 'text') {
				$column_info['display_function'] = array(__CLASS__,'shorten_text_fields');
				$column_info['nowrap'] = false;
			}

			$columns[] = $column_info;

		}
		$paging_table->set_columns($columns);
		$paging_table_output = $paging_table->build_table();
		foreach($paging_table_output as $type => $paging_output) {
			if ($type == 'html')
				$output['html'] .= $paging_output;
			else {
				if (!isset($output[$type])) $output[$type] = array();
				$output[$type] = array_merge($output[$type],$paging_output);
			}
		}

		$output['html'] .= "<p><a href='".static::get_module_url()."'>Return to Table Listing...</a></p>";

		return $output;
	}

	protected static function edit_table_meta($table_name) {
		global $local,$db;
		$output = array("html" => "<h3>$table_name Meta</h3>","css" => array(),'script' => array());

		array_push(
			$output['script'],
			"{$local}script/jquery.min.js",
			"{$local}script/jquery-ui.min.js",
			"{$local}script/ckeditor/ckeditor.js",
			"{$local}script/ckeditor/adapters/jquery.js",
			utilities::get_public_location(__DIR__ . '/js/table-metas.js')
		);

		array_push(
			$output['css'],
			"{$local}style/jquery-ui.css",
			utilities::get_public_location(__DIR__ . "/style/table-meta.css")
		);

		$query = "
			SELECT *
			FROM _TABLE_INFO
			WHERE TABLE_NAME = ?
		";
		$params = array(
			array("type" => "s", "value" => $table_name)
		);
		$result = $db->run_query($query,$params);
		if (!empty($result)) {
			$table_info = utilities::make_html_safe($result[0],ENT_QUOTES);
			if (!is_null($table_info['DETAILED_MENU_OPTIONS'])) $table_info['DETAILED_MENU_OPTIONS'] = $table_info['DETAILED_MENU_OPTIONS'] ? 'checked="checked"' : '';
			if (!is_null($table_info['LINK_BACK_TO_TABLE'])) $table_info['LINK_BACK_TO_TABLE'] = $table_info['LINK_BACK_TO_TABLE'] ? 'checked="checked"' : '';
			if (!is_null($table_info['LINK_TO_ALL_TABLES'])) $table_info['LINK_TO_ALL_TABLES'] = $table_info['LINK_TO_ALL_TABLES'] ? 'checked="checked"' : '';
		}
		else
			$table_info = array_fill_keys(array(
				'SLUG',
				'SHORT_DISPLAY',
				'PREVIEW_DISPLAY_BEFORE',
				'PREVIEW_DISPLAY',
				'PREVIEW_DISPLAY_AFTER',
				'FULL_DISPLAY',
				'ROW_DISPLAY_MAX',
				'LINK_BACK_TO_TABLE',
				'DETAILED_MENU_OPTIONS',
				'DEFAULT_ORDER',
				'FILTER_COLUMN',
				'LINK_TO_ALL_TABLES'
			),null);

		$null_cbs = array();
		foreach($table_info as $key=>$value)
			$null_cbs[$key] = is_null($value) ? 'checked="checked"' : '';

		$query = "
			SELECT META_NAME, META_CONTENT
			FROM _TABLE_METAS
			WHERE TABLE_NAME = ?
		";
		$params = array(
			array("type" => "s", "value" => $table_name)
		);
		$metas = utilities::group_numeric_by_keys($db->run_query($query,$params),array('META_NAME','META_CONTENT'),true);
		if (!empty($metas)) {
			foreach($metas as &$meta)
				$meta = $meta[0];

			if (!isset($metas['description']))
				$metas['description'] = "";
			if (!isset($metas['keywords']))
				$metas['keywords'] = "";
		} else {
			$metas = array(
				'description' => '',
				'keywords' => ''
			);
		}
		$columns = static::get_table_columns($table_name);
		$sorting_options = "<option value=''>Please Select...</option>";
		$filter_options = "<option value=''>Please Select...</option>";
		foreach($columns as $column) {
			$asc_selected = $table_info['DEFAULT_ORDER'] == "{$column['COLUMN_NAME']} ASC" ? "selected='selected'" : "";
			$dsc_selected = $table_info['DEFAULT_ORDER'] == "{$column['COLUMN_NAME']} DESC" ? "selected='selected'" : "";
			$filter_selected = $table_info['FILTER_COLUMN'] == $column['COLUMN_NAME'] ? "selected='selected'" : "";
			$sorting_options .= "<option value='{$column['COLUMN_NAME']} ASC' $asc_selected>{$column['COLUMN_NAME']} ASC</option>";
			$sorting_options .= "<option value='{$column['COLUMN_NAME']} DESC' $dsc_selected>{$column['COLUMN_NAME']} DESC</option>";
			$filter_options .= "<option value='{$column['COLUMN_NAME']}' $filter_selected>{$column['COLUMN_NAME']}</option>";
		}

		// Really not a fan of hardcoding here
		$output['html'] .=<<<TTT
			<form method="post" action="">
				<table>
					<tr>
						<td colspan="3">Display Meta</td>
					</tr>
					<tr>
						<td>Slug:</td>
						<td><input type="checkbox" class="null" id="SLUG_null" name="SLUG_null" value="1" {$null_cbs['SLUG']} /></td>
						<td><input name="SLUG" value="{$table_info['SLUG']}" /></td>
					</tr>
					<tr>
						<td>Short Display:</td>
						<td><input type="checkbox" class="null" id="SHORT_DISPLAY_null" name="SHORT_DISPLAY_null" value="1" {$null_cbs['SHORT_DISPLAY']} /></td>
						<td><input name="SHORT_DISPLAY" value="{$table_info['SHORT_DISPLAY']}" /></td>
					</tr>
					<tr>
						<td>Preview Display (Before):</td>
						<td><input type="checkbox" class="null" id="PREVIEW_DISPLAY_BEFORE_null" name="PREVIEW_DISPLAY_BEFORE_null" value="1" {$null_cbs['PREVIEW_DISPLAY_BEFORE']} /></td>
						<td><textarea name="PREVIEW_DISPLAY_BEFORE">{$table_info['PREVIEW_DISPLAY_BEFORE']}</textarea></td>
					</tr>
					<tr>
						<td>Preview Display:</td>
						<td><input type="checkbox" class="null" id="PREVIEW_DISPLAY_null" name="PREVIEW_DISPLAY_null" value="1" {$null_cbs['PREVIEW_DISPLAY']} /></td>
						<td><textarea name="PREVIEW_DISPLAY">{$table_info['PREVIEW_DISPLAY']}</textarea></td>
					</tr>
					<tr>
						<td>Preview Display (After):</td>
						<td><input type="checkbox" class="null" id="PREVIEW_DISPLAY_AFTER_null" name="PREVIEW_DISPLAY_AFTER_null" value="1" {$null_cbs['PREVIEW_DISPLAY_AFTER']} /></td>
						<td><textarea name="PREVIEW_DISPLAY_AFTER">{$table_info['PREVIEW_DISPLAY_AFTER']}</textarea></td>
					</tr>
					<tr>
						<td>Full Page Display:</td>
						<td><input type="checkbox" class="null" id="FULL_DISPLAY_null" name="FULL_DISPLAY_null" value="1" {$null_cbs['FULL_DISPLAY']} /></td>
						<td><textarea class="ckeditor" name="FULL_DISPLAY" id="FULL_DISPLAY">{$table_info['FULL_DISPLAY']}</textarea></td>
					</tr>
					<tr>
						<td>Link Back to Table?</td>
						<td><input type="checkbox" class="null" id="LINK_BACK_TO_TABLE_null" name="LINK_BACK_TO_TABLE_null" value="1" {$null_cbs['LINK_BACK_TO_TABLE']} /></td>
						<td><input type="checkbox" name="LINK_BACK_TO_TABLE" value="1" {$table_info['LINK_BACK_TO_TABLE']}/></td>
					</tr>
					<tr>
						<td>Link Back to All Tables?</td>
						<td><input type="checkbox" class="null" id="LINK_TO_ALL_TABLES_null" name="LINK_TO_ALL_TABLES_null" value="1" {$null_cbs['LINK_TO_ALL_TABLES']} /></td>
						<td><input type="checkbox" name="LINK_TO_ALL_TABLES" value="1" {$table_info['LINK_TO_ALL_TABLES']}/></td>
					</tr>
					<tr>
						<td>Default Order</td>
						<td><input type="checkbox" class="null" id="DEFAULT_ORDER_null" name="DEFAULT_ORDER_null" value="1" {$null_cbs['DEFAULT_ORDER']} /></td>
						<td><select name="DEFAULT_ORDER">$sorting_options</select></td>
					</tr>
					<tr>
						<td>Filter Column</td>
						<td><input type="checkbox" class="null" id="FILTER_COLUMN_null" name="FILTER_COLUMN_null" value="1" {$null_cbs['FILTER_COLUMN']} /></td>
						<td><select name="FILTER_COLUMN">$filter_options</select></td>
					</tr>
					<tr>
						<td>Row Display - Max</td>
						<td><input type="checkbox" class="null" id="ROW_DISPLAY_MAX_null" name="ROW_DISPLAY_MAX_null" value="1" {$null_cbs['ROW_DISPLAY_MAX']} /></td>
						<td><input name="ROW_DISPLAY_MAX" value="{$table_info['ROW_DISPLAY_MAX']}" /></td>
					</tr>
					<tr>
						<td>Detailed Menu Options?</td>
						<td><input type="checkbox" class="null" id="DETAILED_MENU_OPTIONS_null" name="DETAILED_MENU_OPTIONS_null" value="1" {$null_cbs['DETAILED_MENU_OPTIONS']} /></td>
						<td><input type="checkbox" name="DETAILED_MENU_OPTIONS" value="1" {$table_info['DETAILED_MENU_OPTIONS']}/></td>
					</tr>
					<tr>
						<td colspan="3">Meta Tags</td>
					</tr>
TTT;
		foreach($metas as $key=>$value) {
			$output['html'] .= <<<TTT
					<tr>
						<td>{$key}</td>
						<td></td>
						<td>
							<textarea name="meta[{$key}]">{$value}</textarea>
						</td>
					</tr>

TTT;
		}
		$output['html'] .= <<<TTT
					<tr>
						<td>
							<button type="button" id="new_meta">Add...</button>
						</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td></td>
						<td></td>
						<td><input type="submit" value="Save Table Settings" /></td>
					</tr>
				</table>
			</form>
TTT;



		$output['html'] .= "<p><a href='".static::get_module_url()."$table_name'>Return to $table_name record listing...</a></p>";

		return $output;
	}

	public static function edit_record($table_name, $id=null, $action="") {
		global $local, $db,$s_user;

		if ($id == null)
			$to_check = 'Add';
		elseif ($action=="")
			$to_check = "Edit";
		else
			$to_check = ucfirst($action);
		if (!$s_user->check_right('Tables',$table_name,$to_check))
		{
			header("Location: " . static::get_module_url() . $table_name);
			exit;
			return;
		}

		$output = array('html' => '', 'script' => array(
			"{$local}script/jquery.min.js",
			"{$local}script/ckeditor/ckeditor.js",
			"{$local}script/ckeditor/adapters/jquery.js",
			'$(function() {
				$("textarea").each(function() {
					CKEDITOR.replace($(this).attr("ID"));
					});
				})'
		), 'css' => array());

		$table = new Tables($table_name,$id);

		if ($action == 'delete') {
			$table->delete_record();
			Layout::set_message("Record deleted.","success");
			header("Location: " . static::get_module_url().$table_name);
			exit;
			die;
		}

		$columns = $table->get_columns();
		$data = $table->get_records();

		$action = 'edit';
		if (empty($id) || empty($data) ) {
			$action = 'add';
			foreach($columns as $column) {
				$data[$column['COLUMN_NAME']] = '';
			}
		} else $data = $data[0];
		$output['title'] = ucfirst($action) . " table record...";
		$output['html'] .= "
			<h3>".ucfirst($action)." table record...</h3>
			<form action='".static::get_module_url()."$table_name/$id' method='post'>
			<table>
				<thead>
					<tr>
						<th>Field</th>
						<th>Null?</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>";
		foreach($columns as $idx=>$column) {
			if ($column['IS_AUTO_INCREMENT']) continue;
			$output['html'] .= "
					<tr>
						<td>{$column['COLUMN_NAME']}</td>";
			if (in_array($column['DATA_TYPE'], array('bit','boolean'))) {
				/* input type = checkbox */
				$checked = $data[$column['COLUMN_NAME']] ? "checked='checked'" : '';
				$input = "<input type='checkbox' value='1' id='col$idx' name='col$idx' $checked />";
			} elseif (strcmp($column['DATA_TYPE'],'text')==0) {
				/* input type = textarea */
				$input = "<textarea id='col$idx' name='col$idx'>{$data[$column['COLUMN_NAME']]}</textarea>";
			} elseif (!empty($column['REFERENCED_TABLE_NAME'])) {
				$concat = static::sql_decode_display($column['REFERENCED_TABLE_NAME']);
				$joins = "";
				if (!empty($concat['joins'])) {
					foreach($concat['joins'] as $col => $table_join) {
						$joins .= "LEFT JOIN {$table_join['table']} ON {$column['REFERENCED_TABLE_NAME']}.{$col} = {$table_join['table']}.{$table_join['column']} ";
					}
				}
				$pk = static::get_primary_key($table_name);
				$pk = $pk[0]['COLUMN_NAME'];
				$query = "SELECT {$column['REFERENCED_TABLE_NAME']}.$pk as PK, {$concat['concat']} as DISPLAY
					FROM {$column['REFERENCED_TABLE_NAME']}
					$joins";
				$params = $concat['params'];
				$options = $db->run_query($query,$params);
				$input = "<select id='col$idx' name='col$idx'>
							<option value=''></option>";
				if (!empty($options))
					foreach($options as $option) {
						$selected = ($option['PK']==$data[$column['COLUMN_NAME']]) ? "selected='selected'" : '';
						$input.= "<option value='{$option['PK']}' $selected >{$option['DISPLAY']}</option>";
					}
			} else {
				/* basic input */
				/* Still TODO: character max len check*/
				$class = array();
				if ($column['DATA_TYPE']=='datetime') {
					$class[] = 'datetimepicker';
					array_push(
						$output['script'],
						"{$local}script/jquery-ui.min.js",
						"{$local}script/jquery-ui-timepicker-addon.js",
						'$(function() {
							$(".datetimepicker").datetimepicker({
								dateFormat: "yy-mm-dd",
								timeFormat: "HH:mm:ss"
							});
						});'
						);
					array_push(
						$output['css'] ,
						"{$local}style/jquery-ui.css",
						"{$local}style/timepicker.css"
					);
				} elseif ($column['DATA_TYPE']=='date') {
					$class[] = 'datepicker';
					array_push(
						$output['script'],
						"{$local}script/jquery-ui.min.js",
						'$(function() {
							$(".datepicker").datepicker({
								dateFormat: "yy-mm-dd",
							});
						});'
					);
					array_push(
						$output['css'] ,
						"{$local}style/jquery-ui.css"
					);
				}
				$class = implode(" ",$class);
				if (!empty($data[$column['COLUMN_NAME']]))
					$data[$column['COLUMN_NAME']] = utilities::make_html_safe($data[$column['COLUMN_NAME']],ENT_QUOTES);
				$input = "<input class='$class' id='col$idx' name='col$idx' value='{$data[$column['COLUMN_NAME']]}' />";
			}
			if (is_null($data[$column['COLUMN_NAME']])) $is_null = "checked='checked'";
			else $is_null = "";
			if ($column['IS_NULLABLE']) $null = "<input class='null' type='checkbox' value='1' id='col{$idx}_null' name='col{$idx}_null' title='Click here to make this value null.' $is_null/>";
			else $null = "";
			$output['html'] .= "
						<td>$null</td>
						<td>$input</td>
					</tr>
			";
		}
		$output['html'] .="
					<tr>
						<td colspan='100%'>
							<a href='".static::get_module_url()."$table_name' title='Cancels changes and returns to table view.'>Cancel</a>
							<input type='submit' value='Save record' />
						</td>
					</tr>
				</tbody>
			</table>
			</form>";
		$output['script'][] = '$(function() {
	$(".null").click(function() {
		if ($(this).not(":checked").length) return;
		id = $(this).attr("id");
		id = id.substring(0,id.length-5);
		$("#" + id).val("");
	});
	$(":input").on("change keyup click",function() {
		if ($(this).val() == "") return;
		id = $(this).attr("id");
		$("#" + id + "_null").removeAttr("checked");
	});
})';
		return $output;
	}

}
?>
