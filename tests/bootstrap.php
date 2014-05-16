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
		'pro' => TRUE,
		'active_plugins' => array(
			'bcms/bcms.php',
			'go-config/go-config.php',
		),
		'template' => 'gigaom-child4',
		'stylesheet' => 'gigaom-child4',
	);

	// we need to declare this method so nonce checking will work
	function wp_verify_nonce()
	{
		return TRUE;
	}//end verify_nonce

	require_once $bootstrap;

	// make sure the go-config dir is set
	update_option( 'go-config-dir',  '_search' );
	update_option( 'bcms_searchsmart', TRUE );
}//end if
else
{
	exit( "Couldn't find path to wordpress-tests/bootstrap.php\n" );
}//end else
