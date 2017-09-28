
<?php
	require_once(__DIR__ . "/Database.php");
	class clientData extends Database {
        protected static $db_pass = "gfds/*gs?\"^&+|";
        protected static $db_server = "localhost";
        protected static $db_name = "Web_Bureau";
        protected static $db_login = "wb_user";
        protected $db_connection;
        public function get_db_name() {
            return $this::$db_name;
        }
    }
?>