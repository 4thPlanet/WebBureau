<?php

/**
 * This class is used to generate and maintain a table with paging functionality, taken from the paging class.
 * While *technically* a widget, it can (and should) be used to enhance a module's view method, as opposed to a standalone.
 *
 *
 * */

class pagingtable_widget extends paging implements widget{

	protected $columns;
	protected $options;
	protected $widget_id;

	public function __construct($sql,$params = array()) {
		global $db;
		parent::__construct($sql,$params);
		$query = "
			SELECT W.ID
			FROM _MODULES M
			JOIN _WIDGETS W ON M.ID = W.MODULE_ID
			WHERE M.NAME = ? AND W.NAME = ?
		";
		$params = array(
			array("type" => "s", "value" => "Paging"),
			array("type" => "s", "value" => "Paging Table"),
		);
		$result = $db->run_query($query,$params);
		$this->widget_id = $result[0]['ID'];
	}

	/**
	 * Sets the columns for display.
	 *
	 * @param array $columns - A multidimensional array.  Each element is an array consisting of:
	 * ** column - The actual column alias in the SQL (i.e., "first_name")
	 * ** display_name - How the column will be displayed in the table header (i.e., "First Name")
	 * ** display_function - optional - A callable string.  Function will be provided three parameters, $value, $row, and $this, and output will be the result of this function.  At somepoint in the future, Closures (i.e., Anonymous functions) will be allowed, but right now we can't serialize them for our session object :(
	 * ** display_function_addparams - optional - Array of additional parameters to pass to display_function
	 * ** display_format - optional - If provided, output will be passed through sprintf(display_format,value)
	 * ** nowrap - optional, defaults to true.  When false, text will wrap for this column.
	 *
	 * */
	public function set_columns($columns) {
		$this->columns = $columns;
	}

	public function get_columns() { return $this->columns; }

	/**
	 * Sets any and all options for the table (id, class, style, etc.)
	 *
	 *
	 * */
	public function set_options($options) {
		$this->options = $options;
	}

	/**
	 * Returns the HTML and JS needed for the table to function.
	 * */
	public function build_table() {
		global $local;
		$output = array(
			'html' => '',
			'script' => array(
				"{$local}script/jquery.min.js",
				"{$local}script/jquery-ui.min.js",
				utilities::get_public_location(__DIR__ . '/script/pagingtable.js')
			),
			'css' => array(
				"{$local}style/jquery-ui.css",
				utilities::get_public_location(__DIR__ . '/css/pagingtable.css')
			)
		);

		$attributes = "";
		if (!empty($this->options))
			foreach($this->options as $name => $value)
				$attributes .= "{$name}='".utilities::make_html_safe($value,ENT_QUOTES)."' ";

		$record_num_low = ($this->current_page-1) * $this->per_page + 1;
		$record_num_high = min($this->num_records,$this->current_page * ($this->per_page));
		$one_page = $this->get_page_count() ==1 ? 'class="onepage"' : '';

		$output['html'] .= <<<TABLE
<div class="widget pagingtable" widget-id="{$this->widget_id}" id="{$this->get_id()}">
	<table $attributes >
		<thead>
			<tr>
TABLE;
		foreach($this->columns as $column) {
			$output['html'] .= "<th>{$column['display_name']}</th>";
		}
		$output['html'] .= <<<TABLE
			</tr>
		</thead>
		<tbody>
TABLE;
		$output['html'] .= $this->build_table_body();
		$output['html'] .= <<<TABLE
		</tbody>
	</table>
	<nav {$one_page}>
		<p class="records_shown">Displaying Records <span class="records_low">{$record_num_low}</span> to <span class="records_high">{$record_num_high}</span>.</p>
		<a class="first" href="#">&lt;&lt;</a>
		<a class="prev" href="#">&lt;</a>
		<input class="page_num" value="{$this->current_page}" /> <span>of</span>
		<span class="total_pages">{$this->get_page_count()}</span>
		<a class="next" href="#">&gt;</a>
		<a class="last" href="#">&gt;&gt;</a>
		<p class="records_total"><span class="num_records">{$this->num_records}</span> records total</p>
		<img class="filter" src="{$local}images/icon-filter.png" alt="filter" title="Click here to filter records."/>
	</nav>
</div>
TABLE;

		if (!empty($this->filter)) {
			/* Sanitize for script... */
			$html_filter = array();
			foreach($this->filter as $filter) {
				foreach($this->columns as $idx=>$column) {
					if ($column['column'] == $filter['column']) {
						$filter['column'] = $idx;
						break;
					}
				}

				array_push(
					$html_filter,
					array(
						'pagingtable-filter-column-select' => $filter['column'],
						'pagingtable-filter-operator' => $filter['operator'],
						'pagingtable-filter-value' => $filter['value']
					)
				);
			}
			$output['script'][] = '
			$(function() {
				$("#'.$this->get_id().'").data({
					filters: ' . json_encode($html_filter) . ',
					filtersBackup: ' . json_encode($html_filter) . '
				});
			})';
		}

		return $output;
	}

	/* Returns the current contents of tbody used for table... */
	protected function build_table_body() {
		$data = $this->get_current_page();
		$html = "";
		foreach($data as $row) {
			$html .= "<tr>";
			foreach($this->columns as $column) {
				$val = isset($column['column']) ? $row[$column['column']] : null;
				if (isset($column['display_function']) && is_callable($column['display_function'])){
					$params = array($val,$row);
					if (!empty($column['display_function_add_params'])) $params = array_merge($params,$column['display_function_add_params']);
					$val = call_user_func_array($column['display_function'],$params);
				}
				if (!empty($column['display_format']))
					$val = sprintf($column['display_format'],$val);

				if (isset($column['nowrap']) && $column['nowrap'] == false)
					$class = 'wrap';
				else
					$class = '';
				$html .= "<td class='$class'>$val</td>";
			}
			$html .= "</tr>";
		}
		return $html;
	}

	public static function ajax($request) {
		if (empty($request['pagingtable_id'])) return false;
		$paging = paging::load_paging($request['pagingtable_id']);
		$columns = $paging->get_columns();
		switch($request['ajax']) {
			case 'first':
			case 'prev':
			case 'next':
			case 'last':
				$paging->update_record_count();
				call_user_func(array($paging,"{$request['ajax']}_page")); break;
			case 'goto':
				$paging->update_record_count();
				$paging->goto_page($request['page']); break;
			case 'filter':
				if (empty($request['filters'])) $request['filters'] = array();
				foreach($request['filters'] as &$filter) {
					$filter = array(
						'column' => $columns[$filter['pagingtable-filter-column-select']]['column'],
						'operator' => $filter['pagingtable-filter-operator'],
						'value' => $filter['pagingtable-filter-value']
					);
				}
				$paging->set_filter($request['filters']); break;
			case 'order':
				$paging->set_order(array(array('column' => $columns[$request['column']]['column'], 'direction' => $request['direction']))); break;
			default:
				return false;
		}

		$order_by = null;
		$order = $paging->get_order();
		if (!empty($order)) {
			$order = $order[0];	//We really only care about the first column...
			foreach($columns as $idx=>$column)
				if ($order['column'] == $column['column']) {
					$order_by = array('column' => $idx, 'direction' => $order['direction']);
				}
		}

		return array(
			'records' => $paging->get_record_count(),
			'first_record' => ($paging->get_page_number()-1) * $paging->get_per_page() + 1,
			'last_record' => min($paging->get_record_count(),$paging->get_page_number() * $paging->get_per_page()),
			'page' => $paging->get_page_number(),
			'pages' => $paging->get_page_count(),
			'body' => $paging->build_table_body(),
			'order' => $order_by
		);
	}

	/* overwrite module methods... */
	public static function install() {return true;}	//nothing to do for installation
	public static function menu() {return false;}	//no need for it to be used in a menu
}

?>
