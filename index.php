<?php
    global $db, $local, $local_dir, $s_user;
    $local = "http".(!empty($_SERVER['HTTPS']) ? 's' : '')."://". dirname("{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}") . "/";
    $local_dir = __DIR__ . '/';
    
    /* Remove trailing slashes and convert query string to array */
    if (!empty($_GET['q'])) {
        if (preg_match("/^(.+[^\/]+)\/$/",$_GET['q'],$args)) {
            header("HTTP/1.1 301 Moved Permanently"); 
            header("Location: {$local}{$args[1]}");
            exit();
        }
        $_GET['args'] = explode("/",$_GET['q']);
        unset($_GET['q']);
    }
    
    require_once(__DIR__ . '/includes/ClientData.php');
    $db = new clientData();
    require_once(__DIR__ . '/includes/Utils.php');
    require_once(__DIR__ . '/core/Module.php');
    require_once(__DIR__ . '/core/Widget.php');

    session_start();
    $s_user = users::get_session_user();
    
    if (array_key_exists('ajax',$_REQUEST)) {
		if (empty($_REQUEST['widget_id'])) {
			$module = module::get_module($_REQUEST['args']);
			exit(json_encode(call_user_func_array(array($module['CLASS_NAME'],'ajax'),array($_REQUEST['args'],$_REQUEST))));
		} else {
			/* specific to a given widget... */
			exit(json_encode(call_user_func_array(array('module','widget'),array($_REQUEST['widget_id'],true,$_REQUEST))));
			
		}
	} 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$args = $_GET['args'];
		$module = module::get_module($args);
		call_user_func_array(array($module['CLASS_NAME'],'post'),array($args,$_POST));
	}
    layout::setup_page();
    return;
?>
