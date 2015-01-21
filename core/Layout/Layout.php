<?php

/* 
 * The Layout class covers 2 core tables: _AREAS and _LAYOUT.  
 * _AREAS is a simple table listing each section which can exist on the page.
 * _LAYOUT describes which widgets go in each page
 */
class layout extends module {	
	public static function install() {
		global $db;
		
		require_once("Layout.MainContent.php");
		layout_main_content::install();

		$query = "CREATE TABLE IF NOT EXISTS _AREAS (
			ID int AUTO_INCREMENT,
			AREA_NAME VARCHAR(20),
			DESCRIPTION VARCHAR(100),
			HTML_ORDER INT,
			PRIMARY KEY (ID)
		);";
		$db->run_query($query);
		$query = "
			INSERT INTO _AREAS (AREA_NAME, DESCRIPTION, HTML_ORDER)
			SELECT tmp.AREA_NAME, tmp.DESCRIPTION, tmp.HTML_ORDER FROM (
				SELECT 'header' as AREA_NAME,'The Top Section of the Page' as DESCRIPTION, 10 as HTML_ORDER UNION
				SELECT 'left-sidebar' as AREA_NAME,'The left side of the page' as DESCRIPTION, 20 as HTML_ORDER UNION
				SELECT 'right-sidebar' as AREA_NAME,'The right side of the page' as DESCRIPTION, 30 as HTML_ORDER UNION
				SELECT 'footer' as AREA_NAME,'The bottom of the page' as DESCRIPTION, 50 as HTML_ORDER UNION
				SELECT 'main-content' as AREA_NAME,'The main content of the page' as DESCRIPTION, 40 as HTML_ORDER 
			) tmp
			LEFT JOIN _AREAS A ON tmp.AREA_NAME  = A.AREA_NAME 
			WHERE A.ID IS NULL
			ORDER BY tmp.HTML_ORDER;";
		$db->run_query($query);
		
		$query = "CREATE TABLE IF NOT EXISTS _LAYOUT (
			ID int AUTO_INCREMENT,
			AREA_ID int,
			WIDGET_ID int,
			DISPLAY_ORDER int,
			BLACKLIST_RESTRICTED bit(1),
			PRIMARY KEY (ID),
			FOREIGN KEY (AREA_ID) REFERENCES _AREAS(ID),
			FOREIGN KEY (WIDGET_ID) REFERENCES _WIDGETS(ID)
		);";
		$db->run_query($query);
		
		$query = "CREATE TABLE IF NOT EXISTS _LAYOUT_RESTRICTIONS (
			ID int AUTO_INCREMENT,
			LAYOUT_ID int,
			MODULE_ID int,
			PRIMARY KEY (ID),
			FOREIGN KEY (LAYOUT_ID) REFERENCES _LAYOUT(ID) ON DELETE CASCADE ON UPDATE CASCADE,
			FOREIGN KEY (MODULE_ID) REFERENCES _MODULES(ID) ON DELETE CASCADE ON UPDATE CASCADE
		)";
		$db->run_query($query);
		
		$query = "INSERT INTO _LAYOUT (AREA_ID, WIDGET_ID, DISPLAY_ORDER)
		SELECT A.ID, W.ID, 0
		FROM _LAYOUT L
		RIGHT JOIN _AREAS A ON L.AREA_ID = A.ID
		JOIN _WIDGETS W ON W.NAME = 'Main Content'
		WHERE A.AREA_NAME = 'main-content' AND L.ID IS NULL";
		$db->run_query($query);
		return true;
	}
	public static function uninstall() {return false;}

	public static function set_message($message,$class='') {
		$_SESSION[__CLASS__]['message'][] = array('class' => $class, 'message' => $message);
	}
	
	public static function submit_layout($layout) {
		global $db;
		/* First, clear out the existing layout... */
		$query = "DELETE FROM _LAYOUT";
		$db->run_query($query);
		foreach ($layout as $area=>$widgets) {
			$query = "SELECT ID FROM _AREAS WHERE AREA_NAME = ?";
			$params = array(
				array("type" => "s", "value" => $area)
			);
			$area_id = $db->run_query($query,$params)[0]['ID'];
			$query = "INSERT INTO _LAYOUT (AREA_ID, WIDGET_ID,DISPLAY_ORDER,BLACKLIST_RESTRICTED) VALUES ";
			$params = array();
			$values = array();
			$restrictions = array();
			$order = 0;
			foreach($widgets as $widget) {
				$values[] = "(?,?,?,?)";
				array_push($params,array("type" => "i", "value" => $area_id));
				array_push($params,array("type" => "i", "value" => $widget['id']));
				array_push($params,array("type" => "i", "value" => ++$order));
				array_push($params,array("type" => "i", "value" => array_key_exists('restrict-type',$widget) ? $widget['restrict-type'] : null));
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
	
	public static function ajax($args,$request) {
		switch($request['ajax']) {
			case 'submit-layout':
				return static::submit_layout($request['layout']);
		}
	}
	
	public static function menu() {return false;}
	public static function admin() {
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
	
	public static function view() {
		global $local;
		header("Location: $local");
		exit();
	}

	protected static function load_page_data() {
		global $db,$s_user;
		$query = "
			SELECT DISTINCT A.ID, AREA_NAME, A.HTML_ORDER
			FROM _AREAS A
			JOIN _LAYOUT L ON L.AREA_ID = A.ID
			ORDER BY A.HTML_ORDER";
		$areas = $db->run_query($query);
		
		foreach ($areas as $area) {
			$area_content[$area['AREA_NAME']] = array(
				'css' => array(),
				'script' => array(),
				'meta' => array(),
				'html' => array()
			);
			/* get each widget to display in this area... */
			$query = "
				SELECT L.ID AS LAYOUT_ID, L.BLACKLIST_RESTRICTED, W.ID, W.NAME as WIDGET_NAME, M.NAME as MODULE, RT.NAME AS TYPE, R.NAME as 'RIGHT'
				FROM _LAYOUT  L
				JOIN _WIDGETS W ON L.WIDGET_ID = W.ID
				LEFT JOIN _RIGHTS R ON W.RIGHT_ID = R.ID
				LEFT JOIN _RIGHT_TYPES RT ON R.RIGHT_TYPE_ID = RT.ID
				LEFT JOIN _MODULES M ON RT.MODULE_ID = M.ID
				WHERE AREA_ID = ?";
			$params = array(
				array("type" => "i", "value" => $area['ID'])
			);
			$widgets = $db->run_query($query,$params);
			$display_area = false;
			foreach($widgets as $widget) {
				if (!empty($widget['RIGHT']) && (empty($s_user) || !$s_user->check_right($widget['MODULE'],$widget['TYPE'],$widget['RIGHT']))) continue;
				
				if (!is_null($widget['BLACKLIST_RESTRICTED'])) {
					/* Blacklist/Whitelist check */
					$args_copy = $_GET['args'];
					$module = module::get_module($args_copy);
					$query = "
						SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS module_hit
						FROM _LAYOUT_RESTRICTIONS LR
						JOIN _MODULES M ON LR.MODULE_ID = M.ID
						WHERE LR.LAYOUT_ID = ? AND M.ID = ?";
					$params = array(
						array("type" => "i", "value" => $widget['LAYOUT_ID']),
						array("type" => "i", "value" => $module['ID'])
					);
					$result = $db->run_query($query,$params);
					if ($widget['BLACKLIST_RESTRICTED'] == $result[0]['module_hit']) 
						continue;
				}
				
				$display_area = true;
				$response = parent::widget($widget['ID']);
				if (!empty($response['title'])) $page_title = $response['title'];
				if (!is_array($response)) $response = array('html' => $response);
				
				$area_content[$area['AREA_NAME']] = array_merge_recursive($area_content[$area['AREA_NAME']],$response);
				$area_content[$area['AREA_NAME']]['widgets'][] = array(
					'class' => strtolower(
						preg_replace(
							array('/[\'"]/','/\s/'),
							array('','-'),
							$widget['WIDGET_NAME'])
					),
					'ID' => $widget['ID']
					);
			}
			if (!$display_area) unset ($area_content[$area['AREA_NAME']]);
		}
		return $area_content;
	}

	/* $initial will contain any initial scripts, stylesheets, and meta tags that should be loaded on every page. */
	public static function setup_page($initial = array()) {
		global $local;
		$page_title = '';
		$area_content = static::load_page_data();
?><!DOCTYPE html>
<html>
	<head>
		<title><?echo $page_title?></title>
		<link rel="stylesheet" type="text/css" href="<?echo $local;?>style/style.css" />
		<? if (!empty($initial)) {
			if (!empty($initial['script'])) {
				foreach($initial['script'] as $script) {
					if (filter_var(url_protocol_check($script),FILTER_VALIDATE_URL))
						echo "<script type='text/Javascript' src='$script'></script>";
					else echo "<script type='text/Javascript'>$script</script>";
				}
			} else $initial['script'] = array();
			if (!empty($initial['css'])) { 
				foreach($initial['css'] as $css) {
					if (filter_var(url_protocol_check($css), FILTER_VALIDATE_URL)) 
						echo "<link rel='stylesheet' type='text/css' href='$css' />";
					else echo "<style type='text/css'>$css</style>";
				}
				$initial['css'][] = "{$local}style/style.css";
			} else $initial['css'] = array("{$local}style/style.css");
			if (!empty($initial['meta'])) foreach($initial['meta'] as $key=>$value) 
				echo "<meta name='$key' content='$value' />";
		} else {
			$initial = array('css' => array(),'script' => array());
		}
		$loaded_sources = $initial;
		foreach($area_content as $area=>$content) {
			if (!empty($content['css'])) foreach($content['css'] as $css) {
				if (array_search($css,$loaded_sources['css'])) continue;
				if (filter_var(url_protocol_check($css), FILTER_VALIDATE_URL)) 
					echo "<link rel='stylesheet' type='text/css' href='$css' />";
				else echo "<style type='text/css'>$css</style>";
				$loaded_sources['css'][] = $css;
			}
			if (!empty($content['script'])) foreach($content['script'] as $script) {
				if (array_search($script,$loaded_sources['script'])) continue;
				if (filter_var(url_protocol_check($script),FILTER_VALIDATE_URL))
					echo "<script type='text/Javascript' src='$script'></script>";
				else echo "<script type='text/Javascript'>$script</script>";
				$loaded_sources['script'][] = $script;
			}
			if (!empty($content['meta'])) foreach($content['meta'] as $key=>$value) {
				/* No need to search for duplicate metas as they overwrite themselves already... */
				echo "<meta name='$key' content='$value' />";
			}
		}?>
	</head>
	<body>
		<div id="container">
			<div id="wrapper">
				<?php foreach($area_content as $area=>$content) { ?>
				<div class="<?php echo $area?>">
					<?php foreach($content['html'] as $idx=>$widget) { ?>
					<div class="widget <?echo $content['widgets'][$idx]['class'];?>" widget-id="<?echo $content['widgets'][$idx]['ID'];?>">
						<?php echo $widget ?>
					</div>
					<? } ?>
				</div>
				<? } ?>
			</div>
		</div>
	</body>
</html>
		<?php
	}
}
?>
