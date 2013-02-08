jQuery(function( $ ) {
	$(window).load( function() {
		var parent_url = decodeURIComponent( document.location.hash.replace( /^#/, '' ) );

		$.postMessage({ bcms_wiframe_height: $('body').outerHeight( true ) }, parent_url, parent );
	});
});
