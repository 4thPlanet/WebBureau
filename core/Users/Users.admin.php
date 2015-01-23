<?php
class users_admin extends users {
	public static function ajax() {}
	
	public static function post($args,$request) {
		list($type,$id) = $args;
		switch($type) {
			case 'Users': return static::user_admin_post($id,$request);
			case 'Groups': return static::group_admin_post($id,$request);
		}		
		return false;
	}
	
	public static function view($type='',$id='') {
		global $db,$local;
		switch ($type) {
			case 'Users' : return static::user_admin($id);
			case 'Groups' : return static::group_admin($id);
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
	
	protected static function user_admin($id) {
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
	
	private static function user_admin_post($id,$request) {
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
	
	protected static function group_admin($id) {
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
	
	protected static function group_admin_post($id,$request) {
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
}
?>
