$(function() {
    $('#groups').click(function(e) {
        var $form = $(e.target).closest('form');
		var $d = $('<div class="group-dialog"/>');
		
		for (var idx in groups) {
			if ($form.find('input:hidden[name="groups[]"][value="'+groups[idx]+'"]').length) checked = 'checked'
			else checked = false;
			$('<input />')
				.attr({
					'type' : 'checkbox',
					'value': groups[idx],
					'id' : 'cb_'+idx,
					'checked' : checked
				})
				.appendTo($d)
				.after('<label for="cb_'+idx+'">'+groups[idx]+'</label>')
		}

		$d.dialog({
			modal: true,
			title: 'Edit Group Membership',
			buttons: {
				Save: function() {
					$form.find('[name="groups[]"]').remove();
					$d.find(':checkbox:checked').each(function() {
						$('<input />')
							.attr({
								'type': 'hidden',
								'value': $(this).val(),
								'name': 'groups[]'
							})
							.appendTo($form);
					});
					$d.dialog('close');
				},
				Cancel: function() {
					$d.dialog('close');
				}
			},
			close: function() {$d.remove();}
		});
    });
});
