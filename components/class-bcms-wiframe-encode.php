<?php

class BCMS_Wiframe_Encode
{
	public static function out( $function, $key )
	{
		if ( function_exists( 'status_header' ) )
		{
			status_header( 200 );
		}//end if

		header( 'X-Robots-Tag: noindex', TRUE );

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
