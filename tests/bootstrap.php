<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

// set the options to activate the plugin
passthru("wp option add 'bcms_searchsmart' '1'");

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {

	// activating bCMS' keyword indexing features
	update_option( 'bcms_searchsmart', '1' );

	// including the plugin
	require dirname( __FILE__ ) . '/../bcms.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

