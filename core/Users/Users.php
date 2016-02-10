<?php

class users extends module {
	protected $user_info;
	private $rights;
	public function __construct($user = array('ID'=>0)) {

		$this->user_info = $user;
		$this->reload_rights();
		$this->reload_groups();
		$this->reload_theme();

	}

	public function is_group_member($group) {
		return (!empty($this->user_info['GROUPS'][$group]));
	}

	public function reload_theme() {
		global $db,$s_user;
		if (empty($this->user_info['ID'])) {
			$column = 'UT.SESSION_ID';
			$params = array(
				array("type" => "s", "value" => session_id())
			);
		}
		else {
			$column = 'UT.USER_ID';
			$params = array(
				array("type" => "i", "value" => $this->user_info['ID'])
			);
		}
		$query = "
			SELECT T.ID, T.NAME, M.CLASS_NAME, CASE IFNULL(STYLE,'') WHEN '' THEN 0 ELSE 1 END AS HAS_STYLESHEET
			FROM _THEMES T
			JOIN _MODULES M ON T.MODULE_ID = M.ID
			LEFT JOIN _USERS_THEMES UT ON T.ID = UT.THEME_ID
			WHERE ($column = ? AND UT.THEME_ID IS NOT NULL) OR T.IS_DEFAULT = 1
			ORDER BY T.IS_DEFAULT
			LIMIT 1
		";
		$result = $db->run_query($query,$params);
		if (empty($result)) $this->user_info['THEME'] = null;
		else $this->user_info['THEME'] = $result[0];
	}

	public function get_theme() {return $this->user_info['THEME'];}

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
		$groups = utilities::group_numeric_by_key($db->run_query($query,$params),'NAME');
		if (!empty($groups)) return;
		$query = "SELECT * FROM _GROUPS G WHERE G.NAME = ?";
		$params = array(
			array("type" => "s", "value" => "Guest")
		);
		$groups = utilities::group_numeric_by_key($db->run_query($query,$params),'NAME');
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
		global $s_user;
		if (isset($s_user)) return $s_user;
		elseif (isset($_SESSION['users']['user'])) return $_SESSION['users']['user'];
		else {
			$_SESSION['users']['user'] = new users();
			return $_SESSION['users']['user'];
		}
	}

	public static function current_user_is_guest() {
		global $s_user;
	//	echo "current_user_is_guest() called, s_user = " . var_export($s_user,true) ;
	//	var_export(isset($s_user));
	//	var_export($s_user->user_info['ID']==0);

		if (!isset($s_user)) return true;
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
			$password = utilities::user_password_hash($username,$_POST['password']);
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
				$result = $db->run_query($query,$params);
				$_SESSION[__CLASS__]['user'] = new users($result[0]);
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
			$output['css'][] = utilities::get_public_location(__DIR__ . '/style/user-groups.css');
			$output['script'][] = utilities::get_public_location(__DIR__ . '/js/user-groups.js');
			$query = "SELECT NAME FROM _GROUPS WHERE NAME NOT IN ('Guest','Registered User')";
			$groups = json_encode(utilities::group_numeric_by_key($db->run_query($query),'NAME'));

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
				$group = "<a href='".static::get_module_url()."groups/".utilities::make_url_safe($group)."'>$group</a>";
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

	public static function get_groups() {
		global $db;
		/* Just a list of groups, along with a count of members... */
		$query = "
			SELECT ID,NAME,COUNT(DISTINCT UG.USER_ID) membership
			FROM _GROUPS G
			LEFT JOIN _USERS_GROUPS UG ON G.ID = UG.GROUP_ID
			GROUP BY G.ID, G.NAME
		";
		return $db->run_query($query);
	}

	public static function view_group($group) {
		global $local, $db;
		/* Confirm Real Group... */
		$query = "
			SELECT NAME
			FROM _GROUPS
			WHERE NAME RLIKE ?";
		$params = array(
			array("type" =>"s", "value" => utilities::decode_url_safe($group))
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
			$user_html = utilities::make_html_safe($user['USERNAME']);
			$user_url = utilities::make_url_safe($user['USERNAME']);
			$groups = explode(",",$user['GROUPS']);
			foreach($groups as $idx=>&$group) {
				if (!$s_user->check_right('Users','Groups',"View $group")) {
					unset($groups[$idx]);
					continue;
				}
				$group = "<a href='".static::get_module_url()."groups/".utilities::make_url_safe($group)."'>".utilities::make_html_safe($group)."</a>";
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
		$user_info = utilities::make_html_safe($result[0],ENT_QUOTES);


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
		if (utilities::user_password_hash($username,$data['old'])!==$user->get_hashed_password()) {
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
			array("type" => "s", "value" => utilities::user_password_hash($username,$data['new'])),
			array("type" => "s", "value" => $username)
		);
		$db->run_query($query,$params);
		$user->refresh_hashed_password(utilities::user_password_hash($username,$data['new']));

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
		elseif (empty($data['password'])) $data['password'] = utilities::create_random_string(8);

		/* Check for username uniqueness */
		$query = "SELECT CASE COUNT(*) WHEN 0 THEN 1 ELSE 0 END AS IS_UNIQUE
		FROM _USERS
		WHERE USERNAME = ?";
		$params = array(
			array("type" => "s", "value" => $data['username'])
		);
		$result = $db->run_query($query,$params);
		$is_unique = $result[0]['IS_UNIQUE'];
		if (!$is_unique) return 'Username is not available.';

		if (!array_key_exists('email',$data)) $data['email'] = null;
		if (!array_key_exists('display',$data)) $data['display'] = null;

		$query = "INSERT INTO _USERS (USERNAME,PASSWORD,EMAIL,DISPLAY_NAME)
		VALUES (?,?,?,?)";
		$params = array(
			array("type" => "s", "value" => $data['username']),
			array("type" => "s", "value" => utilities::user_password_hash($data['username'],$data['password'])),
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
			utilities::send_email($emailParams);
		}
		return true;
	}

	public static function required_rights() {
		global $db;
		$rights = array(
			'Users' => array(
				'Widget' => array(
					'Login' => array(
						'description' => 'Allows the user to login.',
						'default_groups' => array('Guest')
					),
					'Welcome' => array(
						'description' => 'Displays a welcome message to the user.',
						'default_groups' => array('Registered User','Admin')
					)
				),
				'Action' => array(
					'Register Self' => array(
						'description' => 'Allows a user to register themselves.',
						'default_groups' => array('Guest')
					),
					'Register Others' => array(
						'description' => 'Allows a user to register others.',
						'default_groups' => array('Admin')
					)
				),
				'Rights' => array(
					'Create Rights' => array(
						'description' => 'Allows a user to create user rights.',
						'default_groups' => array('Admin')
					),
					'Assign Rights' => array(
						'description' => 'Allows a user to assign user rights.',
						'default_groups' => array('Admin')
					)
				),
				'View' => array(
					'View Users' => array(
						'description' => 'Allows a user to view User module, as well as individual user information.',
						'default_groups' => array('Registered User','Admin')
					),
					'View Users\' Email' => array(
						'description' => 'Allows a user to view another user\'s email address.',
						'default_groups' => array('Admin')
					)
				)
			));

		/* For each group add Users/Groups/View <Group Name>... */
		$query = "SELECT NAME FROM _GROUPS";
		$groups = utilities::group_numeric_by_key($db->run_query($query),'NAME');
		foreach($groups as $group)
			$rights['Users']['Groups']["View $group"] = array(
				'description' => "Allows a user to view the $group group.",
				'default_groups' => array('Guest','Registered User','Admin')
			);
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
		$query[] = "CREATE TABLE IF NOT EXISTS _USERS_GROUPS (
			USER_ID int,
			GROUP_ID int,
			PRIMARY KEY (USER_ID,GROUP_ID),
			FOREIGN KEY (USER_ID) REFERENCES _USERS(ID),
			FOREIGN KEY (GROUP_ID) REFERENCES _GROUPS(ID)
		);";
		$query[] = "CREATE TABLE IF NOT EXISTS _GROUPS_RIGHTS (
			GROUP_ID int,
			RIGHT_ID int,
			PRIMARY KEY (GROUP_ID, RIGHT_ID),
			FOREIGN KEY (GROUP_ID) REFERENCES _GROUPS(ID),
			FOREIGN KEY (RIGHT_ID) REFERENCES _RIGHTS(ID)
		);";
		foreach($query as $q) $db->run_query($q);
		$db->trigger('register_time','BEFORE INSERT','_USERS','SET NEW.REGISTER_DATE = IFNULL(NEW.REGISTER_DATE,NOW())');

		/* Add to the _WIDGETS table RIGHT_ID (if necessary)*/
		$query = "SELECT CASE COUNT(*) WHEN 0 THEN 0 ELSE 1 END AS COLUMN_EXISTS
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
		$params = array(
			array("type" => "s", "value" => $db->get_db_name()),
			array("type" => "s", "value" => '_WIDGETS'),
			array("type" => "s", "value" => "RIGHT_ID")
		);
		$result = $db->run_query($query,$params);
		$column_exists = $result[0]['COLUMN_EXISTS'];
		if (!$column_exists) {
			$query = "
				ALTER TABLE _WIDGETS
				ADD RIGHT_ID int,
				ADD FOREIGN KEY (RIGHT_ID) REFERENCES _RIGHTS(ID)";
			$db->run_query($query);
		}

		/* Create the widget records... */
		require_once(__DIR__ . '/Login.Widget.php');
		login_widget::install();
		require_once(__DIR__ . '/Welcome.Widget.php');
		welcome_widget::install();

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

	public static function create_right($module,$type,$name,$description,$super=false) {
		global $db;
		if (empty($module) || empty($type) || empty($name)) return false;
		$u = users::get_session_user();
		if (!$u->check_right('Users','Rights','Create Rights') && !$super) return false;
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

	public static function assign_rights($rights,$super=false) {
		global $local, $db;
		$user = static::get_session_user();
		if (!$user->check_right('Users','Rights','Assign Rights') && !$super) return false;
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

	public static function view() {
		global $local,$db;
		$output = array('html' => '');
		$args = func_get_args();
		$action = array_shift($args);
		switch($action) {
			case 'login' : return login_widget::view();
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
