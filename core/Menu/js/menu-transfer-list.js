var t;
$(function() {
	/* Toggle Show/Hide for Modules... */
	$('body').on('click','h4 .toggle',function() {
		var $toggle = $(this);
		var $div = $(this).parent().next().toggle(250, function() {
			if ($(this).is(':visible')) $toggle.text('-');
			else $toggle.text('+');
		});
	});
	$('html').on('click',':not(#modules,#menu)',function(e) {
		var time = new Date();
		if (time-t < 100) return;
		t = time;
		if ($('#modules,#menu').has(e.target).length) return;
		$('#menu .selected').removeClass('selected');
	});
	/* Add an item to the menu... */
	$('.module').on('click','span.to-menu',function() {
		var $selected = $('#menu .selected').length ? $('#menu .selected>ul') : $('#menu>ul');
		var module = $(this).closest('.module').attr('module');
		var $item = $(this).parent().clone()
			.find('.to-menu').remove().end()
			.appendTo($selected);
		$('<span />')
			.addClass('remove')
			.text('x')
			.appendTo($item.find('li').addBack());
		$item.find('li').addBack().attr('module',module).not(':has(ul)').append('<ul />');
	});
	/* Toggle the selection of a menu item... */
	$('#menu').on('click','li',function(e) {
		var time = new Date();
		if (time - t < 100) return;
		t = time;
		
		$('#menu .selected').removeClass('selected');
		$(this).addClass('selected');
	});
	$('#menu').on('dblclick','li',function(e) {
		alert("At this point a dialog should appear prompting you to enter the new text for this menu item!!");
	});
	/* Make it all sortable... */
	$('#menu>ul').sortable();
	/* Remove functionality */
	$('#menu').on('click','li .remove', function() {
		$(this).parent().hide('slow', function() {$(this).remove();} );
	});
	$('#save').on('click',function() {
		if ($('#menu>ul li').length==0) {
			$('<p />')
				.text('There must be at least one item on the menu!')
				.dialog({
					buttons: {
						'OK' : function() {$(this).dialog('close'); }
					}
				});
			return;
		}
		var data = menu_to_object($('#menu>ul'));
		data['ajax'] = 'save-menu';
		$.post('',data,function(resp) {
			if (resp.success) {
				$('<p/>')
					.text('Menu has been saved.')
					.dialog({
						buttons: {
							'OK' : function() {$(this).dialog('close');}
							}
						});
			}
			else {
				$('<p />')
					.html(resp)
					.addClass('error')
					.dialog({
						buttons: {
							'OK' : function() {$(this).dialog('close');}
						}
					});
			}
		},'json');
	});
});

/* converts the menu to an object recursively*/
function menu_to_object($menu) {
	var obj = {};
	$menu.children('li').each(function() {
		var $this = $(this);
		var text = $this[0].childNodes[0].nodeValue.trim();
		obj[text] = {
			args: $.parseJSON($this.attr('args')),
			module: $this.attr('module'),
			right: $this.attr('right')
			};
		if ($this.find('ul li').length)
			obj[text].submenu = menu_to_object($this.children('ul'));
	});
	return obj;
}
