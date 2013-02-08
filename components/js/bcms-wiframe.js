if ( typeof bcms == "undefined" ) {
	var bcms = {
		wiframes: 1
	};
}//end if

bcms.wiframe = function( args ) {
	bcms.args = args;

	// include jQuery if we need to
	if ( typeof jQuery === "undefined" ) {
		document.write( unescape( '%3Cscript src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"%3E%3C/script%3E' ) );
	}//end if

	// include jQuery postMessage if we need to
	if (  typeof jQuery === "undefined" || typeof jQuery.receiveMessage === "undefined" ) {
		document.write( unescape( '%3Cscript src="' + bcms.args.url.replace( /wiframe\/.*/, '') + 'wp-content/plugins/bcms/components/js/jquery.ba-postmessage.min.js"%3E%3C/script%3E' ) );
	}//end if

	bcms.load_libraries();
};

bcms.load_libraries = function() {
	if ( typeof jQuery === "undefined" || typeof jQuery.receiveMessage === "undefined" ) {
		setTimeout( bcms.load_libraries, 10 );
		return;
	}//end if

	bcms.render_iframe();
};

bcms.render_iframe = function() {
	var src = bcms.args.url;
	var the_id = 'bcms-wiframe-' + bcms.wiframes;

	if ( ! src.match( /\?/ ) ) {
		src += '?_';
	}//end if

	for ( var key in bcms.args ) {
		src += '&' + key + '=' + escape( bcms.args[ key ] );
	}//end for

	src = src + '#' + encodeURIComponent( document.location.href );

	bcms.wiframes++;

	var iframe = '%3Ciframe id="' + the_id + '" width="100%" height="300" scrolling="no" frameborder="0" src="' + src + '"%3E%3C/iframe%3E';
	document.write( unescape( iframe ) );

	var $frame = jQuery( '#' + the_id );

	var height = $frame.outerHeight();

	jQuery.receiveMessage(function(e){
		// Get the height from the passsed data.
		var h = Number( e.data.replace( /.*bcms_wiframe_height=(\d+)(?:&|$)/, '$1' ) );

		if ( !isNaN( h ) && h > 0 && h !== height ) {
			// Height has changed, update the iframe.
			$frame.height( height = h );
		}
	});
};

bcms.wiframe( bcms_wiframe );
