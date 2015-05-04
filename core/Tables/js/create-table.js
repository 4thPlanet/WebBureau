$(function() {
	var validRegex = /^\d*[a-zA-Z]\w*$/;
	var $selectType = 
		$('<select />')
			.addClass('field-type')
			.append(
			'<option value="">Field Type</option>' + 
			'<option value="int">int</option>' + 
			'<option value="float">float</option>' + 
			'<option value="money">money</option>' +
			'<option value="date">date</option>' +
			'<option value="datetime">datetime</option>' +
			'<option value="varchar">varchar</option>' +
			'<option value="text">text</option>'
			);
	$('#new-table-form')
		.on('click','#add-new-field',function(e) {
			var $tr = $('<tr />');
			
			$('<td />')
				.append(
					$('<img />')
						.addClass('delete')
						.attr({
							title: 'Click here to delete this field.',
							alt: 'Delete Field',
							src: "/images/icon-delete.png"
						})
					)
				.appendTo($tr);
			
			$('<td />')
				.append($('<input />').addClass('field-name').attr({placeholder: 'Field Name'}))
				.appendTo($tr);
				
			$('<td />')
				.append($selectType.clone())
				.appendTo($tr);
				
			$('<td />')
				.append(buildOtherExtraCell())
				.appendTo($tr);
				
			
			$tr.insertBefore($(this).closest('tr'));
		}).on('click','img.delete',function() {
			$(this).closest('tr').remove();
			if ($('#new-table-form .field-name').length == 0)
				$('#add-new-field').click();
		}).on('change','.field-name',function() {
			// confirm valid field name and set name of other inputs to this
			var $this = $(this), name = $this.val();
			if (name==="" || name.match(validRegex)) {
				$this.data('val',name);
				$this.closest('tr').find(':input').not(this).each(function() {
					$(this).attr('name',name==="" ? "" : 'fields[' + name + '][' + $(this).attr('class') + ']');
				});
			} else {
				alert("Invalid field name.  Field names may only contain letters, numbers, and underscores, and must contain at least one letter.");
				$this.val($this.data('val'));
			}
		}).on('change','.field-type',function() {
			var type = $(this).val(), $extra = $(this).closest('td').next();
			switch(type) {
				case 'int':
					$extra.html(buildIntExtraCell());
					break;
				case 'varchar':
					$extra.html(buildVarcharExtraCell());
					break;
				default:
					$extra.html(buildOtherExtraCell());
					break;
			}
			$extra.closest('tr').find('.field-name').trigger('change');
		}).on('change','#table-name',function() {
			var $this = $(this), name = $this.val();
			if (name==="" || name.match(validRegex)) {
				if (name=="") {
					return;
				} else if (tables.indexOf(name)!=-1) {
					alert("Table name already exists.");
					$this.val("");
				} else {
					$.post('',{ajax: 'does-table-exist', table_name: name}, function(resp) {
						if (resp) {
							tables.push(name);
							alert("Table name already exists.");
							$this.val("");
						} 
					}, 'json');
				}
			} else {
				alert("Invalid table name.  Table names may only contain letters, numbers, and underscores, and must contain at least one letter.");
				$this.val("");
			}
		}).on('change','.auto-increment',function() {
			if ($(this).is(':checked')) {
				$(this).closest('td').find('.is-primary-key,.not-null').prop('checked',true)
			}
		}).on('change','.is_foreign_key',function() {
			var checked = $(this).is(':checked'), $select = $(this).parent().siblings('.reference-table');
			if (!checked) {
				$select.hide();
				return;
			} 
			
			if ($select.length ==0) {
				$select = $('<select />')
					.addClass('reference-table')
					.attr({
						name: 'fields[' + $(this).closest('tr').find('.field-name').val()+'][reference-table]'
					});
				$('<option />')
					.val('')
					.text('References...')
					.appendTo($select);
				for (idx in tables)
					$('<option />')
						.val(tables[idx])
						.text(tables[idx])
						.appendTo($select);
				$select.insertAfter($(this).closest('label'));
			}
			
			$select.show();
		}).on('blur change input', ':input:not(:checkbox,:button)',function() {
			//Confirm value present...
			if (this.value == "" && $(this).is(':visible')) $(this).addClass('required-missing');
			else {
				$(this).removeClass('required-missing');//.closest('tr').find('.field-name').trigger('change');
				
			}
		}).on('submit',function(e) {
			$(this).find(':input').blur();
			if ($('.required-missing').length)
				e.preventDefault();
		});
		$('#add-new-field').trigger('click');
	
	function buildIntExtraCell() {
		return buildOtherExtraCell() + "<label><input class='auto-increment' type='checkbox' value='AUTO_INCREMENT' /> Is Auto Increment?</label><label><input class='is_foreign_key' type='checkbox' value='1' /> Is Foreign Key?</label>" ;
	}
	function buildVarcharExtraCell() {
		return buildOtherExtraCell() + "<label>Max Length: <input class='max-length' size='2' placeholder='XXXX'/></label>";
	}
	function buildOtherExtraCell() {
		return "<label><input class='is-primary-key' type='checkbox' value='1' /> Primary Key?</label><label><input class='not-null' type='checkbox' value='NOT NULL' /> Prevent NULLs?</label> ";
	}
});
