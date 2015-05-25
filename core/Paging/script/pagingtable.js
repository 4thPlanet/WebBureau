$(function() {
	function updateTable($table) {
		return function(data) {
			$table.find('tbody').html(data.body);
			$table.find('input.page_num').val(data.page);
			$table.find('nav span.total_pages').text(data.pages);
			$table.find('p.records_total span.num_records').text(data.records);
			$table.find('p.records_shown span.records_low').text(data.first_record);
			$table.find('p.records_shown span.records_high').text(data.last_record);

			$table.find('th').removeClass('asc').removeClass('desc');
			if (data.order) {
				$table.find('th:eq('+data.order.column+')').addClass(data.order.direction.toLowerCase());
			}
			if (data.pages ==1 ) $table.find('nav').addClass('onepage');
			else $table.find('nav').removeClass('onepage');

		};
	}

	$('.pagingtable')
		.on('click','a.first,a.prev,a.next,a.last',function(e) {
			e.preventDefault();
			var $pagingtable = $(this).closest('.pagingtable');
			var data = {
				ajax: $(this).attr('class'),
				widget_id: $pagingtable.attr('widget-id'),
				pagingtable_id: $pagingtable.attr('id')
			};
			$.post('',data,updateTable($pagingtable),'json');
		})
		.on('input','input.page_num',function(e) {
			e.preventDefault();
			var $input = $(this),page = $(this).val();
			if (page == "") return;
			if (!page.match(/^\d+$/)) {
				$input.val($input.data('val'));
				return;
			}
			$input.data('val',page);
			var $pagingtable = $(this).closest('.pagingtable');
			var data = {
				ajax: 'goto',
				page: page,
				widget_id: $pagingtable.attr('widget-id'),
				pagingtable_id: $pagingtable.attr('id')
			};
			$.post('',data,updateTable($pagingtable),'json');
		})
		.on('click','th',function(e) {
			var
				$this = $(this), $pagingtable = $this.closest('.pagingtable'),
				data = {
					ajax: 'order',
					widget_id: $pagingtable.attr('widget-id'),
					pagingtable_id: $pagingtable.attr('id'),
					column: $this.index(),
					direction: $this.is('.asc') ? 'DESC' : 'ASC'
				};
				$.post('',data,updateTable($pagingtable),'json');
		})
		.on('filter',function(e) {
			var
				$this = $(this),
				data = {
					ajax: 'filter',
					filters: $this.data('filters'),
					widget_id: $this.attr('widget-id'),
					pagingtable_id: $this.attr('id')
				};
			$.post('',data,updateTable($this),'json');
		})
		.on('click','img.filter',function() {
			var
				$this = $(this),
				$pagingtable = $this.closest('.pagingtable'),
				$columns = $pagingtable.find('th'),
				$d = $('<div />'),
				$filter_select = $('<div />'),
				$existing_filter = $('<div />'),
				$s = $('<select />'),
				$l = $('<label />'),
				$ol = $('#pagingtable-filter-list');


			$filter_select
				.attr({
					id: 'pagingtable-filter-select'
				})
				.on('blur change',':input:not(:button)',function() {
					var $this = $(this);
					if ($this.val()=="") $this.addClass('required-missing');
					else $this.removeClass('required-missing');
				})
				.appendTo($d);

			$('<h3 />')
				.text('Add Filter')
				.appendTo($filter_select);

			$l
				.attr({
					for: 'pagingtable-filter-column-select'
				})
				.text('Column:')
				.appendTo($filter_select);
			$s
				.attr({
					id: 'pagingtable-filter-column-select'
				})
				.appendTo($filter_select);

			$('<option />')
				.val('')
				.text('Please select...')
				.appendTo($s);

			$columns.each(function() {
				var $this = $(this);
				$('<option />')
					.val($this.index())
					.text($this.text())
					.appendTo($s);

			})

			$l = $('<label />')
				.attr({
					for: 'pagingtable-filter-operator'
				})
				.text('Operator:')
				.appendTo($filter_select);

			$s = $('<select />')
				.attr({
					id: 'pagingtable-filter-operator'
				})
				.appendTo($filter_select);

			$('<option />')
				.val('')
				.text('Please select...')
				.appendTo($s);

			var ops = {
				'<': 'LESS THAN',
				'<=': 'LESS THAN OR EQUAL TO',
				'=': 'EQUAL TO',
				'>=': 'GREATER THAN OR EQUAL TO',
				'>' : 'GREATER THAN',
				'LIKE' : 'CONTAINS',
				'NOT LIKE' : 'DOES NOT CONTAIN'
			};
			for (var op in ops)
				$('<option />')
					.val(op)
					.text(ops[op])
					.appendTo($s);

			$l = $('<label />')
				.attr({
					for: 'pagingtable-filter-value'
				})
				.text('Value:')
				.appendTo($filter_select);

			$('<input />')
				.attr({
					id: 'pagingtable-filter-value',
					placeholder: 'Value'
				})
				.appendTo($filter_select);

			$('<button />')
				.attr({
					id: 'pagingtable-filter-add'
				})
				.text('Add Filter')
				.on('click',function(e) {
					// Confirm all fields have value
					var
						$this = $(this),
						this_filter = {},
						$inputs = $this.siblings(':input').blur().each(function() {
							this_filter[$(this).attr('id')] = $(this).val();
							}),
						filters = $pagingtable.data('filters') || [];
					if ($inputs.filter('.required-missing').length) {
						e.preventDefault();
						return;
					}

					filters.push(this_filter);
					$pagingtable.data('filters',filters);
					$('#pagingtable-filter-existing').trigger('refresh');
				})
				.appendTo($filter_select);

			$existing_filter
				.attr({
					id: 'pagingtable-filter-existing'
				})
				.on('click','img.filter-remove',function(e){
					var $el = $(this).closest('li'), idx = $el.index(), filters = $pagingtable.data('filters');
					$el.remove();
					filters.splice(idx,1);
				})
				.on('refresh',function(e){
					var idx, $ol = $('#pagingtable-filter-list').empty(), filters = $pagingtable.data('filters'), text;
					for (idx in filters) {
						text =
							$('#pagingtable-filter-column-select option[value='+filters[idx]['pagingtable-filter-column-select']+']').text() + ' ' +
							$('#pagingtable-filter-operator option[value="'+filters[idx]['pagingtable-filter-operator']+'"]').text() + ' ' +
							filters[idx]['pagingtable-filter-value'];
						$img = $('<img />')
							.attr({
								src: '/images/icon-delete.png'
							})
							.addClass('filter-remove');
						$('<li />')
							.append($img)
							.append(' ' + text)
							.appendTo($ol);
					}
				})
				.appendTo($d);

			$('<h3 />')
				.text('Existing Filters')
				.appendTo($existing_filter);

			if (!$ol.length)
				$ol = $('<ol />')
					.attr({
						id: 'pagingtable-filter-list'
					});
			$ol
				.appendTo($existing_filter);

			$d.dialog({
				title: 'Filter Table Data',
				modal: true,
				width: '50%',
				buttons: {
					OK: function() {
						$pagingtable.data({
							filtersBackup: $pagingtable.data('filters').slice(0)
						}).trigger('filter');
						$(this).dialog('close');
					},
					Cancel: function() {
						$(this).dialog('close');
					},
					Clear: function() {
						$pagingtable.data({
							filters: null,
							filtersBackup: null
						}).trigger('filter');
						$(this).dialog('close');
					}
				},
				close: function() {
					$pagingtable.data({
						filters: $pagingtable.data('filtersBackup').slice(0) || null
					});
					$(this).remove();
				}
			});

			$existing_filter.trigger('refresh');
		});


})
