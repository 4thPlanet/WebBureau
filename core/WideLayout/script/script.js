$(function() {
	if (window.innerHeight > $('body').height())
		$('footer').addClass('force-down');
	else
		$('footer').removeClass('force-down');

	MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

	var observer = new MutationObserver(function(mutations, observer) {
		// fired when a mutation occurs
		if (window.innerHeight > ($('body').height() + ($('footer').is('.force-down') ? $('footer').height() : 0)))
			$('footer').addClass('force-down');
		else
			$('footer').removeClass('force-down');

		observer.observe(document, {
		  subtree: true,
		  childList: true
		  //...
		});
		// ...
	});

	// define what element should be observed by the observer
	// and what types of mutations trigger the callback
	observer.observe(document, {
	  subtree: true,
	  childList: true
	  //...
	});
})
