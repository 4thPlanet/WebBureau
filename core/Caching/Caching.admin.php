<?php
class caching_admin extends caching {
	public static function ajax($args,$request) {
		global $s_user;
		if (!$s_user->check_right('Modules','Administer','Administer Caching')) {return false;}
		switch($request['ajax']) {
			default:
				// what to do?
		}
	}

	public static function post($args,$request) {
		if (empty($args))
		{
			if (isset($request['site_cacher'])) {
				$caching_module = static::get_module_by_name('Caching');
				// confirm value is an extension of Caching module...
				// yes? then save..
				$caching_module->set_module_setting($caching_module->NAME, "Caching Method", $request['site_cacher']);
				layout::set_message("Successfully saved site caching method.","success");
			}
			elseif (isset($request['module_cache_flush'])) {
				$cache = static::get_site_cacher($request['module_cache_flush']);
				$cache->deleteAllModuleCache();
				layout::set_message("Successfully flushed {$request['module_cache_flush']} cache.","success" );
			}
			elseif (isset($request['flush_all_cache'])) {
				$cache = static::get_site_cacher('Caching');
				$cache->deleteAllCache();
				layout::set_message("Successfully flushed all site cache.","success" );
			}
		}
	}

	public static function get_module_url() {
		return Modules::get_module_url() . 'Caching/';
	}

	public static function view() {
		global $local, $db;

		$caching_module = static::get_module_by_name('Caching');

		// Get list of all modules that extend Caching, these are the available options...
		$current_caching_method = $caching_module->get_module_setting($caching_module->NAME, "Caching Method");
		$caching_options = $caching_module->get_module_extensions();

		$output = array('html' => "<h2>Caching Administration</h2>");

		$output['html'] .= "
	<h3>Set Caching Method</h3>
	<form method='post'>
		<p>Select Caching Method: <select name='site_cacher'>";
		foreach($caching_options as $option) {
			$selected = $option['NAME'] == $current_caching_method ? 'selected="selected"' : '';
			$output['html'] .= "<option value='{$option['NAME']}' $selected>{$option['NAME']}</option>";
		}

		$output['html'] .= "</select><input type='submit' value='Save' /></p>
	</form>
	<h3>Flush Cache</h3>
	<form method='post'>
		<label>Flush Cache for Module: <select name='module_cache_flush'>";

		$all_modules = modules::get_all_modules();
		foreach($all_modules as $module) {
			$output['html'] .= "<option value='{$module['NAME']}'>{$module['NAME']}</option>";
		}
		$output['html'] .= "</select></label><input type='submit' value='Flush' /></form><form method='post'><p><button name='flush_all_cache' value='1'>Flush ALL Cache</button></p></form>";
		return $output;
	}
}
?>
