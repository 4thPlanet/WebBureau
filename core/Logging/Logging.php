<?php

/**
 * This class is used for any Logging functionality
 * */

class logging extends module {

	public function __construct() {}

	public static function install() {
		global $db;
		$query = "
			CREATE TABLE IF NOT EXISTS _LOG_MESSAGE_TYPES (
				ID int auto_increment,
				NAME varchar(32),
				PRIORITY int,
				PRIMARY KEY (ID),
				UNIQUE(NAME)
			);
		";
		$db->run_query($query);

		$query = "
			INSERT INTO _LOG_MESSAGE_TYPES ( NAME, PRIORITY )
			VALUES (?,?),(?,?),(?,?),(?,?)
			ON DUPLICATE KEY UPDATE ID=ID
		";
		$params = array(
			array("type" => "s", "value" => "info"),
			array("type" => "i", "value" => 10),
			array("type" => "s", "value" => "error"),
			array("type" => "i", "value" => 100),
			array("type" => "s", "value" => "debug"),
			array("type" => "i", "value" => 10),
			array("type" => "s", "value" => "warn"),
			array("type" => "i", "value" => 50),
		);
		$db->run_query($query,$params);

		$query = "
			CREATE TABLE IF NOT EXISTS _LOG(
				ID int auto_increment,
				LOG_DATE datetime,
				LOG_MESSAGE_TYPE_ID int,
				REQUEST_URI varchar(256),
				CLASS varchar(64),
				FUNCTION varchar(128),
				MESSAGE text,
				PRIMARY KEY (ID),
				FOREIGN KEY (LOG_MESSAGE_TYPE_ID) REFERENCES _LOG_MESSAGE_TYPES (ID),
				INDEX(REQUEST_URI),
				INDEX(CLASS,FUNCTION)
			);
		";
		$db->run_query($query);
		$db->trigger('log_date', 'BEFORE INSERT', '_LOG', 'SET NEW.LOG_DATE = IFNULL(NEW.LOG_DATE,NOW())');

		$query = "
			CREATE TABLE IF NOT EXISTS _LOG_BACKTRACE(
				LOG_ID int,
				REQUEST_METHOD varchar(16),
				BACKTRACE text,
				REQUEST text,
				PRIMARY KEY (LOG_ID),
				FOREIGN KEY (LOG_ID) REFERENCES _LOG(ID)
			)
		";
		$db->run_query($query);

		static::set_module_setting('Logging', 'Log Location', 'Database');
		return true;
	}
	public static function view() { return false; }
	public static function menu() { return false; }

	public static function log($type,$msg,$depth=1) {
		$func = static::get_log_method();
		call_user_func_array(array(__CLASS__,$func), array($type,$msg,$depth+2));
	}

	protected static function get_log_method() {
		switch(static::get_module_setting('Logging', 'Log Location')) {
			case 'File':
				return 'log_to_file';
			case 'Database':
				return 'log_to_db';
		}
	}

	protected static function log_to_db($type,$msg,$depth=2) {
		global $db;
		$backtrace = debug_backtrace();

		$query = "
			INSERT INTO _LOG (LOG_MESSAGE_TYPE_ID,REQUEST_URI,CLASS,FUNCTION,MESSAGE)
			VALUES (?,?,?,?,?)
		";
		$params = array(
			array("type" => "s", "value" => static::get_log_type_id($type)),
			array("type" => "s", "value" => $_SERVER['REQUEST_URI']),
			array("type" => "s", "value" => isset($backtrace[$depth]) ? $backtrace[$depth]['class'] : null),
			array("type" => "s", "value" => isset($backtrace[$depth]) ? $backtrace[$depth]['function'] : null),
			array("type" => "s", "value" => $msg),
		);
		$db->run_query($query,$params);
		$query = "
			INSERT INTO _LOG_BACKTRACE (LOG_ID,REQUEST_METHOD,REQUEST,BACKTRACE)
			VALUES (?,?,?,?)
		";
		$params = array(
			array("type" => "i", "value" => $db->get_inserted_id()),
			array("type" => "s", "value" => $_SERVER['REQUEST_METHOD']),
			array("type" => "s", "value" => print_r($_REQUEST,true)),
			array("type" => "s", "value" => print_r($backtrace,true)),
		);
		$db->run_query($query,$params);
	}

	private static function get_log_type_id($type) {
		global $db;
		$query = "
			SELECT ID
			FROM _LOG_MESSAGE_TYPES
			WHERE NAME = ?
		";
		$params = array(
			array("type" => "s", "value" => $type)
		);
		$result = $db->run_query($query,$params);
		if (empty($result)) {
			if (strtolower($type) != 'error') {
				static::log_to_db('error','Invalid Log type ['.$type.'] specified');
			}
			return null;
		}
		else return $result[0]['ID'];
	}

	public static function get_open_logs() {
		global $db;
		$query = "
			SELECT DISTINCT LMT.NAME
			FROM _LOG_MESSAGE_TYPES LMT
			JOIN _LOG L ON LMT.ID = L.LOG_MESSAGE_TYPE_ID
		";
		return utilities::group_numeric_by_key($db->run_query($query),'NAME');
	}

	protected static function log_to_file($type,$msg,$depth=2) {
		//file is __DIR__ / $type.log
		$backtrace = debug_backtrace();
		$full_message = PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ' . $_SERVER['REQUEST_URI'] .  (isset($backtrace[$depth]) ? ' - ' . $backtrace[$depth]['class'] . '::' . $backtrace[$depth]['function'] : ''). ' ==> ' . $msg;
		file_put_contents(__DIR__ . "/logs/$type.log", $full_message, FILE_APPEND);
	}

}

?>