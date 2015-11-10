$(function() {
	var $transparentBackground = $('<div />')
			.attr({
				id: 'dragdrop_cover'
			})
			.hide()
			.appendTo('body');

	$('#dragdrop_files').on('mousedown','li',function(e) {
		var $file = $(this),
			$copy = $file.clone()
				.addClass('dragdrop_fileClone')
				.css({
					left: e.pageX,
					top: e.pageY
				})
				.appendTo('body');
		$('body').on('mouseup.dragdrop_files',function(e) {
			var
				toAdd,
				$target,
				posToAdd,
				sel,selLength
			;
			$transparentBackground.hide();
			$copy.remove();
			$target = $(document.elementFromPoint(e.pageX-window.scrollX,e.pageY-window.scrollY))
			$(this).off('mouseup.dragdrop_files');
			if ($target.is('input:not(:checkbox,:radio),textarea,.cke_wysiwyg_frame'))
			{
				switch($file.data('tag'))
				{
					case 'img':
						toAdd = $('<img />')
							.attr({
								src: $file.data('location')
							})
							.prop('outerHTML');
						break;
					case 'a':
						toAdd = $('<a />')
							.attr({
								href: $file.data('location')
							})
							.text($file.text())
							.prop('outerHTML');
						break;
				}
				if ($target.is('.cke_wysiwyg_frame')) {
					// Find the iFrame...
					for (instance in CKEDITOR.instances) {
						if ($target.is(CKEDITOR.instances[instance].window.getFrame().$)) {
							CKEDITOR.instances[instance].insertHtml(toAdd);
						}
					}
				} else if ('selectionEnd' in $target[0]) {
					posToAdd = $target[0].selectionEnd;
				} else if ('selection' in document) {
					$target[0].focus();
					Sel = document.selection.createRange();
					selLength = document.selection.createRange().text.length;
					Sel.moveStart('character', -$target.val().length);
					posToAdd = Sel.text.length - selLength;
				}
				// place file in the input/textarea...
				$target.val($target.val().substr(0,posToAdd) + toAdd + $target.val().substr(posToAdd));
			}
		}).on('mousemove',function(e) {
			$copy.css({
				left: e.pageX,
				top: e.pageY
			});
		});

		$transparentBackground.show();
	})
})
