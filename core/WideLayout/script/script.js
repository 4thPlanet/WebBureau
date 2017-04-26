

$(function() {
	function observe(mutations,observer) {
		if (window.innerHeight > ($('body').height() + ($('footer').is('.force-down') ? $('footer').height() : 0)))
			$('footer').addClass('force-down');
		else
			$('footer').removeClass('force-down');

		if (typeof mutations !== 'undefined') {
			observer.observe(document, {
				subtree: true,
				childList: true
			});
		}		
	}
	
	if (window.innerHeight > $('body').height())
		$('footer').addClass('force-down');
	else
		$('footer').removeClass('force-down');

	MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

	var observer = new MutationObserver(function(mutations, observer) {
		observe(mutations,observer);
	});
	
	$('img').load(function() {
		observe();
	})

	// define what element should be observed by the observer
	// and what types of mutations trigger the callback
	observer.observe(document, {
	  subtree: true,
	  childList: true
	  //...
	});
});