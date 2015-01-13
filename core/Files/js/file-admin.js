$(function() {
	$('.widget.main-content')
		.on('click','#file-edit',function(e) {
			e.preventDefault();
			/* Open a dialog for new file description... */
			var $d = $('<div />')
				.append(
					$('<input />')
						.attr({
							id: 'new-file-description',
							name: 'description'
						})
						.css({width: '100%'})
						.val($('#file-description').text())
				)
				.dialog({
					modal: true,
					width: '50%',
					title: 'Edit File Description',
					buttons: {
						'Save': function() {
							var data = {
								ajax: 'edit',
								description: $('#new-file-description').val()
							};
							$.post('',data,function(resp){
								if (resp.success) {
									$('<p />')
										.text('Description successfully updated.')
										.dialog({
											modal: true
										});
									$('#file-description').text(data.description);
									$d.dialog('close');
								} else {
									$('<p />')
										.text('Unable to save new description.  Please try again later.')
										.dialog({
											modal: true
										});
									$d.dialog('close');
								}
							},'json')
						},
						'Cancel' : function() {$(this).dialog('close');}
					},
					close: function() {$(this).remove();}
				})
			
			
		})
		.on('click','#file-delete',function(e) {
			e.preventDefault();
			/* Confirm the user really wants to delete this file... */
			var $d = $('<p />')
				.text('Are you SURE you want to delete this file?  This action cannot be undone.')
				.dialog({
					modal: true,
					width: '55%',
					title: 'Delete File',
					buttons: {
						'Delete This File': function() {
							var data = { ajax: 'delete' };
							$.post('',data,function(resp){
								if (resp.success) {
									/* Return to Files */
									location.href += '/..';
								} else {
									/* Output error message */
									$('<p />')
										.text(resp.msg)
										.dialog({
											modal: true
										});
									$d.dialog('close');
								}
							},'json');
						},
						'Cancel' : function() {
							$(this).dialog('close');
						}
					},
					close: function() {$(this).remove();}
				})
			
		});
});
