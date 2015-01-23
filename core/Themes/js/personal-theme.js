$(function() {
	$('#personal-theme').on('submit',function(e) {
		e.preventDefault();
		var data = {
			ajax: 'set-theme',
			widget_id: $(this).closest('.widget').attr('widget-id'),
			theme: $(this).find('[name=theme]').val()
		};
		$.post('',data,function(resp) {
			if (resp.success) location.reload();
			else alert('There was a problem loading your selected theme.  Please try again later.');
		}, 'json')
	})
});
