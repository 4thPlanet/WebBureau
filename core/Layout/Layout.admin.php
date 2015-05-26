<?php
class layout_admin extends layout {
	public static function ajax($args,$request) {
		switch($request['ajax']) {
			case 'submit-layout':
				return static::submit_layout($request['layout']);
		}
	}

	public static function post($args,$request) {}

	public static function view() {
		global $local, $db;
		$output = array('html' => "",
			'script' => array(
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				get_public_location(__DIR__ . '/js/layout.js')
			),
			'css' => array(
				"{$local}style/jquery-ui.css",
				get_public_location(__DIR__ . '/style/layout.css')
			)
		);
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
			$query = "SELECT W.ID, W.NAME, L.BLACKLIST_RESTRICTED, L.ID as LAYOUT_ID
			FROM _LAYOUT L
			JOIN _WIDGETS W ON L.WIDGET_ID = W.ID
			WHERE L.AREA_ID = ?
			ORDER BY L.DISPLAY_ORDER";
			$params = array(
				array("type" => "i", "value" => $area['ID'])
			);
			$area_widgets = $db->run_query($query,$params);
			foreach($area_widgets as $aw) {
				$output['html'] .= "
					<li name='widget-{$aw['ID']}'>{$aw['NAME']}<span title='Click here to remove this widget.' class='remove'></span><span title='Click here to black/whitelist this widget.' class='list'></span></li>";
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
							'mods' => group_numeric_by_key($db->run_query($query,$params),'MODULE_ID')
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
		$query = "SELECT ID, NAME FROM _WIDGETS";
		$widgets = $db->run_query($query);
		$output['html'] .= "
			<h3>Available Widgets</h3>
			<ul id='widget_list'>";
		foreach($widgets as $widget) {
			$output['html'] .= "
				<li name='widget-{$widget['ID']}'>{$widget['NAME']}<button class='addTo'>Add To...</button></li>";
		}
		$output['html'] .= "
			</ul>";
		$output['script'][] = "var areas = " . json_encode($area_names) . ";" ;
		$query = "SELECT ID, NAME FROM _MODULES";
		$modules = group_numeric_by_key($db->run_query($query),'ID');
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
			$result = $db->run_query($query,$params)
			$area_id = $result[0]['ID'];
			$query = "INSERT INTO _LAYOUT (AREA_ID, WIDGET_ID,DISPLAY_ORDER,BLACKLIST_RESTRICTED) VALUES ";
			$params = array();
			$values = array();
			$restrictions = array();
			$order = 0;
			foreach($widgets as $widget) {
				$values[] = "(?,?,?,?)";
				array_push(
					$params,
					array("type" => "i", "value" => $area_id),
					array("type" => "i", "value" => $widget['id']),
					array("type" => "i", "value" => ++$order),
					array("type" => "i", "value" => array_key_exists('restrict-type',$widget) ? $widget['restrict-type'] : null)
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
