<?php
class layout_wide extends layout {
	/* $initial will contain any initial scripts, stylesheets, and meta tags that should be loaded on every page. */
	public static function setup_page($initial = array(),$last_to_load=array()) {
		global $local;
		$area_content = static::load_page_data();
		$page_title = static::get_site_title($area_content);

		/* Let's rearrange ever so slightly... */
		$layout = array('header' => '', 'main' => '', 'footer' => '');
		foreach($area_content as $area=>$content) {
			switch ($area) {
				case 'header':
				case 'footer':
					$layout[$area] = $content['html'];
					break;
				default:
					$layout['main'][$area] = $content['html'];
			}
		}

		static::force_asset($initial,'css',utilities::get_public_location(__DIR__ . '/style/style.css'));
		static::force_asset($initial,'script',"{$local}script/jquery.min.js");
		static::force_asset($initial,'script',utilities::get_public_location(__DIR__ . '/script/script.js'));
?><!DOCTYPE html>
<html>
	<head>
		<title><?echo $page_title?></title>
		<?php
			/* Go through scripts (if any) */
			if (!empty($initial['script'])) {
				foreach($initial['script'] as $script) {
					if (filter_var(utilities::url_protocol_check($script),FILTER_VALIDATE_URL))
						echo "<script type='text/Javascript' src='$script'></script>";
					else echo "<script type='text/Javascript'>$script</script>";
				}
			} else $initial['script'] = array();
			/* Go through CSS */
			foreach($initial['css'] as $css) {
				if (filter_var(utilities::url_protocol_check($css), FILTER_VALIDATE_URL))
					echo "<link rel='stylesheet' type='text/css' href='$css' />";
				else echo "<style type='text/css'>$css</style>";
			}
			/* Go through meta (if any) */
			if (!empty($initial['meta'])) foreach($initial['meta'] as $key=>$value)
				echo "<meta name='$key' content='$value' />";
		$loaded_sources = $initial;
		foreach($area_content as $area=>$content) {
			/* Load any CSS from widgets */
			if (!empty($content['css'])) foreach($content['css'] as $css) {
				if (in_array($css,$loaded_sources['css'])!==false) continue;
				if (filter_var(utilities::url_protocol_check($css), FILTER_VALIDATE_URL))
					echo "<link rel='stylesheet' type='text/css' href='$css' />";
				else echo "<style type='text/css'>$css</style>";
				$loaded_sources['css'][] = $css;
			}
			/* Load any Scripts from Widgets */
			if (!empty($content['script'])) foreach($content['script'] as $script) {
				if (in_array($script,$loaded_sources['script'])) continue;
				if (filter_var(utilities::url_protocol_check($script),FILTER_VALIDATE_URL))
					echo "<script type='text/Javascript' src='$script'></script>";
				else echo "<script type='text/Javascript'>$script</script>";
				$loaded_sources['script'][] = $script;
			}
			/* Load any metas from widgets */
			if (!empty($content['meta'])) foreach($content['meta'] as $key=>$value) {
				/* No need to search for duplicate metas as they overwrite themselves already... */
				echo "<meta name='$key' content='$value' />";
			}
		}
		if ($last_to_load) {
			foreach($last_to_load as $type => $sources) {
				foreach($sources as $source) {
					if (!in_array($source,$loaded_sources[$type])) {
						switch($type) {
							case 'css':
								if (filter_var(utilities::url_protocol_check($source), FILTER_VALIDATE_URL))
									echo "<link rel='stylesheet' type='text/css' href='$source' />";
								else
									echo "<style type='text/css'>$source</style>";
								break;
							case 'script':
								if (filter_var(utilities::url_protocol_check($source), FILTER_VALIDATE_URL))
									echo "<script type='text/javascript' src='$source'></script>";
									else
										echo "<script type='text/javascript'>$source</script>";
								break;
							case 'meta':
								echo "<meta name='".key($sources)."' content='$source' />";
						}
						$loaded_sources[$type][] = $source;
					}
				}
			}
		}
		?>
	</head>
	<body>
		<?php if (!empty($layout['header'])) { ?>
		<header>
			<div class="wrapper">
				<?php foreach($layout['header'] as $idx=>$widget) { ?>
				<div class="widget <?echo $area_content['header']['widgets'][$idx]['class'];?>" widget-id="<?echo $area_content['header']['widgets'][$idx]['ID'];?>">
					<?php echo $widget; ?>
				</div>
				<?php } ?>
			</div>
		</header>
		<? } ?>

		<?php if (!empty($layout['main'])) { ?>
		<main>
			<div class="wrapper">
				<?php foreach($layout['main'] as $area=>$widgets) { ?>
				<div class="area <?php echo $area;?>">
					<?php foreach ($widgets as $idx=>$widget) { ?>
					<div class="widget <?echo $area_content[$area]['widgets'][$idx]['class'];?>" widget-id="<?echo $area_content[$area]['widgets'][$idx]['ID'];?>">
						<?php echo $widget; ?>
					</div>
					<?php } ?>
				</div>
				<?php } ?>
			</div>
		</main>
		<? } ?>

		<footer>
			<div class="wrapper">
				<?php if (!empty($layout['footer'])) foreach($layout['footer'] as $idx=>$widget) { ?>
				<div class="widget <?echo $area_content['footer']['widgets'][$idx]['class'];?>" widget-id="<?echo $area_content['footer']['widgets'][$idx]['ID'];?>">
					<?php echo $widget; ?>
				</div>
				<?php } ?>
			</div>
		</footer>
	</body>
</html>
		<?php
	}
}
?>
