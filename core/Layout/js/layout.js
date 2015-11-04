$(function() {
	$('.layout .widgets')
		.sortable({
			group: 'layout',
			connectWith: '.layout .widgets'
		})
		.on('click','.setup',function() {
			var
				$widget = $(this).parent(),
				args = $widget.data('args') || [],
				data = {
					ajax: 'get_widget_setup',
					widget: $widget.attr('name').replace(/^widget-/,"")
				};
			$.post('',data,function(questions){
				var idx,
					$prompt = $('<div />'),
					$label,
					$input,
					option;
				for (idx in questions) {
					$label = $('<label />')
						.text(questions[idx].PROMPT);

					switch (questions[idx].TYPE) {
						case 'select':
							$input = $('<select />')
								.appendTo($label);

							$('<option />')
								.val('')
								.text('Select...')
								.appendTo($input);

							for(option in questions[idx].OPTIONS) {
								$('<option />')
									.val(questions[idx].OPTIONS[option].ID)
									.text(questions[idx].OPTIONS[option].VALUE)
									.prop('selected',typeof args[idx]!=='undefined' && args[idx]==questions[idx].OPTIONS[option].ID)
									.appendTo($input);
							}
							break;
						case 'text':
						default:
							$input = $('<input />')
								.val(typeof args[idx] !== 'undefined' ? args[idx] : '')
								.appendTo($label);
					}

					$label.appendTo($prompt).wrap('<div />');

					$prompt.dialog({
						title: 'Setup Widget',
						modal: true,
						width: '40%',
						buttons: {
							Set: function() {
								var args = [];
								$(this).find(':input').each(function(idx,el) {
									args[idx] = $(this).val();
								});
								$widget.data('args',args);
								$(this).dialog('close');
							},
							Cancel: function() {
								$(this).dialog('close');
							}
						},
						close: function() {
							$prompt.remove();
						}
					});
				}
			},'json');
		})
		.on('click','.remove',function() {
			$(this).parent().remove();
		})
		.on('click','.list',function() {
			var $d,$widget;
			$widget = $(this).parent();
			$d = $('<div />')
				.data({src: $widget});

			$('<p />')
				.text('Restriction Type: ')
				.append(
					$('<select />')
						.attr({
							id: 'restriction-type'
						})
						.append(
							$('<option />')
								.text('Please select...')
								.val('')
						).append(
							$('<option />')
								.text('Blacklist')
								.val(1)
						).append(
							$('<option />')
								.text('Whitelist')
								.val(0)
						)
						.change(function() {
							if ($(this).val()!="") {
								$('#restriction-text').text($(this).find(':selected').text());
								$('#set-restrictions')
									.removeClass("ui-state-disabled")
									.prop('disabled',false);
							} else {
								$('#set-restrictions')
									.addClass("ui-state-disabled")
									.prop('disabled',true);
							}
						}).val($widget.data('restrict-type'))

				)
				.appendTo($d);

			$('<h4 />')
				.html('<span id="restriction-text">Restrict</span>ed Modules:')
				.appendTo($d);

			for(idx in modules) {
				$('<div />')
					.append(
						$('<input />')
							.attr({
								id: 'module_' + idx,
								type: 'checkbox'
							})
							.prop({
								checked: typeof $widget.data('restrict-mods')!=='undefined' && $widget.data('restrict-mods').indexOf(idx) !== -1
								})
							.val(idx)
					)
					.append(
						$('<label />')
							.attr({
								for: 'module_' + idx
							})
							.text(modules[idx].NAME)
					)
					.appendTo($d);
			}

			$d.dialog({
				title: 'Set ' + $(this).parent().text() + ' Widget Restrictions',
				modal: true,
				width: '40%',
				buttons:
				[
					{
						id: 'set-restrictions',
						text: 'Set',
						click: function() {
							/* Save the Whitelist/Blacklist for this widget... */
							/* Get each checked off box... */
							var vals = [];
							$(this).find(':checkbox:checked').each(function() {vals[vals.length] = $(this).val(); });
							var type = $('#restriction-type').val();
							var $widget = $d.data('src');
							$widget.data({
								'restrict-type': type,
								'restrict-mods': vals
							});
							$(this).dialog('close');
						}
					},
					{
						id: 'remove-restrictions',
						text: 'Remove Restrictions',
						click: function() {
							var $widget = $d.data('src');
							$widget.data({
								'restrict-type': null,
								'restrict-mods': null
							});
							$(this).dialog('close');
						}
					},
					{
						id: 'cancel-restrictions',
						text: 'Cancel',
						click: function() {
							$(this).dialog('close');
						}
					}
				],
				close: function () {$d.remove();}
			}).find('#restriction-type').change();
			if ($widget.data('restrict-type')==null) $('#remove-restrictions').remove();
		});
	;
	$('#widget_list .addTo').click(function(e) {
		var $widget,$menu;
		$('#layout_menu').remove();
		$widget = $(e.target).parent()
			.clone()
			.find('.addTo').remove()
			.end()
			.append('<span class="remove" />')
			.append('<span class="list" />');
		$widget.find('.list')
			.attr({title: 'Click here to black/whitelist this widget.'});
		$widget.find('.remove')
			.attr({title: 'Click here to remove this widget.'});
		if ($widget.data('requiresSetup')) {
			$('<button />')
				.text('Setup...')
				.addClass('setup')
				.appendTo($widget);
		}
		$menu = $('<ul id="layout_menu"/>');
		for (idx in areas) {
			$('<li />')
				.html('<a href="#">'+areas[idx]+'</a>')
				.appendTo($menu)
		}
		$menu.insertAfter(e.target)
			.menu({
				position: { my: "left center", at: "right-5 top+5" }
			});
		$menu.on('click','a',function() {
			$area = $(this).text();
			$('.layout.'+$area + ' .widgets').append($widget);
			$widget.find('.setup').click();
			$menu.remove();
			});
	});
	$('body').on('click',function(e) {
		/* If they clicked off the widget list, remove the layout menu... */
		var $widget_list = $(e.target).closest('#widget_list');
		if ($widget_list.length == 0) $('#layout_menu').remove();
		/* If they clicked off the list menu, remove it... */
		var $widget = $(e.target).closest('.layout .widgets li');
		if ($widget.length == 0) $('#list_menu').remove();
	});
	$('#save_layout').click(function() {
		var data = {};
		/* Go through each .layout, save area name and id */
		$('.layout').each(function() {
			/* get the class which is NOT layout */
			var area = $(this).attr('class').replace(/^(layout\s+)|(\s+layout)|(\slayout\s)$/,"");
			data[area] = [];
			$(this).find('.widgets li').each(function() {
				var widget = $(this).attr('name').replace(/^widget-/,"");
				data[area][data[area].length] = {
					id: widget,
					'restrict-type': $(this).data('restrict-type'),
					restrictions: $(this).data('restrict-mods'),
					args: $(this).data('args') || []
				};
			});
			if (data[area].length == 0)
				delete data[area];
		});
		$.post('', {ajax: 'submit-layout', layout: data }, function(resp) {
			if (resp.success) {
				$('<p/>')
					.text('Layout has been saved.')
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
	for (idx in restrictions) {
		for (m in restrictions[idx].mods) {
			restrictions[idx].mods[m] = restrictions[idx].mods[m].toString();
		}
		$('[name=widget-'+restrictions[idx].id+']').data({
			'restrict-type': restrictions[idx].type,
			'restrict-mods': restrictions[idx].mods
		});
	}
});
