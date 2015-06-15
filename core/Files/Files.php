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

	public static function post() {}
	public static function ajax($args,$request) {}

	public static function get_files() {
		global $db;
		$query = "
			SELECT ID,FILENAME,TITLE
			FROM _FILES
		";
		return group_numeric_by_key($db->run_query($query),'ID');
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
