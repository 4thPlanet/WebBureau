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
		var $src = $(this);
		var $d = $('<div />')
			.data('src',$src);

		$('<p />')
			.text('Please enter the text for this menu item:')
			.appendTo($d);

		$('<input />')
			.attr({
				id: 'menu-text'
			})
			.css({
				width: '100%'
			})
			.val($src.contents().eq(0).text())
			.appendTo($d);

		$d.dialog({
			modal: true,
			title: 'Menu Item Text',
			width: 500,
			buttons: {
				Set: function() {
					/* Set the menu text... */
					$d.data('src')
						.find(' > .menu-text')
						.text($('#menu-text').val());
					$d.remove();
				},
				Cancel: function() {$(this).dialog('close');}
			},
			close: function() {
				$d.remove();
			}
		});

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
	var idx = 0;
	$menu.children('li').each(function() {
		var $this = $(this);
		var text = $this.find('> .menu-text').text();
		obj[idx] = {
			args: ($this.attr('args') && $.parseJSON($this.attr('args'))) || [],
			module: $this.attr('module') || null,
			right: $this.attr('right') || null,
			text: text
			};
		if ($this.find('ul li').length)
			obj[idx].submenu = menu_to_object($this.children('ul'));
		idx++;
	});
	return obj;
}
