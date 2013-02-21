jQuery(function( $ ) {
	var bcms_wiframe_parent_url = decodeURIComponent( document.location.hash.replace( /^#/, '' ) );
	var bcms_wiframe_height = 0;

	function bcms_wiframe_height_notify() {
		var height = $('body').outerHeight( true );

		if ( height === bcms_wiframe_height ) {
			return;
		}//end if

		bcms_wiframe_height = height;

		$.postMessage({ bcms_wiframe_height: height }, bcms_wiframe_parent_url, parent );
	}//end bcms_wiframe_height_notify

	$(window).load( bcms_wiframe_height_notify );

	// Watch for ajax events.  When they occur, notify if needed but after a short delay
	$(document).ajaxComplete( function() {
		setTimeout( function() {
			bcms_wiframe_height_notify();
		}, 300 );
	});
});
