<?php
/* The primary difference between Tables and Tables Admin is the lack of slugs (since there's no need to make slugs pretty in the admin side)...*/

class tables_admin extends tables {
	public static function ajax($args,$request) {}
	
	public static function post($args,$request) {
		$table_name = array_shift($args);
		$table = new Tables($table_name);
		if (!empty($args)) {
			$id = array_shift($args);
			$table->set_id($id);
		}	
		/* Before we save the data, we need to map the request data to actual columns... */
		$columns = $table->get_columns();
		$data = array();
		foreach($columns as $idx=>$column) {
			if ($column['IS_AUTO_INCREMENT']) continue;
			$data[$column['COLUMN_NAME']] = $request["col$idx"];
		}
		$result = $table->save($data);
		if ($result===false) {
			Layout::set_message('Unable to save table data.  Please try again.','error');
		} else {
			Layout::set_message('Table data successfully saved.','info');
			if (empty($id)) {
				header("Location: " . static::get_module_id() . "$table_name/$result");
				exit();
				return;
			}
		}
		
	}
	
	public static function view($table='',$id='',$action='') {
		if (empty($table)) {
			return static::list_tables();
		} elseif (empty($id)) {
			return static::list_table_records($table);
		} else {
			return static::edit_record($table,$id,$action);
		}
		
	}
	
	public static function get_module_url() {
		return modules::get_module_url() . "Tables/";
	}
	
	protected static function list_tables() {
		global $db;
		$output = array('html' => '<h3>Table Administration</h3>');
		
		$output['html'] .= "<ol>";
		$all_tables = static::get_users_viewable_tables();
		foreach($all_tables as $table) {
			$output['html'] .= "<li><a href='".static::get_module_url()."$table'>$table</a></li>";
		}
		$output['html'] .= "</ol>";
		
		return $output;
		
	}
	
	protected static function list_table_records($table_name) {
		global $db,$s_user;
		if (!$s_user->check_right('Tables',$table_name,'View')) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		$output = array('html' => "<h3>$table_name Administration</h3>");
		$table = new Tables($table_name);
		
		$output['html'] .= "<table>
			<thead>
				<tr>";
		$columns = $table->get_columns();
		foreach($columns as $column) {
			$output['html'] .= "<th>{$column['COLUMN_NAME']}</th>";
		}
		$output['html'].="
				</tr>
			</thead>
			<tbody>";
		$records = $table->get_records();
		foreach($records as $row) {
			$output['html'] .= "
				<tr>";
			foreach($columns as $column) {
				if (strcmp($column['CONSTRAINT_NAME'],'PRIMARY')==0) {
					/* Primary key - make it a link... */
					$row[$column['COLUMN_NAME']] = "<a href='".static::get_module_url() . "$table_name/".make_url_safe($row[$column['COLUMN_NAME']])."'>{$row[$column['COLUMN_NAME']]}</a>";
				}
				/* Still TODO: Check if FOREIGN KEY... */
				if (!empty($column['REFERENCED_TABLE_NAME']) && !empty($column['REFERENCED_COLUMN_NAME'])) {
					/* Get Foreign Key SHORT Display */
					$sql = static::sql_decode_display($column['REFERENCED_TABLE_NAME']);
					$query = "SELECT {$sql['concat']} as display FROM {$column['REFERENCED_TABLE_NAME']} WHERE {$column['REFERENCED_COLUMN_NAME']} = ?";
					$params = $sql['params'];
					array_push($params,array("type" => "s", "value" => $row[$column['COLUMN_NAME']]));
					$result = $db->run_query($query,$params);
					if (!empty($result)) $row[$column['COLUMN_NAME']] = $result[0]['display'];
				
				}
				$output['html'] .= "<td>{$row[$column['COLUMN_NAME']]}</td>";
			}
			$output['html'] .= "
				</tr>";
		}
		$output['html'] .= "
			</tbody>
		</table>
		<p><a href='".static::get_module_url()."'>Return to Table Listing...</a></p>";
		
		return $output;
	}
	
	public static function edit_record($table_name, $id) {
		global $local, $db;
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
		
		$table = new Tables($table_name);
		$table->set_id($id);
		$columns = $table->get_columns();
		$data = $table->get_records();
		
		$action = 'edit';
		if (empty($id)) {
			$action = 'add';
			foreach($columns as $column) {
				$data[$column['COLUMN_NAME']] = '';
			}
		}
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
				$pk = static::get_primary_key($table_name)[0]['COLUMN_NAME'];
				$query = "SELECT $pk as PK, {$concat['concat']} as DISPLAY
					FROM {$column['REFERENCED_TABLE_NAME']}";
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
						'https://raw.githubusercontent.com/trentrichardson/jQuery-Timepicker-Addon/master/src/jquery-ui-timepicker-addon.js',
						'$(function() {
							$(".datetimepicker").datetimepicker();
						});'
						);
					array_push(
						$output['css'] ,
						'//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css',
						"{$local}style/timepicker.css"
					);
				}
				$class = implode(" ",$class);
				if (!empty($data[$column['COLUMN_NAME']]))
					$data[$column['COLUMN_NAME']] = make_html_safe($data[$column['COLUMN_NAME']],ENT_QUOTES);
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
		$output['script'][] = "{$local}script/jquery.min.js";
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
