<?php
class resources_admin extends resources {
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
		if (empty($args) && $s_user->check_right('Resources','Resources','Upload Resource')) {
			/* Upload a new resource... */

			if ($filename = files::upload($_FILES['the_file']['tmp_name'], __DIR__ . '/uploads/' . $_FILES['the_file']['name'])) {
				// save to _RESOURCES...

				if (self::addResource($request['type'], $request['name'], $filename))
					layout::set_message('Successfully uploaded resource to ' . utilities::get_public_location($filename),'success');
				else
					layout::set_message('Able to upload file, but unable to save to database.','error');


			} else {
				layout::set_message("Unable to upload file.  Please confirm server has write access to " . utilities::get_public_location(__DIR__ . '/uploads'),"error");
			}
		} elseif (!empty($args) && $s_user->check_right('Resources','Resources','Edit Resource')) {
			// get file location...
			$resource = new resources();
			$resource->load($args[0]);

			if ($_FILES['resource_file']['tmp_name']) {
				// upload file

				if ($filename = files::upload($_FILES['resource_file']['tmp_name'],$resource->filename,true)) {
					// success message
					layout::set_message('Successfully updated resource.','success');
				} else {
					layout::set_message('Unable to upload updated resource.','error');
				}
			} elseif ($request['resource_source']) {
				if (file_put_contents($resource->filename,$request['resource_source'])) {
					// success message
					layout::set_message('Successfully updated resource.','success');
				} else {
					// error message
					layout::set_message('Unable to save changes to resource.','error');
				}
			}
		}
	}
	public static function view($file_id='') {
		if (empty($file_id)) return static::view_resources();
		else return static::view_resource($file_id);
	}

	protected static function view_resources() {
		global $db,$s_user;
		$output = array(
			'html' => '<h3>Resources</h3>'
		);
		if ($s_user->check_right('Resources','Resources','View Resource')) {
			$output['html'] .= "<h4>Current Resources</h4>";
			$all_resources = static::get_resources_by_type();

			if (empty($all_resources)) {
				$output['html'] .= "<p>No resources available.</p>";
			} else {
				// List all resources
				$output['html'] .= "
				<table>
					<thead>
						<tr>
							<th>Resource</th>
							<th></th>
						</tr>
					</thead>
					<tbody>";
				foreach($all_resources as $type => $resources) {
					foreach($resources as $id => $resource) {
						$url = modules::get_module_url() . "Resources/$id";
						$output['html'] .= <<<TTT
						<tr>
							<td><strong>{$resource['NAME']}</strong> <em>($type)</em></td>
							<td><a href="$url">Manage</a></td>
						</tr>
TTT;
					}
				}

				$output['html'] .= "

					</tbody>
				</table>
				";

			}
		}
		if ($s_user->check_right('Resources','Resources','Upload Resource')) {
			$output['html'] .= <<<TTT
				<h4>Upload New Resource</h4>
				<form action='' method='post' enctype='multipart/form-data'>
					<div>
						<label for="type">Type</label>
						<select id="type" name="type">
							<option value=""></option>
TTT;
			$resource_types = static::get_resource_types();
			foreach($resource_types as $id => $type) {
				$output['html'] .= <<<TTT
							<option value="$id">{$type['NAME']}</option>
TTT;
			}

			$output['html'] .= <<<TTT
						</select>
					</div>
					<div>
						<label for='the_file'>File</label>
						<input type='file' id='the_file' name='the_file' />
					</div>
					<div>
						<label for='name'>Resource Name</label>
						<input id='name' name='name' />
					</div>
					<div>
						<input type='submit' value='Upload Resource' />
					</div>
				</form>
TTT;
		}
		return $output;
	}

	protected static function view_resource($param_id) {
		global $db,$local,$s_user;
		if (!$s_user->check_right('Resources','Resources','Edit Resource')) {
			header("Location: " . static::get_module_url());
			exit;
			return;
		}

		$output = array(
			'html' => '<h3>Resources</h3>',
		);

		$query = "
			SELECT R.NAME,T.NAME AS RESOURCE_TYPE, FILENAME
			FROM _RESOURCES R
			JOIN _RESOURCE_TYPES T ON R.RESOURCE_TYPE_ID = T.ID
			WHERE R.ID = ?";
		$params = array(
			array("type" => "i", "value" => $param_id)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		$resource_info = $result[0];

		$output['html'] .= <<<FORM
	<h4>{$resource_info['NAME']}</h4>
	<form method="post" enctype="multipart/form-data" action="">
FORM;

		if (in_array($resource_info['RESOURCE_TYPE'],array('script','style'))) {
			// can edit in textarea..
			$output['html'] .= "<p>Edit directly in the textarea  below...<br /><textarea name='resource_source' rows='20'>".utilities::make_html_safe(file_get_contents($resource_info['FILENAME']))."</textarea><br />...or</p>";
		}

		$output['html'] .= <<<FORM
		<p>Upload a new version of the file: <input type='file' name="resource_file"/>
			<input type="submit" value="Save Resource" />
		</p>
FORM;


		$output['html'] .= "<p><a href='".modules::get_module_url()."Files'>Return to Files</a></p>";

		return $output;
	}

	public static function get_module_url() {
		return modules::get_module_url() . "Resources/";
	}
}
?>
