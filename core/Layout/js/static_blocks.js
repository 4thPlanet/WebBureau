$(function() {
	var $form = $('#static-blocks-form'), $new = $form.find('table tr:last').clone();
	$form.find('textarea').ckeditor();

	$form
		.on('click','.save',function() {
			var data = {ajax: 'static_block_edit'}, $this = $(this), $r = $this.closest('tr');
			$.map($r.find(':input').serializeArray(),function(el) {data[el.name] = el.value});
			$.post('',data,function(resp) {
				if (typeof resp === 'number') {
					$r
						.data('id',resp)
						.find(':input').each(function() {
							$(this).attr({
								name: this.name.replace(/\[new\]/,'[' + resp + ']')
							});
						});
					$('<img />')
						.attr({
							src: '/images/icon-delete.png',
							alt: 'Delete'
						})
						.wrap('<a />')
						.parent()
						.addClass('delete')
						.attr({
							href: '#',
							title: 'Click here to delete this block.'
						})
						.insertAfter($this)
						.before(' ');
					$new.clone().insertAfter($r).find('textarea').ckeditor();
				}

				if (resp) {
					$('<p />')
						.text('Block saved.')
						.dialog({
							resizeable: false,
							modal: true,
							title: 'Success!',
							buttons: {
								'OK': function() {
									$(this).dialog('close');
								}
							},
							close: function() {$(this).remove();}
						});
				} else {
					$('<p />')
						.text('There was an issue saving the block. Please try again.')
						.dialog({
							width: '40em',
							resizeable: false,
							modal: true,
							title: 'Error',
							buttons: {
								'OK': function() {
									$(this).dialog('close');
								}
							},
							close: function() {$(this).remove();}
						});
				}
			},'json')
		})
		.on('click','.delete',function(e) {
			e.preventDefault();
			$r = $(this).closest('tr');
			var $d = $('<p />')
				.text('Are you SURE you want to delete the '+$r.find('.identifier').val()+' dialog?')
				.dialog({
					width: '40em',
					resizable: false,
					modal: true,
					title: 'Delete Static Block',
					buttons: {
						'YES' : function() {
							var data = {
								ajax: 'static_block_delete',
								id: $r.data('id')
							};
							$.post('',data,function(resp) {
								if (resp) {
									$r.remove();
								} else {
									$('<p />')
										.text('There was an issue removing the block.  Please ensure the block is not in use in any layouts.')
										.dialog({
											width: '40em',
											resizeable: false,
											modal: true,
											title: 'Error',
											buttons: {
												'OK': function() {
													$(this).dialog('close');
												}
											},
											close: function() {$(this).remove();}
										});
								}
								$d.dialog('close');
							},'json');
						},
						'Cancel': function() {
							$d.dialog('close');
						}
					},
					close: function() {$(this).remove();}
				})
		});
})
