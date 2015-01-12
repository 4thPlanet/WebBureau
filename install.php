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
	require_once('core/Layout/Layout.php');
	require_once('core/Menu/Menu.php');
	require_once('core/Tables/Tables.php');
	require_once('core/Users/Users.php');
	require_once('core/Modules/Modules.php');
	global $db;
	$db = new clientData(); ?>
<html>
	<head>
		<title>Installing system...</title>
	</head>
	<body><?
	
	
	
	/* Install the Module module... */
	echo "<p>Installing module 'module'..." . (module::install() ? "success!" : "failed!") . "</p>";
	/* Install the Users module... */
	echo "<p>Installing module 'user'..." . (users::install() ? "success!" : "failed!") . "</p>";
	/* Install the Layout module... */
	echo "<p>Installing module 'layout'..." . (layout::install() ? "success!" : "failed!") . "</p>";
	/* Install the Menu module.... */
	echo "<p>Installing module 'menu'..." . (menu::install() ? "success!" : "failed!") . "</p>";
	/* Install the Tables module... */
	echo "<p>Installing module 'table'..." . (tables::install() ? "success!" : "failed!") . "</p>";
	/* Install the Modules module... */
	echo "<p>Installing module 'modules'..." . (modules::install() ? "success!" : "failed!") . "</p>";
	
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
