<?php

/*
 * The Caching class is a the base for all caching done on the site.
 */
class filecaching extends caching implements CacheInterface {
	private $module;

	public function __construct($module) {
		$this->module = static::get_module_by_name($module);
	}

	public function get($key) {
		$dir = $this->getModuleCacheDirectory();
		if (file_exists($dir . '/' . $key)) {
			return unserialize(file_get_contents($dir . '/' . $key));
		} else {
			return null;
		}
	}

	public function set($key,$val) {
		$dir = $this->getModuleCacheDirectory();
		if (!file_exists($dir)) {
			mkdir($dir,0755);
		}
		file_put_contents($dir . '/' . $key . '.cache',serialize($val));
	}
	public function delete($key) {
		$dir = $this->getModuleCacheDirectory();
		if (file_exists($dir . '/' . $key . '.cache')) {
			unlink($dir . '/' . $key . '.cache');
		}
	}
	public function deleteAllModuleCache() {
		$dir = $this->getModuleCacheDirectory();
		// .. and delete all files in $dir..
		if (is_dir($dir)) {
			$fileCache = array_diff(scandir($dir),array('.','..'));
			foreach($fileCache as $file) {
				unlink($dir . '/' . $file);
			}
		}
	}
	public function deleteAllCache() {
		// loop through all modules, check if they have a directory, and delete the directory if so
		$all_modules = modules::get_all_modules();
		foreach($all_modules as $module) {
			$mod_cache = static::get_site_cacher($module['NAME']);
			$mod_cache->deleteAllModuleCache();
			if (is_dir(__DIR__ . '/cache/' . $module['NAME'])) {
				rmdir(__DIR__ . '/cache/' . $module['NAME']);
			}
		}
	}

	protected function getModuleCacheDirectory() {
		return __DIR__ . '/cache/' . $this->module->NAME;
	}

	public static function install() {
		// make __DIR__/cache/ directory, 755 permissions
		if (!file_exists(__DIR__ . '/cache/')) {
			mkdir(__DIR__ . '/cache/',0755);
		}
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