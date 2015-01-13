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
							<input id="admin_password" name="admin_password" />
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
<? } else {
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
	/* Now create ClientData.php */
	$clientData = '
<?php
	require_once(__DIR__ . "/Database.php");
	class clientData extends Database {
        protected static $db_pass = "'.str_replace('"','\"',$_POST['db_password']).'";
        protected static $db_server = "'.str_replace('"','\"',$_POST['db_location']).'";
        protected static $db_name = "'.str_replace('"','\"',$_POST['db_name']).'";
        protected static $db_login = "'.str_replace('"','\"',$_POST['db_user']).'";
        public function get_db_name() {
            return $this::$db_name;
        }
    }
?>';
	if (!@file_put_contents('includes/ClientData.php',$clientData)) {
		die('Unable to write to ClientData.php.  Check write permissions. Current User = ' . exec('whoami'));
	}

	require_once('includes/ClientData.php');
	require_once('includes/Utils.php');
	require_once('core/Module.php');
	require_once('core/Widget.php');
	
	/* Hoping to get rid of this entire list... */
	/*
	require_once('core/Layout/Layout.php');
	require_once('core/Menu/Menu.php');
	require_once('core/Tables/Tables.php');
	require_once('core/Users/Users.php');
	require_once('core/Files/Files.php');
	require_once('core/Modules/Modules.php');
	*/
	global $db,$local_dir;
	$db = new clientData(); 
	$local_dir = __DIR__ . "/";
	?>
<html>
	<head>
		<title>Installing system...</title>
	</head>
	<body><?
	/* First install the core module module... */
	echo "Installing the base module...<br />";
	module::install();
	
	
	/* Do a search of all .module files... */
	$module_files = recursive_glob('*.module');
	/* For each .module file, convert to object from JSON */
	$every_module = array();
	$idx=0;
	foreach($module_files as $module_info_file) {
		$every_module[$idx] = json_decode(file_get_contents($module_info_file),true);
		$every_module[$idx]['Filename'] = dirname($module_info_file) . "/{$every_module[$idx]['Filename']}" ;
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
	while (true) {
		foreach($every_module as $idx=>$mod_info) {
			/* Don't install if there are other dependencies */
			if (!empty($mod_info['Requires'])) continue;
			/* Install... */
			$mod_name = $mod_info['Module'];
			echo "Installing module $mod_name...<br />";
			if (!module::install_module($mod_info)) die("Unable to install module $mod_name");
			unset($every_module[$idx]);
			$left = array_keys($every_module);
			foreach($left as $idx_left) {
				$key = array_search($mod_name,$every_module[$idx_left]['Requires']);
				if ($key!==false) unset ($every_module[$idx_left]['Requires'][$key]);
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
