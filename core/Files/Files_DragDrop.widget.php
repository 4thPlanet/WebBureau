<?php

class files_dragdrop_widget extends files implements widget {
	public static function install() {
		global $db;

		$query = "
			INSERT INTO _WIDGETS(MODULE_ID,NAME,FILENAME,CLASS_NAME)
			SELECT M.ID, ?, ?, ?
			FROM _MODULES M
			LEFT JOIN _WIDGETS W ON
				M.ID = W.MODULE_ID AND
				? = W.FILENAME
			WHERE M.CLASS_NAME = ? AND W.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Files Drag/Drop"),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => __CLASS__),
			array("type" => "s", "value" => __FILE__),
			array("type" => "s", "value" => get_parent_class(__CLASS__))
		);
		$db->run_query($query,$params);
	}

	public static function view() {
		global $local;
		$all_files = static::get_files();
		if (empty($all_files)) return;
		$output = array('html' => '<h3>Files</h3>', 'css' => array(utilities::get_public_location(__DIR__ . '/css/files_dragdrop.css')), 'script' => array("{$local}script/jquery.min.js", utilities::get_public_location(__DIR__ . '/js/files_dragdrop.js')));
		$output['html'] .= "<ul id='dragdrop_files'>";

		foreach($all_files as $file)
		{
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$mime_type = $finfo->file($file['FILENAME']);
			$tag = (substr($mime_type,0,5)=='image') ? 'img' : 'a';
			$output['html'] .= "<li data-tag='$tag' data-location=".utilities::get_public_location($file['FILENAME']).">{$file['TITLE']}</li>";
		}
		$output['html'] .= "</ul>";

		return $output;
	}
}
?>
