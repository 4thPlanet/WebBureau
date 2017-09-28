<?php

/*
 * The Caching class is a the base for all caching done on the site.
 */
class databasecaching extends caching implements CacheInterface {
	private $module;

	public function __construct($module) {
		$this->module = static::get_module_by_name($module);
	}

	public function get($key) {
		global $db;
		$query = "SELECT CACHE_VALUE FROM _CACHE WHERE MODULE_ID = ? AND CACHE_KEY = ?";
		$params = array(
			array("type" => "i", "value" => $this->module->ID),
			array("type" => "s", "value" => $key)
		);
		$result = $db->run_query($query,$params);
		if ($result)
			return unserialize($result[0]['CACHE_VALUE']);
		else
			return null;
	}

	public function set($key,$val) {
		global $db;
		$query = "REPLACE INTO _CACHE (MODULE_ID,CACHE_KEY,CACHE_VALUE) VALUES (?,?,?)";
		$params = array(
				array("type" => "i", "value" => $this->module->ID),
				array("type" => "s", "value" => $key),
				array("type" => "s", "value" => serialize($val)),
		);
		$db->run_query($query,$params);
	}
	public function delete($key) {
		global $db;
		$query = "DELETE FROM _CACHE WHERE MODULE_ID = ? AND CACHE_KEY = ?";
		$params = array(
			array("type" => "i", "value" => $this->module->ID),
			array("type" => "s", "value" => $key)
		);
		$db->run_query($query,$params);
	}
	public function deleteAllModuleCache() {
		global $db;
		$query = "DELETE FROM _CACHE WHERE MODULE_ID = ?";
		$params = array(
			array("type" => "i", "value" => $this->module->ID),
		);
		$db->run_query($query,$params);
	}
	public function deleteAllCache() {
		global $db;
		$query = "TRUNCATE TABLE _CACHE";
		$db->run_query($query);
	}

	public static function install() {
		// required_tables, only
	}
	public static function required_tables() {
		return array(
			'_CACHE' => array(
				'columns' => array(
					'MODULE_ID' => 'int',
					'CACHE_KEY' => 'varchar(200)',
					'CACHE_VALUE' => 'text',
				),
				'keys' => array(
					'PRIMARY' => array('MODULE_ID','CACHE_KEY'),
					'FOREIGN' => array(
						'MODULE_ID' => array('table' => '_MODULES','column' => 'ID')
					)
				)
			)
		);
	}
	public static function uninstall() {return false;}

	public static function ajax($args,$request) {}

	public static function menu() {return false;}

	public static function view() {
		global $local;
		header("Location: $local");
		exit();
	}


}
?>