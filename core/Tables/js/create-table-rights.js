$(function() {
	$(document).on('click','.assign-rights button', function(e) {
		e.preventDefault();
		/* Pop up a dialog displaying the right name, description, and a list of all available groups (taken from the groups var)*/
		var $b,$d,table,$section,rights;
		$b = $(this);
		$d = 
			$('<div />')
				.data({table: $b.val()});
		
		table = $b.text();
		$('<h3 />')
			.text(table)
			.appendTo($d);
			
		$('<p />')
			.text('The following rights will be created.  Please select which users should be given these rights.')
			.appendTo($d);
		
		rights = ['Add','Edit','Delete','View'];
		
		for (i in rights) {
			$section = $('<section />');
			$('<h4 />')
				.text(rights[i])
				.appendTo($section);
			for (g in groups) {
				$('<input />')
					.attr({
						name: rights[i] + "_groups[]",
						id: rights[i] + "_" + g,
						type: 'checkbox',
						value: g
					})
					.appendTo($section);
				$('<label />')
					.attr({
						for: rights[i] + "_" + g
					})
					.text(groups[g].NAME)
					.appendTo($section);
				$('<div />')
					.addClass('clear')
					.appendTo($section);
					
			}
			
			$section.appendTo($d).css({width: '25%', float:'left'});
		}
		
		$d.dialog({
				modal: true,
				width: '80%',
				buttons: {
					Save: function() {
						/* TODO: Make AJAX call to create rights and assign based on selection... */
						var data = {
							ajax: 'Assign Rights',
							table: $d.data('table'),
						};
						$d.find(':checked').each(function() {
							var name = $(this).attr('name');
							var arr = false;
							if (name.match(/\[\]$/)) {
								name = name.substr(0,name.length-2);
								arr = true;
							}
							if (arr == true && !data[name]) data[name] = [];
							data[name][data[name].length] = $(this).val();
						});
						$.post('',data,function(resp){
							if (resp.success) {
								$('.assign-rights button[value="'+$d.data('table')+'"]').closest('li').remove();
								$('<p />')
									.text('Rights for this table have been created and assigned.  Please refresh the page to see the changes take affect.')
									.dialog({
										title: 'Rights Assigned',
										buttons: {
											'OK' : function() {$(this).dialog('close');}
										}
									})
								
								
								$d.dialog('close');
							}
						},'json');
					},
					Cancel: function() {$d.dialog('close')}
					},
				close: function() {$d.remove();}
			});
	});
});
