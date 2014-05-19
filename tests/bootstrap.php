<?php
// Load WordPress test environment
// https://github.com/nb/wordpress-tests
//

// get the path to wordpress-tests' bootstrap.php file from the environment
$bootstrap = getenv( 'WPT_BOOTSTRAP' );
if ( FALSE === $bootstrap )
{
	echo "\n!!! Please set the WPT_BOOTSTRAP env var to point to your\n!!! wordpress-tests/includes/bootstrap.php file.\n\n";
	return;
}

if ( file_exists( $bootstrap ) )
{
	$GLOBALS['wp_tests_options'] = array(
		// set 'pro' to TRUE to use GO pro style WP directory layout.
		// unset it to use standard WP installation layout
		'pro' => TRUE,
		'active_plugins' => array(
			'bcms/bcms.php',
		),
	);

	require_once $bootstrap;

	update_option( 'bcms_searchsmart', TRUE );
}//end if
else
{
	exit( "Couldn't find path to wordpress-tests/bootstrap.php\n" );
}//end else
