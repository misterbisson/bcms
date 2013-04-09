// define bcms if another script hasn't already done so
if ( typeof bcms == "undefined" ) {
	var bcms = {
		wiframes: 1
	};
}//end if

// define the wiframes variable if it isn't defined
if ( typeof bcms.wiframes == "undefined" ) {
	bcms.wiframes = 1;
}//end if

/**
 * initialize a wiframe
 */
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

	bcms.libraries_loaded();
};

/**
 * check if the needed libraries have loaded.
 */
bcms.libraries_loaded = function() {
	if ( typeof jQuery === "undefined" || typeof jQuery.receiveMessage === "undefined" ) {
		setTimeout( bcms.libraries_loaded, 10 );
		return;
	}//end if

	// once the libraries are loaded, we can render the iframe
	bcms.render_iframe();
};

/**
 * render the iframe
 */
bcms.render_iframe = function() {
	var src = bcms.args.url;
	var the_id = 'bcms-wiframe-' + bcms.wiframes;

	// append a dummy variable so concatenation is easier
	if ( ! src.match( /\?/ ) ) {
		src += '?_';
	}//end if

	// append the arguments to the URL
	for ( var key in bcms.args ) {
		if ( 'url' !== key ) {
			src += '&' + key + '=' + escape( bcms.args[ key ] );
		}//end if
	}//end for

	src = src + '#' + encodeURIComponent( document.location.href );

	// increment the wiframe tracker so our IDs are unique
	bcms.wiframes++;

	var iframe = '%3Ciframe id="' + the_id + '" width="100%" height="300" scrolling="no" frameborder="0" src="' + src + '"%3E%3C/iframe%3E';
	jQuery('#bcms-wiframe-container').html( unescape( iframe ) );

	var $frame = jQuery( '#' + the_id );

	var height = $frame.outerHeight();

	// watch for messages from the iframe to adjust the height
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
