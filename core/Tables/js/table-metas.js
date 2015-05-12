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
	$('form')
		.on("click", ".null", function() {
			var $this = $(this), $el = $this.closest("tr").find(":input:not(.null)");
			if ($this.not(":checked").length) return;
			$el.val("");
			if ($el.is(".ckeditor"))
				CKEDITOR.instances[$el.attr("name")].setData("");

		})
		.on("input", ":input:not(.null)", function() {
			if ($(this).val() == "") return;
			$(this).closest("tr").find("input.null").prop("checked",false);
		})
		.on('change','.new-meta-name',function() {
			var name = $(this).val(), $textarea = $(this).closest('tr').find('textarea');
			$textarea.attr({
				name: 'meta['+name+']'
			});
		});
	$("textarea.ckeditor").each(function() {
		var id = $(this).attr('id');
		CKEDITOR.replace(id);

		CKEDITOR.instances[id].on('change',function(e) {
			if (e.editor.getData()==="")
				e.editor.updateElement();
		});
	});
});
