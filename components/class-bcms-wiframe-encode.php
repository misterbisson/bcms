<?php

class BCMS_Wiframe_Encode
{
	/**
	 * Usage:
	<script type="text/javascript">
		var bcms_wiframe = {
			url           : 'URL_OF_IFRAME_SOURCE',
			any_variables : 'asdf',
			you_want      : 'bacon',
			to_pass       : 'awesome',
		};

		(function() {
			var src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'URL_TO_JS/bcms-wiframe/';
			document.write( unescape( '%3Cscript src="' + src + '"%3E%3C/script%3E' ) );
		})();
	</script>
	<div id="bcms-wiframe-container"></div>
	 */
	public static function out( $function, $key )
	{
		if ( function_exists( 'status_header' ) )
		{
			status_header( 200 );
		}//end if

		header( 'X-Robots-Tag: noindex, follow', TRUE );

		wp_enqueue_script(
			'jquery-postmessage',
			plugins_url( 'components/js/jquery.ba-postmessage.min.js', __DIR__ ),
			array( 'jquery' ),
			'0.5',
			TRUE
		);

		wp_enqueue_script(
			'bcms-wiframe-notify',
			plugins_url( 'components/js/bcms-wiframe-notify.js', __DIR__ ),
			array( 'go-subscriptions' ),
			1,
			TRUE
		);

		?><!doctype html>
		<html>
			<head>
				<?php wp_head(); ?>
			</head>
			<body <?php body_class( apply_filters( 'bcms_wiframe_body_class', array( 'wiframe' ) ) ); ?>>
				<?php
					// calling the function directly here so all enqueued scripts and styles are done
					// BEFORE the widget is dumped out.  This ensures dependencies are set up appropriately
					call_user_func_array( $function, array( $key ) );
					wp_footer();
				?>
			</body>
		</html>
		<?php
	}//end out
}//end class
