$(function() {
	$('#new_meta').on('click',function(e) {
		var $this = $(this),$row = $('<tr />');
		e.preventDefault();
		$('<td />')
			.append(
				$('<input />')
					.attr({
						placeholder: 'Name'
					})
					.addClass('new-meta-name')
			)
			.appendTo($row);

		$('<td />')
			.appendTo($row);

		$('<td />')
			.append(
				$('<textarea />')
			)
			.appendTo($row);
		$row.insertBefore($this.closest('tr'));
	});
	$('form').on('change','.new-meta-name',function() {
		var name = $(this).val(), $textarea = $(this).closest('tr').find('textarea');
		$textarea.attr({
			name: 'meta['+name+']'
		});
	});
	$("textarea.ckeditor").each(function() {
		CKEDITOR.replace($(this).attr("ID"));
	});
});
