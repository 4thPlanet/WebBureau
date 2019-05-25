<?php
if (empty($_POST['username'])) {
?>
<html>
	<head>
		<title>Install Web Bureau!</title>
		<script type="text/Javascript" src="script/jquery.min.js"></script>
		<script type="text/Javascript">
			$(function() {
				$('[name=new_db]').change(function() {
					if ($(this).val()==1) $('.root_info').show();
					else $('.root_info').hide();
				});
			});
		</script>
		<style>
			.root_info {display: none; border: inset 2px #EFEFEF; margin: 1em; background: #EEE;}
		</style>
	</head>
	<body>
		<p>Please enter the admin username, password, and email:</p>
		<form action="install.php" method="post">
			<p>
				<label for="db_location">Where is your database located?</label>
				<input id="db_location" name="db_location" value="localhost" />
			</p>

			<p>
				New DB or Existing?<br />
				<input type="radio" name="new_db" id="newDB" value="1" />
				<label for="newDB">New DB</label>
				<input type="radio" name="new_db" id="existing" value="0" />
				<label for="existing">Existing</label>
			</p>
			<div class="root_info">
				<p>
					To create a new database, we will need the Database Admin Username and password.  This information is NOT stored anywhere, and is only used to create your new Database.
				</p>
				<table>
					<tr>
						<td>
							<label for="admin_user">Database Admin Username:</label>
						</td>
						<td>
							<input id="admin_user" name="admin_user" value="root" />
						</td>
					</tr>
					<tr>
						<td>
							<label for="admin_password">Database Admin Password:</label>
						</td>
						<td>
							<input id="admin_password" name="admin_password" type="password" />
						</td>
					</tr>
				</table>
			</div>
			<p>Please enter your Database login credentials.  These will be stored in your ClientData.php file to connect to the database.</p>
			<table>
				<tr>
					<td>
						<label for="db_name">Database Name:</label>
					</td>
					<td>
						<input id="db_name" name="db_name" value="Web_Bureau" />
					</td>
				</tr>
				<tr>
					<td>
						<label for="db_user">Database User:</label>
					</td>
					<td>
						<input id="db_user" name="db_user" value="wb_user"/>
					</td>
				</tr>
				<tr>
					<td>
						<label for="db_user">Database Password:</label>
					</td>
					<td>
						<input id="db_password" name="db_password" type="password" />
					</td>
				</tr>

			</table>
			<p>Please enter the login credentials for your admin account:</p>
			<table>
				<tr>
					<td>
						<label for="username">Username:</label>
					</td>
					<td>
						<input id="username" name="username" value="admin"/>
					</td>
				</tr>
				<tr>
					<td>
						<label for="password">Password:</label>
					</td>
					<td>
						<input id="password" name="password" type="password" />
					</td>
				</tr>
				<tr>
					<td>
						<label for="email">Email:</label>
					</td>
					<td>
						<input id="email" name="email" />
					</td>
				</tr>
				<tr>
					<td colspan="100%">
						<input type="submit" value="Install system!" />
					</td>
				</tr>
		</table>
		</form>
	</body>
</html>
<?php } else {
	/* Check if existing DB or new DB... */
	if (!empty($_POST['new_db'])) {
		$db = new mysqli($_POST['db_location'], $_POST['admin_user'], $_POST['admin_password']);
		if (mysqli_connect_error()) {
			die('Unable to connect to database.');
		}
		/* Create new Database... */
		$db->query("CREATE DATABASE ".str_replace("'","\'",$_POST['db_name']) . " COLLATE = 'utf8_general_ci'");
		$db->query("CREATE USER '".str_replace("'","\'",$_POST['db_user'])."'@'localhost' IDENTIFIED BY '".str_replace("'","\'",$_POST['db_password'])."'");
		$db->query("GRANT ALL privileges ON ".str_replace("'","\'",$_POST['db_name']).".* TO '".str_replace("'","\'",$_POST['db_user'])."'@'localhost'");
	}

	// generate site salt - since our classes won't entirely work until installation is finished, copy/paste from utilities::create_random_string:
	$site_salt = "";
	for($i=0;$i<128;$i++) {
		$c = chr(rand(33,126));
		if ($c == "'") $c = '\\' . $c;
		$site_salt .= $c;
	}

	/* Now create ClientData.php */
	$clientData = '
<'.'?php
	require_once(__DIR__ . "/Database.php");

	define(\'SITE_SALT\',\''.$site_salt.'\');

	class clientData extends Database {
        protected static $db_pass = "'.str_replace('"','\"',$_POST['db_password']).'";
        protected static $db_server = "'.str_replace('"','\"',$_POST['db_location']).'";
        protected static $db_name = "'.str_replace('"','\"',$_POST['db_name']).'";
        protected static $db_login = "'.str_replace('"','\"',$_POST['db_user']).'";
        protected $db_connection;
        public function get_db_name() {
            return $this::$db_name;
        }
    }
?'.'>';
	if (!@file_put_contents('includes/ClientData.php',$clientData)) {
		die('Unable to write to ClientData.php.  Check write permissions. Current User = ' . exec('whoami'));
	}


	global $db,$local_dir;
	$local_dir = __DIR__ . "/";
	require_once('includes/ClientData.php');
	require_once('core/Utilities/Utilities.php');

	$db = new clientData();

	?>
<html>
	<head>
		<title>Installing system...</title>
	</head>
	<body><?php
	/* First install the core module module... */
	echo "Installing the base module...<br />";
	module::install();


	/* Do a search of all .module files... */
	$module_files = utilities::recursive_glob('*.module');
	/* For each .module file, convert to object from JSON */
	$every_module = array();
	$idx=0;
	foreach($module_files as $module_info_file) {
		$every_module[$idx] = json_decode(file_get_contents($module_info_file),true);
		if (is_null($every_module[$idx])) die("Invalid .module file.  Please see $module_info_file and fix.");
		$every_module[$idx]['Directory'] = dirname($module_info_file) . "/" ;
		$idx++;
	}
	/* Sort by in order of what is required first... */
	function cmp_module($a,$b) {
		if (array_search($b['Module'],$a['Requires'])!==false) return 1;
		elseif (array_search($a['Module'],$b['Requires'])!==false) return -1;
		elseif ((isset($a['Install Order']) ? $a['Install Order'] : 0) > (isset($b['Install Order']) ? $b['Install Order'] : 0)) return 1;
		else return 0;
	}
	usort($every_module,'cmp_module');
	/* Loop through each .module, if no require modules left then install (otherwise continue) */
	foreach($every_module as &$module)
		$module['Still_Requires'] = $module['Requires'];
	unset($module);
	while (true) {
		foreach($every_module as $idx=>$mod_info) {
			/* Don't install if there are other dependencies */
			if (!empty($mod_info['Still_Requires'])) continue;
			/* Install... */
			$mod_name = $mod_info['Module'];
			if (!empty($mod_info['Extends'])) {
				// Get Parent Module ID...
				$mod_info['ModuleParentID'] = module::get_module_id($mod_info['Extends']);
			}
			echo "Installing module $mod_name...<br />";
			if (!module::install_module($mod_info)) die("Unable to install module $mod_name");
			unset($every_module[$idx]);
			$left = array_keys($every_module);
			foreach($left as $idx_left) {
				$key = array_search($mod_name,$every_module[$idx_left]['Still_Requires']);
				if ($key!==false) unset ($every_module[$idx_left]['Still_Requires'][$key]);
			}
		}
		if (empty($every_module)) break;
	}

	/* Create a (very) basic layout - Centered Menu widget in header, Login/Welcome on Left Sidebar... */
	$query = "
		INSERT INTO _LAYOUT(AREA_ID, WIDGET_ID)
		SELECT A.ID, W.ID
		FROM _AREAS A, _WIDGETS W
		WHERE (A.AREA_NAME = 'header' AND W.NAME = 'Centered Menu') OR
		(A.AREA_NAME = 'left-sidebar' AND W.NAME IN ('Login','Welcome'))";
	$db->run_query($query);

	// check for missing rights for the modules module...
	$all_modules_rights = modules::required_rights();
	$all_groups = utilities::group_numeric_by_key(users::get_groups(), 'NAME');
	$admin = $all_groups['Admin']['ID'];
	foreach($all_modules_rights['Modules']['Administer'] as $right_name => $right_data)
	{
		if (!users::get_right_id('Modules','Administer',$right_name))
		{
			$new_right = users::create_right('Modules','Administer',$right_name,$right_data['description'],true);
			//assign to Admin...(STILL TODO!!)
			users::assign_rights(array($new_right => array($admin)),true);
		}
	}

	/* Add the following resources: */
	resources::addResource("style", "jQuery-UI CSS", $local_dir . "style/jquery-ui.css");
	resources::addResource("style", "jQuery Sortable CSS", $local_dir . "style/jquery-sortable.css");
	resources::addResource("style", "jQuery Timepicker CSS", $local_dir . "style/timepicker.css");

	resources::addResource("script", "jQuery", $local_dir . "script/jquery.min.js");
	resources::addResource("script", "jQuery UI", $local_dir . "script/jquery-ui.min.js");
	resources::addResource("script", "jQuery Sortable", $local_dir . "script/jquery-sortable.js");
	resources::addResource("script", "jQuery Timepicker", $local_dir . "script/jquery-ui-timepicker-addon.js");

	resources::addResource("image", "Delete Icon", $local_dir . "images/icon-delete.png");
	resources::addResource("image", "Edit Icon", $local_dir . "images/icon-edit.png");
	resources::addResource("image", "Filter Icon", $local_dir . "images/icon-filter.png");

	/* Just so there's something there, menu is the Tables module... */
	$query = "
		INSERT INTO _MENU (MODULE_ID, TEXT, DISPLAY_ORDER)
		SELECT ID, NAME, 1
		FROM _MODULES
		WHERE NAME = 'Tables'";
	$db->run_query($query);
	/* Add one more - the Modules Module (and its submenus)...*/
	modules::install_menu();

	/* Set up _TABLE_INFO table... */
	$query = "
		INSERT INTO _TABLE_INFO (TABLE_NAME, SHORT_DISPLAY) VALUES
		('_AREAS','{AREA_NAME}'),
		('_GROUPS','{NAME}'),
		('_MODULES','{NAME}'),
		('_RIGHTS','{NAME}'),
		('_RIGHT_TYPES','{NAME}'),
		('_WIDGETS','{NAME}'),
		('_USERS','{USERNAME}')";
	$db->run_query($query);

	if (
	Users::create_user(
		array(
			"username" => $_POST['username'],
			"password" => $_POST['password'],
			"email" => $_POST['email'],
			"groups" => array('Admin')
		)
	))
		echo "<p>A User account has been successfully set up!</p>";
	else echo "<p>Something went wrong...<a href='install.php'>Click Here</a> to try again...</p>";
	}?>
