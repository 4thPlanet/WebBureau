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

		require_once(__DIR__ . '/Files_DragDrop.widget.php');
		files_dragdrop_widget::install();

		return true;
	}

	/**
	 * Attempts to upload a file.  If the proposed filename is already taken, will append -N to filename (for instance, filename.txt becomes filename-2.txt, filename-3.txt, etc.)
	 * @param string $tmpFile - The temporary file
	 * @param string $filename - The proposed new filename.  If an absolute path isn't provided, file will be stored in __DIR__/uploads
	 * @param bool $overwrite - When true, allows overwriting of files
	 * @return string - The new location of the filename.
	 */
	public static function upload($tmpFile,$filename,$overwrite=false) {
		$directory = __DIR__;
		if (strpos($filename,'/') === 0) {
			$directory = dirname($filename);
		}
		$file_name_parts = explode('.',basename($filename));
		$extension = array_pop($file_name_parts);
		$base_name = implode('.',$file_name_parts);

		// does $base_name.$extension exist already?
		if (!$overwrite) {
			$v=1;
			while (file_exists($proposed_name = $directory .'/'. $base_name . ($v==1 ? '' : '-'.$v) . '.' . $extension) && $v++) {
				// noop (while condition does all the work for us)
			}
		} else {
			$proposed_name = $directory .'/' . $base_name . '.' . $extension;
		}


		return move_uploaded_file($tmpFile,$proposed_name) ? $proposed_name : false;
	}

	public static function post() {}
	public static function ajax($args,$request) {}

	public static function get_files() {
		global $db;
		$query = "
			SELECT ID,FILENAME,TITLE
			FROM _FILES
		";
		return utilities::group_numeric_by_key($db->run_query($query),'ID');
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
						<td><a target='_blank' href='".utilities::get_public_location($file['FILENAME'])."'>{$file['TITLE']}</a></td>
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
