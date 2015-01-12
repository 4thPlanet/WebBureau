<?php

class users extends module {
	protected $user_info;
	private $rights;
	public function __construct($user = array('ID'=>0)) {
		$this->user_info = $user;
		$this->reload_rights();
		$this->reload_groups();
	}
	
	public function is_group_member($group) {
		return (!empty($this->user_info['GROUPS'][$group]));
	}
	
	public function reload_groups() {
		global $db;
		$groups = &$this->user_info['GROUPS'];
		$query = "SELECT G.*
			FROM _USERS_GROUPS UG
			JOIN _GROUPS G ON UG.GROUP_ID = G.ID
			WHERE UG.USER_ID = ?";
		$params = array(
			array("type" => "i", "value" => $this->user_info['ID'])
		);
		$groups = group_numeric_by_key($db->run_query($query,$params),'NAME');
		if (!empty($groups)) return;
		$query = "SELECT * FROM _GROUPS G WHERE G.NAME = ?";
		$params = array(
			array("type" => "s", "value" => "Guest")
		);
		$groups = group_numeric_by_key($db->run_query($query,$params),'NAME');
	}
	
	public function check_right($module,$type,$right) {
		$rights = $this->rights;
		if (
			empty($rights[$module]) ||
			empty($rights[$module][$type]) || 
			!array_key_exists($right,$rights[$module][$type])
		) return false;
		else return $rights[$module][$type][$right];
	}
	
	public function reload_rights() {
		/* Get a list of all rights user has */
		global $db;
		$this->rights = array();
		$user_rights = &$this->rights;
		$query = "SELECT M.NAME as MODULE, T.NAME AS TYPE, R.NAME AS 'RIGHT'
		FROM _USERS U
		RIGHT JOIN (
			SELECT G.ID as GROUP_ID, UG.USER_ID AS USER_ID
			FROM _GROUPS G
			LEFT JOIN _USERS_GROUPS UG ON G.ID = UG.GROUP_ID
			WHERE UG.USER_ID IS NOT NULL OR G.NAME = ?
		) UG ON
			U.ID = UG.USER_ID
		JOIN _GROUPS_RIGHTS GR ON UG.GROUP_ID = GR.GROUP_ID
		JOIN _RIGHTS R ON GR.RIGHT_ID = R.ID
		JOIN _RIGHT_TYPES T ON R.RIGHT_TYPE_ID = T.ID
		JOIN _MODULES M ON T.MODULE_ID = M.ID
		WHERE U.ID = ? OR (?=0 AND U.ID IS NULL)";
		$params = array(
			array("type" => "s", "value" => 'Guest'),
			array("type" => "i", "value" => $this->user_info['ID']),
			array("type" => "i", "value" => $this->user_info['ID'])
		);
		$rights = $db->run_query($query,$params);
		foreach($rights as $right) {
			$user_rights[$right['MODULE']][$right['TYPE']][$right['RIGHT']] = 1;
			$user_rights[$right['MODULE']][$right['TYPE']][$right['RIGHT']] = 1;
		}
	}
	
	public function id() {return $this->user_info['ID'];}
	
	public function get_hashed_password() {return $this->user_info['PASSWORD'];}
	public function refresh_hashed_password($pw) {$this->user_info['PASSWORD'] = $pw;}
	
	public function is_current_user($username) {
		return $username = $this->user_info['USERNAME'];
	}
	
	public static function get_session_user() {
		if (isset($_SESSION['users']['user'])) return $_SESSION['users']['user'];
		else {
			$_SESSION['users']['user'] = new users();
			return $_SESSION['users']['user'];
		}
	}
	
	public static function current_user_is_guest() {
		global $s_user;
		if ($s_user->user_info['ID']==0) return true;
		else return false;
	}
	
	public static function logout() {
		/* Processes logout request */
		global $local;
		$_SESSION[__CLASS__]['user'] = new users(array("ID"=>0));
		header("Location: {$local}");
		exit();
		return;
	}
	public static function login_validate() {
		/* Validates login request.  Returns a User object on success, error message on failure */
		global $local, $db;
		if (empty($_POST['username']) || empty($_POST['password'])) {
			return;
		} else {
			$username = $_POST['username'];
			$password = user_password_hash($username,$_POST['password']);
			$query = "SELECT *
			FROM _USERS
			WHERE USERNAME = ? AND PASSWORD = ?";
			$params = array(
				array("type" => "s", "value" => $username),
				array("type" => "s", "value" => $password)
			);
			$user = $db->run_query($query,$params);
			if (empty($user)) {
				/* Redirect to login page with error message "Invalid Login" */
				layout::set_message("Invalid Login.", "error");
				return;
			} else {
				$user = $user[0];
				$_SESSION['users']['user'] = new users($user);
				header("Location: {$_SERVER['HTTP_REFERER']}");
				exit();
				return;
			}
		}
	}
	
	private static function register_submit($data) {
		global $local,$db;
		/* Try to register the user... */
		/* Confirm password.. */
		if ($data['password']!=$data['confirm']) {
			Layout::set_message('Please confirm password.','error');
			header("Location: {$_SERVER['HTTP_REFERER']}");
			exit();
			return;
		}
		$result = users::create_user($data);
		if ($result !== true) {
			Layout::set_message($result,'error');
			header("Location: {$_SERVER['HTTP_REFERER']}");
			exit();
			return;
		} else {
			if (static::current_user_is_guest()) {
				Layout::set_message("Thank you for registering.  If you provided an email, your login credentials have been sent to that address.",'info');
				$query = "SELECT * FROM _USERS WHERE USERNAME = ?";
				$params = array(
					array("type" => "s", "value" => $data['username'])
				);
				$_SESSION[__CLASS__]['user'] = new users($db->run_query($query,$params)[0]);
				header("Location: {$local}");
				exit();
				return;
			} else {
				Layout::set_message("The user has been registered.  If an email address was provided, their login credentials will be sent to that address",'info');
				header("Location: {$local}Users/");
				exit();
				return;
			}		
		}
		
		
		
	}
	
	public static function register() {
		global $local,$db;
		$user = static::get_session_user();
		if (!$user->check_right('Users','Action','Register Self') && !$user->check_right('Users','Action','Register Others')) {
			header("Location: {$local}");
			exit();
			return;
		}
		$output = array('html' => '', 'script' => array());
		$output['html'] .= "
		<form action='' method='post'>
			<table>
				<tr>
					<td><label for='reg_username'>Username</label></td>
					<td><input id='reg_username' name='username' /></td>
				</tr>
				<tr>
					<td><label for='reg_password'>Password</label></td>
					<td><input type='password' id='reg_password' name='password' />
				</tr>
				<tr>
					<td><label for='confirm'>Confirm password</label></td>
					<td><input type='password' id='confirm' name='confirm' />
				</tr>
				<tr>
					<td><label for='email'>Email Address</label></td>
					<td><input type='email' id='email' name='email' />
				</tr>
				<tr>
					<td><label for='display'>Display Name</label></td>
					<td><input type='display' id='display' name='display' />
				</tr>";
		if ($user->check_right('Users','Action','Register Others')) {
			$output['html'] .= "
				<tr>
					<td colspan='100%'><input type='button' id='groups' value='Groups' /></td>
				</tr>
			";
			$output['script'][] = "{$local}script/jquery.min.js";
			$output['script'][] = "{$local}script/jquery-ui.min.js";
			$output['css'][] = "{$local}style/jquery-ui.css";
			$output['css'][] = get_public_location(__DIR__ . '/style/user-groups.css');
			$output['script'][] = get_public_location(__DIR__ . '/js/user-groups.js');
			$query = "SELECT NAME FROM _GROUPS WHERE NAME NOT IN ('Guest','Registered User')";
			$groups = json_encode(group_numeric_by_key($db->run_query($query),'NAME'));
			
			$output['script'][] = "var groups = " . $groups;
		}
		
		$output['html'] .= "
				<tr>
					<td colspan='100%'>
						<input type='submit' value='Register!' />
					</td>
				</tr>
			</table>
		</form>";
		return $output;
	}
	
	public static function view_all_users() {
		global $local, $db;
		$user = static::get_session_user();
		if (!$user->check_right('Users','View','View Users')) {
			header("Location: $local");
			exit();
			return;
		}
		
		$output = array('html' => '', 'title' => 'Users');
		$query = "
			SELECT U.USERNAME, GROUP_CONCAT(G.NAME) as Groups
			FROM _USERS U
			JOIN _USERS_GROUPS UG ON U.ID = UG.USER_ID
			JOIN _GROUPS G ON UG.GROUP_ID = G.ID
			GROUP BY U.USERNAME";
		$users = $db->run_query($query);
		$output['html'] .= "
		<h3>Users</h3>
		<table>
			<thead>
				<tr>
					<th>User</th>
					<th>Groups</th>
				</tr>
			</thead>
			<tbody>";
		foreach($users as $user) {
			$groups = explode(",",$user['Groups']);
			foreach($groups as &$group)
				$group = "<a href='".static::get_module_url()."groups/".make_url_safe($group)."'>$group</a>";
			$user['Groups'] = implode(",",$groups);
			$output['html'] .= "
				<tr>
					<td>
						<a href='{$local}Users/{$user['USERNAME']}/' title='Click here to view this user.'>{$user['USERNAME']}</a>
					</td>
					<td>
						{$user['Groups']}
					</td>
				</tr>";
		}
		$output['html'] .= "
			</tbody>
		</table>";
		return $output;
	}
	
	public static function view_user($username) {
		global $local, $db;
		/* RIGHTS CHECK!! */
		$user = static::get_session_user();
		if (!$user->check_right('Users','View','View Users')) {
			header("Location: $local");
			exit();
			return;
		}
		/* Confirm Real User... */
		$query = "SELECT ID,EMAIL,REGISTER_DATE FROM _USERS WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $username)
		);
		$user_info = $db->run_query($query,$params);
		if (empty($user_info)) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		$user_info = $user_info[0];
		$is_session_user = $user->id() === $user_info['ID'];
		$output = array('html' => '');
		$output['html'] .= "<h3>$username</h3>";
		$output['html'] .= "<p>
			Registered Date: " . preg_replace('/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/','$2/$3/$1',$user_info['REGISTER_DATE']) .
			($is_session_user || $user->check_right("Users","View","View Users' Email") ? "<br />Email: {$user_info['EMAIL']}" : "") . "</p>";
		if ($is_session_user) {
			$output['html'] .= "<p><a href='".static::get_module_url()."$username/edit'>Edit User Info</a></p>";
		}
		$output['html'] .= "<p><a href='".static::get_module_url()."'>Back to Users</a></p>";
		return $output;
	}
	
	public static function view_group($group) {
		global $local, $db;
		/* Confirm Real Group... */
		$query = "
			SELECT NAME
			FROM _GROUPS 
			WHERE NAME RLIKE ?";
		$params = array(
			array("type" =>"s", "value" => decode_url_safe($group))
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			header("Location: " . static::get_module_url());
			exit();
		} else $group = $result[0]['NAME'];
		/* RIGHTS CHECK!! Users/View Group/View <Group Name>*/
		$s_user = static::get_session_user();
		if (!$s_user->check_right("Users","Groups","View $group")) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		
		$output = array('html' => '');
		$output['html'] .= "<h3>$group User Group</h3><p>The following members belong to this user group:</p>";
		$output['html'] .= "<table>
			<thead>
				<tr>
					<th>User</th>
					<th>Groups</th>
				</tr>
			</thead>
			<tbody>";
		
		$query = "
			SELECT U.USERNAME, GROUP_CONCAT(G2.NAME) as GROUPS
			FROM _GROUPS G
			JOIN _USERS_GROUPS UG ON G.ID = UG.GROUP_ID
			JOIN _USERS U ON UG.USER_ID = U.ID
			JOIN _USERS_GROUPS UG2 ON U.ID = UG2.USER_ID
			JOIN _GROUPS G2 ON UG2.GROUP_ID = G2.ID
			WHERE G.NAME = ?";
		$params = array(
			array("type" => "s", "value" => $group)
		);
		$users = $db->run_query($query,$params);
		foreach($users as $user) {
			$user_html = make_html_safe($user['USERNAME']);
			$user_url = make_url_safe($user['USERNAME']);
			$groups = explode(",",$user['GROUPS']);
			foreach($groups as $idx=>&$group) {
				if (!$s_user->check_right('Users','Groups',"View $group")) {
					unset($groups[$idx]);
					continue;
				}
				$group = "<a href='".static::get_module_url()."groups/".make_url_safe($group)."'>".make_html_safe($group)."</a>";
			}
			unset($group);
			$output['html'] .= "
				<tr>
					<td><a href='".static::get_module_url() ."$user_url'>$user_html</a></td>
					<td>".implode(",",$groups)."</td>
				</tr>
			";
		}
		
		$output['html'] .= "			
			</tbody>
		</table>";
		return $output;
	}
	
	public static function edit_user($user) {
		global $local, $db;
		/* This should only be viewable if the current user = $user */
		$query = "SELECT ID,EMAIL,DISPLAY_NAME FROM _USERS WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $user)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		$s_user = static::get_session_user();
		if ($s_user->id() !== $result[0]['ID']) {
			header("Location: " . static::get_module_url() . "$username");
			exit();
			return;
		}
		$user_info = make_html_safe($result[0],ENT_QUOTES);
		
		
		$output = array('html' => '<h3>Edit User Information</h3>');
		$output['html'] .= "<form method='post' action=''>";
		$output['html'] .= "
				<p><a class='button' href='password'>Change Password</a></p>
				<div>
					<label for='email'>Email Address</label>
					<input id='email' name='email' value='{$user_info['EMAIL']}' />
				</div>
				<div>
					<label for='display'>Display Name</label>
					<input id='display' name='display' value='{$user_info['DISPLAY_NAME']}' />
				</div>
				<p><input type='submit' value='Submit Changes' /> or <a href='".static::get_module_url() . $user."'>Cancel</a>.</p>";
		$output['html'] .= "</form>";

		return $output;
	}
	
	public static function update_user($user,$data) {
		global $db;
		/* Confirm Email is valid */
		if (filter_var($data['email'],FILTER_VALIDATE_EMAIL)===false) {
			layout::set_message('Invalid email supplied.','error');
			return false;
		}
		$query = "
			UPDATE _USERS SET
				EMAIL = ?,
				DISPLAY_NAME = ?
			WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $data['email']),
			array("type" => "s", "value" => empty($data['display']) ? null : $data['display']),
			array("type" => "s", "value" => $user)
		);
		$db->run_query($query,$params);
		layout::set_message('User information updated.','info');
		header("Location: " . static::get_module_url() . "$user");
		exit();
		return;
		
	}
	
	public static function edit_password($username) {
		global $local, $db;
		/* This should only be viewable if the current user = $user */
		$query = "SELECT ID FROM _USERS WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $username)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			header("Location: " . static::get_module_url());
			exit();
			return;
		}
		$s_user = static::get_session_user();
		if ($s_user->id() !== $result[0]['ID']) {
			header("Location: " . static::get_module_url() . "$username");
			exit();
			return;
		}
		
		$output = array('html' => '<h3>Update Password</h3>');
		$output['html'] .= "<form action='' method='post'>
			<div>
				<label for='old'>Current Password:</label>
				<input id='old' name='old' type='password' />
			</div>
			<div>
				<label for='new'>New Password:</label>
				<input id='new' name='new' type='password' />
			</div>
			<div>
				<label for='confirm'>Confirm New Password:</label>
				<input id='confirm' name='confirm' type='password' />
			</div>
			<div>
				<input type='submit' value='Set New Password' /> or <a href='".static::get_module_url().$username."/edit'>Return to User Edit</a>
			</div>
		</form>";
		return $output;
	}
	
	public static function update_password($username,$data) {
		global $db;
		$user = static::get_session_user();
		$error = false;
		/* Confirm "old" password is correct */
		if (user_password_hash($username,$data['old'])!==$user->get_hashed_password()) {
			layout::set_message('Unable to confirm current password.','error');
			$error = true;
		}
		
		/* Confirm new password is...confirmed... */
		if ($data['new']!==$data['confirm']) {
			layout::set_message('Unable to confirm new password.','error');
			$error = true;
		}
		if ($error) {
			return;
		}
		
		/* Update Password */
		$query = "UPDATE _USERS SET PASSWORD = ? WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => user_password_hash($username,$data['new'])),
			array("type" => "s", "value" => $username)
		);
		$db->run_query($query,$params);
		$user->refresh_hashed_password(user_password_hash($username,$data['new']));
		
		layout::set_message("Password has been updated.","info");
		header("Location: " . static::get_module_url() . "$username/edit");
		exit();
		return;
	}
	
	public static function create_user($data) {
		/* Attempts to create a user based on $data array passed in...*/
		global $local,$db;
		
		$data = array_change_key_case($data);
		if (empty($data['username'])) return 'Username is a required field';
		elseif (empty($data['password']) && empty($data['email'])) return 'Password and/or email must be submitted.';
		elseif (empty($data['password'])) $data['password'] = create_random_string(8);
		
		/* Check for username uniqueness */
		$query = "SELECT CASE COUNT(*) WHEN 0 THEN 1 ELSE 0 END AS IS_UNIQUE
		FROM _USERS
		WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $data['username'])
		);
		$is_unique = $db->run_query($query,$params)[0]['IS_UNIQUE'];
		if (!$is_unique) return 'Username is not available.';
		
		if (!array_key_exists('email',$data)) $data['email'] = null;
		if (!array_key_exists('display',$data)) $data['display'] = null;
		
		$query = "INSERT INTO _USERS (USERNAME,PASSWORD,EMAIL,DISPLAY_NAME)
		VALUES (?,?,?,?)";
		$params = array(
			array("type" => "s", "value" => $data['username']),
			array("type" => "s", "value" => user_password_hash($data['username'],$data['password'])),
			array("type" => "s", "value" => $data['email']),
			array("type" => "s", "value" => $data['display']),
		);
		$db->run_query($query,$params);
		$userID = $db->get_inserted_id();
		/* Add user to any groups present in $data['groups'] */
		$data['groups'][] = 'Registered User';
		$query = "INSERT INTO _USERS_GROUPS (USER_ID,GROUP_ID)
			SELECT ?, ID
			FROM _GROUPS G
			WHERE G.NAME IN (";
		$params = array(
			array("type" => "i", "value" => $userID)
		);
		foreach($data['groups'] as $group) {
			array_push($params, array("type" => "s", "value" => $group));
			$p[] = "?";
		}
		$query.= implode(",",$p) . ")";
		$db->run_query($query,$params);
		
		/* Send email confirming username set (if email present).. */
		if (!empty($data['email'])) {
			$emailParams = array(
				"to" => $data['email'],
				"subject" => "User Account creation"
			);
			if (empty($data['display'])) $data['display'] = $data['username'];
			$emailParams['message'] = "{$data['display']},

An account has been created for you at $local.  You may log in with the following credentials:

Username: {$data['username']}
Password: {$data['password']}

Please store this information in a secure location.  Your password cannot be retrieved if you lose it.

Regards,

{$local}";
			send_email($emailParams);
		}
		return true;
	}
	
	public static function required_rights() { 
		global $db;
		$rights = array(
			'Users' => array(
				'Widget' => array(
					'Login' => 'Allows the user to login.',
					'Welcome' => 'Displays a welcome message to the user.'
				),
				'Action' => array(
					'Register Self' => 'Allows a user to register themselves.',
					'Register Others' => 'Allows a user to register others.'
				),
				'Rights' => array(
					'Create Rights' => 'Allows a user to create user rights.',
					'Assign Rights' => 'Allows a user to assign user rights.'
				),
				'View' => array(
					'View Users' => 'Allows a user to view User module, as well as individual user information.',
					'View Users\' Email' => 'Allows a user to view another user\'s email address.'
				)
			));

		/* For each group add Users/Groups/View <Group Name>... */
		$query = "SELECT NAME FROM _GROUPS";
		$groups = group_numeric_by_key($db->run_query($query),'NAME');
		foreach($groups as $group)
			$rights['Users']['Groups']["View $group"] = "Allows a user to view the $group group.";
		return $rights;
	}
	
	public static function install() {
		/* Installs the User module */
		global $db;
		/* Create the necessary tables... */
		$query = array();
		$query[] = "CREATE TABLE IF NOT EXISTS _USERS (
			ID int AUTO_INCREMENT,
			USERNAME varchar(20),
			PASSWORD char(64),
			EMAIL varchar(255),
			DISPLAY_NAME varchar(100),
			REGISTER_DATE datetime,
			PRIMARY KEY (ID),
			UNIQUE (USERNAME),
			UNIQUE(EMAIL)
		);";
		$query[] = "CREATE TABLE IF NOT EXISTS _GROUPS (
			ID int AUTO_INCREMENT,
			NAME varchar(100),
			DESCRIPTION varchar(100),
			PRIMARY KEY (ID),
			UNIQUE (NAME)
		);";
		$query[] = "INSERT INTO _GROUPS (NAME, DESCRIPTION)
		SELECT tmp.NAME, tmp.DESCRIPTION
		FROM (
			SELECT 'Admin' as NAME, 'This group will have be able to access the entire site.' as DESCRIPTION 
			UNION 
			SELECT 'Guest' as NAME, 'Users who are not signed in' as DESCRIPTION
			UNION
			SELECT 'Registered User' as NAME, 'Users who are signed in' as DESCRIPTION
		) tmp
		LEFT JOIN _GROUPS G ON tmp.NAME = G.NAME
		WHERE G.ID IS NULL;";
		$query[] = "CREATE TABLE IF NOT EXISTS _USERS_GROUPS (
			USER_ID int,
			GROUP_ID int,
			PRIMARY KEY (USER_ID,GROUP_ID),
			FOREIGN KEY (USER_ID) REFERENCES _USERS(ID),
			FOREIGN KEY (GROUP_ID) REFERENCES _GROUPS(ID)
		);";
		$query[] = "CREATE TABLE IF NOT EXISTS _RIGHT_TYPES (
			ID int AUTO_INCREMENT,
			MODULE_ID int,
			NAME varchar(50),
			PRIMARY KEY (ID),
			FOREIGN KEY (MODULE_ID) REFERENCES _MODULES(ID)
		);";
		$query[] = "CREATE TABLE IF NOT EXISTS _RIGHTS (
			ID int AUTO_INCREMENT,
			RIGHT_TYPE_ID int,
			NAME varchar(100),
			DESCRIPTION varchar(255),
			PRIMARY KEY (ID),
			FOREIGN KEY (RIGHT_TYPE_ID) REFERENCES _RIGHT_TYPES(ID)
		)";
		$query[] = "CREATE TABLE IF NOT EXISTS _GROUPS_RIGHTS (
			GROUP_ID int,
			RIGHT_ID int,
			PRIMARY KEY (GROUP_ID, RIGHT_ID),
			FOREIGN KEY (GROUP_ID) REFERENCES _GROUPS(ID),
			FOREIGN KEY (RIGHT_ID) REFERENCES _RIGHTS(ID)
		);";
		foreach($query as $q) $db->run_query($q); 
		$db->trigger('register_time','BEFORE INSERT','_USERS','SET NEW.REGISTER_DATE = IFNULL(NEW.REGISTER_DATE,NOW())');
		
		/* Create the actual module record... */
		$query = "INSERT INTO _MODULES (NAME, DESCRIPTION, IS_CORE, FILENAME, CLASS_NAME)
			SELECT tmp.NAME, tmp.DESCRIPTION, tmp.IS_CORE, ?, tmp.CLASS_NAME
			FROM (SELECT 'Users' as NAME,'Handles users, groups and rights' as DESCRIPTION,1 as IS_CORE,'users' as CLASS_NAME) tmp
			LEFT JOIN _MODULES M ON tmp.NAME = M.NAME
			WHERE M.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => __FILE__)
		);
		$db->run_query($query,$params);
		/* Add to the _WIDGETS table RIGHT_ID (if necessary)*/
		$query = "SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS COLUMN_EXISTS
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name()),
			array("type" => "s", "value" => '_WIDGETS'),
			array("type" => "s", "value" => "RIGHT_ID")
		);
		$column_exists = $db->run_query($query,$params)[0]['COLUMN_EXISTS'];
		if (!$column_exists) {
			$query = "ALTER TABLE _WIDGETS
			ADD RIGHT_ID int,
			ADD FOREIGN KEY (RIGHT_ID) REFERENCES _RIGHTS(ID)";
			$db->run_query($query);
		}
		
		/* Create the Right Type and rights we will need... */
		$query = "INSERT INTO _RIGHT_TYPES (MODULE_ID, NAME)
		SELECT M.ID, 'Widget'
		FROM _MODULES M 
		LEFT JOIN _RIGHT_TYPES RT ON 
			RT.MODULE_ID = M.ID AND
			RT.NAME = 'Widget'
		WHERE M.NAME = ? AND RT.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Users")
		);
		$db->run_query($query,$params);
		$query = "";

		$query = "INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
		SELECT RT.ID, tmp.NAME, tmp.DESCRIPTION
		FROM _MODULES M
		JOIN _RIGHT_TYPES RT ON M.ID = RT.MODULE_ID
		JOIN (SELECT 'Login' as NAME, 'Allows the user to login.' as DESCRIPTION UNION
		SELECT 'Welcome' as NAME, 'Displays a welcome message to the user.' as DESCRIPTION) tmp ON 1=1
		LEFT JOIN _RIGHTS R ON 
			RT.ID = R.RIGHT_TYPE_ID AND
			tmp.NAME = R.NAME
		WHERE M.NAME = ? AND R.ID IS NULL";
		$db->run_query($query,$params);
		
		/* Insert the widget group rights... */
		$query = "INSERT INTO _GROUPS_RIGHTS (GROUP_ID, RIGHT_ID)
		SELECT G.ID, R.ID
		FROM _GROUPS G 
		JOIN _MODULES M ON M.NAME = ?
		JOIN _RIGHT_TYPES RT ON M.ID = RT.MODULE_ID AND RT.NAME = ?
		JOIN _RIGHTS R ON RT.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
		LEFT JOIN _GROUPS_RIGHTS GR ON 
			G.ID = GR.GROUP_ID AND
			R.ID = GR.RIGHT_ID
		WHERE G.NAME = ? AND GR.GROUP_ID IS NULL
		UNION
		SELECT G.ID, R.ID
		FROM _GROUPS G 
		JOIN _MODULES M ON M.NAME = ?
		JOIN _RIGHT_TYPES RT ON M.ID = RT.MODULE_ID AND RT.NAME = ?
		JOIN _RIGHTS R ON RT.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
		LEFT JOIN _GROUPS_RIGHTS GR ON 
			G.ID = GR.GROUP_ID AND
			R.ID = GR.RIGHT_ID
		WHERE G.NAME IN (?,?) AND GR.GROUP_ID IS NULL";
		$params = array(
			array("type" => "s", "value" => 'Users'),
			array("type" => "s", "value" => 'Widget'),
			array("type" => "s", "value" => 'Login'),
			array("type" => "s", "value" => 'Guest'),
			array("type" => "s", "value" => 'Users'),
			array("type" => "s", "value" => 'Widget'),
			array("type" => "s", "value" => 'Welcome'),
			array("type" => "s", "value" => 'Admin'),
			array("type" => "s", "value" => 'Registered User')
		);
		$db->run_query($query,$params);

		/* Create the widget records... */
		require_once('Login.Widget.php');
		login_widget::install();
		require_once('Welcome.Widget.php');
		welcome_widget::install();
		
/*		
		$query = "INSERT INTO _WIDGETS (MODULE_ID, NAME, FILENAME, CLASS_NAME, RIGHT_ID)
		SELECT M.ID, F.NAME, CONCAT(?,'/',F.filename), F.CLASS_NAME, R.ID
		FROM _MODULES M 
		JOIN (
			SELECT 'Login' as NAME, 'Login.Widget.php' as FILENAME, 'login_widget' as CLASS_NAME UNION
			SELECT 'Welcome' as NAME, 'Welcome.Widget.php' as FILENAME, 'welcome_widget' as CLASS_NAME) F ON 1=1
		JOIN _RIGHT_TYPES RT ON
			M.ID = RT.MODULE_ID AND
			RT.NAME = ?
		JOIN _RIGHTS R ON
			RT.ID = R.RIGHT_TYPE_ID AND
			F.NAME = R.NAME
		LEFT JOIN _WIDGETS W ON 
			M.ID = W.MODULE_ID AND
			F.NAME = W.NAME
		WHERE M.NAME = 'Users' AND W.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => __DIR__),
			array("type" => "s", "value" => "Widget")
		);
		$db->run_query($query,$params);
*/		
		/* Create an action right type */
		
		$query = "INSERT INTO _RIGHT_TYPES (MODULE_ID, NAME)
		SELECT M.ID, ?
		FROM _MODULES M
		LEFT JOIN _RIGHT_TYPES RT ON 
			M.ID = RT.MODULE_ID AND
			RT.NAME = ?
		WHERE M.NAME = ? AND RT.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Action"),
			array("type" => "s", "value" => "Action"),
			array("type" => "s", "value" => "Users")
		);
		$db->run_query($query,$params);
		$rt = $db->get_inserted_id();
		
		/* Now insert the rights for all of our actions... */
		$query = "INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
		SELECT tmp.RIGHT_TYPE_ID, tmp.NAME, tmp.DESCRIPTION
		FROM (
			SELECT *
			FROM (SELECT $rt AS RIGHT_TYPE_ID) A
			JOIN (SELECT 'Register Self' as NAME, 'Allows a user to register themselves.' as DESCRIPTION
			UNION SELECT 'Register Others' as NAME, 'Allows a user to register others.' as DESCRIPTION) B ON 1=1
		) tmp
		LEFT JOIN _RIGHTS R ON
			R.RIGHT_TYPE_ID = tmp.RIGHT_TYPE_ID AND
			R.NAME = tmp.NAME
		WHERE R.ID IS NULL";
		$db->run_query($query);
		/* Give the 'Register Self' right to Guests, 'Register Others' right to Admins... */
		
		$query = "INSERT INTO _GROUPS_RIGHTS (GROUP_ID, RIGHT_ID)
		SELECT G.ID, R.ID
		FROM _GROUPS G
		JOIN _RIGHTS R ON
			R.RIGHT_TYPE_ID = ? AND
			R.NAME = CASE G.NAME WHEN 'Guest' THEN ? WHEN 'Admin' THEN ? END
		LEFT JOIN _GROUPS_RIGHTS GR ON
			GR.GROUP_ID = G.ID AND
			GR.RIGHT_ID = R.ID
		WHERE GR.GROUP_ID IS NULL";
		$params = array(
			array("type" => "i", "value" => $rt),
			array("type" => "s", "value" => 'Register Self'),
			array("type" => "s", "value" => 'Register Others')
		);
		$db->run_query($query,$params);
		
		/* Create the "Rights" Right Type */
		$query = "
			INSERT INTO _RIGHT_TYPES (MODULE_ID,NAME)
			SELECT M.ID, ?
			FROM _MODULES M 
			LEFT JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			WHERE M.NAME = ? AND T.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Rights"),
			array("type" => "s", "value" => "Rights"),
			array("type" => "s", "value" => "Users"),
		);
		$db->run_query($query,$params);
		
		/* Create the "Create" and "Assign" Rights */
		$query = "
			INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
			SELECT T.ID, tmp.NAME, tmp.DESCRIPTION
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			JOIN (
				SELECT 'Create Rights' as NAME, 'Allows a user to create user rights.' as DESCRIPTION UNION
				SELECT 'Assign Rights' as NAME, 'Allows a user to assign user rights.' as DESCRIPTION
			) tmp ON 1=1
			LEFT JOIN _RIGHTS R ON
				R.RIGHT_TYPE_ID = T.ID AND
				R.NAME = tmp.NAME
			WHERE 
				M.NAME = ? AND
				T.NAME = ? AND
				R.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "Rights")
		);
		$db->run_query($query,$params);

		/* Grant the User Rights Rights to Admin Group */
		$query = "
			INSERT INTO _GROUPS_RIGHTS (GROUP_ID, RIGHT_ID)
			SELECT G.ID, R.ID
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID
			JOIN _GROUPS G ON G.NAME = ?
			LEFT JOIN _GROUPS_RIGHTS GR ON 
				G.ID = GR.GROUP_ID AND
				R.ID = GR.RIGHT_ID
			WHERE 
				M.NAME = ? AND
				T.NAME = ? AND
				GR.GROUP_ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Admin"),
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "Rights")
		);
		$db->run_query($query,$params);
		
		/* Create the View Right Type */
		$query = "
			INSERT INTO _RIGHT_TYPES (MODULE_ID,NAME)
			SELECT M.ID, ?
			FROM _MODULES M
			LEFT JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			WHERE M.NAME = ? AND T.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "View"),
			array("type" => "s", "value" => "View"),
			array("type" => "s", "value" => "Users")
		);
		$db->run_query($query,$params);
		
		/* Create the Users/View/User Right */
		$query = "
			INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
			SELECT T.ID, 'View Users','Allows User to view registered users.'
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			LEFT JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
			WHERE M.NAME = ? AND T.NAME = ? AND R.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "View Users"),
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "View")
		);
		$db->run_query($query,$params);
		
		/* Assign Users/View/User Right to Admins and Registered Guests */
		$query = "
			INSERT INTO _GROUPS_RIGHTS (GROUP_ID,RIGHT_ID)
			SELECT G.ID, R.ID
			FROM _GROUPS G
			JOIN _MODULES M ON M.NAME = ?
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
			WHERE G.NAME IN ('Admin','Registered User')";
		$params = array(
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "View"),
			array("type" => "s", "value" => "View Users")
		);
		$db->run_query($query,$params);
		
		/* Create the Users/View/Users' Email Right */
		$query = "
			INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
			SELECT T.ID, 'View Users\' Email','Allows User to view registered users\' email.'
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			LEFT JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
			WHERE M.NAME = ? AND T.NAME = ? AND R.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "View Users' Email"),
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "View")
		);
		$db->run_query($query,$params);
		
		/* Assign Users/View/Users' Email Right to Admins */
		$query = "
			INSERT INTO _GROUPS_RIGHTS (GROUP_ID,RIGHT_ID)
			SELECT G.ID, R.ID
			FROM _GROUPS G
			JOIN _MODULES M ON M.NAME = ?
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
			WHERE G.NAME IN ('Admin')";
		$params = array(
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "View"),
			array("type" => "s", "value" => "View Users' Email")
		);
		$db->run_query($query,$params);
		
		/* Create the Groups Right Type */
		$query = "
			INSERT INTO _RIGHT_TYPES (MODULE_ID,NAME)
			SELECT M.ID, ?
			FROM _MODULES M
			LEFT JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			WHERE M.NAME = ? AND T.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Groups"),
			array("type" => "s", "value" => "Groups"),
			array("type" => "s", "value" => "Users")
		);
		$db->run_query($query,$params);
		
		/* Create the Users/Groups/View <Group> Right for each group */
		$query = "
			INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION)
			SELECT T.ID, CONCAT('View ',G.NAME),CONCAT('Allows User to view the ',G.NAME,' group.')
			FROM _MODULES M
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
			JOIN _GROUPS G ON 1=1
			LEFT JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = CONCAT('View ',G.NAME)
			WHERE M.NAME = ? AND T.NAME = ? AND R.ID IS NULL";
		$params = array(
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "Groups")
		);
		$db->run_query($query,$params);
		
		/* Assign Users/Groups/View Admin to All, /View Registered User to Admins and Registered Users */
		$query = "
			INSERT INTO _GROUPS_RIGHTS (GROUP_ID,RIGHT_ID)
			SELECT G.ID, R.ID
			FROM _GROUPS G
			JOIN _MODULES M ON M.NAME = ?
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = ?";
		$params = array(
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "Groups"),
			array("type" => "s", "value" => "View Admin")
		);
		$db->run_query($query,$params);
		
		$query = "
			INSERT INTO _GROUPS_RIGHTS (GROUP_ID,RIGHT_ID)
			SELECT G.ID, R.ID
			FROM _GROUPS G
			JOIN _MODULES M ON M.NAME = ?
			JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID AND T.NAME = ?
			JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID AND R.NAME = ?
			WHERE G.NAME IN (?,?)";
		$params = array(
			array("type" => "s", "value" => "Users"),
			array("type" => "s", "value" => "Groups"),
			array("type" => "s", "value" => "View Registered User"),
			array("type" => "s", "value" => "Admin"),
			array("type" => "s", "value" => "Registered User"),
		);
		$db->run_query($query,$params);
		
		return true;
	}
	 
	 public static function get_right_id($module, $type, $name) {
		 global $db;
		 $query = "SELECT R.ID
		 FROM _MODULES M
		 JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
		 JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID
		 WHERE M.NAME = ? AND T.NAME = ? AND R.NAME = ?";
		 $params = array(
			array("type" => "s", "value" => $module),
			array("type" => "s", "value" => $type),
			array("type" => "s", "value" => $name)
		 );
		 $right = $db->run_query($query,$params);
		 if (empty($right)) return false;
		 else return $right[0]['ID'];
	 }
	
	public static function create_right($module,$type,$name,$description) {
		global $db;
		if (empty($module) || empty($type) || empty($name)) return false;
		$u = users::get_session_user();
		if (!$u->check_right('Users','Rights','Create Rights')) return false;
		/* First check if $type exists... */
		$query = "
			SELECT T.ID as type_id
			FROM _RIGHT_TYPES T
			JOIN _MODULES M ON T.MODULE_ID = M.ID
			WHERE M.NAME = ? AND T.NAME = ?";
		$params = array(
			array("type" => "s", "value" => $module),
			array("type" => "s", "value" => $type),
		);
		$result = $db->run_query($query,$params);
		
		if (empty($result)) {
			/* Create new type... */
			$query = "
				INSERT INTO _RIGHT_TYPES (MODULE_ID, NAME)
				SELECT ID, ?
				FROM _MODULES
				WHERE NAME = ?";
			$params = array(
				array("type" => "s", "value" => $type),
				array("type" => "s", "value" => $module),
			);
			$db->run_query($query,$params);
			$type_id = $db->get_inserted_id();
		} else $type_id = $result[0]['type_id'];
		
		/* Create the right... */
		$query = "INSERT INTO _RIGHTS (RIGHT_TYPE_ID, NAME, DESCRIPTION) VALUES ( ?, ?, ? )";
		$params = array(
			array("type" => "i", "value" => $type_id),
			array("type" => "s", "value" => $name),
			array("type" => "s", "value" => $description)
		);
		$db->run_query($query,$params);
		return $db->get_inserted_id();
	}
	
	public static function assign_rights($rights) {
		global $local, $db;
		$user = static::get_session_user();
		if (!$user->check_right('Users','Rights','Assign Rights')) return false;
		$query = "INSERT INTO _GROUPS_RIGHTS (GROUP_ID, RIGHT_ID) VALUES (?,?)";
		$errors = array();
		foreach($rights as $right_id=>$groups) {
			if (empty($groups)) continue;
			$params = array(
				array("type" => "i", "value" => 0),
				array("type" => "i", "value" => $right_id)
			);
			foreach($groups as $group) {
				$params[0]['value'] = $group;
				$db->run_query($query,$params);
			}
		}
		$user->reload_rights();
	}
	
	public static function admin_post($args,$request) {
		list($type,$id) = $args;
		switch($type) {
			case 'Users': return static::admin_users_post($id,$request);
			case 'Groups': return static::admin_groups_post($id,$request);
		}		
		return false;
	}
	
	public static function post($args) {
		$action = array_shift($args);
		switch($action) {
			case 'login' : return static::login_validate();
			case 'register' : return static::register_submit($_POST);
		}
		/* Assume to be User activity... */
		$user = static::get_session_user();
		if (!$user->is_current_user($action))
			die("Unknown POST action: $action");
		$user = $action;
		$action = array_shift($args);
		switch($action) {
			case 'edit':
				return static::update_user($user,$_POST);
			case 'password':
				return static::update_password($user,$_POST);
		}
	}
	private static function admin_users_post($id,$request) {
		global $db;
		$query = "
			UPDATE _USERS SET
				EMAIL = ?,
				DISPLAY_NAME = ?
			WHERE ID = ?";
		$params = array(
			array("type" => "s", "value" => $request['email']),
			array("type" => "s", "value" => empty($request['display']) ? null : $request['display']),
			array("type" => "i", "value" => $id)
		);
		$db->run_query($query,$params);
		
		/* Set Groups for user...*/
		/* First remove groups which shouldn't be there... */
		if (!empty($request['groups'])) {
			$query = "
				DELETE UG
				FROM _USERS_GROUPS UG
				JOIN _GROUPS G ON UG.GROUP_ID = G.ID
				WHERE UG.USER_ID = ? AND G.NAME NOT IN (".substr(str_repeat("?,",count($request['groups'])),0,-1).")";
			$params = array(
				array("type" => "i", "value" => $id)
			);
			foreach($request['groups'] as $group)
				array_push($params,array("type" => "s", "value" => $group));
			$db->run_query($query,$params);
			/* Now add any new groups... */
			$query = "
				INSERT INTO _USERS_GROUPS (USER_ID,GROUP_ID)
				SELECT ?,G.ID
				FROM _GROUPS G
				LEFT JOIN _USERS_GROUPS UG ON 
					UG.USER_ID = ? AND
					UG.GROUP_ID = G.ID
				WHERE G.NAME IN (".substr(str_repeat("?,",count($request['groups'])),0,-1).") AND UG.USER_ID IS NULL";
			$params = array(
				array("type" => "i", "value" => $id),
				array("type" => "i", "value" => $id)
			);
			foreach($request['groups'] as $group)
				array_push($params,array("type" => "s", "value" => $group));
			$db->run_query($query,$params);
		} else {
			$query = "DELETE FROM _USERS_GROUPS WHERE USER_ID = ?";
			$params = array(
				array("type" => "i", "value" => $id)
			);
			$db->run_query($query,$params);
		}
	}
	
	private static function admin_users($id) {
		global $db,$local;
		$s_user = static::get_session_user();
		$output = array(
			'html' => '<h3>Users Administration</h3>',
			'script' => array(),
			'css' => array()
		);
		if (empty($id)) {
			/* Show All Users, their Emails (if available), Groups, and Join Date */
			$query = "
				SELECT U.ID, U.USERNAME, U.EMAIL, GROUP_CONCAT(CONCAT(CAST(G.ID as CHAR),'=',G.NAME)) as GROUPS, U.REGISTER_DATE
				FROM _USERS U
				JOIN _USERS_GROUPS UG ON U.ID = UG.USER_ID
				JOIN _GROUPS G ON UG.GROUP_ID = G.ID
				GROUP BY U.ID, U.USERNAME, U.EMAIL, U.REGISTER_DATE
				ORDER BY U.ID";
			$users = make_html_safe($db->run_query($query),ENT_QUOTES);
			$output['html'] .= "
			<table>
				<thead>
					<tr>
						<th>User</th>".
						($s_user->check_right('Users','View','View Users\' Email') ? '<th>Email</th>' : '') .
						"<th>Groups</th>
						<th>Register Date</th>
					</tr>
				</thead>
				<tbody>";
			foreach($users as $user) {
				$groups = explode(",",$user['GROUPS']);
				foreach($groups as &$group) {
					preg_match('/^(?<ID>\d+)=(?<NAME>.*)$/',$group,$group_info);
					$group = "<a href='".modules::get_module_url()."Users/Groups/{$group_info['ID']}'>{$group_info['NAME']}</a>";
				}
				$user['GROUPS'] = implode(",",$groups);
				$output['html'] .= "
					<tr>
						<td><a href='".modules::get_module_url()."Users/Users/{$user['ID']}'>{$user['USERNAME']}</a></td>".
						($s_user->check_right('Users','View','View Users\' Email') ? "<td><a href='mailto:{$user['EMAIL']}'>{$user['EMAIL']}</a></td>" : '') .
						"<td>{$user['GROUPS']}</td>
						<td>".preg_replace('/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/','$2/$3/$1 $4:$5',$user['REGISTER_DATE'])."</td>
					</tr>";
			}
			$output['html'].="				
				</tbody>
			</table>
			<p><a href='".modules::get_module_url()."Users'>Back</a></p>";
		} else {
			/* Get User Information... */
			$query = "
				SELECT ID,USERNAME,EMAIL,DISPLAY_NAME,REGISTER_DATE
				FROM _USERS
				WHERE ID = ?";
			$params = array(
				array("type" => "i", "value" => $id)
			);
			$result = $db->run_query($query,$params);
			if (empty($result)) {
				header("Location: " . modules::get_module_url() . "Users/Users");
				exit();
				return;
			}
			$user_info = make_html_safe($result[0],ENT_QUOTES);
			/* Get User's Groups... */
			$query = "SELECT NAME FROM _USERS_GROUPS UG JOIN _GROUPS G ON UG.GROUP_ID = G.ID WHERE USER_ID = ?";
			$params = array(
				array("type" => "i", "value" => $id)
			);
			$user_groups = group_numeric_by_key($db->run_query($query,$params),'NAME');
			
			/* Get All Groups... */
			$query = "SELECT NAME FROM _GROUPS";
			$groups = group_numeric_by_key($db->run_query($query),'NAME');
			
			$output['html'] .= "
				<h4>{$user_info['USERNAME']} (Member since ".preg_replace('/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/','$2/$3/$1',$user_info['REGISTER_DATE']).")</h4>
				<form method='post' action=''>
					<div>
						<label for='email'>Email Address:</label>
						<input id='email' name='email' value='{$user_info['EMAIL']}' />
					</div>
					<div>
						<label for='display'>Display Name:</label>
						<input id='display' name='display' value='{$user_info['DISPLAY_NAME']}' />
					</div>
					<p>
						<button type='button' id='groups' >Set Groups</button>
					</p>
					<p>
						<input type='submit' value='Save User' />
					</p>";
			foreach($user_groups as $ug) 
				$output['html'] .= "<input type='hidden' value='$ug' name='groups[]' />";
			$output['html'] .= "
				</form>
				<p><a href='".modules::get_module_url()."Users/Users'>Return to User Administration</a></p>";
			array_push(
				$output['script'],
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				get_public_location(__DIR__ . '/js/user-groups.js'),
				'var groups = '.json_encode($groups,true).';'
			);
			array_push(
				$output['css'],
				"{$local}style/jquery-ui.css",
				get_public_location(__DIR__ . '/style/user-groups.css')
			);
		}
		return $output;
	}
	
	private static function admin_groups_post($id,$request) {
		global $db,$s_user;
		$query = "
			UPDATE _GROUPS SET
				NAME = ?,
				DESCRIPTION = ?
			WHERE ID = ?";
		$params = array(
			array("type" => "s", "value" => $request['name']),
			array("type" => "s", "value" => $request['description']),
			array("type" => "i", "value" => $id),
		);
		$db->run_query($query,$params);
		if ($s_user->check_right('Users','Rights','Assign Rights')) {
			if (!empty($request['rights'])) {
				/* Remove all rights not in $request['rights'] */
				$query = "
					DELETE 
					FROM _GROUPS_RIGHTS
					WHERE GROUP_ID = ? AND RIGHT_ID NOT IN (".substr(str_repeat("?,",count($request['rights'])),0,-1).")";
				$params = array(
					array("type" => "i", "value" => $id)
				);
				foreach($request['rights'] as $right)
					array_push($params,array("type" => "i", "value" => $right));
				$db->run_query($query,$params);
				
				/* Add any new rights from $request['rights']... */
				$query = "
					INSERT INTO _GROUPS_RIGHTS(GROUP_ID,RIGHT_ID)
					SELECT ?,R.ID
					FROM _RIGHTS R
					LEFT JOIN _GROUPS_RIGHTS GR ON R.ID = GR.RIGHT_ID AND GR.GROUP_ID = ?
					WHERE R.ID IN (".substr(str_repeat("?,",count($request['rights'])),0,-1).") AND GR.RIGHT_ID IS NULL";
				$params = array(
					array("type" => "i", "value" => $id),
					array("type" => "i", "value" => $id)
				);
				foreach($request['rights'] as $right)
					array_push($params,array("type" => "i", "value" => $right));
				$db->run_query($query,$params);
			} else {
				/* Remove all rights for this group... */
				$query = "DELETE FROM _GROUPS_RIGHTS WHERE GROUP_ID = ?";
				$params = array(
					array("type" => "i", "value" => $id)
				);
				$db->run_query($query,$params);
			}
		}
		layout::set_message("Group {$request['name']} has been updated.",'info');
		return;
	}
	
	private static function admin_groups($id) {
		global $db,$local;
		$s_user = users::get_session_user();
		$output = array(
			'html' => '<h3>Groups Administration</h3>',
			'css' => array(),
			'scripts' => array()
		);
		if (empty($id)) {
			/* Just a list of groups, along with a count of members... */
			$query = "
				SELECT ID,NAME,COUNT(DISTINCT UG.USER_ID) membership
				FROM _GROUPS G
				LEFT JOIN _USERS_GROUPS UG ON G.ID = UG.GROUP_ID
				GROUP BY G.ID,G.NAME";
			$groups = make_html_safe($db->run_query($query));
			$output['html'] .= "
			<table>
				<thead>
					<tr>
						<th>Group</th>
						<th># Members</th>
					</tr>
				</thead>
				<tbody>";
			foreach($groups as $group) {
				$output['html'] .= "
					<tr>
						<td><a href='".modules::get_module_url()."Users/Groups/{$group['ID']}'>{$group['NAME']}</a></td>
						<td>{$group['membership']}</td>
					</tr>";
			}					
			$output['html'] .= "					
				</tbody>
			</table>
			<p><a href='".modules::get_module_url()."Users'>Back</a></p>";
		} else {
			/* Add/Edit... */
			if ($id=='new') {
				$group = array('NAME' => '', 'DESCRIPTION' => '');
				$action = "Create New Group";
			} else {
				$query = "SELECT NAME,DESCRIPTION FROM _GROUPS WHERE ID = ?";
				$params = array(
					array("type" => "i", "value" => $id)
				);
				$result = $db->run_query($query,$params);
				if (empty($result)) {
					header("Location: ".get_module_url() . "Users/Groups");
					exit();
					return;
				}
				$group = make_html_safe($result[0]);
				$action = "Edit {$group['NAME']}";
			}
			/* Will Need EVERY right as well (if user has assign rights right)... */
			$rights_html = "";
			if ($s_user->check_right('Users','Rights','Assign Rights')) {
				$rights_html = "
					<h5>Group Rights</h5>
					<table>
						<thead>
							<tr>
								<th>Module</th>
								<th>Right Type</th>
								<th>Right Name</th>
								<th>Description</th>
								<th></th>
							</tr>
						</thead>
						<tbody>";
				$query = "
					SELECT M.NAME as MODULE,T.NAME as TYPE,R.ID,R.NAME,R.DESCRIPTION, CASE WHEN GR.RIGHT_ID IS NULL THEN 0 ELSE 1 END as has_right
					FROM _MODULES M
					JOIN _RIGHT_TYPES T ON M.ID = T.MODULE_ID
					JOIN _RIGHTS R ON T.ID = R.RIGHT_TYPE_ID
					LEFT JOIN _GROUPS_RIGHTS GR ON R.ID = GR.RIGHT_ID AND GR.GROUP_ID = ?
					ORDER BY MODULE,TYPE,has_right DESC
					";
				$params = array(
					array("type" => "i", "value" => $id)
				);
				$rights = $db->run_query($query,$params);
				foreach($rights as $right) {
					$checked = $right['has_right'] ? 'checked="checked"' : '';
					$rights_html .= "
							<tr>
								<td>{$right['MODULE']}</td>
								<td>{$right['TYPE']}</td>
								<td>{$right['NAME']}</td>
								<td>{$right['DESCRIPTION']}</td>
								<td><input value='{$right['ID']}' type='checkbox' name='rights[]' $checked/></td>
							</tr>";
				}
				$rights_html .= "
						</tbody>
					</table>
					<p><input type='submit' value='Save Group' /></p>";
				
				
			}
			
			$output['html'] .= "
				<h4>$action</h4>
				<form method='post' action=''>
					<div>
						<label for='name'>Group Name:</label>
						<input id='name' name='name' value='{$group['NAME']}' />
					</div>
					<div>
						<label for='description'>Group Description:</label>
						<textarea rows='5' id='description' name='description'>{$group['DESCRIPTION']}</textarea>
					</div>
					<p><input type='submit' value='Save Group' /></p>
					$rights_html
				</form>
				<p><a href='".modules::get_module_url()."Users/Groups'>Return to Groups Administration</a></p>";
		}
		
		
		return $output;
	}
	
	public static function admin($type='',$id='') {
		global $db,$local;
		switch ($type) {
			case 'Users' : return static::admin_users($id);
			case 'Groups' : return static::admin_groups($id);
			case '': break;
			default: 
				header("Location: " . modules::get_module_url() . "Users/");
				exit();
				return;
		}
		
		$output = array(
			'html' => '<h3>User/Group Administration</h3>'
		);
		$output['html'] .= "<p>Please select an area to administer:</p>";
		$output['html'] .= "
			<ul>
				<li><a href='".modules::get_module_url()."Users/Users'>View Users</a></li>
				<li><a href='".modules::get_module_url()."Users/Groups'>View Groups</a></li>
			</ul>";
		
		return $output;
	}
	
	public static function view() {
		global $local,$db;
		$output = array('html' => '');
		$args = func_get_args();
		$action = array_shift($args);
		switch($action) {
			case 'login' : return login_widget::login();
			case 'logout' : return static::logout();
			case 'register': return static::register();
			case '': return static::view_all_users();
			case 'groups':
				if (!empty($args[0])) return static::view_group($args[0]);
		}
		
		/* $action must be a user..confirm this user actually exists... */
		$query = "SELECT USERNAME 
		FROM _USERS U
		WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $action)
		);
		$user = $db->run_query($query,$params);
		if (!empty($user)) {
			$user = $user[0]['USERNAME'];
			$user_action = array_shift($args);
			switch($user_action) {
				case 'edit' : return static::edit_user($user); break;
				case 'password' : return static::edit_password($user); break;
				case '': return static::view_user($user); break;
				default:
					header("Location: {$local}Users/$user/");
					exit();
					return;
			}
		}
		else {
			header("Location: {$local}Users/");
			exit();
			return;
		}
	}
}
?>
