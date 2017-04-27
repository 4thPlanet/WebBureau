<?php

/*
 * While this class extends files, it is truly a utility class.  As a result, not ajax/post/view should all return false;
 */

class resources extends files {

	protected $id;

	public $resource_type;
	public $name;
	public $filename;

	public function __construct($resource_id=null) {
		if ($resource_id) {
			$this->load($resource_id);
		}
	}

	public function load($resource_id) {
		global $db;
		$query = "SELECT * FROM _RESOURCES WHERE ID = ?";
		$params = array(
			array("type" => "i", "value" => $resource_id)
		);
		$resource = $db->run_query($query,$params);
		if (!empty($resource)) {
			$resource = $resource[0];
			$this->id = $resource['ID'];
			$this->resource_type = $resource['RESOURCE_TYPE_ID'];
			$this->name = $resource['NAME'];
			$this->filename = $resource['FILENAME'];
		} else {
			$this->resetInstance();
		}
	}

	public static function install() {
		global $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _RESOURCE_TYPES(
				ID int auto_increment,
			    NAME varchar(32),
			    CODE varchar(255),
			    PRIMARY KEY(ID),
				UNIQUE(NAME)
			)";
		$db->run_query($query);

		$query = "
			INSERT INTO _RESOURCE_TYPES (NAME,CODE) VALUES
				(?,?),(?,?),(?,?),(?,?)
			ON DUPLICATE KEY UPDATE NAME=NAME
		";
		$params = array(
			array("type" => "s", "value" => "script"),
			array("type" => "s", "value" => '<script type="text/javascript" src="{RESOURCE}"></script>'),

			array("type" => "s", "value" => "style"),
			array("type" => "s", "value" => '<link rel="stylesheet" type="text/css" href="{RESOURCE}" />'),

			array("type" => "s", "value" => "image"),
			array("type" => "s", "value" => '<img alt="{NAME}" src="{RESOURCE}" />'),

			array("type" => "s", "value" => "icon"),
			array("type" => "s", "value" => '<link rel="icon" href="{RESOURCE}" />'),
		);
		$db->run_query($query,$params);

		$query = "
			CREATE TABLE IF NOT EXISTS _RESOURCES (
			ID int auto_increment,
		    RESOURCE_TYPE_ID int,
		    NAME varchar(32),
		    FILENAME varchar(255),
		    PRIMARY KEY(ID),
		    FOREIGN KEY (RESOURCE_TYPE_ID) REFERENCES _RESOURCE_TYPES(ID)
		)";
		$db->run_query($query);

		//TODO: Auto-add resources in /images, /script, and /style

		return true;
	}

	public static function addResource($type,$name,$filename) {
		global $db;
		$query = "
			INSERT INTO _RESOURCES (RESOURCE_TYPE_ID,NAME,FILENAME)
			VALUES (?,?,?)
		";
		$params = array(
			array("type" => "i", "value" => is_numeric($type) ? $type : self::getResourceTypeId($type)),
			array("type" => "s", "value" => $name),
			array("type" => "s", "value" => $filename)
		);
		$db->run_query($query,$params);

		return true;
	}

	protected static function getResourceTypeId($resource_type) {
		global $db;
		$query = "
			SELECT ID
			FROM _RESOURCE_TYPES
			WHERE NAME = ?
		";
		$params = array(
			array("type" => "s", "value" => $resource_type)
		);
		$result = $db->run_query($query,$params);
		if ($result) {
			return $result[0]['ID'];
		} else {
			return null;
		}
	}

	public static function get_resource_types() {
		global $db;
		$query = "
			SELECT *
			FROM _RESOURCE_TYPES
		";
		return utilities::group_numeric_by_key($db->run_query($query), 'ID');
	}

	public static function get_resources_by_type($typeID=null) {
		global $db;

		$column = is_numeric($typeID) ? 'RT.ID' : 'RT.NAME';

		$query = "
			SELECT R.ID,R.NAME,RT.NAME AS RESOURCE_TYPE
			FROM _RESOURCES R
			JOIN _RESOURCE_TYPES RT ON R.RESOURCE_TYPE_ID = RT.ID
			WHERE ? IS NULL OR {$column} = ?
		";
		$params = array(
			array("type" => "s", "value" => $typeID),
			array("type" => "s", "value" => $typeID),
		);
		return !is_null($typeID)
			? $db->run_query($query,$params)
			: utilities::group_numeric_by_keys($db->run_query($query,$params),array('RESOURCE_TYPE','ID'));
	}

	public static function getResourceHTML($resourceID) {
		global $db;
		$query = "
			SELECT R.FILENAME,RT.CODE
			FROM _RESOURCES R
			JOIN _RESOURCE_TYPES RT ON R.RESOURCE_TYPE_ID = RT.ID
			WHERE R.ID = ?
		";
		$params = array(
			array("type" => "i", "value" => $resourceID)
		);
		$result = $db->run_query($query,$params);
		if ($result) {
			$result = $result[0];
			$result['RESOURCE'] = utilities::get_public_location($result['FILENAME']);
			return utilities::replace_formatted_string($result['CODE'], '{', '}', $result);
		}
		else {
			return false;
		}
	}

	public static function get_resources_as_output_params($resourceIDs) {
		global $db;
		if (empty($resourceIDs))
			return array();
		elseif (is_int($resourceIDs))
			$resourceIDs = array($resourceIDs);
		$output = array();
		$query = "
			SELECT RT.NAME, R.FILENAME
			FROM _RESOURCES R
			JOIN _RESOURCE_TYPES RT ON R.RESOURCE_TYPE_ID = RT.ID
			WHERE R.ID IN (".implode(",",array_fill(0,count($resourceIDs),"?")).")
		";
		$params = array();
		foreach($resourceIDs as $id)
			array_push($params,array("type" => "i", "value" => $id));
		$resources = $db->run_query($query,$params);
		foreach($resources as $resource) {
			switch($resource['NAME']) {
				case 'style':
					$type = 'css';
					break;
				case 'script':
					$type = $resource['NAME'];
					break;
				default:
					continue;	// icons and images don't go in output (yet)
			}
			$output[$type][] = utilities::get_public_location($resource['FILENAME']);
		}
		return $output;
	}

	public static function post() { return false; }
	public static function ajax($args,$request) { return false; }
	public static function view() { return false; }

	// No additional rights
	public static function required_rights() {
		return array(
				'Resources' => array(
						'Resources' => array(
								'Upload Resource' => array('description' => 'Allows a user to upload a Resource.', 'default_groups' => array('Admin')),
								'View Resource' => array('description' => 'Allows a user to view an uploaded Resource.', 'default_groups' => array('Registered User')),
								'Delete Resource' => array('description' => 'Allows a user to delete an uploaded Resource.', 'default_groups' => array('Admin')),
								'Edit Resource' => array('description' => 'Allows a user to edit an uploaded Resource description.', 'default_groups' => array('Admin'))
						)
				)
		);
	}

}
?>
