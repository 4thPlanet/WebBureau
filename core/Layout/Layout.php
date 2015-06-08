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

	public static function ajax($args,$request) {}

	public static function menu() {return false;}

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
			$args = isset($_GET['args']) ? $_GET['args'] : array();
			foreach($widgets as $widget) {
				if (!empty($widget['RIGHT']) && (empty($s_user) || !$s_user->check_right($widget['MODULE'],$widget['TYPE'],$widget['RIGHT']))) continue;

				if (!is_null($widget['BLACKLIST_RESTRICTED'])) {
					/* Blacklist/Whitelist check */
					$args_copy = $args;
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

				if (empty($response)) continue;
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
		<?php if (!empty($initial)) {
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
			$initial = array('css' => array("{$local}style/style.css"),'script' => array());
		}
		$loaded_sources = $initial;
		foreach($area_content as $area=>$content) {
			if (!empty($content['css'])) foreach($content['css'] as $css) {
				if (in_array($css,$loaded_sources['css'])!==false) continue;
				if (filter_var(url_protocol_check($css), FILTER_VALIDATE_URL))
					echo "<link rel='stylesheet' type='text/css' href='$css' />";
				else echo "<style type='text/css'>$css</style>";
				$loaded_sources['css'][] = $css;
			}
			if (!empty($content['script'])) foreach($content['script'] as $script) {
				if (in_array($script,$loaded_sources['script'])) continue;
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
					<?php } ?>
				</div>
				<?php } ?>
			</div>
		</div>
	</body>
</html>
		<?php
	}
}
?>