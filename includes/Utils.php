<?php
/* This file is for any functions which may be used throughout the system... */

function module_widget_autoload($class) {
	global $db;
	$query = "
		SELECT FILENAME FROM (
		SELECT FILENAME,CLASS_NAME FROM _MODULES UNION
		SELECT FILENAME,CLASS_NAME FROM _WIDGETS
		) FILES
		WHERE CLASS_NAME = ?";
	$params = array(
		array("type" => "s", "value" => $class)
	);
	$result = $db->run_query($query,$params);
	if (empty($result)) return false;
	require_once($result[0]['FILENAME']);
}
spl_autoload_register('module_widget_autoload');


/*
 * Takes a string, checks for http in the beginning, and if it's lacking will add it.
 * Input:
 ** $url - the url to check
 ** $protocol (optional) - the protocol to check for (Defaults to "http")
 * Returns:
 ** $url prepended with a protocol (if necessary)
 */
function url_protocol_check($url,$protocol = 'http') {
	$protocols = array("file", "https?", "ftps?", "php", "zlib", "data", "glob", "phar", "ssh2", "rar", "ogg", "expect" );
	foreach($protocols as $p) {
		$url = preg_replace("/^$p:\/\//i","",$url);
    }
    $url = preg_replace("/^\/\//","",$url);
	return $url = preg_match("/^$protocol:\/\//i",$url) ? $url : "$protocol://$url";
}

/*
 * Determines the public URL of a file
 * Input:
 ** $file - A file or directory with complete path
 * Returns:
 ** URL of where to access this file or directory
 */
function get_public_location($file) {
	global $local,$local_dir;
	if (!preg_match("/^".preg_quote($local_dir,'/')."(?<URI>.*)/",$file,$match)) return false;	//Can't be found publicly
	else return "{$local}{$match['URI']}";
}

/* 
 * Runs input through htmlspecialchars.  Useful for making an entire array HTML-safe
 * Input: 
 ** $string - either a string or an array of strings
 ** $flags (optional) - flag to be passed to htmlspecialchars. See documentation at http://us2.php.net/htmlspecialchars for more information regarding this flag.
 */
function make_html_safe($string, $flags=0) {
	if (is_string($string) || is_numeric($string) || empty($string)) {
		return htmlspecialchars($string,$flags);
	} else {
		foreach ($string as &$str) $str = make_html_safe($str,ENT_QUOTES);
		return $string;
	}
}

/* make_url_safe(), and decode_url_safe() will make use of the following constants
 ** URL_REPLACE_PATTERNS - a serialized array of regular expressions which should not appear in a URL (for instance, /[ ]/ indicates no spaces should be present)
 ** URL_SAFE_PATTERNS - corresponding with URL_SAFE_PATTERNS, a serialized array of strings which should replace URL_REPLACE_PATTERNS
 */

DEFINE('URL_REPLACE_PATTERNS',
	serialize(array(
		'/[ \.]/',
		'/[&]/',
		'/["]/'
	)));
DEFINE('URL_SAFE_PATTERNS',
	serialize(array(
		"-",
		"and",
		"'"
	)));
/* 
 * Takes a string (or array of strings) and converts them to url safe strings
 * Input:
 ** $string - either a string or an array of strings
 */
function make_url_safe($string) {
    $replace = unserialize(URL_REPLACE_PATTERNS);
	$with = unserialize(URL_SAFE_PATTERNS);
	if (is_string($string)) {
		return preg_replace($replace,$with,$string);
	} elseif (is_int($string)) {
        return $string;
    } else {
		foreach($string as $idx=>$str) 
			$string[$idx] = preg_replace($replace,$with,$str);
		return $string;
	}
}

/*
 * Takes a string (or array of strings) and returns a regex of all possible original strings (hello-world returns hello([ ]|-)world)
 * Input:
 ** $string - either a string or an array of strings
 */
function decode_url_safe($string) {

	$safe = unserialize(URL_SAFE_PATTERNS);

	$orig = unserialize(URL_REPLACE_PATTERNS);

	/* Create regex for what each safe component in $string could be... */

	foreach ($orig as $idx=>&$pattern) {

		$start_end = substr($pattern,0,1) . substr($pattern,-1,1);

		if (strcmp($start_end,'//')==0) {

			$pattern = substr($pattern,1,strlen($pattern)-2);

		}

		$pattern = "($pattern|{$safe[$idx]})";

	}

	/* Turn each $safe pattern into a regex*/

	foreach($safe as &$pattern) 

		$pattern = "/$pattern/";


	/* Now actually apply those patterns, escaping apostrophes (') in the process... */

	if (is_string($string)) {

		return "^" . preg_replace($safe,$orig,$string) . "$";

	} else {

		foreach($string as &$str) 

			$str = preg_replace($safe,$orig,$str);

		return "^$string$";

	}
}

/* 
 * Runs a hash on $password using $username as the salt
 * Input:
 ** $username - Username.  The last 4 characters will be used to salt the password
 ** $password - Password.
 ** $algo (optional) - Algorithm to be used.  Defaults to sha256.
 */
function user_password_hash($username,$password,$algo='sha256') {

	return hash($algo,substr($username,-4).$password);
}

/*  
 * Returns an array of all directories in a given location, not counting '.' and '..' 
 * Input: 
 ** $loc - Directory (optional).  Defaults to getcwd() 
 */
function get_directories($loc = '') {
	$loc = (empty($loc) ? getcwd() : $loc);
	$dir = scandir($loc);
	foreach($dir as $idx=>$file) {
		if (preg_match('/\.+/',$file) || is_file($file))
			unset($dir[$idx]);
	}
	return array_values($dir);}
	
/* 
 * Returns an array of all files with one of $filter's extensions
 * Input:
 ** $filter - The allowed extensions.  Can be a string delimited by commas or bars, or an array
 ** $loc - Directory (optional).  Defaults to getcwd()
 */
function get_files_by_ext($filter, $loc='') {
	$loc = (empty($loc) ? getcwd() : $loc);
	if (is_array($filter)) {
		$filter = implode('|',$filter);
	} else
		$filter = str_replace(',','|',$filter);
	$filter = "/.*\.($filter)/";
	$files = scandir($loc);
	foreach($files as $idx=>$file) {
		if (!is_file("$loc/$file") || !preg_match("$filter",$file))
			unset($files[$idx]);
	}
	return array_values($files);
}

/*
 * A recursive glob...
 * Input:
 ** $pattern - the pattern to check for
 ** $dir - The starting directory (defaults to $local_dir global)
 ** $flag - any flags to run
 * Returns:
 ** An array of files which match the pattern in the 
 * */
function recursive_glob($pattern,$dir="",$flag=0) {
	global $local_dir;
	if (empty($dir)) $dir = $local_dir;
	if (substr($dir,-1)!='/' && substr($pattern,0,1)!='/') $dir .= "/";
	$files = glob($dir . $pattern,$flag);
	$folders = get_directories($dir);
	if (!empty($folders))
		foreach($folders as $folder) 
			$files = array_merge($files,recursive_glob($pattern,"{$dir}{$folder}/",$flag));
	
	
	return $files;
}

/*
 * Check's a file belongs to a certain MIME type.
 * Input:
 ** $file - The file to be moved (typically $_FILES['upload-something-or-other'])
 ** $ext - Associative array of allowed file types.  Should be something like ("jpg" => "image/jpeg")
 *
 * Returns: true on valid file, error string on failure
 */
function check_file($to_move, $ext ) {
	/* Check the file is valid... */
	if (class_exists('finfo'))
	{
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$file = $finfo->file($to_move);
	} else {
		/* This only works on Unix systems... */
		$file = exec("file -bi '$to_move'");
		$file = explode(";",$file);
		$file = $file[0];
	}
	$file_ext = array_search($file,$ext);
	if (empty($file_ext)) {
		$types = array();
		foreach($ext as $e=>$type) {
			$types[] = $type;
		}
		return "'$file' is not a supported file type. Supported file types:" . implode(", " , $types);
	}
	return true;
}

/*
 * Converts string to Title Case
 * Taken from: http://www.sitepoint.com/title-case-in-php/
 * Input:
 ** $title - String to be converted to title case
 */
function strtotitle($title)
{
	// Our array of 'small words' which shouldn't be capitalised if
	// they aren't the first word. Add your own words to taste.
	$smallwordsarray = array(
	'of','a','the','and','an','or','nor','but','is','if','then','else','when',
	'at','from','by','on','off','for','in','out','over','to','into','with'
	);

	// Split the string into separate words
	$words = explode(' ', $title);

	foreach ($words as $key => $word)
	{
	// If this word is the first, or it's not one of our small words, capitalise it
	// with ucwords().
	if ($key == 0 or !in_array($word, $smallwordsarray))
	$words[$key] = ucwords($word);
	}

	// Join the words back into a string
	$newtitle = implode(' ', $words);

	return $newtitle;
}

/*
 * Converts all array keys in input to title case. Example:  array("var" => 1, "foo"=>2, "bar"=>3, "foo bar"=>4) becomes array("Var"=>1, "Foo"=>2, "Bar"=>3, "Foo Bar"=>4)
 * Input:
 ** $arr - An Associative array
 */
function title_array($arr) {
	foreach ($arr as $idx=>$val) {
		$newkey = strtotitle($idx);
		if (strcmp($newkey,$idx)==0) continue;
		$arr[$newkey] = $val;
		unset($arr[$idx]);
	}
	return $arr;
}

/*
 * Sends email
 * Input:
 ** $email - An Associative array containing all components of the email - to, from, subject, message, and any additional headers (case insensitive).  If anything is missing from to, from, subject, message, the following will default:
 *** to: returns false
 *** from: do-not-reply@coupons4autos.com
 *** subject: "<no subject>"
 *** message: ""
 **** If subject AND message are missing, they will be replaced with "Test Email", and "This is a test email.  If you feel you have received this email in error, please respond to the sender.", respectively.
 **** 
 * Returns:
 * true on success, false on failure
 */
function send_email($email) {
	/* on the off-chance all that's passed is a string, assume it to be the to field */
	if (is_string($email)) {
		$email = array("to" => $email);
	} 
	$email = title_array($email);
	
	if (empty($email['To'])) return false;
	if (empty($email['From'])) $email['From'] = 'IMPLEMENT ME!!';
	if (empty($email['Subject']) && empty($email['Message'])) {
		$email['Subject'] = "Test Email";
		$email['Message'] = "This is a test email.  If you feel you have received this email in error, please respond to the sender, {$email['from']}.";
	} elseif (empty($email['Subject'])) $email['Subject'] = "<no subject>";
	elseif (empty($email['Message'])) $email['Message'] = "";
	
	$headers = array();
	foreach($email as $header=>$val) {
		if (in_array($header,array('To','Subject','Message'))) continue;
		$headers[] = "$header: $val";
	}
	
	return mail($email['To'],$email['Subject'],$email['Message'],implode("\r\n", $headers), "-f {$email['From']}");
}

/*
 * Creates a random string
 * Input:
 ** $string - a string of acceptable characters.  Optional.  Defaults to all ASCII characters between 33 (!) and 126 (~)
 ** $length - the length of the string.  Required.
 */
function create_random_string($string, $length=0) {
	$rnd = "";
	if ($length==0) {
		/* Only the length was provided (as $string)... */
		$length = $string;
		$string = "";
		for ($i=33; $i<=126; $i++) {
			$string.= chr($i);
		}
	}
	
	$acc_length = strlen($string);

	for ($i=0; $i<$length; $i++) {
		$rnd .= $string[mt_rand(0,$acc_length)];
	}
	return $rnd;
}

/* 
 * Takes a numeric array of associative arrays, removes the numeric keys and groups by $key instead.
 * Any entries in $narr without $key will maintain their numeric key.
 * If multiple entries have the same value for $key, the later entry will override the earlier.
 * Input:
 ** $narr - A Numeric array
 ** $key - The new key to use
 */
function group_numeric_by_key($narr,$key) {
	$arr = array();
	$depth = 0;
	foreach($narr as $idx=>$val) {
		$arr[empty($val[$key]) ? $idx : $val[$key]] = $val;
		unset($arr[$val[$key]][$key]);
		if (!$depth) $depth += count(array_keys($arr[$val[$key]]));
	}
	if (!$depth) {
		$arr = array_keys($arr);
	}
	return $arr;
}

/*
 * Runs through $string, looking for substrings in the form $before$arr[idx]$after, and replaces this with $arr[idx]
 * Example: 
 ** $string: "The {speed} {color} {animal} {verb} over the {adj} {noun}."
 ** $before: "{"
 ** $after: "}"
 ** $arr: array("speed"=>"quick", "color"=>"brown", "animal"=>"fox", "verb"=>"jumped", "adj"=>"lazy", "noun"=>"dog")
 * Will return "The quick brown fox jumped over the lazy dog."
 */
function replace_formatted_string($string,$before,$after,$arr) {
    $find = array();
    $replace = array();
    foreach($arr as $key=>$val) {
        $find[] = "$before$key$after";
        $replace[] = $val;
    }
    return str_replace($find,$replace,$string);
}
?>
