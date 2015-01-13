<?php
class layout_main_content extends layout implements widget {
	/* Prints out main content of the page... */
	public static function install() {
		global $db;
		$query = "
			INSERT INTO _WIDGETS (MODULE_ID, NAME, FILENAME, CLASS_NAME)
			SELECT M.ID, 'Main Content', ?, 'layout_main_content'
			FROM _MODULES M
			LEFT JOIN _WIDGETS W ON M.ID = W.MODULE_ID AND W.Name = 'Main Content'
			WHERE M.NAME = 'Layout' AND W.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => __FILE__)
		);
		$db->run_query($query,$params);
	}
	
	public static function view() {
		global $db, $local;
		/* Confirm args are set (will need to implement a correct way of defaulting later...) */
		$is_homepage = false;
		if (!isset($_GET['args'])) {
			$_GET['args'][] = "";
			$is_homepage = true;
			/* Get Homepage!! */
			} 
		/* First determine the module that should be used ($_GET['args'][0]) ... */
		$module = module::get_module($_GET['args']);
		if (!$module && !$is_homepage) {
			header("Location: $local");
			exit();
			return;
		} elseif (!$module && $is_homepage) {
			/* Uh oh...no home page set...*/
			$output = array(
				'html' => "<p>Welcome to your new site!  Things are looking a bit...bare.  Why don't you set things up a bit?</p>",
				'css' => "{$local}style/style.css"
			);
			return $output;
		}
		
		/* Now call that module's view function, passing in the rest of the $_GET['args'] array */
		$output = call_user_func_array(array($module['CLASS_NAME'],'view'),$_GET['args']);
		if (!array_key_exists('css',$output)) $output['css'] = array();
		array_unshift($output['css'],"{$local}style/style.css");
		if (!empty($_SESSION[__CLASS__]['message'])) {
			foreach($_SESSION[__CLASS__]['message'] as $msg) {
				$output['html'] = "<p class='{$msg['class']}'>{$msg['message']}</p>{$output['html']}";
			}
			unset($_SESSION[__CLASS__]['message']);
		}
		
		return $output;
	}
}
?>
