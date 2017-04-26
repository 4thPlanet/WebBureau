<?php
class layout_static_block extends layout implements widget {
	/* Prints out main content of the page... */
	public static function install() {
		global $db;
		$query = "
			INSERT INTO _WIDGETS (MODULE_ID, NAME, FILENAME, CLASS_NAME)
			SELECT M.ID, 'Static Block', ?, 'layout_static_block'
			FROM _MODULES M
			LEFT JOIN _WIDGETS W ON M.ID = W.MODULE_ID AND W.Name = 'Static Block'
			WHERE M.NAME = 'Layout' AND W.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => __FILE__)
		);
		$db->run_query($query,$params);
	}

	public static function view($block_id="") {
		global $db;
		$query = "
			SELECT HTML
			FROM _LAYOUT_STATIC_HTML
			WHERE ID = ?
		";
		$params = array(
			array("type" => "i", "value" => $block_id)
		);
		$result = $db->run_query($query,$params);
		return $result ? array('html' => $result[0]['HTML']) : false;
	}

	public static function setup() {
		/* Returns setup questions as an array.  Each sub-array should contain 'prompt', 'type', and 'options' (if necessary, each consisting of an array containing ID and VALUE) */
		global $db;
		$query = "
			SELECT ID, IDENTIFIER as VALUE
			FROM _LAYOUT_STATIC_HTML
			ORDER BY VALUE
		";
		return array(
			array(
				'PROMPT' => 'HTML Block: ',
				'TYPE' => 'select',
				'OPTIONS' => $db->run_query($query)
			)
		);
	}
}
?>
