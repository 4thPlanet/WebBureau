<?php

/**
 * This class is used to create a "paging" effect.  Pass in SQL to the constructor, and the instance will maintain which
 *
 *
 *
 * */

class paging extends module {

	/* ID, for referencing across the session */
	private $id;

	/* The "base" variables */
	protected $sql;
	protected $params;

	/* Any additional filtering goes here... */
	protected $filter;

	/* Variables related to paging (AKA LIMIT) */
	protected $order_by;
	protected $per_page;
	protected $current_page;
	protected $num_records;

	public function __construct($sql,$params = array()) {
		$id = md5($sql . json_encode($params));

		$this->sql = $sql;
		$this->params = $params;

		$this->filter = array();

		$this->order_by = array();
		$this->per_page = 10;
		$this->current_page = 1;

		$this->id = $id;
		$this->update_record_count();
		$_SESSION[__CLASS__][$this->id] = &$this;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_page_number() {
		return $this->current_page;
	}

	/**
	 * Returns data for the current page
	 * */
	public function get_current_page() {
		global $db;
		$params = $this->params;
		if (!empty($this->filter)) {
			$condition = array();
			foreach($this->filter as $filter)
			{
				$condition[] = "{$filter['column']} {$filter['operator']} ?";
				if (preg_match('/LIKE/',$filter['operator']))
					$filter['value'] = "%{$filter['value']}%";
				array_push($params, array("type" => "s", "value" => $filter['value']));
			}
			$condition = implode(" AND ",$condition);
		} else {
			$condition = "1=1";
		}
		if (!empty($this->order_by)) {
			$order = "ORDER BY 1";
			foreach($this->order_by as $order_column) {
				if (!empty($order_column['column']))
					$order .= ",{$order_column['column']} {$order_column['direction']}";
			}
		} else {
			$order = "";
		}
		$offset = $this->per_page * ($this->current_page - 1);

		$query = "
			SELECT *
			FROM ({$this->sql}) N
			WHERE $condition
			$order
			LIMIT {$this->per_page} OFFSET $offset
		";
		return $db->run_query($query,$params);
	}

	public function get_page_count() {
		return ceil($this->num_records / $this->per_page);
	}

	public function get_record_count() {
		return $this->num_records;
	}

	public function get_per_page() {
		return $this->per_page;
	}

	protected function update_record_count() {
		global $db;
		$params = $this->params;
		if (!empty($this->filter)) {
			$condition = array();
			foreach($this->filter as $filter)
			{
				$condition[] = "{$filter['column']} {$filter['operator']} ?";
				if (preg_match('/LIKE/',$filter['operator']))
					$filter['value'] = "%{$filter['value']}%";
				array_push($params, array("type" => "s", "value" => $filter['value']));
			}
			$condition = implode(" AND ",$condition);
		} else {
			$condition = "1=1";
		}

		$query = "
			SELECT COUNT(*) as numRecords
			FROM ({$this->sql}) N
			WHERE $condition
		";

		$result = $db->run_query($query,$params);
		$this->num_records = $result[0]['numRecords'];
	}

	public function set_per_page($per_page) {
		$this->per_page = (int) $per_page;
		$this->first_page();
	}

	public function goto_page($page) {
		$this->current_page = min(max((int) $page,1),$this->get_page_count());
		return $this;
	}

	public function first_page() {
		$this->current_page = 1;
		return $this;
	}

	public function prev_page() {
		$this->current_page = max($this->current_page - 1, 1);
		return $this;
	}

	public function next_page() {
		$this->current_page = min($this->current_page + 1, $this->get_page_count());
		return $this;
	}

	public function last_page() {
		$this->current_page = $this->get_page_count();
		return $this;
	}

	public function set_filter($filters) {
		$this->filter = array();
		$allowed_filters = static::allowed_filters();
		foreach($filters as $filter)
		{
			if (
				$filter['column'] &&
				isset($allowed_filters[$filter['operator']]) &&
				(
					true
					// need check that filter can be performed on this field...
				)
			)
				array_push($this->filter,$filter);
		}
		$this->update_record_count();
		$this->goto_page(1);
	}

	public function set_order($order)
	{
		$this->order_by = $order;
		$this->goto_page(1);
	}

	public function get_order() {return $this->order_by;}

	public static function allowed_filters() {
		return array(
			'<'			=> array('display_as' => 'LESS THAN', 'type_restriction' => null),
			'<=' 		=> array('display_as' => 'LESS THAN OR EQUAL TO', 'type_restriction' => null),
			'=' 		=> array('display_as' => 'EQUAL TO', 'type_restriction' => null),
			'>='		=> array('display_as' => 'GREATER THAN OR EQUAL TO', 'type_restriction' => null),
			'>'			=> array('display_as' => 'GREATER THAN', 'type_restriction' => null),
			'LIKE'		=> array('display_as' => 'CONTAINS', 'type_restriction' => 'numeric'),
			'NOT LIKE'	=> array('display_as' => 'DOES NOT CONTAIN', 'type_restriction' => 'numeric')
		);
	}

	/**
	 * Load a paging object in one of two ways: by ID or Query/Params.
	 *
	 * @param string id = either a query string or the id assigned to it.
	 * @param bool | array params - When an array, assumes ID is a query, and will first create an id before attempting to find it.
	 *
	 *
	 * */
	public static function load_paging($id,$params = false) {
		if ($params === false) {
			return isset($_SESSION[__CLASS__][$id]) ? $_SESSION[__CLASS__][$id] : false;
		} else {
			return self::load_paging(md5($id . json_encode($params)));
		}
	}


	/* overwrite module methods... */
	public static function install() {
		global $db;
		//Install pagingtable widget...
		$query = "
			INSERT INTO _WIDGETS(MODULE_ID, NAME, FILENAME, CLASS_NAME)
			SELECT M.ID, ?,?,?
			FROM _MODULES M
			LEFT JOIN _WIDGETS W ON M.ID = W.MODULE_ID AND W.NAME = ?
			WHERE M.NAME = ? AND W.ID IS NULL
		";
		$params = array(
			array("type" => "s", "value" => "Paging Table"),
			array("type" => "s", "value" => __DIR__ . "/PagingTable.php"),
			array("type" => "s", "value" => "pagingtable_widget"),
			array("type" => "s", "value" => "Paging Table"),
			array("type" => "s", "value" => "Paging")
		);
		$db->run_query($query,$params);
		return true;
	}
	public static function menu() {return false;}	//no need for it to be used in a menu
}

?>
