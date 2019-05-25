<?php
class layout_admin extends layout {
	public static function ajax($args,$request) {
		global $s_user;
		if (!$s_user->check_right('Modules','Administer','Administer Layout')) {return false;}
		switch($request['ajax']) {
			case 'get_widget_setup':
				return module::setup_widget($request['widget']);
			case 'submit-layout':
				return static::submit_layout($request['layout']);
			case 'static_block_edit':
				return static::save_static_html($request['block']);
			case 'static_block_delete':
				return !is_string(static::delete_static_html($request['id']));
		}
	}

	public static function post($args,$request) {
		if (empty($args))
		{
			// blank args
			if ( isset($_POST['site_title']))
			{
				static::set_module_setting('Layout', 'Site Title', $_POST['site_title']);
				layout::set_message('Successfully saved site title.','success');
			}
			if (isset($_POST['favicon']))
			{
				static::set_module_setting('Layout', 'favicon', $_POST['favicon']);
				layout::set_message('Successfully saved site favicon.','success');
			}
		}
	}

	public static function edit_static_html($id=""){
		global $local,$db;
		$output = array(
			"html" => "",
			"script" => array(
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				"{$local}script/ckeditor/ckeditor.js",
				"{$local}script/ckeditor/adapters/jquery.js",
				utilities::get_public_location(__DIR__ . '/js/static_blocks.js')
			),
			"css" => array(
				"{$local}style/jquery-ui.css",
				utilities::get_public_location(__DIR__ . '/style/static_blocks.css')
			)
		);
		$output['html'] .= <<<HTML
<h2>Static HTML</h2>
<p>Here you can create and edit static HTML blocks to place in your layout.</p>
<form id="static-blocks-form" method="post" action="">
	<table>
		<thead>
			<tr>
				<th>Identifier</th>
				<th>HTML</th>
				<th>Actions</th>
			</tr>
		</thead>
HTML;

		$query = "
			SELECT ID,IDENTIFIER,HTML
			FROM _LAYOUT_STATIC_HTML
		";
		$static_blocks = utilities::make_html_safe($db->run_query($query),ENT_QUOTES);
		foreach($static_blocks as $block)
		{
			$output['html'] .= <<<INPUT
		<tr data-id="{$block['ID']}">
			<td><input class="identifier" placeholder="Identifier" name="block[{$block['ID']}][id]" value="{$block['IDENTIFIER']}" /></td>
			<td><textarea name="block[{$block['ID']}][html]">{$block['HTML']}</textarea></td>
			<td>
				<button class="save" type="button">Save</button>
				<a href="#" class="delete" title="Click here to delete block."><img src="{$local}images/icon-delete.png" alt="Delete" /></a>
			</td>
		</tr>
INPUT;
		}

		$output['html'] .= <<<INPUT
		<tr>
			<td><input class="identifier" placeholder="Identifier" name="block[new][id]"/></td>
			<td><textarea name="block[new][html]"></textarea></td>
			<td><button class="save" type="button">Save</button></td>
		</tr>
INPUT;

		$output['html'] .= "</table></form>";
		return $output;
	}

	public static function get_module_url() {
		return Modules::get_module_url() . 'Layout/';
	}

	public static function view($sub="") {
		global $local, $db;

		$args = func_get_args();
		if ($args) {
			$sub = array_shift($args);
			$method = false;
			if ($sub == 'static-text') {
				$method = 'edit_static_html';
			}
			if ($method)
				return call_user_func_array(array(__CLASS__,$method),$args);
			else {
				header("Location: " . static::get_module_url());
				exit;
				return;
			}
		}

		$output = array('html' => "",
			'script' => array(
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				utilities::get_public_location(__DIR__ . '/js/layout.js')
			),
			'css' => array(
				"{$local}style/jquery-ui.css",
				utilities::get_public_location(__DIR__ . '/style/layout.css')
			)
		);
		$site_title = utilities::make_html_safe(static::get_module_setting('Layout','Site Title'),ENT_QUOTES);
		$site_icon = static::get_module_setting('Layout', 'favicon');
		$all_icons = resources::get_resources_by_type('icon');

		$icon_options = "";
		foreach($all_icons as $icon) {
		    // selected?
		    $selected = $icon['ID'] == $site_icon ? 'selected="selected"' : '';
		    
			$icon_options .= "<option value='{$icon['ID']}' $selected>{$icon['NAME']}</option>";
		}

		$output['html'] .= <<<SETTINGS
<form id='layout_settings' method='post' action=''>
	<label for='site_title'>Site Title:</label>
	<input id='site_title' name='site_title' value='{$site_title}' />
	<label for='favicon'>Favicon:</label>
	<select id='favicon' name='favicon'>
		$icon_options
	</select>
	<input type='submit' value='Save' />
</form>
SETTINGS;

		$output['html'] .= "<p><a href='".static::get_module_url()."static-text'>Click here to edit static text blocks.</a></p>";
		/* Loop through each area... */
		$query = "SELECT * FROM _AREAS ORDER BY HTML_ORDER";
		$areas = $db->run_query($query);
		$area_names = array();
		$restrictions = array();
		foreach($areas as $area) {
			$area_names[] = $area['AREA_NAME'];
			$output['html'] .= "<div class='layout {$area['AREA_NAME']}'>
				<h4>{$area['AREA_NAME']}</h4>
				<ul class='widgets'>";
			$query = "SELECT W.ID, W.NAME, L.BLACKLIST_RESTRICTED, L.ID as LAYOUT_ID, L.ARGUMENTS, W.CLASS_NAME
			FROM _LAYOUT L
			JOIN _WIDGETS W ON L.WIDGET_ID = W.ID
			WHERE L.AREA_ID = ?
			ORDER BY L.DISPLAY_ORDER";
			$params = array(
				array("type" => "i", "value" => $area['ID'])
			);
			$area_widgets = $db->run_query($query,$params);
			foreach($area_widgets as $aw) {
				$has_setup = (int) method_exists($aw['CLASS_NAME'],'setup');
				$args = $has_setup ? utilities::make_html_safe(json_encode(unserialize($aw['ARGUMENTS'])),ENT_QUOTES) : "";
				$setup = $has_setup ? "<button class='setup'>Setup...</button>" : "";


				$output['html'] .= "
					<li data-args='$args' name='widget-{$aw['ID']}'>{$aw['NAME']}$setup<span title='Click here to remove this widget.' class='remove'></span><span title='Click here to black/whitelist this widget.' class='list'></span></li>";
				if (!is_null($aw['BLACKLIST_RESTRICTED'])) {
					$query = "
						SELECT MODULE_ID
						FROM _LAYOUT_RESTRICTIONS
						WHERE LAYOUT_ID = ?";
					$params = array(
						array("type" => "i", "value" => $aw['LAYOUT_ID'])
					);
					array_push($restrictions,
						array(
							'id' => $aw['ID'],
							'type' => $aw['BLACKLIST_RESTRICTED'],
							'mods' => utilities::group_numeric_by_key($db->run_query($query,$params),'MODULE_ID')
						));
				}
			}

			$output['html'] .= "
				</ul>
			</div>";
		}
		$output['html'] .= "
			<p class='clear'><button id='save_layout'>Save Layout</button></p>";
		/* List available widgets... */
		$query = "SELECT ID, NAME, CLASS_NAME FROM _WIDGETS";
		$widgets = $db->run_query($query);
		$output['html'] .= "
			<h3>Available Widgets</h3>
			<ul id='widget_list'>";
		foreach($widgets as $widget) {
			$has_setup = (int) method_exists($widget['CLASS_NAME'],'setup');
			$output['html'] .= "
				<li data-requires-setup='$has_setup' name='widget-{$widget['ID']}'>{$widget['NAME']}<button class='addTo'>Add To...</button></li>";
		}
		$output['html'] .= "
			</ul>";
		$output['script'][] = "var areas = " . json_encode($area_names) . ";" ;
		$query = "SELECT ID, NAME FROM _MODULES";
		$modules = utilities::group_numeric_by_key($db->run_query($query),'ID');
		$output['script'][] = "var modules = ". json_encode($modules) . ";";
		$output['script'][] = "var restrictions = " . json_encode($restrictions) . ";";
		return $output;
	}

	protected static function submit_layout($layout) {
		global $db;
		/* First, clear out the existing layout... */
		$query = "DELETE FROM _LAYOUT";
		$db->run_query($query);
		foreach ($layout as $area=>$widgets) {
			$query = "SELECT ID FROM _AREAS WHERE AREA_NAME = ?";
			$params = array(
				array("type" => "s", "value" => $area)
			);
			$result = $db->run_query($query,$params);
			$area_id = $result[0]['ID'];
			$query = "INSERT INTO _LAYOUT (AREA_ID, WIDGET_ID,DISPLAY_ORDER,BLACKLIST_RESTRICTED,ARGUMENTS) VALUES ";
			$params = array();
			$values = array();
			$restrictions = array();
			$order = 0;
			foreach($widgets as $widget) {
				$values[] = "(?,?,?,?,?)";
				array_push(
					$params,
					array("type" => "i", "value" => $area_id),
					array("type" => "i", "value" => $widget['id']),
					array("type" => "i", "value" => ++$order),
					array("type" => "i", "value" => array_key_exists('restrict-type',$widget) ? $widget['restrict-type'] : null),
					array("type" => "s", "value" => array_key_exists('args',$widget) ? serialize($widget['args']) : null)
				);
				if (array_key_exists('restrict-type',$widget)) {
					array_push(
						$restrictions,
						array(
							'area' => $area_id,
							'widget' => $widget['id'],
							'modules' => $widget['restrictions']
						)
					);
				}

			}
			if (empty($values)) continue;
			$query .= implode(", ", $values);
			$db->run_query($query,$params);
			if (!empty($restrictions)) {
				foreach($restrictions as $restriction) {
					$query = "
						INSERT INTO _LAYOUT_RESTRICTIONS (LAYOUT_ID,MODULE_ID)
						SELECT L.ID, M.ID
						FROM _LAYOUT L, _MODULES M
						WHERE L.AREA_ID = ? AND L.WIDGET_ID = ? AND M.ID IN (".substr(str_repeat("?,",count($restriction['modules'])),0,-1).")";
					$params = array(
						array("type" => "i", "value" => $restriction['area']),
						array("type" => "i", "value" => $restriction['widget'])
					);
					foreach($restriction['modules'] as $module)
						array_push($params, array("type" => "i", "value" => $module));
					$db->run_query($query,$params);
				}
			}
		}
		return array('success' => 1);
	}
}
?>
