<?php
class files extends module {
	public static function install() {
		global $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _FILES (
				ID int auto_increment,
				FILENAME varchar(100),
				TITLE varchar(100),
				DESCRIPTION varchar(1000),
				UPLOAD_DATE datetime,
				PRIMARY KEY (ID)
			);";
		$db->run_query($query);
		$db->trigger('file_upload_date','BEFORE INSERT','_FILES','SET NEW.UPLOAD_DATE = IFNULL(NEW.UPLOAD_DATE,NOW())');
		
		return true;
	}
	
	public static function admin_post($args,$request) {
		global $db,$s_user;
		if (empty($args) && $s_user->check_right('Files','Files','Upload File')) {
			/* Upload a new file... */
			$filename = __DIR__ . "/uploads/tmp" . date('YmdHis') . session_id();
			if (move_uploaded_file($_FILES['the_file']['tmp_name'],$filename)) {
				$query = "INSERT INTO _FILES (TITLE,DESCRIPTION) VALUES (?,?)";
				$params = array(
					array("type" => "s", "value" => $request['title']),
					array("type" => "s", "value" => $request['description']),
				);
				$db->run_query($query,$params);
				$file_id = $db->get_inserted_id();
				$new_filename = __DIR__ . "/uploads/" . date('YmdHis') . "-$file_id-{$_FILES['the_file']['name']}";
				rename($filename,$new_filename);
				$query = "UPDATE _FILES SET FILENAME = ? WHERE ID = ?";
				$params = array(
					array("type" => "s", "value" => $new_filename),
					array("type" => "i", "value" => $file_id),
				);
				$db->run_query($query,$params);
			} else {
				layout::set_message('Unable to upload file.','error');
			}
		} elseif (!empty($args) && $s_user->check_right('Files','Files','Edit File')) {
			/* Edit a file... */
		}
	}
	
	
	private static function admin_file_view($file_id) {
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
				get_public_location(__DIR__ . '/js/file-admin.js')
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
		$output['html'] .= "<iframe src='".get_public_location($file_info['FILENAME'])."'></iframe>";
		$output['html'] .= "<p><a href='".get_public_location($file_info['FILENAME'])."' target='_blank'>Open in new tab/window</a></p>";
		
		if ($s_user->check_right('Files','Files','Edit File'))
			$output['html'] .= "<p><a href='#' id='file-delete'>Delete File</a></p>";
		
		$output['html'] .= "<p><a href='".modules::get_module_url()."Files'>Return to Files</a></p>";
		
		return $output;
	}
	
	private static function admin_view_files() {
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
	
	public static function admin($file_id="") {
		/* TODO: View Current Files (Along with Edit Description/Delete Functionality), Upload New File */
		if (empty($file_id)) return static::admin_view_files();
		else return static::admin_file_view($file_id);

	}
	public static function post() {
		
	}
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
	public static function view() {
		global $db,$local,$s_user;
		if (!$s_user->check_right('Files','Files','View File')) {
			header("Location: $local");
			exit();
			return;
		}
		$output = array(
			'html' => '<h3>Files</h3>'
		);
		
		$query = "SELECT TITLE,DESCRIPTION,FILENAME FROM _FILES";
		$files = $db->run_query($query);
		
		if (empty($files)) {
			$output['html'] .= "<p>No Files have been uploaded.</p>";
		} else {
		
			$output['html'] .= "
			<table>
				<thead>
					<tr>
						<th>File</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>";
			foreach($files as $file) {
				$output['html'] .= "
					<tr>
						<td><a target='_blank' href='".get_public_location($file['FILENAME'])."'>{$file['TITLE']}</a></td>
						<td>{$file['DESCRIPTION']}</td>
					</tr>";
			}
			$output['html'] .= "
				</tbody>
			</table>";
		}
		return $output;
	}
	public static function required_rights() {
		return array(
			'Files' => array(
				'Files' => array(
					'Upload File' => array('description' => 'Allows a user to upload a file.', 'default_groups' => array('Admin')),
					'View File' => array('description' => 'Allows a user to view an uploaded file.', 'default_groups' => array('Registered User')),
					'Delete File' => array('description' => 'Allows a user to delete an uploaded file.', 'default_groups' => array('Admin')),
					'Edit File' => array('description' => 'Allows a user to edit an uploaded file description.', 'default_groups' => array('Admin'))
				)
			)
		);
	}
	
}
?>
