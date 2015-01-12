$(function() {
	$(document).on('click','.assign-right button',function(e){
		e.preventDefault();
		var $d = $('<div />');
		var $right = $(this);
		
		$('<h3 />')
			.text($right.text())
			.appendTo($d);
		$('<p />')
			.text($right.attr('title'))
			.appendTo($d);
		$('<p />')
			.text('Assign right to...')
			.appendTo($d);
			
		for (g in groups) {
			$group = 
				$('<div />')
					.appendTo($d);
			
			$('<input />')
				.attr({
					id: "group_" + g,
					type: 'checkbox',
					value: g
				})
				.appendTo($group);
			$('<label />')
				.attr({
					for: "group_" + g
				})
				.text(groups[g].NAME)
				.appendTo($group);
		}
		$d
			.data({
				module: $right.attr('module'),
				type: $right.attr('type'),
				right: $right.attr('right'),
				description: $right.attr('title'),
				btn: $right
			})
			.dialog({
				title: 'Assign New Right',
				width: '80%',
				modal: true,
				buttons: {
					Save: function() {
						/* Function to Save/Assign Right goes here...*/
						var groups = [];
						$(this).find(':checked').each(function() {groups[groups.length] = $(this).val();});
						
						var data = {
							module: $d.data('module'),
							type:$d.data('type'),
							right: $d.data('right'),
							description: $d.data('description'),
							groups: groups,
							ajax: 'Assign Right'
						};
						$.post('',data,function(resp){
							if (resp.success) {
								$d.data('btn').closest('li').remove();
								
								$d.dialog('close');
							}
						},'json')
					},
					Cancel: function() {$(this).dialog('close');}
				},
				close: function() {$(this).remove();}
			});
	});
});
