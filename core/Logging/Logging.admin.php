<?php
class logging_admin extends logging {
	public static function get_module_url() {
		return modules::get_module_url() . 'Logging/';
	}

	public static function ajax() {}
	public static function post($args,$request) {
		if (empty($args)) {
			if (isset($request['save_to'])) {
				static::set_module_setting('Logging', 'Log Location', $request['save_to']);
				Layout::set_message('Successfully saved Log settings','success');
			}
		}
	}

	public static function view($log="",$type="") {
		switch ($log)
		{
			case '': return static::view_settings();
			case 'log': return static::view_log($type);
		}
	}

	protected function view_settings() {
		$output = array('html' => '<h3>Logging</h3><h4>Settings</h4>');
		$log_location = static::get_module_setting('Logging', 'Log Location');
		$file_selected = $log_location == 'File' ? 'selected="selected"' : '';
		$database_selected = $log_location == 'Database' ? 'selected="selected"' : '';

		$output['html'] .= <<<HTML
	<form method="post">
		<label for="save_to">Save Logs To:</label>
		<select id="save_to" name="save_to">
			<option value="File" {$file_selected}>File</option>
			<option value="Database" {$database_selected}>Database</option>
		</select>
		<p><strong>Note:</strong> In order to save to file, you must have write access to the module directory!</p>
		<input type="submit" value="Save" />
	</form>
	<h4>View/Download Logs</h4>
HTML;
		//if there are any log files, make them available to download.  If there are any records in _LOG, make them available for viewing
		$files = scandir(__DIR__ . '/logs');
		$files = utilities::get_files_by_ext('log',__DIR__ . '/logs');
		if ($files)
		{
			$output['html'] .= '<h5>Files</h5><ul>';
			foreach($files as $file)
			{
				$output['html'] .= "<li><a href='".static::get_module_url()."log/$file'>$file</a>";
			}
			$output['html'] .= '</ul>';
		}

		$db_logs = static::get_open_logs();
		if ($db_logs)
		{
			$output['html'] .= '<h5>DB Logs</h5><ul>';
			foreach($db_logs as $log)
			{
				$output['html'] .= "<li><a href='".static::get_module_url()."log/$log'>$log</a>";
			}
			$output['html'] .= '</ul>';
		}

		return $output;
	}

	protected function view_log($type) {
		if (preg_match('/\.log$/',$type) && file_exists(__DIR__ . "/logs/$type"))
		{
			//output the file...
			ob_clean();
			header('Content-Type: text/plain');
			header("Content-Disposition: attachment; filename='$type'");
			header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
			header("Pragma: no-cache"); //HTTP 1.0
			header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
			echo file_get_contents(__DIR__ . "/logs/$type");
			exit;
		}
		else
		{
			//output the DB log... for this type...
			$query = "
				SELECT L.LOG_DATE, L.REQUEST_URI, L.CLASS, L.FUNCTION, L.MESSAGE
				FROM _LOG L
				JOIN _LOG_MESSAGE_TYPES LMT ON L.LOG_MESSAGE_TYPE_ID = LMT.ID
				WHERE LMT.NAME = ?
			";
			$params = array(
				array("type" => "s", "value" => $type)
			);
			$log_table = new pagingtable_widget($query,$params);
			$log_table->set_columns(array(
				array('column' => 'LOG_DATE', 'display_name' => 'Date' ),
				array('column' => 'REQUEST_URI', 'display_name' => 'Request URI' ),
				array('column' => 'CLASS', 'display_name' => 'Class' ),
				array('column' => 'FUNCTION', 'display_name' => 'Function' ),
				array('column' => 'MESSAGE', 'display_name' => 'Message' ),
			));
			$output = $log_table->build_table();
			$output['html'] = "<h3>$type log</h3>" . $output['html'];
			$output['html'] .= "<p><a href='".static::get_module_url()."'>Return to Logs</a></p>";
			return $output;
		}
	}

}
?>
