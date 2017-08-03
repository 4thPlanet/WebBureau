<?php

/*
 * The Caching class is a the base for all caching done on the site.
 */
class caching extends module {
	private $siteCachingMethod = null;	// If you know what you're doing, make a hand-edit here to store the caching method.

	public static function install() {
		// nothing to do...
	}
	public static function uninstall() {return false;}

	public static function ajax($args,$request) {}

	public static function menu() {return false;}

	public static function view() {
		global $local;
		header("Location: $local");
		exit();
	}

	public static function required_rights() {
		return array(
			'Caching' => array(
				'Caching' => array(
					'Set Caching Method' => array(
						'description' => 'Allows user to set caching method',
						'default_groups' => array('Admin')
					)
				)
			)
		);
	}

	public static function get_site_cacher($module) {
		// return a new instance of the cacher..
		if ($cacher = static::get_module_setting('Caching', 'Caching Method'))
			return new $cacher($module);
		else return new noCache();
	}
}

interface CacheInterface {
	public function get($key);
	public function set($key,$val);
	public function delete($key);
	public function deleteAllModuleCache();
	public function deleteAllCache();
}

class noCache implements CacheInterface {
	public function get($key) {return null; }
	public function set($key,$val) {return false; }
	public function delete($key) {return true;}
	public function deleteAllModuleCache() {return true; }
	public function deleteAllCache() {return true; }
}
?>