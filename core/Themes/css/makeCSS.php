<?php

global $db, $local_dir;
$local_dir = __DIR__ . '/../../../';

require_once($local_dir . 'includes/ClientData.php');
require_once($local_dir . 'core/Utilities/Utilities.php');
$db = new clientData();

require_once("../Themes.php");
$css = themes::get($_GET['file']);

if ($css){
    file_put_contents($_GET['file'], $css);
    header("Content-Type: text/css");
    echo $css;
} else {
    header('HTTP/1.0 404 Not Found');    
}
