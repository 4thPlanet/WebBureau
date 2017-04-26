<?php
class files_admin extends files {
	public static function ajax($args,$request) {
		global $db,$s_user;
		$file_id = empty($args) ? null : array_shift($args);
		switch($request['ajax']) {
			case 'edit':
				if (!$s_user->check_right('Files','Files','Edit File')) return false;
				$query = "UPDATE _FILES SET DESCRIPTION = ? WHERE ID = ?";
				$params = array(
					array("type" => "s", "value" => $request['description']),
					array("type" => "i", "value" => $file_id)
				);
				$db->run_query($query,$params);
				return array('success' => 1);
			case 'delete':
				if (!$s_user->check_right('Files','Files','Delete File')) return false;
				$query = "SELECT FILENAME FROM _FILES WHERE ID = ?";
				$params = array(
					array("type" => "i", "value" => $file_id)
				);
				$result = $db->run_query($query,$params);
				if (empty($result)) return array('success' => 0, 'msg' => 'Invalid File ID');
				$filename = $result[0]['FILENAME'];
				/* Delete from DB... */
				$query = "DELETE FROM _FILES WHERE ID = ?";
				$db->run_query($query,$params);
				/* Confirm deletion */
				$query = "SELECT CASE COUNT(*) WHEN 0 THEN 1 ELSE 0 END as file_deleted FROM _FILES WHERE ID = ?";
				$result = $db->run_query($query,$params);
				if ($result[0]['file_deleted']) {
					unlink($filename);
					return array('success' => 1);
				} else {
					/* Foreign key constraint somewhere */
					return array('success' => 0, 'msg' => 'Unable to delete file.  Please check if this file is used in any modules...');
				}
		}
	}

	public static function post($args,$request) {
		global $db,$s_user;
		if (empty($args) && $s_user->check_right('Files','Files','Upload File')) {
			/* Upload a new file... */
			if ($filename = self::upload($_FILES['the_file']['tmp_name'],__DIR__ . '/uploads/' . $_FILES['the_file']['name'])) {
				// file uploaded correctly, now save it..
				$query = "INSERT INTO _FILES (FILENAME,TITLE,DESCRIPTION) VALUES (?,?,?)";
				$params = array(
					array("type" => "s", "value" => $filename),
					array("type" => "s", "value" => $request['title']),
					array("type" => "s", "value" => $request['description']),
				);
				$db->run_query($query,$params);
				$file_id = $db->get_inserted_id();
				layout::set_message("Successfully uploaded file.","success");
			} else {
				layout::set_message("Unable to upload file.  Please confirm server has write access to " . utilities::get_public_location(__DIR__ . '/uploads'),"error");
			}
		} elseif (!empty($args) && $s_user->check_right('Files','Files','Edit File')) {
			/* Edit a file... */
		}
	}
	public static function view($file_id='') {
		if (empty($file_id)) return static::view_files();
		else return static::view_file($file_id);
	}

	protected static function view_files() {
		global $db,$s_user;
		$output = array(
			'html' => '<h3>Files</h3>'
		);
		if ($s_user->check_right('Files','Files','View File')) {
			$output['html'] .= "<h4>Uploaded Files</h4>";
			$query = "SELECT ID,TITLE,UPLOAD_DATE FROM _FILES";
			$files = $db->run_query($query);
			if (empty($files)) {
				$output['html'] .= "<p>No files have been uploaded yet.</p>";
			} else {
				$output['html'] .= "
				<table>
					<thead>
						<tr>
							<th>File</th>
							<th>Upload Time</th>
						</tr>
					</thead>
					<tbody>";
				foreach($files as $file) {
					$output['html'] .= "
						<tr>
							<td><a href='".modules::get_module_url()."Files/{$file['ID']}'>{$file['TITLE']}</a></td>
							<td>".preg_replace('/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/','$2/$3/$1 $4:$5',$file['UPLOAD_DATE'])."</td>
						</tr>";
				}
				$output['html'] .= "
					</tbody>
				</table>";
			}
		}
		if ($s_user->check_right('Files','Files','Upload File')) {
		$output['html'] .= "
			<h4>Upload New File</h4>
			<form action='' method='post' enctype='multipart/form-data'>
				<div>
					<label for='the_file'>File</label>
					<input type='file' id='the_file' name='the_file' />
				</div>
				<div>
					<label for='title'>Title</label>
					<input id='title' name='title' />
				</div>
				<div>
					<label for='description'>Description</label>
					<input id='description' name='description' />
				</div>
				<div>
					<input type='submit' value='Upload File' />
				</div>
			</form>
			";
		}
		return $output;
	}

	protected static function view_file($file_id) {
		global $db,$local,$s_user;
		$output = array(
			'html' => '<h3>Files</h3>',
			'css' => array(
				"{$local}style/jquery-ui.css",
				".widget.main-content iframe {width: 100%; min-height: 7.5em;}"
			),
			'script' => array(
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				utilities::get_public_location(__DIR__ . '/js/file-admin.js')
			)
		);

		$query = "SELECT ID,FILENAME,TITLE,DESCRIPTION,UPLOAD_DATE FROM _FILES WHERE ID = ?";
		$params = array(
			array("type" => "i", "value" => $file_id)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			header("Location: " . modules::get_module_url() . "Files");
			exit();
			return;
		}
		$file_info = $result[0];
		$output['html'] .= "<h4>{$file_info['TITLE']}</h4>";
		$output['html'] .= "<p>Uploaded ".preg_replace('/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/','$2/$3/$1 $4:$5',$file_info['UPLOAD_DATE'])."<br /><span id='file-description'>{$file_info['DESCRIPTION']}</span>";
		if ($s_user->check_right('Files','Files','Edit File'))
			$output['html'] .= " (<a href='#' id='file-edit'>Edit Description</a>)";
		$output['html'] .= "</p>";
		$output['html'] .= "<iframe src='".utilities::get_public_location($file_info['FILENAME'])."'></iframe>";
		$output['html'] .= "<p><a href='".utilities::get_public_location($file_info['FILENAME'])."' target='_blank'>Open in new tab/window</a></p>";

		if ($s_user->check_right('Files','Files','Edit File'))
			$output['html'] .= "<p><a href='#' id='file-delete'>Delete File</a></p>";

		$output['html'] .= "<p><a href='".modules::get_module_url()."Files'>Return to Files</a></p>";

		return $output;
	}
}
?>
